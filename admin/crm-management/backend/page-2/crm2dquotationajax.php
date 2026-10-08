<?php
// crm2dquotationajax.php  — INITIAL step (walang contract dito; nasa Final na)
// + CUSTOMER REVIEW step (pagitan ng Initial approval at Final)


include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES, ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

const Q2D_MAX_FILES_PER_SLOT = 10;
const Q2D_MAX_FILE_BYTES = 15 * 1024 * 1024; // 15MB bawat file

$currentUserId = intval($_SESSION['account_id'] ?? 0);

// ⚠️ Adjust this if your session stores the role under a different key
$currentUserRole = $_SESSION['role'] ?? '';
$isSales = ($currentUserRole === ROLE_SALES);
$ownerColumn = $isSales ? 'sales_staff_id' : 'designer_id';
$roleLabel = $isSales ? 'sales' : 'designer';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$inquiryId = intval($_POST['inquiry_id'] ?? $_GET['inquiry_id'] ?? 0);
$slot = $_POST['slot'] ?? $_GET['slot'] ?? '';

function q2dRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Kapag lumampas sa post_max_size, binubura ng PHP ang buong $_POST at $_FILES.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    q2dRespond(false, 'The files are too large for the server to accept. Try uploading fewer or smaller files.');
}

function q2dRequireDesigner(bool $isSales): void
{
    if ($isSales) {
        q2dRespond(false, 'Only the assigned designer can perform this action.');
    }
}

// per-slot na check — 2D at 3D ay designer-only, Quotation ay sales-only.
function q2dRequireSlotOwner(string $slot, bool $isSales): void
{
    if ($slot === 'quotation') {
        return; // sales at designer, pareho pwede
    }
    // '2d' at '3d' — designer only
    if ($isSales) {
        q2dRespond(false, 'Only the assigned designer can perform this action.');
    }
}


if ($inquiryId <= 0) {
    q2dRespond(false, 'Missing or invalid inquiry reference.');
}

if (in_array($action, ['save_slot', 'unlock_slot', 'delete_file'], true) && !in_array($slot, ['2d', 'quotation', '3d'], true)) {
    q2dRespond(false, 'Invalid slot.');
}

// Ang contract (amount + attachment) ay nasa Final na — dito hindi na.
if ($action === 'save_contract_amount') {
    q2dRespond(false, 'The Contract is now set in the Final submission.');
}

$stmt = $conn->prepare("
    SELECT id, control_no, client_name, status, mode, deadline,
           design_progress, design_confirmed, design_confirmed_at, design_confirmed_by, clientstatus,
           designer_id, sales_staff_id
    FROM noblecrminquiry
    WHERE id = ? AND {$ownerColumn} = ?
    LIMIT 1
");
$stmt->bind_param("ii", $inquiryId, $currentUserId);
$stmt->execute();
$inquiry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$inquiry) {
    q2dRespond(false, 'Inquiry not found, or not assigned to you.');
}
if (!in_array($inquiry['status'], ['In Progress', 'Approved', 'For Revision'], true)) {
    q2dRespond(false, 'The site visit must be completed first.');
}

// shared helper — true kapag lagpas na sa deadline ng inquiry ngayong araw.
function q2dIsPastDeadline(array $inquiry): bool
{
    if (empty($inquiry['deadline'])) {
        return false;
    }
    return strtotime($inquiry['deadline']) < strtotime('today');
}

