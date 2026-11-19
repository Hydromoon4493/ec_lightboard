<?php
// POST /api/authentication/change_email.php
// Body (JSON): currentPassword, newEmail

require __DIR__ . '/bootstrap.php';
require_method('POST');

if (empty($_SESSION['userID'])) {
    respond(401, ['error' => 'Please log in again.']);
}

$in = json_input();

$currentPassword = (string) ($in['currentPassword'] ?? '');
$newEmail = strtolower(trim((string) ($in['newEmail'] ?? '')));

if ($currentPassword === '' || $newEmail === '') {
    respond(400, ['error' => 'Please complete all fields.']);
}

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 255) {
    respond(400, ['error' => 'Please enter a valid email address.']);
}

$pdo = db();

// Get the current account details.
$stmt = $pdo->prepare(
    'SELECT email, password FROM users WHERE userID = ?'
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

if (strtolower($user['email']) === $newEmail) {
    respond(400, ['error' => 'That is already your current email address.']);
}

// Check whether another account uses this email.
$stmt = $pdo->prepare(
    'SELECT userID FROM users WHERE email = ? AND userID != ?'
);
$stmt->execute([$newEmail, (int) $_SESSION['userID']]);

if ($stmt->fetch()) {
    respond(409, ['error' => 'That email address is already in use.']);
}

// Update the email.
try {
    $stmt = $pdo->prepare(
        'UPDATE users SET email = ? WHERE userID = ?'
    );
    $stmt->execute([$newEmail, (int) $_SESSION['userID']]);
} catch (PDOException $e) {
    // Handles a duplicate email if another account claimed it
    // between the availability check and the update.
    if ($e->getCode() === '23000') {
        respond(409, ['error' => 'That email address is already in use.']);
    }

    throw $e;
}

respond(200, [
    'message' => 'Your email address has been updated.',
    'email' => $newEmail
]);
