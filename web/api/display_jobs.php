<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: api/display_jobs.php
 *
 * Public endpoint called by the browser. Builds the sketch, queues a
 * display job and returns immediately with the job id.
 *
 * POST JSON: {"src":"p","id":3,"brightness":35}
 * Response:  {"job_id":12}
 */

require_once __DIR__ . '/../includes/lightboard/jobs.php';

header('Content-Type: application/json');

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST required.');
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = $_POST;
}

$key        = (string)($in['src'] ?? '');
$id         = (int)($in['id'] ?? 0);
$brightness = (int)($in['brightness'] ?? 35);

if (!in_array($key, ['p', 'c'], true) || $id <= 0) {
    fail(400, 'Invalid image.');
}

try {
    echo json_encode(['job_id' => queueDisplay($key, $id, $brightness)]);
} catch (RuntimeException $e) {
    fail(404, $e->getMessage());
} catch (Throwable $e) {
    error_log('api/display_jobs.php: ' . $e->getMessage());
    fail(500, 'Could not queue the display.');
}