<?php
// check2dquotationajax.php
//
// FINAL 2D & Quotation (+3D) approval (noblecrm_2dquotation_final).
//   • Designer Head → nag-a-approve ng 2D at 3D
//   • Sales Head    → nag-a-approve ng Quotation
//   • Superadmin    → REVIEW / MONITOR lang (nakikita lahat, walang Approve / Revision)
// Approved lang ang buong Final kapag tapos na ang dalawang head (walang "Pending" na slot).
// Kapag may isang head na nag-revision, hinihintay pa rin ang kabila para isang balikan lang.
// Ang Initial approval ay nasa Designer Head pa rin: checkdesigner2dquotationajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN, ROLE_SALES, ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$currentRole = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

const CHK2D_VISIBLE_STATUSES = ['Waiting for Approval', 'Approved', 'For Revision'];
const CHK2D_REVIEW_DECISIONS = ['Approved', 'For Revision'];

// Sino ang may-ari ng bawat slot
const CHK2D_SLOT_OWNER = [
    'design_2d' => 'designer_head',
    'design_3d' => 'designer_head',
    'quotation' => 'sales_head',
];
const CHK2D_VIEWER_LABEL = [
    'designer_head' => 'Designer Head',
    'sales_head'    => 'Sales Head',
    'superadmin'    => 'Superadmin',
];
const CHK2D_SLOT_LABEL = [
    'design_2d' => '2D File',
    'quotation' => 'Quotation File',
    'design_3d' => '3D File',
];

$stage = 'Final';
$table = 'noblecrm_2dquotation_final';

// ═══════════════════════════════════════════════════════════
// SINO ANG NAKA-LOGIN: Superadmin (monitor) o Head ng Sales / Designer
// ═══════════════════════════════════════════════════════════

$viewer = '';
if ($currentRole === ROLE_SUPERADMIN) {
    $viewer = 'superadmin';
} elseif ($currentRole === ROLE_SALES || $currentRole === ROLE_DESIGNER) {
    $headStmt = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
    $headPos = POSITION_HEAD;
    $headStmt->bind_param("iss", $currentUserId, $currentRole, $headPos);
    $headStmt->execute();
    $isHead = (bool) $headStmt->get_result()->fetch_assoc();
    $headStmt->close();

    if ($isHead) {
        $viewer = $currentRole === ROLE_SALES ? 'sales_head' : 'designer_head';
    }
}

if ($viewer === '') {
    echo json_encode(['success' => false, 'message' => 'Only the Sales Head, Designer Head or Superadmin can open the Final approvals.']);
    exit;
}

// Mga slot na hawak ng naka-login (Superadmin = wala)
$mySlots = array_keys(array_filter(CHK2D_SLOT_OWNER, fn($owner) => $owner === $viewer));

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

/** May column ba (para hindi masira kung hindi pa na-run ang SQL ng reviewer columns). */
function chk2dHasCol(mysqli $conn, string $col): bool
{
    static $cols = null;
    global $table;
    if ($cols === null) {
        $cols = [];
        $res = @$conn->query("SHOW COLUMNS FROM {$table}");
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                $cols[$c['Field']] = true;
            }
        }
    }
    return isset($cols[$col]);
}

function chk2dReviewerColsReady(mysqli $conn): bool
{
    return chk2dHasCol($conn, 'design_reviewed_by')
        && chk2dHasCol($conn, 'design_reviewed_at')
        && chk2dHasCol($conn, 'quotation_reviewed_by')
        && chk2dHasCol($conn, 'quotation_reviewed_at')
        && chk2dHasCol($conn, 'design_3d_reviewed_by');
}

/**
 * SELECT for the Final table. $latestOnly = true keeps only the newest
 * row per inquiry (used by the list); false is used for single-record detail.
 */
