<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Here because we can't do `// require_once __DIR__ . '/../require_login.php';` in includes/lightboard/images.php
session_start();

/*
 * Author: Sam Vossen 2026.10.15
 * Filename: includes/lightboard/upload_db.php
 *
 * This file processes custom Lightboard image uploads.
 *
 * Responsibilities:
 * - Verify the request is a POST request.
 * - Validate the uploaded image.
 * - Check the configured file-size limit.
 * - Convert the image to the Lightboard's configured size.
 * - Generate the FastLED CRGB pixel map.
 * - Store the processed image and pixel map in the database.
 * - Associate the uploaded display with the logged-in user.
 *
 * This file does NOT:
 * - Display the upload form.
 * - Communicate directly with the ESP32.
 * - Compile or upload Arduino sketches.
 *
 * The user-facing upload form is handled by upload.php.
 */

require_once __DIR__ . '/images.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../upload.php');
    exit;
}

if (!isset($_FILES['image'])) {
    header('Location: ../../upload.php?upload=error');
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../../upload.php?upload=error');
    exit;
}

$config = require __DIR__ . '/../config.php';

$maxBytes = (int) $config['max_bytes'];

if ((int) $file['size'] > $maxBytes) {
    header('Location: ../../upload.php?upload=too_large');
    exit;
}

/*
 * Read the uploaded image.
 */
$imageData = file_get_contents($file['tmp_name']);

if ($imageData === false) {
    header('Location: ../../upload.php?upload=error');
    exit;
}

/*
 * Verify that it is actually an image.
 */
$imageInfo = getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    header('Location: ../../upload.php?upload=invalid');
    exit;
}

$mimeType = $imageInfo['mime'];

$allowedTypes = [
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
];

if (!in_array($mimeType, $allowedTypes, true)) {
    header('Location: ../../upload.php?upload=invalid');
    exit;
}

/*
 * Convert the uploaded image to a board-ready
 * 48x48 GD image.
 */
$im = buildImage($imageData);

/*
 * Generate the FastLED CRGB pixel map.
 */
$pixelMap = pixelMapC($im);

/*
 * Store the processed 48x48 image as PNG.
 */
ob_start();

imagepng($im);

$pngData = ob_get_clean();

imagedestroy($im);

if ($pngData === false) {
    header('Location: ../../upload.php?upload=error');
    exit;
}

/*
 * Get the display name entered by the user.
 */
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    header('Location: ../../upload.php?upload=error');
    exit;
}

$name = mb_substr($name, 0, 45);


/*
 * The logged-in user's ID comes from require_login.php.
 */
if (empty($_SESSION['userID'])) {
    header('Location: ../../upload.php?upload=error');
    exit;
}

$userID = (int) $_SESSION['userID'];

/*
 * Insert the custom display.
 */
dbRetry(function (PDO $pdo) use (
    $userID,
    $name,
    $pngData,
    $pixelMap
): void {

    $stmt = $pdo->prepare(
        "INSERT INTO customDisplay
            (userID, name, mimeType, display, pixel_map)
         VALUES
            (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $userID,
        $name,
        'image/png',
        $pngData,
        $pixelMap
    ]);
});

header(
    'Location: ../../upload.php?upload=success&name=' . urlencode($name)
);
exit;
