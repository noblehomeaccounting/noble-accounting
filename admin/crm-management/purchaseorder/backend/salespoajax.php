<?php
// salespoajax.php  (route: salespoajax) — Sales Purchase Order
// Daloy ng pirma:
//   Sales (Prepared by) → Accounting Head (Acknowledged by) → Superadmin (Approved by)
//   → Accounting Staff (Received by) → Custodian / Custodian Assistant (Released by)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES, ROLE_ACCOUNTING, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    error_log('salespoajax: ' . $e->getMessage());
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});

$spoMe = (int) ($_SESSION['account_id'] ?? 0);
$spoRole = (string) ($_SESSION['role'] ?? '');
$spoPos = (string) ($_SESSION['position'] ?? '');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Saan pupunta ang notification
const SPO_SALES_LINK = '/purchaseordermain';
const SPO_APPROVAL_LINK = '/salespoapproval';

// Bawat hakbang: status → [sino ang pipirma, label, susunod na status, column prefix]
const SPO_STAGES = [
    'pending_ack'      => ['who' => 'acct_head',  'label' => 'Acknowledged by', 'next' => 'pending_approval', 'col' => 'ack'],
    'pending_approval' => ['who' => 'superadmin', 'label' => 'Approved by',     'next' => 'pending_receive',  'col' => 'approved'],
    'pending_receive'  => ['who' => 'acct_staff', 'label' => 'Received by',     'next' => 'pending_release',  'col' => 'received'],
    'pending_release'  => ['who' => 'custodian',  'label' => 'Released by',     'next' => 'released',         'col' => 'released'],
];
const SPO_STATUS_LABEL = [
    'draft'            => 'Draft',
    'pending_ack'      => 'For Accounting Head',
    'pending_approval' => 'For Superadmin approval',
    'pending_receive'  => 'For Accounting Staff',
    'pending_release'  => 'For release',
    'released'         => 'Released',
    'returned'         => 'Returned to Sales',
];

function spoOut(array $d): void
{
    echo json_encode($d);
    exit;
}

/** Sino ang tumitingin: sales | acct_head | acct_staff | custodian | superadmin | '' */
function spoViewer(string $role, string $pos): string
{
    if ($role === ROLE_SALES) return 'sales';
    if ($role === ROLE_SUPERADMIN) return 'superadmin';
    if ($role === ROLE_ACCOUNTING) {
        if ($pos === POSITION_HEAD) return 'acct_head';
        if ($pos === POSITION_STAFF) return 'acct_staff';
        if ($pos === POSITION_CUSTODIAN || $pos === POSITION_CUSTOASSISTANT) return 'custodian';
    }
    return '';
}
$spoViewer = spoViewer($spoRole, $spoPos);
if ($spoViewer === '') spoOut(['success' => false, 'message' => 'You do not have access to Purchase Orders.']);

$spoIsSalesHead = false;
if ($spoViewer === 'sales') {
    $st = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
    $r = ROLE_SALES; $p = POSITION_HEAD;
    $st->bind_param('iss', $spoMe, $r, $p);
    $st->execute();
    $spoIsSalesHead = (bool) $st->get_result()->fetch_assoc();
    $st->close();
}

function spoOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r ?: null;
}
function spoAll(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $r;
}

/** Pangalan + naka-set na pirma (kapareho ng Budget Request: active_signature_id muna) */
function spoUser(mysqli $conn, int $uid): array
{
    $u = spoOne($conn, "SELECT name, active_signature_id FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid]) ?? [];
    $path = null;
    if (!empty($u['active_signature_id'])) {
        $path = spoOne($conn, "SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1", 'ii', [(int) $u['active_signature_id'], $uid])['path'] ?? null;
    }
    if ($path === null) {
        $path = spoOne($conn, "SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", 'i', [$uid])['path'] ?? null;
    }
    return ['name' => (string) ($u['name'] ?? ''), 'sig' => $path];
}

function spoSigUrl(?string $p): ?string
{
    if (!$p) return null;
    return preg_match('#^https?://#', $p) ? $p : BASE_URL . '/' . ltrim($p, '/');
}

/** Puwede bang pumirma ang viewer sa status na ito? */
function spoCanSign(string $viewer, string $status): bool
{
    return isset(SPO_STAGES[$status]) && SPO_STAGES[$status]['who'] === $viewer;
}

function spoNotify(mysqli $conn, array $userIds, int $poId, string $poNo, string $msg, string $link, int $sender): void
{
    try {
        $n = $conn->prepare("INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
                             VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)");
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid <= 0 || $uid === $sender) continue;
            $n->bind_param('iissis', $uid, $poId, $poNo, $msg, $sender, $link);
            $n->execute();
        }
        $n->close();
    } catch (Throwable $e) { error_log('spoNotify: ' . $e->getMessage()); }
}

