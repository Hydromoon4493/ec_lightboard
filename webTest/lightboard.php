<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Lightboard web interface (runs on the Raspberry Pi)
 *
 * This single PHP file is the web controller for the lightboard:
 *   1. Reads preset/custom images from MySQL.
 *   2. Shows those images in the web gallery.
 *   3. On the first board display of an image, converts the stored
 *      image into the 48x48 CRGB pixel-map format used by the ESP32.
 *   4. Saves that generated pixel_map back into the same database row.
 *   5. On later displays, uses the saved pixel_map instead of decoding
 *      and resizing the original image again.
 *   6. Generates the Arduino sketch and compiles/uploads it to the ESP32.
 *
 * The original image remains in the database so the gallery can still
 * show thumbnails. The pixel_map column acts as the cached, board-ready
 * representation of the image.
 * ------------------------------------------------------------------ */

$CFG = require __DIR__ . '/config.php';

/* image sources: key => table + id column (both tables' IDs start at 1) */
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

/* ------------------------------ database ---------------------------
 *
 * Creates one reusable PDO connection to the Lightshow MySQL database.
 * PDO exceptions are enabled so database errors can be handled by the
 * main request try/catch block instead of being silently ignored.
 * ------------------------------------------------------------------- */

function db(?bool $reset = false): PDO
{
    global $CFG;

    static $pdo = null;

    // Allow the caller to explicitly close the existing connection.
    if ($reset) {
        $pdo = null;
    }

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $d   = $CFG['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $d['host'],
        $d['port'],
        $d['name']
    );

    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function dbDisconnect(): void
{
    // Force the current PDO connection to be closed.
    // The next call to db() will create a fresh connection.
    static $pdo = null;
}

function src(string $key): array
{
    if (!isset(SOURCES[$key])) {
        throw new RuntimeException('Unknown image source.');
    }
    return SOURCES[$key];
}

/** List id + name only (no blobs). */
function listImages(string $key): array
{
    $s = src($key);
    return db()->query("SELECT {$s['id']} AS id, name FROM {$s['table']} ORDER BY {$s['id']} DESC")->fetchAll();
}

/**
 * Get one image record.
 *
 * We fetch both the original image (`display`) and the cached
 * `pixel_map`. The original is only needed when there is no cached
 * pixel map yet (or for the thumbnail endpoint); normal board
 * displays use pixel_map once it has been generated.
 */
function getImage(string $key, int $id): ?array
{
    $s  = src($key);
    $st = db()->prepare("SELECT name, mimeType, display, pixel_map FROM {$s['table']} WHERE {$s['id']} = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ------------------------------ image ------------------------------
 *
 * Image conversion helpers:
 *   buildImage()       = decode/normalize an original image to 48x48.
 *   pixelMapC()        = convert a GD image into CRGB C/C++ text.
 *   imageFromPixelMap()= rebuild a GD image from cached CRGB text.
 *   getOrCreatePixelMap() = database cache layer used by board display.
 * ------------------------------------------------------------------- */

/** Decode image bytes into a 48x48 truecolor GD image (resizing if allowed). */
function buildImage(string $data): GdImage
{
    global $CFG;
    $im = @imagecreatefromstring($data);
    if ($im === false) {
        throw new RuntimeException('Could not read that as an image (use PNG, JPG, GIF or WebP).');
    }

    $n = $CFG['size'];
    $w = imagesx($im);
    $h = imagesy($im);

    if ($w !== $n || $h !== $n) {
        if (!$CFG['auto_resize']) {
            throw new RuntimeException(sprintf('Image must be exactly %dx%d (got %dx%d).', $n, $n, $w, $h));
        }
        // center-crop to a square, then scale down
        $side = min($w, $h);
        $out  = imagecreatetruecolor($n, $n);
        imagealphablending($out, true);
        imagefill($out, 0, 0, imagecolorallocate($out, 0, 0, 0));
        imagecopyresampled($out, $im, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $n, $n, $side, $side);
        return $out;
    }

    imagepalettetotruecolor($im);
    return $im;
}

function pixelMapC(GdImage $im): string
{
    global $CFG;

    // Convert the normalized 48x48 GD image into the C/C++ CRGB array
    // expected by the ESP32 Arduino sketch.
    $n   = $CFG['size'];
    $out = "CRGB image[$n][$n] = {\n";

    for ($y = 0; $y < $n; $y++) {
        $row = [];

        for ($x = 0; $x < $n; $x++) {
            $c     = imagecolorat($im, $x, $y);
            $row[] = sprintf(
                'CRGB(%d,%d,%d)',
                ($c >> 16) & 255,
                ($c >> 8) & 255,
                $c & 255
            );
        }

        $out .= ' {' . implode(',', $row) . ' }' . ($y < $n - 1 ? ',' : '') . "\n";
    }

    return $out . "};";
}

/**
 * Convert a cached CRGB pixel_map back into a GD image.
 *
 * This is used after the first conversion so the preview shown by the
 * page can still be generated without reading/re-decoding the original
 * image blob. It also validates that the cached map contains the
 * expected number of RGB pixels.
 */
function imageFromPixelMap(string $pixelMap): GdImage
{
    global $CFG;

    $n = $CFG['size'];

    // Extract every CRGB(r,g,b) entry from the cached text.
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

            $color = ($r << 16) | ($g << 8) | $b;
            imagesetpixel($im, $x, $y, $color);
            $i++;
        }
    }

    return $im;
}

/**
 * Return the cached board-ready pixel map for an image.
 *
 * First use:
 *   - Decode the original database image.
 *   - Normalize it to 48x48.
 *   - Generate the CRGB pixel map.
 *   - Save that pixel map into the database.
 *
 * Later uses:
 *   - Skip the original image conversion completely.
 *   - Return the pixel_map already stored in the database.
 *
 * The function returns both the pixel map and a 48x48 GD image so the
 * caller can create the same preview regardless of which path was used.
 */
function getOrCreatePixelMap(string $key, int $id, array $row): array
{
    $s = src($key);

    // A non-empty pixel_map means this image has already been converted.
    if (!empty($row['pixel_map'])) {
        return [
            'pixel_map' => (string)$row['pixel_map'],
            'image'     => imageFromPixelMap((string)$row['pixel_map']),
            'cached'    => true,
        ];
    }

    // No cached map exists yet, so this is the first board request.
    if (!isset($row['display'])) {
        throw new RuntimeException('The original image is missing from the database.');
    }

    $im = buildImage((string)$row['display']);
    $pixelMap = pixelMapC($im);

    // Store the generated map in the same row. Future displays will use
    // this cached value and will not need to process the original image.
    $st = db()->prepare(
        "UPDATE {$s['table']} SET pixel_map = ? WHERE {$s['id']} = ?"
    );
    $st->execute([$pixelMap, $id]);

    return [
        'pixel_map' => $pixelMap,
        'image'     => $im,
        'cached'    => false,
    ];
}

function previewDataUri(GdImage $im): string
{
    $big = imagescale($im, 384, 384, IMG_NEAREST_NEIGHBOUR);
    ob_start();
    imagepng($big);
    return 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
}

/* ------------------------------ board ------------------------------
 *
 * The board section creates the temporary Arduino sketch from the
 * cached CRGB pixel map, then runs arduino-cli to compile and upload
 * that sketch to the ESP32.
 * ------------------------------------------------------------------- */

/** Write sketch, then compile + upload. Returns [exitCode, output]. */
function flashBoard(string $imageC, int $brightness): array
{
    global $CFG;
    $dir = $CFG['sketch_root'] . '/' . $CFG['sketch_name'];
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException("Cannot create sketch folder: $dir");
    }

    $tpl = file_get_contents(__DIR__ . '/templates/webImage.ino.tpl');
    if ($tpl === false) {
        throw new RuntimeException('Missing templates/webImage.ino.tpl');
    }
    $sketch = str_replace(
        ['__GENERATED__', '__BRIGHTNESS__', '__IMAGE__'],
        [date('c'), (string)$brightness, $imageC],
        $tpl
    );

    $ino = "$dir/" . $CFG['sketch_name'] . '.ino';
    if (file_put_contents($ino, $sketch) === false) {
        throw new RuntimeException("Cannot write $ino");
    }
    file_put_contents("$dir/pixel_map.txt", $imageC . "\n");

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
    return [$code, implode("\n", $lines)];
}

