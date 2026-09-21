<?php
// check2dquotationajax.php
//
// Handles BOTH stages of the 2D & Quotation approval:
//   stage = Initial → noblecrm_2dquotation
//   stage = Final   → noblecrm_2dquotation_final   (same columns)
// Ids can repeat across the two tables, so a record is always identified by (stage, id).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Statuses that are actually visible to the approver. 'Draft' means the
// designer/sales hasn't submitted yet, so it's excluded entirely.
const CHK2D_VISIBLE_STATUSES = ['Waiting for Approval', 'Approved', 'For Revision'];
const CHK2D_REVIEW_DECISIONS = ['Approved', 'For Revision'];
const CHK2D_3D_STAGES_VISIBLE = ['Waiting for Approval', 'Approved', 'For Revision'];

// Stage → table. (Only ever interpolated after being validated against these keys.)
const CHK2D_TABLES = [
    'Initial' => 'noblecrm_2dquotation',
    'Final'   => 'noblecrm_2dquotation_final',
];

// Stage is required by detail / review / review_3d. `list` covers both stages.
$stage = $_POST['stage'] ?? $_GET['stage'] ?? 'Initial';
if (!isset(CHK2D_TABLES[$stage])) {
    echo json_encode(['success' => false, 'message' => 'Invalid stage.']);
    exit;
}
$table = CHK2D_TABLES[$stage];

/**
 * SELECT for one stage's table. $latestOnly = true keeps only the newest
 * row per inquiry (used by the list); false is used for single-record detail.
 */
function chk2dSelectSql(string $stage, bool $latestOnly): string
{
    $table = CHK2D_TABLES[$stage];

    $latestJoin = $latestOnly ? "
        JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM {$table}
            GROUP BY inquiry_id
        ) latest ON latest.inquiry_id = q.inquiry_id AND latest.max_id = q.id
    " : '';

    return "
        SELECT
            '{$stage}' AS stage,
            q.id, q.inquiry_id, q.design_2d_path, q.design_2d_uploaded_by, q.design_2d_uploaded_role,
            q.design_2d_done, q.design_2d_uploaded_at,
            q.quotation_path, q.quotation_uploaded_by, q.quotation_uploaded_role,
            q.quotation_done, q.quotation_uploaded_at,
            q.design_2d_review_status, q.design_2d_remarks,
            q.quotation_review_status, q.quotation_remarks,
            q.include_3d, q.design_3d_stage, q.design_3d_path, q.design_3d_uploaded_by, q.design_3d_uploaded_role,
            q.design_3d_done, q.design_3d_uploaded_at, q.design_3d_review_status, q.design_3d_remarks,
            q.status, q.remarks, q.submitted_at, q.created_at, q.reviewed_at,
            i.control_no, i.client_name, i.contact_number, i.project_type, i.branch, i.contract_amount,
            i.target_completion_date,
            d2u.name AS design_2d_uploader_name,
            qtu.name AS quotation_uploader_name,
            d3u.name AS design_3d_uploader_name
        FROM {$table} q
        {$latestJoin}
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole d2u ON d2u.id = q.design_2d_uploaded_by
        LEFT JOIN noblerole qtu ON qtu.id = q.quotation_uploaded_by
        LEFT JOIN noblerole d3u ON d3u.id = q.design_3d_uploaded_by
    ";
}

function chk2dRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    return '—';
}


function chk2dReviewTarget(array $row): string
{
    if ($row['status'] === 'Waiting for Approval') {
        return (int) $row['include_3d'] === 1 ? 'main_with_3d' : 'main';
    }
    if ($row['status'] === 'Approved' && ($row['design_3d_stage'] ?? 'Locked') === 'Waiting for Approval') {
        return '3d_only';
    }
    return 'none';
}