function q2dHasUnresolvedFeedback(mysqli $conn, int $quotationId): bool
{
    $stmt = $conn->prepare("SELECT id FROM noblecrm_2d_feedback WHERE quotation_id = ? AND is_resolved = 0 LIMIT 1");
    $stmt->bind_param("i", $quotationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

function q2dGetLatestEntry(mysqli $conn, int $inquiryId): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM noblecrm_2dquotation
        WHERE inquiry_id = ? AND stage = 'Initial'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function q2dGetEntryById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM noblecrm_2dquotation WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function q2dGetHistory(mysqli $conn, int $inquiryId): array
{
    $stmt = $conn->prepare("
        SELECT * FROM noblecrm_2dquotation
        WHERE inquiry_id = ? AND stage = 'Initial'
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function q2dAccountName(mysqli $conn, ?int $accountId): string
{
    if (!$accountId)
        return '—';
    $stmt = $conn->prepare("SELECT name FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? '—';
}

function q2dRoleLabel(?string $role): string
{
    if ($role === 'sales')
        return 'Sales';
    if ($role === 'designer')
        return 'Designer';
    return '—';
}

function q2dStatusStyle(string $status): array
{
    switch ($status) {
        case 'Approved':
            return ['border-green-800 text-green-800', 'Approved'];
        case 'For Revision':
            return ['border-red-700 text-red-700', 'For Revision'];
        case 'Waiting for Approval':
            return ['border-amber-700 text-amber-700', 'Waiting for Approval'];
        default:
            return ['border-gray-400 text-gray-600', 'Draft'];
    }
}

function q2dUrl(?string $path): ?string
{
    return !empty($path) ? BASE_URL . '/' . $path : null;
}


// ═══════════════════════════════════════════════════════════════
//  MULTI-FILE HELPERS
// ═══════════════════════════════════════════════════════════════

// Column prefix ng bawat slot sa noblecrm_2dquotation.
function q2dSlotPrefix(string $slot): string
{
    if ($slot === '2d')
        return 'design_2d_';
    if ($slot === '3d')
        return 'design_3d_';
    return 'quotation_';
}

// I-normalize ang $_FILES['files'] (files[]) papunta sa listahan ng single-file arrays.
function q2dNormalizeFiles(array $f): array
{
    $out = [];
    if (!isset($f['name'])) {
        return $out;
    }
    if (!is_array($f['name'])) {
        return ($f['error'] === UPLOAD_ERR_NO_FILE) ? [] : [$f];
    }
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $n,
            'type' => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i],
            'size' => $f['size'][$i],
        ];
    }
    return $out;
}

function q2dCountSlotFiles(mysqli $conn, int $quotationId, string $slot): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM noblecrm_2dquotation_files WHERE quotation_id = ? AND slot = ?");
    $stmt->bind_param("is", $quotationId, $slot);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

// Lumang data (may *_path pero wala pang rows sa files table) → auto-backfill.
function q2dBackfillEntry(mysqli $conn, array $row): void
{
    foreach (['2d', 'quotation', '3d'] as $slot) {
        $p = q2dSlotPrefix($slot);
        $path = $row[$p . 'path'] ?? null;
        if (empty($path)) {
            continue;
        }
        if (q2dCountSlotFiles($conn, (int) $row['id'], $slot) > 0) {
            continue;
        }
        $by = !empty($row[$p . 'uploaded_by']) ? (int) $row[$p . 'uploaded_by'] : null;
        $role = $row[$p . 'uploaded_role'] ?? null;
        $name = basename($path);
        $qid = (int) $row['id'];
        $inqId = (int) $row['inquiry_id'];

        $stmt = $conn->prepare("
            INSERT INTO noblecrm_2dquotation_files
                (quotation_id, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssis", $qid, $inqId, $slot, $path, $name, $by, $role);
        $stmt->execute();
        $stmt->close();
    }
}

// Kunin lahat ng files ng ilang submissions nang sabay: [quotation_id][slot] => [ {id,url,name}, ... ]
function q2dLoadFilesMap(mysqli $conn, array $quotationIds): array
{
    $ids = array_values(array_filter(array_map('intval', $quotationIds)));
    if (empty($ids)) {
        return [];
    }
    $in = implode(',', $ids);
    $res = $conn->query("
        SELECT id, quotation_id, slot, path, original_name
        FROM noblecrm_2dquotation_files
        WHERE quotation_id IN ({$in})
        ORDER BY id ASC
    ");
    $map = [];
    while ($r = $res->fetch_assoc()) {
        $map[(int) $r['quotation_id']][$r['slot']][] = [
            'id' => (int) $r['id'],
            'url' => q2dUrl($r['path']),
            'name' => !empty($r['original_name']) ? $r['original_name'] : basename($r['path']),
        ];
    }
    return $map;
}

// JSON ng isang slot ng isang submission.
function q2dSlotJson(mysqli $conn, array $row, string $slot, array $filesMap): array
{
    $p = q2dSlotPrefix($slot);
    $files = $filesMap[(int) $row['id']][$slot] ?? [];
    $path = $row[$p . 'path'] ?? null;
    $by = !empty($row[$p . 'uploaded_by']) ? (int) $row[$p . 'uploaded_by'] : null;

    return [
        'done' => (bool) ($row[$p . 'done'] ?? false),
        'path' => $path,
        // compat: "first file" para sa lumang consumers (hal. feedback2d.php)
        'url' => $files[0]['url'] ?? q2dUrl($path),
        'filename' => $files[0]['name'] ?? (!empty($path) ? basename($path) : null),
        'files' => $files,
        'uploaded_by_name' => q2dAccountName($conn, $by),
        'uploaded_role_label' => q2dRoleLabel($row[$p . 'uploaded_role'] ?? null),
    ];
}

// I-sync ang `*_path` column sa first file ng slot (o NULL kung wala nang file).
function q2dSyncPathColumn(mysqli $conn, int $quotationId, string $slot): void
{
    $col = q2dSlotPrefix($slot) . 'path'; // whitelisted sa q2dSlotPrefix
    $stmt = $conn->prepare("SELECT path FROM noblecrm_2dquotation_files WHERE quotation_id = ? AND slot = ? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param("is", $quotationId, $slot);
    $stmt->execute();
    $first = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $path = $first['path'] ?? null;
    $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET {$col} = ? WHERE id = ?");
    $stmt->bind_param("si", $path, $quotationId);
    $stmt->execute();
    $stmt->close();
}

// Ilang rows ang gumagamit pa ng path na ito? (may kopya kasi tuwing carry-over)
function q2dPathRefCount(mysqli $conn, string $path): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM noblecrm_2dquotation_files WHERE path = ?");
    $stmt->bind_param("s", $path);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $c;
}

function q2dCopySlotFiles(mysqli $conn, int $fromId, int $toId, string $slot): void
{
    $stmt = $conn->prepare("
        INSERT INTO noblecrm_2dquotation_files
            (quotation_id, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role, uploaded_at)
        SELECT ?, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role, uploaded_at
        FROM noblecrm_2dquotation_files
        WHERE quotation_id = ? AND slot = ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("iis", $toId, $fromId, $slot);
    $stmt->execute();
    $stmt->close();
}


// ═══════════════════════════════════════════════════════════════
//  CUSTOMER REVIEW HELPERS
// ═══════════════════════════════════════════════════════════════

// Handa na ba para sa customer? Lahat ng required slot ay Approved na ng Designer Head
// at kumpleto (done) ang 2D at Quotation.
function q2dIsReadyForCustomer(array $latest): bool
{
    if (($latest['status'] ?? '') !== 'Approved') {
        return false;
    }
    if ((int) ($latest['design_2d_done'] ?? 0) !== 1 || (int) ($latest['quotation_done'] ?? 0) !== 1) {
        return false;
    }
    if ((int) ($latest['include_3d'] ?? 0) === 1) {
        // 3D kasabay ng 2D & Quotation sa main review
        return ($latest['design_3d_review_status'] ?? '') === 'Approved';
    }
    // Standalone 3D — kailangang Approved na rin ang stage niya
    return ($latest['design_3d_stage'] ?? 'Locked') === 'Approved';
}

// [slot => 'Okay'|'Revise'|null] — pinakabagong decision bawat slot (null = pending)
function q2dCustomerDecisions(mysqli $conn, int $quotationId): array
{
    $out = ['2d' => null, 'quotation' => null, '3d' => null];
    $stmt = $conn->prepare("
        SELECT slot, decision FROM noblecrm_2dquotation_customer_review
        WHERE quotation_id = ? ORDER BY id ASC
    ");
    $stmt->bind_param("i", $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $out[$r['slot']] = $r['decision']; // huling row ang mananalo
    }
    $stmt->close();
    return $out;
}

function q2dCustomerApproved(mysqli $conn, array $latest): bool
{
    if (!q2dIsReadyForCustomer($latest)) {
        return false;
    }
    $d = q2dCustomerDecisions($conn, (int) $latest['id']);
    return $d['2d'] === 'Okay' && $d['quotation'] === 'Okay' && $d['3d'] === 'Okay';
}

// Kopyahin ang customer "Okay" ng mga slot na carried-over sa bagong draft,
// para hindi na ulit tanungin ang customer sa mga okay na.
function q2dCopyCustomerOkay(mysqli $conn, int $fromId, int $toId, array $slots): void
{
    if (empty($slots)) {
        return;
    }
    $decisions = q2dCustomerDecisions($conn, $fromId);

    $stmt = $conn->prepare("
        INSERT INTO noblecrm_2dquotation_customer_review
            (quotation_id, inquiry_id, slot, decision, decided_by, decided_at)
        SELECT ?, inquiry_id, slot, 'Okay', decided_by, decided_at
        FROM noblecrm_2dquotation_customer_review
        WHERE quotation_id = ? AND slot = ? AND decision = 'Okay'
        ORDER BY id DESC LIMIT 1
    ");
    foreach ($slots as $s) {
        if (($decisions[$s] ?? null) !== 'Okay') {
            continue;
        }
        $stmt->bind_param("iis", $toId, $fromId, $s);
        $stmt->execute();
    }
    $stmt->close();
}


// ═══════════════════════════════════════════════════════════════
//  NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════

function q2dDesignerHeadIds(mysqli $conn): array
{
    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
    $role = ROLE_DESIGNER;
    $pos = POSITION_HEAD;
    $stmt->bind_param("ss", $role, $pos);
    $stmt->execute();
    $heads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($h) => (int) $h['id'], $heads);
}

// Generic: magpadala ng notification sa listahan ng user ids (walang duplicate, hindi kasama ang sender).
function q2dNotifyUsers(mysqli $conn, array $userIds, int $inquiryId, array $inquiry, int $senderId, string $message, string $link): void
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0 && $id !== $senderId)));
    if (empty($userIds)) {
        return;
    }

    $controlNo = $inquiry['control_no'];
    $stmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_2dquotation', ?, 0, NOW(), ?, ?)
    ");
    $stmt->bind_param("iissis", $uid, $inquiryId, $controlNo, $message, $senderId, $link);
    foreach ($userIds as $uid) {
        $stmt->execute();
    }
    $stmt->close();
}

// Initial submissions → DESIGNER HEAD na ang magre-review (hindi na Superadmin).
function q2dNotifyDesignerHeads(mysqli $conn, int $inquiryId, array $inquiry, int $senderId, bool $is3dOnly = false): void
{
    $heads = q2dDesignerHeadIds($conn);
    if (empty($heads)) {
        return;
    }

    $message = $is3dOnly
        ? "New Initial 3D file submission from {$inquiry['client_name']} (Control No. {$inquiry['control_no']})"
        : "New Initial 2D and Quotation submission from {$inquiry['client_name']} (Control No. {$inquiry['control_no']})";
    $link = "/checkdesigner2dquotation?id={$inquiryId}";

    q2dNotifyUsers($conn, $heads, $inquiryId, $inquiry, $senderId, $message, $link);
}

function q2dGetOrCreateDraft(mysqli $conn, int $inquiryId): array
{
    $latest = q2dGetLatestEntry($conn, $inquiryId);

    if ($latest && in_array($latest['status'], ['Draft', 'Waiting for Approval'], true)) {
        return $latest;
    }

    $carry2dDone = 0;
    $carry2dPath = null;
    $carry2dRole = null;
    $carry2dBy = null;
    $carry2dReview = 'Pending';
    $carryQuotDone = 0;
    $carryQuotPath = null;
    $carryQuotRole = null;
    $carryQuotBy = null;
    $carryQuotReview = 'Pending';
    $carry3dDone = 0;
    $carry3dPath = null;
    $carry3dRole = null;
    $carry3dBy = null;
    $carry3dReview = 'Pending';
    $carryInclude3d = 0;
    $carry3dStage = 'Locked';

    $copySlots = []; // slots na naka-Approved → kokopyahin ang lahat ng files nila

    if ($latest && $latest['status'] === 'For Revision') {

        q2dBackfillEntry($conn, $latest);

        $carryInclude3d = (int) ($latest['include_3d'] ?? 0);

        if (($latest['design_2d_review_status'] ?? null) === 'Approved') {
            $carry2dDone = 1;
            $carry2dPath = $latest['design_2d_path'];
            $carry2dRole = $latest['design_2d_uploaded_role'];
            $carry2dBy = $latest['design_2d_uploaded_by'];
            $carry2dReview = 'Approved';
            $copySlots[] = '2d';
        }
        if (($latest['quotation_review_status'] ?? null) === 'Approved') {
            $carryQuotDone = 1;
            $carryQuotPath = $latest['quotation_path'];
            $carryQuotRole = $latest['quotation_uploaded_role'];
            $carryQuotBy = $latest['quotation_uploaded_by'];
            $carryQuotReview = 'Approved';
            $copySlots[] = 'quotation';
        }

        // Standalone 3D na Approved na (stage) — dapat manatiling Approved kahit 2D/Quotation lang ang binabalik.
        $stand3dApproved = (!$carryInclude3d && ($latest['design_3d_stage'] ?? 'Locked') === 'Approved');

        if (($latest['design_3d_review_status'] ?? null) === 'Approved' || $stand3dApproved) {
            $carry3dDone = 1;
            $carry3dPath = $latest['design_3d_path'];
            $carry3dRole = $latest['design_3d_uploaded_role'];
            $carry3dBy = $latest['design_3d_uploaded_by'];
            $carry3dReview = 'Approved';
            $copySlots[] = '3d';
        }
        if ($carryInclude3d) {
            $carry3dStage = 'Draft';
        } elseif ($stand3dApproved) {
            $carry3dStage = 'Approved';
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO noblecrm_2dquotation
            (inquiry_id, stage, status, created_at, include_3d,
             design_2d_done, design_2d_path, design_2d_uploaded_role, design_2d_uploaded_by, design_2d_uploaded_at, design_2d_review_status,
             quotation_done, quotation_path, quotation_uploaded_role, quotation_uploaded_by, quotation_uploaded_at, quotation_review_status,
             design_3d_done, design_3d_path, design_3d_uploaded_role, design_3d_uploaded_by, design_3d_uploaded_at, design_3d_review_status, design_3d_stage)
        VALUES
            (?, 'Initial', 'Draft', NOW(), ?,
             ?, ?, ?, ?, IF(? = 1, NOW(), NULL), ?,
             ?, ?, ?, ?, IF(? = 1, NOW(), NULL), ?,
             ?, ?, ?, ?, IF(? = 1, NOW(), NULL), ?, ?)
    ");

    $types = 'ii' . str_repeat('issiis', 3) . 's';
    $stmt->bind_param(
        $types,
        $inquiryId,
        $carryInclude3d,
        $carry2dDone,
        $carry2dPath,
        $carry2dRole,
        $carry2dBy,
        $carry2dDone,
        $carry2dReview,
        $carryQuotDone,
        $carryQuotPath,
        $carryQuotRole,
        $carryQuotBy,
        $carryQuotDone,
        $carryQuotReview,
        $carry3dDone,
        $carry3dPath,
        $carry3dRole,
        $carry3dBy,
        $carry3dDone,
        $carry3dReview,
        $carry3dStage
    );
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    // Kopyahin ang lahat ng files ng mga naka-Approved na slot papunta sa bagong draft,
    // pati ang customer "Okay" ng parehong mga slot.
    if ($latest && $newId > 0) {
        foreach ($copySlots as $cs) {
            q2dCopySlotFiles($conn, (int) $latest['id'], $newId, $cs);
        }
        q2dCopyCustomerOkay($conn, (int) $latest['id'], $newId, $copySlots);
    }

    return q2dGetEntryById($conn, $newId) ?: q2dGetLatestEntry($conn, $inquiryId);
}


function q2dSaveUploadedPdf(array $file, string $prefix, int $inquiryId, int $maxBytes, string $uploadDir): array
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => 'A file is too large for the server to accept.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($ext !== 'pdf' || $mime !== 'application/pdf') {
        return ['path' => null, 'error' => '"' . $file['name'] . '" must be a valid PDF.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['path' => null, 'error' => '"' . $file['name'] . '" exceeds the 15MB limit.'];
    }

    $safeName = $prefix . '_' . $inquiryId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['path' => null, 'error' => 'Something went wrong while saving the file.'];
    }

    return ['path' => 'uploads/crm-2dquotation/' . $safeName, 'error' => null];
}


function q2dSaveUploaded3dFile(array $file, int $inquiryId, int $maxBytes, string $uploadDir): array
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => 'A file is too large for the server to accept.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['path' => null, 'error' => '"' . $file['name'] . '" exceeds the 15MB limit.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $safeBase = '3d_' . $inquiryId . '_' . time() . '_' . bin2hex(random_bytes(4));

    if ($mime === 'application/pdf') {
        $destPath = $uploadDir . $safeBase . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['path' => null, 'error' => 'Something went wrong while saving the file.'];
        }
        return ['path' => 'uploads/crm-2dquotation/' . $safeBase . '.pdf', 'error' => null];
    }

    $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedImageMimes, true)) {
        return ['path' => null, 'error' => '"' . $file['name'] . '": the 3D file must be a PDF or an image (JPG, PNG, WEBP).'];
    }

    $srcImage = null;
    if ($mime === 'image/jpeg')
        $srcImage = @imagecreatefromjpeg($file['tmp_name']);
    if ($mime === 'image/png')
        $srcImage = @imagecreatefrompng($file['tmp_name']);
    if ($mime === 'image/webp')
        $srcImage = @imagecreatefromwebp($file['tmp_name']);

    if (!$srcImage) {
        return ['path' => null, 'error' => 'Could not read the uploaded image "' . $file['name'] . '".'];
    }

    // Preserve transparency for PNG/WEBP sources.
    imagepalettetotruecolor($srcImage);
    imagealphablending($srcImage, true);
    imagesavealpha($srcImage, true);

    $destPath = $uploadDir . $safeBase . '.webp';
    if (!imagewebp($srcImage, $destPath, 90)) {
        imagedestroy($srcImage);
        return ['path' => null, 'error' => 'Something went wrong while converting the image.'];
    }
    imagedestroy($srcImage);

    return ['path' => 'uploads/crm-2dquotation/' . $safeBase . '.webp', 'error' => null];
}


