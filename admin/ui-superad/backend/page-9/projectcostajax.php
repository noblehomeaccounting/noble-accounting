<?php
// projectcostajax.php  —  Project Cost: contract ng client laban sa lahat ng gastos ng project
//
// Budget  = contract ng client (noblecrminquiry.contract_amount)
// Gastos  = Materials (Purchase Request, hindi kasama ang Rejected)
//         + Labor (approved na Labor Quotation + change orders ng huling billing)
// Nagastos na = PR na bayad na (paid_at) + labor billing na approved
// Chain: inquiry → 2D quotation → BOM → PR → Transmittal → DR → Labor quotation / Labor billing
//
// Basa lang ito — walang binabago sa database.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN, ROLE_ACCOUNTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
include_once ROOT_PATH . '/network/client-payment.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Accounting: head lang
if (($_SESSION['role'] ?? '') === ROLE_ACCOUNTING && defined('POSITION_HEAD') && ($_SESSION['position'] ?? '') !== POSITION_HEAD) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accounting Head only.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function pcoOut(array $d): void
{
    echo json_encode($d);
    exit;
}
set_exception_handler(function (Throwable $e) {
    error_log('[projectcostajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
});
function pcoAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function pcoIn(array $ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    return $ids ? implode(',', $ids) : '0';
}
function pcoHasCol(mysqli $conn, string $table, string $col): bool
{
    static $cache = [];
    $k = "$table.$col";
    if (!isset($cache[$k])) {
        try {
            $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($col) . "'");
            $cache[$k] = $r && $r->num_rows > 0;
        } catch (Throwable $e) {
            $cache[$k] = false;
        }
    }
    return $cache[$k];
}
function pcoMoney($v): float
{
    return round((float) ($v ?? 0), 2);
}
function pcoDate($v): ?string
{
    $v = (string) ($v ?? '');
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) && substr($v, 0, 10) !== '0000-00-00' ? substr($v, 0, 10) : null;
}

/**
 * Kinukuha ang lahat ng project (o isang inquiry lang) at ang mga konektadong dokumento.
 * @return array<int, array> naka-key sa inquiry id
 */
