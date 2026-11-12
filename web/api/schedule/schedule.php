<?php
// /api/schedule.php
//
//   GET    [?status=pending]              Logged-in user: their own requests
//   GET    ?scope=all[&status=pending]    Admin only: every request (with requester info)
//   POST   body: displaySource, displayID, start, end, note
//                                         Create a request (status = pending)
//   PATCH  ?id=5  body: status            Admin only: "approved" or "denied" (pending requests only)
//   DELETE ?id=5                          Cancel a request (soft delete: status = "cancelled").
//                                         Owner: own request that hasn't started. Admin: any active request.
//
// start / end use the datetime-local format "YYYY-MM-DDTHH:MM" and are Eastern time.
require __DIR__ . '/../authentication/bootstrap.php';
require_once __DIR__ . '/../../includes/lightboard/core/database.php'; // SOURCES

date_default_timezone_set('America/New_York');

const ADMIN_ROLE_NAME      = 'admin';
const MIN_LEAD_SECONDS     = 24 * 3600; // must be requested at least 24 h ahead
const MAX_DURATION_SECONDS = 72 * 3600; // a request may last at most 72 h
const NOTE_MAX_LENGTH      = 250;
const STATUSES             = ['pending', 'approved', 'denied', 'cancelled'];
const CONFLICT_MESSAGE     = 'There is already a display approved or requested during the times selected.';

if (empty($_SESSION['userID'])) {
    respond(401, ['error' => 'Please log in.']);
}
$userID = (int) $_SESSION['userID'];
$pdo    = db();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':    list_requests($pdo, $userID);   break;
    case 'POST':   create_request($pdo, $userID);  break;
    case 'PATCH':  review_request($pdo, $userID);  break;
    case 'DELETE': cancel_request($pdo, $userID);  break;
    default:
        header('Allow: GET, POST, PATCH, DELETE');
        respond(405, ['error' => 'Method not allowed.']);
}


/* ============================================================
 * Helpers
 * ============================================================ */

function is_admin(PDO $pdo, int $userID): bool
{
    $stmt = $pdo->prepare(
        'SELECT r.roleName FROM users u JOIN roles r ON r.roleID = u.roleID WHERE u.userID = ?'
    );
    $stmt->execute([$userID]);
    return $stmt->fetchColumn() === ADMIN_ROLE_NAME;
}

// Strict parse so "2026-13-45T99:99" and similar are rejected.
function parse_local(string $value): ?DateTimeImmutable
{
    $d   = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $err = DateTimeImmutable::getLastErrors();
    if (!$d || ($err && ($err['warning_count'] > 0 || $err['error_count'] > 0))) {
        return null;
    }
    return $d;
}

function id_from_query(): int
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        respond(400, ['error' => 'A valid id is required.']);
    }
    return $id;
}

// One writer at a time, so two people can't both pass the overlap check and both insert.
function acquire_lock(PDO $pdo): void
{
    if ((int) $pdo->query("SELECT GET_LOCK('schedule_write', 5)")->fetchColumn() !== 1) {
        respond(503, ['error' => 'The schedule is busy. Please try again.']);
    }
}

function release_lock(PDO $pdo): void
{
    $pdo->query("SELECT RELEASE_LOCK('schedule_write')");
}

