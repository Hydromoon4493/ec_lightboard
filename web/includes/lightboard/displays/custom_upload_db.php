<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../core/images.php';

/*
 * Author: Sam Vossen 2026.11.05
 * Filename: includes/lightboard/displays/custom_upload_db.php
 *
 * Handles uploads of custom Lightboard displays.
 *
 * Supported formats:
 * - PNG
 * - JPEG
 * - GIF
 * - WebP
 *
 * Static images:
 * - Processed using the existing GD image pipeline.
 * - Resized/cropped to the configured Lightboard dimensions.
 * - Converted into a CRGB pixel map.
 * - Stored in customDisplay.
 *
 * Animated GIFs:
 * - Processed using Imagick.
 * - GIF frames are coalesced to account for transparency and disposal.
 * - Each frame is resized/cropped to the configured Lightboard dimensions.
 * - Each frame is converted into a CRGB pixel map.
 * - Frame maps and delays are stored in customDisplayFrames.
 * - The first frame is stored in customDisplay for previews.
 *
 * This file does NOT generate Arduino sketches or communicate
 * with the ESP32. Sketch generation is handled by board.php
 * when a display job is queued.
 */

/* ============================================================
 * ANIMATION LIMITS
 * ============================================================
 *
 * These limits prevent excessively large GIF uploads from
 * creating oversized Arduino sketches or database records.
 */

// Maximum number of GIF frames supported.
const GIF_MAX_FRAMES = 50;

// Maximum total animation duration.
const GIF_MAX_TOTAL_DURATION_MS = 10000;

// Minimum allowed frame delay.
const GIF_MIN_FRAME_DELAY_MS = 20;

// Maximum allowed frame delay.
const GIF_MAX_FRAME_DELAY_MS = 2000;


/* ============================================================
 * REDIRECT HELPER
 * ============================================================
 *
 * Redirects the user back to the custom-display upload page
 * with a status query parameter.
 *
 * $status: upload status, such as success or error.
 */
function uploadRedirect(string $status): void
{
    if ($status === 'error') {
        $trace = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS,
            2
        );

        $line = isset($trace[0]['line'])
            ? (int)$trace[0]['line']
            : 0;

        $source = file(__FILE__);

        http_response_code(500);

        echo '<h2>Lightboard Upload Diagnostic</h2>';
        echo '<p>Failure location: custom_upload_db.php line '
            . $line . '</p>';

        echo '<pre>';

        $start = max(1, $line - 5);
        $end = min(count($source), $line + 5);

        for ($i = $start; $i <= $end; $i++) {
            echo ($i === $line ? '>>> ' : '    ');

            echo htmlspecialchars(
                $i . ': ' . $source[$i - 1],
                ENT_QUOTES,
                'UTF-8'
            );
        }

        echo '</pre>';

        exit;
    }

    header(
        'Location: ../../../upload.php?upload=' . urlencode($status)
    );

    exit;
}


/* ============================================================
 * PNG ENCODING HELPER
 * ============================================================
 *
 * Converts a GD image resource into PNG binary data.
 *
 * Used to store the first processed GIF frame as a normal
 * PNG image in customDisplay.display.
 *
 * $image: GD image resource.
 *
 * Returns PNG binary data.
 */
function encodePng($image): string
{
    ob_start();

    if (!imagepng($image)) {
        ob_end_clean();

        throw new RuntimeException(
            'Could not encode processed image as PNG.'
        );
    }

    $data = ob_get_clean();

    if ($data === false) {
        throw new RuntimeException(
            'Could not retrieve encoded PNG data.'
        );
    }

    return $data;
}


/* ============================================================
 * PROCESS ANIMATED GIF
 * ============================================================
 *
 * Processes a multi-frame GIF and converts each frame into
 * a Lightboard-compatible pixel map.
 *
 * Imagick is used instead of GD because Imagick supports
 * GIF frame sequences and disposal methods.
 *
 * Coalescing is important because GIF frames may contain
 * only changed portions of the image rather than a complete
 * replacement frame.
 *
 * $gifData: original uploaded GIF binary data.
 *
 * Returns:
 * [
 *     'firstPng' => first processed frame as PNG binary data,
 *     'firstMap' => first processed frame pixel map,
 *     'frames' => [
 *         [
 *             'pixel_map' => CRGB pixel map,
 *             'delayMs' => frame duration in milliseconds
 *         ],
 *         ...
 *     ]
 * ]
 */
