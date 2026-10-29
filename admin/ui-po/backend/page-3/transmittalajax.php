<?php
// transmittalajax.php  —  Transmittal Form (PO): pwede lang kapag PAID na ang Purchase Request

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

include_once ROOT_PATH . '/network/po-bom-group.php';   // isang PR = lahat ng BOM ng order

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Gitnang bahagi ng Ref No (permanente): <taon>06-<sunod-sunod>, hal. 202606-01
const TR_REF_MIDDLE = '06';

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

function trOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// Pangalan + aktibong pirma ng naka-login (Issued by)
function trSigner(mysqli $conn, int $userId): array
{
    $out = ['name' => '', 'signature_path' => null];
    if ($userId <= 0) return $out;
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

// Paid na Purchase Request na wala pang transmittal
function trPaidPr(mysqli $conn, int $prId): ?array
{
    $st = $conn->prepare("
        SELECT p.id, p.req_no, p.bom_id, p.project_scope, p.paid_at,
               COALESCE(NULLIF(b.client_name, ''), p.client) AS client,   -- client ng BOM (kapareho ng PR)
               (SELECT t.id FROM nobletransmittal t WHERE t.pr_id = p.id LIMIT 1) AS transmittal_id
        FROM noblepurchaserequest p
        LEFT JOIN noblecrm_bom b ON b.id = p.bom_id
        WHERE p.id = ? AND p.status = 'Approved' AND p.paid_at IS NOT NULL
        LIMIT 1
    ");
    $st->bind_param('i', $prId);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    return $pr ?: null;
}

// ================= LIST =================
// ready = paid na PR na wala pang transmittal | done = mga nagawa nang transmittal
if ($action === 'list') {
    $ready = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.total_amount, p.paid_at,
               b.bom_number,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count
        FROM noblepurchaserequest p
        LEFT JOIN noblecrm_bom b ON b.id = p.bom_id
        LEFT JOIN nobletransmittal t ON t.pr_id = p.id
        WHERE p.status = 'Approved' AND p.paid_at IS NOT NULL AND t.id IS NULL
        ORDER BY p.paid_at DESC, p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $done = $conn->query("
        SELECT t.id, t.ref_no, t.transmittal_date, t.project_name, t.client_name, t.subject,
               t.issued_by, t.received_by, t.created_at, p.req_no,
               (SELECT COUNT(*) FROM nobletransmittal_items i WHERE i.transmittal_id = t.id) AS item_count
        FROM nobletransmittal t
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        ORDER BY t.id DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($ready as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['bom_number'] = poPrBomNumbers($conn, $r['id']) ?: $r['bom_number'];   // hal. "BOM-0003, BOM-0004"
    }
    unset($r);
    foreach ($done as &$r) { $r['id'] = (int) $r['id']; $r['item_count'] = (int) $r['item_count']; }
    unset($r);

    trOut(['success' => true, 'ready' => $ready, 'done' => $done]);
}

// ================= PREFILL =================
// Laman ng bagong form galing sa paid na Purchase Request
if ($action === 'prefill') {
    $prId = intval($_GET['pr_id'] ?? 0);
    $pr = trPaidPr($conn, $prId);
    if (!$pr) trOut(['success' => false, 'message' => 'A transmittal can only be made once the purchase request is paid.']);
    if (!empty($pr['transmittal_id'])) trOut(['success' => false, 'message' => 'This purchase request already has a transmittal.', 'transmittal_id' => (int) $pr['transmittal_id']]);

    $it = $conn->prepare("SELECT item_name, quantity, unit, remarks FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $prId);
    $it->execute();
    $items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    $qty = function ($q, $u) {
        $n = rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.');
        return trim($n . ' ' . strtoupper((string) $u));
    };

    trOut([
        'success' => true,
        'pr'      => ['id' => (int) $pr['id'], 'req_no' => $pr['req_no'], 'client' => $pr['client'], 'project_scope' => $pr['project_scope']],
        'ref_preview' => date('Y') . TR_REF_MIDDLE . '-xx',
        'items'   => array_map(fn($r) => [
            'description' => $r['item_name'],
            'quantity'    => $qty($r['quantity'], $r['unit']),
            'remarks'     => $r['remarks'] ?? '',
        ], $items),
    ]);
}

// ================= CREATE =================
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') trOut(['success' => false, 'message' => 'Invalid request.']);

    $prId = intval($_POST['pr_id'] ?? 0);
    $pr = trPaidPr($conn, $prId);
    if (!$pr) trOut(['success' => false, 'message' => 'A transmittal can only be made once the purchase request is paid.']);
    if (!empty($pr['transmittal_id'])) trOut(['success' => false, 'message' => 'This purchase request already has a transmittal.']);

    $date      = trim($_POST['transmittal_date'] ?? '');
    $project   = trim($_POST['project_name'] ?? '');
    $client    = trim((string) $pr['client']);   // galing sa BOM / Purchase Request, hindi na tina-type
    $company   = trim($_POST['client_company'] ?? '');
    $address   = trim($_POST['client_address'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $received  = trim($_POST['received_by'] ?? '');
    // para sa Summary of Transmittal (hindi kasama sa print)
    $particulars = mb_substr(trim($_POST['particulars'] ?? ''), 0, 255);
    $mode        = mb_substr(trim($_POST['delivery_mode'] ?? ''), 0, 255);
    $sumRemarks  = mb_substr(trim($_POST['summary_remarks'] ?? ''), 0, 1000);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) trOut(['success' => false, 'message' => 'Date is invalid.']);
    if ($project === '') trOut(['success' => false, 'message' => 'Project name is required.']);
    if ($client === '') trOut(['success' => false, 'message' => 'The purchase request has no client name.']);
    if ($particulars === '') trOut(['success' => false, 'message' => 'Particulars is required for the Summary of Transmittal.']);

    $items = [];
    $descs = $_POST['description'] ?? [];
    foreach ($descs as $i => $d) {
        $d = trim($d);
        if ($d === '') continue;
        $items[] = [
            'serial'   => trim($_POST['serial_no'][$i] ?? ''),
            'desc'     => $d,
            'qty'      => trim($_POST['quantity'][$i] ?? ''),
            'remarks'  => trim($_POST['remarks'][$i] ?? ''),
        ];
    }
    if (!$items) trOut(['success' => false, 'message' => 'Add at least one item.']);

    $me = trSigner($conn, $currentUserId);
    if ($me['name'] === '') trOut(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);

    $conn->begin_transaction();
    try {
        // Ref No: <taon>06-<sunod-sunod, 2 digit pataas>, nagre-reset kada taon
        $prefix = date('Y') . TR_REF_MIDDLE . '-';
        $startPos = strlen($prefix) + 1;
        $sq = $conn->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(ref_no, ?) AS UNSIGNED)), 0) AS last_no
            FROM nobletransmittal WHERE ref_no LIKE CONCAT(?, '%') FOR UPDATE
        ");
        $sq->bind_param('is', $startPos, $prefix);
        $sq->execute();
        $last = (int) ($sq->get_result()->fetch_assoc()['last_no'] ?? 0);
        $sq->close();
        $refNo = $prefix . str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);

        $bomId = $pr['bom_id'] !== null ? (int) $pr['bom_id'] : null;
        $st = $conn->prepare("
            INSERT INTO nobletransmittal
                (ref_no, pr_id, bom_id, transmittal_date, project_name, client_name, client_company, client_address, subject, notes,
                 issued_by, issued_by_id, issued_by_signature_path, received_by,
                 particulars, delivery_mode, summary_remarks, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $st->bind_param('siissssssssisssssi', $refNo, $prId, $bomId, $date, $project, $client, $company, $address, $subject, $notes,
            $me['name'], $currentUserId, $me['signature_path'], $received, $particulars, $mode, $sumRemarks, $currentUserId);
        $st->execute();
        $trId = $st->insert_id;
        $st->close();

        $it = $conn->prepare("
            INSERT INTO nobletransmittal_items (transmittal_id, item_no, serial_no, description, quantity, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $k => $row) {
            $no = $k + 1;
            $it->bind_param('iissss', $trId, $no, $row['serial'], $row['desc'], $row['qty'], $row['remarks']);
            $it->execute();
        }
        $it->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        trOut(['success' => false, 'message' => 'Could not save the transmittal. Please try again.']);
    }

    trRealtime($conn, $prId, 'transmittal_created', [[ROLE_PO, null], [ROLE_PC, null], [ROLE_SUPERADMIN, null]]);
    trOut(['success' => true, 'id' => $trId, 'ref_no' => $refNo, 'has_signature' => $me['signature_path'] !== null]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("
        SELECT t.*, p.req_no FROM nobletransmittal t
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        WHERE t.id = ? LIMIT 1
    ");
    $st->bind_param('i', $id);
    $st->execute();
    $tr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$tr) trOut(['success' => false, 'message' => 'Transmittal not found.']);

    $it = $conn->prepare("SELECT serial_no, description, quantity, remarks FROM nobletransmittal_items WHERE transmittal_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $tr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    trOut(['success' => true, 'tr' => $tr]);
}

trOut(['success' => false, 'message' => 'Unknown action.']);