/** User ids ng susunod na pipirma */
function spoNextSigners(mysqli $conn, string $status): array
{
    $who = SPO_STAGES[$status]['who'] ?? '';
    $q = [
        'acct_head'  => ["SELECT id FROM noblerole WHERE role = ? AND position = ?", 'ss', [ROLE_ACCOUNTING, POSITION_HEAD]],
        'superadmin' => ["SELECT id FROM noblerole WHERE role = ?", 's', [ROLE_SUPERADMIN]],
        'acct_staff' => ["SELECT id FROM noblerole WHERE role = ? AND position = ?", 'ss', [ROLE_ACCOUNTING, POSITION_STAFF]],
        'custodian'  => ["SELECT id FROM noblerole WHERE role = ? AND position IN (?, ?)", 'sss', [ROLE_ACCOUNTING, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT]],
    ][$who] ?? null;
    if (!$q) return [];
    return array_column(spoAll($conn, $q[0], $q[1], $q[2]), 'id');
}

function spoRealtime(string $type, int $poId): void
{
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        if (!function_exists('nhRealtimeNotifyGroup')) return;
        foreach ([[ROLE_SALES, null], [ROLE_ACCOUNTING, null], [ROLE_SUPERADMIN, null]] as $g) {
            nhRealtimeNotifyGroup((string) $g[0], $g[1], 'changed', ['type' => $type, 'po_id' => $poId]);
        }
    } catch (Throwable $e) { error_log('spoRealtime: ' . $e->getMessage()); }
}

/** Para sa list: buod ng isang PO */
function spoRowJson(array $r, string $viewer): array
{
    $waitingSince = [
        'pending_ack' => $r['submitted_at'], 'pending_approval' => $r['ack_at'],
        'pending_receive' => $r['approved_at'], 'pending_release' => $r['received_at'],
    ][$r['status']] ?? null;
    return [
        'id'            => (int) $r['id'],
        'po_no'         => $r['po_no'],
        'po_date'       => $r['po_date'],
        'status'        => $r['status'],
        'status_label'  => SPO_STATUS_LABEL[$r['status']] ?? $r['status'],
        'vendor'        => $r['vendor_company'] ?: $r['vendor_name'],
        'vendor_name'   => $r['vendor_name'],
        'customer'      => $r['cust_company'] ?: $r['cust_name'],
        'prepared_by'   => $r['prepared_by'],
        'final_amount'  => (float) $r['final_amount'],
        'item_count'    => (int) $r['item_count'],
        'created_at'    => $r['created_at'],
        'submitted_at'  => $r['submitted_at'],
        'updated_at'    => $r['updated_at'] ?: $r['created_at'],
        'waiting_since' => $waitingSince,
        'released_at'   => $r['released_at'],
        'return_remarks' => $r['status'] === 'returned' ? $r['return_remarks'] : null,
        'returned_at'   => $r['returned_at'],
        'my_turn'       => spoCanSign($viewer, $r['status'])
                           || ($viewer === 'sales' && in_array($r['status'], ['draft', 'returned'], true)),
    ];
}

