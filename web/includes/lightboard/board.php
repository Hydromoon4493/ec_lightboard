<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.15
 * Filename: includes/lightboard/board.php
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

    /*
     * Only one upload at a time: two tabs or a double click
     * must never run two arduino-cli processes on one port.
     */
    $lock = fopen(sys_get_temp_dir() . '/lightboard-upload.lock', 'c');

    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException(
            'The Lightboard is already being updated. Wait for that upload to finish and try again.'
        );
    }

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
     * COMPILE AND UPLOAD (streamed)
     * ======================================================== */

    try {
        $say('Compiling sketch (the first build can take a few minutes)...');

        $command = [
            'timeout', '-k', '5', (string)$CFG['timeout'],
            $CFG['arduino_cli'], 'compile', '--upload',
            '-p', $CFG['port'],
            '--fqbn', $CFG['fqbn'],
            '.',
        ];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],   // stderr merged into stdout
            ],
            $pipes,
            $dir,
            array_merge(getenv(), ['PYTHONUNBUFFERED' => '1'])
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start arduino-cli.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);

        $log       = [];
        $lastPct   = -10;
        $compiled  = false;
        $connected = false;
        $started   = time();
        $lastNote  = time();

        /*
         * Turns arduino-cli / esptool output into friendly status
         * messages. Upload percentages are reported every 10%.
         */
        $handleLine = function (string $line) use (
            &$log, &$lastPct, &$compiled, &$connected, &$lastNote, $say
        ): void {
            $line = trim($line);

            if ($line === '') {
                return;
            }

            if (preg_match('/Writing at .*\((\d+)\s*%\)/', $line, $m)) {
                $pct = (int)$m[1];

                if ($pct >= $lastPct + 10 || $pct === 100) {
                    $lastPct = $pct;
                    $lastNote = time();
                    $say("Uploading to the ESP32... {$pct}%");
                }

                return;
            }

            $log[] = $line;

            if (!$compiled && stripos($line, 'Sketch uses') !== false) {
                $compiled = true;
                $lastNote = time();
                $say('Compile finished.');
            } elseif (!$connected && stripos($line, 'Connecting') !== false) {
                $connected = true;
                $lastNote = time();
                $say('Connecting to the ESP32...');
            } elseif (stripos($line, 'Hash of data verified') !== false) {
                $say('Upload verified.');
            } elseif (stripos($line, 'Hard resetting') !== false) {
                $say('Restarting the board...');
            }
        };

        $buffer   = '';
        $exitCode = null;

        while (true) {
            $read = [$pipes[1]];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) > 0) {
                $chunk = fread($pipes[1], 8192);

                if ($chunk !== false && $chunk !== '') {
                    $buffer .= $chunk;

                    // esptool uses \r for progress lines, so split on both.
                    $parts  = preg_split('/[\r\n]+/', $buffer);
                    $buffer = (string)array_pop($parts);

                    foreach ($parts as $part) {
                        $handleLine($part);
                    }
                }
            }

            $info = proc_get_status($process);

            if (!$info['running']) {
                $exitCode = $info['exitcode'];
                $buffer  .= (string)stream_get_contents($pipes[1]);

                foreach (preg_split('/[\r\n]+/', $buffer) as $part) {
                    $handleLine($part);
                }

                break;
            }

            // Heartbeat while compiling: arduino-cli prints nothing then.
            if (!$compiled && !$connected && time() - $lastNote >= 15) {
                $lastNote = time();
                $say('Still compiling... ' . (time() - $started) . 's elapsed');
            }
        }

        fclose($pipes[1]);
        proc_close($process);

        if ($exitCode === 124 || $exitCode === 137) {
            throw new RuntimeException(
                'Arduino upload timed out. The compile/upload did not finish before the configured timeout.'
            );
        }

        if ($exitCode !== 0) {
            $tail = trim(implode("\n", array_slice($log, -30)));

            throw new RuntimeException(
                "Arduino compile/upload failed (exit code $exitCode):\n\n"
                . ($tail !== '' ? $tail : 'arduino-cli exited without providing an error message.')
            );
        }

    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    return [
        'code' => 0,
        'log'  => implode("\n", $log),
    ];
}