function chk2dSelectSql(mysqli $conn, bool $latestOnly): string
{
    global $table, $stage;

    $latestJoin = $latestOnly ? "
        JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM {$table}
            GROUP BY inquiry_id
        ) latest ON latest.inquiry_id = q.inquiry_id AND latest.max_id = q.id
    " : '';

    if (chk2dReviewerColsReady($conn)) {
        $reviewerCols = "
            q.design_reviewed_at, q.quotation_reviewed_at,
            d2r.name AS design_reviewer_name,
            qtr.name AS quotation_reviewer_name,
            d3r.name AS design_3d_reviewer_name,";
        $reviewerJoins = "
        LEFT JOIN noblerole d2r ON d2r.id = q.design_reviewed_by
        LEFT JOIN noblerole qtr ON qtr.id = q.quotation_reviewed_by
        LEFT JOIN noblerole d3r ON d3r.id = q.design_3d_reviewed_by";
    } else {
        $reviewerCols = "
            NULL AS design_reviewed_at, NULL AS quotation_reviewed_at,
            NULL AS design_reviewer_name, NULL AS quotation_reviewer_name, NULL AS design_3d_reviewer_name,";
        $reviewerJoins = '';
    }

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
            q.design_3d_reviewed_at,
            {$reviewerCols}
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
        {$reviewerJoins}
    ";
}

function chk2dRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    return '—';
}

/** Review status ng isang slot (NULL / blank = Pending). */
function chk2dSlotStatus(array $row, string $slot): string
{
    $v = $row[$slot . '_review_status'] ?? null;
    return in_array($v, CHK2D_REVIEW_DECISIONS, true) ? $v : 'Pending';
}

/** Mga slot na kasama sa main review ng row na ito. */
function chk2dMainSlots(array $row): array
{
    $slots = ['design_2d', 'quotation'];
    if ((int) ($row['include_3d'] ?? 0) === 1) {
        $slots[] = 'design_3d';
    }
    return $slots;
}

/** Mga slot na wala pang desisyon (habang Waiting for Approval). */
function chk2dPendingSlots(array $row): array
{
    if (($row['status'] ?? '') !== 'Waiting for Approval') {
        return [];
    }
    return array_values(array_filter(chk2dMainSlots($row), fn($s) => chk2dSlotStatus($row, $s) === 'Pending'));
}

function chk2dIs3dOnlyWaiting(array $row): bool
{
    return ($row['status'] ?? '') === 'Approved' && ($row['design_3d_stage'] ?? 'Locked') === 'Waiting for Approval';
}

/** Sino pa ang hinihintay (labels). */
function chk2dWaitingOn(array $row): array
{
    if (chk2dIs3dOnlyWaiting($row)) {
        return [CHK2D_VIEWER_LABEL['designer_head']];
    }
    $owners = [];
    foreach (chk2dPendingSlots($row) as $slot) {
        $owners[CHK2D_SLOT_OWNER[$slot]] = CHK2D_VIEWER_LABEL[CHK2D_SLOT_OWNER[$slot]];
    }
    return array_values($owners);
}

/** Ano ang puwedeng gawin ng naka-login: main | 3d_only | none */
function chk2dReviewTarget(array $row): string
{
    global $viewer, $mySlots;

    if ($viewer === 'superadmin') {
        return 'none';
    }
    if (($row['status'] ?? '') === 'Waiting for Approval') {
        return array_intersect($mySlots, chk2dPendingSlots($row)) ? 'main' : 'none';
    }
    if (chk2dIs3dOnlyWaiting($row) && $viewer === 'designer_head') {
        return '3d_only';
    }
    return 'none';
}

