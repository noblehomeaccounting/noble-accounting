<?php
// transmittalsummaryajax.php  —  Summary of Transmittal: listahan + Mark as Delivered

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Realtime doorbell (Supabase Broadcast): PO, Project Coordinator, Superadmin + Sales/Designer ng inquiry.
// Hindi kailanman pinipigilan ang save — kapag pumalya, naka-log lang.
if (!function_exists('trRealtime')) {
    function trRealtime(mysqli $conn, int $prId, string $type, array $groups): void
    {
        try {
            include_once ROOT_PATH . '/network/realtime-helper.php';
            $st = $conn->prepare("SELECT b.quotation_id FROM noblepurchaserequest p LEFT JOIN noblecrm_bom b ON b.id = p.bom_id WHERE p.id = ? LIMIT 1");
            $st->bind_param('i', $prId);
            $st->execute();
            $qid = (int) ($st->get_result()->fetch_assoc()['quotation_id'] ?? 0);
            $st->close();
            if ($qid > 0) {
                nhRtQuotation($conn, $qid, $groups, $type);
            } else {
                foreach ($groups as [$role, $pos]) {
                    nhRealtimeNotifyGroup((string) $role, $pos !== null ? (string) $pos : null, 'changed', ['type' => $type, 'pr_id' => $prId]);
                }
            }
        } catch (Throwable $e) {
            error_log('trRealtime: ' . $e->getMessage());
        }
    }
}

function tsOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = $conn->query("
        SELECT t.id, t.ref_no, t.transmittal_date, t.client_name, t.client_company, t.project_name,
               t.particulars, t.delivery_mode, t.summary_remarks, t.delivered_at, t.delivered_marked_at,
               t.pr_id, p.req_no,
               (SELECT name FROM noblerole r WHERE r.id = t.delivered_by_id LIMIT 1) AS delivered_marked_by
        FROM nobletransmittal t
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        ORDER BY (t.delivered_at IS NOT NULL), t.transmittal_date DESC, t.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) { $r['id'] = (int) $r['id']; }
    unset($r);

    $modes = array_column($conn->query("SELECT DISTINCT delivery_mode FROM nobletransmittal WHERE delivery_mode IS NOT NULL AND delivery_mode <> '' ORDER BY delivery_mode")->fetch_all(MYSQLI_ASSOC), 'delivery_mode');

    tsOut(['success' => true, 'rows' => $rows, 'modes' => $modes]);
}

// ================= MARK AS DELIVERED =================
// Isang beses lang; kailangan ng petsa at kumpirmasyon
if ($action === 'deliver') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tsOut(['success' => false, 'message' => 'Invalid request.']);

    $id      = intval($_POST['id'] ?? 0);
    $date    = trim($_POST['delivered_at'] ?? '');
    $mode    = mb_substr(trim($_POST['delivery_mode'] ?? ''), 0, 255);
    $remarks = mb_substr(trim($_POST['summary_remarks'] ?? ''), 0, 1000);
    $confirm = ($_POST['confirm'] ?? '') === '1';

    if (!$confirm) tsOut(['success' => false, 'message' => 'Please confirm that the items were delivered.']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) tsOut(['success' => false, 'message' => 'Date delivered is required.']);
    if ($mode === '') tsOut(['success' => false, 'message' => 'Mode of delivery is required.']);

    $q = $conn->prepare("SELECT id, pr_id, transmittal_date, delivered_at FROM nobletransmittal WHERE id = ? LIMIT 1");
    $q->bind_param('i', $id);
    $q->execute();
    $tr = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$tr) tsOut(['success' => false, 'message' => 'Transmittal not found.']);
    if (!empty($tr['delivered_at'])) tsOut(['success' => false, 'message' => 'This transmittal is already marked as delivered.']);
    if ($date < $tr['transmittal_date']) tsOut(['success' => false, 'message' => 'Date delivered cannot be earlier than the transmittal date.']);
    if ($date > date('Y-m-d')) tsOut(['success' => false, 'message' => 'Date delivered cannot be in the future.']);

    $st = $conn->prepare("
        UPDATE nobletransmittal
        SET delivered_at = ?, delivery_mode = ?, summary_remarks = ?, delivered_by_id = ?, delivered_marked_at = NOW()
        WHERE id = ? AND delivered_at IS NULL
    ");
    $st->bind_param('sssii', $date, $mode, $remarks, $currentUserId, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();
    if ($affected < 1) tsOut(['success' => false, 'message' => 'This transmittal is already marked as delivered.']);

    // Kusang punan ang Client Series (Date Deliver to Client / Mode) kung wala pang laman doon
    try {
        $prId = (int) $tr['pr_id'];
        $cs = $conn->prepare("
            INSERT INTO nobleclientseries (pr_id, deliver_client_date, delivery_mode, updated_by, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                deliver_client_date = COALESCE(deliver_client_date, VALUES(deliver_client_date)),
                delivery_mode       = COALESCE(NULLIF(delivery_mode, ''), VALUES(delivery_mode)),
                updated_by = VALUES(updated_by), updated_at = NOW()
        ");
        $cs->bind_param('issi', $prId, $date, $mode, $currentUserId);
        $cs->execute();
        $cs->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ang pag-deliver */ }

    // PC: lalabas agad sa "Ready for DR" · Sales: "Delivered" sa Track
    trRealtime($conn, (int) $tr['pr_id'], 'transmittal_delivered', [[ROLE_PO, null], [ROLE_PC, null], [ROLE_SUPERADMIN, null]]);
    tsOut(['success' => true]);
}

// ================= EDIT (habang hindi pa delivered) =================
if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tsOut(['success' => false, 'message' => 'Invalid request.']);
    $id          = intval($_POST['id'] ?? 0);
    $particulars = mb_substr(trim($_POST['particulars'] ?? ''), 0, 255);
    $mode        = mb_substr(trim($_POST['delivery_mode'] ?? ''), 0, 255);
    $remarks     = mb_substr(trim($_POST['summary_remarks'] ?? ''), 0, 1000);
    if ($particulars === '') tsOut(['success' => false, 'message' => 'Particulars is required.']);

    $st = $conn->prepare("UPDATE nobletransmittal SET particulars = ?, delivery_mode = ?, summary_remarks = ? WHERE id = ? AND delivered_at IS NULL");
    $st->bind_param('sssi', $particulars, $mode, $remarks, $id);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    // 0 affected = walang binago o delivered na — suriin kung alin
    if ($affected < 1) {
        $c = $conn->prepare("SELECT delivered_at FROM nobletransmittal WHERE id = ? LIMIT 1");
        $c->bind_param('i', $id);
        $c->execute();
        $row = $c->get_result()->fetch_assoc();
        $c->close();
        if (!$row) tsOut(['success' => false, 'message' => 'Transmittal not found.']);
        if (!empty($row['delivered_at'])) tsOut(['success' => false, 'message' => 'Delivered transmittals can no longer be edited.']);
    }
    if ($affected > 0) {
        foreach ([[ROLE_PO, null], [ROLE_PC, null]] as [$role, $pos]) {
            try {
                include_once ROOT_PATH . '/network/realtime-helper.php';
                nhRealtimeNotifyGroup((string) $role, null, 'changed', ['type' => 'transmittal_updated', 'id' => $id]);
            } catch (Throwable $e) { error_log('transmittal_updated: ' . $e->getMessage()); }
        }
    }
    tsOut(['success' => true]);
}

tsOut(['success' => false, 'message' => 'Unknown action.']);