/** Basahin at linisin ang laman ng form (Sales) */
function spoReadForm(): array
{
    $t = fn($k, $max = 150) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $date = function ($k) {
        $v = trim((string) ($_POST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    };
    $num = fn($k) => round(max(0, (float) str_replace(',', '', (string) ($_POST[$k] ?? 0))), 2);

    $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
    $clean = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            $desc = mb_substr(trim((string) ($it['description'] ?? '')), 0, 255);
            if ($desc === '') continue;
            $qty = round(max(0, (float) ($it['quantity'] ?? 0)), 2);
            $price = round(max(0, (float) ($it['unit_price'] ?? 0)), 2);
            $clean[] = [
                'description' => $desc,
                'spec'        => mb_substr(trim((string) ($it['spec'] ?? '')), 0, 255),
                'quantity'    => $qty,
                'unit'        => mb_substr(trim((string) ($it['unit'] ?? '')), 0, 30),
                'unit_price'  => $price,
                'amount'      => round($qty * $price, 2),
            ];
        }
    }
    $total = round(array_sum(array_column($clean, 'amount')), 2);
    $disc = min(100, $num('discount_pct'));
    $after = round($total - $total * $disc / 100, 2);
    $addl = min($after, $num('addl_discount'));
    $final = round($after - $addl, 2);
    $dp = min($final, $num('down_payment'));

    return [
        'po_date'        => $date('po_date') ?? date('Y-m-d'),
        'cust_name'      => $t('cust_name'), 'cust_company' => $t('cust_company'),
        'cust_address'   => $t('cust_address', 255), 'cust_phone' => $t('cust_phone', 80),
        'cust_email'     => $t('cust_email'), 'start_date' => $date('start_date'),
        'vendor_name'    => $t('vendor_name'), 'vendor_company' => $t('vendor_company'),
        'vendor_address' => $t('vendor_address', 255), 'vendor_phone' => $t('vendor_phone', 80),
        'vendor_email'   => $t('vendor_email'),
        'note'           => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000),
        'discount_pct'   => $disc, 'addl_discount' => $addl,
        'down_payment'   => $dp, 'dp_date' => $date('dp_date'),
        'total_amount'   => $total, 'final_amount' => $final, 'balance' => round($final - $dp, 2),
        'items'          => $clean,
    ];
}

/** Bagong PO number: NHPO-2026-001 */
function spoNextNo(mysqli $conn): string
{
    $y = date('Y');
    $like = "NHPO-$y-%";
    $last = spoOne($conn, "SELECT po_no FROM noblesales_po WHERE po_no LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE", 's', [$like])['po_no'] ?? null;
    $n = $last && preg_match('/-(\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;
    return sprintf('NHPO-%s-%03d', $y, $n);
}

// ═══════════════ LIST ═══════════════
if ($action === 'list') {
    $sql = "SELECT p.*, (SELECT COUNT(*) FROM noblesales_po_item i WHERE i.po_id = p.id) AS item_count FROM noblesales_po p";
    $types = ''; $params = [];
    if ($spoViewer === 'sales') {
        if (!$spoIsSalesHead) { $sql .= " WHERE p.prepared_by_id = ?"; $types = 'i'; $params = [$spoMe]; }
    } else {
        $sql .= " WHERE p.status NOT IN ('draft')";   // approvers: lahat ng naipasa na
    }
    $sql .= " ORDER BY p.id DESC LIMIT 500";
    $rows = array_map(fn($r) => spoRowJson($r, $spoViewer), spoAll($conn, $sql, $types, $params));
    $me = spoUser($conn, $spoMe);
    spoOut(['success' => true, 'rows' => $rows, 'viewer' => $spoViewer, 'me' => $me['name'], 'my_signature' => spoSigUrl($me['sig']), 'now' => date('Y-m-d H:i:s')]);
}

// ═══════════════ GET (isang PO, o mga default para sa bago) ═══════════════
if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    $me = spoUser($conn, $spoMe);
    if ($id <= 0) {
        if ($spoViewer !== 'sales') spoOut(['success' => false, 'message' => 'Only Sales can create a Purchase Order.']);
        spoOut(['success' => true, 'po' => [
            'id' => 0, 'po_no' => '', 'po_date' => date('Y-m-d'), 'status' => 'draft',
            'cust_name' => $me['name'], 'cust_company' => 'Noblehome Construction Corporation',
            'cust_address' => '1181 MC Premiere Bldg, EDSA Balintawak, Quezon City',
            'cust_phone' => '02-8822-1295 / 0968-591-6544', 'cust_email' => 'noblehomeconst.ph@gmail.com',
            'start_date' => date('Y-m-d'), 'items' => [],
            'discount_pct' => 0, 'addl_discount' => 0, 'down_payment' => 0,
            'prepared_by' => $me['name'],
        ], 'can_edit' => true, 'can_sign' => false, 'my_signature' => spoSigUrl($me['sig']), 'me' => $me['name']]);
    }
    $po = spoOne($conn, "SELECT * FROM noblesales_po WHERE id = ? LIMIT 1", 'i', [$id]);
    if (!$po) spoOut(['success' => false, 'message' => 'Purchase Order not found.']);
    if ($spoViewer === 'sales' && !$spoIsSalesHead && (int) $po['prepared_by_id'] !== $spoMe) spoOut(['success' => false, 'message' => 'This Purchase Order is not yours.']);
    if ($spoViewer !== 'sales' && $po['status'] === 'draft') spoOut(['success' => false, 'message' => 'This Purchase Order has not been submitted yet.']);

    $po['items'] = spoAll($conn, "SELECT description, spec, quantity, unit, unit_price, amount FROM noblesales_po_item WHERE po_id = ? ORDER BY item_no", 'i', [$id]);
    foreach (['prepared', 'ack', 'approved', 'received', 'released'] as $k) $po[$k . '_sig_url'] = spoSigUrl($po[$k . '_sig'] ?? null);
    $po['status_label'] = SPO_STATUS_LABEL[$po['status']] ?? $po['status'];
    $canEdit = $spoViewer === 'sales' && (int) $po['prepared_by_id'] === $spoMe && in_array($po['status'], ['draft', 'returned'], true);
    spoOut([
        'success' => true, 'po' => $po,
        'can_edit' => $canEdit,
        'can_sign' => spoCanSign($spoViewer, $po['status']),
        'sign_label' => SPO_STAGES[$po['status']]['label'] ?? null,
        'my_signature' => spoSigUrl($me['sig']), 'me' => $me['name'],
    ]);
}