function processAnimatedGif(string $gifData): array
{
    /* CHECK IMAGICK AVAILABILITY */

    if (!extension_loaded('imagick')) {
        throw new RuntimeException(
            'Animated GIF uploads require the Imagick PHP extension.'
        );
    }

    $gif = new Imagick();
    $coalesced = null;

    try {
        /* READ GIF DATA */

        if (!$gif->readImageBlob($gifData)) {
            throw new RuntimeException(
                'Could not read the uploaded GIF.'
            );
        }

        /*
         * Check the original frame count before processing.
         * This avoids unnecessary memory use for oversized GIFs.
         */
        $originalFrameCount = $gif->getNumberImages();

        if (
            $originalFrameCount < 2
            || $originalFrameCount > GIF_MAX_FRAMES
        ) {
            throw new RuntimeException(
                'GIF animations must contain between 2 and '
                . GIF_MAX_FRAMES
                . ' frames.'
            );
        }

        /*
         * Coalesce GIF frames.
         *
         * This reconstructs each frame using the previous
         * frames and the GIF disposal settings.
         */
        $coalesced = $gif->coalesceImages();

        $coalescedFrameCount = $coalesced->getNumberImages();

        /*
         * Validate the coalesced frame count.
         */
        if (
            $coalescedFrameCount < 2
            || $coalescedFrameCount > GIF_MAX_FRAMES
        ) {
            throw new RuntimeException(
                'The coalesced GIF contains an unsupported number of frames.'
            );
        }

        $frames = [];
        $firstPng = null;
        $firstMap = null;
        $totalDuration = 0;

        /* PROCESS EACH FRAME */

        /*
         * IMPORTANT:
         *
         * Use an indexed loop instead of foreach.
         *
         * The Lightboard GIF diagnostic confirmed that
         * Imagick foreach iteration can produce an additional
         * iteration beyond the reported frame count.
         *
         * setIteratorIndex() and getImage() allow us to
         * process exactly the number of frames reported
         * by getNumberImages().
         */
        for (
            $frameNumber = 0;
            $frameNumber < $coalescedFrameCount;
            $frameNumber++
        ) {
            $frame = null;
            $flattened = null;
            $gdImage = null;

            try {
                /*
                 * Select the exact frame by its index.
                 */
                if (!$coalesced->setIteratorIndex($frameNumber)) {
                    throw new RuntimeException(
                        'Could not select GIF frame '
                        . $frameNumber . '.'
                    );
                }

                /*
                 * Retrieve the selected frame.
                 */
                $frame = $coalesced->getImage();

                if (!$frame instanceof Imagick) {
                    throw new RuntimeException(
                        'Could not retrieve GIF frame '
                        . $frameNumber . '.'
                    );
                }

                /*
                 * Imagick returns GIF frame delays in centiseconds.
                 *
                 * Convert centiseconds to milliseconds:
                 * 1 centisecond = 10 milliseconds.
                 *
                 * Example:
                 * 5 centiseconds = 50 milliseconds.
                 */
                $rawDelay = (int)$frame->getImageDelay();

                $delay = $rawDelay * 10;

                /*
                 * Clamp delays to the supported range.
                 */
                $delay = max(
                    GIF_MIN_FRAME_DELAY_MS,
                    min(GIF_MAX_FRAME_DELAY_MS, $delay)
                );

                $totalDuration += $delay;

                if ($totalDuration > GIF_MAX_TOTAL_DURATION_MS) {
                    throw new RuntimeException(
                        'GIF animation duration exceeds 10 seconds.'
                    );
                }

                /*
                 * Flatten transparency against a black background.
                 *
                 * The Lightboard pixel-map pipeline expects a complete
                 * rectangular image rather than transparent regions.
                 */
                $frame->setImageBackgroundColor(
                    new ImagickPixel('black')
                );

                $flattened = $frame->mergeImageLayers(
                    Imagick::LAYERMETHOD_FLATTEN
                );

                /* CONVERT FRAME TO PNG */

                $flattened->setImageFormat('png');

                $frameBlob = $flattened->getImageBlob();

                if ($frameBlob === false || $frameBlob === '') {
                    throw new RuntimeException(
                        'Could not convert GIF frame '
                        . $frameNumber . ' to PNG.'
                    );
                }

                /*
                 * Use the existing image processing functions.
                 *
                 * buildImage() performs the normal Lightboard
                 * crop/resize operation.
                 *
                 * pixelMapC() converts the processed image
                 * into the Arduino CRGB pixel-map format.
                 */
                $gdImage = buildImage($frameBlob);

                if (!$gdImage) {
                    throw new RuntimeException(
                        'Could not process GIF frame '
                        . $frameNumber . ' with buildImage().'
                    );
                }

                try {
                    $pixelMap = pixelMapC($gdImage);

                    if (!is_string($pixelMap) || $pixelMap === '') {
                        throw new RuntimeException(
                            'Could not generate pixel map for GIF frame '
                            . $frameNumber . '.'
                        );
                    }

                    /* SAVE FIRST FRAME FOR THE PARENT RECORD */

                    if ($frameNumber === 0) {
                        $firstPng = encodePng($gdImage);
                        $firstMap = $pixelMap;
                    }

                } finally {
                    imagedestroy($gdImage);
                    $gdImage = null;
                }

                /* SAVE FRAME DATA */

                $frames[] = [
                    'pixel_map' => $pixelMap,
                    'delayMs' => $delay,
                ];

            } finally {
                /*
                 * Release resources for the current frame.
                 */
                if ($gdImage !== null) {
                    imagedestroy($gdImage);
                }

                if ($flattened instanceof Imagick) {
                    $flattened->clear();
                    $flattened->destroy();
                }

                if ($frame instanceof Imagick) {
                    $frame->clear();
                    $frame->destroy();
                }
            }
        }

        /*
         * Validate that all expected frames were processed.
         */
        if (
            count($frames) !== $coalescedFrameCount
            || count($frames) < 2
            || $firstPng === null
            || $firstMap === null
        ) {
            throw new RuntimeException(
                'Not all GIF animation frames were processed successfully.'
            );
        }

        return [
            'firstPng' => $firstPng,
            'firstMap' => $firstMap,
            'frames' => $frames,
        ];

    } finally {
        /*
         * Always release Imagick resources.
         */
        if ($coalesced instanceof Imagick) {
            $coalesced->clear();
            $coalesced->destroy();
        }

        $gif->clear();
        $gif->destroy();
    }
}


