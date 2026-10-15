<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Lightboard web interface
 *
 * Features:
 *   1. Reads preset/custom images from MySQL.
 *   2. Shows them in the web gallery.
 *   3. Creates a 48x48 CRGB pixel map on first display.
 *   4. Saves the generated pixel_map in MySQL.
 *   5. Reuses the cached pixel_map on later displays.
 *   6. Generates, compiles, and uploads the Arduino sketch.
 *   7. Automatically reconnects if MySQL drops a connection.
 *   8. Streams useful display progress to the browser.
 * ------------------------------------------------------------------ */

$CFG = require __DIR__ . '/config.php';

const SOURCES = [
    'p' => ['table' => 'presetDisplay', 'id' => 'pDisplayID', 'title' => 'Presets', 'deletable' => false],
    'c' => ['table' => 'customDisplay', 'id' => 'cDisplayID', 'title' => 'Custom uploads', 'deletable' => true],
];

/* ------------------------------ auth ------------------------------- */

if ($CFG['password'] !== '') {
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($u !== 'admin' || !hash_equals($CFG['password'], $p)) {
        header('WWW-Authenticate: Basic realm="Lightboard"');
        http_response_code(401);
        exit('Authentication required.');
    }
}

/* ------------------------------ database --------------------------- */

function db(bool $reset = false): PDO
{
    global $CFG;
    static $pdo = null;

    if ($reset) {
        $pdo = null;
    }

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $d = $CFG['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $d['host'],
        $d['port'],
        $d['name']
    );

    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * Run a database callback and transparently retry once if MySQL has
 * dropped the connection.
 *
 * MySQL errors covered:
 *   2006 = MySQL server has gone away
 *   2013 = Lost connection to MySQL server during query
 */
function dbRetry(callable $operation): mixed
{
    try {
        return $operation(db());
    } catch (PDOException $e) {
        $mysqlCode = (int)($e->errorInfo[1] ?? 0);

        if ($mysqlCode !== 2006 && $mysqlCode !== 2013) {
            throw $e;
        }

        // Throw away the dead PDO object and create a fresh connection.
        db(true);

        return $operation(db());
    }
}

function src(string $key): array
{
    if (!isset(SOURCES[$key])) {
        throw new RuntimeException('Unknown image source.');
    }
    return SOURCES[$key];
}

/** List only IDs and names. */
function listImages(string $key): array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s): array {
        $st = $pdo->query(
            "SELECT {$s['id']} AS id, name
             FROM {$s['table']}
             ORDER BY {$s['id']} DESC"
        );
        return $st->fetchAll();
    });
}

/**
 * Fetch only the lightweight metadata + pixel_map.
 *
 * The original image blob is deliberately NOT selected here.
 * We only fetch it if pixel_map is missing.
 */
function getImageForDisplay(string $key, int $id): ?array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s, $id): ?array {
        $st = $pdo->prepare(
            "SELECT name, mimeType, pixel_map
             FROM {$s['table']}
             WHERE {$s['id']} = ?"
        );
        $st->execute([$id]);

        return $st->fetch() ?: null;
    });
}

/** Fetch the original image only when a pixel map does not exist. */
function getOriginalImage(string $key, int $id): ?array
{
    $s = src($key);

    return dbRetry(function (PDO $pdo) use ($s, $id): ?array {
        $st = $pdo->prepare(
            "SELECT name, mimeType, display
             FROM {$s['table']}
             WHERE {$s['id']} = ?"
        );
        $st->execute([$id]);

        return $st->fetch() ?: null;
    });
}

/** Fetch the original image for browser thumbnails. */
function getImageForThumbnail(string $key, int $id): ?array
{
    return getOriginalImage($key, $id);
}

/* ------------------------------ image ------------------------------ */

function buildImage(string $data): GdImage
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

    if ($w !== $n || $h !== $n) {
        if (!$CFG['auto_resize']) {
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

        $side = min($w, $h);
        $out  = imagecreatetruecolor($n, $n);

        imagealphablending($out, true);
        imagefill(
            $out,
            0,
            0,
            imagecolorallocate($out, 0, 0, 0)
        );

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

    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($im);
    }

    return $im;
}

