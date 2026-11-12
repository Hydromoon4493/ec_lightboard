<?php

/*
 * Author: Sam Vossen 2026.11.05
 * Filename: includes/lightboard/jobs/jobs.php
 *
 * Functions for creating and managing Lightboard display jobs.
 *
 * The website generates the complete Arduino sketch and stores it
 * in display_jobs.sketch. The Raspberry Pi worker retrieves that
 * sketch, compiles it and uploads it to the ESP32.
 *
 * - queueDisplay():  creates a new display job.
 * - getJobStatus():  returns the current status and user-facing message.
 * - claimNextJob():  lets the Raspberry Pi claim the next pending job.
 * - completeJob():  marks a claimed job as completed or failed.
 *
 * Static and animated display handling:
 * - Static images use getOrCreatePixelMap() and buildSketch().
 * - Animated custom displays load frames from customDisplayFrames.
 * - Animated preset displays load frames from presetDisplayFrames.
 * - Both use buildAnimatedSketch().
 *
 * This file does NOT communicate directly with the ESP32 or run
 * arduino-cli. Those tasks are handled by the Raspberry Pi worker.
 */

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/images.php';
require_once __DIR__ . '/../hardware/board.php';


/* ============================================================
 * JOB TIMEOUT SETTINGS
 * ============================================================
 *
 * Pending jobs older than this are cancelled.
 * Claimed jobs older than this are marked as failed.
 */
const JOB_PENDING_TTL_MINUTES = 10;
const JOB_CLAIM_TTL_MINUTES = 5;


/* ============================================================
 * QUEUE DISPLAY
 * ============================================================
 *
 * Creates a display job for the requested image or custom display.
 *
 * $key:
 *   Source identifier used by the existing image system.
 *   'c' identifies a custom display.
 *   'p' identifies a preset display.
 *
 * $id:
 *   ID of the image/custom display.
 *
 * $brightness:
 *   LED brightness, clamped to the supported range of 1–100.
 *
 * Process:
 * 1. Validate the source and load the display record.
 * 2. Determine whether the selected display is animated.
 * 3. Generate either a static or animated Arduino sketch.
 * 4. Cancel older pending jobs.
 * 5. Insert the new job into display_jobs.
 *
 * Returns the newly created job ID.
 */
