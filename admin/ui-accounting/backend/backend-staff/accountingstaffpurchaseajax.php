<?php
// accountingstaffpurchaseajax.php  —  Accounting Staff: receive reviewed Purchase Requests (walang pirma)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_STAFF];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function aspOut(array $d): void
{
    echo json_encode($d);
    exit;
}

if (($_SESSION['position'] ?? '') !== POSITION_STAFF) {
    http_response_code(403);
    aspOut(['success' => false, 'message' => 'Only Accounting Staff can access this.']);
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Saan pupunta ang notification ng custodian / custodian assistant
const ASP_CUSTODIAN_LINK  = '/accountingcustodiandashboard';
const ASP_ASSISTANT_LINK  = '/accountingcustodianassistant';

// ================= LIST =================
// Lahat ng na-review na ng Accounting Head
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.date_needed, p.requested_by, p.prepared_by,
               p.total_amount, p.reviewed_by, p.reviewed_at, p.staff_received_by, p.staff_received_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count,
               (p.staff_received_at IS NOT NULL AND DATE(p.staff_received_at) = CURDATE()) AS done_today
        FROM noblepurchaserequest p
        WHERE p.status = 'Approved' AND p.reviewed_at IS NOT NULL
        ORDER BY (p.staff_received_at IS NOT NULL), p.staff_received_at DESC, p.reviewed_at DESC, p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['done_today'] = (int) $r['done_today'] === 1;
    }
    unset($r);
    aspOut(['success' => true, 'rows' => $rows]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM noblepurchaserequest WHERE id = ? AND status = 'Approved' AND reviewed_at IS NOT NULL LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pr) aspOut(['success' => false, 'message' => 'Purchase request not found or not yet reviewed by the Accounting Head.']);

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    aspOut(['success' => true, 'pr' => $pr]);
}

// ================= RECEIVE =================
// Walang pirma — itinatala lang kung sino at kailan, saka ipinapasa sa custodian
if ($action === 'receive') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') aspOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);

    $u = $conn->prepare("SELECT name FROM noblerole WHERE id = ? LIMIT 1");
    $u->bind_param('i', $currentUserId);
    $u->execute();
    $me = $u->get_result()->fetch_assoc();
    $u->close();
    if (!$me) aspOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);

    $st = $conn->prepare("
        UPDATE noblepurchaserequest
        SET staff_received_by = ?, staff_received_by_id = ?, staff_received_at = NOW()
        WHERE id = ? AND status = 'Approved' AND reviewed_at IS NOT NULL AND staff_received_at IS NULL
    ");
    $st->bind_param('sii', $me['name'], $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected < 1) aspOut(['success' => false, 'message' => 'This request was already received. Refreshing the list.']);

    // Abisuhan ang Custodian at Custodian Assistant
    try {
        $q = $conn->prepare("SELECT req_no, client FROM noblepurchaserequest WHERE id = ? LIMIT 1");
        $q->bind_param('i', $id);
        $q->execute();
        $pr = $q->get_result()->fetch_assoc();
        $q->close();

        $msg = "Purchase request {$pr['req_no']} — {$pr['client']} is ready for release.";
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        $roleAcc = ROLE_ACCOUNTING;
        foreach ([POSITION_CUSTODIAN => ASP_CUSTODIAN_LINK, POSITION_CUSTOASSISTANT => ASP_ASSISTANT_LINK] as $pos => $link) {
            $h = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
            $h->bind_param('ss', $roleAcc, $pos);
            $h->execute();
            $ids = array_column($h->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
            $h->close();
            foreach ($ids as $to) {
                $to = (int) $to;
                $n->bind_param('iissis', $to, $id, $pr['req_no'], $msg, $currentUserId, $link);
                $n->execute();
            }
        }
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang pag-receive */ }

    // Realtime: ibang Accounting Staff (listahan + badge), Custodian at Custodian Assistant (ire-release na),
    // PO at Superadmin (status ng PR) + Sales/Designer ng inquiry
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $qq = $conn->prepare("SELECT b.quotation_id FROM noblepurchaserequest p LEFT JOIN noblecrm_bom b ON b.id = p.bom_id WHERE p.id = ? LIMIT 1");
        $qq->bind_param('i', $id);
        $qq->execute();
        $qid = (int) ($qq->get_result()->fetch_assoc()['quotation_id'] ?? 0);
        $qq->close();
        $groups = [[ROLE_ACCOUNTING, POSITION_STAFF], [ROLE_ACCOUNTING, POSITION_CUSTODIAN], [ROLE_ACCOUNTING, POSITION_CUSTOASSISTANT],
                   [ROLE_PO, null], [ROLE_SUPERADMIN, null]];
        if ($qid > 0) {
            nhRtQuotation($conn, $qid, $groups, 'pr_staff_received');
        } else {
            foreach ($groups as $g) nhRealtimeNotifyGroup((string) $g[0], $g[1] !== null ? (string) $g[1] : null, 'changed', ['type' => 'pr_staff_received', 'pr_id' => $id]);
        }
    } catch (Throwable $e) {
        error_log('pr staff receive realtime: ' . $e->getMessage());
    }

    aspOut(['success' => true]);
}

aspOut(['success' => false, 'message' => 'Unknown action.']);