// True if an existing request with one of $statuses overlaps [$start, $end).
// Back-to-back requests (one ends exactly when the next starts) are NOT overlaps.
function overlap_exists(PDO $pdo, string $start, string $end, array $statuses, ?int $excludeID = null): bool
{
    $marks  = implode(',', array_fill(0, count($statuses), '?'));
    $sql    = "SELECT 1 FROM schedule WHERE status IN ($marks) AND startEvent < ? AND endEvent > ?";
    $params = [...$statuses, $end, $start];
    if ($excludeID !== null) {
        $sql     .= ' AND eventID <> ?';
        $params[] = $excludeID;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function fetch_request(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT eventID, userID, status, startEvent, endEvent FROM schedule WHERE eventID = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(404, ['error' => 'Request not found.']);
    }
    return $row;
}


/* ============================================================
 * GET
 * ============================================================ */

function list_requests(PDO $pdo, int $userID): void
{
    $all = ($_GET['scope'] ?? '') === 'all';
    if ($all && !is_admin($pdo, $userID)) {
        respond(403, ['error' => 'Admins only.']);
    }

    $where  = [];
    $params = [];
    if (!$all) {
        $where[]  = 's.userID = ?';
        $params[] = $userID;
    }
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        if (!in_array($_GET['status'], STATUSES, true)) {
            respond(400, ['error' => 'Invalid status filter.']);
        }
        $where[]  = 's.status = ?';
        $params[] = $_GET['status'];
    }
    // Upcoming = not finished yet. Times are stored in Eastern, which is
    // this file's default timezone, so date() matches the stored values.
    if (($_GET['upcoming'] ?? '') === '1') {
        $where[]  = 's.endEvent > ?';
        $params[] = date('Y-m-d H:i:s');
    }

    // Only metadata is selected: the image BLOBs are never loaded here.
    $sql = "SELECT s.eventID, s.userID, s.pDisplayID, s.cDisplayID,
                 COALESCE(p.name, c.name) AS displayName,
                 DATE_FORMAT(s.startEvent, '%Y-%m-%dT%H:%i') AS startText,
                 DATE_FORMAT(s.endEvent,   '%Y-%m-%dT%H:%i') AS endText,
                 s.note, s.status,
                 DATE_FORMAT(s.createdAt,  '%Y-%m-%d') AS createdText,
                 u.Fname, u.Lname, u.email
            FROM schedule s
            LEFT JOIN presetDisplay p ON p.pDisplayID = s.pDisplayID
            LEFT JOIN customDisplay c ON c.cDisplayID = s.cDisplayID
            LEFT JOIN users u         ON u.userID     = s.userID"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY ' . (($_GET['sort'] ?? '') === 'start' ? 's.startEvent ASC' : 's.createdAt DESC');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt as $r) {
        $item = [
            'eventID'       => (int) $r['eventID'],
            'displaySource' => $r['pDisplayID'] !== null ? 'p' : 'c',
            'displayID'     => (int) ($r['pDisplayID'] ?? $r['cDisplayID']),
            'displayName'   => $r['displayName'],
            'start'         => $r['startText'],
            'end'           => $r['endText'],
            'note'          => $r['note'],
            'status'        => $r['status'],
            'createdAt'     => $r['createdText'],
        ];
        if ($all) {
            $item['user'] = [
                'userID' => $r['userID'] !== null ? (int) $r['userID'] : null,
                'name'   => trim(($r['Fname'] ?? '') . ' ' . ($r['Lname'] ?? '')),
                'email'  => $r['email'],
            ];
        }
        $out[] = $item;
    }

    respond(200, ['requests' => $out]);
}


/* ============================================================
 * POST
 * ============================================================ */

