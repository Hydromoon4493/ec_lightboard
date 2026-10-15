<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Lightboard diagnostics. Run on the Pi:   php testDB.php
 * Checks PHP, the database connection, the schema, the images and
 * the board setup, and explains common failures.
 * ------------------------------------------------------------------ */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line: php testDB.php');
}

$fails = 0;
$warns = 0;

function out(string $s = ''): void { echo $s . "\n"; }
function head(string $s): void { out("\n== $s =="); }
function ok(string $s): void { out("  [ OK ] $s"); }
function info(string $s): void { out("  [info] $s"); }
function warn(string $s): void { global $warns; $warns++; out("  [WARN] $s"); }
function fail(string $s, string $hint = ''): void
{
    global $fails;
    $fails++;
    out("  [FAIL] $s");
    if ($hint !== '') out("         -> $hint");
}

/* ------------------------------ PHP -------------------------------- */

head('PHP');
info('Version ' . PHP_VERSION . ' (' . PHP_OS . ')');
foreach (['pdo' => 'php-common', 'pdo_mysql' => 'php-mysql', 'gd' => 'php-gd', 'mbstring' => 'php-mbstring'] as $ext => $pkg) {
    extension_loaded($ext)
        ? ok("extension $ext")
        : fail("extension $ext missing", "sudo apt install $pkg");
}

/* ----------------------------- config ------------------------------ */

head('Config');
$cfgPath = __DIR__ . '/config.php';
if (!is_file($cfgPath)) {
    fail('config.php not found next to this script');
    exit(1);
}
$CFG = require $cfgPath;
$d   = $CFG['db'] ?? [];
foreach (['host', 'port', 'name', 'user', 'pass'] as $k) {
    if (!isset($d[$k]) || $d[$k] === '') fail("db.$k is empty");
}
if (($d['pass'] ?? '') === 'change-me') {
    fail('db.pass is still the placeholder "change-me"', 'Use the DB user password from hPanel > Databases');
}
info(sprintf('host=%s port=%s db=%s user=%s pass=%s',
    $d['host'] ?? '?', $d['port'] ?? '?', $d['name'] ?? '?', $d['user'] ?? '?',
    isset($d['pass']) ? '(' . strlen((string)$d['pass']) . ' chars)' : '?'));

/* ----------------------------- network ----------------------------- */

head('Network');
$host = (string)($d['host'] ?? '');
$port = (int)($d['port'] ?? 3306);
$ip   = gethostbyname($host);
if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
    fail("DNS lookup failed for $host", 'Check the Pi\'s internet connection and the host name');
} else {
    ok("$host resolves to $ip");
    $t0 = microtime(true);
    $s  = @fsockopen($host, $port, $errno, $errstr, 8);
    if ($s) {
        fclose($s);
        ok(sprintf('TCP port %d reachable (%.0f ms)', $port, (microtime(true) - $t0) * 1000));
    } else {
        fail("Cannot reach $host:$port ($errstr)", 'Firewall or the host blocks the port. Check hPanel > Databases > Remote MySQL');
    }
}

$pubIp = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 4]]));
if ($pubIp) {
    info("This Pi's public IP is $pubIp (this must be allowed in hPanel > Remote MySQL)");
}

/* ----------------------------- database ---------------------------- */

head('Database connection');
$pdo = null;
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $d['name'] ?? ''),
            (string)($d['user'] ?? ''),
            (string)($d['pass'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 8]
        );
        ok('Connected and authenticated');
    } catch (PDOException $e) {
        $code  = (int)($e->errorInfo[1] ?? 0);
        $hints = [
            1045 => 'Wrong password, or this Pi\'s IP is not allowed. Check the password and Remote MySQL in hPanel.',
            1044 => 'User has no access to that database. Add the user to the database in hPanel.',
            1049 => 'Database name not found. Check db.name (Hostinger prefixes it, like u123_name).',
            2002 => 'Connection refused or timed out. Check host/port and Remote MySQL.',
            2006 => 'Server dropped the connection.',
        ];
        fail('Could not connect: ' . $e->getMessage(), $hints[$code] ?? 'See the error above.');
    }
}

