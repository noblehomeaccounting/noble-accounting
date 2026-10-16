<?php
// clientseriesajax.php  —  Client Series Number (monitoring ng lahat ng Purchase Request + manual na delivery info)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function csOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// Mga column na mano-manong pinupunan (hindi pa konektado sa system)
const CS_FIELDS = [
    'receive_office_date' => 'date',
    'deliver_client_date' => 'date',
    'delivery_mode'       => 'text',
    'delivered_by'        => 'text',
    'remarks'             => 'text',
    'is_done'             => 'bool',
];

// ================= LIST =================
// Galing sa system: P.O date, approve date, payment date, req no, client, amount, transmittal no.
// Manual: lahat ng nasa nobleclientseries
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.total_amount, p.status,
               p.request_date, p.created_at, p.approved_at, p.rejected_at, p.paid_at,
               t.id AS tr_id, t.ref_no AS tr_ref_no,
               c.receive_office_date, c.deliver_client_date,
               c.delivery_mode, c.delivered_by, c.remarks, COALESCE(c.is_done, 0) AS is_done
        FROM noblepurchaserequest p
        LEFT JOIN nobletransmittal t   ON t.pr_id = p.id
        LEFT JOIN nobleclientseries c  ON c.pr_id = p.id
        ORDER BY COALESCE(p.request_date, DATE(p.created_at)) DESC, p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id']       = (int) $r['id'];
        $r['tr_id']    = $r['tr_id'] !== null ? (int) $r['tr_id'] : null;
        $r['is_done']  = (int) $r['is_done'] === 1;
        $r['total_amount'] = (float) $r['total_amount'];
    }
    unset($r);

    // mga dating ginamit na Mode of Delivery / Person para sa suggestions
    $modes = array_column($conn->query("SELECT DISTINCT delivery_mode FROM nobleclientseries WHERE delivery_mode IS NOT NULL AND delivery_mode <> '' ORDER BY delivery_mode")->fetch_all(MYSQLI_ASSOC), 'delivery_mode');
    $people = array_column($conn->query("SELECT DISTINCT delivered_by FROM nobleclientseries WHERE delivered_by IS NOT NULL AND delivered_by <> '' ORDER BY delivered_by")->fetch_all(MYSQLI_ASSOC), 'delivered_by');

    csOut(['success' => true, 'rows' => $rows, 'modes' => $modes, 'people' => $people]);
}

// ================= SAVE (isang cell) =================
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') csOut(['success' => false, 'message' => 'Invalid request.']);

    $prId  = intval($_POST['pr_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = trim((string) ($_POST['value'] ?? ''));

    if (!array_key_exists($field, CS_FIELDS)) csOut(['success' => false, 'message' => 'Invalid field.']);

    $chk = $conn->prepare("SELECT id FROM noblepurchaserequest WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $prId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) csOut(['success' => false, 'message' => 'Purchase request not found.']);

    $type = CS_FIELDS[$field];
    if ($type === 'date') {
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) csOut(['success' => false, 'message' => 'Invalid date.']);
        $value = $value === '' ? null : $value;
    } elseif ($type === 'bool') {
        $value = in_array($value, ['1', 'true', 'on'], true) ? 1 : 0;
    } else {
        $value = $value === '' ? null : mb_substr($value, 0, $field === 'remarks' ? 1000 : 255);
    }

    // $field ay galing lang sa CS_FIELDS (whitelist), kaya ligtas ilagay sa query
    $sql = "INSERT INTO nobleclientseries (pr_id, `$field`, updated_by, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE `$field` = VALUES(`$field`), updated_by = VALUES(updated_by), updated_at = NOW()";
    $st = $conn->prepare($sql);
    if ($type === 'bool') $st->bind_param('iii', $prId, $value, $currentUserId);
    else $st->bind_param('isi', $prId, $value, $currentUserId);
    $ok = $st->execute();
    $st->close();

    csOut(['success' => (bool) $ok, 'message' => $ok ? 'Saved.' : 'Could not save.']);
}

csOut(['success' => false, 'message' => 'Unknown action.']);