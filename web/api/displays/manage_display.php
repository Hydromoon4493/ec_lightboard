<?php
/*
 * Author: Sam Vossen 2026.11.10
 * Filename: api/displays/manage_display.php
 *
 * Handles renaming and deleting Lightboard displays.
 *
 * Permissions:
 * - Users can rename and delete their own custom displays.
 * - Administrators can rename and delete preset displays.
 *
 * Supported actions:
 * - rename
 * - delete
 */


require_once __DIR__ . '/../../includes/auth/require_login.php';
require_once __DIR__ . '/../../includes/lightboard/core/images.php';

header('Content-Type: application/json; charset=utf-8');


/* ============================================================
 * JSON RESPONSE HELPER
 * ============================================================
 */

function manageDisplayResponse(
    bool $success,
    string $message,
    int $status = 200
): void {

    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}


/* ============================================================
 * REQUEST VALIDATION
 * ============================================================
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    manageDisplayResponse(false, 'Invalid request method.', 405);
}


/* ============================================================
 * CSRF VALIDATION
 * ============================================================
 */

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['lightboard_csrf'])
    || !is_string($csrfToken)
    || !hash_equals($_SESSION['lightboard_csrf'], $csrfToken)
) {
    manageDisplayResponse(false, 'Invalid security token. Refresh the page and try again.', 403);
}


/* ============================================================
 * VALIDATE ACTION
 * ============================================================
 */

$action = $_POST['action'] ?? '';

if (!in_array($action, ['rename', 'delete'], true)) {
    manageDisplayResponse(false, 'Invalid display action.', 400);
}


/* ============================================================
 * VALIDATE SOURCE
 * ============================================================
 */

$source = $_POST['source'] ?? '';

if (!in_array($source, ['p', 'c'], true)) {
    manageDisplayResponse(false, 'Invalid display source.', 400);
}


/* ============================================================
 * VALIDATE DISPLAY ID
 * ============================================================
 */

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id < 1) {
    manageDisplayResponse(false, 'Invalid display ID.', 400);
}


/* ============================================================
 * VALIDATE NEW NAME
 * ============================================================
 */

$name = '';

if ($action === 'rename') {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        manageDisplayResponse(false, 'Display name cannot be empty.', 400);
    }

    if (function_exists('mb_strlen')) {
        $nameLength = mb_strlen($name);
    } else {
        $nameLength = strlen($name);
    }

    if ($nameLength > 45) {
        manageDisplayResponse(false, 'Display name cannot exceed 45 characters.', 400);
    }

}


/* ============================================================
 * DATABASE AND PERMISSIONS
 * ============================================================
 */

try {

    $pdo = db();

    $userID = (int) $_SESSION['userID'];

    /*
     * Retrieve the user's actual database role.
     */

    $roleStmt = $pdo->prepare(
        'SELECT roleID
         FROM users
         WHERE userID = ?
         LIMIT 1'
    );

    $roleStmt->execute([$userID]);

    $roleID = (int) $roleStmt->fetchColumn();

    $isAdmin = ($roleID === 1);


    /* ========================================================
     * SELECT TABLE AND ID COLUMN
     * ========================================================
     *
     * These identifiers are selected only from fixed values.
     * User input is never inserted into SQL identifiers.
     */

    if ($source === 'p') {

        if (!$isAdmin) {
            manageDisplayResponse(false, 'Administrator privileges required.', 403);
        }

        $table = 'presetDisplay';
        $idColumn = 'pDisplayID';

    } else {

        $table = 'customDisplay';
        $idColumn = 'cDisplayID';

    }


    /* ========================================================
     * VERIFY DISPLAY OWNERSHIP / EXISTENCE
     * ========================================================
     */

    if ($source === 'c') {

        $checkStmt = $pdo->prepare(
            'SELECT cDisplayID
             FROM customDisplay
             WHERE cDisplayID = ?
             AND userID = ?
             LIMIT 1'
        );

        $checkStmt->execute([
            $id,
            $userID
        ]);

    } else {

        $checkStmt = $pdo->prepare(
            'SELECT pDisplayID
             FROM presetDisplay
             WHERE pDisplayID = ?
             LIMIT 1'
        );

        $checkStmt->execute([$id]);

    }

    if (!$checkStmt->fetchColumn()) {
        manageDisplayResponse(false, 'Display not found or you do not have permission to manage it.', 404);
    }


    /* ========================================================
     * RENAME DISPLAY
     * ========================================================
     */

    if ($action === 'rename') {

        if ($source === 'c') {

            $updateStmt = $pdo->prepare(
                'UPDATE customDisplay
                 SET name = ?
                 WHERE cDisplayID = ?
                 AND userID = ?'
            );

            $updateStmt->execute([
                $name,
                $id,
                $userID
            ]);

        } else {

            $updateStmt = $pdo->prepare(
                'UPDATE presetDisplay
                 SET name = ?
                 WHERE pDisplayID = ?'
            );

            $updateStmt->execute([
                $name,
                $id
            ]);

        }

        manageDisplayResponse(true, 'Display renamed successfully.');

    }


    /* ========================================================
     * DELETE DISPLAY
     * ========================================================
     */

    if ($action === 'delete') {

        if ($source === 'c') {

            $deleteStmt = $pdo->prepare(
                'DELETE FROM customDisplay
                 WHERE cDisplayID = ?
                 AND userID = ?'
            );

            $deleteStmt->execute([
                $id,
                $userID
            ]);

        } else {

            $deleteStmt = $pdo->prepare(
                'DELETE FROM presetDisplay
                 WHERE pDisplayID = ?'
            );

            $deleteStmt->execute([$id]);

        }

        manageDisplayResponse(true, 'Display deleted successfully.');

    }


} catch (Throwable $e) {

    error_log(
        'Lightboard display management error: '
        . $e->getMessage()
    );

    manageDisplayResponse(false, 'A database error occurred while managing the display.', 500);

}