/* ------------------ thumbnail endpoint (?src=p&img=ID) --------------
 *
 * The gallery still uses the original image stored in `display`. The
 * cached pixel_map is intentionally not used for this endpoint because
 * browsers need normal image bytes (PNG/JPG/etc.), not C/C++ source text.
 * The board-display path below is the part that uses pixel_map.
 * -------------------------------------------------------------------- */

if (isset($_GET['img'])) {
    try {
        $row = getImage((string)($_GET['src'] ?? 'p'), (int)$_GET['img']);
    } catch (Throwable $e) {
        $row = null;
    }
    if (!$row) { http_response_code(404); exit; }
    header('Content-Type: ' . $row['mimeType']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    echo $row['display'];
    exit;
}

/* ------------------------------ request ----------------------------
 *
 * POST requests perform actions such as saving, deleting, or displaying
 * an image. After the action, the gallery is queried so the page can
 * render the current contents of both database tables.
 * ------------------------------------------------------------------- */

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$error = $notice = $preview = $log = null;
$ok = false;
$brightness = max(5, min(100, (int)($_POST['brightness'] ?? 35)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0);
    $action = $_POST['action'] ?? '';
    $lock   = null;

    try {
        if ($action === 'upload') {
            // Upload a new custom image. It is normalized to 48x48, stored
            // as PNG for the gallery, and its pixel_map cache is generated
            // at the same time.
            $f = $_FILES['image'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file uploaded, or the upload failed (check php upload_max_filesize).');
            }
            if ($f['size'] > $CFG['max_bytes']) {
                throw new RuntimeException('File is too large.');
            }
            $im = buildImage((string)file_get_contents($f['tmp_name'])); // validate + normalize to 48x48

            ob_start();
            imagepng($im);
            $png  = (string)ob_get_clean();
            $name = trim((string)($_POST['name'] ?? '')) ?: pathinfo($f['name'], PATHINFO_FILENAME);

            // userID stays NULL until the site has logins
            // The uploaded image has already been normalized to 48x48 above.
            // Generate its board-ready cache now so the first display does not
            // have to perform this conversion again.
            $pixelMap = pixelMapC($im);

            $st = db()->prepare(
                'INSERT INTO customDisplay (userID, name, mimeType, display, pixel_map)
                 VALUES (NULL, ?, ?, ?, ?)'
            );
            $st->bindValue(1, mb_substr($name, 0, 45));
            $st->bindValue(2, 'image/png');
            $st->bindValue(3, $png, PDO::PARAM_LOB);
            $st->bindValue(4, $pixelMap);
            $st->execute();
            $notice = 'Saved to the database.';

        } elseif ($action === 'delete') {
            $key = (string)($_POST['src'] ?? '');
            if (!src($key)['deletable']) {
                throw new RuntimeException('Presets can\'t be deleted here.');
            }
            try {
                $st = db()->prepare('DELETE FROM customDisplay WHERE cDisplayID = ?');
                $st->execute([(int)($_POST['id'] ?? 0)]);
                $notice = 'Deleted.';
            } catch (PDOException $e) {
                // FK from schedule uses ON DELETE RESTRICT
                if (($e->errorInfo[1] ?? 0) === 1451) {
                    throw new RuntimeException('That image is used by a scheduled event. Remove the event first.');
                }
                throw $e;
            }

        } elseif ($action === 'display') {
            // Display an image on the physical board. A filesystem lock prevents
            // two browser requests from compiling/flashing the ESP32 at once.
            $lock = fopen(sys_get_temp_dir() . '/lightboard_upload.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another upload is already running. Try again in a minute.');
            }

            $row = getImage((string)($_POST['src'] ?? ''), (int)($_POST['id'] ?? 0));
            if (!$row) {
                throw new RuntimeException('Image not found in the database.');
            }

            // Get the cached board-ready representation.
            //
            // First time:
            //   original image -> 48x48 GD image -> pixel_map -> database
            //
            // Every later time:
            //   database pixel_map -> 48x48 GD image
            //
            // This keeps the expensive image conversion out of repeat displays.
            $cached = getOrCreatePixelMap(
                (string)($_POST['src'] ?? ''),
                (int)($_POST['id'] ?? 0),
                $row
            );

            $im = $cached['image'];
            $preview = previewDataUri($im);

            /*
            * flashBoard() can take around a minute because arduino-cli
            * compiles and uploads the sketch.
            *
            * Close the MySQL connection before starting that long operation.
            * Otherwise MySQL may close the idle connection while the ESP32
            * upload is happening.
            */
            db(true);

            [$code, $log] = flashBoard($cached['pixel_map'], $brightness);

            $ok = $code === 0;
            if (!$ok) {
                $error = $code === 124
                    ? 'Timed out. If the log ends at "Connecting...", hold the ESP32 BOOT/IO0 button and retry.'
                    : "arduino-cli exited with code $code.";
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

/* Build the gallery list. This intentionally retrieves only IDs and
 * names, not the image blobs or pixel maps, so loading the page stays
 * lightweight even when the database contains many images. */
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
  :root { --bg:#111418; --card:#1b2027; --fg:#e8ecf1; --mut:#8b96a5; --acc:#4da3ff; --ok:#3ecf8e; --bad:#ff6b6b; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.5 system-ui,sans-serif; }
  main { max-width:760px; margin:0 auto; padding:24px 16px 48px; }
  h1 { font-size:1.4rem; margin:0 0 4px; }
  h2 { font-size:1.05rem; margin:0 0 12px; }
  h3 { font-size:.95rem; margin:18px 0 10px; color:var(--mut); font-weight:600; }
  p.sub { color:var(--mut); margin:0 0 20px; }
  .card { background:var(--card); border-radius:12px; padding:20px; margin-bottom:16px; }
  label { display:block; margin:0 0 6px; color:var(--mut); font-size:.9rem; }
  input[type=file], input[type=text] { width:100%; margin-bottom:12px; color:var(--fg); }
  input[type=text] { background:#0b0d10; border:1px solid #2a313b; border-radius:6px; padding:8px; }
  input[type=range] { width:100%; }
  button { padding:10px 14px; border:0; border-radius:8px; background:var(--acc); color:#001a33; font-weight:600; font-size:.95rem; cursor:pointer; }
  button.full { width:100%; margin-top:12px; }
  button.del { background:transparent; color:var(--mut); font-weight:400; padding:6px; }
  button:disabled { opacity:.6; cursor:wait; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; }
  .tile { background:#14181d; border-radius:10px; padding:10px; text-align:center; }
  .tile img { width:100%; image-rendering:pixelated; border-radius:6px; background:#000; display:block; margin-bottom:8px; }
  .tile .nm { font-size:.85rem; margin-bottom:8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tile .row { display:flex; gap:6px; justify-content:center; align-items:center; }
  .tile form { margin:0; }
  img.px { width:100%; max-width:384px; image-rendering:pixelated; display:block; margin:0 auto; border-radius:6px; background:#000; }
  .msg { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
  .msg.ok { background:rgba(62,207,142,.15); color:var(--ok); }
  .msg.bad { background:rgba(255,107,107,.15); color:var(--bad); }
  pre { background:#0b0d10; padding:12px; border-radius:8px; overflow:auto; max-height:260px; font-size:.8rem; }
  details summary { cursor:pointer; color:var(--mut); }
</style>
</head>
<body>
<main>
  <h1>Lightboard</h1>
  <p class="sub">Pick an image to send it to the board (compile + flash takes about a minute).</p>

  <?php if ($error): ?><div class="msg bad"><?= h($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="msg ok">Uploaded to the board.</div><?php endif; ?>

  <?php if ($preview): ?>
  <div class="card">
    <label>48×48 pixel map sent to the board</label>
    <img class="px" src="<?= $preview ?>" alt="48x48 preview">
  </div>
  <?php endif; ?>

  <?php if ($log): ?>
  <details class="card" <?= $ok ? '' : 'open' ?>>
    <summary>arduino-cli output</summary>
    <pre><?= h($log) ?></pre>
  </details>
  <?php endif; ?>

  <div class="card">
    <h2>Add custom image</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <label for="image">Image (PNG, JPG, GIF, WebP)<?= $CFG['auto_resize'] ? ' – non-48×48 will be center-cropped and scaled' : ' – must be exactly 48×48' ?></label>
      <input type="file" id="image" name="image" accept="image/*" required>
      <label for="name">Name (optional, max 45 characters)</label>
      <input type="text" id="name" name="name" maxlength="45">
      <button type="submit" class="full">Save to database</button>
    </form>
  </div>

  <div class="card">
    <h2>Gallery</h2>
    <label for="b">Brightness: <span id="bv"><?= $brightness ?></span></label>
    <input type="range" id="b" min="5" max="100" value="<?= $brightness ?>">

    <?php foreach (SOURCES as $key => $s): ?>
      <h3><?= h($s['title']) ?></h3>
      <?php if (empty($gallery[$key])): ?>
        <p style="color:var(--mut);margin:0">None yet.</p>
      <?php else: ?>
      <div class="grid">
        <?php foreach ($gallery[$key] as $img): ?>
        <div class="tile">
          <img src="?src=<?= h($key) ?>&amp;img=<?= (int)$img['id'] ?>" alt="" loading="lazy">
          <div class="nm" title="<?= h($img['name']) ?>"><?= h($img['name']) ?></div>
          <div class="row">
            <form method="post" class="disp">
              <input type="hidden" name="action" value="display">
              <input type="hidden" name="src" value="<?= h($key) ?>">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <input type="hidden" name="brightness" class="bri" value="<?= $brightness ?>">
              <button type="submit">Display</button>
            </form>
            <?php if ($s['deletable']): ?>
            <form method="post" onsubmit="return confirm('Delete this image?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="src" value="<?= h($key) ?>">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" class="del" title="Delete">✕</button>
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
  const b = document.getElementById('b'), bv = document.getElementById('bv');
  b.oninput = () => bv.textContent = b.value;
  document.querySelectorAll('form.disp').forEach(f => {
    f.onsubmit = () => {
      f.querySelector('.bri').value = b.value;
      const btn = f.querySelector('button');
      btn.disabled = true; btn.textContent = 'Flashing…';
      document.querySelectorAll('form.disp button').forEach(x => x.disabled = true);
    };
  });
</script>
</body>
</html>
