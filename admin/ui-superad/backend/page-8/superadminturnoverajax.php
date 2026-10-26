<?php
// superadminturnoverajax.php  —  Superadmin: pirmahan (Respectfully, GM) o ibalik ang Turnover Letter ng PC

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function sOut(array $d): void
{
    echo json_encode($d);
    exit;
}
set_exception_handler(function (Throwable $e) {
    error_log('[superadminturnoverajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});
function sAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function sOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return sAll($conn, $sql, $types, $args)[0] ?? null;
}
function sUrl(?string $p): ?string
{
    return $p ? rtrim(BASE_URL, '/') . '/' . ltrim($p, '/') : null;
}
function sUserName(mysqli $conn, int $uid): string
{
    return sOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function sActiveSig(mysqli $conn, int $uid): ?string
{
    return sOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1", 'i', [$uid])['path'] ?? null;
}

if ($action === 'list') {
    $rows = sAll($conn, "
        SELECT tv.id, tv.dr_id, tv.letter_date, tv.project, tv.location, tv.client_name, tv.status, tv.prepared_by, COALESCE(tv.submitted_at, tv.created_at) AS submitted_at,
               tv.sa_by, tv.sa_at, tv.return_remarks, tv.returned_at, tv.closed_at, tv.accepted_date,
               d.dr_no, q.project_name, q.subcontractor,
               (SELECT COUNT(*) FROM noblepc_punch p WHERE p.dr_id = tv.dr_id) AS punch_total,
               (SELECT COUNT(*) FROM noblepc_turnover_scope s WHERE s.turnover_id = tv.id) AS scope_count
        FROM noblepc_turnover tv
        JOIN noblepc_dr d ON d.id = tv.dr_id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = tv.dr_id
        ORDER BY FIELD(tv.status, 'pending_sa', 'returned', 'approved', 'closed'), COALESCE(tv.submitted_at, tv.created_at) ASC, tv.id DESC");
    foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['punch_total'] = (int) $r['punch_total']; $r['scope_count'] = (int) $r['scope_count']; }
    unset($r);
    sOut(['success' => true, 'rows' => $rows, 'me' => sUserName($conn, $currentUserId), 'my_signature' => sUrl(sActiveSig($conn, $currentUserId))]);
}

if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $t = sOne($conn, "SELECT tv.*, d.dr_no, q.quote_no, q.project_name AS quote_project, q.subcontractor, q.total AS contract_amount FROM noblepc_turnover tv JOIN noblepc_dr d ON d.id = tv.dr_id LEFT JOIN noblepc_laborquote q ON q.dr_id = tv.dr_id WHERE tv.id = ?", 'i', [$id]);
    if (!$t) sOut(['success' => false, 'message' => 'Turnover letter not found.']);
    $t['scope'] = sAll($conn, "SELECT item_no, scope, area, status FROM noblepc_turnover_scope WHERE turnover_id = ? ORDER BY item_no", 'i', [$id]);
    $t['sa_signature_url'] = in_array($t['status'], ['approved', 'closed'], true) ? sUrl($t['sa_signature']) : null;
    $t['signed_copy_url'] = sUrl($t['signed_copy']);
    unset($t['sa_signature']);
    $punch = sAll($conn, "SELECT area, deficiency, status, verified_by, verified_at FROM noblepc_punch WHERE dr_id = ? ORDER BY id", 'i', [(int) $t['dr_id']]);
    $bill = sOne($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status = 'approved' THEN this_period END), 0) AS approved FROM noblepc_billing WHERE dr_id = ?", 'i', [(int) $t['dr_id']]);
    $tl = sOne($conn, "SELECT date_started, date_finish FROM noblepc_timeline WHERE dr_id = ?", 'i', [(int) $t['dr_id']]);
    sOut(['success' => true, 'turnover' => $t, 'punch' => $punch, 'billing' => ['count' => (int) $bill['n'], 'approved' => (float) $bill['approved']], 'timeline' => $tl]);
}

if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $me = sUserName($conn, $currentUserId);
    if ($me === '') sOut(['success' => false, 'message' => 'Your account name was not found. Please log in again.']);
    $sig = sActiveSig($conn, $currentUserId);
    if (!$sig) sOut(['success' => false, 'message' => 'You have no active signature yet. Add one in Insert Signature first.']);
    $st = $conn->prepare("UPDATE noblepc_turnover SET status = 'approved', sa_by = ?, sa_by_id = ?, sa_at = NOW(), sa_signature = ? WHERE id = ? AND status = 'pending_sa'");
    $st->bind_param('sisi', $me, $currentUserId, $sig, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) sOut(['success' => false, 'message' => 'This letter is not waiting for your signature anymore. Please reload.']);
    sOut(['success' => true]);
}

if ($action === 'return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if (mb_strlen($remarks) < 3) sOut(['success' => false, 'message' => 'Tell the PC what to fix (remarks are required).']);
    $me = sUserName($conn, $currentUserId);
    $st = $conn->prepare("UPDATE noblepc_turnover SET status = 'returned', return_remarks = ?, returned_by = ?, returned_at = NOW() WHERE id = ? AND status = 'pending_sa'");
    $st->bind_param('ssi', $remarks, $me, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) sOut(['success' => false, 'message' => 'This letter is not waiting for your signature anymore. Please reload.']);
    sOut(['success' => true]);
}

sOut(['success' => false, 'message' => 'Unknown action.']);