
<?php
/*
 * Filename: web/includes/auth/require_admin.php
 *
 * API administrator authorization helper.
 * Requires an active login session and verifies the user's admin role.
 *
 * Must be included after authentication/bootstrap.php.
 */

function requireAdminApi(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['userID'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => 'Authentication required.'
        ]);
        exit;
    }

    try {
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT r.roleName
             FROM users u
             LEFT JOIN roles r ON r.roleID = u.roleID
             WHERE u.userID = ?
             LIMIT 1'
        );

        $stmt->execute([(int) $_SESSION['userID']]);

        $role = $stmt->fetchColumn();

        if ($role !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'error' => 'Administrator access required.'
            ]);
            exit;
        }

    } catch (Throwable $e) {
        error_log('Admin authorization error: ' . $e);

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => 'Could not verify administrator permissions.'
        ]);
        exit;
    }
}