function create_request(PDO $pdo, int $userID): void
{
    // Support both standard form POST and JSON payloads
    $in        = !empty($_POST) ? $_POST : json_input();
    $source    = (string) ($in['displaySource'] ?? '');
    $displayID = filter_var($in['displayID'] ?? null, FILTER_VALIDATE_INT);
    $startRaw  = trim((string) ($in['start'] ?? ''));
    $endRaw    = trim((string) ($in['end'] ?? ''));
    $note      = trim((string) ($in['note'] ?? ''));

    if (!isset(SOURCES[$source]) || $displayID === false || $displayID < 1) {
        respond(400, ['error' => 'Please select a display.']);
    }
    if ($startRaw === '' || $endRaw === '') {
        respond(400, ['error' => 'Please choose a start and end time.']);
    }
    if (mb_strlen($note) > NOTE_MAX_LENGTH) {
        respond(400, ['error' => 'Notes must be ' . NOTE_MAX_LENGTH . ' characters or fewer.']);
    }

    $start = parse_local($startRaw);
    $end   = parse_local($endRaw);
    if ($start === null || $end === null) {
        respond(400, ['error' => 'Please enter valid dates and times.']);
    }
    if ($end <= $start) {
        respond(400, ['error' => 'End time must be after the start time.']);
    }
    if ($start->getTimestamp() < time() + MIN_LEAD_SECONDS) {
        respond(400, ['error' => 'Requests must be made at least 24 hours in advance.']);
    }
    if ($end->getTimestamp() - $start->getTimestamp() > MAX_DURATION_SECONDS) {
        respond(400, ['error' => 'A display can be scheduled for at most 72 hours.']);
    }

    // Table/column names come from the SOURCES whitelist, never from the client.
    $s    = SOURCES[$source];
    $stmt = $pdo->prepare("SELECT 1 FROM {$s['table']} WHERE {$s['id']} = ?");
    $stmt->execute([$displayID]);
    if (!$stmt->fetchColumn()) {
        respond(404, ['error' => 'That display no longer exists.']);
    }

    $startSql = $start->format('Y-m-d H:i:s');
    $endSql   = $end->format('Y-m-d H:i:s');

    acquire_lock($pdo);
    try {
        if (overlap_exists($pdo, $startSql, $endSql, ['pending', 'approved'])) {
            respond(409, ['error' => CONFLICT_MESSAGE]);
        }

        // Status is set by the server (defaults to "pending"); never taken from the client.
        $stmt = $pdo->prepare(
            'INSERT INTO schedule (userID, pDisplayID, cDisplayID, startEvent, endEvent, note, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userID,
            $source === 'p' ? $displayID : null,
            $source === 'c' ? $displayID : null,
            $startSql,
            $endSql,
            $note === '' ? null : $note,
            date('Y-m-d H:i:s'),
        ]);
        $eventID = (int) $pdo->lastInsertId();
    } finally {
        release_lock($pdo);
    }

    respond(201, ['message' => 'Request submitted.', 'eventID' => $eventID]);
}


/* ============================================================
 * PATCH (admin: approve / deny)
 * ============================================================ */

function review_request(PDO $pdo, int $userID): void
{
    if (!is_admin($pdo, $userID)) {
        respond(403, ['error' => 'Admins only.']);
    }

    $id     = id_from_query();
    $status = (string) (json_input()['status'] ?? '');
    if (!in_array($status, ['approved', 'denied'], true)) {
        respond(400, ['error' => 'Status must be "approved" or "denied".']);
    }

    acquire_lock($pdo);
    try {
        $row = fetch_request($pdo, $id);
        if ($row['status'] !== 'pending') {
            respond(409, ['error' => 'Only pending requests can be approved or denied.']);
        }
        if ($status === 'approved'
            && new DateTimeImmutable($row['startEvent']) <= new DateTimeImmutable('now')) {
            respond(409, ['error' => 'This request\'s start time has already passed.']);
        }
        if ($status === 'approved'
            && overlap_exists($pdo, $row['startEvent'], $row['endEvent'], ['approved'], $id)) {
            respond(409, ['error' => CONFLICT_MESSAGE]);
        }

        $stmt = $pdo->prepare("UPDATE schedule SET status = ? WHERE eventID = ? AND status = 'pending'");
        $stmt->execute([$status, $id]);
    } finally {
        release_lock($pdo);
    }

    respond(200, ['message' => 'Request ' . $status . '.']);
}


/* ============================================================
 * DELETE (soft: status = "cancelled")
 * ============================================================ */

function cancel_request(PDO $pdo, int $userID): void
{
    $id    = id_from_query();
    $row   = fetch_request($pdo, $id);
    $admin = is_admin($pdo, $userID);

    if ((int) $row['userID'] !== $userID && !$admin) {
        respond(403, ['error' => 'You can only cancel your own requests.']);
    }
    if (!in_array($row['status'], ['pending', 'approved'], true)) {
        respond(409, ['error' => 'This request is already ' . $row['status'] . '.']);
    }
    if (!$admin && new DateTimeImmutable($row['startEvent']) <= new DateTimeImmutable('now')) {
        respond(409, ['error' => 'Requests that have already started cannot be cancelled.']);
    }

    $stmt = $pdo->prepare("UPDATE schedule SET status = 'cancelled' WHERE eventID = ?");
    $stmt->execute([$id]);

    respond(200, ['message' => 'Request cancelled.']);
}