function pixelMapC(GdImage $im): string
{
    global $CFG;

    $n   = $CFG['size'];
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

function imageFromPixelMap(string $pixelMap): GdImage
{
    global $CFG;

    $n = $CFG['size'];

    $count = preg_match_all(
        '/CRGB\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/',
        $pixelMap,
        $matches,
        PREG_SET_ORDER
    );

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

/**
 * Get or create the cached board-ready pixel map.
 *
 * This function is intentionally status-aware so the AJAX endpoint can
 * tell the browser what it is doing.
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

    if (!empty($row['pixel_map'])) {
        $say('Pixel map found in database.');

        $pixelMap = (string)$row['pixel_map'];

        return [
            'pixel_map' => $pixelMap,
            'image'     => imageFromPixelMap($pixelMap),
            'cached'    => true,
        ];
    }

    $say('No pixel map found. Loading original image...');

    $original = getOriginalImage($key, $id);

    if (!$original || !isset($original['display'])) {
        throw new RuntimeException(
            'The original image is missing from the database.'
        );
    }

    $say('Creating 48×48 pixel map...');

    $im       = buildImage((string)$original['display']);
    $pixelMap = pixelMapC($im);

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
        'image'     => $im,
        'cached'    => false,
    ];
}

function previewDataUri(GdImage $im): string
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

/* ------------------------------ board ------------------------------ */

function flashBoard(
    string $imageC,
    int $brightness,
    ?callable $status = null
): array {
    global $CFG;

    $say = static function (string $message) use ($status): void {
        if ($status) {
            $status($message);
        }
    };

    $say('Creating Arduino sketch...');

    $dir = $CFG['sketch_root'] . '/' . $CFG['sketch_name'];

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException(
            "Cannot create sketch folder: $dir"
        );
    }

    $tpl = file_get_contents(
        __DIR__ . '/templates/webImage.ino.tpl'
    );

    if ($tpl === false) {
        throw new RuntimeException(
            'Missing templates/webImage.ino.tpl.'
        );
    }

    $sketch = str_replace(
        ['__GENERATED__', '__BRIGHTNESS__', '__IMAGE__'],
        [date('c'), (string)$brightness, $imageC],
        $tpl
    );

    $ino = "$dir/" . $CFG['sketch_name'] . '.ino';

    if (file_put_contents($ino, $sketch) === false) {
        throw new RuntimeException(
            "Cannot write Arduino sketch: $ino"
        );
    }

    if (
        file_put_contents(
            "$dir/pixel_map.txt",
            $imageC . "\n"
        ) === false
    ) {
        throw new RuntimeException(
            "Cannot write pixel_map.txt in $dir."
        );
    }

    $say('Compiling image and sending it to the ESP32...');

    $cmd = sprintf(
        'cd %s && timeout %d %s compile --upload -p %s --fqbn %s . 2>&1',
        escapeshellarg($dir),
        $CFG['timeout'],
        escapeshellarg($CFG['arduino_cli']),
        escapeshellarg($CFG['port']),
        escapeshellarg($CFG['fqbn'])
    );

    $lines = [];
    $code  = 0;

    exec($cmd, $lines, $code);

    if ($code === 124) {
        throw new RuntimeException(
            'Arduino upload timed out. The ESP32 did not finish responding before the configured timeout.'
        );
    }

    if ($code !== 0) {
        $output = trim(implode("\n", $lines));

        $useful = $output !== ''
            ? $output
            : 'arduino-cli exited without providing an error message.';

        throw new RuntimeException(
            "Arduino compile/upload failed (exit code $code):\n\n$useful"
        );
    }

    $say('Sending image to board...');

    // arduino-cli --upload performs compilation and the USB upload as one
    // command, so the exact boundary between compile and transfer is not
    // available here. The progress message above covers both operations.

    $say('Success. Image displayed on the board.');

    return [
        'code' => 0,
        'log'  => implode("\n", $lines),
    ];
}

/* ------------------------------ streamed status ------------------- */

function sendStatus(string $type, string $message): void
{
    $line = json_encode([
        'type'    => $type,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        $line = json_encode([
            'type'    => 'error',
            'message' => 'Could not encode progress message.',
        ]);
    }

    // Many web servers/proxies buffer small chunks. Padding each event to
    // roughly 4 KB makes the progress stream reach fetch() immediately.
    echo str_pad((string)$line, 4096, ' ') . "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
}

/**
 * AJAX endpoint used by the Display buttons.
 *
 * Each line is a JSON status event. The browser reads these events
 * while the long Arduino command is running.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'display_stream'
) {
    set_time_limit(0);

    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');

    // Prevent PHP output buffering from hiding the progress messages.
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    $lock = null;

    try {
        $lock = fopen(
            sys_get_temp_dir() . '/lightboard_upload.lock',
            'c'
        );

        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException(
                'Another board upload is already running. Try again in a minute.'
            );
        }

        $key = (string)($_POST['src'] ?? '');
        $id  = (int)($_POST['id'] ?? 0);

        if (!isset(SOURCES[$key])) {
            throw new RuntimeException('Invalid image source.');
        }

        if ($id <= 0) {
            throw new RuntimeException('Invalid image ID.');
        }

        $brightness = max(
            5,
            min(100, (int)($_POST['brightness'] ?? 35))
        );

        sendStatus('status', 'Selecting image...');

        $row = getImageForDisplay($key, $id);

        if (!$row) {
            throw new RuntimeException(
                'Image was not found in the database.'
            );
        }

        $cached = getOrCreatePixelMap(
            $key,
            $id,
            $row,
            static function (string $message): void {
                sendStatus('status', $message);
            }
        );

        $preview = previewDataUri($cached['image']);

        /*
         * The Arduino operation can take a while. Release the current
         * MySQL connection before starting it. The database layer will
         * create a new connection later if needed.
         */
        db(true);

        $result = flashBoard(
            $cached['pixel_map'],
            $brightness,
            static function (string $message): void {
                sendStatus('status', $message);
            }
        );

        sendStatus('success', 'Success. Image displayed.');

        sendStatus('preview', $preview);

        sendStatus('done', 'done');
    } catch (Throwable $e) {
        $message = $e->getMessage();

        sendStatus(
            'error',
            'Error: ' . $message
        );

        sendStatus('done', 'done');
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    exit;
}

/* ------------------------------ thumbnail endpoint ---------------- */

if (isset($_GET['img'])) {
    try {
        $row = getImageForThumbnail(
            (string)($_GET['src'] ?? 'p'),
            (int)$_GET['img']
        );
    } catch (Throwable $e) {
        $row = null;
    }

    if (!$row) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $row['mimeType']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');

    echo $row['display'];
    exit;
}

/* ------------------------------ request ---------------------------- */

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES);
}

