<?php
// crmlistresetajax.php
// RESET ng isang inquiry (Sales & Market List): binubura ang LAHAT ng nakakabit sa inquiry —
// site visit, spaces, 2D & Quotation (Initial/Final), revisions, feedback, customer review,
// sales quotation, cutting/BOM, purchase request, transmittal, PC project (DR, timeline, billing,
// delivery, turnover, punch list…) at notifications — pati ang mismong files sa uploads/.
// Ang inquiry (client, contact, address, project details, control no.) ay NAIIWAN at bumabalik sa simula.
//
//   GET  ?action=preview&id=<inquiry_id>           → ano ang mabubura (bilang bawat table + files)
//   POST action=reset, id, confirm=<control_no>     → gawin ang reset (hindi na maibabalik)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function rsRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$inquiryId = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if (!in_array($action, ['preview', 'reset'], true)) rsRespond(false, 'Invalid action.');
if ($currentUserId <= 0) rsRespond(false, 'Session expired. Please log in again.');
if ($inquiryId <= 0) rsRespond(false, 'Invalid inquiry.');
if ($action === 'reset' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rsRespond(false, 'Invalid request.');

// ── Inquiry + may-ari ──
$stmt = $conn->prepare("SELECT * FROM noblecrminquiry WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $inquiryId);
$stmt->execute();
$inquiry = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$inquiry) rsRespond(false, 'Inquiry not found.');
if ((int) ($inquiry['sales_staff_id'] ?? 0) !== $currentUserId) {
    rsRespond(false, 'Only the sales staff who filed this inquiry can reset it.');
}

// ═══════════════════════════════════════════════════════════════
//  SCHEMA (ano ang talagang meron sa database na ito)
// ═══════════════════════════════════════════════════════════════
$schema = [];   // table => [column => ['nullable'=>bool,'default'=>mixed,'type'=>string]]
$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
");
while ($c = $res->fetch_assoc()) {
    $schema[$c['TABLE_NAME']][$c['COLUMN_NAME']] = [
        'nullable' => $c['IS_NULLABLE'] === 'YES',
        'default' => $c['COLUMN_DEFAULT'],
        'type' => strtolower($c['DATA_TYPE']),
    ];
}
$has = fn(string $t, ?string $col = null) => isset($schema[$t]) && ($col === null || isset($schema[$t][$col]));
$safeName = fn(string $n) => (bool) preg_match('/^[A-Za-z0-9_]+$/', $n);

// ═══════════════════════════════════════════════════════════════
//  CASCADE: parent table ← (child table, column)
//  Kilalang ugnayan + awtomatikong hanap ayon sa pangalan ng column.
// ═══════════════════════════════════════════════════════════════
$edges = [];   // [child, column, parent]
$addEdge = function (string $child, string $col, string $parent) use (&$edges, $has) {
    if ($child !== $parent && $has($child, $col) && $has($parent, 'id')) {
        $edges[$child . '|' . $col . '|' . $parent] = [$child, $col, $parent];
    }
};

// 2D & Quotation (Initial) — quotation_id dito = noblecrm_2dquotation.id
foreach (['noblecrm_2dquotation_files', 'noblecrm_2dquotation_customer_review', 'noblecrm_2d_feedback', 'noblecrm_2d_revision', 'noblecrm_cuttinglistprogression', 'noblecrm_bom'] as $t) {
    $addEdge($t, 'quotation_id', 'noblecrm_2dquotation');
}
// Sales quotation builder — quotation_id dito = noblecrm_quotations.id (IBA sa 2D!)
$addEdge('noblecrm_quotation_sections', 'quotation_id', 'noblecrm_quotations');
$addEdge('noblecrm_quotation_settings', 'quotation_id', 'noblecrm_quotations');
$addEdge('noblecrm_quotation_items', 'quotation_id', 'noblecrm_quotations');

// Awtomatiko ayon sa column (para masama ang mga table na hindi ko pa nakikita)
$byColumn = [
    'sitevisit_id' => ['noblecrm_sitevisit', null],
    'revision_id' => ['noblecrm_2d_revision', 'noblecrm_2d'],
    'final_id' => ['noblecrm_2dquotation_final', 'noblecrm_2d'],
    'section_id' => ['noblecrm_quotation_sections', 'noblecrm_quotation'],
    'bom_id' => ['noblecrm_bom', null],
    'pr_id' => ['noblepurchaserequest', null],
    'transmittal_id' => ['nobletransmittal', null],
    'dr_id' => ['noblepc_dr', 'noblepc_'],
    'project_id' => ['noblepc_project', 'noblepc_'],
    'delivery_id' => ['noblepc_delivery', 'noblepc_'],
    'timeline_id' => ['noblepc_timeline', 'noblepc_'],
    'subcon_quote_id' => ['noblepc_subcon_quote', 'noblepc_'],
    'laborquote_id' => ['noblepc_laborquote', 'noblepc_'],
];
foreach ($schema as $t => $cols) {
    foreach ($byColumn as $col => [$parent, $prefix]) {
        if (isset($cols[$col]) && ($prefix === null || str_starts_with($t, $prefix))) {
            $addEdge($t, $col, $parent);
        }
    }
}

// Ugat: lahat ng table na may inquiry_id (maliban sa inquiry mismo)
$roots = [];
foreach ($schema as $t => $cols) {
    if (isset($cols['inquiry_id']) && $t !== 'noblecrminquiry' && $safeName($t)) $roots[] = $t;
}

// ═══════════════════════════════════════════════════════════════
//  KOLEKTAHIN ang mga row na mabubura (walang binubura dito)
// ═══════════════════════════════════════════════════════════════
$rowsByTable = [];   // table => [id|key => row]
$order = [];         // pagkakasunod ng pagkakita (parent bago child)

$collect = function (string $table, string $col, array $values) use ($conn, &$rowsByTable, &$order, $safeName): array {
    if (!$values || !$safeName($table) || !$safeName($col)) return [];
    $in = implode(',', array_map('intval', $values));
    $res = $conn->query("SELECT * FROM `{$table}` WHERE `{$col}` IN ({$in})");
    $newIds = [];
    while ($r = $res->fetch_assoc()) {
        $key = isset($r['id']) ? 'id:' . $r['id'] : 'row:' . md5(json_encode($r));
        if (isset($rowsByTable[$table][$key])) continue;
        $rowsByTable[$table][$key] = $r;
        if (isset($r['id'])) $newIds[] = (int) $r['id'];
    }
    if (!in_array($table, $order, true) && !empty($rowsByTable[$table])) $order[] = $table;
    return $newIds;
};

$queue = [];
foreach ($roots as $t) {
    $ids = $collect($t, 'inquiry_id', [$inquiryId]);
    if ($ids) $queue[] = [$t, $ids];
}
// PC: may mga DR/punch list na naka-inquiry_id; sundan din ang mga anak nila
$guard = 0;
while ($queue && $guard++ < 500) {
    [$parent, $ids] = array_shift($queue);
    foreach ($edges as [$child, $col, $p]) {
        if ($p !== $parent) continue;
        $newIds = $collect($child, $col, $ids);
        if ($newIds) $queue[] = [$child, $newIds];
    }
}

// Notifications ng CRM para sa inquiry na ito
$notifCount = 0;
if ($has('noblenotification', 'request_id')) {
    $typeCol = $has('noblenotification', 'type') ? 'type' : null;
    $sql = "SELECT COUNT(*) AS c FROM noblenotification WHERE request_id = ?"
        . ($typeCol ? " AND (`type` LIKE 'crm%' OR `type` LIKE '%2d%' OR `type` LIKE '%sitevisit%')" : " AND 1 = 0");
    $st = $conn->prepare($sql);
    $st->bind_param("i", $inquiryId);
    $st->execute();
    $notifCount = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
}

// ── Files sa uploads/ na nakasulat sa mga row (pati contract file ng inquiry) ──
// Tinatanggap ang "uploads/x.pdf", "admin/.../uploads/x.pdf" o buong URL; dapat nasa loob ng ROOT_PATH
// at may "/uploads/" sa path — kaya hindi kailanman mabubura ang code o ibang file.
$rootReal = realpath(ROOT_PATH);
$baseUrlPath = trim((string) parse_url((string) BASE_URL, PHP_URL_PATH), '/');
$files = [];
$scanValue = function ($v) use (&$files) {
    if (!is_string($v) || stripos($v, 'uploads/') === false) return;
    foreach (preg_split('/[,;\n]+/', $v) as $part) {
        $part = trim($part);
        if ($part !== '' && stripos($part, 'uploads/') !== false) $files[$part] = true;
    }
};
// Hindi kasama ang mga signature/logo/avatar — pag-aari ng user, ginagamit din sa ibang record
$skipCol = fn(string $col) => (bool) preg_match('/signature|avatar|logo|profile/i', $col);
foreach ($rowsByTable as $rows) foreach ($rows as $r) foreach ($r as $col => $v) if (!$skipCol((string) $col)) $scanValue($v);
foreach (['contract_file', 'contract_path', 'contract_attachment'] as $c) $scanValue($inquiry[$c] ?? null);

$existingFiles = [];
if ($rootReal) {
    foreach (array_keys($files) as $raw) {
        $path = (string) (parse_url($raw, PHP_URL_PATH) ?? $raw);      // tanggalin ang http://host at ?query
        $path = ltrim(rawurldecode($path), '/');
        if ($baseUrlPath !== '' && str_starts_with($path, $baseUrlPath . '/')) $path = substr($path, strlen($baseUrlPath) + 1);
        $candidates = [$path];
        $pos = stripos($path, 'uploads/');
        if ($pos !== false && $pos > 0) $candidates[] = substr($path, $pos);
        foreach ($candidates as $rel) {
            if (str_contains($rel, '..')) continue;
            $abs = realpath($rootReal . '/' . $rel);
            if ($abs && is_file($abs)
                && str_starts_with($abs, $rootReal . DIRECTORY_SEPARATOR)
                && stripos(str_replace('\\', '/', $abs), '/uploads/') !== false
                && !preg_match('/\.(php\d?|phtml|htaccess)$/i', $abs)) {
                $existingFiles[$abs] = true;
                break;
            }
        }
    }
}
$existingFiles = array_keys($existingFiles);

// Huwag burahin ang file kung may IBANG record (hindi kasama sa reset) na gumagamit pa rito
if ($existingFiles) {
    $stillUsed = '';
    foreach ($order as $t) {
        $textCols = array_keys(array_filter($schema[$t], fn($m) => in_array($m['type'], ['varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'char'], true)));
        $textCols = array_values(array_filter($textCols, $safeName));
        if (!$textCols || !isset($schema[$t]['id'])) continue;
        $like = implode(' OR ', array_map(fn($c) => "`{$c}` LIKE '%uploads/%'", $textCols));
        $ids = array_map(fn($r) => (int) $r['id'], array_values($rowsByTable[$t]));
        $notIn = $ids ? ' AND id NOT IN (' . implode(',', $ids) . ')' : '';
        $res = $conn->query("SELECT `" . implode('`,`', $textCols) . "` FROM `{$t}` WHERE ({$like}){$notIn}");
        while ($r = $res->fetch_row()) $stillUsed .= "\n" . implode("\n", array_map('strval', $r));
    }
    if ($stillUsed !== '') {
        $existingFiles = array_values(array_filter($existingFiles, function ($abs) use ($rootReal, $stillUsed) {
            $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($rootReal))), '/');
            return stripos($stillUsed, basename($rel)) === false;
        }));
    }
}

// ── Mga label para sa preview ──
$labels = [
    'noblecrm_sitevisit' => 'Site visit records',
    'noblecrm_sitevisit_space' => 'Site visit spaces',
    'noblecrm_2dquotation' => '2D & Quotation (Initial) entries',
    'noblecrm_2dquotation_files' => '2D & Quotation files',
    'noblecrm_2dquotation_customer_review' => 'Customer review decisions',
    'noblecrm_2dquotation_final' => 'Final 2D & Quotation entries',
    'noblecrm_2d_feedback' => 'Cutting feedback',
    'noblecrm_2d_revision' => '2D revisions',
    'noblecrm_2d_revision_file' => '2D revision files',
    'noblecrm_cuttinglistprogression' => 'Cutting progress',
    'noblecrm_bom' => 'Bills of materials (BOM)',
    'noblecrm_bom_items' => 'BOM items',
    'noblecrm_quotations' => 'Sales quotations',
    'noblecrm_quotation_sections' => 'Sales quotation sections',
    'noblecrm_quotation_items' => 'Sales quotation items',
    'noblepurchaserequest' => 'Purchase requests',
    'noblepurchaserequest_items' => 'Purchase request items',
    'nobletransmittal' => 'Transmittals',
    'nobletransmittal_items' => 'Transmittal items',
    'noblepc_project' => 'PC projects',
    'noblepc_dr' => 'Delivery receipts',
    'noblepc_dr_item' => 'Delivery receipt items',
    'noblepc_delivery' => 'Deliveries',
    'noblepc_delivery_item' => 'Delivery items',
    'noblepc_timeline' => 'Timeline entries',
    'noblepc_timeline_task' => 'Timeline tasks',
    'noblepc_billing' => 'Billing records',
    'noblepc_turnover' => 'Turnover letters',
    'noblepc_punch' => 'Punch list items',
    'noblepc_permit' => 'Permits',
    'noblepc_step' => 'Project steps',
    'noblepc_subcon_quote' => 'Subcon quotes',
    'noblepc_subcon_quote_item' => 'Subcon quote items',
    'noblepc_laborquote' => 'Labor quotations',
    'noblepc_laborquote_item' => 'Labor quotation items',
    'noblepc_clientlog' => 'Client logs',
    'noblepc_attendance' => 'Attendance records',
];
$groups = [];
foreach ($order as $t) {
    $groups[] = ['table' => $t, 'label' => $labels[$t] ?? $t, 'rows' => count($rowsByTable[$t])];
}
if ($notifCount) $groups[] = ['table' => 'noblenotification', 'label' => 'CRM notifications', 'rows' => $notifCount];

if ($action === 'preview') {
    rsRespond(true, '', [
        'control_no' => $inquiry['control_no'],
        'client_name' => $inquiry['client_name'],
        'groups' => $groups,
        'total_rows' => array_sum(array_column($groups, 'rows')),
        'files' => count($existingFiles),
    ]);
}

// ═══════════════════════════════════════════════════════════════
//  RESET (hindi na maibabalik)
// ═══════════════════════════════════════════════════════════════
$confirm = trim((string) ($_POST['confirm'] ?? ''));
if ($confirm === '' || strcasecmp($confirm, (string) $inquiry['control_no']) !== 0) {
    rsRespond(false, 'Type the control number exactly to confirm the reset.');
}

$conn->begin_transaction();
try {
    // Anak muna bago magulang
    foreach (array_reverse($order) as $t) {
        $rows = $rowsByTable[$t];
        if (isset($schema[$t]['id'])) {
            $ids = array_map(fn($r) => (int) $r['id'], array_values($rows));
            foreach (array_chunk($ids, 500) as $chunk) {
                $conn->query("DELETE FROM `{$t}` WHERE id IN (" . implode(',', $chunk) . ")");
            }
        } else {
            // walang id column → burahin ayon sa parehong kondisyon na pinanggalingan
            foreach ($edges as [$child, $col, $parent]) {
                if ($child !== $t || empty($rowsByTable[$parent])) continue;
                $pids = array_filter(array_map(fn($r) => (int) ($r['id'] ?? 0), $rowsByTable[$parent]));
                if ($pids) $conn->query("DELETE FROM `{$t}` WHERE `{$col}` IN (" . implode(',', $pids) . ")");
            }
            if (isset($schema[$t]['inquiry_id'])) {
                $conn->query("DELETE FROM `{$t}` WHERE inquiry_id = " . (int) $inquiryId);
            }
        }
    }

    if ($notifCount) {
        $st = $conn->prepare("DELETE FROM noblenotification WHERE request_id = ? AND (`type` LIKE 'crm%' OR `type` LIKE '%2d%' OR `type` LIKE '%sitevisit%')");
        $st->bind_param("i", $inquiryId);
        $st->execute();
        $st->close();
    }

    // Ibalik ang inquiry sa simula ("To schedule"); naiiwan ang client/project details
    $resetCols = [
        'designer_id' => null, 'measurement_datetime' => null, 'status' => 'Pending',
        'client_status' => null, 'statusdesignersitevisit' => null, 'clientstatus' => null,
        'deadline' => null, 'design_progress' => '0',
        'design_confirmed' => 0, 'design_confirmed_at' => null, 'design_confirmed_by' => null,
        'contract_amount' => null, 'contract_file' => null,
    ];
    $sets = [];
    foreach ($resetCols as $col => $val) {
        if (!isset($schema['noblecrminquiry'][$col])) continue;
        $meta = $schema['noblecrminquiry'][$col];
        if ($val === null && !$meta['nullable']) {
            // hindi puwedeng NULL → default ng column, o 0 / '' depende sa type
            $val = $meta['default'] ?? (in_array($meta['type'], ['int', 'tinyint', 'smallint', 'bigint', 'decimal', 'float', 'double'], true) ? 0 : '');
            if (is_string($val) && strtoupper($val) === 'NULL') $val = '';
        }
        $sets[] = "`{$col}` = " . ($val === null ? 'NULL' : "'" . $conn->real_escape_string((string) $val) . "'");
    }
    if ($sets) {
        $conn->query("UPDATE noblecrminquiry SET " . implode(', ', $sets) . " WHERE id = " . (int) $inquiryId);
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    rsRespond(false, 'Reset failed — nothing was deleted. (' . $e->getMessage() . ')');
}

// Files: pagkatapos lang ma-commit (kapag pumalya ang DB, buo pa rin ang files)
$deleted = 0; $failed = 0;
foreach ($existingFiles as $abs) {
    if (@unlink($abs)) $deleted++; else $failed++;
}

rsRespond(true, 'Inquiry reset.', [
    'deleted_rows' => array_sum(array_column($groups, 'rows')),
    'deleted_files' => $deleted,
    'failed_files' => $failed,
    'warning' => $failed ? "{$failed} file(s) could not be deleted from the server." : null,
]);