function pcoLoad(mysqli $conn, int $onlyInquiry = 0, bool $withDocs = false): array
{
    $where = $onlyInquiry > 0 ? ' AND q.inquiry_id = ' . $onlyInquiry : '';

    // 1) BOM na approved (ito ang simula ng project)
    $boms = pcoAll($conn, "
        SELECT b.id, b.bom_number, b.approved_at, b.date_submitted, b.general_scope, b.progression_id, q.inquiry_id,
               (SELECT COUNT(*) FROM noblecrm_bom_items it WHERE it.bom_id = b.id) AS item_count
        FROM noblecrm_bom b
        JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
        WHERE b.approved_at IS NOT NULL AND b.bom_number NOT LIKE 'TEMP-%' $where
        ORDER BY b.id");
    if (!$boms) return [];
    $inqIds = array_column($boms, 'inquiry_id');

    // 2) Client + contract + bayad
    $paidSql = cpClientPaidSql($conn, 'i.id');
    $inqs = pcoAll($conn, "SELECT i.id, i.control_no, i.client_name, COALESCE(i.contract_amount, 0) AS contract, $paidSql AS client_paid
                           FROM noblecrminquiry i WHERE i.id IN (" . pcoIn($inqIds) . ")");
    $P = [];
    foreach ($inqs as $i) {
        $P[(int) $i['id']] = [
            'id' => (int) $i['id'], 'control_no' => (string) $i['control_no'], 'client' => (string) $i['client_name'],
            'contract' => pcoMoney($i['contract']), 'client_paid' => pcoMoney($i['client_paid']),
            'mat' => 0.0, 'mat_paid' => 0.0, 'mat_pending' => 0.0,
            'labor' => 0.0, 'labor_paid' => 0.0, 'labor_pending' => 0.0,
            'bom_n' => 0, 'pr_n' => 0, 'tr_n' => 0, 'dr_n' => 0, 'lq_n' => 0, 'bill_n' => 0,
            'last_at' => null, 'events' => [],
            'docs' => ['bom' => [], 'pr' => [], 'transmittal' => [], 'dr' => [], 'labor' => [], 'billing' => [], 'payment' => []],
        ];
    }
    $bomInq = [];
    foreach ($boms as $b) {
        $inq = (int) $b['inquiry_id'];
        if (!isset($P[$inq])) continue;
        $bomInq[(int) $b['id']] = $inq;
        $P[$inq]['bom_n']++;
        $P[$inq]['last_at'] = max($P[$inq]['last_at'] ?? '', (string) $b['approved_at']);
        if ($withDocs) $P[$inq]['docs']['bom'][] = [
            'id' => (int) $b['id'], 'no' => $b['bom_number'], 'scope' => $b['general_scope'], 'items' => (int) $b['item_count'],
            'date' => pcoDate($b['approved_at']), 'status' => 'Approved',
        ];
    }
    if (!$bomInq) return [];

    // 3) Purchase Request (ang "lead" BOM ng PR ay laging nasa parehong client)
    $prs = pcoAll($conn, "
        SELECT p.id, p.bom_id, p.req_no, p.status, COALESCE(p.total_amount, 0) AS total, p.request_date, p.created_at,
               p.approved_at, p.released_at, p.paid_at,
               (SELECT COALESCE(SUM(x.amount), 0) FROM noblepurchaserequest_items x WHERE x.pr_id = p.id) AS items_total
        FROM noblepurchaserequest p
        WHERE p.bom_id IN (" . pcoIn(array_keys($bomInq)) . ") AND COALESCE(p.status, '') <> 'Rejected'
        ORDER BY p.id");
    $prInq = [];
    foreach ($prs as $p) {
        $inq = $bomInq[(int) $p['bom_id']] ?? 0;
        if (!$inq) continue;
        $prInq[(int) $p['id']] = $inq;
        $amt = pcoMoney((float) $p['total'] > 0 ? $p['total'] : $p['items_total']);
        $stage = $p['paid_at'] ? 'Paid' : ($p['released_at'] ? 'Released' : ($p['approved_at'] ? 'Approved' : ($p['status'] ?: 'Pending')));
        $P[$inq]['pr_n']++;
        $P[$inq]['mat'] += $amt;
        if ($p['paid_at']) $P[$inq]['mat_paid'] += $amt;
        if (!$p['approved_at'] && !$p['paid_at']) $P[$inq]['mat_pending'] += $amt;
        $d = pcoDate($p['request_date']) ?? pcoDate($p['created_at']);
        if ($d) $P[$inq]['events'][] = [$d, $amt, 'mat'];
        $P[$inq]['last_at'] = max($P[$inq]['last_at'] ?? '', (string) $p['created_at']);
        if ($withDocs) $P[$inq]['docs']['pr'][] = ['id' => (int) $p['id'], 'no' => $p['req_no'], 'amount' => $amt, 'date' => $d, 'status' => $stage, 'items' => []];
    }

    // PR items (para makita kung saan napunta ang pera)
    if ($withDocs && $prInq) {
        $its = pcoAll($conn, "SELECT pr_id, item_no, item_name, quantity, unit, price, amount FROM noblepurchaserequest_items
                              WHERE pr_id IN (" . pcoIn(array_keys($prInq)) . ") ORDER BY pr_id, item_no");
        $byPr = [];
        foreach ($its as $it) $byPr[(int) $it['pr_id']][] = [
            'name' => $it['item_name'], 'qty' => (float) $it['quantity'], 'unit' => $it['unit'],
            'price' => pcoMoney($it['price']), 'amount' => pcoMoney($it['amount']),
        ];
        foreach ($P as &$pp) foreach ($pp['docs']['pr'] as &$pr) $pr['items'] = $byPr[$pr['id']] ?? [];
        unset($pp, $pr);
    }

    // 4) Transmittal
    $trInq = [];
    if ($prInq) {
        $trs = pcoAll($conn, "SELECT id, pr_id, ref_no, transmittal_date, delivered_at, created_at FROM nobletransmittal
                              WHERE pr_id IN (" . pcoIn(array_keys($prInq)) . ") ORDER BY id");
        foreach ($trs as $t) {
            $inq = $prInq[(int) $t['pr_id']] ?? 0;
            if (!$inq) continue;
            $trInq[(int) $t['id']] = $inq;
            $P[$inq]['tr_n']++;
            if ($withDocs) $P[$inq]['docs']['transmittal'][] = [
                'id' => (int) $t['id'], 'no' => $t['ref_no'], 'date' => pcoDate($t['transmittal_date']) ?? pcoDate($t['created_at']),
                'status' => $t['delivered_at'] ? 'Delivered' : 'For delivery',
            ];
        }
    }

    // 5) DR
    $drInq = [];
    if ($trInq) {
        $drs = pcoAll($conn, "SELECT id, transmittal_id, dr_no, status FROM noblepc_dr WHERE transmittal_id IN (" . pcoIn(array_keys($trInq)) . ") ORDER BY id");
        foreach ($drs as $d) {
            $inq = $trInq[(int) $d['transmittal_id']] ?? 0;
            if (!$inq) continue;
            $drInq[(int) $d['id']] = $inq;
            $P[$inq]['dr_n']++;
            if ($withDocs) $P[$inq]['docs']['dr'][] = ['id' => (int) $d['id'], 'no' => $d['dr_no'], 'status' => ucfirst((string) $d['status'])];
        }
    }

    // 6) Labor quotation + labor billing (bayad sa subcontractor)
    if ($drInq) {
        $in = pcoIn(array_keys($drInq));
        $co = [];
        $bills = pcoAll($conn, "SELECT id, dr_id, seq, " . (pcoHasCol($conn, 'noblepc_billing', 'app_no') ? 'app_no' : 'seq AS app_no') . ",
                                       " . (pcoHasCol($conn, 'noblepc_billing', 'billing_date') ? 'billing_date' : 'NULL AS billing_date') . ",
                                       status, COALESCE(this_period, 0) AS amt, COALESCE(change_orders, 0) AS co
                                FROM noblepc_billing WHERE dr_id IN ($in) ORDER BY dr_id, seq");
        foreach ($bills as $b) $co[(int) $b['dr_id']] = pcoMoney($b['co']);   // huling billing = kasalukuyang change orders

        $lqDate = pcoHasCol($conn, 'noblepc_laborquote', 'quote_date') ? 'quote_date' : 'NULL AS quote_date';
        $lqs = pcoAll($conn, "SELECT id, dr_id, quote_no, subcontractor, status, COALESCE(total, 0) AS total, $lqDate
                              FROM noblepc_laborquote WHERE dr_id IN ($in) ORDER BY id");
        foreach ($lqs as $q) {
            $inq = $drInq[(int) $q['dr_id']] ?? 0;
            if (!$inq) continue;
            $amt = pcoMoney($q['total']);
            $status = (string) $q['status'];
            $P[$inq]['lq_n']++;
            if ($status === 'approved') {
                $P[$inq]['labor'] += $amt + ($co[(int) $q['dr_id']] ?? 0);
            } elseif ($status !== 'returned' && $status !== 'rejected') {
                $P[$inq]['labor_pending'] += $amt;   // hindi pa approved — inaasahang gastos
            }
            if ($withDocs) $P[$inq]['docs']['labor'][] = [
                'id' => (int) $q['id'], 'no' => $q['quote_no'], 'sub' => $q['subcontractor'], 'amount' => $amt,
                'change_orders' => $co[(int) $q['dr_id']] ?? 0, 'date' => pcoDate($q['quote_date']), 'status' => ucfirst($status ?: 'pending'),
            ];
        }
        foreach ($bills as $b) {
            $inq = $drInq[(int) $b['dr_id']] ?? 0;
            if (!$inq) continue;
            $amt = pcoMoney($b['amt']);
            $P[$inq]['bill_n']++;
            if ($b['status'] === 'approved') {
                $P[$inq]['labor_paid'] += $amt;
                $d = pcoDate($b['billing_date']);
                if ($d) $P[$inq]['events'][] = [$d, $amt, 'labor'];
            }
            if ($withDocs) $P[$inq]['docs']['billing'][] = [
                'id' => (int) $b['id'], 'no' => 'App #' . ($b['app_no'] ?: $b['seq']), 'amount' => $amt,
                'date' => pcoDate($b['billing_date']), 'status' => ucwords(str_replace('_', ' ', (string) $b['status'])),
            ];
        }
    }

    // 7) Bayad ng client
    if ($withDocs && cpHasPaymentTable($conn)) {
        $dateCol = pcoHasCol($conn, 'noblecrm_deposit_payment', 'paid_on') ? 'paid_on' : 'NULL';
        $refCol = pcoHasCol($conn, 'noblecrm_deposit_payment', 'reference_no') ? 'reference_no' : "''";
        $pays = pcoAll($conn, "SELECT id, inquiry_id, amount, $dateCol AS paid_on, $refCol AS ref FROM noblecrm_deposit_payment
                               WHERE inquiry_id IN (" . pcoIn(array_keys($P)) . ") ORDER BY id");
        foreach ($pays as $p) {
            $inq = (int) $p['inquiry_id'];
            if (!isset($P[$inq])) continue;
            $P[$inq]['docs']['payment'][] = ['id' => (int) $p['id'], 'no' => $p['ref'] ?: 'Payment', 'amount' => pcoMoney($p['amount']), 'date' => pcoDate($p['paid_on']), 'status' => 'Received'];
        }
    }

    // 8) Totals at status
    foreach ($P as &$p) {
        foreach (['mat', 'mat_paid', 'mat_pending', 'labor', 'labor_paid', 'labor_pending'] as $k) $p[$k] = round($p[$k], 2);
        $p['cost'] = round($p['mat'] + $p['labor'], 2);                       // kabuuang gastos (committed)
        $p['spent'] = round($p['mat_paid'] + $p['labor_paid'], 2);            // nagastos na talaga
        $p['projected'] = round($p['cost'] + $p['labor_pending'], 2);         // kasama ang labor na hindi pa approved
        $p['remaining'] = round($p['contract'] - $p['cost'], 2);
        $p['used_pct'] = $p['contract'] > 0 ? round($p['cost'] / $p['contract'] * 100, 1) : null;
        $p['status'] = $p['contract'] <= 0 ? 'no_contract'
            : ($p['cost'] > $p['contract'] + 0.004 ? 'over' : ($p['used_pct'] >= 90 ? 'near' : 'within'));
        $p['projected_over'] = $p['contract'] > 0 && $p['projected'] > $p['contract'] + 0.004;
        usort($p['events'], fn($a, $b) => strcmp($a[0], $b[0]));
        if (!$withDocs) unset($p['docs'], $p['events']);
    }
    unset($p);
    return $P;
}

if ($action === 'list') {
    $rows = array_values(pcoLoad($conn));
    usort($rows, fn($a, $b) => strcmp((string) $b['last_at'], (string) $a['last_at']));
    $sum = ['projects' => count($rows), 'contract' => 0.0, 'cost' => 0.0, 'spent' => 0.0, 'over' => 0, 'near' => 0, 'within' => 0, 'no_contract' => 0];
    foreach ($rows as $r) {
        $sum['contract'] += $r['contract'];
        $sum['cost'] += $r['cost'];
        $sum['spent'] += $r['spent'];
        $sum[$r['status']]++;
    }
    foreach (['contract', 'cost', 'spent'] as $k) $sum[$k] = round($sum[$k], 2);
    pcoOut(['success' => true, 'rows' => $rows, 'summary' => $sum]);
}

// ── Buong dokumento (para sa View) ──
// Basa lang. Pangkalahatan: SELECT * ng dokumento + mga item + pirma + attachment.
const PCO_DOCS = [
    'pr'          => ['Purchase Request', 'noblepurchaserequest', 'noblepurchaserequest_items', 'pr_id', 'req_no'],
    'bom'         => ['Bill of Materials', 'noblecrm_bom', 'noblecrm_bom_items', 'bom_id', 'bom_number'],
    'transmittal' => ['Transmittal', 'nobletransmittal', 'nobletransmittal_items', 'transmittal_id', 'ref_no'],
    'dr'          => ['Delivery Receipt', 'noblepc_dr', 'noblepc_dr_item', 'dr_id', 'dr_no'],
    'labor'       => ['Labor Quotation', 'noblepc_laborquote', 'noblepc_laborquote_item', 'quote_id', 'quote_no'],
    'billing'     => ['Labor Billing', 'noblepc_billing', null, null, 'app_no'],
    'payment'     => ['Client Payment', 'noblecrm_deposit_payment', null, null, 'reference_no'],
];
const PCO_MONEY = '/(^|_)(amount|total|total_amount|price|unit_cost|this_period|change_orders|contract_amount|cost|balance|deposit_amount)$/';

function pcoTableExists(mysqli $conn, string $t): bool
{
    static $c = [];
    if (!isset($c[$t])) {
        try { $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'"); $c[$t] = $r && $r->num_rows > 0; }
        catch (Throwable $e) { $c[$t] = false; }
    }
    return $c[$t];
}
// Ligtas na URL ng na-upload na file (relative path lang, walang "..")
function pcoFileUrl($p): ?string
{
    $p = trim((string) ($p ?? ''));
    if ($p === '' || strpos($p, '..') !== false || preg_match('#^[a-z]+:#i', $p) || strpos($p, '\\') !== false) return null;
    return rtrim(BASE_URL, '/') . '/' . ltrim($p, '/');
}
function pcoIsFileCol(string $k): bool
{
    return (bool) preg_match('/(_path|signature|signed_copy|slip|attachment|_file)$/', $k) || strpos($k, 'signature') !== false;
}
function pcoLabel(string $k): string
{
    static $map = ['req_no' => 'PR No.', 'bom_number' => 'BOM No.', 'ref_no' => 'Reference No.', 'dr_no' => 'DR No.', 'quote_no' => 'Quotation No.',
        'app_no' => 'Application No.', 'reference_no' => 'Reference No.', 'total_amount' => 'Total Amount', 'this_period' => 'This Billing',
        'retainage_rate' => 'Retention (%)', 'downpayment_pct' => 'Downpayment (%)', 'paid_on' => 'Date Paid', 'item_no' => '#',
        'item_code' => 'Code', 'item_description' => 'Description', 'item_name' => 'Item', 'unit_cost' => 'Unit Cost', 'qty' => 'Qty', 'quantity' => 'Qty',
        'serial_no' => 'Serial No.', 'section_name' => 'Section', 'section_no' => 'Sec.'];
    return $map[$k] ?? ucwords(str_replace('_', ' ', preg_replace(['/_at$/', '/_path$/'], ['_date', ''], $k)));
}
// Itago ang mga id at teknikal na column
function pcoSkip(string $k): bool
{
    return $k === 'id' || (bool) preg_match('/(_id|_by_id|created_by|password|token|_sort|seq)$/', $k) || pcoIsFileCol($k);
}
function pcoFmt(string $k, $v)
{
    if ($v === null || $v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
    if (preg_match(PCO_MONEY, $k) && is_numeric($v)) return ['money' => round((float) $v, 2)];
    return (string) $v;
}

if ($action === 'doc') {
    $type = (string) ($_GET['type'] ?? '');
    $id = intval($_GET['id'] ?? 0);
    if (!isset(PCO_DOCS[$type]) || $id <= 0) pcoOut(['success' => false, 'message' => 'Invalid document.']);
    [$title, $table, $itemTable, $fk, $noCol] = PCO_DOCS[$type];
    if (!pcoTableExists($conn, $table)) pcoOut(['success' => false, 'message' => 'Document not found.']);
    $row = pcoAll($conn, "SELECT * FROM `$table` WHERE id = ? LIMIT 1", 'i', [$id])[0] ?? null;
    if (!$row) pcoOut(['success' => false, 'message' => 'Document not found.']);

    // Paraan ng bayad (payment)
    if ($type === 'payment' && !empty($row['payment_method_id']) && pcoTableExists($conn, 'noblecrm_paymentmethod')) {
        $pm = pcoAll($conn, "SELECT * FROM noblecrm_paymentmethod WHERE id = ? LIMIT 1", 'i', [(int) $row['payment_method_id']])[0] ?? [];
        foreach (['name', 'method_name', 'method', 'label', 'title'] as $c) if (!empty($pm[$c])) { $row['payment_method'] = $pm[$c]; break; }
    }

    $fields = [];
    $sigs = [];
    $files = [];
    foreach ($row as $k => $v) {
        if (pcoIsFileCol($k)) {
            $url = pcoFileUrl($v);
            if (!$url) continue;
            if (strpos($k, 'signature') !== false) {
                $who = preg_replace('/_?signature(_path)?$/', '', $k);
                if ($who === 'approval') $who = 'approved_by';
                $sigs[] = ['label' => pcoLabel($who), 'name' => (string) ($row[$who] ?? ''), 'title' => (string) ($row[$who . '_title'] ?? ''), 'url' => $url];
            } else {
                $files[] = ['label' => pcoLabel($k), 'url' => $url, 'image' => (bool) preg_match('/\.(png|jpe?g|webp|gif)$/i', (string) $v)];
            }
            continue;
        }
        if (pcoSkip($k)) continue;
        if (preg_match('/^(.+)_title$/', $k, $m) && array_key_exists($m[1], $row)) continue;   // kasama na sa pangalan
        if (array_key_exists($k . '_title', $row) && trim((string) $row[$k . '_title']) !== '' && trim((string) $v) !== '') $v = $v . ' · ' . $row[$k . '_title'];
        $f = pcoFmt($k, $v);
        if ($f !== null) $fields[] = ['key' => $k, 'label' => pcoLabel($k), 'value' => $f];
    }

    $items = ['cols' => [], 'rows' => []];
    if ($itemTable && pcoTableExists($conn, $itemTable)) {
        $order = pcoHasCol($conn, $itemTable, 'item_no') ? 'item_no' : 'id';
        if (pcoHasCol($conn, $itemTable, 'section_no')) $order = 'section_no, ' . $order;
        $its = pcoAll($conn, "SELECT * FROM `$itemTable` WHERE `$fk` = ? ORDER BY $order", 'i', [$id]);
        $cols = [];
        foreach ($its as $it) foreach ($it as $k => $v) {
            if ($k === 'id' || $k === $fk || pcoSkip($k) && !pcoIsFileCol($k)) continue;
            if (pcoIsFileCol($k)) { if (pcoFileUrl($v)) $cols[$k] = true; continue; }
            if ($v !== null && $v !== '') $cols[$k] = true;
        }
        $cols = array_keys($cols);
        $items['cols'] = array_map(fn($k) => ['key' => $k, 'label' => pcoLabel($k), 'money' => (bool) preg_match(PCO_MONEY, $k), 'file' => pcoIsFileCol($k)], $cols);
        foreach ($its as $it) {
            $r = [];
            foreach ($cols as $k) $r[$k] = pcoIsFileCol($k) ? pcoFileUrl($it[$k] ?? null) : (preg_match(PCO_MONEY, $k) && is_numeric($it[$k] ?? null) ? round((float) $it[$k], 2) : ($it[$k] ?? ''));
            $items['rows'][] = $r;
        }
    }

    pcoOut(['success' => true, 'doc' => [
        'type' => $type, 'title' => $title, 'no' => (string) ($row[$noCol] ?? ''), 'status' => (string) ($row['status'] ?? ''),
        'fields' => $fields, 'items' => $items, 'signatures' => $sigs, 'files' => $files,
    ]]);
}

if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) pcoOut(['success' => false, 'message' => 'Invalid project.']);
    $P = pcoLoad($conn, $id, true);
    if (!isset($P[$id])) pcoOut(['success' => false, 'message' => 'Project not found.']);
    pcoOut(['success' => true, 'project' => $P[$id]]);
}

pcoOut(['success' => false, 'message' => 'Unknown action.']);