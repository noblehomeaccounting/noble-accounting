<?php
// superadminbillingajax.php  —  Superadmin: pirmahan o ibalik ang Progress Billing (Application for Payment)
// Daloy: PC → Accounting Head (Noted by) → Superadmin (Approved by). Pirma = active signature sa noblesignature.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

const STAGE = 'sa';                       // 'head' o 'sa'
const MY_STATUS = STAGE === 'head' ? 'pending_head' : 'pending_sa';
const NEXT_STATUS = STAGE === 'head' ? 'pending_sa' : 'approved';

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Realtime doorbell (Supabase Broadcast): sinasabihan ang PC (at iba pang bukas na page) na may nagbago.
// Hindi kailanman pinipigilan ang pirma — kapag pumalya, naka-log lang.
if (!function_exists('pcRealtime')) {
    function pcRealtime(mysqli $conn, string $type, array $groups, array $payload = []): void
    {
        try {
            include_once ROOT_PATH . '/network/realtime-helper.php';
            $payload = ['type' => $type, 'by' => intval($_SESSION['account_id'] ?? 0)] + $payload;
            foreach ($groups as [$role, $pos]) {
                nhRealtimeNotifyGroup((string) $role, $pos !== null ? (string) $pos : null, 'changed', $payload);
            }
            // Sales + Designer ng inquiry → makikita agad sa CRM List ang galaw ng project hanggang Turnover
            {
                $drId = (int) ($payload['dr_id'] ?? 0);
                foreach (['quote_id' => 'noblepc_laborquote', 'billing_id' => 'noblepc_billing', 'turnover_id' => 'noblepc_turnover'] as $key => $table) {
                    if ($drId > 0 || empty($payload[$key])) continue;
                    $st = $conn->prepare("SELECT dr_id FROM $table WHERE id = ? LIMIT 1");
                    $id = (int) $payload[$key];
                    $st->bind_param('i', $id);
                    $st->execute();
                    $drId = (int) ($st->get_result()->fetch_assoc()['dr_id'] ?? 0);
                    $st->close();
                }
                if ($drId > 0) {
                    $st = $conn->prepare("SELECT q.inquiry_id FROM noblepc_dr d
                                          JOIN nobletransmittal t ON t.id = d.transmittal_id
                                          JOIN noblepurchaserequest p ON p.id = t.pr_id
                                          JOIN noblecrm_bom b ON b.id = p.bom_id
                                          JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                                          WHERE d.id = ? LIMIT 1");
                    $st->bind_param('i', $drId);
                    $st->execute();
                    $inq = (int) ($st->get_result()->fetch_assoc()['inquiry_id'] ?? 0);
                    $st->close();
                    if ($inq > 0) nhRtInquiry($conn, $inq, [], 'crm_project');
                }
            }
        } catch (Throwable $e) {
            error_log('pcRealtime: ' . $e->getMessage());
        }
    }
}

function aOut(array $d): void
{
    echo json_encode($d);
    exit;
}
set_exception_handler(function (Throwable $e) {
    error_log('[superadminbillingajax.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});
function aAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function aOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return aAll($conn, $sql, $types, $args)[0] ?? null;
}
function aUrl(?string $p): ?string
{
    return $p ? rtrim(BASE_URL, '/') . '/' . ltrim($p, '/') : null;
}
function aUserName(mysqli $conn, int $uid): string
{
    return aOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function aActiveSig(mysqli $conn, int $uid): ?string
{
    return aOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1", 'i', [$uid])['path'] ?? null;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = aAll($conn, "
        SELECT b.id, b.dr_id, b.seq, b.app_no, b.ref_no, b.billing_type, b.this_period, b.contract_amount, b.change_orders, b.billing_date, b.status,
               b.prepared_by, b.submitted_at, b.head_by, b.head_at, b.sa_by, b.sa_at, b.return_remarks, b.returned_by, b.returned_stage, b.returned_at,
               d.dr_no, d.client_name AS owner, q.project_name, q.location, q.subcontractor,
               (SELECT COALESCE(SUM(x.this_period), 0) FROM noblepc_billing x WHERE x.dr_id = b.dr_id AND x.seq <= b.seq) AS to_date
        FROM noblepc_billing b
        JOIN noblepc_dr d ON d.id = b.dr_id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = b.dr_id
        ORDER BY FIELD(b.status, ?, 'returned', 'pending_head', 'pending_sa', 'approved'), COALESCE(b.submitted_at, b.created_at) ASC, b.id DESC", 's', [MY_STATUS]);
    foreach ($rows as &$r) {
        foreach (['id', 'dr_id', 'seq', 'app_no'] as $k) $r[$k] = (int) $r[$k];
        foreach (['this_period', 'contract_amount', 'change_orders', 'to_date'] as $k) $r[$k] = (float) $r[$k];
    }
    unset($r);
    aOut(['success' => true, 'rows' => $rows, 'me' => aUserName($conn, $currentUserId), 'my_signature' => aUrl(aActiveSig($conn, $currentUserId)), 'stage' => STAGE]);
}

// ================= GET: papel (lahat ng billing hanggang dito) =================
if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $b = aOne($conn, "SELECT dr_id, seq FROM noblepc_billing WHERE id = ?", 'i', [$id]);
    if (!$b) aOut(['success' => false, 'message' => 'Billing not found.']);
    $p = aOne($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name AS owner, t.project_name AS tr_project, q.quote_no, q.project_name, q.location, q.subcontractor, q.quote_date AS contract_date, q.total AS contract_amount
        FROM noblepc_dr d JOIN nobletransmittal t ON t.id = d.transmittal_id LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id WHERE d.id = ?", 'i', [(int) $b['dr_id']]);
    $apps = aAll($conn, "SELECT * FROM noblepc_billing WHERE dr_id = ? AND seq <= ? ORDER BY seq", 'ii', [(int) $b['dr_id'], (int) $b['seq']]);
    foreach ($apps as &$a) {
        foreach (['id', 'seq', 'app_no'] as $k) $a[$k] = (int) $a[$k];
        foreach (['contract_amount', 'change_orders', 'retainage_rate', 'this_period'] as $k) $a[$k] = (float) $a[$k];
        $a['prepared_signature_url'] = aUrl($a['prepared_signature']);
        $a['head_signature_url'] = $a['head_at'] ? aUrl($a['head_signature']) : null;
        $a['sa_signature_url'] = $a['status'] === 'approved' ? aUrl($a['sa_signature']) : null;
        unset($a['prepared_signature'], $a['head_signature'], $a['sa_signature']);
    }
    unset($a);
    aOut(['success' => true, 'project' => $p, 'apps' => $apps]);
}

// ================= SIGN =================
if ($action === 'approve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') aOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $me = aUserName($conn, $currentUserId);
    if ($me === '') aOut(['success' => false, 'message' => 'Your account name was not found. Please log in again.']);
    $sig = aActiveSig($conn, $currentUserId);
    if (!$sig) aOut(['success' => false, 'message' => 'You have no active signature yet. Add one in Insert Signature first.']);
    $sql = STAGE === 'head'
        ? "UPDATE noblepc_billing SET status = 'pending_sa', head_by = ?, head_by_id = ?, head_at = NOW(), head_signature = ? WHERE id = ? AND status = 'pending_head'"
        : "UPDATE noblepc_billing SET status = 'approved', sa_by = ?, sa_by_id = ?, sa_at = NOW(), sa_signature = ? WHERE id = ? AND status = 'pending_sa'";
    $st = $conn->prepare($sql);
    $st->bind_param('sisi', $me, $currentUserId, $sig, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) {
        $cur = aOne($conn, "SELECT status FROM noblepc_billing WHERE id = ?", 'i', [$id]);
        aOut(['success' => false, 'message' => !$cur ? 'Billing not found.' : 'This billing is not waiting for your signature anymore (status: ' . $cur['status'] . '). Please reload.']);
    }
    $r = aOne($conn, "SELECT ref_no FROM noblepc_billing WHERE id = ?", 'i', [$id]);
    pcRealtime($conn, STAGE === 'head' ? 'billing_noted' : 'billing_approved', [[ROLE_PC, null], [ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_SUPERADMIN, null]], ['billing_id' => $id]);
    aOut(['success' => true, 'ref_no' => $r['ref_no'] ?? '']);
}

// ================= RETURN TO PC =================
if ($action === 'return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') aOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if (mb_strlen($remarks) < 3) aOut(['success' => false, 'message' => 'Tell the PC what to fix (remarks are required).']);
    $me = aUserName($conn, $currentUserId);
    $stage = STAGE;
    $st = $conn->prepare("UPDATE noblepc_billing SET status = 'returned', return_remarks = ?, returned_by = ?, returned_stage = ?, returned_at = NOW(),
                                 head_by = NULL, head_by_id = NULL, head_at = NULL, head_signature = NULL WHERE id = ? AND status = ?");
    $my = MY_STATUS;
    $st->bind_param('sssis', $remarks, $me, $stage, $id, $my);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) aOut(['success' => false, 'message' => 'This billing is not waiting for your signature anymore. Please reload.']);
    pcRealtime($conn, 'billing_returned', [[ROLE_PC, null], [ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_SUPERADMIN, null]], ['billing_id' => $id]);
    aOut(['success' => true]);
}

aOut(['success' => false, 'message' => 'Unknown action.']);