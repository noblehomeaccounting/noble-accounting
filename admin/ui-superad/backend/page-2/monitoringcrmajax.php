<?php
// monitoringcrmajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function monRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    return '—';
}

// Adds an equality filter to the query. "__none__" matches NULL / empty.
// $column is always a hardcoded string from this file (never user input).
function monAddFilter(string &$sql, string &$types, array &$params, string $column, string $value): void
{
    if ($value === '') return;
    if ($value === '__none__') {
        $sql .= " AND ($column IS NULL OR $column = '') ";
        return;
    }
    $sql .= " AND $column = ? ";
    $types .= 's';
    $params[] = $value;
}

function monStageInfo(?array $row): array
{
    if (!$row) {
        return ['stage_label' => 'Not Started', 'stage_group' => 'draft'];
    }

    if ($row['status'] === 'Draft') {
        return ['stage_label' => '2D & Quotation — Draft', 'stage_group' => 'draft'];
    }
    if ($row['status'] === 'Waiting for Approval') {
        return ['stage_label' => 'Waiting for 2D & Quotation Approval', 'stage_group' => 'in_progress'];
    }
    if ($row['status'] === 'For Revision') {
        return ['stage_label' => '2D & Quotation — For Revision', 'stage_group' => 'for_revision'];
    }

    // status === 'Approved' — main cycle is done, look at the 3D stage
    // (or lack of one) to figure out what's actually next.
    $stage3d = $row['design_3d_stage'] ?? 'Locked';
    if ($stage3d === 'Draft') {
        return ['stage_label' => 'Approved — 3D Upload Pending', 'stage_group' => 'in_progress'];
    }
    if ($stage3d === 'Waiting for Approval') {
        return ['stage_label' => 'Waiting for 3D Approval', 'stage_group' => 'in_progress'];
    }
    if ($stage3d === 'For Revision') {
        return ['stage_label' => '3D — For Revision', 'stage_group' => 'for_revision'];
    }
    if ($stage3d === 'Approved') {
        return ['stage_label' => 'Fully Approved', 'stage_group' => 'completed'];
    }

    // No 3D involved at all ('Locked') — 2D & Quotation approved is the
    // end of what this module tracks; next hand-off is Accounting.
    return ['stage_label' => 'Approved — Awaiting Accounting', 'stage_group' => 'completed'];
}

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $stageFilter = trim($_GET['stage'] ?? '');
    $modeFilter = trim($_GET['mode'] ?? '');
    $clientFilter = trim($_GET['clientstatus'] ?? '');
    $visitFilter = trim($_GET['visitstatus'] ?? '');

    $sql = "
        SELECT
            i.id AS inquiry_id, i.control_no, i.client_name, i.contact_number, i.project_type, i.branch,
            i.mode, i.clientstatus, i.statusdesignersitevisit,
            i.created_at AS inquiry_created_at,
            q.id AS q_id, q.status, q.include_3d, q.design_3d_stage,
            q.submitted_at, q.reviewed_at, q.created_at AS q_created_at
        FROM noblecrminquiry i
        LEFT JOIN (
            SELECT q1.*
            FROM noblecrm_2dquotation q1
            JOIN (
                SELECT inquiry_id, MAX(id) AS max_id
                FROM noblecrm_2dquotation
                GROUP BY inquiry_id
            ) latest ON latest.inquiry_id = q1.inquiry_id AND latest.max_id = q1.id
        ) q ON q.inquiry_id = i.id
        WHERE 1 = 1
    ";
    $types = '';
    $params = [];

    if ($search !== '') {
        $sql .= " AND (i.control_no LIKE ? OR i.client_name LIKE ? OR i.contact_number LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // Mode: NULL/empty is treated as 'site_visit' (same default used in the timeline action)
    if ($modeFilter === 'site_visit') {
        $sql .= " AND (i.mode = 'site_visit' OR i.mode IS NULL OR i.mode = '') ";
    } elseif ($modeFilter !== '') {
        monAddFilter($sql, $types, $params, 'i.mode', $modeFilter);
    }
    monAddFilter($sql, $types, $params, 'i.clientstatus', $clientFilter);
    monAddFilter($sql, $types, $params, 'i.statusdesignersitevisit', $visitFilter);

    $sql .= " ORDER BY COALESCE(q.reviewed_at, q.submitted_at, q.created_at, i.created_at) DESC LIMIT 200 ";

    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $info = monStageInfo($row['q_id'] ? $row : null);

        if ($stageFilter !== '' && $info['stage_group'] !== $stageFilter) {
            continue;
        }

        $rows[] = [
            'inquiry_id'     => (int) $row['inquiry_id'],
            'control_no'     => $row['control_no'],
            'client_name'    => $row['client_name'],
            'contact_number' => $row['contact_number'],
            'project_type'   => $row['project_type'],
            'branch'         => $row['branch'],
            'mode'           => $row['mode'] ?: 'site_visit',
            'clientstatus'   => $row['clientstatus'],
            'visitstatus'    => $row['statusdesignersitevisit'],
            'stage_label'    => $info['stage_label'],
            'stage_group'    => $info['stage_group'],
            'last_updated'   => $row['reviewed_at'] ?? $row['submitted_at'] ?? $row['q_created_at'] ?? $row['inquiry_created_at'],
        ];
    }
    if (isset($stmt)) $stmt->close();

    echo json_encode([
        'success' => true,
        'rows'    => $rows,
        'count'   => count($rows),
        'server_time' => date('c'),
    ]);
    exit;
}

