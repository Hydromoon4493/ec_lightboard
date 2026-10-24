<?php
// POST /api/signup.php
// Body (JSON): firstName, lastName, email, password, confirmPassword
require __DIR__ . '/bootstrap.php';
require_method('POST');

$in       = json_input();
$first    = trim((string) ($in['firstName'] ?? ''));
$last     = trim((string) ($in['lastName'] ?? ''));
$email    = strtolower(trim((string) ($in['email'] ?? '')));
$password = (string) ($in['password'] ?? '');
$confirm  = (string) ($in['confirmPassword'] ?? '');

if ($first === '' || $last === '' || $email === '' || $password === '') {
    respond(400, ['error' => 'Please fill in all fields.']);
}
if (mb_strlen($first) > 45 || mb_strlen($last) > 45) {
    respond(400, ['error' => 'Names must be 45 characters or fewer.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    respond(400, ['error' => 'Please enter a valid email address.']);
}
if (strlen($password) < 8) {
    respond(400, ['error' => 'Password must be at least 8 characters.']);
}
if (strlen($password) > 72) { // bcrypt ignores anything past 72 bytes
    respond(400, ['error' => 'Password must be 72 characters or fewer.']);
}
if ($password !== $confirm) {
    respond(400, ['error' => 'Passwords do not match.']);
}

$pdo = db();

// Duplicate email: friendly early check (the UNIQUE index is the real guarantee).
$stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    respond(409, ['error' => 'An account with that email already exists.']);
}

// New accounts always get the plain "user" role; never take a role from the client.
$stmt = $pdo->prepare('SELECT roleID FROM roles WHERE roleName = ?');
$stmt->execute(['user']);
$roleID = $stmt->fetchColumn();
if ($roleID === false) {
    throw new RuntimeException('roles table has no row with roleName "user".');
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (Fname, Lname, email, password, roleID) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$first, $last, $email, password_hash($password, PASSWORD_DEFAULT), $roleID]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // two signups raced past the check above
        respond(409, ['error' => 'An account with that email already exists.']);
    }
    throw $e;
}

// Sign them straight in.
session_regenerate_id(true);
$_SESSION['userID'] = (int) $pdo->lastInsertId();

respond(201, ['message' => 'Account created.']);