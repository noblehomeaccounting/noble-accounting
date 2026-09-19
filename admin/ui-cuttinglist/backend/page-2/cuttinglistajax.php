<?php
// cuttinglistajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Same labeling convention as acctListRoleLabel() / chk2dRoleLabel().
function cutListRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    if ($role === 'accounting') return 'Accounting';
    return '—';
}

// NOTE: notification_id / is_read are now pulled via correlated subqueries
// instead of a LEFT JOIN. A quotation's inquiry can have more than one
// noblenotification row for the same user (e.g. multiple status updates),
// and a plain LEFT JOIN against a one-to-many table duplicates the parent
// row once per match — that was the cause of "test3" (and any inquiry with
// >1 notification) showing up twice/thrice in the list. Subqueries collapse
// it back to exactly one row per quotation. Preference: an unread
// notification if one exists, otherwise the most recent one.
function cutListBaseQuery(): string
{
    return "
        SELECT
            q.id, q.inquiry_id,
            q.design_2d_path, q.design_2d_uploaded_by, q.design_2d_uploaded_role, q.design_2d_review_status, q.design_2d_uploaded_at,
            q.quotation_path, q.quotation_uploaded_by, q.quotation_uploaded_role, q.quotation_review_status, q.quotation_uploaded_at,
            q.include_3d, q.design_3d_stage, q.design_3d_path,
            q.design_3d_uploaded_by, q.design_3d_uploaded_role, q.design_3d_review_status, q.design_3d_uploaded_at,
            q.status, q.reviewed_at,
            q.deposit_status, q.deposit_slip_path, q.deposit_amount,
            q.deposit_payment_method_id, q.deposit_uploaded_by,
            q.deposit_uploaded_role, q.deposit_uploaded_at,
            q.design_2d_verified_by_cutting, q.design_2d_verified_at,
            i.control_no, i.client_name, i.contact_number, i.project_type, i.branch,
            i.address, i.project_scope, i.measuring_space, i.measurement_datetime,
            i.contract_amount, i.designer_id, i.sales_staff_id,
            d.name AS designer_name,
            s.name AS sales_staff_name,
            d2u.name AS design_2d_uploader_name,
            qtu.name AS quotation_uploader_name,
            d3u.name AS design_3d_uploader_name,
            cv.name AS design_2d_verifier_name,
            pm.name AS deposit_payment_method_name,
            (
                SELECT n.id
                FROM noblenotification n
                WHERE n.request_id = q.inquiry_id AND n.user_id = ?
                ORDER BY n.is_read ASC, n.id DESC
                LIMIT 1
            ) AS notification_id,
            (
                SELECT n.is_read
                FROM noblenotification n
                WHERE n.request_id = q.inquiry_id AND n.user_id = ?
                ORDER BY n.is_read ASC, n.id DESC
                LIMIT 1
            ) AS is_read
        FROM noblecrm_2dquotation q
        JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM noblecrm_2dquotation
            GROUP BY inquiry_id
        ) latest ON latest.inquiry_id = q.inquiry_id AND latest.max_id = q.id
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole d ON d.id = i.designer_id
        LEFT JOIN noblerole s ON s.id = i.sales_staff_id
        LEFT JOIN noblerole d2u ON d2u.id = q.design_2d_uploaded_by
        LEFT JOIN noblerole qtu ON qtu.id = q.quotation_uploaded_by
        LEFT JOIN noblerole d3u ON d3u.id = q.design_3d_uploaded_by
        LEFT JOIN noblerole cv ON cv.id = q.design_2d_verified_by_cutting
        LEFT JOIN noblecrm_paymentmethod pm ON pm.id = q.deposit_payment_method_id
    ";
}

