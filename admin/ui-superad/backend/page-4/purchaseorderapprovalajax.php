<?php
// purchaseorderapprovalajax.php  —  Superadmin (CEO): approve / return Purchase Requests

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Saan pupunta ang notification ng PO (route ng index-purchaserequest.php)
const PRA_PO_LINK = '/purchaserequest';
// Saan pupunta ang notification ng Accounting Head (route ng index-accounting-purchaseorder.php)
const PRA_ACCOUNTING_LINK = '/accountingpurchaseorder';

function praOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// Pangalan at aktibong pirma ng Superadmin
function praApprover(mysqli $conn, int $userId): array
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

// Abiso sa PO na gumawa ng request
function praNotify(mysqli $conn, int $prId, int $senderId, string $message): void
{
    $st = $conn->prepare("SELECT created_by, req_no FROM noblepurchaserequest WHERE id = ? LIMIT 1");
    $st->bind_param('i', $prId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row || empty($row['created_by'])) return;

    try {
        $to = (int) $row['created_by'];
        $link = PRA_PO_LINK;
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        $n->bind_param('iissis', $to, $prId, $row['req_no'], $message, $senderId, $link);
        $n->execute();
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang approval */ }
}

// Realtime: signal sa mga apektadong department + Sales/Designer ng inquiry (galing sa BOM ng PR)
function praRealtime(mysqli $conn, int $prId, array $groups, string $type): void
{
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $st = $conn->prepare("
            SELECT b.quotation_id FROM noblepurchaserequest p
            LEFT JOIN noblecrm_bom b ON b.id = p.bom_id
            WHERE p.id = ? LIMIT 1
        ");
        $st->bind_param('i', $prId);
        $st->execute();
        $qid = (int) ($st->get_result()->fetch_assoc()['quotation_id'] ?? 0);
        $st->close();
        if ($qid > 0) {
            nhRtQuotation($conn, $qid, $groups, $type);
        } else {
            foreach ($groups as $g) {
                nhRealtimeNotifyGroup((string) $g[0], isset($g[1]) ? (string) $g[1] : null, 'changed', ['type' => $type, 'pr_id' => $prId]);
            }
        }
    } catch (Throwable $e) {
        error_log('praRealtime: ' . $e->getMessage());
    }
}

// ================= LIST =================
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.request_date, p.date_needed,
               p.requested_by, p.prepared_by, p.total_amount, p.status,
               p.approved_by, p.approved_at, p.rejection_remarks, p.rejected_at, p.created_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count,
               (p.approved_at IS NOT NULL AND DATE(p.approved_at) = CURDATE())          AS approved_today
        FROM noblepurchaserequest p
        ORDER BY (p.status <> 'Pending'), p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['approved_today'] = (int) $r['approved_today'] === 1;
    }
    unset($r);
    praOut(['success' => true, 'rows' => $rows]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM noblepurchaserequest WHERE id = ? LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pr) praOut(['success' => false, 'message' => 'Purchase request not found.']);

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    praOut(['success' => true, 'pr' => $pr]);
}

// ================= APPROVE =================
// Inilalagay ang pangalan + pirma ng Superadmin sa "Approved By" (CEO)
if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') praOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $ap = praApprover($conn, $currentUserId);
    if ($ap['name'] === '') praOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);
    $title = 'CEO';

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET status = 'Approved', approved_by = ?, approved_by_title = ?, approved_by_signature_path = ?,
            approved_by_id = ?, approved_at = NOW()
        WHERE id = ? AND status = 'Pending'
    ");
    $st->bind_param('sssii', $ap['name'], $title, $ap['signature_path'], $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) praOut(['success' => false, 'message' => 'This request was already approved or returned. Refreshing the list.']);

    praNotify($conn, $id, $currentUserId, 'Your purchase request has been approved by the CEO.');

    // Abisuhan ang Accounting Head na may susunod na ire-review
    try {
        $q = $conn->prepare("SELECT req_no, client FROM noblepurchaserequest WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $pr = $q->get_result()->fetch_assoc();
        $q->close();

        $roleAcc = ROLE_ACCOUNTING;
        $posHead = POSITION_HEAD;
        $h = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
        $h->bind_param('ss', $roleAcc, $posHead);
        $h->execute();
        $heads = array_column($h->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $h->close();

        $msg  = "Purchase request {$pr['req_no']} — {$pr['client']} was approved by the CEO and is ready for your review.";
        $link = PRA_ACCOUNTING_LINK;
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        foreach ($heads as $hid) {
            $hid = (int) $hid;
            $n->bind_param('iissis', $hid, $id, $pr['req_no'], $msg, $currentUserId, $link);
            $n->execute();
        }
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang approval */ }

    // Superadmin (ibang CEO/badge), PO (listahan, Client Series), Accounting Head (susunod na ire-review)
    praRealtime($conn, $id, [[ROLE_SUPERADMIN, null], [ROLE_PO, null], [ROLE_ACCOUNTING, POSITION_HEAD]], 'pr_approved');
    praOut(['success' => true, 'has_signature' => $ap['signature_path'] !== null]);
}

// ================= RETURN (REJECT) =================
if ($action === 'reject') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') praOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    if ($remarks === '') praOut(['success' => false, 'message' => 'A reason is required.']);

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET status = 'Rejected', rejection_remarks = ?, rejected_by_id = ?, rejected_at = NOW()
        WHERE id = ? AND status = 'Pending'
    ");
    $st->bind_param('sii', $remarks, $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) praOut(['success' => false, 'message' => 'This request was already approved or returned. Refreshing the list.']);

    praNotify($conn, $id, $currentUserId, 'Your purchase request was returned: ' . $remarks);
    praRealtime($conn, $id, [[ROLE_SUPERADMIN, null], [ROLE_PO, null]], 'pr_returned');
    praOut(['success' => true]);
}

praOut(['success' => false, 'message' => 'Unknown action.']);