/* ============================================================
 * VALIDATE UPLOAD REQUEST
 * ============================================================
 *
 * This endpoint accepts POST requests containing an uploaded
 * image in the "image" form field.
 */

/* REQUIRE POST */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uploadRedirect('error');
}

/* CHECK FILE FIELD */

if (
    !isset($_FILES['image'])
    || !is_array($_FILES['image'])
) {
    uploadRedirect('error');
}

$file = $_FILES['image'];

/* CHECK PHP UPLOAD ERROR */

if (
    !isset($file['error'])
    || $file['error'] !== UPLOAD_ERR_OK
) {
    uploadRedirect('error');
}

/* CHECK UPLOAD SIZE */

global $CFG;

$maxBytes = (int)$CFG['max_bytes'];

if (
    !isset($file['size'])
    || (int)$file['size'] <= 0
    || (int)$file['size'] > $maxBytes
) {
    uploadRedirect('error');
}


/* ============================================================
 * READ AND VALIDATE IMAGE
 * ============================================================
 */

/* READ UPLOADED FILE */

$imageData = file_get_contents($file['tmp_name']);

if ($imageData === false || $imageData === '') {
    uploadRedirect('error');
}

/* DETECT IMAGE TYPE */

$imageInfo = @getimagesizefromstring($imageData);

if ($imageInfo === false || empty($imageInfo['mime'])) {
    uploadRedirect('error');
}

$mimeType = $imageInfo['mime'];

/* ALLOWED IMAGE FORMATS */

$allowedMimeTypes = [
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    uploadRedirect('error');
}


/* ============================================================
 * VALIDATE DISPLAY NAME
 * ============================================================
 */

$name = mb_substr(
    trim($_POST['name'] ?? ''),
    0,
    45
);

if ($name === '') {
    uploadRedirect('error');
}


/* ============================================================
 * GET USER INFORMATION
 * ============================================================
 *
 * A custom display may belong to a logged-in user.
 * If no user is logged in, userID is stored as NULL.
 */

$userID = isset($_SESSION['userID'])
    ? (int)$_SESSION['userID']
    : null;


/* ============================================================
 * DETERMINE WHETHER GIF IS ANIMATED
 * ============================================================
 *
 * A GIF with only one frame follows the normal static-image
 * pipeline. Only multi-frame GIFs use Imagick animation
 * processing and customDisplayFrames.
 */

$isAnimated = false;

if ($mimeType === 'image/gif') {
    if (!extension_loaded('imagick')) {
        uploadRedirect('error');
    }

    $probe = new Imagick();

    try {
        if (!$probe->readImageBlob($imageData)) {
            uploadRedirect('error');
        }

        $isAnimated = $probe->getNumberImages() > 1;

    } finally {
        $probe->clear();
        $probe->destroy();
    }
}


