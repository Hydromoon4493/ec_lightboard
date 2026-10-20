<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.20
 * Filename: api/thumbnail.php
 * Serves a 48x48 board-ready image, enlarged with nearest-neighbor
 * scaling so individual LED pixels stay sharp.
 * Usage: api/thumbnail.php?src=p&id=3
 */

require_once __DIR__ . '/../includes/lightboard/images.php';

$key = (string)($_GET['src'] ?? '');
$id  = (int)($_GET['id'] ?? 0);

if (!in_array($key, ['p', 'c'], true) || $id <= 0) {
    http_response_code(400);
    exit;
}

try {
    $row = getImageForThumbnail($key, $id);

    if (!$row || empty($row['display'])) {
        http_response_code(404);
        exit;
    }

    $im  = buildImage((string)$row['display']);
    $big = imagescale($im, 192, 192, IMG_NEAREST_NEIGHBOUR);

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');

    imagepng($big);

} catch (Throwable $e) {
    error_log('thumbnail.php: ' . $e->getMessage());
    http_response_code(500);
}