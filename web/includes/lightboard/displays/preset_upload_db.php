
<?php

/*
 * Author: Sam Vossen 2026.11.10
 * Filename: includes/lightboard/preset_custom_upload_db.php
 *
 * Handles uploads of administrator preset Lightboard displays.
 *
 * Supported formats:
 * - PNG
 * - JPEG
 * - GIF (including animated GIFs)
 * - WebP
 *
 * Responsibilities:
 * - Require an authenticated administrator.
 * - Validate uploaded image files.
 * - Resize and crop images using the existing image pipeline.
 * - Generate Lightboard pixel maps.
 * - Process animated GIF frames using Imagick.
 * - Store preset images in presetDisplay.
 * - Store animated preset frames in presetDisplayFrames.
 *
 * Animated GIF limits:
 * - Maximum 50 frames.
 * - Maximum total animation duration: 10 seconds.
 * - Minimum frame delay: 20ms.
 * - Maximum frame delay: 2000ms.
 *
 * Custom display uploads remain handled by custom_upload_db.php.
 */


/* ============================================================
 * REQUIRE LOGIN AND IMAGE FUNCTIONS
 * ============================================================
 */

require_once __DIR__ . '/../require_login.php';
require_once __DIR__ . '/images.php';


/* ============================================================
 * GIF CONFIGURATION
 * ============================================================
 */

define('PRESET_GIF_MAX_FRAMES', 50);
define('PRESET_GIF_MAX_DURATION_MS', 10000);
define('PRESET_GIF_MIN_DELAY_MS', 20);
define('PRESET_GIF_MAX_DELAY_MS', 2000);


/* ============================================================
 * REDIRECT HELPER
 * ============================================================
 */

function presetUploadRedirect(string $status): void
{
    header(
        'Location: ../../upload.php?preset_upload='
        . urlencode($status)
    );

    exit;
}


/* ============================================================
 * PNG ENCODING HELPER
 * ============================================================
 */

function encodePresetPng($image): string
{
    ob_start();

    if (!imagepng($image)) {
        ob_end_clean();

        throw new RuntimeException(
            'Could not encode preset image as PNG.'
        );
    }

    $data = ob_get_clean();

    if ($data === false || $data === '') {
        throw new RuntimeException(
            'Could not retrieve encoded preset image.'
        );
    }

    return $data;
}


/* ============================================================
 * PROCESS ONE IMAGE INTO A PIXEL MAP
 * ============================================================
 */

function processPresetFrame(string $imageData): array
{
    $image = buildImage($imageData);

    if (!$image) {
        throw new RuntimeException(
            'Could not process preset image frame.'
        );
    }

    try {

        $pixelMap = pixelMapC($image);

        if (
            !is_string($pixelMap)
            || $pixelMap === ''
        ) {
            throw new RuntimeException(
                'Could not generate preset pixel map.'
            );
        }

        $pngData = encodePresetPng($image);

        return [
            'pixel_map' => $pixelMap,
            'display' => $pngData
        ];

    } finally {

        imagedestroy($image);

    }
}


/* ============================================================
 * NORMALIZE GIF FRAME DELAY
 * ============================================================
 *
 * Imagick commonly reports GIF delays in centiseconds.
 * This follows the delay handling used by the custom upload
 * handler: small values are converted from centiseconds,
 * while larger values are treated as milliseconds.
 */

function normalizePresetGifDelay(int $rawDelay): int
{
    $delay = ($rawDelay >= 20)
        ? $rawDelay
        : ($rawDelay * 10);

    $delay = max(
        PRESET_GIF_MIN_DELAY_MS,
        $delay
    );

    $delay = min(
        PRESET_GIF_MAX_DELAY_MS,
        $delay
    );

    return $delay;
}


/* ============================================================
 * VERIFY ADMINISTRATOR ROLE
 * ============================================================
 *
 * roleID 1 = admin
 * roleID 2 = user
 */

