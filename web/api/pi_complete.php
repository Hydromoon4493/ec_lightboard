<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: api/pi_complete.php
 * Called by the Raspberry Pi to report a job result.
 * POST JSON: {"id":12,"ok":true,"log":"..."}
 */

require_once __DIR__ . '/../includes/lightboard/jobs.php';
require_once __DIR__ . '/../includes/lightboard/pi_auth.php';

requirePiToken();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required.']);
    exit;
}

$in = json_decode((string)file_get_contents('php://input'), true);
$id = (int)($in['id'] ?? 0);

if (!is_array($in) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

try {
    completeJob($id, !empty($in['ok']), (string)($in['log'] ?? ''));
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('api/pi_complete.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}