function chk2dFormatRow(array $row): array
{
    return [
        'stage'                     => $row['stage'],
        'id'                        => (int) $row['id'],
        'inquiry_id'                => (int) $row['inquiry_id'],
        'control_no'                => $row['control_no'],
        'client_name'               => $row['client_name'],
        'contact_number'            => $row['contact_number'],
        'project_type'              => $row['project_type'],
        'branch'                    => $row['branch'],
        'contract_amount'           => $row['contract_amount'],
        'target_completion_date'    => $row['target_completion_date'],
        'design_2d_path'            => $row['design_2d_path'],
        'design_2d_uploader_name'   => $row['design_2d_uploader_name'] ?? '—',
        'design_2d_uploaded_role'   => chk2dRoleLabel($row['design_2d_uploaded_role']),
        'design_2d_uploaded_at'     => $row['design_2d_uploaded_at'],
        'design_2d_review_status'   => $row['design_2d_review_status'] ?? 'Pending',
        'design_2d_remarks'         => $row['design_2d_remarks'],
        'quotation_path'            => $row['quotation_path'],
        'quotation_uploader_name'   => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role'   => chk2dRoleLabel($row['quotation_uploaded_role']),
        'quotation_uploaded_at'     => $row['quotation_uploaded_at'],
        'quotation_review_status'   => $row['quotation_review_status'] ?? 'Pending',
        'quotation_remarks'         => $row['quotation_remarks'],
        'include_3d'                => (bool) $row['include_3d'],
        'design_3d_stage'           => $row['design_3d_stage'] ?? 'Locked',
        'design_3d_path'            => $row['design_3d_path'],
        'design_3d_uploader_name'   => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role'   => chk2dRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_uploaded_at'     => $row['design_3d_uploaded_at'],
        'design_3d_review_status'   => $row['design_3d_review_status'] ?? 'Pending',
        'design_3d_remarks'         => $row['design_3d_remarks'],
        'review_target'             => chk2dReviewTarget($row),
        'status'                    => $row['status'],
        'remarks'                   => $row['remarks'],
        'submitted_at'              => $row['submitted_at'],
        'reviewed_at'               => $row['reviewed_at'],
        'created_at'                => $row['created_at'],
    ];
}

// ═══════════════════════════════════════════════════════════
// NOTIFICATIONS
// ═══════════════════════════════════════════════════════════

function chk2dNotifyAccountingHead(mysqli $conn, int $inquiryId, array $record, int $senderId, string $stage = 'Initial'): void
{
    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
    $role = ROLE_ACCOUNTING;      // ⚠️ verify column name `role`
    $position = POSITION_HEAD;    // ⚠️ verify column name `position`
    $stmt->bind_param("ss", $role, $position);
    $stmt->execute();
    $accountingHeads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($accountingHeads)) {
        return;
    }

    $message = "{$stage} 2D and Quotation approved for {$record['client_name']} (Control No. {$record['control_no']})";
    $link = "/crmaccounting?id={$inquiryId}"; // ⚠️ verify intended destination for accounting
    $controlNo = $record['control_no'];

    $stmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_2dquotation', ?, 0, NOW(), ?, ?)
    ");
    $stmt->bind_param("iissis", $headId, $inquiryId, $controlNo, $message, $senderId, $link);

    foreach ($accountingHeads as $head) {
        $headId = (int) $head['id'];
        $stmt->execute();
    }
    $stmt->close();
}


function chk2dNotifyRoleRevision(
    mysqli $conn,
    string $role,
    int $inquiryId,
    array $record,
    int $senderId,
    string $fileLabel,
    ?string $remarks,
    string $stage = 'Initial'
): void {
    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ?"); // ⚠️ verify column name `role`
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($members)) {
        return;
    }

    $message = "{$stage} {$fileLabel} for {$record['client_name']} (Control No. {$record['control_no']}) needs revision";
    if ($remarks) {
        $message .= ": {$remarks}";
    }

    // Each stage has its own page.  ⚠️ verify both routes.
    $route = ($stage === 'Final') ? '/crm2dquotationfinal' : '/crm2dquotation';
    $link = "{$route}?id={$inquiryId}";
    $controlNo = $record['control_no'];

    $stmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_2dquotation', ?, 0, NOW(), ?, ?)
    ");
    $stmt->bind_param("iissis", $memberId, $inquiryId, $controlNo, $message, $senderId, $link);

    foreach ($members as $member) {
        $memberId = (int) $member['id'];
        $stmt->execute();
    }
    $stmt->close();
}

