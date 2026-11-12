<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.11.05
 * Filename: includes/lightboard/hardware/board.php
 *
 * Functions for preparing static and animated Arduino sketches and
 * (optionally) uploading a static sketch to the Lightboard ESP32.
 *
 * - buildSketch():          template + pixel map + brightness -> .ino text.
 *                            Used for normal static images.
 * - buildAnimatedSketch():  animation frames + delays + brightness -> .ino text.
 *                            Used for animated GIFs.
 * - flashBoard():           builds, compiles and uploads locally with arduino-cli.
 *                            Kept for local testing; the hosted site does not use it.
 *
 * Static and animated sketches use separate templates:
 * - templates/webImage.ino.tpl
 * - templates/animatedImage.ino.tpl
 *
 * This file does NOT retrieve images, generate pixel maps, render HTML
 * or handle API requests.
 */

/* ============================================================
 * BUILD STATIC SKETCH
 * ============================================================
 *
 * Returns the complete Arduino sketch text for a static image.
 *
 * $imageC:     generated CRGB pixel-map string.
 * $brightness: brightness value inserted into the sketch.
 *
 * The existing static template and replacement behavior are preserved.
 */
function buildSketch(string $imageC, int $brightness): string
{
    $templatePath = __DIR__ . '/../../../templates/webImage.ino.tpl';
    $tpl = file_get_contents($templatePath);

    if ($tpl === false) {
        throw new RuntimeException('Missing templates/webImage.ino.tpl.');
    }

    return str_replace(
        ['__GENERATED__', '__BRIGHTNESS__', '__IMAGE__'],
        [date('c'), (string)$brightness, $imageC],
        $tpl
    );
}


/* ============================================================
 * VALIDATE ANIMATION PIXEL MAP
 * ============================================================
 *
 * Converts one stored pixel-map declaration into the initializer
 * used inside the Arduino animation frame array.
 *
 * Stored pixel maps have this format:
 *
 * CRGB image[48][48] = {
 *     ...
 * };
 *
 * The generated animation template needs only the initializer:
 *
 * {
 *     ...
 * }
 *
 * $pixelMap: pixel-map declaration stored in customDisplayFrames.
 */
function pixelMapInitializer(string $pixelMap): string
{
    global $CFG;

    $size = (int)$CFG['size'];

    $pattern = '/^\s*CRGB\s+image\['
        . $size
        . '\]\['
        . $size
        . '\]\s*=\s*(\{.*\})\s*;\s*$/s';

    if (!preg_match($pattern, $pixelMap, $matches)) {
        throw new RuntimeException(
            'An animation frame contains an invalid pixel map.'
        );
    }

    $initializer = $matches[1];

    // Each pixel is generated as a CRGB(r, g, b) expression.
    $count = preg_match_all('/CRGB\s*\(/', $initializer);

    if ($count !== ($size * $size)) {
        throw new RuntimeException(
            'An animation frame has an incorrect pixel count.'
        );
    }

    return $initializer;
}


/* ============================================================
 * BUILD ANIMATED GIF SKETCH
 * ============================================================
 *
 * Returns the complete Arduino sketch text for an animated image.
 *
 * $frames:     rows from customDisplayFrames, ordered by frameNumber.
 * $brightness: brightness value inserted into the sketch.
 *
 * Each frame contains:
 * - pixel_map: the generated CRGB pixel map.
 * - delayMs:   how long the frame should remain displayed.
 *
 * Animation limits:
 * - Minimum frames:       2
 * - Maximum frames:       30
 * - Minimum frame delay:  20 milliseconds
 * - Maximum frame delay:  2000 milliseconds
 * - Maximum total length: 10000 milliseconds
 *
 * This function uses animatedImage.ino.tpl.
 * It does NOT modify or use the static webImage.ino.tpl.
 */
