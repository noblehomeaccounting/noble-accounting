<?php
// accountingpurchaseorderajax.php  —  Accounting Head: review & sign approved Purchase Requests

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function apoOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// Accounting Head lang
if (($_SESSION['position'] ?? '') !== POSITION_HEAD) {
    http_response_code(403);
    apoOut(['success' => false, 'message' => 'Only the Accounting Head can access this.']);
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Saan pupunta ang notification ng PO (route ng index-purchaserequest.php)
const APO_PO_LINK = '/purchaserequest';
// Saan pupunta ang notification ng Accounting Staff (route ng index-staff-purchase.php)
const APO_STAFF_LINK = '/staffpurchase';

function apoSigner(mysqli $conn, int $userId): array
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

// ================= LIST =================
// Lahat ng na-approve na ng CEO
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.date_needed, p.requested_by, p.prepared_by,
               p.total_amount, p.approved_by, p.approved_at, p.reviewed_by, p.reviewed_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count,
               (p.reviewed_at IS NOT NULL AND DATE(p.reviewed_at) = CURDATE())          AS reviewed_today
        FROM noblepurchaserequest p
        WHERE p.status = 'Approved'
        ORDER BY (p.reviewed_at IS NOT NULL), p.reviewed_at DESC, p.approved_at DESC, p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['reviewed_today'] = (int) $r['reviewed_today'] === 1;
        $r['is_reviewed'] = !empty($r['reviewed_at']);
    }
    unset($r);
    apoOut(['success' => true, 'rows' => $rows]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM noblepurchaserequest WHERE id = ? AND status = 'Approved' LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pr) apoOut(['success' => false, 'message' => 'Purchase request not found or not yet approved by the CEO.']);

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    apoOut(['success' => true, 'pr' => $pr]);
}

// ================= REVIEW & SIGN =================
// Inilalagay ang pangalan + pirma ng Accounting Head sa "Reviewed By"
if ($action === 'review') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') apoOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $me = apoSigner($conn, $currentUserId);
    if ($me['name'] === '') apoOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);
    $title = 'ACCOUNTING HEAD';

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET reviewed_by = ?, reviewed_by_title = ?, reviewed_by_signature_path = ?, reviewed_by_id = ?, reviewed_at = NOW()
        WHERE id = ? AND status = 'Approved' AND reviewed_at IS NULL
    ");
    $st->bind_param('sssii', $me['name'], $title, $me['signature_path'], $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) apoOut(['success' => false, 'message' => 'This request was already reviewed. Refreshing the list.']);

    // Abiso sa PO na gumawa ng request
    try {
        $q = $conn->prepare("SELECT created_by, req_no FROM noblepurchaserequest WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        if ($row && !empty($row['created_by'])) {
            $to = (int) $row['created_by'];
            $msg = "Purchase request {$row['req_no']} has been reviewed by Accounting.";
            $link = APO_PO_LINK;
            $n = $conn->prepare("
                INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
                VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
            ");
            $n->bind_param('iissis', $to, $id, $row['req_no'], $msg, $currentUserId, $link);
            $n->execute();
            $n->close();
        }
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang review */ }

    // Abisuhan ang Accounting Staff na may ire-receive
    try {
        $q = $conn->prepare("SELECT req_no, client FROM noblepurchaserequest WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $pr = $q->get_result()->fetch_assoc();
        $q->close();

        $roleAcc = ROLE_ACCOUNTING;
        $posStaff = POSITION_STAFF;
        $h = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
        $h->bind_param('ss', $roleAcc, $posStaff);
        $h->execute();
        $staff = array_column($h->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $h->close();

        $msg  = "Purchase request {$pr['req_no']} — {$pr['client']} was reviewed by the Accounting Head and is ready to receive.";
        $link = APO_STAFF_LINK;
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        foreach ($staff as $sid) {
            $sid = (int) $sid;
            $n->bind_param('iissis', $sid, $id, $pr['req_no'], $msg, $currentUserId, $link);
            $n->execute();
        }
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang review */ }

    // Realtime: ibang Accounting Head (badge + listahan), Accounting Staff (ire-receive), PO, Superadmin + Sales/Designer ng inquiry
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $qq = $conn->prepare("SELECT b.quotation_id FROM noblepurchaserequest p LEFT JOIN noblecrm_bom b ON b.id = p.bom_id WHERE p.id = ? LIMIT 1");
        $qq->bind_param('i', $id);
        $qq->execute();
        $qid = (int) ($qq->get_result()->fetch_assoc()['quotation_id'] ?? 0);
        $qq->close();
        $groups = [[ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_ACCOUNTING, POSITION_STAFF], [ROLE_PO, null], [ROLE_SUPERADMIN, null]];
        if ($qid > 0) {
            nhRtQuotation($conn, $qid, $groups, 'pr_reviewed');
        } else {
            foreach ($groups as $g) nhRealtimeNotifyGroup((string) $g[0], $g[1] !== null ? (string) $g[1] : null, 'changed', ['type' => 'pr_reviewed', 'pr_id' => $id]);
        }
    } catch (Throwable $e) {
        error_log('pr review realtime: ' . $e->getMessage());
    }

    apoOut(['success' => true, 'has_signature' => $me['signature_path'] !== null]);
}

apoOut(['success' => false, 'message' => 'Unknown action.']);