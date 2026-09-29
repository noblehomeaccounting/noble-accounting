<?php
// checkdesigner2dquotationajax.php
//
// Initial 2D & Quotation approval — DESIGNER HEAD only.
// (Final approval stays with Superadmin in check2dquotationajax.php)
// MULTI-ATTACHMENT + PER-FILE REVIEW VERSION:
// nagbabasa/sumusulat na ng noblecrm_2dquotation_files (review_status, remarks, reviewed_at kada file).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);

// ── Designer HEAD lang ang pwede (hindi ang ibang designer staff) ──
$headStmt = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
$headRole = ROLE_DESIGNER;
$headPos = POSITION_HEAD;
$headStmt->bind_param("iss", $currentUserId, $headRole, $headPos);
$headStmt->execute();
$isDesignerHead = (bool) $headStmt->get_result()->fetch_assoc();
$headStmt->close();

if (!$isDesignerHead) {
    echo json_encode(['success' => false, 'message' => 'Only the Designer Head can review Initial submissions.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

const DCHK_VISIBLE_STATUSES = ['Waiting for Approval', 'Approved', 'For Revision'];
const DCHK_REVIEW_DECISIONS = ['Approved', 'For Revision'];
const DCHK_TABLE = 'noblecrm_2dquotation';
const DCHK_FILES_TABLE = 'noblecrm_2dquotation_files';

function dchkSelectSql(bool $latestOnly): string
{
    $table = DCHK_TABLE;

    $latestJoin = $latestOnly ? "
        JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM {$table}
            GROUP BY inquiry_id
        ) latest ON latest.inquiry_id = q.inquiry_id AND latest.max_id = q.id
    " : '';

    return "
        SELECT
            q.id, q.inquiry_id, q.design_2d_path, q.design_2d_uploaded_by, q.design_2d_uploaded_role,
            q.design_2d_done, q.design_2d_uploaded_at,
            q.quotation_path, q.quotation_uploaded_by, q.quotation_uploaded_role,
            q.quotation_done, q.quotation_uploaded_at,
            q.design_2d_review_status, q.design_2d_remarks,
            q.quotation_review_status, q.quotation_remarks,
            q.include_3d, q.design_3d_stage, q.design_3d_path, q.design_3d_uploaded_by, q.design_3d_uploaded_role,
            q.design_3d_done, q.design_3d_uploaded_at, q.design_3d_review_status, q.design_3d_remarks,
            q.status, q.remarks, q.submitted_at, q.created_at, q.reviewed_at, q.is_late,
            i.control_no, i.client_name, i.contact_number, i.project_type, i.branch, i.contract_amount,
            i.target_completion_date, i.designer_id, i.sales_staff_id,
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

function dchkRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    return '—';
}

function dchkUrl(?string $path): ?string
{
    return !empty($path) ? BASE_URL . '/' . $path : null;
}

// ═══════════════════════════════════════════════════════════
// MULTI-FILE HELPERS
// ═══════════════════════════════════════════════════════════

// [quotation_id][slot] => [ {id,url,name,review_status,remarks}, ... ]
function dchkLoadFilesMap(mysqli $conn, array $quotationIds): array
{
    $ids = array_values(array_filter(array_map('intval', $quotationIds)));
    if (empty($ids)) {
        return [];
    }
    $in = implode(',', $ids);
    $table = DCHK_FILES_TABLE;
    $res = $conn->query("
        SELECT id, quotation_id, slot, path, original_name, review_status, remarks
        FROM {$table}
        WHERE quotation_id IN ({$in})
        ORDER BY id ASC
    ");
    $map = [];
    while ($r = $res->fetch_assoc()) {
        $map[(int) $r['quotation_id']][$r['slot']][] = [
            'id' => (int) $r['id'],
            'url' => dchkUrl($r['path']),
            'name' => !empty($r['original_name']) ? $r['original_name'] : basename($r['path']),
            'review_status' => $r['review_status'] ?? 'Pending',
            'remarks' => $r['remarks'],
        ];
    }
    return $map;
}

// Mga file ng isang slot. Kung wala pang rows (lumang data) pero may *_path → fallback sa path (id = 0).
function dchkSlotFiles(array $row, string $slot, array $filesMap): array
{
    $files = $filesMap[(int) $row['id']][$slot] ?? [];
    if (!empty($files)) {
        return $files;
    }
    $pathKey    = $slot === '2d' ? 'design_2d_path' : ($slot === '3d' ? 'design_3d_path' : 'quotation_path');
    $statusKey  = $slot === '2d' ? 'design_2d_review_status' : ($slot === '3d' ? 'design_3d_review_status' : 'quotation_review_status');
    $remarksKey = $slot === '2d' ? 'design_2d_remarks' : ($slot === '3d' ? 'design_3d_remarks' : 'quotation_remarks');
    $path = $row[$pathKey] ?? null;
    if (!empty($path)) {
        return [[
            'id' => 0,
            'url' => dchkUrl($path),
            'name' => basename($path),
            'review_status' => $row[$statusKey] ?? 'Pending',
            'remarks' => $row[$remarksKey] ?? null,
        ]];
    }
    return [];
}

// Kopya ng lahat ng files papunta sa bagong row, dala ang per-file review status.
// $cascade2d = true → lahat ng 2D files ay ibabalik sa For Revision (3D revision cascade).
function dchkCopyAllFiles(mysqli $conn, int $fromId, int $toId, bool $cascade2d = false, string $cascadeNote = ''): bool
{
    $table = DCHK_FILES_TABLE;
    $stmt = $conn->prepare("
        INSERT INTO {$table}
            (quotation_id, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role, uploaded_at,
             review_status, remarks, reviewed_at)
        SELECT ?, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role, uploaded_at,
               IF(? = 1 AND slot = '2d', 'For Revision', review_status),
               IF(? = 1 AND slot = '2d', ?, remarks),
               IF(? = 1 AND slot = '2d', NOW(), reviewed_at)
        FROM {$table}
        WHERE quotation_id = ?
        ORDER BY id ASC
    ");
    $c = $cascade2d ? 1 : 0;
    $stmt->bind_param("iiisii", $toId, $c, $c, $cascadeNote, $c, $fromId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ═══════════════════════════════════════════════════════════
// PER-FILE REVIEW HELPERS
// ═══════════════════════════════════════════════════════════

// [slot] => [ {id, review_status, remarks}, ... ]  (ayos ayon sa id = kapareho ng "View File N")
function dchkLoadSlotFileRows(mysqli $conn, int $quotationId): array
{
    $table = DCHK_FILES_TABLE;
    $stmt = $conn->prepare("SELECT id, slot, review_status, remarks FROM {$table} WHERE quotation_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $quotationId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = ['2d' => [], 'quotation' => [], '3d' => []];
    foreach ($rows as $r) {
        $out[$r['slot']][] = $r;
    }
    return $out;
}

// file_decisions = JSON [{id, decision, remarks}, ...]
function dchkParseDecisions(): array
{
    $raw = json_decode($_POST['file_decisions'] ?? '[]', true);
    $map = [];
    if (is_array($raw)) {
        foreach ($raw as $d) {
            if (is_array($d) && isset($d['id'])) {
                $map[(int) $d['id']] = [
                    'decision' => trim((string) ($d['decision'] ?? '')),
                    'remarks'  => trim((string) ($d['remarks'] ?? '')),
                ];
            }
        }
    }
    return $map;
}

// Aggregate ng isang slot: Approved kung lahat approved, For Revision kung kahit isa ang revision.
function dchkSummarizeSlot(array $items): array
{
    $status = 'Approved';
    $notes = [];
    $multi = count($items) > 1;
    foreach ($items as $it) {
        if ($it['decision'] === 'For Revision') {
            $status = 'For Revision';
            if (!empty($it['remarks'])) {
                $notes[] = $multi ? "File {$it['n']}: {$it['remarks']}" : $it['remarks'];
            }
        }
    }
    return ['error' => null, 'items' => $items, 'status' => $status, 'remarks' => $notes ? implode("\n", $notes) : null];
}

// I-validate ang mga desisyon ng isang slot. Ang file na Approved na dati ay naka-lock.
function dchkEvaluateSlot(array $files, array $input, string $label): array
{
    if (empty($files)) {
        return ['error' => "No {$label} file found to review."];
    }
    $items = [];
    $multi = count($files) > 1;
    foreach ($files as $idx => $f) {
        $fid = (int) $f['id'];
        $n = $idx + 1;
        $name = $multi ? "{$label} File {$n}" : "{$label} file";

        if ($f['review_status'] === 'Approved') {
            $items[$fid] = ['n' => $n, 'decision' => 'Approved', 'remarks' => null, 'locked' => true];
            continue;
        }

        $decision = $input[$fid]['decision'] ?? '';
        $remarks  = $input[$fid]['remarks'] ?? '';
        if (!in_array($decision, DCHK_REVIEW_DECISIONS, true)) {
            return ['error' => "Please decide on {$name}."];
        }
        if ($decision === 'For Revision' && $remarks === '') {
            return ['error' => "Please provide remarks for {$name}."];
        }
        $items[$fid] = [
            'n' => $n,
            'decision' => $decision,
            'remarks' => $decision === 'For Revision' ? $remarks : null,
            'locked' => false,
        ];
    }
    return dchkSummarizeSlot($items);
}

// 3D revision → lahat ng 2D file ay sasama pabalik.
function dchkForceRevision(array $slot, string $note): array
{
    foreach ($slot['items'] as $fid => $it) {
        if ($it['decision'] === 'Approved') {
            $slot['items'][$fid] = ['n' => $it['n'], 'decision' => 'For Revision', 'remarks' => $note, 'locked' => false];
        }
    }
    return dchkSummarizeSlot($slot['items']);
}

function dchkSaveFileReviews(mysqli $conn, int $quotationId, array $items): bool
{
    $table = DCHK_FILES_TABLE;
    $stmt = $conn->prepare("
        UPDATE {$table}
        SET review_status = ?, remarks = ?, reviewed_at = NOW()
        WHERE id = ? AND quotation_id = ?
    ");
    $decision = '';
    $remarks = null;
    $fid = 0;
    $stmt->bind_param("ssii", $decision, $remarks, $fid, $quotationId);
    $ok = true;
    foreach ($items as $itemId => $it) {
        if (!empty($it['locked'])) continue;   // hindi ginagalaw ang approved na dati
        $fid = (int) $itemId;
        $decision = $it['decision'];
        $remarks = $it['remarks'];
        if (!$stmt->execute()) { $ok = false; break; }
    }
    $stmt->close();
    return $ok;
}

function dchkReviewTarget(array $row): string
{
    if ($row['status'] === 'Waiting for Approval') {
        return (int) $row['include_3d'] === 1 ? 'main_with_3d' : 'main';
    }
    if ($row['status'] === 'Approved' && ($row['design_3d_stage'] ?? 'Locked') === 'Waiting for Approval') {
        return '3d_only';
    }
    return 'none';
}

function dchkFormatRow(array $row, array $filesMap): array
{
    return [
        'id'                        => (int) $row['id'],
        'inquiry_id'                => (int) $row['inquiry_id'],
        'control_no'                => $row['control_no'],
        'client_name'               => $row['client_name'],
        'contact_number'            => $row['contact_number'],
        'project_type'              => $row['project_type'],
        'branch'                    => $row['branch'],
        'contract_amount'           => $row['contract_amount'],
        'target_completion_date'    => $row['target_completion_date'],
        'is_late'                   => (bool) ($row['is_late'] ?? 0),
        'design_2d_path'            => $row['design_2d_path'],
        'design_2d_files'           => dchkSlotFiles($row, '2d', $filesMap),
        'design_2d_uploader_name'   => $row['design_2d_uploader_name'] ?? '—',
        'design_2d_uploaded_role'   => dchkRoleLabel($row['design_2d_uploaded_role']),
        'design_2d_review_status'   => $row['design_2d_review_status'] ?? 'Pending',
        'design_2d_remarks'         => $row['design_2d_remarks'],
        'quotation_path'            => $row['quotation_path'],
        'quotation_files'           => dchkSlotFiles($row, 'quotation', $filesMap),
        'quotation_uploader_name'   => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role'   => dchkRoleLabel($row['quotation_uploaded_role']),
        'quotation_review_status'   => $row['quotation_review_status'] ?? 'Pending',
        'quotation_remarks'         => $row['quotation_remarks'],
        'include_3d'                => (bool) $row['include_3d'],
        'design_3d_stage'           => $row['design_3d_stage'] ?? 'Locked',
        'design_3d_path'            => $row['design_3d_path'],
        'design_3d_files'           => dchkSlotFiles($row, '3d', $filesMap),
        'design_3d_uploader_name'   => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role'   => dchkRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_review_status'   => $row['design_3d_review_status'] ?? 'Pending',
        'design_3d_remarks'         => $row['design_3d_remarks'],
        'review_target'             => dchkReviewTarget($row),
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

function dchkInsertNotification(mysqli $conn, int $userId, int $inquiryId, string $controlNo, string $message, int $senderId, string $link): void
{
    $stmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_2dquotation', ?, 0, NOW(), ?, ?)
    ");
    $stmt->bind_param("iissis", $userId, $inquiryId, $controlNo, $message, $senderId, $link);
    $stmt->execute();
    $stmt->close();
}

// Initial approved → Accounting Head (same behavior as before)
function dchkNotifyAccountingHead(mysqli $conn, int $inquiryId, array $record, int $senderId): void
{
    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
    $role = ROLE_ACCOUNTING;
    $position = POSITION_HEAD;
    $stmt->bind_param("ss", $role, $position);
    $stmt->execute();
    $heads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $message = "Initial 2D and Quotation approved for {$record['client_name']} (Control No. {$record['control_no']})";
    $link = "/crmaccounting?id={$inquiryId}"; // ⚠️ verify destination
    foreach ($heads as $h) {
        dchkInsertNotification($conn, (int) $h['id'], $inquiryId, $record['control_no'], $message, $senderId, $link);
    }
}

// Revision → ang MISMONG assigned designer / sales ng inquiry lang (hindi lahat ng designer)
function dchkNotifyUserRevision(mysqli $conn, ?int $userId, int $inquiryId, array $record, int $senderId, string $fileLabel, ?string $remarks): void
{
    if (!$userId) return;

    $message = "Initial {$fileLabel} for {$record['client_name']} (Control No. {$record['control_no']}) needs revision";
    if ($remarks) {
        $message .= ": {$remarks}";
    }
    $link = "/crm2dquotation?id={$inquiryId}";
    dchkInsertNotification($conn, $userId, $inquiryId, $record['control_no'], $message, $senderId, $link);
}

// ═══════════════════════════════════════════════════════════
// LIST
// ═══════════════════════════════════════════════════════════

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    $placeholders = implode(',', array_fill(0, count(DCHK_VISIBLE_STATUSES), '?'));

    $sql = "
        SELECT * FROM (" . dchkSelectSql(true) . ") x
        WHERE (
            x.status IN ({$placeholders})
            OR (x.status = 'Approved' AND x.design_3d_stage = 'Waiting for Approval')
        )
    ";
    $types = str_repeat('s', count(DCHK_VISIBLE_STATUSES));
    $params = DCHK_VISIBLE_STATUSES;

    if ($statusFilter !== '' && in_array($statusFilter, DCHK_VISIBLE_STATUSES, true)) {
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
    $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $filesMap = dchkLoadFilesMap($conn, array_column($rawRows, 'id'));

    $rows = [];
    foreach ($rawRows as $row) {
        $rows[] = dchkFormatRow($row, $filesMap);
    }

    echo json_encode(['success' => true, 'rows' => $rows, 'count' => count($rows), 'server_time' => date('c')]);
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

    $stmt = $conn->prepare(dchkSelectSql(false) . " WHERE q.id = ? LIMIT 1 ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    $filesMap = dchkLoadFilesMap($conn, [(int) $row['id']]);

    echo json_encode(['success' => true, 'record' => dchkFormatRow($row, $filesMap)]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// REVIEW — 2D + Quotation (+ bundled 3D), PER-FILE decisions
// ═══════════════════════════════════════════════════════════

if ($action === 'review') {

    $table = DCHK_TABLE;
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT q.status, q.inquiry_id, q.include_3d, q.design_2d_uploaded_by,
               i.control_no, i.client_name, i.designer_id, i.sales_staff_id
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

    // OPTIONAL: bawal i-approve ang sariling gawa. I-uncomment kung gusto mo.
    // if ((int) $current['design_2d_uploaded_by'] === $currentUserId) {
    //     echo json_encode(['success' => false, 'message' => 'You cannot review your own submission.']);
    //     exit;
    // }

    $include3d = (int) $current['include_3d'];
    $input = dchkParseDecisions();
    $slotFiles = dchkLoadSlotFileRows($conn, $id);

    $r2d = dchkEvaluateSlot($slotFiles['2d'], $input, '2D');
    $rq  = dchkEvaluateSlot($slotFiles['quotation'], $input, 'Quotation');
    $r3d = $include3d ? dchkEvaluateSlot($slotFiles['3d'], $input, '3D') : null;

    foreach ([$r2d, $rq, $r3d] as $r) {
        if ($r && !empty($r['error'])) {
            echo json_encode(['success' => false, 'message' => $r['error']]);
            exit;
        }
    }

    // 3D is derived from 2D — a 3D revision sends the 2D files back too.
    if ($r3d && $r3d['status'] === 'For Revision' && $r2d['status'] === 'Approved') {
        $r2d = dchkForceRevision($r2d, 'Automatically sent back together with the 3D file revision.');
    }

    // Slot-level (aggregate) values — para gumana pa rin ang ibang pages
    $design2dDecision  = $r2d['status'];
    $quotationDecision = $rq['status'];
    $design3dDecision  = $r3d ? $r3d['status'] : '';

    $overallStatus = ($design2dDecision === 'Approved' && $quotationDecision === 'Approved'
        && (!$include3d || $design3dDecision === 'Approved'))
        ? 'Approved'
        : 'For Revision';

    $design2dRemarksToSave  = $design2dDecision === 'For Revision' ? $r2d['remarks'] : null;
    $quotationRemarksToSave = $quotationDecision === 'For Revision' ? $rq['remarks'] : null;
    $design3dRemarksToSave  = ($include3d && $design3dDecision === 'For Revision') ? $r3d['remarks'] : null;

    $combinedRemarks = trim(implode("\n\n", array_filter([
        $design2dRemarksToSave ? "2D: {$design2dRemarksToSave}" : '',
        $quotationRemarksToSave ? "Quotation: {$quotationRemarksToSave}" : '',
        $design3dRemarksToSave ? "3D: {$design3dRemarksToSave}" : '',
    ]))) ?: null;

    if ($include3d) {
        $new3dStage = $overallStatus;
    } elseif ($overallStatus === 'Approved') {
        $new3dStage = 'Draft';   // unlock 3D upload
    } else {
        $new3dStage = 'Locked';
    }

    $conn->begin_transaction();

    // 1) per-file status
    $ok = dchkSaveFileReviews($conn, $id, $r2d['items'])
        && dchkSaveFileReviews($conn, $id, $rq['items'])
        && ($r3d ? dchkSaveFileReviews($conn, $id, $r3d['items']) : true);

    // 2) slot-level status ng submission
    if ($ok) {
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
        $types = 'sssssisissisi';
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
    }

    if ($ok) {
        $conn->commit();
    } else {
        $conn->rollback();
    }

    if ($ok) {
        $recordForNotif = ['control_no' => $current['control_no'], 'client_name' => $current['client_name']];
        $inquiryId = (int) $current['inquiry_id'];

        if ($overallStatus === 'Approved') {
            dchkNotifyAccountingHead($conn, $inquiryId, $recordForNotif, $currentUserId);
        } else {
            if ($design2dDecision === 'For Revision') {
                dchkNotifyUserRevision($conn, (int) $current['designer_id'], $inquiryId, $recordForNotif, $currentUserId, '2D File', $design2dRemarksToSave);
            }
            if ($quotationDecision === 'For Revision') {
                dchkNotifyUserRevision($conn, (int) $current['sales_staff_id'], $inquiryId, $recordForNotif, $currentUserId, 'Quotation File', $quotationRemarksToSave);
            }
        }
    }

    $message = $overallStatus === 'Approved' ? 'Submission approved.' : 'Sent back for revision.';
    echo json_encode(['success' => (bool) $ok, 'message' => $ok ? $message : 'Failed to save review.']);
    exit;
}

// ═══════════════════════════════════════════════════════════
// REVIEW 3D — standalone (sequential) 3D decision, PER-FILE
// ═══════════════════════════════════════════════════════════

if ($action === 'review_3d') {

    $table = DCHK_TABLE;
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT q.*, i.control_no, i.client_name, i.designer_id
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

    $slotFiles = dchkLoadSlotFileRows($conn, $id);
    $r3d = dchkEvaluateSlot($slotFiles['3d'], dchkParseDecisions(), '3D');
    if (!empty($r3d['error'])) {
        echo json_encode(['success' => false, 'message' => $r3d['error']]);
        exit;
    }

    $recordForNotif = ['control_no' => $current['control_no'], 'client_name' => $current['client_name']];
    $message = 'Submission approved.';

    $conn->begin_transaction();

    // Isave muna ang per-file status sa kasalukuyang row (para makopya rin pag may revision)
    $ok = dchkSaveFileReviews($conn, $id, $r3d['items']);

    if ($ok && $r3d['status'] === 'Approved') {

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

    } elseif ($ok) {

        $cascadeNote = 'Revision requested on the 3D file — please also review/update the 2D file.';
        $design3dRemarks = $r3d['remarks'];
        $newStatus = 'For Revision';
        $include3d = 1;
        $newStage3d = 'Draft';

        $stmt = $conn->prepare("
            INSERT INTO {$table}
                (inquiry_id, stage, status, created_at, include_3d,
                 design_2d_done, design_2d_path, design_2d_uploaded_role, design_2d_uploaded_by, design_2d_review_status, design_2d_remarks,
                 quotation_done, quotation_path, quotation_uploaded_role, quotation_uploaded_by, quotation_uploaded_at, quotation_review_status,
                 design_3d_done, design_3d_path, design_3d_uploaded_role, design_3d_uploaded_by, design_3d_review_status, design_3d_remarks, design_3d_stage)
            VALUES
                (?, 'Initial', ?, NOW(), ?,
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
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        // Kopyahin ang lahat ng attachments: 2D → For Revision (cascade), Quotation/3D → dala ang per-file status
        if ($ok && $newId > 0) {
            $ok = dchkCopyAllFiles($conn, (int) $current['id'], $newId, true, $cascadeNote);
        }

        $message = 'Sent back for revision — the 2D file has also been reopened for rework.';

        if ($ok) {
            dchkNotifyUserRevision(
                $conn, (int) $current['designer_id'], (int) $current['inquiry_id'], $recordForNotif,
                $currentUserId, '2D File (triggered by 3D revision)', $cascadeNote
            );
        }
    }

    if ($ok) {
        $conn->commit();
    } else {
        $conn->rollback();
    }

    echo json_encode(['success' => (bool) $ok, 'message' => $ok ? $message : 'Failed to save review.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);