// ═══════════════════════════════════════════════════════════
// LIST — Initial + Final together, each row tagged with its stage
// ═══════════════════════════════════════════════════════════

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $placeholders = implode(',', array_fill(0, count(CHK2D_VISIBLE_STATUSES), '?'));

    $union = '(' . chk2dSelectSql('Initial', true) . ') UNION ALL (' . chk2dSelectSql('Final', true) . ')';

    $sql = "
        SELECT * FROM ({$union}) x
        WHERE (
            x.status IN ({$placeholders})
            OR (x.status = 'Approved' AND x.design_3d_stage = 'Waiting for Approval')
        )
    ";
    $types = str_repeat('s', count(CHK2D_VISIBLE_STATUSES));
    $params = CHK2D_VISIBLE_STATUSES;

    if ($statusFilter !== '' && in_array($statusFilter, CHK2D_VISIBLE_STATUSES, true)) {
        $sql .= " AND x.status = ? ";
        $types .= "s";
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        $sql .= " AND (x.control_no LIKE ? OR x.client_name LIKE ? OR x.contact_number LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY (x.submitted_at IS NULL), x.submitted_at DESC, x.created_at DESC LIMIT 200 ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = chk2dFormatRow($row);
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

// ═══════════════════════════════════════════════════════════
// DETAIL
// ═══════════════════════════════════════════════════════════

if ($action === 'detail') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $sql = chk2dSelectSql($stage, false) . " WHERE q.id = ? LIMIT 1 ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'record'  => chk2dFormatRow($row),
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// REVIEW — the main 2D + Quotation (+ bundled 3D) decision
// ═══════════════════════════════════════════════════════════

if ($action === 'review') {

    $id = intval($_POST['id'] ?? 0);
    $design2dDecision  = trim($_POST['design_2d_decision'] ?? '');
    $design2dRemarks   = trim($_POST['design_2d_remarks'] ?? '');
    $quotationDecision = trim($_POST['quotation_decision'] ?? '');
    $quotationRemarks  = trim($_POST['quotation_remarks'] ?? '');
    // Only required/used when the record's include_3d = 1.
    $design3dDecision  = trim($_POST['design_3d_decision'] ?? '');
    $design3dRemarks   = trim($_POST['design_3d_remarks'] ?? '');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    if (!in_array($design2dDecision, CHK2D_REVIEW_DECISIONS, true)
        || !in_array($quotationDecision, CHK2D_REVIEW_DECISIONS, true)) {
        echo json_encode(['success' => false, 'message' => 'Please decide on both the 2D file and the Quotation file.']);
        exit;
    }

    if ($design2dDecision === 'For Revision' && $design2dRemarks === '') {
        echo json_encode(['success' => false, 'message' => 'Please provide remarks for the 2D file revision.']);
        exit;
    }

    if ($quotationDecision === 'For Revision' && $quotationRemarks === '') {
        echo json_encode(['success' => false, 'message' => 'Please provide remarks for the Quotation file revision.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT q.status, q.inquiry_id, q.include_3d, i.control_no, i.client_name
        FROM {$table} q
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE q.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    if ($current['status'] !== 'Waiting for Approval') {
        echo json_encode(['success' => false, 'message' => 'Only submissions waiting for approval can be reviewed.']);
        exit;
    }

    $include3d = (int) $current['include_3d'];

    // If this submission bundled a 3D file, its decision is required too
    // before the review can be saved.
    if ($include3d) {
        if (!in_array($design3dDecision, CHK2D_REVIEW_DECISIONS, true)) {
            echo json_encode(['success' => false, 'message' => 'Please decide on the 3D file as well.']);
            exit;
        }
        if ($design3dDecision === 'For Revision' && $design3dRemarks === '') {
            echo json_encode(['success' => false, 'message' => 'Please provide remarks for the 3D file revision.']);
            exit;
        }

        // 3D is derived from 2D — a 3D revision sends the 2D back too.
        if ($design3dDecision === 'For Revision' && $design2dDecision !== 'For Revision') {
            $design2dDecision = 'For Revision';
            $cascadeNote = 'Automatically sent back together with the 3D file revision.';
            $design2dRemarks = $design2dRemarks !== ''
                ? $design2dRemarks . "\n\n" . $cascadeNote
                : $cascadeNote;
        }
    }

    $overallStatus = ($design2dDecision === 'Approved' && $quotationDecision === 'Approved'
        && (!$include3d || $design3dDecision === 'Approved'))
        ? 'Approved'
        : 'For Revision';

    $design2dRemarksToSave  = $design2dDecision === 'For Revision' ? $design2dRemarks : null;
    $quotationRemarksToSave = $quotationDecision === 'For Revision' ? $quotationRemarks : null;
    $design3dRemarksToSave  = ($include3d && $design3dDecision === 'For Revision') ? $design3dRemarks : null;

    $combinedRemarks = trim(implode("\n\n", array_filter([
        $design2dRemarksToSave ? "2D: {$design2dRemarksToSave}" : '',
        $quotationRemarksToSave ? "Quotation: {$quotationRemarksToSave}" : '',
        $design3dRemarksToSave ? "3D: {$design3dRemarksToSave}" : '',
    ]))) ?: null;

    if ($include3d) {
        // Bundled cycle: 3D's stage mirrors whatever the batch outcome was.
        $new3dStage = $overallStatus; // 'Approved' or 'For Revision'
    } elseif ($overallStatus === 'Approved') {
        // Sequential flow: 2D & Quotation just got approved without 3D —
        // unlock the 3D upload slot now.
        $new3dStage = 'Draft';
    } else {
        // 2D/Quotation sent back for revision — 3D isn't relevant yet.
        $new3dStage = 'Locked';
    }

    $stmt = $conn->prepare("
        UPDATE {$table}
        SET status = ?,
            design_2d_review_status = ?, design_2d_remarks = ?,
            quotation_review_status = ?, quotation_remarks = ?,
            design_3d_review_status = IF(? = 1, ?, design_3d_review_status),
            design_3d_remarks = IF(? = 1, ?, design_3d_remarks),
            design_3d_stage = ?,
            design_3d_reviewed_at = IF(? = 1, NOW(), design_3d_reviewed_at),
            remarks = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");

    $types = 's' . 's' . 's' . 's' . 's' . 'i' . 's' . 'i' . 's' . 's' . 'i' . 's' . 'i';
    $stmt->bind_param(
        $types,
        $overallStatus,
        $design2dDecision, $design2dRemarksToSave,
        $quotationDecision, $quotationRemarksToSave,
        $include3d, $design3dDecision,
        $include3d, $design3dRemarksToSave,
        $new3dStage,
        $include3d,
        $combinedRemarks,
        $id
    );
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $recordForNotif = [
            'control_no'  => $current['control_no'],
            'client_name' => $current['client_name'],
        ];

        if ($overallStatus === 'Approved') {

            chk2dNotifyAccountingHead(
                $conn,
                (int) $current['inquiry_id'],
                $recordForNotif,
                $currentUserId,
                $stage
            );
        } else {

            if ($design2dDecision === 'For Revision') {
                chk2dNotifyRoleRevision(
                    $conn,
                    ROLE_DESIGNER,
                    (int) $current['inquiry_id'],
                    $recordForNotif,
                    $currentUserId,
                    '2D File',
                    $design2dRemarksToSave,
                    $stage
                );
            }

            if ($quotationDecision === 'For Revision') {
                chk2dNotifyRoleRevision(
                    $conn,
                    ROLE_SALES,
                    (int) $current['inquiry_id'],
                    $recordForNotif,
                    $currentUserId,
                    'Quotation File',
                    $quotationRemarksToSave,
                    $stage
                );
            }
        }
    }

    $message = $overallStatus === 'Approved' ? 'Submission approved.' : 'Sent back for revision.';
    echo json_encode(['success' => (bool) $ok, 'message' => $ok ? $message : 'Failed to save review.']);
    exit;
}

// ═══════════════════════════════════════════════════════════
// REVIEW 3D — standalone (sequential) 3D decision
// ═══════════════════════════════════════════════════════════

if ($action === 'review_3d') {

    $id = intval($_POST['id'] ?? 0);
    $design3dDecision = trim($_POST['design_3d_decision'] ?? '');
    $design3dRemarks  = trim($_POST['design_3d_remarks'] ?? '');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }
    if (!in_array($design3dDecision, CHK2D_REVIEW_DECISIONS, true)) {
        echo json_encode(['success' => false, 'message' => 'Please decide on the 3D file.']);
        exit;
    }
    if ($design3dDecision === 'For Revision' && $design3dRemarks === '') {
        echo json_encode(['success' => false, 'message' => 'Please provide remarks for the 3D file revision.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT q.*, i.control_no, i.client_name
        FROM {$table} q
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE q.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }
    if ($current['status'] !== 'Approved' || ($current['design_3d_stage'] ?? 'Locked') !== 'Waiting for Approval') {
        echo json_encode(['success' => false, 'message' => 'This 3D file is not currently waiting for approval.']);
        exit;
    }

    $recordForNotif = [
        'control_no'  => $current['control_no'],
        'client_name' => $current['client_name'],
    ];

    if ($design3dDecision === 'Approved') {

        $stmt = $conn->prepare("
            UPDATE {$table}
            SET design_3d_stage = 'Approved', design_3d_review_status = 'Approved',
                design_3d_remarks = NULL, design_3d_reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        $message = 'Submission approved.';

    } else {

        $cascadeNote = 'Revision requested on the 3D file — please also review/update the 2D file.';

        $newStatus = 'For Revision';
        $include3d = 1;
        $newStage3d = 'Draft';

        // New revision cycle row in the SAME table (Initial stays in Initial,
        // Final stays in Final).
        $stmt = $conn->prepare("
            INSERT INTO {$table}
                (inquiry_id, status, created_at, include_3d,
                 design_2d_done, design_2d_path, design_2d_uploaded_role, design_2d_uploaded_by, design_2d_review_status, design_2d_remarks,
                 quotation_done, quotation_path, quotation_uploaded_role, quotation_uploaded_by, quotation_uploaded_at, quotation_review_status,
                 design_3d_done, design_3d_path, design_3d_uploaded_role, design_3d_uploaded_by, design_3d_review_status, design_3d_remarks, design_3d_stage)
            VALUES
                (?, ?, NOW(), ?,
                 0, ?, ?, ?, 'For Revision', ?,
                 1, ?, ?, ?, NOW(), 'Approved',
                 0, ?, ?, ?, 'For Revision', ?, ?)
        ");

        $types = 'i' . 's' . 'i' . 's' . 's' . 'i' . 's' . 's' . 's' . 'i' . 's' . 's' . 'i' . 's' . 's';
        $design2dUploadedBy = (int) $current['design_2d_uploaded_by'];
        $quotationUploadedBy = (int) $current['quotation_uploaded_by'];
        $design3dUploadedBy = (int) $current['design_3d_uploaded_by'];
        $stmt->bind_param(
            $types,
            $current['inquiry_id'], $newStatus, $include3d,
            $current['design_2d_path'], $current['design_2d_uploaded_role'], $design2dUploadedBy, $cascadeNote,
            $current['quotation_path'], $current['quotation_uploaded_role'], $quotationUploadedBy,
            $current['design_3d_path'], $current['design_3d_uploaded_role'], $design3dUploadedBy, $design3dRemarks, $newStage3d
        );
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            chk2dNotifyRoleRevision(
                $conn,
                ROLE_DESIGNER,
                (int) $current['inquiry_id'],
                $recordForNotif,
                $currentUserId,
                '2D File (triggered by 3D revision)',
                $cascadeNote,
                $stage
            );
        }

        $message = 'Sent back for revision — the 2D file has also been reopened for rework.';
    }

    echo json_encode(['success' => (bool) $ok, 'message' => $ok ? $message : 'Failed to save review.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);