<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: includes/lightboard/jobs.php
 *
 * Display-job queue used between the website and the Raspberry Pi.
 *
 * Website side: queueDisplay(), getJobStatus()
 * Pi side:      claimNextJob(), completeJob()
 *
 * The finished Arduino sketch is generated on the server and stored
 * with the job, so the Pi only has to download, compile and upload it.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/board.php';

/* Pending jobs older than this are dropped instead of flashed late. */
const JOB_PENDING_TTL_MINUTES = 10;

/* A claimed job not reported finished within this time is failed. */
const JOB_CLAIM_TTL_MINUTES = 5;


/*
 * Builds the sketch for an image and queues it. Returns the job id.
 * Any older pending jobs are cancelled: only the newest request matters.
 */
function queueDisplay(string $key, int $id, int $brightness): int
{
    src($key); // throws on an unknown source

    $brightness = max(1, min(100, $brightness));

    $row = getImageForDisplay($key, $id);
    if (!$row) {
        throw new RuntimeException('Image not found.');
    }

    /*
     * Returns ['pixel_map' => string, 'image' => GD image, 'cached' => bool].
     * Only the CRGB pixel-map string goes into the sketch.
     */
    $pixelData = getOrCreatePixelMap($key, $id, $row);
    $imageC    = $pixelData['pixel_map'];

    $sketch = buildSketch($imageC, $brightness);

    $pdo = db();
    $pdo->beginTransaction();

    $pdo->exec("UPDATE display_jobs
                SET status = 'cancelled', finished_at = NOW()
                WHERE status = 'pending'");

    $st = $pdo->prepare(
        'INSERT INTO display_jobs (source, image_id, brightness, sketch)
         VALUES (?, ?, ?, ?)'
    );
    $st->execute([$key, $id, $brightness, $sketch]);
    $jobId = (int)$pdo->lastInsertId();

    $pdo->commit();

    return $jobId;
}


/*
 * Returns the public status of a job, or null if it does not exist.
 * The raw arduino-cli log is deliberately not exposed publicly.
 */
function getJobStatus(int $id): ?array
{
    $st = db()->prepare('SELECT status FROM display_jobs WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row) {
        return null;
    }

    $messages = [
        'pending'   => 'Waiting for the Lightboard to pick this up...',
        'claimed'   => 'Compiling and uploading to the Lightboard...',
        'done'      => 'Your display is now on the Lightboard.',
        'failed'    => 'The Lightboard could not be updated. Please try again.',
        'cancelled' => 'This request was replaced by a newer one.',
    ];

    return [
        'status'  => $row['status'],
        'message' => $messages[$row['status']] ?? '',
    ];
}


/*
 * Pi: atomically hands out the oldest pending job, or null.
 */
function claimNextJob(): ?array
{
    $pdo = db();

    // Housekeeping: stale pending jobs and jobs the Pi never finished.
    $pdo->exec("UPDATE display_jobs
                SET status = 'cancelled', finished_at = NOW()
                WHERE status = 'pending'
                  AND created_at < NOW() - INTERVAL " . JOB_PENDING_TTL_MINUTES . " MINUTE");

    $pdo->exec("UPDATE display_jobs
                SET status = 'failed', log = 'Timed out waiting for the Pi.', finished_at = NOW()
                WHERE status = 'claimed'
                  AND claimed_at < NOW() - INTERVAL " . JOB_CLAIM_TTL_MINUTES . " MINUTE");

    $pdo->beginTransaction();

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

    $pdo->prepare("UPDATE display_jobs SET status = 'claimed', claimed_at = NOW() WHERE id = ?")
        ->execute([$job['id']]);

    $pdo->commit();

    return $job;
}


/*
 * Pi: records the result of a claimed job.
 */
function completeJob(int $id, bool $ok, string $log): void
{
    db()->prepare(
        "UPDATE display_jobs
         SET status = ?, log = ?, finished_at = NOW()
         WHERE id = ? AND status = 'claimed'"
    )->execute([$ok ? 'done' : 'failed', substr($log, -4000), $id]);
}