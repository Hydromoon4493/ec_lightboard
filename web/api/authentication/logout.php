<?php
// POST /api/logout.php
require __DIR__ . '/bootstrap.php';
require_method('POST');
 
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $p['secure'],
    ]);
}
session_destroy();
 
respond(200, ['message' => 'Logged out.']);