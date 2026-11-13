<?php
/*
 * Filename: includes/require_admin.php
 *
 * One shared definition of "admin" for pages, APIs and the navbar.
 * The role is read live from the DB (once per request), so a demotion
 * takes effect on the user's next request.
 */

require_once __DIR__ . '/lightboard/core/database.php';

const ADMIN_ROLE = 'admin';

function isAdmin(bool $allowCached = false): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (empty($_SESSION['userID'])) {
        return $cache = false;
    }

    // Navbar only: trust a role checked within the last 60 seconds.
    if ($allowCached
        && isset($_SESSION['admin_flag'], $_SESSION['admin_checked_at'])
        && time() - $_SESSION['admin_checked_at'] < 60) {
        return (bool) $_SESSION['admin_flag'];
    }

    try {
        $stmt = db()->prepare(
            'SELECT r.roleName FROM users u
               JOIN roles r ON r.roleID = u.roleID
              WHERE u.userID = ? LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['userID']]);
        $cache = strtolower((string) $stmt->fetchColumn()) === ADMIN_ROLE;
        $_SESSION['admin_flag']       = $cache;
        $_SESSION['admin_checked_at'] = time();
    } catch (Throwable $e) {
        error_log('isAdmin lookup failed: ' . $e->getMessage());
        $cache = false; // fail closed
    }
    return $cache;
}

// For pages: call after require_login.php.
function requireAdminPage(): void
{
    if (!isAdmin()) {
        header('Location: /ec_lightboard/web/display.php');
        exit;
    }
}

// For APIs: call after bootstrap.php (uses its respond()).
function requireAdminApi(): void
{
    if (empty($_SESSION['userID'])) {
        respond(401, ['error' => 'Please log in.']);
    }
    if (!isAdmin()) {
        respond(403, ['error' => 'Admins only.']);
    }
}