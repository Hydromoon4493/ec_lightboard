<?php
// POST /api/login.php
// Body (JSON): email, password
require __DIR__ . '/bootstrap.php';
require_method('POST');

$in       = json_input();
$email    = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');

if ($email === '' || $password === '') {
    respond(400, ['error' => 'Please enter your email and password.']);
}

$stmt = db()->prepare('SELECT userID, password FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Same message for "no such email" and "wrong password".
if (!$user || !password_verify($password, $user['password'])) {
    respond(401, ['error' => 'Invalid email or password.']);
}

session_regenerate_id(true);
$_SESSION['userID'] = (int) $user['userID'];

respond(200, ['message' => 'Logged in.']);