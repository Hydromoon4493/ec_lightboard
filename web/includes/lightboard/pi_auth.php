<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.22
 * Filename: includes/lightboard/pi_auth.php
 *
 * Shared-secret check for the Raspberry Pi endpoints.
 * The Pi sends the token in an X-Pi-Token header (some shared hosts
 * strip the Authorization header before PHP sees it).
 */

function requirePiToken(): void
{
    $cfg      = require __DIR__ . '/../config.php';
    $expected = (string)($cfg['pi_token'] ?? '');
    $sent     = (string)($_SERVER['HTTP_X_PI_TOKEN'] ?? '');

    // An unset token must never let everyone in.
    if ($expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}