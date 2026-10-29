<?php
// coordinatorbillingajax.php  —  Project Coordinator: Progress Billing / Application for Payment
// Bawat billing ay isang papel: PC → Accounting Head → Superadmin. Hindi makakagawa ng bago hangga't hindi approved ang nauna.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Realtime doorbell (Supabase Broadcast): sinasabihan ang ibang bukas na page na may nagbago.
// Hindi kailanman pinipigilan ang save — kapag pumalya, naka-log lang.
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

function bOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[coordinatorbillingajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function bAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function bOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return bAll($conn, $sql, $types, $args)[0] ?? null;
}
function bDate($d): ?string
{
    $d = trim((string) $d);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
    return $d;
}
function bMoney($v): float
{
    return round((float) str_replace(',', '', (string) $v), 2);
}
function bUrl(?string $p): ?string
{
    return $p ? rtrim(BASE_URL, '/') . '/' . ltrim($p, '/') : null;
}
function bUserName(mysqli $conn, int $uid): string
{
    return bOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function bActiveSig(mysqli $conn, int $uid): ?string
{
    return bOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1", 'i', [$uid])['path'] ?? null;
}
// Proyekto: dapat approved ang labor quotation
function bProject(mysqli $conn, int $drId): array
{
    $p = bOne($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name AS owner, t.ref_no, t.project_name AS tr_project,
               q.id AS quote_id, q.quote_no, q.status AS quote_status, q.project_name, q.location, q.subcontractor,
               q.quote_date AS contract_date, q.total AS contract_amount, q.downpayment_pct, q.duration_days
        FROM noblepc_dr d
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id
        WHERE d.id = ? LIMIT 1", 'i', [$drId]);
    if (!$p) bOut(['success' => false, 'message' => 'Project not found.']);
    if (($p['quote_status'] ?? '') !== 'approved') bOut(['success' => false, 'message' => 'The labor quotation must be approved first.']);
    $p['contract_amount'] = (float) $p['contract_amount'];
    return $p;
}
function bApps(mysqli $conn, int $drId): array
{
    $rows = bAll($conn, "SELECT * FROM noblepc_billing WHERE dr_id = ? ORDER BY seq", 'i', [$drId]);
    foreach ($rows as &$r) {
        foreach (['id', 'seq', 'app_no'] as $k) $r[$k] = (int) $r[$k];
        foreach (['contract_amount', 'change_orders', 'retainage_rate', 'this_period'] as $k) $r[$k] = (float) $r[$k];
        $r['prepared_signature_url'] = bUrl($r['prepared_signature']);
        $r['head_signature_url'] = bUrl($r['head_signature']);
        $r['sa_signature_url'] = $r['status'] === 'approved' ? bUrl($r['sa_signature']) : null;
        unset($r['prepared_signature'], $r['head_signature'], $r['sa_signature']);
    }
    unset($r);
    return $rows;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = bAll($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name AS owner, q.quote_no, q.project_name, q.location, q.subcontractor, q.total AS contract_amount,
               (SELECT COUNT(*) FROM noblepc_billing b WHERE b.dr_id = d.id) AS app_count,
               (SELECT COALESCE(SUM(b.this_period), 0) FROM noblepc_billing b WHERE b.dr_id = d.id AND b.status = 'approved') AS approved_total,
               (SELECT COALESCE(SUM(b.this_period), 0) FROM noblepc_billing b WHERE b.dr_id = d.id) AS billed_total,
               (SELECT b.change_orders FROM noblepc_billing b WHERE b.dr_id = d.id ORDER BY b.seq DESC LIMIT 1) AS change_orders,
               (SELECT b.status FROM noblepc_billing b WHERE b.dr_id = d.id ORDER BY b.seq DESC LIMIT 1) AS last_status,
               (SELECT b.ref_no FROM noblepc_billing b WHERE b.dr_id = d.id ORDER BY b.seq DESC LIMIT 1) AS last_ref,
               (SELECT b.return_remarks FROM noblepc_billing b WHERE b.dr_id = d.id ORDER BY b.seq DESC LIMIT 1) AS last_remarks
        FROM noblepc_laborquote q
        JOIN noblepc_dr d ON d.id = q.dr_id
        WHERE q.status = 'approved'
        ORDER BY FIELD(COALESCE((SELECT b.status FROM noblepc_billing b WHERE b.dr_id = d.id ORDER BY b.seq DESC LIMIT 1), 'none'), 'returned', 'none', 'approved', 'pending_head', 'pending_sa'), d.id DESC
    ");
    foreach ($rows as &$r) {
        $r['dr_id'] = (int) $r['dr_id'];
        $r['app_count'] = (int) $r['app_count'];
        foreach (['contract_amount', 'approved_total', 'billed_total', 'change_orders'] as $k) $r[$k] = (float) $r[$k];
    }
    unset($r);
    bOut(['success' => true, 'rows' => $rows, 'today' => date('Y-m-d')]);
}

// ================= GET: buong project + lahat ng billing =================
if ($action === 'get') {
    $drId = intval($_GET['dr_id'] ?? 0);
    $p = bProject($conn, $drId);
    bOut(['success' => true, 'project' => $p, 'apps' => bApps($conn, $drId), 'me' => bUserName($conn, $currentUserId),
          'my_signature' => bUrl(bActiveSig($conn, $currentUserId)), 'today' => date('Y-m-d')]);
}

// ================= SAVE (bago o i-edit ang pending/returned na pinakahuli) =================
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') bOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    $editId = intval($_POST['id'] ?? 0);
    $p = bProject($conn, $drId);

    $type     = ($_POST['billing_type'] ?? '') === 'dp' ? 'dp' : 'progress';
    $amount   = bMoney($_POST['this_period'] ?? 0);
    $date     = bDate($_POST['billing_date'] ?? '') ?: date('Y-m-d');
    $from     = bDate($_POST['period_from'] ?? '');
    $to       = bDate($_POST['period_to'] ?? '');
    $rate     = round((float) ($_POST['retainage_rate'] ?? 0), 2);
    $change   = bMoney($_POST['change_orders'] ?? 0);
    $remarks  = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if ($amount <= 0) bOut(['success' => false, 'message' => 'Enter the amount for this billing.', 'field' => 'this_period']);
    if ($rate < 0 || $rate > 100) bOut(['success' => false, 'message' => 'Retainage must be 0 to 100%.', 'field' => 'retainage_rate']);
    if ($from && $to && $to < $from) bOut(['success' => false, 'message' => 'The billing period end cannot be before its start.', 'field' => 'period_to']);
    $sum = round($p['contract_amount'] + $change, 2);
    if ($sum <= 0) bOut(['success' => false, 'message' => 'The contract sum must be more than zero.', 'field' => 'change_orders']);

    $me = bUserName($conn, $currentUserId);
    $mySig = bActiveSig($conn, $currentUserId);

    $conn->begin_transaction();
    try {
        $apps = bAll($conn, "SELECT id, seq, app_no, billing_type, this_period, status, billing_date FROM noblepc_billing WHERE dr_id = ? ORDER BY seq FOR UPDATE", 'i', [$drId]);
        $last = $apps ? $apps[count($apps) - 1] : null;
        if ($editId) {
            if (!$last || (int) $last['id'] !== $editId) throw new RuntimeException('Only the latest billing can be edited.');
            if (!in_array($last['status'], ['pending_head', 'returned'], true)) throw new RuntimeException('This billing is already being signed and can no longer be edited.');
            $prev = array_slice($apps, 0, -1);
            $seq = (int) $last['seq'];
        } else {
            if ($last && $last['status'] !== 'approved') throw new RuntimeException('Finish the signing of ' . ($last['status'] === 'returned' ? 'the returned billing' : 'the current billing') . ' first.');
            $prev = $apps;
            $seq = count($apps) + 1;
        }
        // Down payment: unang billing lang
        if ($type === 'dp' && $prev) throw new RuntimeException('A down payment can only be the first billing.');
        $prevBilled = round(array_sum(array_map(fn($a) => (float) $a['this_period'], $prev)), 2);
        if ($prevBilled + $amount > $sum + 0.004) {
            throw new RuntimeException('This billing goes over the contract sum. Remaining balance is ₱' . number_format($sum - $prevBilled, 2) . '.');
        }
        $lastPrevDate = $prev ? $prev[count($prev) - 1]['billing_date'] : null;
        if ($lastPrevDate && $date < $lastPrevDate) throw new RuntimeException('The billing date cannot be earlier than the previous billing (' . date('M j, Y', strtotime($lastPrevDate)) . ').');
        $appNo = $type === 'dp' ? 0 : count(array_filter($prev, fn($a) => $a['billing_type'] === 'progress')) + 1;
        $ref = 'APP-' . $p['dr_no'] . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        if ($editId) {
            $st = $conn->prepare("UPDATE noblepc_billing SET app_no = ?, billing_type = ?, contract_amount = ?, change_orders = ?, retainage_rate = ?, this_period = ?, billing_date = ?, period_from = ?, period_to = ?, remarks = ?,
                                         prepared_by = ?, prepared_by_id = ?, prepared_signature = ?, submitted_at = NOW(), status = 'pending_head',
                                         head_by = NULL, head_by_id = NULL, head_at = NULL, head_signature = NULL WHERE id = ?");
            $st->bind_param('isddddsssssisi', $appNo, $type, $p['contract_amount'], $change, $rate, $amount, $date, $from, $to, $remarks, $me, $currentUserId, $mySig, $editId);
            $st->execute();
            $st->close();
            $id = $editId;
        } else {
            $st = $conn->prepare("INSERT INTO noblepc_billing (dr_id, seq, app_no, ref_no, billing_type, contract_amount, change_orders, retainage_rate, this_period, billing_date, period_from, period_to, remarks,
                                         prepared_by, prepared_by_id, prepared_signature, created_at, submitted_at, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'pending_head')");
            $st->bind_param('iiissddddsssssis', $drId, $seq, $appNo, $ref, $type, $p['contract_amount'], $change, $rate, $amount, $date, $from, $to, $remarks, $me, $currentUserId, $mySig);
            $st->execute();
            $id = $st->insert_id;
            $st->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if (!$e instanceof RuntimeException) error_log('[coordinatorbillingajax save] ' . $e->getMessage());
        bOut(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the billing: ' . $e->getMessage()]);
    }
    pcRealtime($conn, 'pc_billing', [[ROLE_PC, null], [ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_SUPERADMIN, null]], ['dr_id' => $drId, 'billing_id' => $id]);
    bOut(['success' => true, 'id' => $id, 'ref_no' => $ref]);
}

bOut(['success' => false, 'message' => 'Unknown action.']);