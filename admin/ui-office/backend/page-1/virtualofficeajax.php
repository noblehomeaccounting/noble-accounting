<?php
// virtualofficeajax.php  —  Virtual Office: listahan ng lahat ng admin account at kung sino ang online
//
// Online = may heartbeat (noblerole.last_active) sa loob ng 60 segundo — kapareho ng presence dot sa sidebar.
// Basa lang. Pangalan, department, posisyon at branch lang ang ibinabalik (walang email / password / iba pa).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$me = intval($_SESSION['account_id'] ?? 0);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

set_exception_handler(function (Throwable $e) {
    error_log('[virtualofficeajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});

if ($me <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}

function voHasCol(mysqli $conn, string $col): bool
{
    try {
        $r = $conn->query("SHOW COLUMNS FROM noblerole LIKE '" . $conn->real_escape_string($col) . "'");
        return $r && $r->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// role (galing sa DB) → kuwarto sa opisina
$roomOf = [];
foreach ([
    'ROLE_SUPERADMIN' => 'superadmin', 'ROLE_ACCOUNTING' => 'accounting', 'ROLE_SALES' => 'sales', 'ROLE_DESIGNER' => 'designer',
    'ROLE_GRAPHIC' => 'graphic', 'ROLE_CUTTING' => 'cutting', 'ROLE_PO' => 'po', 'ROLE_PC' => 'pc',
    'ROLE_OPERATIONS' => 'operations', 'ROLE_HR' => 'hr', 'ROLE_IT' => 'it',
] as $const => $key) {
    if (defined($const)) $roomOf[(string) constant($const)] = $key;
}

$branchSel = voHasCol($conn, 'branch') ? 'branch' : "'' AS branch";
$rows = $conn->query("SELECT id, name, role, position, $branchSel,
                             CASE WHEN last_active IS NULL THEN NULL ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, last_active, NOW())) END AS secs
                      FROM noblerole ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$people = [];
foreach ($rows as $r) {
    $secs = $r['secs'] === null ? null : (int) $r['secs'];
    $people[] = [
        'id'       => (int) $r['id'],
        'name'     => trim((string) $r['name']) ?: 'User #' . $r['id'],
        'room'     => $roomOf[(string) $r['role']] ?? 'lobby',
        'role'     => (string) $r['role'],
        'position' => (string) $r['position'],
        'branch'   => (string) $r['branch'],
        'secs'     => $secs,                        // ilang segundo mula sa huling heartbeat
        'online'   => $secs !== null && $secs < 60,
        'me'       => (int) $r['id'] === $me,
    ];
}

echo json_encode(['success' => true, 'people' => $people, 'server_time' => date('c')]);