function buildAnimatedSketch(array $frames, int $brightness): string
{
    global $CFG;

    $frameCount = count($frames);

    // Reject animations outside the supported frame count.
    if ($frameCount < 2 || $frameCount > 50) {
    throw new RuntimeException(
        'Animated displays must contain between 2 and 50 frames.'
        );
    }

    $initializers = [];
    $delays = [];
    $totalDuration = 0;

    /*
     * Validate each frame and prepare its Arduino initializer.
     *
     * The frame rows must already be ordered by frameNumber.
     */
    foreach ($frames as $frame) {
        if (!isset($frame['pixel_map'], $frame['delayMs'])) {
            throw new RuntimeException(
                'An animation frame is missing required data.'
            );
        }

        $delay = (int)$frame['delayMs'];

        if ($delay < 20 || $delay > 2000) {
            throw new RuntimeException(
                'An animation frame has an invalid duration.'
            );
        }

        $totalDuration += $delay;

        if ($totalDuration > 10000) {
            throw new RuntimeException(
                'Animation duration exceeds 10 seconds.'
            );
        }

        $initializers[] = pixelMapInitializer(
            (string)$frame['pixel_map']
        );

        $delays[] = (string)$delay;
    }

    /* LOAD ANIMATED ARDUINO TEMPLATE */

    $templatePath = __DIR__ . '/../../templates/animatedImage.ino.tpl';
    $tpl = file_get_contents($templatePath);

    if ($tpl === false) {
        throw new RuntimeException(
            'Missing templates/animatedImage.ino.tpl.'
        );
    }

    $size = (int)$CFG['size'];

    /*
     * Replace the animation template placeholders.
     *
     * __FRAMES__ contains all frame pixel initializers.
     * __FRAME_DELAYS__ contains the matching frame durations.
     */
    return str_replace(
        [
            '__GENERATED__',
            '__BRIGHTNESS__',
            '__FRAME_COUNT__',
            '__BOARD_SIZE__',
            '__FRAMES__',
            '__FRAME_DELAYS__',
        ],
        [
            date('c'),
            (string)max(1, min(100, $brightness)),
            (string)$frameCount,
            (string)$size,
            implode(",\n", $initializers),
            implode(', ', $delays),
        ],
        $tpl
    );
}


/* ============================================================
 * FLASH IMAGE TO LIGHTBOARD (LOCAL TESTING)
 * ============================================================
 *
 * Builds, compiles and uploads a static sketch directly to the ESP32.
 *
 * The hosted website does not call this function. The hosted queue
 * stores generated sketch text for the Raspberry Pi worker instead.
 *
 * $imageC:     generated CRGB pixel-map string.
 * $brightness: brightness value inserted into the sketch.
 * $status:     optional callback used to report progress messages.
 *
 * Returns:
 * - code: process exit code.
 * - log:  Arduino CLI output.
 */