/* ============================================================
 * PROCESS STATIC IMAGE
 * ============================================================
 *
 * Static PNG, JPEG, WebP, and single-frame GIF uploads use
 * the original GD-based image pipeline.
 *
 * This section intentionally remains separate from the
 * animated GIF processing path.
 */

if (!$isAnimated) {
    try {
        /* RESIZE AND CROP IMAGE */

        $im = buildImage($imageData);

        try {
            /* GENERATE PIXEL MAP */

            $pixelMap = pixelMapC($im);

            /* ENCODE PROCESSED IMAGE */

            $displayData = encodePng($im);

        } finally {
            imagedestroy($im);
        }

        /* STORE STATIC DISPLAY */

        $pdo = db();

        $st = $pdo->prepare(
            'INSERT INTO customDisplay
                (userID, name, mimeType, display, pixel_map, isAnimated)
             VALUES (?, ?, ?, ?, ?, 0)'
        );

        $st->execute([
            $userID,
            $name,
            'image/png',
            $displayData,
            $pixelMap,
        ]);

        uploadRedirect('success');

    } catch (Throwable $e) {
        error_log(
            'Lightboard image upload error: '
            . $e->getMessage()
        );

        http_response_code(500);

        echo '<h2>Lightboard Image Upload Error</h2>';
        echo '<pre>';
        echo htmlspecialchars(
            get_class($e) . ': ' . $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        );
        echo '</pre>';

        exit;
    }
}


/* ============================================================
 * PROCESS ANIMATED GIF
 * ============================================================
 *
 * Multi-frame GIFs are processed into:
 *
 * 1. A parent customDisplay record containing:
 *    - display name
 *    - first frame PNG
 *    - first frame pixel map
 *    - isAnimated = 1
 *
 * 2. Multiple customDisplayFrames records containing:
 *    - displayID
 *    - frameNumber
 *    - pixel_map
 *    - delayMs
 *
 * The parent and child records are inserted inside one
 * database transaction so partial animations are not saved.
 */

try {
    /* PROCESS GIF FRAMES */

    $animation = processAnimatedGif($imageData);

    $firstPng = $animation['firstPng'];
    $firstMap = $animation['firstMap'];
    $frames = $animation['frames'];

    /* BEGIN DATABASE TRANSACTION */

    $pdo = db();

    $pdo->beginTransaction();

    try {
        /* INSERT PARENT CUSTOM DISPLAY */

        $st = $pdo->prepare(
            'INSERT INTO customDisplay
                (userID, name, mimeType, display, pixel_map, isAnimated)
             VALUES (?, ?, ?, ?, ?, 1)'
        );

        $st->execute([
            $userID,
            $name,
            'image/png',
            $firstPng,
            $firstMap,
        ]);

        /*
         * Retrieve the newly created customDisplay ID.
         * Every animation frame references this parent record.
         */
        $displayID = (int)$pdo->lastInsertId();

        if ($displayID <= 0) {
            throw new RuntimeException(
                'Could not create the animated display record.'
            );
        }

        /* PREPARE FRAME INSERT */

        $frameInsert = $pdo->prepare(
            'INSERT INTO customDisplayFrames
                (displayID, frameNumber, pixel_map, delayMs)
             VALUES (?, ?, ?, ?)'
        );

        /*
         * Insert frames in their original order.
         *
         * Frame numbering starts at zero.
         */
        foreach ($frames as $frameNumber => $frame) {
            $frameInsert->execute([
                $displayID,
                $frameNumber,
                $frame['pixel_map'],
                $frame['delayMs'],
            ]);
        }

        /* COMMIT TRANSACTION */

        $pdo->commit();

    } catch (Throwable $e) {
        /*
         * If any frame fails to insert, remove the parent
         * and all previously inserted frames by rolling back.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    uploadRedirect('success');

} catch (Throwable $e) {
    error_log(
        'Lightboard animated GIF upload error: '
        . $e->getMessage()
    );

    http_response_code(500);

    echo '<h2>Animated GIF Upload Exception</h2>';

    echo '<p><strong>Exception:</strong> '
        . htmlspecialchars(
            get_class($e),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</p>';

    echo '<p><strong>Message:</strong> '
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</p>';

    echo '<p><strong>File:</strong> '
        . htmlspecialchars(
            basename($e->getFile()),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</p>';

    echo '<p><strong>Line:</strong> '
        . (int)$e->getLine()
        . '</p>';

    exit;
}
