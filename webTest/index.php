<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Lightboard web uploader
 * Upload image -> 48x48 pixel map -> generate sketch -> compile+upload
 * ------------------------------------------------------------------ */

const CONFIG = [
    'sketch_root' => '/home/lightboard/Documents/ec_lightboard/sketches',
    'sketch_name' => 'webImage',                 // folder + .ino name must match
    'arduino_cli' => '/home/lightboard/Documents/lightboard_scripts/bin/arduino-cli',
    'port'        => '/dev/ttyUSB0',
    'fqbn'        => 'esp32:esp32:esp32',
    'timeout'     => 300,                        // seconds for compile+upload
    'max_bytes'   => 8 * 1024 * 1024,
    'size'        => 48,
];

/** Load an uploaded image. Assumes it is already a cleaned-up 48x48 image. */
function buildImage(string $path): GdImage
{
    $data = file_get_contents($path);
    $im   = $data === false ? false : @imagecreatefromstring($data);
    if ($im === false) {
        throw new RuntimeException('Could not read that file as an image (use PNG, JPG, GIF or WebP).');
    }

    $n = CONFIG['size'];
    if (imagesx($im) !== $n || imagesy($im) !== $n) {
        throw new RuntimeException(sprintf('Image must be exactly %dx%d (got %dx%d).', $n, $n, imagesx($im), imagesy($im)));
    }

    imagepalettetotruecolor($im); // so indexed PNG/GIF pixels read as RGB
    return $im;
}

/** Build the C array in the same format png_to_fastled.py produces. */
function pixelMapC(GdImage $im): string
{
    $n   = CONFIG['size'];
    $out = "CRGB image[$n][$n] = {\n";
    for ($y = 0; $y < $n; $y++) {
        $row = [];
        for ($x = 0; $x < $n; $x++) {
            $c     = imagecolorat($im, $x, $y);
            $row[] = sprintf('CRGB(%d,%d,%d)', ($c >> 16) & 255, ($c >> 8) & 255, $c & 255);
        }
        $out .= ' {' . implode(',', $row) . ' }' . ($y < $n - 1 ? ',' : '') . "\n";
    }
    return $out . "};";
}

function previewDataUri(GdImage $im): string
{
    $big = imagescale($im, 384, 384, IMG_NEAREST_NEIGHBOUR);
    ob_start();
    imagepng($big);
    return 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
}

/** Write sketch, then compile + upload. Returns [exitCode, output]. */
function flashBoard(string $imageC, int $brightness): array
{
    $dir = CONFIG['sketch_root'] . '/' . CONFIG['sketch_name'];
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException("Cannot create sketch folder: $dir");
    }

    $tpl = file_get_contents(__DIR__ . '/template/webImage.ino.tpl');
    if ($tpl === false) {
        throw new RuntimeException('Missing template/webImage.ino.tpl');
    }
    $sketch = str_replace(
        ['__GENERATED__', '__BRIGHTNESS__', '__IMAGE__'],
        [date('c'), (string)$brightness, $imageC],
        $tpl
    );

    $ino = "$dir/" . CONFIG['sketch_name'] . '.ino';
    if (file_put_contents($ino, $sketch) === false) {
        throw new RuntimeException("Cannot write $ino");
    }
    file_put_contents("$dir/pixel_map.txt", $imageC . "\n");

    $cmd = sprintf(
        'cd %s && timeout %d %s compile --upload -p %s --fqbn %s . 2>&1',
        escapeshellarg($dir),
        CONFIG['timeout'],
        escapeshellarg(CONFIG['arduino_cli']),
        escapeshellarg(CONFIG['port']),
        escapeshellarg(CONFIG['fqbn'])
    );

    $lines = [];
    $code  = 0;
    exec($cmd, $lines, $code);
    return [$code, implode("\n", $lines)];
}

/* ----------------------------- request ----------------------------- */

$error = $preview = $log = null;
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0);
    $lock = fopen(sys_get_temp_dir() . '/lightboard_upload.lock', 'c');

    try {
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another upload is already running. Try again in a minute.');
        }
        $f = $_FILES['image'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No file uploaded, or the upload failed (check php upload_max_filesize).');
        }
        if ($f['size'] > CONFIG['max_bytes']) {
            throw new RuntimeException('File is too large.');
        }

        $brightness = max(5, min(100, (int)($_POST['brightness'] ?? 35)));
        $im         = buildImage($f['tmp_name']);
        $preview    = previewDataUri($im);

        [$code, $log] = flashBoard(pixelMapC($im), $brightness);
        $ok = $code === 0;
        if (!$ok) {
            $error = $code === 124
                ? 'Timed out. If the log ends at "Connecting...", hold the ESP32 BOOT/IO0 button and retry.'
                : "arduino-cli exited with code $code.";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    } finally {
        if (!empty($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lightboard Uploader</title>
<style>
  :root { --bg:#111418; --card:#1b2027; --fg:#e8ecf1; --mut:#8b96a5; --acc:#4da3ff; --ok:#3ecf8e; --bad:#ff6b6b; }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.5 system-ui,sans-serif; }
  main { max-width:560px; margin:0 auto; padding:24px 16px 48px; }
  h1 { font-size:1.4rem; margin:0 0 4px; }
  p.sub { color:var(--mut); margin:0 0 20px; }
  .card { background:var(--card); border-radius:12px; padding:20px; margin-bottom:16px; }
  label { display:block; margin:0 0 6px; color:var(--mut); font-size:.9rem; }
  input[type=file] { width:100%; margin-bottom:16px; color:var(--fg); }
  input[type=range] { width:100%; }
  button { width:100%; margin-top:16px; padding:12px; border:0; border-radius:8px; background:var(--acc); color:#001a33; font-weight:600; font-size:1rem; cursor:pointer; }
  button:disabled { opacity:.6; cursor:wait; }
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
  <h1>Lightboard Uploader</h1>
  <p class="sub">Upload an image. It must already be 48×48. It's converted to a pixel map and flashed to the board.</p>

  <?php if ($error): ?><div class="msg bad"><?= h($error) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="msg ok">Uploaded to the board.</div><?php endif; ?>

  <form class="card" method="post" enctype="multipart/form-data" id="f">
    <label for="image">Image (PNG, JPG, GIF, WebP)</label>
    <input type="file" id="image" name="image" accept="image/*" required>

    <img id="pre" class="px" alt="" style="display:none;margin-bottom:16px">

    <label for="b">Brightness: <span id="bv">35</span></label>
    <input type="range" id="b" name="brightness" min="5" max="100" value="<?= (int)($_POST['brightness'] ?? 35) ?>">

    <button type="submit" id="go">Convert &amp; upload to board</button>
  </form>

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
</main>

<script>
  const file = document.getElementById('image'), pre = document.getElementById('pre');
  const b = document.getElementById('b'), bv = document.getElementById('bv');
  bv.textContent = b.value;
  b.oninput = () => bv.textContent = b.value;
  file.onchange = () => {
    const f = file.files[0];
    if (!f) { pre.style.display = 'none'; return; }
    pre.src = URL.createObjectURL(f); pre.style.display = 'block';
  };
  document.getElementById('f').onsubmit = () => {
    const g = document.getElementById('go');
    g.disabled = true; g.textContent = 'Compiling & uploading… (can take ~1 min)';
  };
</script>
</body>
</html>
