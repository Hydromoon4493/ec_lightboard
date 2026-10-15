<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.15
 *
 * This file contains the Lightboard display API logic.
 *
 * Responsibilities:
 * - Receive and validate display requests.
 * - Retrieve the requested image.
 * - Create or retrieve the board-ready pixel map.
 * - Pass the pixel map to the board uploader.
 * - Report display progress and errors.
 * - Provide the server-side logic used by the Lightboard
 *   display AJAX/streaming endpoint.
 *
 * This file does NOT:
 * - Define database connections.
 * - Define image-source configuration.
 * - Process raw images directly.
 * - Generate pixel maps directly.
 * - Generate Arduino sketches directly.
 * - Contain HTML markup.
 *
 * Database functionality is provided by database.php.
 * Image functionality is provided by images.php.
 * ESP32 upload functionality is provided by board.php.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/board.php';


/* ============================================================
 * DISPLAY IMAGE
 * ============================================================
 *
 * Coordinates the complete process of displaying an image:
 *
 *     database
 *         ↓
 *     image / pixel map
 *         ↓
 *     Arduino sketch
 *         ↓
 *     ESP32
 *
 * The optional status callback allows the caller to stream
 * progress messages back to the browser.
 */
function displayImage(
    string $key,
    int $id,
    int $brightness,
    ?callable $status = null
): array {
    /*
     * Send a status message to the caller when a callback
     * has been provided.
     */
    $say = static function (string $message) use ($status): void {
        if ($status) {
            $status($message);
        }
    };


    /* ========================================================
     * LOAD IMAGE
     * ======================================================== */

    $say('Loading image...');

    $row = getImageForDisplay($key, $id);

    if (!$row) {
        throw new RuntimeException(
            'The requested image could not be found.'
        );
    }


    /* ========================================================
     * GET OR CREATE PIXEL MAP
     * ======================================================== */

    $pixelData = getOrCreatePixelMap(
        $key,
        $id,
        $row,
        $say
    );


    /* ========================================================
     * SEND IMAGE TO BOARD
     * ======================================================== */

    $say('Preparing Lightboard upload...');

    $result = flashBoard(
        $pixelData['pixel_map'],
        $brightness,
        $say
    );


    /* ========================================================
     * RETURN RESULT
     * ======================================================== */

    return [
        'code' => $result['code'],
        'log' => $result['log'],
        'cached' => $pixelData['cached'],
    ];
}
