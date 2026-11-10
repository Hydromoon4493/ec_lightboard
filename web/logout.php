
<?php
/*
 * Author: Sam Vossen 2026.11.10
 * Filename: logout.php
 *
 * Logs out the current user and redirects to the homepage.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session data.
$_SESSION = array();

// Remove the session cookie.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session.
session_destroy();

// Return to homepage.
header('Location: /ec_lightboard/web/index.php');
exit;