function cutListFormatRow(array $row): array
{
    $show3d = ((int) $row['include_3d'] === 1) || (($row['design_3d_stage'] ?? 'Locked') !== 'Locked');

    return [
        'id'                    => (int) $row['id'],
        'inquiry_id'            => (int) $row['inquiry_id'],
        'control_no'            => $row['control_no'],
        'client_name'           => $row['client_name'],
        'contact_number'        => $row['contact_number'],
        'project_type'          => $row['project_type'],
        'branch'                => $row['branch'],
        'address'               => $row['address'],
        'project_scope'         => $row['project_scope'],
        'measuring_space'       => $row['measuring_space'],
        'measurement_datetime'  => $row['measurement_datetime'],
        'contract_amount'       => $row['contract_amount'],
        'designer_name'         => $row['designer_name'],
        'sales_staff_name'      => $row['sales_staff_name'],

        'design_2d_path'          => $row['design_2d_path'],
        'design_2d_uploader_name' => $row['design_2d_uploader_name'] ?? '—',
        'design_2d_uploaded_role' => cutListRoleLabel($row['design_2d_uploaded_role']),
        'design_2d_review_status' => $row['design_2d_review_status'] ?? 'Pending',
        'design_2d_uploaded_at'   => $row['design_2d_uploaded_at'] ?? null,

        // Cutting verification of the 2D file ("wala ng mali").
        'design_2d_verified'          => !empty($row['design_2d_verified_by_cutting']),
        'design_2d_verified_by_name'  => $row['design_2d_verifier_name'] ?? null,
        'design_2d_verified_at'       => $row['design_2d_verified_at'] ?? null,

        'quotation_path'          => $row['quotation_path'],
        'quotation_uploader_name' => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role' => cutListRoleLabel($row['quotation_uploaded_role']),
        'quotation_review_status' => $row['quotation_review_status'] ?? 'Pending',
        'quotation_uploaded_at'   => $row['quotation_uploaded_at'] ?? null,

        'include_3d'              => (bool) $row['include_3d'],
        'show_3d'                 => $show3d,
        'design_3d_path'          => $row['design_3d_path'],
        'design_3d_uploader_name' => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role' => cutListRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_review_status' => $row['design_3d_review_status'] ?? 'Pending',
        'design_3d_uploaded_at'   => $row['design_3d_uploaded_at'] ?? null,

        'status'                => $row['status'],
        'reviewed_at'           => $row['reviewed_at'],

        'deposit_status'              => $row['deposit_status'] ?? 'Hold',
        'deposit_slip_path'           => $row['deposit_slip_path'],
        'deposit_amount'              => $row['deposit_amount'],
        'deposit_payment_method_name' => $row['deposit_payment_method_name'],
        'deposit_uploader_name'       => $row['deposit_uploader_name'] ?? '—',
        'deposit_uploaded_role'       => cutListRoleLabel($row['deposit_uploaded_role']),
        'deposit_uploaded_at'         => $row['deposit_uploaded_at'],

        'is_read'               => $row['notification_id'] === null ? 1 : (int) $row['is_read'],
    ];
}


if ($action === 'send_feedback') {

    $quotationId = intval($_POST['quotation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($quotationId <= 0 || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Feedback message is required.']);
        exit;
    }

    // Cutting can only give feedback on records they can actually see (Approved).
    $stmt = $conn->prepare("SELECT id FROM noblecrm_2dquotation WHERE id = ? AND status = 'Approved' LIMIT 1");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Record not found or not yet approved.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO noblecrm_2d_feedback (quotation_id, message, created_by, created_by_role)
        VALUES (?, ?, ?, 'cutting')
    ");
    $stmt->bind_param('isi', $quotationId, $message, $currentUserId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Feedback sent to the designer.']);
    exit;
}

