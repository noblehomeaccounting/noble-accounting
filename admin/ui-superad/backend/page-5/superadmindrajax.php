<?php
// superadmindrajax.php  —  Superadmin: pirmahan (approve) o ibalik (return) ang Delivery Receipt ng PC
// Mapi-print lang ng PC ang DR kapag approved na dito.

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

function saOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[superadmindrajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});

function saAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function saOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return saAll($conn, $sql, $types, $args)[0] ?? null;
}
function saUserName(mysqli $conn, int $uid): string
{
    return saOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function saSigUrl(?string $path): ?string
{
    return $path ? rtrim(BASE_URL, '/') . '/' . ltrim($path, '/') : null;
}
// Active signature ng user (galing sa Insert Signature → noblesignature), gaya ng sa budget request approval
function saActiveSig(mysqli $conn, int $uid): ?string
{
    $r = saOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1", 'i', [$uid]);
    return $r['path'] ?? null;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = saAll($conn, "
        SELECT d.id, d.dr_no, d.dr_date, d.client_name, d.address, d.scheduled_date, d.prepared_by, d.permit_required,
               COALESCE(d.status, 'pending') AS status, COALESCE(d.submitted_at, d.created_at) AS submitted_at,
               d.approved_by, d.approved_at, d.return_remarks, d.returned_by, d.returned_at,
               t.ref_no, t.project_name, p.req_no,
               (SELECT COUNT(*) FROM noblepc_dr_item i WHERE i.dr_id = d.id) AS item_count,
               (SELECT COALESCE(SUM(i.amount * IF(i.qty > 0, i.qty, 1)), 0) FROM noblepc_dr_item i WHERE i.dr_id = d.id) AS total
        FROM noblepc_dr d
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        ORDER BY FIELD(COALESCE(d.status, 'pending'), 'pending', 'returned', 'approved'),
                 COALESCE(d.submitted_at, d.created_at) ASC, d.id DESC
    ");
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['total'] = (float) $r['total'];
        $r['permit_required'] = $r['permit_required'] !== null ? (int) $r['permit_required'] : null;
        // naibalik na dati at ipinasa ulit
        $r['resubmitted'] = $r['status'] === 'pending' && !empty($r['return_remarks']);
    }
    unset($r);
    saOut(['success' => true, 'rows' => $rows, 'me' => saUserName($conn, $currentUserId),
           'my_signature' => saSigUrl(saActiveSig($conn, $currentUserId))]);
}

// ================= VIEW DR =================
if ($action === 'dr') {
    $id = intval($_GET['id'] ?? 0);
    $dr = saOne($conn, "SELECT d.*, t.ref_no, t.project_name FROM noblepc_dr d JOIN nobletransmittal t ON t.id = d.transmittal_id WHERE d.id = ? LIMIT 1", 'i', [$id]);
    if (!$dr) saOut(['success' => false, 'message' => 'DR not found.']);
    $dr['status'] = $dr['status'] ?: 'pending';
    $dr['permit_required'] = $dr['permit_required'] !== null ? (int) $dr['permit_required'] : null;
    $dr['items'] = saAll($conn, "SELECT particular, size, qty, unit, amount FROM noblepc_dr_item WHERE dr_id = ? ORDER BY item_no", 'i', [$id]);
    $dr['signature_url'] = $dr['status'] === 'approved' ? saSigUrl($dr['approval_signature']) : null;
    unset($dr['approval_signature']);
    saOut(['success' => true, 'dr' => $dr]);
}

// ================= APPROVE & SIGN =================
if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') saOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $me = saUserName($conn, $currentUserId);
    if ($me === '') saOut(['success' => false, 'message' => 'Your account name was not found. Please log in again.']);

    // Pirma: ang active signature mo sa Insert Signature
    $sigPath = saActiveSig($conn, $currentUserId);
    if (!$sigPath) saOut(['success' => false, 'message' => 'You have no active signature yet. Add one in Insert Signature first.']);

    $conn->begin_transaction();
    try {
        $cur = saOne($conn, "SELECT id, dr_no, COALESCE(status, 'pending') AS status FROM noblepc_dr WHERE id = ? FOR UPDATE", 'i', [$id]);
        if (!$cur) throw new RuntimeException('DR not found.');
        if ($cur['status'] === 'approved') throw new RuntimeException('This DR is already approved.');
        if ($cur['status'] === 'returned') throw new RuntimeException('This DR was returned to the PC. Wait for them to resubmit it.');

        $st = $conn->prepare("UPDATE noblepc_dr SET status = 'approved', approved_by = ?, approved_by_id = ?, approved_at = NOW(), approval_signature = ?
                              WHERE id = ? AND COALESCE(status, 'pending') = 'pending'");
        $st->bind_param('sisi', $me, $currentUserId, $sigPath, $id);
        $st->execute();
        $ok = $st->affected_rows === 1;
        $st->close();
        if (!$ok) throw new RuntimeException('This DR was changed by someone else. Please reload.');
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if (!$e instanceof RuntimeException) error_log('[superadmindrajax approve] ' . $e->getMessage());
        saOut(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not approve the DR. Please try again.']);
    }
    saOut(['success' => true, 'dr_no' => $cur['dr_no'], 'signature_url' => saSigUrl($sigPath)]);
}

// ================= RETURN TO PC (may remarks) =================
if ($action === 'return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') saOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if (mb_strlen($remarks) < 3) saOut(['success' => false, 'message' => 'Tell the PC what to fix (remarks are required).']);
    $me = saUserName($conn, $currentUserId);

    $st = $conn->prepare("UPDATE noblepc_dr SET status = 'returned', return_remarks = ?, returned_by = ?, returned_at = NOW()
                          WHERE id = ? AND COALESCE(status, 'pending') = 'pending'");
    $st->bind_param('ssi', $remarks, $me, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) {
        $cur = saOne($conn, "SELECT status FROM noblepc_dr WHERE id = ?", 'i', [$id]);
        saOut(['success' => false, 'message' => !$cur ? 'DR not found.' : ($cur['status'] === 'approved' ? 'This DR is already approved.' : 'This DR was already returned.')]);
    }
    saOut(['success' => true]);
}

saOut(['success' => false, 'message' => 'Unknown action.']);