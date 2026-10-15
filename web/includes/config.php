<?php
declare(strict_types=1);

return [
    // --- Database (remote MySQL; tables come from schema.sql) ---
    'db' => [
        'host'    => 'srv557.hstgr.io',
        'port'    => 3306,
        'name'    => 'u413142534_lightshowdb',
        'user'    => 'u413142534_lightshow',
        'pass'    => '46fOX9xA!',
    ],

    // --- Board / arduino-cli ---
    'sketch_root' => '/home/lightboard/Documents/ec_lightboard/sketches',
    'sketch_name' => 'webImage',            // folder + .ino name must match
    'arduino_cli' => '/usr/local/bin/arduino-cli',
    'port'        => '/dev/ttyUSB0',
    'fqbn'        => 'esp32:esp32:esp32',
    'timeout'     => 300,                   // seconds for compile+upload

    // --- Images ---
    'size'        => 48,
    'max_bytes'   => 8 * 1024 * 1024,
    'auto_resize' => true,                  // center-crop + scale non-48x48 uploads

    // --- Access ---
    // Set a password to require HTTP Basic auth (username: admin). Empty = no auth.
    'password'    => '',
];
