<?php
// checkdesigner2dquotationajax.php
//
// Initial 2D & Quotation approval — hati na sa dalawang head:
//   • Designer Head → 2D at 3D files
//   • Sales Head    → Quotation files
// Approved lang ang Initial kapag tapos na ang dalawa (walang "Pending" na slot).
// Kapag may isang head na nag-revision, hinihintay pa rin ang kabila para isang balikan lang.
// (Ang Final ay nasa check2dquotationajax.php — parehong hati.)
// MULTI-ATTACHMENT + PER-FILE REVIEW VERSION:
// nagbabasa/sumusulat na ng noblecrm_2dquotation_files (review_status, remarks, reviewed_at kada file).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER, ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);

// ── HEAD lang ang pwede (Designer Head o Sales Head) — hindi ang ibang staff ──
$currentRole = $_SESSION['role'] ?? '';
$headStmt = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
$headPos = POSITION_HEAD;
$headStmt->bind_param("iss", $currentUserId, $currentRole, $headPos);
$headStmt->execute();
$isHead = (bool) $headStmt->get_result()->fetch_assoc();
$headStmt->close();

if (!$isHead) {
    echo json_encode(['success' => false, 'message' => 'Only the Designer Head or Sales Head can review Initial submissions.']);
    exit;
}

$viewer = $currentRole === ROLE_SALES ? 'sales_head' : 'designer_head';

// slot (files table) => may-ari
const DCHK_SLOT_OWNER = ['2d' => 'designer_head', '3d' => 'designer_head', 'quotation' => 'sales_head'];
const DCHK_SLOT_PREFIX = ['2d' => 'design_2d', 'quotation' => 'quotation', '3d' => 'design_3d'];
const DCHK_SLOT_LABEL = ['2d' => '2D', 'quotation' => 'Quotation', '3d' => '3D'];
const DCHK_OWNER_LABEL = ['designer_head' => 'Designer Head', 'sales_head' => 'Sales Head'];

$mySlots = array_keys(array_filter(DCHK_SLOT_OWNER, fn($o) => $o === $viewer));

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

const DCHK_VISIBLE_STATUSES = ['Waiting for Approval', 'Approved', 'For Revision'];
const DCHK_REVIEW_DECISIONS = ['Approved', 'For Revision'];
const DCHK_TABLE = 'noblecrm_2dquotation';
const DCHK_FILES_TABLE = 'noblecrm_2dquotation_files';

function dchkSelectSql(bool $latestOnly): string
{
    global $conn;
    $table = DCHK_TABLE;

    if (dchkReviewerColsReady($conn)) {
        $reviewerCols = "q.design_reviewed_at, q.quotation_reviewed_at, q.design_3d_reviewed_at,
            d2r.name AS design_reviewer_name, qtr.name AS quotation_reviewer_name, d3r.name AS design_3d_reviewer_name";
        $reviewerJoins = "
        LEFT JOIN noblerole d2r ON d2r.id = q.design_reviewed_by
        LEFT JOIN noblerole qtr ON qtr.id = q.quotation_reviewed_by
        LEFT JOIN noblerole d3r ON d3r.id = q.design_3d_reviewed_by";
    } else {
        $reviewerCols = "NULL AS design_reviewed_at, NULL AS quotation_reviewed_at, NULL AS design_3d_reviewed_at,
            NULL AS design_reviewer_name, NULL AS quotation_reviewer_name, NULL AS design_3d_reviewer_name";
        $reviewerJoins = '';
    }

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
            d3u.name AS design_3d_uploader_name,
            {$reviewerCols}
        FROM {$table} q
        {$latestJoin}
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole d2u ON d2u.id = q.design_2d_uploaded_by
        LEFT JOIN noblerole qtu ON qtu.id = q.quotation_uploaded_by
        LEFT JOIN noblerole d3u ON d3u.id = q.design_3d_uploaded_by
        {$reviewerJoins}
    ";
}