// Distinct values used to populate the Client Status / Site Visit Status dropdowns.
if ($action === 'filter_options') {

    $clientStatuses = [];
    $r = $conn->query("SELECT DISTINCT clientstatus AS v FROM noblecrminquiry WHERE clientstatus IS NOT NULL AND clientstatus <> '' ORDER BY v");
    while ($x = $r->fetch_assoc()) $clientStatuses[] = $x['v'];

    $visitStatuses = [];
    $r = $conn->query("SELECT DISTINCT statusdesignersitevisit AS v FROM noblecrminquiry WHERE statusdesignersitevisit IS NOT NULL AND statusdesignersitevisit <> '' ORDER BY v");
    while ($x = $r->fetch_assoc()) $visitStatuses[] = $x['v'];

    echo json_encode([
        'success'         => true,
        'client_statuses' => $clientStatuses,
        'visit_statuses'  => $visitStatuses,
    ]);
    exit;
}

if ($action === 'timeline') {

    $inquiryId = intval($_GET['id'] ?? 0);
    if ($inquiryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            i.id, i.control_no, i.client_name, i.address, i.contact_number,
            i.project_type, i.project_scope, i.measuring_space, i.measurement_datetime,
            i.contract_amount, i.branch, i.created_at, i.status AS inquiry_status, i.mode, i.deadline,
            i.sales_staff_id, i.designer_id,
            i.design_progress, i.design_confirmed, i.design_confirmed_at, i.design_confirmed_by, i.clientstatus,
            sales.name AS sales_staff_name,
            designer.name AS designer_name,
            confirmedby.name AS design_confirmed_by_name
        FROM noblecrminquiry i
        LEFT JOIN noblerole sales ON sales.id = i.sales_staff_id
        LEFT JOIN noblerole designer ON designer.id = i.designer_id
        LEFT JOIN noblerole confirmedby ON confirmedby.id = i.design_confirmed_by
        WHERE i.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => 'Inquiry not found.']);
        exit;
    }


    $isReadyForQuotation = ($inquiry['mode'] ?? 'site_visit') === 'ready_for_quotation';


    $stmt = $conn->prepare("
        SELECT sv.id, sv.address, sv.visit_datetime, sv.visited, sv.photos, sv.created_at,
               sv.measurement_files, sv.measurements, sv.site_conditions,
               sv.client_requirements, sv.existing_structure,
               d.name AS designer_name
        FROM noblecrm_sitevisit sv
        LEFT JOIN noblerole d ON d.id = sv.designer_id
        WHERE sv.inquiry_id = ?
        ORDER BY sv.id ASC
    ");
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $result = $stmt->get_result();

    $siteVisits = [];
    while ($row = $result->fetch_assoc()) {
        $photos = array_values(array_filter(explode(',', $row['photos'] ?? '')));
        $measFiles = array_values(array_filter(explode(',', $row['measurement_files'] ?? '')));
        $siteVisits[] = [
            'id'                  => (int) $row['id'],
            'address'             => $row['address'],
            'visit_datetime'      => $row['visit_datetime'],
            'visited'             => $row['visited'] === 'yes',
            'photos'              => array_map(fn($p) => BASE_URL . '/' . $p, $photos),
            'measurement_files'   => array_map(fn($p) => BASE_URL . '/' . $p, $measFiles),
            'measurements'        => $row['measurements'],
            'site_conditions'     => $row['site_conditions'],
            'client_requirements' => $row['client_requirements'],
            'existing_structure'  => $row['existing_structure'],
            'designer_name'       => $row['designer_name'] ?? '—',
            'created_at'          => $row['created_at'],
        ];
    }
    $stmt->close();

    // ─────────────────────────────────────────────────────────────
    // 2D & Quotation HISTORY — Initial + Final, APPROVED submissions only.
    // Draft / Waiting for Approval / For Revision rows are left out.
    // Order: Initial (oldest→newest) then Final (oldest→newest); the front-end
    // reverses it so the newest (Final) shows first.
    // ─────────────────────────────────────────────────────────────
    $stageTables = [
        'Initial' => 'noblecrm_2dquotation',
        'Final'   => 'noblecrm_2dquotation_final',
    ];

    $cycles = [];
    $latestAny = null;       // newest row overall (any status) — only for the overall stage label
    $latestAnyStage = null;

    foreach ($stageTables as $stageName => $tbl) {
        $stmt = $conn->prepare("
            SELECT
                q.id, q.status, q.remarks, q.submitted_at, q.created_at, q.reviewed_at,
                q.design_2d_path, q.design_2d_uploaded_role, q.design_2d_uploaded_at,
                q.design_2d_review_status, q.design_2d_remarks,
                q.quotation_path, q.quotation_uploaded_role, q.quotation_uploaded_at,
                q.quotation_review_status, q.quotation_remarks,
                q.include_3d, q.design_3d_stage, q.design_3d_path, q.design_3d_uploaded_role,
                q.design_3d_uploaded_at, q.design_3d_review_status, q.design_3d_remarks, q.design_3d_reviewed_at,
                d2u.name AS design_2d_uploader_name,
                qtu.name AS quotation_uploader_name,
                d3u.name AS design_3d_uploader_name
            FROM {$tbl} q
            LEFT JOIN noblerole d2u ON d2u.id = q.design_2d_uploaded_by
            LEFT JOIN noblerole qtu ON qtu.id = q.quotation_uploaded_by
            LEFT JOIN noblerole d3u ON d3u.id = q.design_3d_uploaded_by
            WHERE q.inquiry_id = ?
            ORDER BY q.id ASC
        ");
        $stmt->bind_param("i", $inquiryId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($rows)) {
            $latestAny = end($rows);
            $latestAnyStage = $stageName;
        }

        $approvedRows = array_values(array_filter($rows, fn($r) => $r['status'] === 'Approved'));
        $stageTotal = count($approvedRows);

        foreach ($approvedRows as $n => $row) {
            $info = monStageInfo($row);
            $cycles[] = [
                'stage'                    => $stageName,
                'stage_no'                 => $n + 1,
                'stage_total'              => $stageTotal,
                'id'                       => (int) $row['id'],
                'status'                   => $row['status'],
                'stage_label'              => $info['stage_label'],
                'stage_group'              => $info['stage_group'],
                'remarks'                  => $row['remarks'],
                'submitted_at'             => $row['submitted_at'],
                'created_at'               => $row['created_at'],
                'reviewed_at'              => $row['reviewed_at'],
                'design_2d_path'           => $row['design_2d_path'],
                'design_2d_uploaded_role'  => monRoleLabel($row['design_2d_uploaded_role']),
                'design_2d_uploader_name'  => $row['design_2d_uploader_name'] ?? '—',
                'design_2d_uploaded_at'    => $row['design_2d_uploaded_at'],
                'design_2d_review_status'  => $row['design_2d_review_status'] ?? 'Pending',
                'design_2d_remarks'        => $row['design_2d_remarks'],
                'quotation_path'           => $row['quotation_path'],
                'quotation_uploaded_role'  => monRoleLabel($row['quotation_uploaded_role']),
                'quotation_uploader_name'  => $row['quotation_uploader_name'] ?? '—',
                'quotation_uploaded_at'    => $row['quotation_uploaded_at'],
                'quotation_review_status'  => $row['quotation_review_status'] ?? 'Pending',
                'quotation_remarks'        => $row['quotation_remarks'],
                'include_3d'               => (bool) $row['include_3d'],
                'design_3d_stage'          => $row['design_3d_stage'] ?? 'Locked',
                'design_3d_path'           => $row['design_3d_path'],
                'design_3d_uploaded_role'  => monRoleLabel($row['design_3d_uploaded_role']),
                'design_3d_uploader_name'  => $row['design_3d_uploader_name'] ?? '—',
                'design_3d_uploaded_at'    => $row['design_3d_uploaded_at'],
                'design_3d_review_status'  => $row['design_3d_review_status'] ?? 'Pending',
                'design_3d_remarks'        => $row['design_3d_remarks'],
                'design_3d_reviewed_at'    => $row['design_3d_reviewed_at'],
            ];
        }
    }

    // Overall stage label still reflects the newest row of ANY status, so the
    // header info stays accurate even though the history hides unapproved ones.
    if ($latestAny) {
        $overall = monStageInfo($latestAny);
        if ($latestAnyStage === 'Final') {
            $overall['stage_label'] = 'Final — ' . $overall['stage_label'];
        }
    } else {
        $overall = monStageInfo(null);
    }


    if ($isReadyForQuotation) {
        $designProgress = [
            'progress'          => '100',
            'confirmed'         => true,
            'confirmed_at'      => $inquiry['design_confirmed_at'] ?? $inquiry['created_at'],
            'confirmed_by_name' => $inquiry['design_confirmed_by_name'] ?? 'Client-provided 2D (no site visit)',
            'client_status'     => $inquiry['clientstatus'],
        ];
    } else {
        $designProgress = [
            'progress'          => $inquiry['design_progress'] ?? '0',
            'confirmed'         => (bool) ($inquiry['design_confirmed'] ?? 0),
            'confirmed_at'      => $inquiry['design_confirmed_at'],
            'confirmed_by_name' => $inquiry['design_confirmed_by_name'] ?? '—',
            'client_status'     => $inquiry['clientstatus'],
        ];
    }

    echo json_encode([
        'success' => true,
        'inquiry' => [
            'inquiry_id'          => (int) $inquiry['id'],
            'control_no'          => $inquiry['control_no'],
            'client_name'         => $inquiry['client_name'],
            'address'             => $inquiry['address'],
            'contact_number'      => $inquiry['contact_number'],
            'project_type'        => $inquiry['project_type'],
            'project_scope'       => $inquiry['project_scope'],
            'measuring_space'     => $inquiry['measuring_space'],
            'measurement_datetime'=> $inquiry['measurement_datetime'],
            'contract_amount'     => $inquiry['contract_amount'],
            'deadline'            => $inquiry['deadline'],
            'branch'              => $inquiry['branch'],
            'created_at'          => $inquiry['created_at'],
            'inquiry_status'      => $inquiry['inquiry_status'],
            'mode'                => $inquiry['mode'] ?? 'site_visit',
            'sales_staff_name'    => $inquiry['sales_staff_name'] ?? '—',
            'designer_name'       => $inquiry['designer_name'] ?? '—',
            'stage_label'         => $overall['stage_label'],
            'stage_group'         => $overall['stage_group'],
        ],

        'design_progress' => $designProgress,
        'site_visits' => $siteVisits,
        'cycles'  => $cycles,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);