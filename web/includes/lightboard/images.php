<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.15
 * Filename: includes/lightboard/images.php 
 *
 * This file contains the Lightboard image-processing and
 * image-retrieval functions.
 *
 * Responsibilities:
 * - Retrieve Lightboard images from the database.
 * - Retrieve original image data for processing.
 * - Retrieve thumbnail/display image data.
 * - Resize and crop images to the configured board size.
 * - Convert images into FastLED CRGB pixel-map data.
 * - Reconstruct GD images from cached pixel maps.
 * - Create and cache pixel maps when one does not already exist.
 * - Generate enlarged PNG previews for the web interface.
 *
 * This file does NOT:
 * - Handle HTML rendering.
 * - Handle file uploads directly.
 * - Handle ESP32 flashing.
 * - Handle API requests.
 * - Manage the database connection directly.
 *
 * Database functions such as db(), dbRetry(), and src() are
 * provided by database.php.
 */

require_once __DIR__ . '/database.php';


/* ============================================================
 * LIST IMAGES
 * ============================================================
 *
 * Retrieves the images belonging to a specific Lightboard
 * image source.
 *
 * The source is identified by:
 *
 *     p = preset displays
 *     c = custom uploads
 */
function listImages(string $key): array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s): array {
        $sql = sprintf(
            "SELECT
                %s AS id,
                name,
                mimeType
            FROM %s
            ORDER BY name ASC",
            $s['id'],
            $s['table']
        );

        return $pdo
            ->query($sql)
            ->fetchAll();
    });
}


/* ============================================================
 * GET IMAGE FOR DISPLAY
 * ============================================================
 *
 * Retrieves the information needed to display an image in
 * the Lightboard gallery.
 *
 * The original image data is intentionally not returned here.
 * This keeps normal gallery requests smaller.
 */
function getImageForDisplay(string $key, int $id): ?array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s, $id): ?array {
        $st = $pdo->prepare(
            "SELECT
                {$s['id']} AS id,
                name,
                mimeType,
                pixel_map
             FROM {$s['table']}
             WHERE {$s['id']} = ?"
        );

        $st->execute([$id]);

        $row = $st->fetch();

        return $row ?: null;
    });
}


/* ============================================================
 * GET ORIGINAL IMAGE
 * ============================================================
 *
 * Retrieves the original image data stored in the database.
 *
 * The display column contains the actual binary image data.
 *
 * This function is used when a cached pixel map does not exist
 * and the application needs to build one from the original
 * image.
 */
function getOriginalImage(string $key, int $id): ?array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s, $id): ?array {
        $st = $pdo->prepare(
            "SELECT
                {$s['id']} AS id,
                name,
                mimeType,
                display
             FROM {$s['table']}
             WHERE {$s['id']} = ?"
        );

        $st->execute([$id]);

        $row = $st->fetch();

        return $row ?: null;
    });
}


/* ============================================================
 * GET IMAGE FOR THUMBNAIL
 * ============================================================
 *
 * Retrieves the image data used by the thumbnail endpoint.
 *
 * The original image is returned so the caller can construct
 * the appropriate browser response.
 */
function getImageForThumbnail(string $key, int $id): ?array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s, $id): ?array {
        $st = $pdo->prepare(
            "SELECT
                {$s['id']} AS id,
                name,
                mimeType,
                display
             FROM {$s['table']}
             WHERE {$s['id']} = ?"
        );

        $st->execute([$id]);

        $row = $st->fetch();

        return $row ?: null;
    });
}


/* ============================================================
 * BUILD BOARD-READY IMAGE
 * ============================================================
 *
 * Loads an image from its binary data and converts it into the
 * configured Lightboard dimensions.
 *
 * If the image is already the correct size, it is returned
 * without resizing.
 *
 * If automatic resizing is enabled, the image is center-cropped
 * into a square and resized to the configured board dimensions.
 */
// function buildImage(string $data): GdImage -> USE AFTER PHP UPDATE
/** @return resource|GdImage */
function buildImage(string $data)
{
    global $CFG;

    $im = @imagecreatefromstring($data);

    if ($im === false) {
        throw new RuntimeException(
            'Could not read that as an image (use PNG, JPG, GIF or WebP).'
        );
    }

    $n = $CFG['size'];
    $w = imagesx($im);
    $h = imagesy($im);

    /*
     * The image is already the correct board size.
     */
    if ($w === $n && $h === $n) {
        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($im);
        }

        return $im;
    }

    /*
     * Automatic resizing is disabled.
     */
    if (!$CFG['auto_resize']) {
        imagedestroy($im);

        throw new RuntimeException(
            sprintf(
                'Image must be exactly %dx%d (got %dx%d).',
                $n,
                $n,
                $w,
                $h
            )
        );
    }

    /*
     * Crop the image to a square using the smaller dimension.
     */
    $side = min($w, $h);

    $out = imagecreatetruecolor($n, $n);

    imagealphablending($out, true);

    imagefill(
        $out,
        0,
        0,
        imagecolorallocate($out, 0, 0, 0)
    );

    /*
     * Center-crop and resize.
     */
    imagecopyresampled(
        $out,
        $im,
        0,
        0,
        intdiv($w - $side, 2),
        intdiv($h - $side, 2),
        $n,
        $n,
        $side,
        $side
    );

    imagedestroy($im);

    return $out;
}