$error = $notice = $preview = $log = null;
$ok = false;

$brightness = max(
    5,
    min(100, (int)($_POST['brightness'] ?? 35))
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0);

    $action = $_POST['action'] ?? '';
    $lock   = null;

    try {
        if ($action === 'upload') {
            $f = $_FILES['image'] ?? null;

            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    'No file uploaded, or the upload failed (check php upload_max_filesize).'
                );
            }

            if ($f['size'] > $CFG['max_bytes']) {
                throw new RuntimeException('File is too large.');
            }

            $im = buildImage(
                (string)file_get_contents($f['tmp_name'])
            );

            ob_start();
            imagepng($im);
            $png = (string)ob_get_clean();

            $name = trim(
                (string)($_POST['name'] ?? '')
            ) ?: pathinfo(
                $f['name'],
                PATHINFO_FILENAME
            );

            $pixelMap = pixelMapC($im);

            dbRetry(function (PDO $pdo) use (
                $name,
                $png,
                $pixelMap
            ): void {
                $st = $pdo->prepare(
                    'INSERT INTO customDisplay
                    (userID, name, mimeType, display, pixel_map)
                    VALUES (NULL, ?, ?, ?, ?)'
                );

                $st->bindValue(
                    1,
                    mb_substr($name, 0, 45)
                );

                $st->bindValue(
                    2,
                    'image/png'
                );

                $st->bindValue(
                    3,
                    $png,
                    PDO::PARAM_LOB
                );

                $st->bindValue(
                    4,
                    $pixelMap
                );

                $st->execute();
            });

            $notice = 'Saved to the database.';

        } elseif ($action === 'delete') {
            $key = (string)($_POST['src'] ?? '');

            if (!src($key)['deletable']) {
                throw new RuntimeException(
                    'Presets can\'t be deleted here.'
                );
            }

            try {
                dbRetry(function (PDO $pdo): void {
                    $st = $pdo->prepare(
                        'DELETE FROM customDisplay
                         WHERE cDisplayID = ?'
                    );

                    $st->execute([
                        (int)($_POST['id'] ?? 0)
                    ]);
                });

                $notice = 'Deleted.';
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1451) {
                    throw new RuntimeException(
                        'That image is used by a scheduled event. Remove the event first.'
                    );
                }

                throw $e;
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* Build gallery. A dropped MySQL connection is automatically retried. */
$gallery = [];

try {
    foreach (SOURCES as $key => $s) {
        $gallery[$key] = listImages($key);
    }
} catch (Throwable $e) {
    $error = $error ?: 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lightboard</title>

<style>
  :root {
    --bg:#111418;
    --card:#1b2027;
    --fg:#e8ecf1;
    --mut:#8b96a5;
    --acc:#4da3ff;
    --ok:#3ecf8e;
    --bad:#ff6b6b;
  }

  * { box-sizing:border-box; }

  body {
    margin:0;
    background:var(--bg);
    color:var(--fg);
    font:16px/1.5 system-ui,sans-serif;
  }

  main {
    max-width:760px;
    margin:0 auto;
    padding:24px 16px 48px;
  }

  h1 { font-size:1.4rem; margin:0 0 4px; }
  h2 { font-size:1.05rem; margin:0 0 12px; }
  h3 {
    font-size:.95rem;
    margin:18px 0 10px;
    color:var(--mut);
    font-weight:600;
  }

  p.sub {
    color:var(--mut);
    margin:0 0 20px;
  }

  .card {
    background:var(--card);
    border-radius:12px;
    padding:20px;
    margin-bottom:16px;
  }

  label {
    display:block;
    margin:0 0 6px;
    color:var(--mut);
    font-size:.9rem;
  }

  input[type=file],
  input[type=text] {
    width:100%;
    margin-bottom:12px;
    color:var(--fg);
  }

  input[type=text] {
    background:#0b0d10;
    border:1px solid #2a313b;
    border-radius:6px;
    padding:8px;
  }

  input[type=range] {
    width:100%;
  }

  button {
    padding:10px 14px;
    border:0;
    border-radius:8px;
    background:var(--acc);
    color:#001a33;
    font-weight:600;
    font-size:.95rem;
    cursor:pointer;
  }

  button.full {
    width:100%;
    margin-top:12px;
  }

  button.del {
    background:transparent;
    color:var(--mut);
    font-weight:400;
    padding:6px;
  }

  button:disabled {
    opacity:.6;
    cursor:wait;
  }

  .grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    gap:12px;
  }

  .tile {
    background:#14181d;
    border-radius:10px;
    padding:10px;
    text-align:center;
  }

  .tile img {
    width:100%;
    image-rendering:pixelated;
    border-radius:6px;
    background:#000;
    display:block;
    margin-bottom:8px;
  }

  .tile .nm {
    font-size:.85rem;
    margin-bottom:8px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }

  .tile .row {
    display:flex;
    gap:6px;
    justify-content:center;
    align-items:center;
  }

  .tile form {
    margin:0;
  }

  img.px {
    width:100%;
    max-width:384px;
    image-rendering:pixelated;
    display:block;
    margin:0 auto;
    border-radius:6px;
    background:#000;
  }

  .msg {
    padding:12px 14px;
    border-radius:8px;
    margin-bottom:16px;
    white-space:pre-wrap;
  }

  .msg.ok {
    background:rgba(62,207,142,.15);
    color:var(--ok);
  }

  .msg.bad {
    background:rgba(255,107,107,.15);
    color:var(--bad);
  }

  .status-card {
    display:none;
  }

  .status-card.show {
    display:block;
  }

  .status-list {
    margin:0;
    padding:0;
    list-style:none;
  }

  .status-item {
    padding:7px 0;
    border-bottom:1px solid rgba(255,255,255,.06);
    color:var(--mut);
  }

  .status-item:last-child {
    border-bottom:0;
  }

  .status-item.active {
    color:var(--fg);
  }

  .status-item.success {
    color:var(--ok);
  }

  .status-item.error {
    color:var(--bad);
  }

  pre {
    background:#0b0d10;
    padding:12px;
    border-radius:8px;
    overflow:auto;
    max-height:260px;
    font-size:.8rem;
  }

  details summary {
    cursor:pointer;
    color:var(--mut);
  }
</style>
</head>

<body>
<main>

  <h1>Lightboard</h1>

  <p class="sub">
    Pick an image to send it to the board
    (compile + flash takes about a minute).
  </p>

  <?php if ($error): ?>
    <div class="msg bad"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($notice): ?>
    <div class="msg ok"><?= h($notice) ?></div>
  <?php endif; ?>

  <!-- Live display status -->
  <div id="displayStatus" class="card status-card">
    <h2>Board status</h2>
    <ul id="statusList" class="status-list"></ul>
  </div>

  <!-- Final result -->
  <div id="displayResult"></div>

  <div id="previewCard"
       class="card"
       style="display:none;">
    <label>48×48 pixel map sent to the board</label>
    <img id="previewImage"
         class="px"
         alt="48x48 preview">
  </div>

  <div id="logCard"
       class="card"
       style="display:none;">
    <details>
      <summary>arduino-cli output</summary>
      <pre id="arduinoLog"></pre>
    </details>
  </div>

  <div class="card">
    <h2>Add custom image</h2>

    <form method="post" enctype="multipart/form-data">

      <input type="hidden"
             name="action"
             value="upload">

      <label for="image">
        Image
        (PNG, JPG, GIF, WebP)
        <?= $CFG['auto_resize']
          ? ' – non-48×48 will be center-cropped and scaled'
          : ' – must be exactly 48×48'
        ?>
      </label>

      <input type="file"
             id="image"
             name="image"
             accept="image/*"
             required>

      <label for="name">
        Name (optional, max 45 characters)
      </label>

      <input type="text"
             id="name"
             name="name"
             maxlength="45">

      <button type="submit"
              class="full">
        Save to database
      </button>

    </form>
  </div>

  <div class="card">

    <h2>Gallery</h2>

    <label for="b">
      Brightness:
      <span id="bv"><?= $brightness ?></span>
    </label>

    <input type="range"
           id="b"
           min="5"
           max="100"
           value="<?= $brightness ?>">

    <?php foreach (SOURCES as $key => $s): ?>

      <h3><?= h($s['title']) ?></h3>

      <?php if (empty($gallery[$key])): ?>

        <p style="color:var(--mut);margin:0">
          None yet.
        </p>

      <?php else: ?>

        <div class="grid">

          <?php foreach ($gallery[$key] as $img): ?>

            <div class="tile">

              <img
                src="?src=<?= h($key) ?>&amp;img=<?= (int)$img['id'] ?>"
                alt=""
                loading="lazy">

              <div class="nm"
                   title="<?= h($img['name']) ?>">
                <?= h($img['name']) ?>
              </div>

              <div class="row">

                <button
                  type="button"
                  class="display-button"
                  data-src="<?= h($key) ?>"
                  data-id="<?= (int)$img['id'] ?>">
                  Display
                </button>

                <?php if ($s['deletable']): ?>

                  <form
                    method="post"
                    onsubmit="return confirm('Delete this image?')">

                    <input type="hidden"
                           name="action"
                           value="delete">

                    <input type="hidden"
                           name="src"
                           value="<?= h($key) ?>">

                    <input type="hidden"
                           name="id"
                           value="<?= (int)$img['id'] ?>">

                    <button
                      type="submit"
                      class="del"
                      title="Delete">
                      ✕
                    </button>

                  </form>

                <?php endif; ?>

              </div>
            </div>

          <?php endforeach; ?>

        </div>

      <?php endif; ?>

    <?php endforeach; ?>

  </div>

</main>

<script>
const b = document.getElementById('b');
const bv = document.getElementById('bv');

const statusCard = document.getElementById('displayStatus');
const statusList = document.getElementById('statusList');
const resultBox = document.getElementById('displayResult');

const previewCard = document.getElementById('previewCard');
const previewImage = document.getElementById('previewImage');

const logCard = document.getElementById('logCard');
const arduinoLog = document.getElementById('arduinoLog');

b.oninput = () => {
  bv.textContent = b.value;
};

function addStatus(message, type = 'active') {
  const item = document.createElement('li');

  item.className = 'status-item ' + type;
  item.textContent =
    (type === 'success' ? '✓ ' :
     type === 'error' ? '✗ ' :
     '• ') + message;

  statusList.appendChild(item);

  item.scrollIntoView({
    behavior: 'smooth',
    block: 'nearest'
  });
}

function setButtonsDisabled(disabled) {
  document
    .querySelectorAll('.display-button')
    .forEach(button => {
      button.disabled = disabled;

      if (disabled) {
        button.dataset.oldText = button.textContent;
        button.textContent = 'Working…';
      } else {
        button.textContent =
          button.dataset.oldText || 'Display';
      }
    });
}

async function displayImage(src, id, button) {
  statusCard.classList.add('show');
  statusList.innerHTML = '';
  resultBox.innerHTML = '';

  previewCard.style.display = 'none';
  logCard.style.display = 'none';

  setButtonsDisabled(true);

  addStatus('Selecting image...');

  const form = new FormData();

  form.append('action', 'display_stream');
  form.append('src', src);
  form.append('id', id);
  form.append('brightness', b.value);

  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: form,
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error(
        'HTTP error ' +
        response.status +
        ' (' +
        response.statusText +
        ')'
      );
    }

    if (!response.body) {
      throw new Error(
        'The browser did not provide a streaming response.'
      );
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    let buffer = '';

    while (true) {
      const { value, done } = await reader.read();

      if (done) {
        break;
      }

      buffer += decoder.decode(value, {
        stream: true
      });

      const lines = buffer.split('\n');

      buffer = lines.pop();

      for (const line of lines) {
        if (!line.trim()) {
          continue;
        }

        let event;

        try {
          event = JSON.parse(line);
        } catch (e) {
          continue;
        }

        if (event.type === 'status') {
          addStatus(event.message);
        }

        else if (event.type === 'success') {
          addStatus(event.message, 'success');

          resultBox.innerHTML =
            '<div class="msg ok">' +
            escapeHtml(event.message) +
            '</div>';
        }

        else if (event.type === 'error') {
          addStatus(event.message, 'error');

          resultBox.innerHTML =
            '<div class="msg bad">' +
            escapeHtml(event.message) +
            '</div>';
        }

        else if (event.type === 'preview') {
          previewImage.src = event.message;
          previewCard.style.display = 'block';
        }
      }
    }

  } catch (error) {
    addStatus(
      'Error: ' + error.message,
      'error'
    );

    resultBox.innerHTML =
      '<div class="msg bad">' +
      escapeHtml(
        'Display request failed: ' +
        error.message
      ) +
      '</div>';

  } finally {
    setButtonsDisabled(false);
  }
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}

document
  .querySelectorAll('.display-button')
  .forEach(button => {

    button.addEventListener('click', () => {

      displayImage(
        button.dataset.src,
        button.dataset.id,
        button
      );

    });

  });
</script>

</body>
</html>
