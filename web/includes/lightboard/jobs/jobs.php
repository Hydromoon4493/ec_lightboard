
<?php

/*
 * Author: Sam Vossen 2026.11.17
 * Filename: includes/lightboard/jobs/jobs.php
 *
 * Functions for creating and managing Lightboard display jobs.
 *
 * The website generates the complete Arduino sketch and stores it
 * in display_jobs.sketch. The Raspberry Pi worker retrieves that
 * sketch, compiles it and uploads it to the ESP32.
 *
 * Scheduling:
 * - Normal requests are remembered as the latest normal display.
 * - Approved schedules take priority while they are active.
 * - Normal requests made during a schedule remain pending.
 * - When a schedule ends, the latest normal display is restored.
 *
 * The Raspberry Pi worker does not need to know about schedules.
 * Schedule priority is handled by claimNextJob().
 */

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/images.php';
require_once __DIR__ . '/../hardware/board.php';


/* ============================================================
 * JOB TIMEOUT SETTINGS
 * ============================================================ */

const JOB_PENDING_TTL_MINUTES = 10;
const JOB_CLAIM_TTL_MINUTES = 5;

const LIGHTBOARD_LOCK_NAME = 'lightboard_jobs';


/* ============================================================
 * SCHEDULE TIME HELPER
 * ============================================================ */

/*
 * Get the current Eastern time for schedule comparisons.
 *
 * The schedule table stores Eastern local datetime values.
 * MySQL NOW() uses the hosting server's timezone, which is UTC.
 *
 * Using PHP's America/New_York timezone ensures schedule
 * comparisons remain correct during daylight saving changes.
 */
function lightboardScheduleNow(): string
{
    $timezone = new DateTimeZone('America/New_York');

    return (new DateTimeImmutable('now', $timezone))
        ->format('Y-m-d H:i:s');
}


/* ============================================================
 * LOCK HELPERS
 * ============================================================ */

function acquireLightboardLock(PDO $pdo): void
{
    $result = $pdo->query(
        "SELECT GET_LOCK('" . LIGHTBOARD_LOCK_NAME . "', 10)"
    )->fetchColumn();

    if ((int)$result !== 1) {
        throw new RuntimeException(
            'The Lightboard job queue is busy. Please try again.'
        );
    }
}


function releaseLightboardLock(PDO $pdo): void
{
    $pdo->query(
        "SELECT RELEASE_LOCK('" . LIGHTBOARD_LOCK_NAME . "')"
    );
}


/* ============================================================
 * BUILD DISPLAY SKETCH
 * ============================================================
 *
 * Generates the Arduino sketch for either a static or animated
 * custom/preset display.
 *
 * This is shared by normal requests and scheduled displays.
 */

function buildDisplaySketch(
    string $key,
    int $id,
    int $brightness
): string {

    src($key);

    $brightness = max(1, min(100, $brightness));

    $row = getImageForDisplay($key, $id);

    if (!$row) {
        throw new RuntimeException('Image not found.');
    }

    $isAnimated = false;

    if ($key === 'c') {

        $check = db()->prepare(
            'SELECT isAnimated
             FROM customDisplay
             WHERE cDisplayID = ?'
        );

        $check->execute([$id]);

        $isAnimated = (bool)$check->fetchColumn();

    } elseif ($key === 'p') {

        $check = db()->prepare(
            'SELECT isAnimated
             FROM presetDisplay
             WHERE pDisplayID = ?'
        );

        $check->execute([$id]);

        $isAnimated = (bool)$check->fetchColumn();
    }

    if ($isAnimated) {

        if ($key === 'c') {

            $frameStmt = db()->prepare(
                'SELECT frameNumber, pixel_map, delayMs
                 FROM customDisplayFrames
                 WHERE displayID = ?
                 ORDER BY frameNumber ASC'
            );

        } else {

            $frameStmt = db()->prepare(
                'SELECT frameNumber, pixel_map, delayMs
                 FROM presetDisplayFrames
                 WHERE pDisplayID = ?
                 ORDER BY frameNumber ASC'
            );
        }

        $frameStmt->execute([$id]);

        $frames = $frameStmt->fetchAll();

        if (count($frames) < 2) {
            throw new RuntimeException(
                'This animated display has no usable animation frames.'
            );
        }

        return buildAnimatedSketch(
            $frames,
            $brightness
        );
    }

    $pixelData = getOrCreatePixelMap(
        $key,
        $id,
        $row
    );

    return buildSketch(
        $pixelData['pixel_map'],
        $brightness
    );
}


