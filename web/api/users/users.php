<?php
// /api/users/users.php   (admin only)
//
//   GET   ?page=1&limit=20&search=&role=admin|user   Paginated user list
//   PATCH ?id=5  body: role ("admin" or "user")      Promote / demote
require __DIR__ . '/../authentication/bootstrap.php';
require_once __DIR__ . '/../../includes/require_admin.php';

const PAGE_SIZES = [10, 20, 50, 100];
const ROLE_NAMES = ['admin', 'user'];

requireAdminApi();
$pdo = db();
$me  = (int) $_SESSION['userID'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':   list_users($pdo, $me);  break;
    case 'PATCH': change_role($pdo, $me); break;
    default:
        header('Allow: GET, PATCH');
        respond(405, ['error' => 'Method not allowed.']);
}


function list_users(PDO $pdo, int $me): void
{
    $limit = (int) ($_GET['limit'] ?? 20);
    if (!in_array($limit, PAGE_SIZES, true)) {
        $limit = 20; // capped server-side, never trust the client
    }
    $page = max(1, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));

    $where  = [];
    $params = [];

    $search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = "(u.Fname LIKE ? OR u.Lname LIKE ? OR u.email LIKE ?
                     OR CONCAT(u.Fname, ' ', u.Lname) LIKE ?)";
        array_push($params, $like, $like, $like, $like);
    }

    $role = (string) ($_GET['role'] ?? '');
    if ($role !== '') {
        if (!in_array($role, ROLE_NAMES, true)) {
            respond(400, ['error' => 'Invalid role filter.']);
        }
        $where[]  = 'r.roleName = ?';
        $params[] = $role;
    }

    $from = 'FROM users u LEFT JOIN roles r ON r.roleID = u.roleID'
          . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

    $stmt = $pdo->prepare("SELECT COUNT(*) $from");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $pages  = max(1, (int) ceil($total / $limit));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $limit; // both ints, safe to inline

    $stmt = $pdo->prepare(
        "SELECT u.userID, u.Fname, u.Lname, u.email, r.roleName
         $from
         ORDER BY u.Lname, u.Fname, u.userID
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);

    $users = [];
    foreach ($stmt as $r) {
        $users[] = [
            'userID'    => (int) $r['userID'],
            'firstName' => $r['Fname'],
            'lastName'  => $r['Lname'],
            'email'     => $r['email'],
            'role'      => $r['roleName'] ?? 'unknown',
        ];
    }

    respond(200, [
        'users' => $users,
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'limit' => $limit,
        'me'    => $me,
    ]);
}


function change_role(PDO $pdo, int $me): void
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        respond(400, ['error' => 'A valid id is required.']);
    }

    $role = (string) (json_input()['role'] ?? '');
    if (!in_array($role, ROLE_NAMES, true)) {
        respond(400, ['error' => 'Role must be "admin" or "user".']);
    }
    if ($id === $me) {
        respond(403, ['error' => 'You cannot change your own role.']);
    }

    // One writer at a time, so two admins can't demote each other at once
    // and leave the site with zero admins.
    if ((int) $pdo->query("SELECT GET_LOCK('user_role_write', 5)")->fetchColumn() !== 1) {
        respond(503, ['error' => 'Busy. Please try again.']);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT r.roleName FROM users u
               LEFT JOIN roles r ON r.roleID = u.roleID
              WHERE u.userID = ?'
        );
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            respond(404, ['error' => 'User not found.']);
        }
        if ($current === $role) {
            respond(409, ['error' => 'That user already has the ' . $role . ' role.']);
        }

        if ($current === 'admin' && $role !== 'admin') {
            $admins = (int) $pdo->query(
                "SELECT COUNT(*) FROM users u JOIN roles r ON r.roleID = u.roleID
                  WHERE r.roleName = 'admin'"
            )->fetchColumn();
            if ($admins <= 1) {
                respond(409, ['error' => 'The last remaining admin cannot be demoted.']);
            }
        }

        $stmt = $pdo->prepare('SELECT roleID FROM roles WHERE roleName = ?');
        $stmt->execute([$role]);
        $roleID = $stmt->fetchColumn();
        if ($roleID === false) {
            throw new RuntimeException('roles table has no row for "' . $role . '".');
        }

        $stmt = $pdo->prepare('UPDATE users SET roleID = ? WHERE userID = ?');
        $stmt->execute([(int) $roleID, $id]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('user_role_write')");
    }

    respond(200, ['message' => 'Role updated.', 'role' => $role]);
}