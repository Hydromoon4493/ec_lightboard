<?php
// Shared setup for every API endpoint: JSON responses, DB connection, session.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($status, array $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Any uncaught error becomes a generic 500; details go to the server log only.
// set_exception_handler(function ($e) {
//     error_log((string) $e);
//     respond(500, ['error' => 'Something went wrong. Please try again.']);
// });
set_exception_handler(function ($e) {
    error_log((string) $e);
    respond(500, [
        'error' => 'Something went wrong. Please try again.',
        'debug' => $e->getMessage()   // TEMPORARY, remove before sharing the site
    ]);
});

function require_method($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        respond(405, ['error' => 'Method not allowed.']);
    }
}

function json_input()
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../../includes/config.php';
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();