<?php
// superadminlaborajax.php  —  Superadmin: pirmahan (Conforme) o ibalik ang Labor Quotation ng PC
// Pirma = active signature sa noblesignature (gaya ng budget request approval)

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

function slOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[superadminlaborajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});

function slAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function slOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return slAll($conn, $sql, $types, $args)[0] ?? null;
}
function slUserName(mysqli $conn, int $uid): string
{
    return slOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function slUrl(?string $path): ?string
{
    return $path ? rtrim(BASE_URL, '/') . '/' . ltrim($path, '/') : null;
}
function slActiveSig(mysqli $conn, int $uid): ?string
{
    return slOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1", 'i', [$uid])['path'] ?? null;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = slAll($conn, "
        SELECT q.id, q.quote_no, q.project_name, q.location, q.subcontractor, q.quote_date, q.duration_days, q.total, q.prepared_by,
               q.status, COALESCE(q.submitted_at, q.created_at) AS submitted_at, q.approved_by, q.approved_at, q.return_remarks, q.returned_at,
               d.dr_no, t.ref_no, f.name AS foreman_name,
               (SELECT COUNT(*) FROM noblepc_laborquote_item i WHERE i.quote_id = q.id) AS item_count
        FROM noblepc_laborquote q
        JOIN noblepc_dr d ON d.id = q.dr_id
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_foreman f ON f.dr_id = q.dr_id
        ORDER BY FIELD(q.status, 'pending', 'returned', 'approved'), COALESCE(q.submitted_at, q.created_at) ASC, q.id DESC
    ");
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['total'] = (float) $r['total'];
        $r['resubmitted'] = $r['status'] === 'pending' && !empty($r['return_remarks']);
    }
    unset($r);
    slOut(['success' => true, 'rows' => $rows, 'me' => slUserName($conn, $currentUserId),
           'my_signature' => slUrl(slActiveSig($conn, $currentUserId))]);
}

// ================= VIEW =================
if ($action === 'quote') {
    $id = intval($_GET['id'] ?? 0);
    $q = slOne($conn, "SELECT q.*, d.dr_no, d.dr_date, t.ref_no FROM noblepc_laborquote q JOIN noblepc_dr d ON d.id = q.dr_id JOIN nobletransmittal t ON t.id = d.transmittal_id WHERE q.id = ? LIMIT 1", 'i', [$id]);
    if (!$q) slOut(['success' => false, 'message' => 'Quotation not found.']);
    $q['items'] = slAll($conn, "SELECT section_no, section_name, item_no, item, qty, unit, unit_cost FROM noblepc_laborquote_item WHERE quote_id = ? ORDER BY section_no, item_no", 'i', [$id]);
    $q['signature_url'] = $q['status'] === 'approved' ? slUrl($q['approval_signature']) : null;
    unset($q['approval_signature']);
    $f = slOne($conn, "SELECT name, contact_no, id_type, id_number, id_photo FROM noblepc_foreman WHERE dr_id = ? LIMIT 1", 'i', [(int) $q['dr_id']]);
    if ($f) { $f['id_photo_url'] = slUrl($f['id_photo']); unset($f['id_photo']); }
    slOut(['success' => true, 'quote' => $q, 'foreman' => $f]);
}

// ================= APPROVE (Conforme) =================
if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') slOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $me = slUserName($conn, $currentUserId);
    if ($me === '') slOut(['success' => false, 'message' => 'Your account name was not found. Please log in again.']);
    $sigPath = slActiveSig($conn, $currentUserId);
    if (!$sigPath) slOut(['success' => false, 'message' => 'You have no active signature yet. Add one in Insert Signature first.']);

    $st = $conn->prepare("UPDATE noblepc_laborquote SET status = 'approved', approved_by = ?, approved_by_id = ?, approved_at = NOW(), approval_signature = ?
                          WHERE id = ? AND status = 'pending'");
    $st->bind_param('sisi', $me, $currentUserId, $sigPath, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) {
        $cur = slOne($conn, "SELECT status FROM noblepc_laborquote WHERE id = ?", 'i', [$id]);
        slOut(['success' => false, 'message' => !$cur ? 'Quotation not found.' : ($cur['status'] === 'approved' ? 'This quotation is already approved.' : 'This quotation was returned to the PC. Wait for them to resubmit it.')]);
    }
    $q = slOne($conn, "SELECT quote_no FROM noblepc_laborquote WHERE id = ?", 'i', [$id]);
    slOut(['success' => true, 'quote_no' => $q['quote_no'] ?? '']);
}

// ================= RETURN TO PC =================
if ($action === 'return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') slOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if (mb_strlen($remarks) < 3) slOut(['success' => false, 'message' => 'Tell the PC what to fix (remarks are required).']);
    $me = slUserName($conn, $currentUserId);

    $st = $conn->prepare("UPDATE noblepc_laborquote SET status = 'returned', return_remarks = ?, returned_by = ?, returned_at = NOW() WHERE id = ? AND status = 'pending'");
    $st->bind_param('ssi', $remarks, $me, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) {
        $cur = slOne($conn, "SELECT status FROM noblepc_laborquote WHERE id = ?", 'i', [$id]);
        slOut(['success' => false, 'message' => !$cur ? 'Quotation not found.' : ($cur['status'] === 'approved' ? 'This quotation is already approved.' : 'This quotation was already returned.')]);
    }
    slOut(['success' => true]);
}

slOut(['success' => false, 'message' => 'Unknown action.']);