// Alin bang submission row ang kasalukuyang ine-edit ng slot na ito?
// Returns [entry|null, errorMessage]. $create = true → pwedeng gumawa ng bagong Draft.
function q2dResolveEditableEntry(mysqli $conn, int $inquiryId, string $slot, bool $create): array
{
    $latest = q2dGetLatestEntry($conn, $inquiryId);

    if ($slot === '3d') {
        // Bundled 3D na ni-reject (Head o Customer) → gumawa ng bagong draft para doon i-upload ang bagong 3D.
        if (
            $latest && $latest['status'] === 'For Revision'
            && (int) ($latest['include_3d'] ?? 0) === 1
            && ($latest['design_3d_review_status'] ?? '') === 'For Revision'
        ) {
            if (!$create) {
                return [null, 'Nothing to edit right now.'];
            }
            $draft = q2dGetOrCreateDraft($conn, $inquiryId);
            if (($draft['design_3d_stage'] ?? 'Locked') !== 'Draft') {
                return [null, '3D upload is not open right now.'];
            }
            return [$draft, ''];
        }

        // Standalone 3D → bukas kapag Draft o For Revision (galing sa customer/head).
        if (!$latest || !in_array($latest['design_3d_stage'] ?? 'Locked', ['Draft', 'For Revision'], true)) {
            return [null, '3D upload is not open right now.'];
        }
        return [$latest, ''];
    }

    // Approved na submission pero may open feedback sa 2D → pwedeng palitan ang 2D file.
    if ($slot === '2d' && $latest && $latest['status'] === 'Approved' && (int) $latest['design_2d_done'] === 0) {
        if (!q2dHasUnresolvedFeedback($conn, (int) $latest['id'])) {
            return [null, 'No open feedback on this file.'];
        }
        return [$latest, ''];
    }

    if ($latest && $latest['status'] === 'Approved') {
        return [null, 'Nothing to edit right now.'];
    }

    if ($create) {
        $draft = q2dGetOrCreateDraft($conn, $inquiryId);
    } else {
        $draft = ($latest && in_array($latest['status'], ['Draft', 'Waiting for Approval'], true)) ? $latest : null;
    }

    if (!$draft) {
        return [null, 'Nothing to edit right now.'];
    }
    if ($draft['status'] !== 'Draft') {
        return [null, 'This submission is locked and can no longer be edited.'];
    }
    return [$draft, ''];
}

