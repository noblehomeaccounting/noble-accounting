<?php
// purchaseorderapprovalajax.php  —  Purchasing (PO) Head: approve / return Purchase Requests
// (Superadmin = tingin lang)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN, ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Approval: Purchasing Head na · Superadmin = review / monitor lang
include_once ROOT_PATH . '/network/head-approval.php';
$nhApprover = nhApproverViewer($conn, ROLE_PO);
nhApproverGuard($nhApprover, (string) ($_GET['action'] ?? $_POST['action'] ?? ''), ['approve', 'reject'], 'Purchasing Head');

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

// Pangalan at aktibong pirma ng nag-a-approve (Purchasing Head)
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

// Project ID ng bawat PR: PR → BOM (bom_id at/o noblepurchaserequest_bom) → quotation → inquiry.control_no
function praHasPrLink(mysqli $conn): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $t = $conn->query("SHOW TABLES LIKE 'noblepurchaserequest_bom'");
            $has = $t && $t->num_rows > 0;
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

function praIdSql(mysqli $conn): string
{
    $bomMatch = praHasPrLink($conn)
        ? "(b.id = p.bom_id OR b.id IN (SELECT pb.bom_id FROM noblepurchaserequest_bom pb WHERE pb.pr_id = p.id))"
        : "b.id = p.bom_id";
    return "
        (SELECT GROUP_CONCAT(DISTINCT iq.control_no ORDER BY iq.control_no SEPARATOR ', ')
           FROM noblecrm_bom b
           JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
           JOIN noblecrminquiry iq ON iq.id = q.inquiry_id
          WHERE $bomMatch)                                                         AS control_no,
        (SELECT GROUP_CONCAT(DISTINCT b.bom_number ORDER BY b.bom_number SEPARATOR ', ')
           FROM noblecrm_bom b WHERE $bomMatch AND b.bom_number IS NOT NULL)        AS bom_numbers";
}

// ================= LIST =================
if ($action === 'list') {
    $sqlBase = "
        SELECT p.id, p.req_no, p.client, p.project_scope, p.request_date, p.date_needed,
               p.requested_by, p.prepared_by, p.total_amount, p.status,
               p.approved_by, p.approved_at, p.rejection_remarks, p.rejected_at, p.created_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count,
               (p.approved_at IS NOT NULL AND DATE(p.approved_at) = CURDATE())          AS approved_today%s
        FROM noblepurchaserequest p
        ORDER BY (p.status <> 'Pending'), p.id DESC";
    try {
        $rows = $conn->query(sprintf($sqlBase, ',' . praIdSql($conn)))->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        // kung iba ang tables sa server — listahan pa rin, walang Project ID
        error_log('pra list ids: ' . $e->getMessage());
        $rows = $conn->query(sprintf($sqlBase, ", NULL AS control_no, NULL AS bom_numbers"))->fetch_all(MYSQLI_ASSOC);
    }

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

    // Project ID (Control No. + BOM No.) para sa header ng modal
    $pr['control_no'] = null;
    $pr['bom_numbers'] = null;
    try {
        $ids = $conn->prepare("SELECT " . praIdSql($conn) . " FROM noblepurchaserequest p WHERE p.id = ? LIMIT 1");
        $ids->bind_param('i', $id);
        $ids->execute();
        $x = $ids->get_result()->fetch_assoc() ?: [];
        $ids->close();
        $pr['control_no'] = $x['control_no'] ?? null;
        $pr['bom_numbers'] = $x['bom_numbers'] ?? null;
    } catch (Throwable $e) { error_log('pra view ids: ' . $e->getMessage()); }

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    praOut(['success' => true, 'pr' => $pr]);
}

// ================= APPROVE =================
// Inilalagay ang pangalan + pirma ng Purchasing Head sa "Approved By"
if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') praOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $ap = praApprover($conn, $currentUserId);
    if ($ap['name'] === '') praOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);
    $title = 'Purchasing Head';

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

    praNotify($conn, $id, $currentUserId, 'Your purchase request has been approved by the Purchasing Head.');

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

        $msg  = "Purchase request {$pr['req_no']} — {$pr['client']} was approved by the Purchasing Head and is ready for your review.";
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

    // Superadmin (monitor), PO (Head + staff: listahan, Client Series), Accounting Head (susunod na ire-review)
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