function queueDisplay(string $key, int $id, int $brightness): int
{
    /* VALIDATE SOURCE AND BRIGHTNESS */

    src($key);

    $brightness = max(1, min(100, $brightness));

    /* LOAD DISPLAY RECORD */

    $row = getImageForDisplay($key, $id);

    if (!$row) {
        throw new RuntimeException('Image not found.');
    }

    /* GENERATE THE ARDUINO SKETCH */

    /*
     * Determine whether the selected display is animated.
     *
     * Custom displays use customDisplay.isAnimated.
     * Preset displays use presetDisplay.isAnimated.
     *
     * Each display type retains its own frame table.
     */

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

        /*
         * ANIMATED DISPLAY
         *
         * Retrieve frames from the correct frame table.
         * Both tables use the same pixel_map and delayMs
         * structure, so buildAnimatedSketch() handles both.
         */

        if ($key === 'c') {

            // Existing custom animation retrieval.
            $frameStmt = db()->prepare(
                'SELECT frameNumber, pixel_map, delayMs
                 FROM customDisplayFrames
                 WHERE displayID = ?
                 ORDER BY frameNumber ASC'
            );

        } elseif ($key === 'p') {

            // Preset animation retrieval.
            $frameStmt = db()->prepare(
                'SELECT frameNumber, pixel_map, delayMs
                 FROM presetDisplayFrames
                 WHERE pDisplayID = ?
                 ORDER BY frameNumber ASC'
            );

        } else {

            throw new RuntimeException(
                'Unsupported animated display source.'
            );
        }

        $frameStmt->execute([$id]);

        $frames = $frameStmt->fetchAll();

        if (count($frames) < 2) {
            throw new RuntimeException(
                'This animated display has no usable animation frames.'
            );
        }

        // Both display types use the same animation builder.
        $sketch = buildAnimatedSketch(
            $frames,
            $brightness
        );

    } else {

        /*
         * STATIC IMAGE
         *
         * Original static-image workflow remains unchanged.
         */
        $pixelData = getOrCreatePixelMap(
            $key,
            $id,
            $row
        );

        $sketch = buildSketch(
            $pixelData['pixel_map'],
            $brightness
        );
    }

    /* INSERT THE DISPLAY JOB */

    $pdo = db();

    $pdo->beginTransaction();

    try {

        /*
         * Cancel older pending jobs.
         *
         * A newer display request replaces requests that have
         * not yet been claimed by the Raspberry Pi.
         */
        $pdo->exec(
            "UPDATE display_jobs
             SET status = 'cancelled',
                 finished_at = NOW()
             WHERE status = 'pending'"
        );

        /*
         * Store the complete generated sketch.
         *
         * The Raspberry Pi does not need to know whether this
         * sketch represents a static image or an animation.
         */
        $st = $pdo->prepare(
            'INSERT INTO display_jobs
                (source, image_id, brightness, sketch)
             VALUES (?, ?, ?, ?)'
        );

        $st->execute([
            $key,
            $id,
            $brightness,
            $sketch
        ]);

        $jobId = (int)$pdo->lastInsertId();

        $pdo->commit();

        return $jobId;

    } catch (Throwable $e) {

        /*
         * Roll back the transaction if inserting the new job
         * or cancelling older jobs fails.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/* ============================================================
 * GET JOB STATUS
 * ============================================================
 *
 * Retrieves the status of a display job and returns a
 * user-facing message for the website.
 *
 * $id: display_jobs primary key.
 *
 * Returns:
 * - status:  current database status.
 * - message: user-facing status message.
 *
 * Returns null if the job does not exist.
 */
function getJobStatus(int $id): ?array
{
    $st = db()->prepare(
        'SELECT status
         FROM display_jobs
         WHERE id = ?'
    );

    $st->execute([$id]);

    $row = $st->fetch();

    if (!$row) {
        return null;
    }

    /* MAP DATABASE STATUSES TO USER-FACING MESSAGES */

    $messages = [
        'pending' => 'Waiting for the Lightboard to pick this up...',
        'claimed' => 'Compiling and uploading to the Lightboard...',
        'done' => 'Your display is now on the Lightboard.',
        'failed' => 'The Lightboard could not be updated. Please try again.',
        'cancelled' => 'This request was replaced by a newer one.',
    ];

    return [
        'status' => $row['status'],
        'message' => $messages[$row['status']] ?? '',
    ];
}


/* ============================================================
 * CLAIM NEXT JOB
 * ============================================================
 *
 * Called by the Raspberry Pi worker.
 *
 * Process:
 * 1. Cancel pending jobs that have expired.
 * 2. Mark jobs that were claimed too long ago as failed.
 * 3. Lock the oldest pending job.
 * 4. Mark it as claimed.
 * 5. Return its sketch and job information to the worker.
 *
 * Returns null when there are no pending jobs.
 */
function claimNextJob(): ?array
{
    $pdo = db();

    /* EXPIRE OLD PENDING JOBS */

    $pdo->exec(
        "UPDATE display_jobs
         SET status = 'cancelled',
             finished_at = NOW()
         WHERE status = 'pending'
           AND created_at < NOW() - INTERVAL "
        . JOB_PENDING_TTL_MINUTES
        . " MINUTE"
    );

    /* EXPIRE JOBS STUCK IN THE CLAIMED STATE */

    $pdo->exec(
        "UPDATE display_jobs
         SET status = 'failed',
             log = 'Timed out waiting for the Pi.',
             finished_at = NOW()
         WHERE status = 'claimed'
           AND claimed_at < NOW() - INTERVAL "
        . JOB_CLAIM_TTL_MINUTES
        . " MINUTE"
    );

    /* CLAIM THE OLDEST PENDING JOB */

    $pdo->beginTransaction();

    try {

        /*
         * FOR UPDATE prevents another worker from claiming
         * the same pending job at the same time.
         */
        $job = $pdo->query(
            "SELECT id, brightness, sketch
             FROM display_jobs
             WHERE status = 'pending'
             ORDER BY id
             LIMIT 1
             FOR UPDATE"
        )->fetch();

        if (!$job) {
            $pdo->commit();

            return null;
        }

        /* MARK THE JOB AS CLAIMED */

        $pdo->prepare(
            "UPDATE display_jobs
             SET status = 'claimed',
                 claimed_at = NOW()
             WHERE id = ?"
        )->execute([$job['id']]);

        $pdo->commit();

        return $job;

    } catch (Throwable $e) {

        /*
         * Release the row lock and undo changes if claiming
         * the job fails.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/* ============================================================
 * COMPLETE JOB
 * ============================================================
 *
 * Called by the Raspberry Pi worker after attempting to
 * compile and upload the sketch.
 *
 * $id:  display_jobs primary key.
 * $ok:  true if the compile/upload succeeded; false otherwise.
 * $log: output or error log from the worker.
 *
 * Only jobs currently marked as claimed are updated.
 * The stored log is limited to the last 4000 characters.
 */
function completeJob(int $id, bool $ok, string $log): void
{
    db()->prepare(
        "UPDATE display_jobs
         SET status = ?,
             log = ?,
             finished_at = NOW()
         WHERE id = ?
           AND status = 'claimed'"
    )->execute([
        $ok ? 'done' : 'failed',
        substr($log, -4000),
        $id
    ]);
}
