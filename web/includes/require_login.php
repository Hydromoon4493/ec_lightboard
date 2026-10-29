<?php
// Include at the very top of any page that needs a logged-in user.
const LOGIN_URL = '/ec_lightboard/web/login.php'; // change if the site moves

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

if (empty($_SESSION['userID'])) {
    header('Location: ' . LOGIN_URL);
    exit;
}
?>