function flashBoard(
    string $imageC,
    int $brightness,
    ?callable $status = null
): array {
    global $CFG;

    /*
     * Send progress messages to the optional callback.
     */
    $say = static function (string $message) use ($status): void {
        if ($status) {
            $status($message);
        }
    };

    /*
     * Only one upload at a time.
     *
     * Two tabs or a double click must never run two arduino-cli
     * processes against the same serial port.
     */
    $lock = fopen(
        sys_get_temp_dir() . '/lightboard-upload.lock',
        'c'
    );

    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException(
            'The Lightboard is already being updated. '
            . 'Wait for that upload to finish and try again.'
        );
    }

    try {
        /* CREATE SKETCH DIRECTORY */

        $say('Creating Arduino sketch...');

        $dir = $CFG['sketch_root'] . '/' . $CFG['sketch_name'];

        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException(
                "Cannot create sketch folder: $dir"
            );
        }

        /* GENERATE AND WRITE ARDUINO SKETCH */

        $sketch = buildSketch($imageC, $brightness);

        $ino = $dir . '/' . $CFG['sketch_name'] . '.ino';

        if (file_put_contents($ino, $sketch) === false) {
            throw new RuntimeException(
                "Cannot write Arduino sketch: $ino"
            );
        }

        /* SAVE PIXEL MAP */

        $pixelMapPath = $dir . '/pixel_map.txt';

        if (file_put_contents(
            $pixelMapPath,
            $imageC . "\n"
        ) === false) {
            throw new RuntimeException(
                "Cannot write pixel_map.txt in $dir."
            );
        }

        /* COMPILE AND UPLOAD (STREAMED) */

        $say(
            'Compiling sketch (the first build can take a few minutes)...'
        );

        $command = [
            'timeout',
            '-k',
            '5',
            (string)$CFG['timeout'],
            $CFG['arduino_cli'],
            'compile',
            '--upload',
            '-p',
            $CFG['port'],
            '--fqbn',
            $CFG['fqbn'],
            '.',
        ];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1], // stderr merged into stdout
            ],
            $pipes,
            $dir,
            array_merge(
                getenv(),
                ['PYTHONUNBUFFERED' => '1']
            )
        );

        if (!is_resource($process)) {
            throw new RuntimeException(
                'Could not start arduino-cli.'
            );
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);

        $log = [];
        $lastPct = -10;
        $compiled = false;
        $connected = false;
        $started = time();
        $lastNote = time();

        /*
         * Convert arduino-cli / esptool output into friendly status
         * messages. Upload percentages are reported every 10%.
         */
        $handleLine = function (string $line) use (
            &$log,
            &$lastPct,
            &$compiled,
            &$connected,
            &$lastNote,
            $say
        ): void {
            $line = trim($line);

            if ($line === '') {
                return;
            }

            if (preg_match(
                '/Writing at .*\((\d+)\s*%\)/',
                $line,
                $m
            )) {
                $pct = (int)$m[1];

                if ($pct >= $lastPct + 10 || $pct === 100) {
                    $lastPct = $pct;
                    $lastNote = time();

                    $say(
                        "Uploading to the ESP32... {$pct}%"
                    );
                }

                return;
            }

            $log[] = $line;

            if (
                !$compiled
                && stripos($line, 'Sketch uses') !== false
            ) {
                $compiled = true;
                $lastNote = time();
                $say('Compile finished.');
            } elseif (
                !$connected
                && stripos($line, 'Connecting') !== false
            ) {
                $connected = true;
                $lastNote = time();
                $say('Connecting to the ESP32...');
            } elseif (
                stripos($line, 'Hash of data verified') !== false
            ) {
                $say('Upload verified.');
            } elseif (
                stripos($line, 'Hard resetting') !== false
            ) {
                $say('Restarting the board...');
            }
        };

        $buffer = '';
        $exitCode = null;

        /*
         * Read process output until arduino-cli exits.
         *
         * esptool uses carriage returns for upload progress,
         * so the buffer is split on both carriage returns and
         * newline characters.
         */
        while (true) {
            $read = [$pipes[1]];
            $write = null;
            $except = null;

            if (stream_select(
                $read,
                $write,
                $except,
                1
            ) > 0) {
                $chunk = fread($pipes[1], 8192);

                if ($chunk !== false && $chunk !== '') {
                    $buffer .= $chunk;

                    $parts = preg_split(
                        '/[\r\n]+/',
                        $buffer
                    );

                    $buffer = (string)array_pop($parts);

                    foreach ($parts as $part) {
                        $handleLine($part);
                    }
                }
            }

            $info = proc_get_status($process);

            if (!$info['running']) {
                $exitCode = $info['exitcode'];

                $buffer .= (string)stream_get_contents(
                    $pipes[1]
                );

                foreach (
                    preg_split('/[\r\n]+/', $buffer) as $part
                ) {
                    $handleLine($part);
                }

                break;
            }

            /*
             * arduino-cli may not print anything while compiling.
             * Send a heartbeat so the user knows it is still running.
             */
            if (
                !$compiled
                && !$connected
                && time() - $lastNote >= 15
            ) {
                $lastNote = time();

                $say(
                    'Still compiling... '
                    . (time() - $started)
                    . 's elapsed'
                );
            }
        }

        fclose($pipes[1]);
        proc_close($process);

        if ($exitCode === 124 || $exitCode === 137) {
            throw new RuntimeException(
                'Arduino upload timed out. '
                . 'The compile/upload did not finish '
                . 'before the configured timeout.'
            );
        }

        if ($exitCode !== 0) {
            $tail = trim(
                implode(
                    "\n",
                    array_slice($log, -30)
                )
            );

            throw new RuntimeException(
                "Arduino compile/upload failed "
                . "(exit code $exitCode):\n\n"
                . (
                    $tail !== ''
                        ? $tail
                        : 'arduino-cli exited without providing an error message.'
                )
            );
        }

    } finally {
        /*
         * Always release the upload lock, including when an
         * exception occurs during compilation or upload.
         */
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    return [
        'code' => 0,
        'log' => implode("\n", $log),
    ];
}