try {

    $pdo = db();

    $roleStmt = $pdo->prepare(
        'SELECT roleID
         FROM users
         WHERE userID = ?
         LIMIT 1'
    );

    $roleStmt->execute([
        (int) $_SESSION['userID']
    ]);

    $roleID = $roleStmt->fetchColumn();

    if ((int) $roleID !== 1) {
        http_response_code(403);
        exit('Access denied. Administrator privileges required.');
    }

} catch (Throwable $e) {

    error_log(
        'Lightboard preset role verification error: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Could not verify administrator permissions.');

}


/* ============================================================
 * VALIDATE REQUEST METHOD
 * ============================================================
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    presetUploadRedirect('error');
}


/* ============================================================
 * VALIDATE DISPLAY NAME
 * ============================================================
 */

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    presetUploadRedirect('error');
}

if (function_exists('mb_substr')) {
    $name = mb_substr($name, 0, 45);
} else {
    $name = substr($name, 0, 45);
}


/* ============================================================
 * VALIDATE UPLOADED FILE
 * ============================================================
 */

if (
    !isset($_FILES['image'])
    || !is_array($_FILES['image'])
) {
    presetUploadRedirect('error');
}

$file = $_FILES['image'];

if (
    !isset($file['error'])
    || (int) $file['error'] !== UPLOAD_ERR_OK
) {

    if (
        isset($file['error'])
        && in_array(
            (int) $file['error'],
            [
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE
            ],
            true
        )
    ) {
        presetUploadRedirect('too_large');
    }

    presetUploadRedirect('error');
}


/* ============================================================
 * CHECK FILE SIZE
 * ============================================================
 */

global $CFG;

$maxBytes = isset($CFG['max_bytes'])
    ? (int) $CFG['max_bytes']
    : 8 * 1024 * 1024;

if (
    !isset($file['size'])
    || (int) $file['size'] <= 0
    || (int) $file['size'] > $maxBytes
) {
    presetUploadRedirect('too_large');
}


/* ============================================================
 * VERIFY TEMPORARY UPLOAD
 * ============================================================
 */

if (
    !isset($file['tmp_name'])
    || !is_uploaded_file($file['tmp_name'])
) {
    presetUploadRedirect('error');
}


/* ============================================================
 * READ IMAGE DATA
 * ============================================================
 */

$imageData = file_get_contents($file['tmp_name']);

if ($imageData === false || $imageData === '') {
    presetUploadRedirect('invalid');
}


/* ============================================================
 * DETECT IMAGE MIME TYPE
 * ============================================================
 */

$imageInfo = @getimagesizefromstring($imageData);

if (
    $imageInfo === false
    || empty($imageInfo['mime'])
) {
    presetUploadRedirect('invalid');
}

$mimeType = $imageInfo['mime'];

$allowedMimeTypes = [
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp'
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    presetUploadRedirect('invalid');
}


/* ============================================================
 * PREPARE IMAGE DATA
 * ============================================================
 */

$frames = [];
$isAnimated = false;