/* ============================================================
 * INSERT JOB
 * ============================================================ */

function insertDisplayJob(
    PDO $pdo,
    string $key,
    int $id,
    int $brightness,
    string $sketch,
    string $jobType = 'normal',
    ?int $eventID = null
): int {

    $stmt = $pdo->prepare(
        'INSERT INTO display_jobs
            (
                source,
                image_id,
                brightness,
                sketch,
                job_type,
                schedule_event_id
            )
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $key,
        $id,
        $brightness,
        $sketch,
        $jobType,
        $eventID
    ]);

    return (int)$pdo->lastInsertId();
}


/* ============================================================
 * QUEUE DISPLAY
 * ============================================================
 *
 * Creates a normal display request.
 *
 * Every normal request becomes the latest normal display,
 * including requests made while a schedule is active.
 *
 * During a schedule, the normal job remains pending. The
 * schedule-aware claim function will not let it interrupt
 * the scheduled display.
 *
 * Returns the newly created job ID.
 */

function queueDisplay(
    string $key,
    int $id,
    int $brightness
): int {

    $brightness = max(1, min(100, $brightness));

    // Generate the sketch before taking the queue lock.
    $sketch = buildDisplaySketch(
        $key,
        $id,
        $brightness
    );

    $pdo = db();

    acquireLightboardLock($pdo);

    try {

        $pdo->beginTransaction();

        try {

            /*
             * Remember the latest normal display.
             *
             * This is deliberately separate from the job queue.
             * It allows the normal display to be restored even
             * after its original job has been replaced.
             */
            $pdo->prepare(
                'UPDATE lightboard_state
                 SET normal_source = ?,
                     normal_image_id = ?,
                     normal_brightness = ?,
                     normal_sketch = ?
                 WHERE id = 1'
            )->execute([
                $key,
                $id,
                $brightness,
                $sketch
            ]);

            /*
             * Replace older normal/restore requests that have
             * not yet been claimed.
             *
             * Scheduled jobs are never cancelled here.
             */
            $pdo->exec(
                "UPDATE display_jobs
                 SET status = 'cancelled',
                     finished_at = NOW()
                 WHERE status = 'pending'
                   AND job_type IN ('normal', 'restore')"
            );

            /*
             * Insert the new normal request.
             *
             * If a schedule is active, this stays pending until
             * the schedule ends.
             */
            $jobID = insertDisplayJob(
                $pdo,
                $key,
                $id,
                $brightness,
                $sketch,
                'normal',
                null
            );

            $pdo->commit();

            return $jobID;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

    } finally {

        releaseLightboardLock($pdo);
    }
}


/* ============================================================
 * GET JOB STATUS
 * ============================================================ */

function getJobStatus(int $id): ?array
{
    $st = db()->prepare(
        'SELECT status, job_type
         FROM display_jobs
         WHERE id = ?'
    );

    $st->execute([$id]);

    $row = $st->fetch();

    if (!$row) {
        return null;
    }

    $messages = [
        'pending' => 'Waiting for the Lightboard to pick this up...',
        'claimed' => 'Compiling and uploading to the Lightboard...',
        'done' => 'Your display is now on the Lightboard.',
        'failed' => 'The Lightboard could not be updated. Please try again.',
        'cancelled' => 'This request was replaced by a newer one.',
    ];

    $message = $messages[$row['status']] ?? '';

    /*
     * Explain why a normal request may remain pending.
     *
     * Use Eastern time rather than MySQL NOW().
     */
    if (
        $row['status'] === 'pending'
        && in_array($row['job_type'], ['normal', 'restore'], true)
    ) {

        $scheduleNow = lightboardScheduleNow();

        $activeStmt = db()->prepare(
            "SELECT 1
             FROM schedule
             WHERE status = 'approved'
               AND startEvent <= ?
               AND endEvent > ?
             LIMIT 1"
        );

        $activeStmt->execute([
            $scheduleNow,
            $scheduleNow
        ]);

        $active = $activeStmt->fetchColumn();

        if ($active) {
            $message =
                'Saved. This display will appear when the scheduled display ends.';
        }
    }

    return [
        'status' => $row['status'],
        'message' => $message,
    ];
}


/* ============================================================
 * RECONCILE SCHEDULE QUEUE
 * ============================================================
 *
 * Must be called while holding LIGHTBOARD_LOCK_NAME.
 *
 * This function compares the currently approved schedule with
 * the last successfully uploaded display.
 *
 * Active schedule:
 *   Ensure its scheduled job exists.
 *
 * No active schedule:
 *   Cancel scheduled jobs that have not been claimed.
 *   Restore the latest normal display if a schedule had
 *   successfully taken control of the board.
 */

function reconcileScheduleQueueLocked(PDO $pdo): void
{
    /*
     * Read the currently active approved schedule.
     *
     * Schedule timestamps are stored as Eastern local datetime
     * values. Do not compare them against MySQL NOW(), because
     * the hosting server's MySQL timezone is UTC.
     */
    $scheduleNow = lightboardScheduleNow();

    $activeStmt = $pdo->prepare(
        "SELECT eventID, pDisplayID, cDisplayID, brightness
         FROM schedule
         WHERE status = 'approved'
           AND startEvent <= ?
           AND endEvent > ?
         ORDER BY startEvent DESC
         LIMIT 1"
    );

    $activeStmt->execute([
        $scheduleNow,
        $scheduleNow
    ]);

    $active = $activeStmt->fetch();

    /*
     * Read the saved normal display and last applied mode.
     */
    $state = $pdo->query(
        'SELECT normal_source,
                normal_image_id,
                normal_brightness,
                normal_sketch,
                current_mode,
                current_event_id
         FROM lightboard_state
         WHERE id = 1'
    )->fetch();

    if (!$state) {
        throw new RuntimeException(
            'The lightboard_state row is missing. Run the setup SQL.'
        );
    }


    /* --------------------------------------------------------
     * AN ACTIVE SCHEDULE EXISTS
     * -------------------------------------------------------- */

    if ($active) {

        $eventID = (int)$active['eventID'];

        $key = $active['pDisplayID'] !== null ? 'p' : 'c';

        $displayID = (int)(
            $active['pDisplayID'] ?? $active['cDisplayID']
        );

        /*
         * Cancel scheduled jobs for older events that have not
         * yet been claimed.
         *
         * A claimed job is allowed to finish. The next claim
         * will then apply the currently active schedule.
         */
        $cancel = $pdo->prepare(
            "UPDATE display_jobs
             SET status = 'cancelled',
                 finished_at = NOW()
             WHERE status = 'pending'
               AND job_type = 'scheduled'
               AND (
                    schedule_event_id IS NULL
                    OR schedule_event_id <> ?
               )"
        );

        $cancel->execute([$eventID]);

        /*
         * If this event has already been successfully uploaded,
         * it is already controlling the board.
         */
        if (
            $state['current_mode'] === 'scheduled'
            && (int)$state['current_event_id'] === $eventID
        ) {
            return;
        }

        /*
         * Do not create duplicate jobs while the same schedule
         * is already pending or being uploaded.
         */
        $existing = $pdo->prepare(
            "SELECT id
             FROM display_jobs
             WHERE job_type = 'scheduled'
               AND schedule_event_id = ?
               AND status IN ('pending', 'claimed')
             LIMIT 1"
        );

        $existing->execute([$eventID]);

        if ($existing->fetchColumn()) {
            return;
        }

        /*
         * Use the brightness saved with this schedule.
         * The fallback protects older rows or installations
         * where the value is unexpectedly NULL/out of range.
         */
        $brightness = (int)($active['brightness'] ?? DEFAULT_BRIGHTNESS);
        $brightness = max(1, min(100, $brightness));

        /*
         * Build the scheduled display sketch using its saved
         * brightness so static and animated displays match the
         * value selected when the schedule was created.
         */
        $sketch = buildDisplaySketch(
            $key,
            $displayID,
            $brightness
        );

        insertDisplayJob(
            $pdo,
            $key,
            $displayID,
            $brightness,
            $sketch,
            'scheduled',
            $eventID
        );

        return;
    }


    /* --------------------------------------------------------
     * NO ACTIVE SCHEDULE
     * -------------------------------------------------------- */

    /*
     * A schedule may have ended while its job was still
     * pending. Do not upload an expired scheduled display.
     */
    $pdo->exec(
        "UPDATE display_jobs
         SET status = 'cancelled',
             finished_at = NOW()
         WHERE status = 'pending'
           AND job_type = 'scheduled'"
    );

    /*
     * If the last successful upload was a scheduled display,
     * restore the latest normal display.
     */
    if ($state['current_mode'] === 'scheduled') {

        /*
         * There may already be a normal request waiting from
         * during the schedule.
         *
         * Convert that pending request into the restoration
         * job and use the latest saved sketch.
         */
        $pending = $pdo->query(
            "SELECT id
             FROM display_jobs
             WHERE status = 'pending'
               AND job_type IN ('normal', 'restore')
             ORDER BY id DESC
             LIMIT 1"
        )->fetch();

        if (
            empty($state['normal_source'])
            || empty($state['normal_image_id'])
            || empty($state['normal_sketch'])
        ) {

            /*
             * There is no previous normal display to restore.
             * Clear the schedule marker so this is not retried
             * on every Pi poll.
             */
            $pdo->exec(
                "UPDATE lightboard_state
                 SET current_mode = 'normal',
                     current_event_id = NULL
                 WHERE id = 1"
            );

            return;
        }

        if ($pending) {

            $update = $pdo->prepare(
                "UPDATE display_jobs
                 SET source = ?,
                     image_id = ?,
                     brightness = ?,
                     sketch = ?,
                     job_type = 'restore',
                     schedule_event_id = NULL,
                     created_at = NOW()
                 WHERE id = ?
                   AND status = 'pending'"
            );

            $update->execute([
                $state['normal_source'],
                $state['normal_image_id'],
                $state['normal_brightness'],
                $state['normal_sketch'],
                $pending['id']
            ]);

        } else {

            insertDisplayJob(
                $pdo,
                $state['normal_source'],
                (int)$state['normal_image_id'],
                (int)$state['normal_brightness'],
                $state['normal_sketch'],
                'restore',
                null
            );
        }

        return;
    }

    /*
     * If the board is already in normal mode, there is nothing
     * else to do. Any pending normal job can be claimed normally.
     */
}


/* ============================================================
 * CLAIM NEXT JOB
 * ============================================================
 *
 * Called by the Raspberry Pi worker.
 *
 * Before claiming a job:
 * 1. Expire old scheduled pending jobs.
 * 2. Mark stale claimed jobs as failed.
 * 3. Reconcile the active schedule.
 * 4. Claim the highest-priority eligible job.
 *
 * During an active schedule, only scheduled jobs can be
 * claimed. Normal requests remain pending.
 *
 * The returned array still contains id, brightness and sketch,
 * so the existing Raspberry Pi worker remains compatible.
 */

function claimNextJob(): ?array
{
    $pdo = db();

    acquireLightboardLock($pdo);

    try {

        /*
         * Expire old scheduled pending jobs.
         *
         * Normal jobs are deliberately excluded because they
         * may be waiting for a schedule to finish.
         */
        $pdo->exec(
            "UPDATE display_jobs
             SET status = 'cancelled',
                 finished_at = NOW()
             WHERE status = 'pending'
               AND job_type = 'scheduled'
               AND created_at < NOW() - INTERVAL "
            . JOB_PENDING_TTL_MINUTES . " MINUTE"
        );

        /*
         * Mark jobs stuck in the claimed state as failed.
         */
        $pdo->exec(
            "UPDATE display_jobs
             SET status = 'failed',
                 log = 'Timed out waiting for the Pi.',
                 finished_at = NOW()
             WHERE status = 'claimed'
               AND claimed_at < NOW() - INTERVAL "
            . JOB_CLAIM_TTL_MINUTES . " MINUTE"
        );

        /*
         * Reconcile the schedule and queue the correct display.
         */
        $pdo->beginTransaction();

        try {

            reconcileScheduleQueueLocked($pdo);

            /*
             * Check whether a schedule is currently active.
             *
             * Use the same Eastern clock as reconciliation so
             * both decisions agree about the active schedule.
             */
            $scheduleNow = lightboardScheduleNow();

            $activeStmt = $pdo->prepare(
                "SELECT eventID
                 FROM schedule
                 WHERE status = 'approved'
                   AND startEvent <= ?
                   AND endEvent > ?
                 LIMIT 1"
            );

            $activeStmt->execute([
                $scheduleNow,
                $scheduleNow
            ]);

            $active = $activeStmt->fetch();

            /*
             * If a scheduled job is already claimed, do not
             * allow another worker to claim a normal job.
             *
             * This also protects against accidentally running
             * two Pi workers at once.
             */
            if ($active) {

                $claimed = $pdo->query(
                    "SELECT id
                     FROM display_jobs
                     WHERE status = 'claimed'
                       AND job_type = 'scheduled'
                     LIMIT 1"
                )->fetch();

                if ($claimed) {
                    $pdo->commit();
                    return null;
                }

                $jobStmt = $pdo->prepare(
                    "SELECT id, brightness, sketch
                     FROM display_jobs
                     WHERE status = 'pending'
                       AND job_type = 'scheduled'
                     ORDER BY id
                     LIMIT 1
                     FOR UPDATE"
                );

                $jobStmt->execute();

            } else {

                /*
                 * If an expired schedule job is still claimed,
                 * let it finish before sending the restoration.
                 */
                $claimed = $pdo->query(
                    "SELECT id
                     FROM display_jobs
                     WHERE status = 'claimed'
                       AND job_type = 'scheduled'
                     LIMIT 1"
                )->fetch();

                if ($claimed) {
                    $pdo->commit();
                    return null;
                }

                $jobStmt = $pdo->prepare(
                    "SELECT id, brightness, sketch
                     FROM display_jobs
                     WHERE status = 'pending'
                       AND job_type IN ('normal', 'restore')
                     ORDER BY id
                     LIMIT 1
                     FOR UPDATE"
                );

                $jobStmt->execute();
            }

            $job = $jobStmt->fetch();

            if (!$job) {
                $pdo->commit();
                return null;
            }

            /*
             * Mark the selected job as claimed.
             */
            $pdo->prepare(
                "UPDATE display_jobs
                 SET status = 'claimed',
                     claimed_at = NOW()
                 WHERE id = ?"
            )->execute([$job['id']]);

            $pdo->commit();

            return $job;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

    } finally {

        releaseLightboardLock($pdo);
    }
}


/* ============================================================
 * COMPLETE JOB
 * ============================================================
 *
 * Called by the Raspberry Pi after compiling and uploading.
 *
 * Successful jobs update lightboard_state so the server knows
 * which display currently controls the board.
 *
 * Failed scheduled jobs are retried by the next claim request.
 */

function completeJob(
    int $id,
    bool $ok,
    string $log
): void {

    $pdo = db();

    acquireLightboardLock($pdo);

    try {

        $pdo->beginTransaction();

        try {

            $stmt = $pdo->prepare(
                'SELECT job_type, schedule_event_id
                 FROM display_jobs
                 WHERE id = ?
                   AND status = \'claimed\'
                 FOR UPDATE'
            );

            $stmt->execute([$id]);

            $job = $stmt->fetch();

            if (!$job) {
                $pdo->commit();
                return;
            }

            /*
             * Update the job status and store the worker log.
             */
            $update = $pdo->prepare(
                'UPDATE display_jobs
                 SET status = ?,
                     log = ?,
                     finished_at = NOW()
                 WHERE id = ?
                   AND status = \'claimed\''
            );

            $update->execute([
                $ok ? 'done' : 'failed',
                substr($log, -4000),
                $id
            ]);

            /*
             * Only successful uploads change the current
             * display state.
             */
            if ($ok) {

                if ($job['job_type'] === 'scheduled') {

                    $state = $pdo->prepare(
                        "UPDATE lightboard_state
                         SET current_mode = 'scheduled',
                             current_event_id = ?
                         WHERE id = 1"
                    );

                    $state->execute([
                        $job['schedule_event_id']
                    ]);

                } else {

                    /*
                     * Both normal and restoration jobs put the
                     * board back into normal mode.
                     */
                    $pdo->exec(
                        "UPDATE lightboard_state
                         SET current_mode = 'normal',
                             current_event_id = NULL
                         WHERE id = 1"
                    );
                }
            }

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

    } finally {

        releaseLightboardLock($pdo);
    }
}
