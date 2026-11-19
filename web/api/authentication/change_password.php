<?php
// POST /api/authentication/change_password.php
// Body (JSON): currentPassword, newPassword, confirmPassword

require __DIR__ . '/bootstrap.php';
require_method('POST');

if (empty($_SESSION['userID'])) {
    respond(401, ['error' => 'Please log in again.']);
}

$in = json_input();

$currentPassword = (string) ($in['currentPassword'] ?? '');
$newPassword = (string) ($in['newPassword'] ?? '');
$confirmPassword = (string) ($in['confirmPassword'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    respond(400, ['error' => 'Please complete all fields.']);
}

if (strlen($newPassword) < 8) {
    respond(400, ['error' => 'Your new password must be at least 8 characters.']);
}

if ($newPassword !== $confirmPassword) {
    respond(400, ['error' => 'Your new passwords do not match.']);
}

$pdo = db();

// Get the current password hash.
$stmt = $pdo->prepare(
    'SELECT password FROM users WHERE userID = ?'
);
$stmt->execute([(int) $_SESSION['userID']]);
$user = $stmt->fetch();

if (!$user) {
    respond(404, ['error' => 'Account not found.']);
}

// Verify the current password.
if (!password_verify($currentPassword, $user['password'])) {
    respond(401, ['error' => 'Your current password is incorrect.']);
}

// Hash the new password.
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

if ($newHash === false) {
    respond(500, ['error' => 'Could not update your password.']);
}

// Save the new hash.
$stmt = $pdo->prepare(
    'UPDATE users SET password = ? WHERE userID = ?'
);
$stmt->execute([$newHash, (int) $_SESSION['userID']]);

// Regenerate the session ID after the credential change.
session_regenerate_id(true);

respond(200, [
    'message' => 'Your password has been updated.'
]);
