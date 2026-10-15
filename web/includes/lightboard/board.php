<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.15
 *
 * This file contains the functions responsible for preparing
 * an Arduino sketch and uploading it to the Lightboard ESP32.
 *
 * Responsibilities:
 * - Create the Lightboard sketch directory.
 * - Load the Arduino sketch template.
 * - Insert the generated pixel map and brightness settings.
 * - Write the generated .ino sketch to disk.
 * - Save the pixel map used for the upload.
 * - Compile the Arduino sketch.
 * - Upload the compiled sketch to the ESP32.
 * - Report progress and return the Arduino CLI output.
 *
 * This file does NOT:
 * - Retrieve images from the database.
 * - Process images.
 * - Generate pixel maps.
 * - Handle HTML rendering.
 * - Handle API requests.
 *
 * Configuration is provided by config.php.
 */

require_once __DIR__ . '/../config.php';


/* ============================================================
 * FLASH IMAGE TO LIGHTBOARD
 * ============================================================
 *
 * Takes a generated FastLED CRGB image definition and uploads
 * it to the ESP32.
 *
 * $imageC:
 *     Generated CRGB pixel-map string.
 *
 * $brightness:
 *     Brightness value inserted into the Arduino sketch.
 *
 * $status:
 *     Optional callback used to send progress messages back
 *     to the caller.
 */
function flashBoard(
    string $imageC,
    int $brightness,
    ?callable $status = null
): array {
    global $CFG;

    /*
     * Helper used to report progress.
     */
    $say = static function (string $message) use ($status): void {
        if ($status) {
            $status($message);
        }
    };


    /* ========================================================
     * CREATE SKETCH DIRECTORY
     * ======================================================== */

    $say('Creating Arduino sketch...');

    $dir = $CFG['sketch_root'] . '/' . $CFG['sketch_name'];

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException(
            "Cannot create sketch folder: $dir"
        );
    }


    /* ========================================================
     * LOAD ARDUINO TEMPLATE
     * ======================================================== */

    /*
     * board.php is located at:
     *
     *     web/includes/lightboard/board.php
     *
     * The Arduino template is located at:
     *
     *     web/templates/webImage.ino.tpl
     *
     * Therefore we move:
     *
     *     lightboard -> includes -> web
     *
     * and then enter templates/.
     */
    $templatePath = __DIR__ . '/../../templates/webImage.ino.tpl';

    $tpl = file_get_contents($templatePath);

    if ($tpl === false) {
        throw new RuntimeException(
            'Missing templates/webImage.ino.tpl.'
        );
    }


    /* ========================================================
     * GENERATE ARDUINO SKETCH
     * ======================================================== */

    $sketch = str_replace(
        [
            '__GENERATED__',
            '__BRIGHTNESS__',
            '__IMAGE__'
        ],
        [
            date('c'),
            (string)$brightness,
            $imageC
        ],
        $tpl
    );


    /* ========================================================
     * WRITE ARDUINO SKETCH
     * ======================================================== */

    $ino = $dir . '/' . $CFG['sketch_name'] . '.ino';

    if (file_put_contents($ino, $sketch) === false) {
        throw new RuntimeException(
            "Cannot write Arduino sketch: $ino"
        );
    }


    /* ========================================================
     * SAVE PIXEL MAP
     * ======================================================== */

    $pixelMapPath = $dir . '/pixel_map.txt';

    if (
        file_put_contents(
            $pixelMapPath,
            $imageC . "\n"
        ) === false
    ) {
        throw new RuntimeException(
            "Cannot write pixel_map.txt in $dir."
        );
    }


    /* ========================================================
     * COMPILE AND UPLOAD
     * ======================================================== */

    $say('Compiling image and sending it to the ESP32...');

    /*
     * Arduino CLI performs the compilation and USB upload
     * through the same command.
     *
     * timeout prevents a failed/unresponsive board from
     * leaving the PHP request running indefinitely.
     */
    $cmd = sprintf(
        'cd %s && timeout %d %s compile --upload -p %s --fqbn %s . 2>&1',
        escapeshellarg($dir),
        $CFG['timeout'],
        escapeshellarg($CFG['arduino_cli']),
        escapeshellarg($CFG['port']),
        escapeshellarg($CFG['fqbn'])
    );

    $lines = [];
    $code = 0;

    exec($cmd, $lines, $code);


    /* ========================================================
     * HANDLE TIMEOUT
     * ======================================================== */

    if ($code === 124) {
        throw new RuntimeException(
            'Arduino upload timed out. The ESP32 did not finish responding before the configured timeout.'
        );
    }


    /* ========================================================
     * HANDLE ARDUINO CLI FAILURE
     * ======================================================== */

    if ($code !== 0) {
        $output = trim(implode("\n", $lines));

        $useful = $output !== ''
            ? $output
            : 'arduino-cli exited without providing an error message.';

        throw new RuntimeException(
            "Arduino compile/upload failed (exit code $code):\n\n$useful"
        );
    }


    /* ========================================================
     * COMPLETE
     * ======================================================== */

    $say('Sending image to board...');

    /*
     * arduino-cli --upload performs compilation and USB
     * transfer as part of the same command.
     *
     * Therefore the exact point where compilation ends and
     * uploading begins is not available to this PHP process.
     */
    $say('Success. Image displayed on the board.');


    /* ========================================================
     * RETURN RESULT
     * ======================================================== */

    return [
        'code' => 0,
        'log' => implode("\n", $lines),
    ];
}