try {

    /* ========================================================
     * ANIMATED GIF PROCESSING
     * ========================================================
     */

    if ($mimeType === 'image/gif') {

        if (!class_exists('Imagick')) {
            throw new RuntimeException(
                'Imagick is required to process preset GIF uploads.'
            );
        }

        $gif = new Imagick();

        try {

            if (!$gif->readImageBlob($imageData)) {
                throw new RuntimeException(
                    'Could not read uploaded GIF.'
                );
            }

            /*
             * Coalesce frames so that each frame represents
             * the complete displayed image rather than only
             * its changed region.
             */

            $coalesced = $gif->coalesceImages();

            if (!$coalesced) {
                throw new RuntimeException(
                    'Could not coalesce GIF frames.'
                );
            }

            try {

                $frameCount = $coalesced->getNumberImages();

                if ($frameCount < 1) {
                    throw new RuntimeException(
                        'GIF contains no frames.'
                    );
                }

                if ($frameCount > PRESET_GIF_MAX_FRAMES) {
                    throw new RuntimeException(
                        'GIF contains too many frames.'
                    );
                }

                $totalDuration = 0;
                $frameNumber = 0;

                foreach ($coalesced as $frame) {

                    /*
                     * Convert the current coalesced frame
                     * to PNG data for the existing GD pipeline.
                     */

                    $frameCopy = clone $frame;

                    try {

                        $frameCopy->setImageFormat('png');

                        $frameData = $frameCopy->getImageBlob();

                    } finally {

                        $frameCopy->clear();
                        $frameCopy->destroy();

                    }

                    if (
                        $frameData === false
                        || $frameData === ''
                    ) {
                        throw new RuntimeException(
                            'Could not extract GIF frame.'
                        );
                    }

                    /*
                     * Process frame into a Lightboard pixel map.
                     */

                    $processed = processPresetFrame($frameData);

                    /*
                     * Normalize the frame delay.
                     */

                    $rawDelay = (int) $frame->getImageDelay();

                    $delayMs = normalizePresetGifDelay($rawDelay);

                    $totalDuration += $delayMs;

                    if (
                        $totalDuration >
                        PRESET_GIF_MAX_DURATION_MS
                    ) {
                        throw new RuntimeException(
                            'GIF animation duration exceeds 10 seconds.'
                        );
                    }

                    $frames[] = [
                        'frameNumber' => $frameNumber,
                        'pixel_map' => $processed['pixel_map'],
                        'display' => $processed['display'],
                        'delayMs' => $delayMs
                    ];

                    $frameNumber++;
                }

            } finally {

                $coalesced->clear();
                $coalesced->destroy();

            }

        } finally {

            $gif->clear();
            $gif->destroy();

        }

        /*
         * Only mark the preset animated if it actually
         * contains multiple frames.
         */

        $isAnimated = count($frames) > 1;

    }


    /* ========================================================
     * STATIC IMAGE PROCESSING
     * ========================================================
     */

    if (!$isAnimated) {

        /*
         * Static GIFs also use the normal image pipeline.
         * Animated GIFs with multiple frames have already
         * been processed above.
         */

        if (empty($frames)) {

            $processed = processPresetFrame($imageData);

            $frames[] = [
                'frameNumber' => 0,
                'pixel_map' => $processed['pixel_map'],
                'display' => $processed['display'],
                'delayMs' => 100
            ];

        }

    }


    /* ========================================================
     * STORE PRESET AND FRAMES
     * ========================================================
     */

    $firstFrame = $frames[0];

    $pdo->beginTransaction();

    try {

        /*
         * Insert the parent preset.
         * The first frame is also stored in presetDisplay,
         * matching the existing custom-display structure.
         */

        $stmt = $pdo->prepare(
            'INSERT INTO presetDisplay
                (name, mimeType, display, pixel_map, isAnimated)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $name,
            'image/png',
            $firstFrame['display'],
            $firstFrame['pixel_map'],
            $isAnimated ? 1 : 0
        ]);

        $presetID = (int) $pdo->lastInsertId();


        /* ====================================================
         * INSERT ANIMATED FRAMES
         * ====================================================
         *
         * Static images do not need frame records.
         * Animated GIFs store every frame, including frame 0.
         */

        if ($isAnimated) {

            $frameStmt = $pdo->prepare(
                'INSERT INTO presetDisplayFrames
                    (pDisplayID, frameNumber, pixel_map, delayMs)
                 VALUES (?, ?, ?, ?)'
            );

            foreach ($frames as $frame) {

                $frameStmt->execute([
                    $presetID,
                    $frame['frameNumber'],
                    $frame['pixel_map'],
                    $frame['delayMs']
                ]);

            }

        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;

    }


    /* ========================================================
     * SUCCESS
     * ========================================================
     */

    presetUploadRedirect('success');


} catch (Throwable $e) {

    error_log(
        'Lightboard preset upload error: '
        . $e->getMessage()
    );

    presetUploadRedirect('error');

}