function chk2dFormatRow(array $row): array
{
    global $viewer, $mySlots;

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
        'design_2d_review_status'   => chk2dSlotStatus($row, 'design_2d'),
        'design_2d_remarks'         => $row['design_2d_remarks'],
        'design_2d_reviewer_name'   => $row['design_reviewer_name'] ?? null,
        'design_2d_reviewed_at'     => $row['design_reviewed_at'] ?? null,
        'quotation_path'            => $row['quotation_path'],
        'quotation_uploader_name'   => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role'   => chk2dRoleLabel($row['quotation_uploaded_role']),
        'quotation_uploaded_at'     => $row['quotation_uploaded_at'],
        'quotation_review_status'   => chk2dSlotStatus($row, 'quotation'),
        'quotation_remarks'         => $row['quotation_remarks'],
        'quotation_reviewer_name'   => $row['quotation_reviewer_name'] ?? null,
        'quotation_reviewed_at'     => $row['quotation_reviewed_at'] ?? null,
        'include_3d'                => (bool) $row['include_3d'],
        'design_3d_stage'           => $row['design_3d_stage'] ?? 'Locked',
        'design_3d_path'            => $row['design_3d_path'],
        'design_3d_uploader_name'   => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role'   => chk2dRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_uploaded_at'     => $row['design_3d_uploaded_at'],
        'design_3d_review_status'   => chk2dSlotStatus($row, 'design_3d'),
        'design_3d_remarks'         => $row['design_3d_remarks'],
        'design_3d_reviewer_name'   => $row['design_3d_reviewer_name'] ?? null,
        'design_3d_reviewed_at'     => $row['design_3d_reviewed_at'] ?? null,
        'review_target'             => chk2dReviewTarget($row),
        'waiting_on'                => chk2dWaitingOn($row),
        'my_slots'                  => $mySlots,
        'viewer'                    => $viewer,
        'status'                    => $row['status'],
        'remarks'                   => $row['remarks'],
        'submitted_at'              => $row['submitted_at'],
        'reviewed_at'               => $row['reviewed_at'],
        'created_at'                => $row['created_at'],
    ];
}

function chk2dRespond(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

/** Realtime: Sales + Designer ng inquiry, Superadmin, Sales Head, Designer Head (+ Accounting Head at Cutting kapag approved). */
function chk2dRealtime(mysqli $conn, int $inquiryId, bool $approved): void
{
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $groups = [
            [ROLE_SUPERADMIN, null],
            [ROLE_SALES, POSITION_HEAD],
            [ROLE_DESIGNER, POSITION_HEAD],
        ];
        if ($approved) {
            $groups[] = [ROLE_ACCOUNTING, POSITION_HEAD];
            $groups[] = [ROLE_CUTTING, null];
        }
        nhRtInquiry($conn, $inquiryId, $groups, 'crm_final_review');
    } catch (Throwable $e) {
        error_log('check2dquotationajax realtime: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════
// NOTIFICATIONS
// ═══════════════════════════════════════════════════════════

function chk2dNotifyAccountingHead(mysqli $conn, int $inquiryId, array $record, int $senderId, string $stage = 'Final'): void
{
    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
    $role = ROLE_ACCOUNTING;
    $position = POSITION_HEAD;
    $stmt->bind_param("ss", $role, $position);
    $stmt->execute();
    $accountingHeads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($accountingHeads)) {
        return;
    }

    $message = "{$stage} 2D and Quotation approved for {$record['client_name']} (Control No. {$record['control_no']})";
    $link = "/crmaccounting?id={$inquiryId}";
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
    string $stage = 'Final'
): void {
    // Ang MISMONG naka-assign lang sa inquiry (Designer o Sales) — hindi ang buong department
    $col = $role === ROLE_SALES ? 'sales_staff_id' : 'designer_id';
    $stmt = $conn->prepare("SELECT {$col} AS id FROM noblecrminquiry WHERE id = ? AND {$col} > 0 LIMIT 1");
    $stmt->bind_param("i", $inquiryId);
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

    $link = "/crm2dquotationfinal?id={$inquiryId}";
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
// LIST — Final lang
// ═══════════════════════════════════════════════════════════

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $placeholders = implode(',', array_fill(0, count(CHK2D_VISIBLE_STATUSES), '?'));

    $sql = "
        SELECT * FROM (" . chk2dSelectSql($conn, true) . ") x
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
        'success'     => true,
        'rows'        => $rows,
        'count'       => count($rows),
        'viewer'      => $viewer,
        'my_slots'    => $mySlots,
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
        chk2dRespond(false, 'Invalid record.');
    }

    $sql = chk2dSelectSql($conn, false) . " WHERE q.id = ? LIMIT 1 ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        chk2dRespond(false, 'Record not found.');
    }

    echo json_encode(['success' => true, 'record' => chk2dFormatRow($row)]);
    exit;
}