/* ============================================================
 * CREATE FASTLED PIXEL MAP
 * ============================================================
 *
 * Converts a GD image into the C++ CRGB array format used by
 * the Lightboard Arduino sketch.
 */
// function pixelMapC(GdImage $im): string -> USER AFTER PHP VERSION UPDATE
function pixelMapC($im): string
{
    global $CFG;

    $n = $CFG['size'];

    $out = "CRGB image[$n][$n] = {\n";

    for ($y = 0; $y < $n; $y++) {
        $row = [];

        for ($x = 0; $x < $n; $x++) {
            $c = imagecolorat($im, $x, $y);

            $row[] = sprintf(
                'CRGB(%d,%d,%d)',
                ($c >> 16) & 255,
                ($c >> 8) & 255,
                $c & 255
            );
        }

        $out .= ' {' . implode(',', $row) . ' }'
              . ($y < $n - 1 ? ',' : '')
              . "\n";
    }

    return $out . "};";
}


/* ============================================================
 * RECREATE IMAGE FROM PIXEL MAP
 * ============================================================
 *
 * Converts a stored CRGB pixel map back into a GD image.
 */
// function imageFromPixelMap(string $pixelMap): GdImage -> USER AFTER PHP VERSION UPDATE
function imageFromPixelMap(string $pixelMap)
{
    global $CFG;

    $n = $CFG['size'];

    /*
     * Find every CRGB(r,g,b) value.
     */
    $count = preg_match_all(
        '/CRGB\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/',
        $pixelMap,
        $matches,
        PREG_SET_ORDER
    );

    /*
     * There must be exactly one RGB value for every
     * pixel on the board.
     */
    if ($count !== ($n * $n)) {
        throw new RuntimeException(
            sprintf(
                'Invalid pixel map: expected %d pixels, found %d.',
                $n * $n,
                $count
            )
        );
    }

    $im = imagecreatetruecolor($n, $n);

    $i = 0;

    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {

            $r = (int)$matches[$i][1];
            $g = (int)$matches[$i][2];
            $b = (int)$matches[$i][3];

            imagesetpixel(
                $im,
                $x,
                $y,
                ($r << 16) | ($g << 8) | $b
            );

            $i++;
        }
    }

    return $im;
}


/* ============================================================
 * GET OR CREATE CACHED PIXEL MAP
 * ============================================================
 *
 * Uses the cached pixel map when available.
 *
 * Otherwise:
 * 1. Loads the original image.
 * 2. Converts it to the board dimensions.
 * 3. Creates the CRGB pixel map.
 * 4. Saves the pixel map to the database.
 * 5. Returns the generated image and pixel map.
 *
 * The optional status callback allows the caller to report
 * progress to the browser.
 */
function getOrCreatePixelMap(
    string $key,
    int $id,
    array $row,
    ?callable $status = null
): array {
    $s = src($key);

    $say = static function (string $message) use ($status): void {
        if ($status) {
            $status($message);
        }
    };

    /*
     * Use the cached pixel map when one exists.
     */
    if (!empty($row['pixel_map'])) {
        try {
            $pixelMap = (string)$row['pixel_map'];
            $im = imageFromPixelMap($pixelMap);
            $say('Pixel map found in database.');

            return ['pixel_map' => $pixelMap, 'image' => $im, 'cached' => true];

        } catch (RuntimeException $e) {
            $say('Saved pixel map is invalid. Rebuilding from the original image...');
        }
    }

    /*
     * No cached map exists, so retrieve the original image.
     */
    $say('No pixel map found. Loading original image...');

    $original = getOriginalImage($key, $id);

    if (!$original || !isset($original['display'])) {
        throw new RuntimeException(
            'The original image is missing from the database.'
        );
    }

    /*
     * Convert the original image into a board-sized image.
     */
    $say(
        sprintf(
            'Creating %d×%d pixel map...',
            $GLOBALS['CFG']['size'],
            $GLOBALS['CFG']['size']
        )
    );

    $im = buildImage((string)$original['display']);

    /*
     * Convert the image into FastLED CRGB data.
     */
    $pixelMap = pixelMapC($im);

    /*
     * Save the generated map for future requests.
     */
    $say('Saving pixel map to database...');

    dbRetry(function (PDO $pdo) use ($s, $pixelMap, $id): void {
        $st = $pdo->prepare(
            "UPDATE {$s['table']}
             SET pixel_map = ?
             WHERE {$s['id']} = ?"
        );

        $st->execute([$pixelMap, $id]);
    });

    $say('Pixel map saved successfully.');

    return [
        'pixel_map' => $pixelMap,
        'image' => $im,
        'cached' => false,
    ];
}


/* ============================================================
 * CREATE BROWSER PREVIEW
 * ============================================================
 *
 * Enlarges a board-sized image using nearest-neighbor scaling
 * so individual LED pixels remain sharp.
 *
 * Returns a base64 PNG data URI suitable for an HTML <img>.
 */
// function previewDataUri(GdImage $im): string -> USER AFTER PHP VERSION UPDATE
function previewDataUri($im): string
{
    $big = imagescale(
        $im,
        384,
        384,
        IMG_NEAREST_NEIGHBOUR
    );

    ob_start();

    imagepng($big);

    $png = (string)ob_get_clean();

    imagedestroy($big);

    return 'data:image/png;base64,' . base64_encode($png);
}