/** May column ba (para hindi masira kung hindi pa na-run ang SQL ng reviewer columns). */
function dchkReviewerColsReady(mysqli $conn): bool
{
    static $ready = null;
    if ($ready === null) {
        $cols = [];
        $res = @$conn->query("SHOW COLUMNS FROM " . DCHK_TABLE);
        if ($res) {
            while ($c = $res->fetch_assoc()) $cols[$c['Field']] = true;
        }
        $ready = isset($cols['design_reviewed_by'], $cols['design_reviewed_at'], $cols['quotation_reviewed_by'],
            $cols['quotation_reviewed_at'], $cols['design_3d_reviewed_by']);
    }
    return $ready;
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

// ── HATI NG REVIEW (Designer Head = 2D/3D · Sales Head = Quotation) ──

function dchkMainSlots(array $row): array
{
    $slots = ['2d', 'quotation'];
    if ((int) ($row['include_3d'] ?? 0) === 1) {
        $slots[] = '3d';
    }
    return $slots;
}

/** Status ng slot (NULL / blank = Pending). */
function dchkSlotStatus(array $row, string $slot): string
{
    $v = $row[DCHK_SLOT_PREFIX[$slot] . '_review_status'] ?? null;
    return in_array($v, DCHK_REVIEW_DECISIONS, true) ? $v : 'Pending';
}

/**
 * Mga slot na wala pang desisyon habang Waiting for Approval.
 * Kasama rin ang slot na "Approved" pero may bagong file na hindi pa nare-review.
 */
function dchkPendingSlots(array $row, array $filesBySlot): array
{
    if (($row['status'] ?? '') !== 'Waiting for Approval') {
        return [];
    }
    $out = [];
    foreach (dchkMainSlots($row) as $slot) {
        $st = dchkSlotStatus($row, $slot);
        if ($st === 'Pending') {
            $out[] = $slot;
            continue;
        }
        if ($st === 'Approved') {
            foreach ($filesBySlot[$slot] ?? [] as $f) {
                if ((int) $f['id'] > 0 && !in_array($f['review_status'] ?? 'Pending', DCHK_REVIEW_DECISIONS, true)) {
                    $out[] = $slot;
                    break;
                }
            }
        }
    }
    return $out;
}

function dchkIs3dOnlyWaiting(array $row): bool
{
    return ($row['status'] ?? '') === 'Approved' && ($row['design_3d_stage'] ?? 'Locked') === 'Waiting for Approval';
}

function dchkWaitingOn(array $row, array $filesBySlot): array
{
    if (dchkIs3dOnlyWaiting($row)) {
        return [DCHK_OWNER_LABEL['designer_head']];
    }
    $owners = [];
    foreach (dchkPendingSlots($row, $filesBySlot) as $slot) {
        $owners[DCHK_SLOT_OWNER[$slot]] = DCHK_OWNER_LABEL[DCHK_SLOT_OWNER[$slot]];
    }
    return array_values($owners);
}

/** Ano ang puwedeng gawin ng naka-login: main | 3d_only | none */
function dchkReviewTarget(array $row, array $filesBySlot = []): string
{
    global $viewer, $mySlots;
    if ($row['status'] === 'Waiting for Approval') {
        return array_intersect($mySlots, dchkPendingSlots($row, $filesBySlot)) ? 'main' : 'none';
    }
    if (dchkIs3dOnlyWaiting($row) && $viewer === 'designer_head') {
        return '3d_only';
    }
    return 'none';
}

function dchkFormatRow(array $row, array $filesMap): array
{
    global $viewer, $mySlots;
    $bySlot = [
        '2d'        => dchkSlotFiles($row, '2d', $filesMap),
        'quotation' => dchkSlotFiles($row, 'quotation', $filesMap),
        '3d'        => dchkSlotFiles($row, '3d', $filesMap),
    ];
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
        'design_2d_files'           => $bySlot['2d'],
        'design_2d_uploader_name'   => $row['design_2d_uploader_name'] ?? '—',
        'design_2d_uploaded_role'   => dchkRoleLabel($row['design_2d_uploaded_role']),
        'design_2d_review_status'   => dchkSlotStatus($row, '2d'),
        'design_2d_reviewer_name'   => $row['design_reviewer_name'] ?? null,
        'design_2d_reviewed_at'     => $row['design_reviewed_at'] ?? null,
        'design_2d_remarks'         => $row['design_2d_remarks'],
        'quotation_path'            => $row['quotation_path'],
        'quotation_files'           => $bySlot['quotation'],
        'quotation_uploader_name'   => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role'   => dchkRoleLabel($row['quotation_uploaded_role']),
        'quotation_review_status'   => dchkSlotStatus($row, 'quotation'),
        'quotation_reviewer_name'   => $row['quotation_reviewer_name'] ?? null,
        'quotation_reviewed_at'     => $row['quotation_reviewed_at'] ?? null,
        'quotation_remarks'         => $row['quotation_remarks'],
        'include_3d'                => (bool) $row['include_3d'],
        'design_3d_stage'           => $row['design_3d_stage'] ?? 'Locked',
        'design_3d_path'            => $row['design_3d_path'],
        'design_3d_files'           => $bySlot['3d'],
        'design_3d_uploader_name'   => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role'   => dchkRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_review_status'   => dchkSlotStatus($row, '3d'),
        'design_3d_reviewer_name'   => $row['design_3d_reviewer_name'] ?? null,
        'design_3d_reviewed_at'     => $row['design_3d_reviewed_at'] ?? null,
        'design_3d_remarks'         => $row['design_3d_remarks'],
        'review_target'             => dchkReviewTarget($row, $bySlot),
        'waiting_on'                => dchkWaitingOn($row, $bySlot),
        'my_slots'                  => $mySlots,
        'viewer'                    => $viewer,
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

    echo json_encode(['success' => true, 'rows' => $rows, 'count' => count($rows), 'viewer' => $viewer, 'server_time' => date('c')]);
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

    $fail = function (string $msg) use ($conn) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    };

    $conn->begin_transaction();
    try {
        // FOR UPDATE: para hindi magbanggaan ang dalawang head na sabay nag-submit
        $stmt = $conn->prepare("
            SELECT q.*, i.control_no, i.client_name, i.designer_id, i.sales_staff_id
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
            $fail('Record not found.');
        }
        if ($current['status'] !== 'Waiting for Approval') {
            $fail('Only submissions waiting for approval can be reviewed.');
        }

        $include3d = (int) $current['include_3d'];
        $slotFiles = dchkLoadSlotFileRows($conn, $id);
        $pendingMine = array_values(array_intersect($mySlots, dchkPendingSlots($current, $slotFiles)));

        if (!$pendingMine) {
            $waiting = dchkWaitingOn($current, $slotFiles);
            $fail($waiting
                ? 'You have already reviewed your part. Waiting for the ' . implode(' and ', $waiting) . '.'
                : 'Nothing for you to review here.');
        }

        // Desisyon kada file — sa mga slot lang na hawak ko
        $input = dchkParseDecisions();
        $results = [];
        foreach ($pendingMine as $slot) {
            $r = dchkEvaluateSlot($slotFiles[$slot], $input, DCHK_SLOT_LABEL[$slot]);
            if (!empty($r['error'])) {
                $fail($r['error']);
            }
            $results[$slot] = $r;
        }

        // 3D is derived from 2D — a 3D revision sends the 2D files back too (Designer Head ang may hawak ng dalawa)
        if (isset($results['3d']) && $results['3d']['status'] === 'For Revision') {
            $r2d = $results['2d'] ?? dchkEvaluateSlot($slotFiles['2d'], [], '2D');
            if (empty($r2d['error']) && $r2d['status'] === 'Approved') {
                $results['2d'] = dchkForceRevision($r2d, 'Automatically sent back together with the 3D file revision.');
            }
        }

        // 1) per-file status
        foreach ($results as $r) {
            if (!dchkSaveFileReviews($conn, $id, $r['items'])) {
                $fail('Failed to save review.');
            }
        }

        // 2) slot-level status ng mga slot ko (+ sino at kailan)
        $set = [];
        $types = '';
        $params = [];
        $trackReviewer = dchkReviewerColsReady($conn);
        foreach ($results as $slot => $r) {
            $p = DCHK_SLOT_PREFIX[$slot];
            $set[] = "{$p}_review_status = ?";
            $set[] = "{$p}_remarks = ?";
            $types .= 'ss';
            $params[] = $r['status'];
            $params[] = $r['status'] === 'For Revision' ? $r['remarks'] : null;
        }
        if (isset($results['3d'])) {
            $set[] = 'design_3d_reviewed_at = NOW()';
        }
        if ($trackReviewer) {
            $byCol = ['2d' => 'design_reviewed', 'quotation' => 'quotation_reviewed', '3d' => 'design_3d_reviewed'];
            foreach (array_keys($results) as $slot) {
                $set[] = "{$byCol[$slot]}_by = ?";
                $types .= 'i';
                $params[] = $currentUserId;
                if ($slot !== '3d') {
                    $set[] = "{$byCol[$slot]}_at = NOW()";
                }
            }
        }
        $types .= 'i';
        $params[] = $id;
        $stmt = $conn->prepare("UPDATE {$table} SET " . implode(', ', $set) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            $fail('Failed to save review.');
        }
        $stmt->close();

        // 3) Bagong estado — tapos na ba ang dalawang head?
        $after = $current;
        foreach ($results as $slot => $r) {
            $p = DCHK_SLOT_PREFIX[$slot];
            $after["{$p}_review_status"] = $r['status'];
            $after["{$p}_remarks"] = $r['status'] === 'For Revision' ? $r['remarks'] : null;
        }
        $afterFiles = dchkLoadSlotFileRows($conn, $id);
        $finalized = !dchkPendingSlots($after, $afterFiles);
        $overallStatus = 'Waiting for Approval';

        if ($finalized) {
            $allApproved = true;
            foreach (dchkMainSlots($after) as $slot) {
                if (dchkSlotStatus($after, $slot) !== 'Approved') $allApproved = false;
            }
            $overallStatus = $allApproved ? 'Approved' : 'For Revision';

            $combinedRemarks = trim(implode("\n\n", array_filter([
                dchkSlotStatus($after, '2d') === 'For Revision' && $after['design_2d_remarks'] ? "2D: {$after['design_2d_remarks']}" : '',
                dchkSlotStatus($after, 'quotation') === 'For Revision' && $after['quotation_remarks'] ? "Quotation: {$after['quotation_remarks']}" : '',
                $include3d && dchkSlotStatus($after, '3d') === 'For Revision' && $after['design_3d_remarks'] ? "3D: {$after['design_3d_remarks']}" : '',
            ]))) ?: null;

            if ($include3d) {
                $new3dStage = $overallStatus;
            } elseif ($overallStatus === 'Approved') {
                $new3dStage = 'Draft';   // unlock 3D upload
            } else {
                $new3dStage = 'Locked';
            }

            $stmt = $conn->prepare("
                UPDATE {$table}
                SET status = ?, design_3d_stage = ?, remarks = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $overallStatus, $new3dStage, $combinedRemarks, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                $fail('Failed to save review.');
            }
            $stmt->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('checkdesigner2dquotationajax review: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save review.']);
        exit;
    }

    $recordForNotif = ['control_no' => $current['control_no'], 'client_name' => $current['client_name']];
    $inquiryId = (int) $current['inquiry_id'];

    if ($finalized) {
        try {
            if ($overallStatus === 'Approved') {
                dchkNotifyAccountingHead($conn, $inquiryId, $recordForNotif, $currentUserId);
            } else {
                $d3Rev = $include3d && dchkSlotStatus($after, '3d') === 'For Revision';
                if (dchkSlotStatus($after, '2d') === 'For Revision' || $d3Rev) {
                    dchkNotifyUserRevision($conn, (int) $current['designer_id'], $inquiryId, $recordForNotif, $currentUserId,
                        $d3Rev ? '2D / 3D File' : '2D File', $d3Rev ? $after['design_3d_remarks'] : $after['design_2d_remarks']);
                }
                if (dchkSlotStatus($after, 'quotation') === 'For Revision') {
                    dchkNotifyUserRevision($conn, (int) $current['sales_staff_id'], $inquiryId, $recordForNotif, $currentUserId,
                        'Quotation File', $after['quotation_remarks']);
                }
            }
        } catch (Throwable $e) {
            error_log('checkdesigner2dquotationajax notify: ' . $e->getMessage());
        }
    }

    // Realtime: Sales + Designer ng inquiry, ang dalawang head (badge + listahan),
    // at Accounting Head kapag approved (CRM List · Waiting for deposit)
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $rtGroups = [[ROLE_DESIGNER, POSITION_HEAD], [ROLE_SALES, POSITION_HEAD]];
        if ($finalized && $overallStatus === 'Approved') $rtGroups[] = [ROLE_ACCOUNTING, POSITION_HEAD];
        nhRtInquiry($conn, $inquiryId, $rtGroups, 'crm_head_review');
    } catch (Throwable $e) {
        error_log('checkdesigner2dquotationajax realtime: ' . $e->getMessage());
    }

    if (!$finalized) {
        $waiting = dchkWaitingOn($after, $afterFiles);
        echo json_encode(['success' => true, 'message' => 'Your review is saved. Waiting for the ' . implode(' and ', $waiting) . '.']);
        exit;
    }
    $message = $overallStatus === 'Approved' ? 'Initial approved — both heads are done.' : 'Sent back for revision.';
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// REVIEW 3D — standalone (sequential) 3D decision, PER-FILE
// ═══════════════════════════════════════════════════════════

if ($action === 'review_3d') {

    if ($viewer !== 'designer_head') {
        echo json_encode(['success' => false, 'message' => 'Only the Designer Head can review the 3D file.']);
        exit;
    }

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
                design_3d_remarks = NULL, design_3d_reviewed_at = NOW()" . (dchkReviewerColsReady($conn) ? ", design_3d_reviewed_by = {$currentUserId}" : '') . "
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

    // Realtime: Sales + Designer ng inquiry at ibang Designer Head
    if ($ok) {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        nhRtInquiry($conn, (int) $current['inquiry_id'], [[ROLE_DESIGNER, POSITION_HEAD], [ROLE_SALES, POSITION_HEAD]], 'crm_head_review');
    }

    echo json_encode(['success' => (bool) $ok, 'message' => $ok ? $message : 'Failed to save review.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);