<?php
// GET /api/profile.php  -> the logged-in user's profile
require __DIR__ . '/bootstrap.php';
require_method('GET');

if (empty($_SESSION['userID'])) {
    respond(401, ['error' => 'Not logged in.']);
}

$stmt = db()->prepare(
    'SELECT u.Fname, u.Lname, u.email, r.roleName
       FROM users u
       LEFT JOIN roles r ON r.roleID = u.roleID
      WHERE u.userID = ?'
);
$stmt->execute([$_SESSION['userID']]);
$row = $stmt->fetch();

if (!$row) { // account was deleted while the session was alive
    $_SESSION = [];
    session_destroy();
    respond(401, ['error' => 'Not logged in.']);
}

respond(200, [
    'firstName' => $row['Fname'],
    'lastName'  => $row['Lname'],
    'email'     => $row['email'],
    'role'      => $row['roleName'] ?? 'unknown',
    'isAdmin'   => strtolower((string) $row['roleName']) === 'admin',
]);