// ═══════════════ SAVE / SUBMIT (Sales) ═══════════════
if ($action === 'save' || $action === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') spoOut(['success' => false, 'message' => 'Invalid request.']);
    if ($spoViewer !== 'sales') spoOut(['success' => false, 'message' => 'Only Sales can prepare a Purchase Order.']);
    $id = (int) ($_POST['id'] ?? 0);
    $f = spoReadForm();

    if ($action === 'submit') {
        $missing = [];
        if ($f['vendor_name'] === '' && $f['vendor_company'] === '') $missing[] = 'vendor name or company';
        if ($f['cust_name'] === '') $missing[] = 'customer name';
        if (!$f['items']) $missing[] = 'at least one item';
        if ($missing) spoOut(['success' => false, 'message' => 'Please add the ' . implode(', ', $missing) . '.']);
        foreach ($f['items'] as $i => $it) {
            if ($it['quantity'] <= 0) spoOut(['success' => false, 'message' => 'Item ' . ($i + 1) . ' needs a quantity.']);
        }
    }

    $me = spoUser($conn, $spoMe);
    if ($action === 'submit' && !$me['sig']) spoOut(['success' => false, 'message' => 'Set your e-signature first (the same one used in Budget Requests), then submit.']);

    $conn->begin_transaction();
    try {
        if ($id > 0) {
            $cur = spoOne($conn, "SELECT id, po_no, status, prepared_by_id FROM noblesales_po WHERE id = ? FOR UPDATE", 'i', [$id]);
            if (!$cur || (int) $cur['prepared_by_id'] !== $spoMe) throw new RuntimeException('Purchase Order not found.');
            if (!in_array($cur['status'], ['draft', 'returned'], true)) throw new RuntimeException('This Purchase Order was already submitted and can no longer be edited.');
            $poNo = $cur['po_no'];
        } else {
            $poNo = spoNextNo($conn);
            $st = $conn->prepare("INSERT INTO noblesales_po (po_no, po_date, status, prepared_by_id, prepared_by, created_at) VALUES (?, ?, 'draft', ?, ?, NOW())");
            $st->bind_param('ssis', $poNo, $f['po_date'], $spoMe, $me['name']);
            $st->execute();
            $id = (int) $conn->insert_id;
            $st->close();
        }

        $st = $conn->prepare("UPDATE noblesales_po SET po_date = ?, cust_name = ?, cust_company = ?, cust_address = ?, cust_phone = ?, cust_email = ?, start_date = ?,
            vendor_name = ?, vendor_company = ?, vendor_address = ?, vendor_phone = ?, vendor_email = ?, note = ?,
            discount_pct = ?, addl_discount = ?, down_payment = ?, dp_date = ?, total_amount = ?, final_amount = ?, balance = ?, prepared_by = ?
            WHERE id = ?");
        $st->bind_param('sssssssssssssdddsdddsi',
            $f['po_date'], $f['cust_name'], $f['cust_company'], $f['cust_address'], $f['cust_phone'], $f['cust_email'], $f['start_date'],
            $f['vendor_name'], $f['vendor_company'], $f['vendor_address'], $f['vendor_phone'], $f['vendor_email'], $f['note'],
            $f['discount_pct'], $f['addl_discount'], $f['down_payment'], $f['dp_date'], $f['total_amount'], $f['final_amount'], $f['balance'], $me['name'], $id);
        $st->execute();
        $st->close();

        $del = $conn->prepare("DELETE FROM noblesales_po_item WHERE po_id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        $ins = $conn->prepare("INSERT INTO noblesales_po_item (po_id, item_no, description, spec, quantity, unit, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($f['items'] as $i => $it) {
            $no = $i + 1;
            $ins->bind_param('iissdsdd', $id, $no, $it['description'], $it['spec'], $it['quantity'], $it['unit'], $it['unit_price'], $it['amount']);
            $ins->execute();
        }
        $ins->close();

        if ($action === 'submit') {
            // Bagong pasa: linis ang lahat ng naunang pirma ng approvers
            $st = $conn->prepare("UPDATE noblesales_po SET status = 'pending_ack', prepared_sig = ?, submitted_at = NOW(),
                ack_by_id = NULL, ack_by = NULL, ack_sig = NULL, ack_at = NULL,
                approved_by_id = NULL, approved_by = NULL, approved_sig = NULL, approved_at = NULL,
                received_by_id = NULL, received_by = NULL, received_sig = NULL, received_at = NULL,
                released_by_id = NULL, released_by = NULL, released_title = NULL, released_sig = NULL, released_at = NULL
                WHERE id = ?");
            $st->bind_param('si', $me['sig'], $id);
            $st->execute();
            $st->close();
        }
        $conn->commit();
    } catch (RuntimeException $e) {
        $conn->rollback();
        spoOut(['success' => false, 'message' => $e->getMessage()]);
    }

    if ($action === 'submit') {
        spoNotify($conn, spoNextSigners($conn, 'pending_ack'), $id, $poNo, "Purchase Order {$poNo} from {$me['name']} needs your acknowledgement.", SPO_APPROVAL_LINK, $spoMe);
        spoRealtime('spo_submitted', $id);
        spoOut(['success' => true, 'id' => $id, 'po_no' => $poNo, 'message' => "{$poNo} submitted to the Accounting Head."]);
    }
    spoOut(['success' => true, 'id' => $id, 'po_no' => $poNo, 'message' => "{$poNo} saved as draft."]);
}

// ═══════════════ DELETE DRAFT (Sales) ═══════════════
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $spoViewer !== 'sales') spoOut(['success' => false, 'message' => 'Invalid request.']);
    $id = (int) ($_POST['id'] ?? 0);
    $st = $conn->prepare("DELETE FROM noblesales_po WHERE id = ? AND prepared_by_id = ? AND status = 'draft'");
    $st->bind_param('ii', $id, $spoMe);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    spoOut($ok ? ['success' => true, 'message' => 'Draft deleted.'] : ['success' => false, 'message' => 'Only your own drafts can be deleted.']);
}

// ═══════════════ SIGN (Accounting Head / Superadmin / Accounting Staff / Custodian) ═══════════════
if ($action === 'sign') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') spoOut(['success' => false, 'message' => 'Invalid request.']);
    $id = (int) ($_POST['id'] ?? 0);
    $me = spoUser($conn, $spoMe);
    if (!$me['sig']) spoOut(['success' => false, 'message' => 'Set your e-signature first (the same one used in Budget Requests), then sign.']);

    $conn->begin_transaction();
    $cur = spoOne($conn, "SELECT id, po_no, status, prepared_by_id FROM noblesales_po WHERE id = ? FOR UPDATE", 'i', [$id]);
    if (!$cur) { $conn->rollback(); spoOut(['success' => false, 'message' => 'Purchase Order not found.']); }
    if (!spoCanSign($spoViewer, $cur['status'])) {
        $conn->rollback();
        spoOut(['success' => false, 'message' => 'This Purchase Order is not waiting for your signature (it may have been signed already). Refreshing.']);
    }
    $stage = SPO_STAGES[$cur['status']];
    $col = $stage['col'];
    $next = $stage['next'];
    if ($col === 'released') {
        $title = $spoPos === POSITION_CUSTOASSISTANT ? 'Custodian Assistant' : 'Custodian';
        $st = $conn->prepare("UPDATE noblesales_po SET status = ?, released_by_id = ?, released_by = ?, released_title = ?, released_sig = ?, released_at = NOW() WHERE id = ?");
        $st->bind_param('sisssi', $next, $spoMe, $me['name'], $title, $me['sig'], $id);
    } else {
        $st = $conn->prepare("UPDATE noblesales_po SET status = ?, {$col}_by_id = ?, {$col}_by = ?, {$col}_sig = ?, {$col}_at = NOW() WHERE id = ?");
        $st->bind_param('sissi', $next, $spoMe, $me['name'], $me['sig'], $id);
    }
    $st->execute();
    $st->close();
    $conn->commit();

    $poNo = $cur['po_no'];
    if ($next === 'released') {
        spoNotify($conn, [(int) $cur['prepared_by_id']], $id, $poNo, "Your Purchase Order {$poNo} has been released.", SPO_SALES_LINK, $spoMe);
    } else {
        $what = ['pending_approval' => 'approval', 'pending_receive' => 'receiving', 'pending_release' => 'release'][$next];
        spoNotify($conn, spoNextSigners($conn, $next), $id, $poNo, "Purchase Order {$poNo} is ready for your {$what}.", SPO_APPROVAL_LINK, $spoMe);
        spoNotify($conn, [(int) $cur['prepared_by_id']], $id, $poNo, "Your Purchase Order {$poNo}: {$stage['label']} {$me['name']}.", SPO_SALES_LINK, $spoMe);
    }
    spoRealtime('spo_signed', $id);
    spoOut(['success' => true, 'po_no' => $poNo, 'status' => $next, 'message' => "{$poNo} signed."]);
}

// ═══════════════ RETURN (ibalik sa Sales) ═══════════════
if ($action === 'return') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') spoOut(['success' => false, 'message' => 'Invalid request.']);
    $id = (int) ($_POST['id'] ?? 0);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if (mb_strlen($remarks) < 3) spoOut(['success' => false, 'message' => 'Tell Sales what to fix.']);
    $me = spoUser($conn, $spoMe);

    $conn->begin_transaction();
    $cur = spoOne($conn, "SELECT id, po_no, status, prepared_by_id FROM noblesales_po WHERE id = ? FOR UPDATE", 'i', [$id]);
    if (!$cur || !spoCanSign($spoViewer, $cur['status'])) {
        $conn->rollback();
        spoOut(['success' => false, 'message' => 'Only the person whose turn it is can return this Purchase Order.']);
    }
    $st = $conn->prepare("UPDATE noblesales_po SET status = 'returned', returned_at = NOW(), returned_by = ?, returned_stage = ?, return_remarks = ? WHERE id = ?");
    $st->bind_param('sssi', $me['name'], $cur['status'], $remarks, $id);
    $st->execute();
    $st->close();
    $conn->commit();

    spoNotify($conn, [(int) $cur['prepared_by_id']], $id, $cur['po_no'], "Your Purchase Order {$cur['po_no']} was returned: {$remarks}", SPO_SALES_LINK, $spoMe);
    spoRealtime('spo_returned', $id);
    spoOut(['success' => true, 'message' => "{$cur['po_no']} returned to Sales."]);
}

spoOut(['success' => false, 'message' => 'Unknown action.']);