// Ang sentral na "Mark as Done": tanggapin ang mga bagong files, i-save, i-mark done.
function q2dSaveSlotFiles(mysqli $conn, array $entry, string $slot, int $inquiryId, int $userId, string $roleLabel): void
{
    $p = q2dSlotPrefix($slot);
    $entryId = (int) $entry['id'];

    if ((int) ($entry[$p . 'done'] ?? 0) === 1) {
        q2dRespond(false, 'This file is already marked done. Click Edit first.');
    }

    q2dBackfillEntry($conn, $entry);

    $newFiles = q2dNormalizeFiles($_FILES['files'] ?? []);
    $existingCount = q2dCountSlotFiles($conn, $entryId, $slot);
    $total = $existingCount + count($newFiles);

    if ($total === 0) {
        q2dRespond(false, 'Please attach at least one file before marking this as done.');
    }
    if ($total > Q2D_MAX_FILES_PER_SLOT) {
        q2dRespond(false, 'Maximum of ' . Q2D_MAX_FILES_PER_SLOT . ' files per slot.');
    }

    $saved = [];
    if (!empty($newFiles)) {
        $uploadDir = ROOT_PATH . '/uploads/crm-2dquotation/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($newFiles as $f) {
            $r = ($slot === '3d')
                ? q2dSaveUploaded3dFile($f, $inquiryId, Q2D_MAX_FILE_BYTES, $uploadDir)
                : q2dSaveUploadedPdf($f, $slot === '2d' ? 'design2d' : 'quotation', $inquiryId, Q2D_MAX_FILE_BYTES, $uploadDir);

            if (!$r['path']) {
                foreach ($saved as $s) { // rollback — walang natitirang kalahating upload
                    @unlink(ROOT_PATH . '/' . $s['path']);
                }
                q2dRespond(false, $r['error']);
            }
            $saved[] = ['path' => $r['path'], 'name' => $f['name']];
        }

        $ins = $conn->prepare("
            INSERT INTO noblecrm_2dquotation_files
                (quotation_id, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($saved as $s) {
            $ins->bind_param("iisssis", $entryId, $inquiryId, $slot, $s['path'], $s['name'], $userId, $roleLabel);
            $ins->execute();
        }
        $ins->close();
    }

    q2dSyncPathColumn($conn, $entryId, $slot);

    $stmt = $conn->prepare("
        UPDATE noblecrm_2dquotation
        SET {$p}done = 1, {$p}uploaded_role = ?, {$p}uploaded_by = ?, {$p}uploaded_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $roleLabel, $userId, $entryId);
    $stmt->execute();
    $stmt->close();
}


// ═══════════════════════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════════════════════

if ($action === 'state') {

    $qHistory = q2dGetHistory($conn, $inquiryId);

    // Lumang data → siguraduhing may rows sa files table.
    foreach ($qHistory as $hRow) {
        q2dBackfillEntry($conn, $hRow);
    }

    $latest = $qHistory[0] ?? null;

    if (
        $latest
        && $latest['status'] === 'Approved'
        && (int) ($latest['include_3d'] ?? 0) === 0
        && ($latest['design_3d_stage'] ?? 'Locked') === 'Locked'
    ) {
        $healStage = 'Draft';
        $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET design_3d_stage = ? WHERE id = ?");
        $stmt->bind_param("si", $healStage, $latest['id']);
        $stmt->execute();
        $stmt->close();

        $latest['design_3d_stage'] = 'Draft';
        $qHistory[0] = $latest;
    }

    $filesMap = q2dLoadFilesMap($conn, array_column($qHistory, 'id'));

    $activeDraftRow = null;
    if ($latest && in_array($latest['status'], ['Draft', 'Waiting for Approval'], true)) {
        $activeDraftRow = $latest;
    }

    $isLocked = $activeDraftRow && $activeDraftRow['status'] === 'Waiting for Approval';
    $pastEntries = $activeDraftRow ? array_slice($qHistory, 1) : $qHistory;

    $completedRow = null;
    if (!$activeDraftRow && $latest && $latest['status'] === 'Approved') {
        $completedRow = $latest;
    }

    $revisionEntryRow = null;
    $design2dNeedsRevision = false;
    $quotationNeedsRevision = false;
    $design3dNeedsRevision = false;

    if (!$activeDraftRow && !$completedRow && !empty($qHistory) && $qHistory[0]['status'] === 'For Revision') {
        $revisionEntryRow = $qHistory[0];
        $design2dNeedsRevision = ($revisionEntryRow['design_2d_review_status'] ?? 'For Revision') === 'For Revision';
        $quotationNeedsRevision = ($revisionEntryRow['quotation_review_status'] ?? 'For Revision') === 'For Revision';
        $design3dNeedsRevision = (int) ($revisionEntryRow['include_3d'] ?? 0) === 1
            && ($revisionEntryRow['design_3d_review_status'] ?? '') === 'For Revision';
    }

    $activeDraftJson = null;
    if ($activeDraftRow) {
        [$statusClass, $statusLabel] = q2dStatusStyle($activeDraftRow['status']);
        $activeDraftJson = [
            'id' => (int) $activeDraftRow['id'],
            'status' => $activeDraftRow['status'],
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'is_locked' => $isLocked,
            'is_late' => $isLocked
                ? (bool) ($activeDraftRow['is_late'] ?? 0)
                : q2dIsPastDeadline($inquiry),
            'both_done' => (bool) $activeDraftRow['design_2d_done'] && (bool) $activeDraftRow['quotation_done'],
            'design_2d' => q2dSlotJson($conn, $activeDraftRow, '2d', $filesMap),
            'quotation' => q2dSlotJson($conn, $activeDraftRow, 'quotation', $filesMap),
        ];
    }

    $hasUnresolvedFeedback = $latest ? q2dHasUnresolvedFeedback($conn, (int) $latest['id']) : false;

    $completedEntryJson = null;
    if ($completedRow) {
        $completedEntryJson = [
            'reviewed_at' => $completedRow['reviewed_at'] ? date('F d, Y g:i A', strtotime($completedRow['reviewed_at'])) : null,
            'design_2d_revisable' => $hasUnresolvedFeedback,
            'design_2d' => q2dSlotJson($conn, $completedRow, '2d', $filesMap),
            'quotation' => q2dSlotJson($conn, $completedRow, 'quotation', $filesMap),
        ];
    }

    $revisionEntryJson = null;
    if ($revisionEntryRow) {
        $revisionEntryJson = [
            'source' => $revisionEntryRow['revision_source'] ?? 'Head', // 'Head' | 'Customer'
            'include_3d' => (bool) ($revisionEntryRow['include_3d'] ?? 0),
            'design_2d_needs_revision' => $design2dNeedsRevision,
            'quotation_needs_revision' => $quotationNeedsRevision,
            'design_3d_needs_revision' => $design3dNeedsRevision,
            'design_2d' => array_merge(
                q2dSlotJson($conn, $revisionEntryRow, '2d', $filesMap),
                ['remarks' => $revisionEntryRow['design_2d_remarks']]
            ),
            'quotation' => array_merge(
                q2dSlotJson($conn, $revisionEntryRow, 'quotation', $filesMap),
                ['remarks' => $revisionEntryRow['quotation_remarks']]
            ),
            'design_3d' => array_merge(
                q2dSlotJson($conn, $revisionEntryRow, '3d', $filesMap),
                ['remarks' => $revisionEntryRow['design_3d_remarks'] ?? null]
            ),
        ];
    }

    $pastEntriesJson = array_map(function (array $entry) use ($conn, $filesMap): array {
        [$statusClass, $statusLabel] = q2dStatusStyle($entry['status']);

        $d2dReviewStatus = (!empty($entry['design_2d_review_status']) && $entry['design_2d_review_status'] !== 'Pending')
            ? $entry['design_2d_review_status'] : null;
        $qtReviewStatus = (!empty($entry['quotation_review_status']) && $entry['quotation_review_status'] !== 'Pending')
            ? $entry['quotation_review_status'] : null;
        $d3dReviewStatus = (!empty($entry['design_3d_review_status']) && $entry['design_3d_review_status'] !== 'Pending')
            ? $entry['design_3d_review_status'] : null;

        return [
            'design_2d' => array_merge(q2dSlotJson($conn, $entry, '2d', $filesMap), [
                'review_status' => $d2dReviewStatus,
                'review_class' => $d2dReviewStatus ? q2dStatusStyle($d2dReviewStatus)[0] : null,
            ]),
            'quotation' => array_merge(q2dSlotJson($conn, $entry, 'quotation', $filesMap), [
                'review_status' => $qtReviewStatus,
                'review_class' => $qtReviewStatus ? q2dStatusStyle($qtReviewStatus)[0] : null,
            ]),
            'design_3d' => array_merge(q2dSlotJson($conn, $entry, '3d', $filesMap), [
                'included' => (bool) ($entry['include_3d'] ?? 0),
                'review_status' => $d3dReviewStatus,
                'review_class' => $d3dReviewStatus ? q2dStatusStyle($d3dReviewStatus)[0] : null,
            ]),
            'submitted_at' => $entry['submitted_at'] ? date('M d, Y g:i A', strtotime($entry['submitted_at'])) : null,
            'status' => $entry['status'],
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'is_late' => (bool) ($entry['is_late'] ?? 0),
            'revision_source' => $entry['revision_source'] ?? 'Head',
            'design_2d_remarks' => $entry['design_2d_remarks'] ?? null,
            'quotation_remarks' => $entry['quotation_remarks'] ?? null,
            'remarks' => $entry['remarks'] ?? null,
        ];
    }, $pastEntries);

    $include3d = (int) ($latest['include_3d'] ?? 0);
    $design3dJson = $latest ? array_merge(q2dSlotJson($conn, $latest, '3d', $filesMap), [
        'include_3d' => (bool) $include3d,
        'stage' => $latest['design_3d_stage'] ?? 'Locked',
        'review_status' => $latest['design_3d_review_status'] ?? 'Pending',
        'remarks' => $latest['design_3d_remarks'] ?? null,
        // NOTE: 3D standalone submissions are no longer flagged as Late Submission — 2D & Quotation only.
        'toggle_editable' => (bool) ($activeDraftRow && $activeDraftRow['status'] === 'Draft'),
    ]) : null;

    $isReadyForQuotation = ($inquiry['mode'] ?? 'site_visit') === 'ready_for_quotation';

    // Progress tracker lang — hindi na gate ang customer confirmation dito.
    $step1Json = [
        'progress' => $isReadyForQuotation ? '100' : ($inquiry['design_progress'] ?? '0'),
        'auto_confirmed' => $isReadyForQuotation, // read-only ang progress kapag ready_for_quotation
    ];

    $cuttingFeedback = [];
    if ($latest) {
        $stmt = $conn->prepare("
            SELECT f.id, f.message, f.created_at, f.is_resolved, r.name AS created_by_name
            FROM noblecrm_2d_feedback f
            LEFT JOIN noblerole r ON r.id = f.created_by
            WHERE f.quotation_id = ?
            ORDER BY f.id DESC
        ");
        $stmt->bind_param("i", $latest['id']);
        $stmt->execute();
        $fbResult = $stmt->get_result();
        while ($fb = $fbResult->fetch_assoc()) {
            $cuttingFeedback[] = [
                'id' => (int) $fb['id'],
                'message' => $fb['message'],
                'created_by_name' => $fb['created_by_name'] ?? '—',
                'created_at' => date('M d, Y g:i A', strtotime($fb['created_at'])),
                'is_resolved' => (bool) $fb['is_resolved'],
            ];
        }
        $stmt->close();
    }

    $qDeadlineOverdue = false;
    if (!empty($inquiry['deadline'])) {
        if ($latest && in_array($latest['status'], ['Approved', 'Waiting for Approval'], true)) {
            $qDeadlineOverdue = (bool) ($latest['is_late'] ?? 0);
        } else {
            $qDeadlineOverdue = q2dIsPastDeadline($inquiry);
        }
    }

    $finalStmt = $conn->prepare("
        SELECT id FROM noblecrm_2dquotation_final
        WHERE inquiry_id = ?
        LIMIT 1
    ");
    $finalStmt->bind_param("i", $inquiryId);
    $finalStmt->execute();
    $hasFinal = (bool) $finalStmt->get_result()->fetch_assoc();
    $finalStmt->close();

    // Customer review status
    $readyForCustomer = $latest ? q2dIsReadyForCustomer($latest) : false;
    $customerApproved = $latest ? q2dCustomerApproved($conn, $latest) : false;

    q2dRespond(true, '', [
        'stage' => $latest['stage'] ?? 'Initial',
        'inquiry' => [
            'control_no' => $inquiry['control_no'],
            'client_name' => $inquiry['client_name'],
            'deadline' => !empty($inquiry['deadline']) ? date('F d, Y', strtotime($inquiry['deadline'])) : null,
            'deadline_overdue' => $qDeadlineOverdue,
        ],
        'is_sales' => $isSales,
        'max_files' => Q2D_MAX_FILES_PER_SLOT,
        'step1' => $step1Json,
        'active_draft' => $activeDraftJson,
        'completed_entry' => $completedEntryJson,
        'revision_entry' => $revisionEntryJson,
        'past_entries' => $pastEntriesJson,
        'design_3d' => $design3dJson,
        'cutting_feedback' => $cuttingFeedback,
        'has_final' => $hasFinal,
        'ready_for_customer' => $readyForCustomer,
        'customer_approved' => $customerApproved,
        'server_time' => date('c'),
    ]);
}

if ($action === 'save_progress') {
    q2dRequireDesigner($isSales);

    // Tracker lang ito ng 2D design (0/50/100) — hindi gate para sa pag-upload o pag-submit.
    $progress = $_POST['progress'] ?? '';
    if (!in_array($progress, ['0', '50', '100'], true)) {
        q2dRespond(false, 'Invalid progress value.');
    }

    $stmt = $conn->prepare("UPDATE noblecrminquiry SET design_progress = ? WHERE id = ?");
    $stmt->bind_param("si", $progress, $inquiryId);
    $stmt->execute();
    $stmt->close();

    q2dRespond(true, 'Progress updated.');
}


// Wala nang "Confirm Customer Approval" sa progress step.
// Ang customer check ay nasa Customer Review, pagkatapos lang ma-approve ng Designer Head.
if ($action === 'confirm_customer') {
    q2dRespond(false, 'This step is no longer needed. Customer review happens after the Designer Head approves the files.');
}


if ($action === 'save_toggle') {
    q2dRequireDesigner($isSales);

    $include3d = (intval($_POST['include_3d'] ?? 0) === 1) ? 1 : 0;

    $draft = q2dGetOrCreateDraft($conn, $inquiryId);
    if ($draft['status'] !== 'Draft') {
        q2dRespond(false, 'This submission is locked and can no longer be edited.');
    }

    // Kung OFF, i-lock lang ang stage — hindi burahin ang naka-Draft nang
    // 3D file kung sakaling na-toggle ON tapos OFF ulit bago mag-submit.
    $stage = $include3d ? 'Draft' : 'Locked';

    $stmt = $conn->prepare("
        UPDATE noblecrm_2dquotation
        SET include_3d = ?, design_3d_stage = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $include3d, $stage, $draft['id']);
    $stmt->execute();
    $stmt->close();

    q2dRespond(true, 'Updated.');
}


// Isang save_slot na para sa lahat ng slot (2D / Quotation / 3D), multi-file.
if ($action === 'save_slot') {
    q2dRequireSlotOwner($slot, $isSales);

    [$entry, $err] = q2dResolveEditableEntry($conn, $inquiryId, $slot, true);
    if (!$entry) {
        q2dRespond(false, $err);
    }

    q2dSaveSlotFiles($conn, $entry, $slot, $inquiryId, $currentUserId, $roleLabel);

    // Revised 2D sa Approved na submission → i-resolve ang lahat ng open feedback.
    if ($slot === '2d' && $entry['status'] === 'Approved') {
        $stmt = $conn->prepare("UPDATE noblecrm_2d_feedback SET is_resolved = 1 WHERE quotation_id = ? AND is_resolved = 0");
        $stmt->bind_param("i", $entry['id']);
        $stmt->execute();
        $stmt->close();

        q2dRespond(true, 'Revised 2D file saved.');
    }

    q2dRespond(true, 'Saved.');
}


// Alisin ang isang file sa slot habang naka-unlock (hindi pa "done").
if ($action === 'delete_file') {
    q2dRequireSlotOwner($slot, $isSales);

    $fileId = intval($_POST['file_id'] ?? 0);
    if ($fileId <= 0) {
        q2dRespond(false, 'Invalid file.');
    }

    [$entry, $err] = q2dResolveEditableEntry($conn, $inquiryId, $slot, false);
    if (!$entry) {
        q2dRespond(false, $err);
    }

    $p = q2dSlotPrefix($slot);
    if ((int) ($entry[$p . 'done'] ?? 0) === 1) {
        q2dRespond(false, 'Click Edit first before removing files.');
    }

    $entryId = (int) $entry['id'];

    $stmt = $conn->prepare("SELECT path FROM noblecrm_2dquotation_files WHERE id = ? AND quotation_id = ? AND slot = ? LIMIT 1");
    $stmt->bind_param("iis", $fileId, $entryId, $slot);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        q2dRespond(false, 'File not found.');
    }

    $stmt = $conn->prepare("DELETE FROM noblecrm_2dquotation_files WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $stmt->close();

    // Buburahin lang sa disk kung wala nang ibang submission na gumagamit (carry-over copies).
    if (q2dPathRefCount($conn, $row['path']) === 0) {
        @unlink(ROOT_PATH . '/' . $row['path']);
    }

    q2dSyncPathColumn($conn, $entryId, $slot);

    q2dRespond(true, 'File removed.');
}


if ($action === 'unlock_slot') {
    q2dRequireSlotOwner($slot, $isSales);

    if ($slot === '2d') {
        $latestApproved = q2dGetLatestEntry($conn, $inquiryId);
        if ($latestApproved && $latestApproved['status'] === 'Approved') {
            if (!q2dHasUnresolvedFeedback($conn, (int) $latestApproved['id'])) {
                q2dRespond(false, 'No open feedback on this file.');
            }
            $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET design_2d_done = 0 WHERE id = ?");
            $stmt->bind_param("i", $latestApproved['id']);
            $stmt->execute();
            $stmt->close();
            q2dRespond(true, 'Unlocked for revision.');
        }
    }

    if ($slot === '3d') {
        $latest = q2dGetLatestEntry($conn, $inquiryId);
        if (!$latest || !in_array($latest['design_3d_stage'] ?? 'Locked', ['Draft', 'For Revision'], true)) {
            q2dRespond(false, 'Nothing to edit right now.');
        }
        $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET design_3d_done = 0 WHERE id = ?");
        $stmt->bind_param("i", $latest['id']);
        $stmt->execute();
        $stmt->close();
        q2dRespond(true, 'Unlocked.');
    }

    $draft = q2dGetLatestEntry($conn, $inquiryId);

    if (!$draft || $draft['status'] !== 'Draft') {
        q2dRespond(false, 'Nothing to edit right now.');
    }

    $doneField = $slot === '2d' ? 'design_2d_done' : 'quotation_done';

    $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET {$doneField} = 0 WHERE id = ?");
    $stmt->bind_param("i", $draft['id']);
    $stmt->execute();
    $stmt->close();

    q2dRespond(true, 'Unlocked.');
}


if ($action === 'submit_final') {

    $draft = q2dGetLatestEntry($conn, $inquiryId);

    if (!$draft || $draft['status'] !== 'Draft') {
        q2dRespond(false, 'Nothing to submit right now.');
    }

    if (!$draft['design_2d_done'] || !$draft['quotation_done']) {
        q2dRespond(false, 'Both the 2D File and Quotation File must be marked done before submitting.');
    }

    $include3d = (int) ($draft['include_3d'] ?? 0);
    if ($include3d && !$draft['design_3d_done']) {
        q2dRespond(false, 'The 3D File must be marked done before submitting, or turn off "Submit 3D together" to submit 2D and Quotation only.');
    }

    $isLate = q2dIsPastDeadline($inquiry) ? 1 : 0;

    $newStatus = 'Waiting for Approval';
    // Standalone 3D na Approved na (carried-over) → huwag i-lock ulit.
    $new3dStage = $include3d
        ? 'Waiting for Approval'
        : (($draft['design_3d_stage'] ?? 'Locked') === 'Approved' ? 'Approved' : 'Locked');

    $stmt = $conn->prepare("
        UPDATE noblecrm_2dquotation
        SET status = ?, submitted_at = NOW(), design_3d_stage = ?, is_late = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $newStatus, $new3dStage, $isLate, $draft['id']);
    $stmt->execute();
    $stmt->close();

    // Naisumite na ang 2D — itala ang progress bilang 100%.
    $stmt = $conn->prepare("UPDATE noblecrminquiry SET design_progress = '100' WHERE id = ?");
    $stmt->bind_param("i", $inquiryId);
    $stmt->execute();
    $stmt->close();

    q2dNotifyDesignerHeads($conn, $inquiryId, $inquiry, $currentUserId);

    q2dRespond(true, $isLate ? 'Submitted for approval (Late Submission).' : 'Submitted for approval.');
}


if ($action === 'submit_3d') {
    q2dRequireDesigner($isSales);

    $latest = q2dGetLatestEntry($conn, $inquiryId);

    if (!$latest || $latest['status'] !== 'Approved') {
        q2dRespond(false, 'The 2D File and Quotation must be approved first.');
    }
    if ((int) ($latest['include_3d'] ?? 0) === 1) {
        q2dRespond(false, 'This submission already includes 3D in its main review.');
    }
    if (!in_array($latest['design_3d_stage'] ?? 'Locked', ['Draft', 'For Revision'], true)) {
        q2dRespond(false, '3D upload is not open right now.');
    }
    if (!$latest['design_3d_done']) {
        q2dRespond(false, 'The 3D File must be marked done before submitting.');
    }

    $newStage = 'Waiting for Approval';
    $stmt = $conn->prepare("UPDATE noblecrm_2dquotation SET design_3d_stage = ? WHERE id = ?");
    $stmt->bind_param("si", $newStage, $latest['id']);
    $stmt->execute();
    $stmt->close();

    q2dNotifyDesignerHeads($conn, $inquiryId, $inquiry, $currentUserId, true);

    q2dRespond(true, 'Submitted for approval.');
}


// ═══════════════════════════════════════════════════════════════
//  CUSTOMER REVIEW ACTIONS
// ═══════════════════════════════════════════════════════════════

if ($action === 'customer_state') {
    $latest = q2dGetLatestEntry($conn, $inquiryId);
    if (!$latest || !q2dIsReadyForCustomer($latest)) {
        q2dRespond(false, 'The Designer Head has not approved all files yet.');
    }

    q2dBackfillEntry($conn, $latest);
    $filesMap = q2dLoadFilesMap($conn, [$latest['id']]);
    $decisions = q2dCustomerDecisions($conn, (int) $latest['id']);

    $slots = [];
    foreach (['2d' => '2D', 'quotation' => 'Quotation', '3d' => '3D'] as $s => $label) {
        $slots[] = [
            'slot' => $s,
            'label' => $label,
            'decision' => $decisions[$s],            // null = pending
            'data' => q2dSlotJson($conn, $latest, $s, $filesMap),
        ];
    }

    q2dRespond(true, '', [
        'inquiry' => [
            'control_no' => $inquiry['control_no'],
            'client_name' => $inquiry['client_name'],
        ],
        'status' => q2dCustomerApproved($conn, $latest) ? 'Customer Approved' : 'Waiting for Customer',
        'slots' => $slots,
    ]);
}


if ($action === 'submit_customer_review') {
    $latest = q2dGetLatestEntry($conn, $inquiryId);
    if (!$latest || !q2dIsReadyForCustomer($latest)) {
        q2dRespond(false, 'Not ready for customer review yet.');
    }
    if (q2dCustomerApproved($conn, $latest)) {
        q2dRespond(false, 'The customer has already approved all files.', ['next' => 'final']);
    }

    // {"2d":{"decision":"Okay"},"quotation":{"decision":"Revise","remarks":"..."}, ...}
    $payload = json_decode($_POST['decisions'] ?? '', true);
    if (!is_array($payload)) {
        q2dRespond(false, 'Invalid data.');
    }

    $current = q2dCustomerDecisions($conn, (int) $latest['id']);
    $toSave = [];
    foreach (['2d', 'quotation', '3d'] as $s) {
        if ($current[$s] === 'Okay') {
            continue; // okay na dati, skip
        }
        $d = $payload[$s]['decision'] ?? '';
        if (!in_array($d, ['Okay', 'Revise'], true)) {
            q2dRespond(false, 'Please choose Okay or Needs Revision for every file.');
        }
        $rm = trim((string) ($payload[$s]['remarks'] ?? ''));
        if ($d === 'Revise' && $rm === '') {
            q2dRespond(false, 'Remarks are required for files that need revision.');
        }
        $toSave[$s] = ['decision' => $d, 'remarks' => ($d === 'Revise' ? $rm : null)];
    }

    if (empty($toSave)) {
        q2dRespond(false, 'Nothing to save.');
    }

    $entryId = (int) $latest['id'];
    $include3d = (int) ($latest['include_3d'] ?? 0);
    $anyRevise = false;
    $reviseLabels = [];
    $slotLabels = ['2d' => '2D', 'quotation' => 'Quotation', '3d' => '3D'];

    $conn->begin_transaction();
    try {
        $ins = $conn->prepare("
            INSERT INTO noblecrm_2dquotation_customer_review
                (quotation_id, inquiry_id, slot, decision, remarks, decided_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($toSave as $s => $v) {
            $ins->bind_param("iisssi", $entryId, $inquiryId, $s, $v['decision'], $v['remarks'], $currentUserId);
            $ins->execute();
        }
        $ins->close();

        foreach ($toSave as $s => $v) {
            if ($v['decision'] !== 'Revise') {
                continue;
            }
            $anyRevise = true;
            $reviseLabels[] = $slotLabels[$s];

            if ($s === '2d' || $s === 'quotation' || ($s === '3d' && $include3d)) {
                // Parehong state ng Head rejection → gagana na ang carry-over/re-upload flow.
                $p = q2dSlotPrefix($s);
                $stmt = $conn->prepare("
                    UPDATE noblecrm_2dquotation
                    SET status = 'For Revision', revision_source = 'Customer',
                        {$p}review_status = 'For Revision', {$p}remarks = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $v['remarks'], $entryId);
                $stmt->execute();
                $stmt->close();
            } else {
                // Standalone 3D → 3D lang ang babalik; hindi gagalawin ang status ng 2D/Quotation.
                $stmt = $conn->prepare("
                    UPDATE noblecrm_2dquotation
                    SET design_3d_stage = 'For Revision', design_3d_done = 0,
                        design_3d_review_status = 'For Revision', design_3d_remarks = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $v['remarks'], $entryId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        q2dRespond(false, 'Something went wrong. Please try again.');
    }

    $clientLine = "{$inquiry['client_name']} (Control No. {$inquiry['control_no']})";

    if ($anyRevise) {
        // Designer ang gagawa ng revision.
        q2dNotifyUsers(
            $conn,
            [(int) ($inquiry['designer_id'] ?? 0)],
            $inquiryId,
            $inquiry,
            $currentUserId,
            'Customer requested revision (' . implode(', ', $reviseLabels) . ") for {$clientLine}",
            "/crm2dquotation?id={$inquiryId}"
        );
        q2dRespond(true, 'Sent back for revision.', ['next' => 'revision']);
    }

    // Lahat Okay → ito na ang "customer confirmed" ng design (para sa ibang pages na gumagamit ng design_confirmed).
    $clientStatus = 'Client Review & Approval';
    $stmt = $conn->prepare("
        UPDATE noblecrminquiry
        SET design_confirmed = 1, design_confirmed_at = NOW(), design_confirmed_by = ?, clientstatus = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $currentUserId, $clientStatus, $inquiryId);
    $stmt->execute();
    $stmt->close();

    // Lahat Okay → i-notify ang Designer Heads at ang designer.
    q2dNotifyUsers(
        $conn,
        array_merge(q2dDesignerHeadIds($conn), [(int) ($inquiry['designer_id'] ?? 0)]),
        $inquiryId,
        $inquiry,
        $currentUserId,
        "Customer approved all Initial files for {$clientLine}",
        "/crm2dquotation?id={$inquiryId}"
    );
    q2dRespond(true, 'Customer approved all files.', ['next' => 'final']);
}

q2dRespond(false, 'Unknown action.');