if ($action === 'verify_2d') {

    $quotationId = intval($_POST['quotation_id'] ?? 0);
    if ($quotationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    // Cutting can only verify records that are Approved and already NTP
    // (files are only visible to them at that point anyway).
    $stmt = $conn->prepare("
        SELECT id, design_2d_path, design_2d_verified_by_cutting
        FROM noblecrm_2dquotation
        WHERE id = ? AND status = 'Approved' AND deposit_status = 'Notice to Proceed'
        LIMIT 1
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Record not found or not yet ready for verification.']);
        exit;
    }

    if (empty($record['design_2d_path'])) {
        echo json_encode(['success' => false, 'message' => 'No 2D file has been uploaded yet.']);
        exit;
    }

    if (!empty($record['design_2d_verified_by_cutting'])) {
        echo json_encode(['success' => false, 'message' => 'This 2D file is already verified.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE noblecrm_2dquotation
        SET design_2d_verified_by_cutting = ?, design_2d_verified_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('ii', $currentUserId, $quotationId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => '2D file marked as verified.']);
    exit;
}

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $filter = trim($_GET['filter'] ?? 'all');

    $sql = cutListBaseQuery() . " WHERE q.status = 'Approved' ";
    // Two placeholders here are for the two correlated subqueries
    // (notification_id, is_read) inside cutListBaseQuery().
    $types = 'ii';
    $params = [$currentUserId, $currentUserId];

    if ($filter === 'unread') {
        // EXISTS instead of joining on n.id/n.is_read directly, since those
        // columns now come from subqueries rather than a joined table.
        $sql .= " AND EXISTS (
            SELECT 1 FROM noblenotification n2
            WHERE n2.request_id = q.inquiry_id AND n2.user_id = ? AND n2.is_read = 0
        ) ";
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($search !== '') {
        $sql .= " AND (i.control_no LIKE ? OR i.client_name LIKE ? OR i.contact_number LIKE ? OR i.branch LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY (COALESCE(q.deposit_uploaded_at, q.reviewed_at) IS NULL),
                        COALESCE(q.deposit_uploaded_at, q.reviewed_at) DESC LIMIT 200 ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = cutListFormatRow($row);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'rows'    => $rows,
        'count'   => count($rows),
        'server_time' => date('c'),
    ]);
    exit;
}

if ($action === 'detail') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $sql = cutListBaseQuery() . " WHERE q.id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    // Same two extra placeholders as the list action above, plus the actual id.
    $stmt->bind_param('iii', $currentUserId, $currentUserId, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    // Gate on overall approval only — deposit/NTP status is no longer a
    // requirement to view the detail page, just something shown on it.
    if ($row['status'] !== 'Approved') {
        echo json_encode(['success' => false, 'message' => 'Not yet fully approved by accounting.']);
        exit;
    }

    // Mark this cutting user's notification for this inquiry as read.
    if ($row['notification_id'] !== null && (int) $row['is_read'] === 0) {
        $stmt = $conn->prepare("UPDATE noblenotification SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $row['notification_id']);
        $stmt->execute();
        $stmt->close();
        $row['is_read'] = 1;
    }

    $record = cutListFormatRow($row);

    $stmt = $conn->prepare("
        SELECT sv.id, sv.address, sv.visit_datetime, sv.visited, sv.photos,
               sv.measurements, sv.site_conditions, sv.client_requirements, sv.existing_structure,
               sv.created_at, d.name AS designer_name
        FROM noblecrm_sitevisit sv
        LEFT JOIN noblerole d ON d.id = sv.designer_id
        WHERE sv.inquiry_id = ?
        ORDER BY sv.id ASC
    ");
    $stmt->bind_param('i', $row['inquiry_id']);
    $stmt->execute();
    $svResult = $stmt->get_result();

    $siteVisits = [];
    while ($sv = $svResult->fetch_assoc()) {
        $photos = array_values(array_filter(explode(',', $sv['photos'] ?? '')));
        $siteVisits[] = [
            'id'                  => (int) $sv['id'],
            'address'             => $sv['address'],
            'visit_datetime'      => $sv['visit_datetime'],
            'visited'             => $sv['visited'] === 'yes',
            'photos'              => array_map(fn($p) => BASE_URL . '/' . $p, $photos),
            'measurements'        => $sv['measurements'],
            'site_conditions'     => $sv['site_conditions'],
            'client_requirements' => $sv['client_requirements'],
            'existing_structure'  => $sv['existing_structure'],
            'designer_name'       => $sv['designer_name'] ?? '—',
            'created_at'          => $sv['created_at'],
        ];
    }
    $stmt->close();

    $record['site_visits'] = $siteVisits;

    $stmt = $conn->prepare("
        SELECT f.id, f.message, f.created_at, f.is_resolved, r.name AS created_by_name
        FROM noblecrm_2d_feedback f
        LEFT JOIN noblerole r ON r.id = f.created_by
        WHERE f.quotation_id = ?
        ORDER BY f.id DESC
    ");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    $fbResult = $stmt->get_result();

    $feedbackLog = [];
    while ($fb = $fbResult->fetch_assoc()) {
        $feedbackLog[] = [
            'id'              => (int) $fb['id'],
            'message'         => $fb['message'],
            'created_by_name' => $fb['created_by_name'] ?? '—',
            'created_at'      => $fb['created_at'],
            'is_resolved'     => (bool) $fb['is_resolved'],
        ];
    }
    $stmt->close();

    $record['cutting_feedback'] = $feedbackLog;

    echo json_encode([
        'success' => true,
        'record'  => $record,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);