// Lahat ng susunod ay pag-save — Superadmin ay monitor lang.
if (in_array($action, ['review', 'review_3d'], true) && $viewer === 'superadmin') {
    chk2dRespond(false, 'The Final is now approved by the Sales Head and Designer Head. Superadmin can only review it here.');
}

// ═══════════════════════════════════════════════════════════
// REVIEW — desisyon ng head sa SARILI niyang slot(s)
//   Designer Head: 2D (+ bundled 3D) · Sales Head: Quotation
// ═══════════════════════════════════════════════════════════

if ($action === 'review') {

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        chk2dRespond(false, 'Invalid record.');
    }

    $conn->begin_transaction();
    try {
        // FOR UPDATE: para hindi magbanggaan ang dalawang head na sabay nag-submit
        $stmt = $conn->prepare("
            SELECT q.*, i.control_no, i.client_name
            FROM {$table} q
            JOIN noblecrminquiry i ON i.id = q.inquiry_id
            WHERE q.id = ? LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current) {
            $conn->rollback();
            chk2dRespond(false, 'Record not found.');
        }
        if ($current['status'] !== 'Waiting for Approval') {
            $conn->rollback();
            chk2dRespond(false, 'Only submissions waiting for approval can be reviewed.');
        }

        $include3d = (int) $current['include_3d'] === 1;
        $mainSlots = chk2dMainSlots($current);
        $pendingMine = array_values(array_intersect($mySlots, chk2dPendingSlots($current)));

        if (!$pendingMine) {
            $conn->rollback();
            $waiting = chk2dWaitingOn($current);
            chk2dRespond(false, $waiting
                ? 'You have already reviewed your part. Waiting for the ' . implode(' and ', $waiting) . '.'
                : 'Nothing for you to review here.');
        }

        // Basahin at i-validate ang desisyon kada slot na hawak ko
        $decisions = [];
        foreach ($pendingMine as $slot) {
            $decision = trim($_POST[$slot . '_decision'] ?? '');
            $remarks = trim($_POST[$slot . '_remarks'] ?? '');
            $label = CHK2D_SLOT_LABEL[$slot];

            if (!in_array($decision, CHK2D_REVIEW_DECISIONS, true)) {
                $conn->rollback();
                chk2dRespond(false, "Please decide on the {$label}.");
            }
            if ($decision === 'For Revision' && $remarks === '') {
                $conn->rollback();
                chk2dRespond(false, "Please provide remarks for the {$label} revision.");
            }
            if (mb_strlen($remarks) > 2000) {
                $conn->rollback();
                chk2dRespond(false, "Remarks for the {$label} are too long (max 2000 characters).");
            }
            $decisions[$slot] = ['decision' => $decision, 'remarks' => $decision === 'For Revision' ? $remarks : null];
        }

        // 3D is derived from 2D — a 3D revision sends the 2D back too (Designer Head lang ang may hawak ng dalawa)
        if ($include3d && isset($decisions['design_3d']) && $decisions['design_3d']['decision'] === 'For Revision') {
            $prev2d = $decisions['design_2d'] ?? ['decision' => chk2dSlotStatus($current, 'design_2d'), 'remarks' => $current['design_2d_remarks']];
            if ($prev2d['decision'] !== 'For Revision') {
                $decisions['design_2d'] = [
                    'decision' => 'For Revision',
                    'remarks'  => 'Automatically sent back together with the 3D file revision.',
                ];
            }
        }

        // I-save ang desisyon ng head na ito
        $set = [];
        $types = '';
        $params = [];
        $trackReviewer = chk2dReviewerColsReady($conn);

        foreach ($decisions as $slot => $d) {
            $set[] = "{$slot}_review_status = ?";
            $set[] = "{$slot}_remarks = ?";
            $types .= 'ss';
            $params[] = $d['decision'];
            $params[] = $d['remarks'];
        }
        if (isset($decisions['design_3d'])) {
            $set[] = "design_3d_reviewed_at = NOW()";
            if ($trackReviewer) {
                $set[] = "design_3d_reviewed_by = ?";
                $types .= 'i';
                $params[] = $currentUserId;
            }
        }
        if ($trackReviewer && isset($decisions['design_2d'])) {
            $set[] = "design_reviewed_by = ?";
            $set[] = "design_reviewed_at = NOW()";
            $types .= 'i';
            $params[] = $currentUserId;
        }
        if ($trackReviewer && isset($decisions['quotation'])) {
            $set[] = "quotation_reviewed_by = ?";
            $set[] = "quotation_reviewed_at = NOW()";
            $types .= 'i';
            $params[] = $currentUserId;
        }

        $types .= 'i';
        $params[] = $id;
        $stmt = $conn->prepare("UPDATE {$table} SET " . implode(', ', $set) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            $conn->rollback();
            chk2dRespond(false, 'Failed to save review.');
        }

        // Bagong estado ng row
        $after = $current;
        foreach ($decisions as $slot => $d) {
            $after[$slot . '_review_status'] = $d['decision'];
            $after[$slot . '_remarks'] = $d['remarks'];
        }
        $stillPending = chk2dPendingSlots($after);
        $finalized = !$stillPending;
        $overallStatus = 'Waiting for Approval';

        if ($finalized) {
            $allApproved = true;
            foreach ($mainSlots as $slot) {
                if (chk2dSlotStatus($after, $slot) !== 'Approved') {
                    $allApproved = false;
                }
            }
            $overallStatus = $allApproved ? 'Approved' : 'For Revision';

            $combinedRemarks = trim(implode("\n\n", array_filter([
                chk2dSlotStatus($after, 'design_2d') === 'For Revision' && $after['design_2d_remarks'] ? "2D: {$after['design_2d_remarks']}" : '',
                chk2dSlotStatus($after, 'quotation') === 'For Revision' && $after['quotation_remarks'] ? "Quotation: {$after['quotation_remarks']}" : '',
                $include3d && chk2dSlotStatus($after, 'design_3d') === 'For Revision' && $after['design_3d_remarks'] ? "3D: {$after['design_3d_remarks']}" : '',
            ]))) ?: null;

            if ($include3d) {
                $new3dStage = $overallStatus;
            } elseif ($overallStatus === 'Approved') {
                $new3dStage = 'Draft';
            } else {
                $new3dStage = 'Locked';
            }

            $stmt = $conn->prepare("
                UPDATE {$table}
                SET status = ?, design_3d_stage = ?, remarks = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $overallStatus, $new3dStage, $combinedRemarks, $id);
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                $conn->rollback();
                chk2dRespond(false, 'Failed to save review.');
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('check2dquotationajax review: ' . $e->getMessage());
        chk2dRespond(false, 'Failed to save review.');
    }

    $inquiryId = (int) $current['inquiry_id'];
    $recordForNotif = ['control_no' => $current['control_no'], 'client_name' => $current['client_name']];

    if ($finalized) {
        try {
            if ($overallStatus === 'Approved') {
                chk2dNotifyAccountingHead($conn, $inquiryId, $recordForNotif, $currentUserId, $stage);
            } else {
                if (chk2dSlotStatus($after, 'design_2d') === 'For Revision' || ($include3d && chk2dSlotStatus($after, 'design_3d') === 'For Revision')) {
                    $label = ($include3d && chk2dSlotStatus($after, 'design_3d') === 'For Revision') ? '2D / 3D File' : '2D File';
                    chk2dNotifyRoleRevision($conn, ROLE_DESIGNER, $inquiryId, $recordForNotif, $currentUserId, $label, $after['design_2d_remarks'], $stage);
                }
                if (chk2dSlotStatus($after, 'quotation') === 'For Revision') {
                    chk2dNotifyRoleRevision($conn, ROLE_SALES, $inquiryId, $recordForNotif, $currentUserId, 'Quotation File', $after['quotation_remarks'], $stage);
                }
            }
        } catch (Throwable $e) {
            error_log('check2dquotationajax notify: ' . $e->getMessage());
        }
    }

    chk2dRealtime($conn, $inquiryId, $finalized && $overallStatus === 'Approved');

    if (!$finalized) {
        $waiting = chk2dWaitingOn($after);
        chk2dRespond(true, 'Your review is saved. Waiting for the ' . implode(' and ', $waiting) . '.');
    }
    chk2dRespond(true, $overallStatus === 'Approved' ? 'Final approved — both heads are done.' : 'Sent back for revision.');
}