$tables = ['roles', 'users', 'customDisplay', 'presetDisplay', 'schedule'];
if ($pdo) {
    $v = $pdo->query('SELECT VERSION() AS v, CURRENT_USER() AS u, DATABASE() AS d')->fetch();
    info("Server {$v['v']}, user {$v['u']}, database {$v['d']}");
    $mp = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetch();
    if ($mp) info('max_allowed_packet = ' . round((int)$mp['Value'] / 1048576, 1) . ' MB');

    head('Schema');
    $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        if (in_array($t, $have, true)) {
            $n = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            ok(sprintf('%-14s %d row%s', $t, $n, $n === 1 ? '' : 's'));
        } else {
            $near = array_values(array_filter($have, fn($x) => strcasecmp($x, $t) === 0));
            $near
                ? fail("table $t missing, but found '{$near[0]}'", 'Table names are case-sensitive on Linux')
                : fail("table $t missing", 'Run the schema SQL on this database');
        }
    }

    head('Images');
    $sets = [
        'presetDisplay' => ['pDisplayID', 'p'],
        'customDisplay' => ['cDisplayID', 'c'],
    ];
    foreach ($sets as $table => [$idCol, $key]) {
        if (!in_array($table, $have, true)) continue;
        $rows = $pdo->query("SELECT $idCol AS id, name, mimeType, display FROM $table ORDER BY $idCol")->fetchAll();
        if (!$rows) { info("$table is empty"); continue; }
        foreach ($rows as $r) {
            $bytes = strlen((string)$r['display']);
            $label = sprintf('%s #%d "%s" (%s, %d bytes)', $table, $r['id'], $r['name'], $r['mimeType'], $bytes);
            if ($bytes === 0) { fail("$label is empty"); continue; }
            if (!extension_loaded('gd')) { info($label); continue; }
            $im = @imagecreatefromstring((string)$r['display']);
            if ($im === false) {
                fail("$label does not decode as an image", 'The blob may be corrupt. Re-insert it');
            } else {
                $w = imagesx($im); $h = imagesy($im);
                $size = $CFG['size'] ?? 48;
                ($w === $size && $h === $size)
                    ? ok("$label ${w}x$h")
                    : warn("$label is ${w}x$h; it will be " . (!empty($CFG['auto_resize']) ? 'center-cropped and scaled' : 'rejected (auto_resize is off)'));
            }
        }
    }
}

/* ------------------------------ board ------------------------------ */

head('Board setup');
$cli = (string)($CFG['arduino_cli'] ?? '');
if (is_file($cli) && is_executable($cli)) {
    $ver = trim((string)shell_exec(escapeshellarg($cli) . ' version 2>&1'));
    ok("arduino-cli found: $ver");
    $cores = (string)shell_exec(escapeshellarg($cli) . ' core list 2>&1');
    str_contains($cores, 'esp32:esp32')
        ? ok('esp32 core installed')
        : fail('esp32 core not installed', 'arduino-cli core install esp32:esp32');
    $libs = (string)shell_exec(escapeshellarg($cli) . ' lib list 2>&1');
    stripos($libs, 'FastLED') !== false
        ? ok('FastLED library installed')
        : fail('FastLED library not installed', 'arduino-cli lib install FastLED');
} else {
    fail("arduino-cli not found or not executable at $cli", 'Fix arduino_cli in config.php (try: which arduino-cli)');
}

$port = (string)($CFG['port'] ?? '');
if (file_exists($port)) {
    ok("$port exists");
    is_readable($port) && is_writable($port)
        ? ok('current user can read/write the serial port')
        : fail('no read/write access to the serial port', 'sudo usermod -aG dialout $USER, then log out and back in');
} else {
    fail("$port not found", 'Is the ESP32 plugged in? Check: ls /dev/ttyUSB* /dev/ttyACM*');
}

$root = (string)($CFG['sketch_root'] ?? '');
$dir  = $root . '/' . ($CFG['sketch_name'] ?? '');
if (is_dir($dir)) {
    is_writable($dir) ? ok("sketch folder writable: $dir") : fail("sketch folder not writable: $dir");
} elseif (is_dir($root) && is_writable($root)) {
    ok("sketch folder will be created under $root");
} else {
    fail("sketch_root missing or not writable: $root");
}

$tpl = __DIR__ . '/templates/webImage.ino.tpl';
if (is_file($tpl)) {
    $t = (string)file_get_contents($tpl);
    $missing = array_filter(['__GENERATED__', '__BRIGHTNESS__', '__IMAGE__'], fn($m) => !str_contains($t, $m));
    $missing ? fail('template missing placeholders: ' . implode(', ', $missing)) : ok('templates/webImage.ino.tpl has all placeholders');
} else {
    fail('templates/webImage.ino.tpl not found');
}

$up = ini_get('upload_max_filesize');
$pm = ini_get('post_max_size');
info("PHP upload_max_filesize=$up post_max_size=$pm (php -S can override with -d)");

/* ------------------------------ summary ---------------------------- */

head('Summary');
out("  $fails failure(s), $warns warning(s)");
out($fails === 0 ? '  Ready to run: php -S 0.0.0.0:8080' : '  Fix the [FAIL] items above, then run this again.');
exit($fails === 0 ? 0 : 1);
