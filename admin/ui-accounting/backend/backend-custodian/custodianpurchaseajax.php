<?php
// custodianpurchaseajax.php  —  Custodian / Custodian Assistant: release (pirma), paid

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function cpOut(array $d): void
{
    echo json_encode($d);
    exit;
}

$myPosition = $_SESSION['position'] ?? '';
if (!in_array($myPosition, [POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT], true)) {
    http_response_code(403);
    cpOut(['success' => false, 'message' => 'Only the Custodian or Custodian Assistant can access this.']);
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Saan pupunta ang notification ng PO (route ng index-purchaserequest.php)
const CP_PO_LINK = '/purchaserequest';

function cpSigner(mysqli $conn, int $userId): array
{
    $out = ['name' => '', 'signature_path' => null];
    $st = $conn->prepare("SELECT name, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $userId);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$u) return $out;
    $out['name'] = $u['name'] ?? '';

    $path = null;
    if (!empty($u['active_signature_id'])) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1");
        $s->bind_param('ii', $u['active_signature_id'], $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $path = $r['path'];
    }
    if ($path === null) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $s->bind_param('i', $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $path = $r['path'];
    }
    $out['signature_path'] = $path;
    return $out;
}

function cpNotifyPo(mysqli $conn, int $id, int $sender, string $message): void
{
    try {
        $q = $conn->prepare("SELECT created_by, req_no FROM noblepurchaserequest WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        if (!$row || empty($row['created_by'])) return;
        $to = (int) $row['created_by'];
        $link = CP_PO_LINK;
        $msg = str_replace('{req}', $row['req_no'], $message);
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        $n->bind_param('iissis', $to, $id, $row['req_no'], $msg, $sender, $link);
        $n->execute();
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang proseso */ }
}

// ================= LIST =================
// Lahat ng na-receive na ng Accounting Staff
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.date_needed, p.requested_by, p.prepared_by,
               p.total_amount, p.staff_received_by, p.staff_received_at,
               p.released_by, p.released_at, p.paid_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count,
               (p.released_at IS NOT NULL AND DATE(p.released_at) = CURDATE())          AS done_today
        FROM noblepurchaserequest p
        WHERE p.status = 'Approved' AND p.staff_received_at IS NOT NULL
        ORDER BY (p.released_at IS NOT NULL), (p.paid_at IS NOT NULL), p.staff_received_at DESC, p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['done_today'] = (int) $r['done_today'] === 1;
    }
    unset($r);
    cpOut(['success' => true, 'rows' => $rows]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM noblepurchaserequest WHERE id = ? AND status = 'Approved' AND staff_received_at IS NOT NULL LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pr) cpOut(['success' => false, 'message' => 'Purchase request not found or not yet received by Accounting Staff.']);

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    cpOut(['success' => true, 'pr' => $pr]);
}

// ================= RELEASE & SIGN =================
// Alinman sa Custodian o Custodian Assistant — kung sino ang unang pumirma
if ($action === 'release') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') cpOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $me = cpSigner($conn, $currentUserId);
    if ($me['name'] === '') cpOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);
    $title = $myPosition === POSITION_CUSTOASSISTANT ? 'CUSTODIAN ASSISTANT' : 'CUSTODIAN';

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET released_by = ?, released_by_title = ?, released_by_signature_path = ?, released_by_id = ?, released_at = NOW()
        WHERE id = ? AND status = 'Approved' AND staff_received_at IS NOT NULL AND released_at IS NULL
    ");
    $st->bind_param('sssii', $me['name'], $title, $me['signature_path'], $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) cpOut(['success' => false, 'message' => 'This request was already released by the other custodian. Refreshing the list.']);

    cpNotifyPo($conn, $id, $currentUserId, 'Purchase request {req} has been released. Waiting to be paid.');
    cpOut(['success' => true, 'has_signature' => $me['signature_path'] !== null]);
}

// ================= MARK AS PAID =================
// Pagkatapos ma-print at malagyan ng Paid Stamp ang papel
if ($action === 'paid') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') cpOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET paid_by_id = ?, paid_at = NOW()
        WHERE id = ? AND released_at IS NOT NULL AND paid_at IS NULL
    ");
    $st->bind_param('ii', $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) cpOut(['success' => false, 'message' => 'This request is already marked as paid or not yet released.']);

    cpNotifyPo($conn, $id, $currentUserId, 'Purchase request {req} has been paid.');
    cpOut(['success' => true]);
}

cpOut(['success' => false, 'message' => 'Unknown action.']);