// ═══════════════════════════════════════════════════════════
// REVIEW 3D — standalone (sequential) 3D decision · Designer Head lang
// ═══════════════════════════════════════════════════════════

if ($action === 'review_3d') {

    if ($viewer !== 'designer_head') {
        chk2dRespond(false, 'Only the Designer Head can review the 3D file.');
    }

    $id = intval($_POST['id'] ?? 0);
    $design3dDecision = trim($_POST['design_3d_decision'] ?? '');
    $design3dRemarks  = trim($_POST['design_3d_remarks'] ?? '');

    if ($id <= 0) {
        chk2dRespond(false, 'Invalid record.');
    }
    if (!in_array($design3dDecision, CHK2D_REVIEW_DECISIONS, true)) {
        chk2dRespond(false, 'Please decide on the 3D file.');
    }
    if ($design3dDecision === 'For Revision' && $design3dRemarks === '') {
        chk2dRespond(false, 'Please provide remarks for the 3D file revision.');
    }
    if (mb_strlen($design3dRemarks) > 2000) {
        chk2dRespond(false, 'Remarks are too long (max 2000 characters).');
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
        chk2dRespond(false, 'Record not found.');
    }
    if (!chk2dIs3dOnlyWaiting($current)) {
        chk2dRespond(false, 'This 3D file is not currently waiting for approval.');
    }

    $recordForNotif = [
        'control_no'  => $current['control_no'],
        'client_name' => $current['client_name'],
    ];
    $trackReviewer = chk2dReviewerColsReady($conn);

    if ($design3dDecision === 'Approved') {

        $by = $trackReviewer ? ", design_3d_reviewed_by = ?" : '';
        $stmt = $conn->prepare("
            UPDATE {$table}
            SET design_3d_stage = 'Approved', design_3d_review_status = 'Approved',
                design_3d_remarks = NULL, design_3d_reviewed_at = NOW(){$by}
            WHERE id = ? AND design_3d_stage = 'Waiting for Approval'
        ");
        if ($trackReviewer) {
            $stmt->bind_param("ii", $currentUserId, $id);
        } else {
            $stmt->bind_param("i", $id);
        }
        $ok = $stmt->execute();
        $stmt->close();

        $message = '3D file approved.';

    } else {

        $cascadeNote = 'Revision requested on the 3D file — please also review/update the 2D file.';

        $newStatus = 'For Revision';
        $include3d = 1;
        $newStage3d = 'Draft';

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

        $types = 'isississsississ';
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
        $newId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();

        // Itala kung sino ang nag-revision ng 3D
        if ($ok && $trackReviewer && $newId > 0) {
            $stmt = $conn->prepare("UPDATE {$table} SET design_3d_reviewed_by = ?, design_3d_reviewed_at = NOW(),
                design_reviewed_by = ?, design_reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("iii", $currentUserId, $currentUserId, $newId);
            $stmt->execute();
            $stmt->close();
        }

        if ($ok) {
            try {
                chk2dNotifyRoleRevision(
                    $conn, ROLE_DESIGNER, (int) $current['inquiry_id'], $recordForNotif,
                    $currentUserId, '2D File (triggered by 3D revision)', $cascadeNote, $stage
                );
            } catch (Throwable $e) {
                error_log('check2dquotationajax notify 3d: ' . $e->getMessage());
            }
        }

        $message = 'Sent back for revision — the 2D file has also been reopened for rework.';
    }

    if ($ok) {
        chk2dRealtime($conn, (int) $current['inquiry_id'], false);
    }

    chk2dRespond((bool) $ok, $ok ? $message : 'Failed to save review.');
}

chk2dRespond(false, 'Unknown action.');