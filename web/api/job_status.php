<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: api/job_status.php
 * Public endpoint the browser polls. Usage: api/job_status.php?id=12
 */

require_once __DIR__ . '/../includes/lightboard/jobs.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid job id.']);
    exit;
}

try {
    $job = getJobStatus($id);

    if ($job === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found.']);
        exit;
    }

    echo json_encode($job);
} catch (Throwable $e) {
    error_log('api/job_status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not read job status.']);
}