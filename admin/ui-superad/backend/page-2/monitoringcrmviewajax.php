<?php
// monitoringcrmviewajax.php — backend ng monitoringcrmview.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

function mvRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Kunin ang unang may laman na column sa listahan (para hindi mag-fatal kapag iba ang pangalan ng column).
function mvPick(array $row, array $keys, $default = null)
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return $default;
}

function mvFetchAll(mysqli $conn, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Accepts JSON array, array, o text na pinaghiwalay ng newline / comma / pipe.
function mvList($value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    if (is_array($value)) {
        $items = $value;
    } else {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        if ($value[0] === '[') {
            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : [];
        } else {
            $items = preg_split('/[\r\n,|]+/', $value);
        }
    }
    $out = [];
    foreach ($items as $it) {
        $it = trim((string) $it);
        if ($it !== '') {
            $out[] = $it;
        }
    }
    return $out;
}

// Relative path → buong URL. Kung full path/URL na ang nakasave, hindi ginagalaw.
function mvUrl($path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^(https?:)?//#i', $path) || $path[0] === '/') {
        return $path;
    }
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function mvUrls(array $paths): array
{
    return array_values(array_filter(array_map('mvUrl', $paths)));
}

// id => name ng lahat ng staff (sales, designer, uploader, confirmer)
function mvNameMap(mysqli $conn): array
{
    $map = [];
    foreach (mvFetchAll($conn, "SELECT id, name FROM noblerole") as $r) {
        $map[(int) $r['id']] = $r['name'];
    }
    return $map;
}

// Isang submission (Initial o Final) → flat na shape na hinihingi ng set-4quotationhistory.php
function mvBuildCycle(array $q, string $stage, int $no, int $total, array $names): array
{
    $out = [
        'id'           => (int) $q['id'],
        'stage'        => $stage,
        'stage_no'     => $no,
        'stage_total'  => $total,
        'submitted_at' => mvPick($q, ['submitted_at', 'created_at']),
        'reviewed_at'  => mvPick($q, ['reviewed_at']),
    ];

    foreach (['design_2d', 'quotation', 'design_3d'] as $p) {
        $by = (int) mvPick($q, [$p . '_uploaded_by', $p . '_uploader_id'], 0);

        // Per-file review status; kung walang column, ang status ng buong submission (Approved) ang gamit.
        // Ang 3D ay may sariling stage (Locked / Waiting for Approval / For Revision / Approved).
        $reviewKeys = $p === 'design_3d'
            ? ['design_3d_review_status', 'design_3d_stage']
            : [$p . '_review_status'];

        $out[$p . '_path']            = mvUrl(mvPick($q, [$p . '_path', $p . '_file']));
        $out[$p . '_uploader_name']   = $names[$by] ?? '—';
        $out[$p . '_uploaded_role']   = mvPick($q, [$p . '_uploaded_role'], '—');
        $out[$p . '_uploaded_at']     = mvPick($q, [$p . '_uploaded_at']);
        $out[$p . '_review_status']   = mvPick($q, $reviewKeys, $p === 'design_3d' ? 'Pending' : ($q['status'] ?? 'Pending'));
        $out[$p . '_remarks']         = mvPick($q, [$p . '_remarks']);
    }
    $out['design_3d_reviewed_at'] = mvPick($q, ['design_3d_reviewed_at']);

    return $out;
}


// ── Production → Purchasing → Delivery (pagkatapos ng Final Approval) ──
// Bawat bahagi ay may sariling try/catch: kapag wala pa ang table, walang laman lang, hindi sira ang page.
function mvSafe(callable $fn): array
{
    try {
        return $fn();
    } catch (Throwable $e) {
        error_log('monitoringcrmviewajax production: ' . $e->getMessage());
        return [];
    }
}
function mvInts(array $rows, string $key): array
{
    return array_values(array_unique(array_map('intval', array_column($rows, $key))));
}
// ── Operations (Project Coordinator): DR approval → Foreman → Labor → Timeline → Billing → Punch list → Turnover ──
// Bawat DR ng inquiry (transmittal → PR → BOM → 2D quotation). Bawat table may sariling mvSafe.
function mvOperations(mysqli $conn, int $inquiryId): array
{
    $drs = mvSafe(fn() => mvFetchAll($conn,
        "SELECT d.* FROM noblepc_dr d
         JOIN nobletransmittal t ON t.id = d.transmittal_id
         JOIN noblepurchaserequest p ON p.id = t.pr_id
         JOIN noblecrm_bom b ON b.id = p.bom_id
         JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
         WHERE q.inquiry_id = ? ORDER BY d.id ASC", [$inquiryId], 'i'));
    if (!$drs) {
        return [];
    }
    $ids = implode(',', mvInts($drs, 'id'));
    $byDr = function (array $rows): array {
        $m = [];
        foreach ($rows as $r) {
            $m[(int) $r['dr_id']][] = $r;
        }
        return $m;
    };

    $foremen   = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_foreman WHERE dr_id IN ($ids)")));
    $lqs       = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_laborquote WHERE dr_id IN ($ids)")));
    $lqIds     = [];
    foreach ($lqs as $list) { foreach ($list as $q) { $lqIds[] = (int) $q['id']; } }
    $lqSec     = $lqIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT quote_id, section_name, COUNT(*) AS n FROM noblepc_laborquote_item WHERE quote_id IN (" . implode(',', $lqIds) . ") GROUP BY quote_id, section_no, section_name ORDER BY section_no")) : [];
    $timelines = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_timeline WHERE dr_id IN ($ids)")));
    $tlIds     = [];
    foreach ($timelines as $list) { foreach ($list as $x) { $tlIds[] = (int) $x['id']; } }
    $tasks     = $tlIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT timeline_id, phase_name, task, start_date, end_date, duration FROM noblepc_timeline_task WHERE timeline_id IN (" . implode(',', $tlIds) . ") ORDER BY phase_no, task_no")) : [];
    $billings  = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_billing WHERE dr_id IN ($ids) ORDER BY seq ASC")));
    $punch     = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT dr_id, status, COUNT(*) AS n FROM noblepc_punch WHERE dr_id IN ($ids) GROUP BY dr_id, status")));
    $turnovers = $byDr(mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_turnover WHERE dr_id IN ($ids)")));

    $today = date('Y-m-d');
    $out = [];
    foreach ($drs as $d) {
        $did = (int) $d['id'];

        $f = $foremen[$did][0] ?? null;
        $q = $lqs[$did][0] ?? null;
        $tl = $timelines[$did][0] ?? null;
        $to = $turnovers[$did][0] ?? null;

        // Timeline: progress = tasks na tapos na ang end_date
        $tlTasks = $tl ? array_values(array_filter($tasks, fn($k) => (int) $k['timeline_id'] === (int) $tl['id'])) : [];
        $done = count(array_filter($tlTasks, fn($k) => $k['end_date'] < $today));

        // Billing totals (approved lang ang bilang na "billed")
        $bl = $billings[$did] ?? [];
        $last = $bl ? end($bl) : null;
        $contract = $last ? (float) $last['contract_amount'] + (float) $last['change_orders'] : 0.0;
        $billed = 0.0;
        $rows = [];
        foreach ($bl as $b) {
            if ($b['status'] === 'approved') {
                $billed += (float) $b['this_period'];
            }
            $rows[] = [
                'ref_no'       => $b['ref_no'],
                'label'        => $b['billing_type'] === 'dp' ? 'Downpayment' : 'Application #' . (int) $b['app_no'],
                'billing_date' => $b['billing_date'],
                'this_period'  => (float) $b['this_period'],
                'status'       => $b['status'],
                'head_by'      => $b['head_by'],
                'head_at'      => $b['head_at'],
                'sa_by'        => $b['sa_by'],
                'sa_at'        => $b['sa_at'],
                'return_remarks' => $b['return_remarks'],
            ];
        }
        $rate = $last ? (float) $last['retainage_rate'] : 0.0;

        $pc = ['open' => 0, 'fixed' => 0, 'verified' => 0];
        foreach ($punch[$did] ?? [] as $r) {
            $pc[$r['status']] = (int) $r['n'];
        }

        $out[] = [
            'dr_no'           => $d['dr_no'],
            'created_at'      => $d['created_at'],
            'prepared_by'     => $d['prepared_by'] ?? null,
            'dr_status'       => $d['status'] ?? null,
            'permit_required' => isset($d['permit_required']) ? ($d['permit_required'] === null ? null : (int) $d['permit_required']) : null,
            'dr_approved_by'  => $d['approved_by'] ?? null,
            'dr_approved_at'  => $d['approved_at'] ?? null,
            'dr_return_remarks' => $d['return_remarks'] ?? null,
            'foreman' => $f ? [
                'name'       => $f['name'],
                'contact_no' => $f['contact_no'],
                'id_type'    => $f['id_type'],
                'id_number'  => $f['id_number'],
                'id_photo'   => mvUrl($f['id_photo']),
                'created_at' => $f['created_at'],
            ] : null,
            'labor' => $q ? [
                'quote_no'      => $q['quote_no'],
                'subcontractor' => $q['subcontractor'],
                'total'         => (float) $q['total'],
                'duration_days' => $q['duration_days'] !== null ? (int) $q['duration_days'] : null,
                'status'        => $q['status'],
                'submitted_at'  => $q['submitted_at'],
                'approved_by'   => $q['approved_by'],
                'approved_at'   => $q['approved_at'],
                'return_remarks' => $q['return_remarks'],
                'sections'      => array_values(array_map(fn($s) => $s['section_name'],
                                    array_filter($lqSec, fn($s) => (int) $s['quote_id'] === (int) $q['id']))),
            ] : null,
            'timeline' => $tl ? [
                'date_started' => $tl['date_started'],
                'date_finish'  => $tl['date_finish'],
                'updated_at'   => $tl['updated_at'] ?: $tl['created_at'],
                'task_count'   => count($tlTasks),
                'done'         => $done,
                'phases'       => array_values(array_unique(array_column($tlTasks, 'phase_name'))),
            ] : null,
            'billing' => $bl ? [
                'contract'  => $contract,
                'billed'    => $billed,
                'balance'   => max(0, $contract - $billed),
                'retainage_rate' => $rate,
                'complete'  => $contract > 0 && $billed + 0.005 >= $contract,
                'rows'      => $rows,
            ] : null,
            'punch' => ($pc['open'] + $pc['fixed'] + $pc['verified']) ? $pc : null,
            'turnover' => $to ? [
                'status'        => $to['status'],
                'letter_date'   => $to['letter_date'],
                'client_name'   => $to['client_name'],
                'submitted_at'  => $to['submitted_at'],
                'sa_by'         => $to['sa_by'],
                'sa_at'         => $to['sa_at'],
                'return_remarks' => $to['return_remarks'],
                'accepted_date' => $to['accepted_date'],
                'client_signed' => (int) $to['client_signed'] === 1,
                'signed_copy'   => mvUrl($to['signed_copy']),
                'closed_by'     => $to['closed_by'],
                'closed_at'     => $to['closed_at'],
            ] : null,
        ];
    }
    return $out;
}

function mvProduction(mysqli $conn, int $inquiryId, array $names): array
{
    $qIds = mvSafe(fn() => mvInts(mvFetchAll($conn, "SELECT id FROM noblecrm_2dquotation WHERE inquiry_id = ?", [$inquiryId], 'i'), 'id'));
    $out = ['deposit' => null, 'cutting' => [], 'boms' => [], 'prs' => [], 'transmittals' => [], 'drs' => []];
    if (!$qIds) return $out;
    $qIn = implode(',', $qIds);   // int lang, ligtas

    // 0. Deposit & Notice to Proceed (Accounting) — galing sa pinakabagong 2D & Quotation
    $out['deposit'] = mvSafe(function () use ($conn, $qIn, $names) {
        $rows = mvFetchAll($conn, "
            SELECT q.*, pm.name AS payment_method_name
            FROM noblecrm_2dquotation q
            LEFT JOIN noblecrm_paymentmethod pm ON pm.id = q.deposit_payment_method_id
            WHERE q.id IN ($qIn)
            ORDER BY (q.deposit_status = 'Notice to Proceed') DESC, q.id DESC LIMIT 1");
        $q = $rows[0] ?? null;
        if (!$q) return [];
        return [
            'status'         => $q['deposit_status'] ?: 'Hold',
            'amount'         => $q['deposit_amount'] ?? null,
            'payment_method' => $q['payment_method_name'] ?? null,
            'slip'           => mvUrl($q['deposit_slip_path'] ?? ''),
            'logged_by'      => $names[(int) ($q['deposit_uploaded_by'] ?? 0)] ?? null,
            'logged_at'      => $q['deposit_uploaded_at'] ?? null,
        ];
    }) ?: null;

    // 1. Cutting list
    $out['cutting'] = mvSafe(function () use ($conn, $qIn, $names) {
        $list = [];
        foreach (mvFetchAll($conn, "SELECT * FROM noblecrm_cuttinglistprogression WHERE quotation_id IN ($qIn) ORDER BY id ASC") as $c) {
            $list[] = [
                'id'                => (int) $c['id'],
                'status'            => $c['status'] ?? null,
                'uploaded_at'       => mvPick($c, ['created_at', 'uploaded_at']),
                'uploaded_by'       => $names[(int) ($c['uploaded_by'] ?? 0)] ?? null,
                'photos'            => mvUrls(mvList($c['photos'] ?? '')),
                'archive'           => mvUrl($c['archive_path'] ?? ''),
                'archive_name'      => $c['archive_original_name'] ?? null,
                'qr_approved'       => (int) ($c['qr_approved'] ?? 0) === 1,
                'approved_at'       => $c['approved_at'] ?? null,
                'approved_by'       => $names[(int) ($c['approved_by'] ?? 0)] ?? null,
                'qr_rejected'       => (int) ($c['qr_rejected'] ?? 0) === 1,
                'rejected_at'       => $c['rejected_at'] ?? null,
                'rejected_by'       => $names[(int) ($c['rejected_by'] ?? 0)] ?? null,
                'rejection_remarks' => $c['rejection_remarks'] ?? null,
            ];
        }
        return $list;
    });

    // 2. BOM (hindi kasama ang TEMP na hindi pa na-save)
    $boms = mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblecrm_bom WHERE quotation_id IN ($qIn) AND bom_number NOT LIKE 'TEMP-%' ORDER BY id ASC"));
    $bomIds = mvInts($boms, 'id');
    $bomItems = $bomIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblecrm_bom_items WHERE bom_id IN (" . implode(',', $bomIds) . ") ORDER BY item_no ASC")) : [];
    foreach ($boms as $b) {
        $out['boms'][] = [
            'id' => (int) $b['id'], 'bom_number' => $b['bom_number'], 'date_submitted' => $b['date_submitted'] ?? null,
            'date_needed' => $b['date_needed'] ?? null, 'general_scope' => $b['general_scope'] ?? null, 'created_at' => $b['created_at'] ?? null,
            'requested_by' => $b['requested_by'] ?? null, 'approved_by' => $b['approved_by'] ?? null, 'approved_at' => $b['approved_at'] ?? null,
            'received_by' => $b['received_by'] ?? null, 'received_at' => $b['received_at'] ?? null,
            'items' => array_values(array_filter($bomItems, fn($i) => (int) $i['bom_id'] === (int) $b['id'])),
        ];
    }
    if (!$bomIds) return $out;

    // 3. Purchase request
    $prs = mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepurchaserequest WHERE bom_id IN (" . implode(',', $bomIds) . ") ORDER BY id ASC"));
    $prIds = mvInts($prs, 'id');
    $prItems = $prIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT pr_id, item_name, quantity, unit, price, amount FROM noblepurchaserequest_items WHERE pr_id IN (" . implode(',', $prIds) . ") ORDER BY item_no ASC")) : [];
    foreach ($prs as $p) {
        $keep = ['req_no', 'status', 'total_amount', 'request_date', 'date_needed', 'created_at', 'prepared_by', 'approved_by', 'approved_at',
                 'rejected_at', 'rejection_remarks', 'reviewed_by', 'reviewed_at', 'staff_received_by', 'staff_received_at',
                 'released_by', 'released_at', 'paid_at'];
        $row = ['id' => (int) $p['id']];
        foreach ($keep as $k) $row[$k] = $p[$k] ?? null;
        $row['items'] = array_values(array_filter($prItems, fn($i) => (int) $i['pr_id'] === (int) $p['id']));
        $out['prs'][] = $row;
    }
    if (!$prIds) return $out;

    // 4. Transmittal
    $trs = mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM nobletransmittal WHERE pr_id IN (" . implode(',', $prIds) . ") ORDER BY id ASC"));
    $trIds = mvInts($trs, 'id');
    $trItems = $trIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT transmittal_id, serial_no, description, quantity FROM nobletransmittal_items WHERE transmittal_id IN (" . implode(',', $trIds) . ") ORDER BY item_no ASC")) : [];
    foreach ($trs as $t) {
        $out['transmittals'][] = [
            'id' => (int) $t['id'], 'ref_no' => $t['ref_no'], 'transmittal_date' => $t['transmittal_date'] ?? null, 'created_at' => $t['created_at'] ?? null,
            'project_name' => $t['project_name'] ?? null, 'particulars' => $t['particulars'] ?? null, 'delivery_mode' => $t['delivery_mode'] ?? null,
            'issued_by' => $t['issued_by'] ?? null, 'received_by' => $t['received_by'] ?? null, 'delivered_at' => $t['delivered_at'] ?? null,
            'items' => array_values(array_filter($trItems, fn($i) => (int) $i['transmittal_id'] === (int) $t['id'])),
        ];
    }
    if (!$trIds) return $out;

    // 5. Delivery receipt (Project Coordinator)
    $drs = mvSafe(fn() => mvFetchAll($conn, "SELECT * FROM noblepc_dr WHERE transmittal_id IN (" . implode(',', $trIds) . ") ORDER BY id ASC"));
    $drIds = mvInts($drs, 'id');
    $drItems = $drIds ? mvSafe(fn() => mvFetchAll($conn, "SELECT dr_id, particular, size, qty, unit, amount FROM noblepc_dr_item WHERE dr_id IN (" . implode(',', $drIds) . ") ORDER BY item_no ASC")) : [];
    foreach ($drs as $d) {
        $out['drs'][] = [
            'id' => (int) $d['id'], 'dr_no' => $d['dr_no'], 'dr_date' => $d['dr_date'] ?? null, 'scheduled_date' => $d['scheduled_date'] ?? null,
            'prepared_by' => $d['prepared_by'] ?? null, 'created_at' => $d['created_at'] ?? null,
            'items' => array_values(array_filter($drItems, fn($i) => (int) $i['dr_id'] === (int) $d['id'])),
        ];
    }
    return $out;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'timeline') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        mvRespond(false, 'Invalid inquiry.');
    }

    try {
        $inq = mvFetchAll($conn, "SELECT * FROM noblecrminquiry WHERE id = ? LIMIT 1", [$id], 'i')[0] ?? null;
        if (!$inq) {
            mvRespond(false, 'Inquiry not found.');
        }

        $names = mvNameMap($conn);

        // ── Header ──
        $inquiry = [
            'id'                   => (int) $inq['id'],
            'control_no'           => $inq['control_no'],
            'client_name'          => $inq['client_name'],
            'created_at'           => $inq['created_at'],
            'branch'               => mvPick($inq, ['branch', 'branch_name']),
            'address'              => mvPick($inq, ['address', 'client_address']),
            'contact_number'       => $inq['contact_number'] ?? null,
            'project_type'         => $inq['project_type'] ?? null,
            'project_scope'        => mvPick($inq, ['project_scope', 'scope_of_project', 'scope']),
            'measuring_space'      => mvPick($inq, ['measuring_space', 'space_to_measure']),
            'measurement_datetime' => mvPick($inq, ['measurement_datetime']),
            'sales_staff_name'     => $names[(int) ($inq['sales_staff_id'] ?? 0)] ?? null,
            'designer_name'        => $names[(int) ($inq['designer_id'] ?? 0)] ?? null,
            'contract_amount'      => $inq['contract_amount'] ?? null,
        ];

        // ── Site visits (oldest → newest; ang page ang nagre-reverse) ──
        $siteVisits = [];
        $svRows = mvFetchAll(
            $conn,
            "SELECT * FROM noblecrm_sitevisit WHERE inquiry_id = ? ORDER BY created_at ASC, id ASC",
            [$id],
            'i'
        );
        foreach ($svRows as $sv) {
            $designerId = (int) mvPick($sv, ['designer_id', 'created_by'], $inq['designer_id'] ?? 0);
            $siteVisits[] = [
                'id'                 => (int) $sv['id'],
                'created_at'         => $sv['created_at'],
                'designer_name'      => $names[$designerId] ?? '—',
                'visited'            => (($sv['visited'] ?? 'yes') !== 'no'),
                'address'            => mvPick($sv, ['address', 'site_address'], $inquiry['address']),
                'visit_datetime'     => mvPick($sv, ['visit_datetime', 'visit_date', 'visited_at']),
                'measurements'       => mvPick($sv, ['measurements']),
                'measurement_files'  => mvUrls(mvList(mvPick($sv, ['measurement_files', 'measurement_pdfs']))),
                'site_conditions'    => mvPick($sv, ['site_conditions', 'site_notes']),
                'client_requirements' => mvPick($sv, ['client_requirements']),
                'existing_structure' => mvPick($sv, ['existing_structure']),
                'photos'             => mvUrls(mvList(mvPick($sv, ['photos', 'photo_paths', 'photographs']))),
            ];
        }

        // ── Design progress / Client Review & Approval ──
        $confirmedBy = (int) mvPick($inq, ['design_confirmed_by', 'confirmed_by'], 0);
        $designProgress = [
            'progress'          => (int) ($inq['design_progress'] ?? 0),
            'confirmed'         => !empty($inq['design_confirmed']),
            'confirmed_at'      => mvPick($inq, ['design_confirmed_at', 'confirmed_at']),
            'confirmed_by_name' => $names[$confirmedBy] ?? '—',
        ];

        // ── 2D & Quotation history: Approved Initial, tapos Approved Final (oldest → newest bawat grupo) ──
        $initials = mvFetchAll(
            $conn,
            "SELECT * FROM noblecrm_2dquotation WHERE inquiry_id = ? AND stage = 'Initial' AND status = 'Approved' ORDER BY id ASC",
            [$id],
            'i'
        );
        $finals = mvFetchAll(
            $conn,
            "SELECT * FROM noblecrm_2dquotation_final WHERE inquiry_id = ? AND status = 'Approved' ORDER BY id ASC",
            [$id],
            'i'
        );

        $cycles = [];
        foreach ($initials as $i => $q) {
            $cycles[] = mvBuildCycle($q, 'Initial', $i + 1, count($initials), $names);
        }
        foreach ($finals as $i => $q) {
            $cycles[] = mvBuildCycle($q, 'Final', $i + 1, count($finals), $names);
        }

        mvRespond(true, '', [
            'inquiry'         => $inquiry,
            'site_visits'     => $siteVisits,
            'deadline'        => mvPick($inq, ['deadline_2d_quotation', 'deadline_2d', 'quotation_deadline', 'deadline']),
            'design_progress' => $designProgress,
            'cycles'          => $cycles,
            'production'      => mvProduction($conn, $id, $names),
            'operations'      => mvOperations($conn, $id),
        ]);
    } catch (Throwable $e) {
        error_log('monitoringcrmviewajax timeline: ' . $e->getMessage());
        mvRespond(false, 'Server error while loading the record.');
    }
}

mvRespond(false, 'Unknown action.');