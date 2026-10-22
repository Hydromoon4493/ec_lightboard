<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: api/pi_claim.php
 * Called by the Raspberry Pi. Returns the oldest pending job (once)
 * or {"job": null}. Requires the X-Pi-Token header.
 */

require_once __DIR__ . '/../includes/lightboard/jobs.php';
require_once __DIR__ . '/../includes/lightboard/pi_auth.php';

requirePiToken();

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    echo json_encode(['job' => claimNextJob()]);
} catch (Throwable $e) {
    error_log('api/pi_claim.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}