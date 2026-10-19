<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.18
 * Filename: api/display_stream.php
 *
 * Streaming endpoint for the Lightboard display request.
 * Sends one JSON object per line (NDJSON):
 *     {"type":"status|preview|success|error","message":"..."}
 */

require_once __DIR__ . '/display_service.php';

@set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function send(string $type, string $message): void
{
    echo json_encode(
        ['type' => $type, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ) . "\n";
    flush();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send('error', 'POST required.');
    exit;
}

try {
    $key        = (string)($_POST['src'] ?? '');
    $id         = (int)($_POST['id'] ?? 0);
    $brightness = max(1, min(100, (int)($_POST['brightness'] ?? 35)));

    if (!in_array($key, ['p', 'c'], true) || $id <= 0) {
        throw new RuntimeException('Invalid image selection.');
    }

    displayImage(
        $key,
        $id,
        $brightness,
        fn(string $m) => send('status', $m),
        fn(string $uri) => send('preview', $uri)
    );

    send('success', 'Success. Image displayed on the board.');

} catch (Throwable $e) {
    send('error', $e->getMessage());
}