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
        ]);
    } catch (Throwable $e) {
        error_log('monitoringcrmviewajax timeline: ' . $e->getMessage());
        mvRespond(false, 'Server error while loading the record.');
    }
}

mvRespond(false, 'Unknown action.');