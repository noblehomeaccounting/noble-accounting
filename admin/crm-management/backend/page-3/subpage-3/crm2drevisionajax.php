<?php
// crm2drevisionajax.php
// Revision history ng 2D file — sagot ng designer sa feedback ng Cutting.
// HINDI pinapalitan ang lumang 2D: bawat upload ay bagong revision (Original, Rev 1, Rev 2...),
// at ang pinakabago ang nagiging "current" 2D na nakikita ng Cutting.
//
// Actions:
//   list    (GET)  — inquiry_id (Designer/Sales) o quotation_id (Cutting) → revisions, pinakabago muna
//   upload  (POST) — Designer lang: inquiry_id, files[] (PDF), change_notes, feedback_ids (JSON array)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER, ROLE_SALES, ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

// Laging JSON ang sagot, kahit may SQL error (para hindi "Connection error" lang ang makita).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

const REV_MAX_FILES = 10;
const REV_MAX_BYTES = 15 * 1024 * 1024; // 15MB bawat file

function revRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
// ⚠️ Kaparehong session key ng crm2dquotationajax.php
$currentUserRole = $_SESSION['role'] ?? '';
$isSales = ($currentUserRole === ROLE_SALES);
$isCutting = ($currentUserRole === ROLE_CUTTING);
$isDesigner = !$isSales && !$isCutting;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kapag lumampas sa post_max_size, binubura ng PHP ang buong $_POST at $_FILES.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    revRespond(false, 'The files are too large for the server to accept. Try fewer or smaller files.');
}

// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════

function revUrl(?string $path): ?string
{
    return !empty($path) ? BASE_URL . '/' . $path : null;
}

function revFmt(?string $dt): ?string
{
    return !empty($dt) ? date('M d, Y g:i A', strtotime($dt)) : null;
}

function revRoleLabel(?string $role): string
{
    if ($role === 'designer') return 'Designer';
    if ($role === 'sales') return 'Sales';
    return '—';
}

// Pinakabagong Initial entry ng inquiry (kapareho ng q2dGetLatestEntry)
function revLatestEntry(mysqli $conn, int $inquiryId): ?array
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

// Alamin kung aling quotation row ang titingnan, at kung may access ang user.
function revResolveQuotation(mysqli $conn, bool $isCutting, bool $isSales, int $userId): array
{
    if ($isCutting) {
        $qid = intval($_GET['quotation_id'] ?? $_POST['quotation_id'] ?? 0);
        if ($qid <= 0) revRespond(false, 'Invalid record.');
        $stmt = $conn->prepare("SELECT * FROM noblecrm_2dquotation WHERE id = ? AND status = 'Approved' LIMIT 1");
        $stmt->bind_param("i", $qid);
        $stmt->execute();
        $q = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$q) revRespond(false, 'Record not found or not yet approved.');
        return $q;
    }

    $inquiryId = intval($_GET['inquiry_id'] ?? $_POST['inquiry_id'] ?? 0);
    if ($inquiryId <= 0) revRespond(false, 'Missing or invalid inquiry reference.');

    $ownerColumn = $isSales ? 'sales_staff_id' : 'designer_id';
    $stmt = $conn->prepare("SELECT id FROM noblecrminquiry WHERE id = ? AND {$ownerColumn} = ? LIMIT 1");
    $stmt->bind_param("ii", $inquiryId, $userId);
    $stmt->execute();
    $own = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$own) revRespond(false, 'Inquiry not found, or not assigned to you.');

    $q = revLatestEntry($conn, $inquiryId);
    if (!$q) revRespond(true, '', ['revisions' => [], 'current_revision_no' => null, 'quotation_id' => null]);
    return $q;
}

// Kasalukuyang 2D files ng quotation (galing sa multi-file table; fallback sa design_2d_path)
function revCurrent2dFiles(mysqli $conn, array $q): array
{
    $stmt = $conn->prepare("
        SELECT path, original_name, uploaded_at
        FROM noblecrm_2dquotation_files
        WHERE quotation_id = ? AND slot = '2d'
        ORDER BY id ASC
    ");
    $qid = (int) $q['id'];
    $stmt->bind_param("i", $qid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rows && !empty($q['design_2d_path'])) {
        $rows = [['path' => $q['design_2d_path'], 'original_name' => basename($q['design_2d_path']), 'uploaded_at' => $q['design_2d_uploaded_at'] ?? null]];
    }
    return $rows;
}

function revAccountName(mysqli $conn, ?int $id): string
{
    if (!$id) return '—';
    $stmt = $conn->prepare("SELECT name FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? '—';
}

// I-save ang PDF sa disk (kapareho ng q2dSaveUploadedPdf, pero "design2d_rev" ang prefix)
function revSavePdf(array $file, int $inquiryId, string $uploadDir): array
{
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => '"' . $file['name'] . '" is too large for the server to accept.'];
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
    if ($file['size'] > REV_MAX_BYTES) {
        return ['path' => null, 'error' => '"' . $file['name'] . '" exceeds the 15MB limit.'];
    }

    $safeName = 'design2d_rev_' . $inquiryId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
        return ['path' => null, 'error' => 'Something went wrong while saving the file.'];
    }
    return ['path' => 'uploads/crm-2dquotation/' . $safeName, 'error' => null];
}

function revNormalizeFiles(array $f): array
{
    $out = [];
    if (!isset($f['name'])) return $out;
    if (!is_array($f['name'])) return ($f['error'] === UPLOAD_ERR_NO_FILE) ? [] : [$f];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

// Listahan ng revisions (pinakabago muna). Kung wala pa, ang kasalukuyang 2D ang "Original".
function revBuildList(mysqli $conn, array $q): array
{
    $qid = (int) $q['id'];

    // Feedback messages, para maipakita kung alin ang sinagot ng bawat revision
    $fbMap = [];
    $stmt = $conn->prepare("
        SELECT f.id, f.message, f.created_at, r.name AS created_by_name
        FROM noblecrm_2d_feedback f
        LEFT JOIN noblerole r ON r.id = f.created_by
        WHERE f.quotation_id = ?
    ");
    $stmt->bind_param("i", $qid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($f = $res->fetch_assoc()) {
        $fbMap[(int) $f['id']] = [
            'id' => (int) $f['id'],
            'message' => $f['message'],
            'created_by_name' => $f['created_by_name'] ?? '—',
            'created_at' => revFmt($f['created_at']),
        ];
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT rv.*, r.name AS created_by_name
        FROM noblecrm_2d_revision rv
        LEFT JOIN noblerole r ON r.id = rv.created_by
        WHERE rv.quotation_id = ?
        ORDER BY rv.revision_no DESC
    ");
    $stmt->bind_param("i", $qid);
    $stmt->execute();
    $revs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$revs) {
        // Wala pang revision → ipakita ang kasalukuyang 2D bilang Original (hindi pa sine-save)
        $files = revCurrent2dFiles($conn, $q);
        if (!$files) return [];
        return [[
            'id' => 0,
            'revision_no' => 0,
            'label' => 'Original',
            'is_current' => true,
            'change_notes' => null,
            'created_at' => revFmt($files[0]['uploaded_at'] ?? ($q['design_2d_uploaded_at'] ?? null)),
            'created_by_name' => revAccountName($conn, !empty($q['design_2d_uploaded_by']) ? (int) $q['design_2d_uploaded_by'] : null),
            'created_role' => revRoleLabel($q['design_2d_uploaded_role'] ?? null),
            'answers' => [],
            'files' => array_map(fn($f) => ['url' => revUrl($f['path']), 'name' => $f['original_name'] ?: basename($f['path'])], $files),
        ]];
    }

    $ids = implode(',', array_map(fn($r) => (int) $r['id'], $revs));
    $filesByRev = [];
    $res = $conn->query("SELECT revision_id, path, original_name FROM noblecrm_2d_revision_file WHERE revision_id IN ({$ids}) ORDER BY id ASC");
    while ($f = $res->fetch_assoc()) {
        $filesByRev[(int) $f['revision_id']][] = ['url' => revUrl($f['path']), 'name' => $f['original_name'] ?: basename($f['path'])];
    }

    $out = [];
    foreach ($revs as $i => $r) {
        $answers = [];
        foreach (array_filter(array_map('intval', explode(',', (string) $r['feedback_ids']))) as $fid) {
            if (isset($fbMap[$fid])) $answers[] = $fbMap[$fid];
        }
        $no = (int) $r['revision_no'];
        $out[] = [
            'id' => (int) $r['id'],
            'revision_no' => $no,
            'label' => $no === 0 ? 'Original' : 'Rev ' . $no,
            'is_current' => $i === 0,
            'change_notes' => $r['change_notes'],
            'created_at' => revFmt($r['created_at']),
            'created_by_name' => $r['created_by_name'] ?? '—',
            'created_role' => revRoleLabel($r['created_role']),
            'answers' => $answers,
            'files' => $filesByRev[(int) $r['id']] ?? [],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════════════════════

if ($action === 'list') {
    $q = revResolveQuotation($conn, $isCutting, $isSales, $currentUserId);
    $list = revBuildList($conn, $q);
    revRespond(true, '', [
        'quotation_id' => (int) $q['id'],
        'revisions' => $list,
        'current_revision_no' => $list ? $list[0]['revision_no'] : null,
        'next_revision_no' => $list ? $list[0]['revision_no'] + 1 : 1,
    ]);
}

if ($action === 'upload') {
    if (!$isDesigner) {
        revRespond(false, 'Only the assigned designer can upload a 2D revision.');
    }

    $inquiryId = intval($_POST['inquiry_id'] ?? 0);
    $q = revResolveQuotation($conn, false, false, $currentUserId);
    $qid = (int) $q['id'];

    if ($q['status'] !== 'Approved') {
        revRespond(false, 'Revisions can only be uploaded after the 2D & Quotation is approved.');
    }

    // ── Validation (server-side) ──
    $notes = trim((string) ($_POST['change_notes'] ?? ''));
    if (mb_strlen($notes) < 5) {
        revRespond(false, 'Please describe what changed in this revision.');
    }
    if (mb_strlen($notes) > 2000) {
        revRespond(false, '"What changed" is too long (max 2000 characters).');
    }

    $files = revNormalizeFiles($_FILES['files'] ?? []);
    if (!$files) {
        revRespond(false, 'Please attach the revised 2D PDF.');
    }
    if (count($files) > REV_MAX_FILES) {
        revRespond(false, 'Maximum of ' . REV_MAX_FILES . ' files per revision.');
    }

    // Aling feedback ang sinagot (dapat bukas pa at para sa quotation na ito)
    $openStmt = $conn->prepare("SELECT id FROM noblecrm_2d_feedback WHERE quotation_id = ? AND is_resolved = 0");
    $openStmt->bind_param("i", $qid);
    $openStmt->execute();
    $openIds = array_map(fn($r) => (int) $r['id'], $openStmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $openStmt->close();

    if (!$openIds) {
        revRespond(false, 'There is no open feedback from Cutting on this 2D file.');
    }

    $picked = json_decode((string) ($_POST['feedback_ids'] ?? '[]'), true);
    $picked = is_array($picked) ? array_values(array_unique(array_map('intval', $picked))) : [];
    $answerIds = array_values(array_intersect($picked, $openIds));
    if (!$answerIds) {
        revRespond(false, 'Choose which feedback this revision answers.');
    }

    // ── I-save ang files sa disk (rollback kapag may pumalya) ──
    $uploadDir = ROOT_PATH . '/uploads/crm-2dquotation/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $saved = [];
    foreach ($files as $f) {
        $r = revSavePdf($f, $inquiryId, $uploadDir);
        if (!$r['path']) {
            foreach ($saved as $s) @unlink(ROOT_PATH . '/' . $s['path']);
            revRespond(false, $r['error']);
        }
        $saved[] = ['path' => $r['path'], 'name' => $f['name']];
    }

    $roleLabel = 'designer';
    $conn->begin_transaction();
    try {
        // Lock para hindi magdoble ang revision number kapag sabay na nag-upload
        $lock = $conn->prepare("SELECT id FROM noblecrm_2dquotation WHERE id = ? FOR UPDATE");
        $lock->bind_param("i", $qid);
        $lock->execute();
        $lock->close();

        $maxStmt = $conn->prepare("SELECT MAX(revision_no) AS m, COUNT(*) AS c FROM noblecrm_2d_revision WHERE quotation_id = ?");
        $maxStmt->bind_param("i", $qid);
        $maxStmt->execute();
        $mx = $maxStmt->get_result()->fetch_assoc();
        $maxStmt->close();

        $insRev = $conn->prepare("
            INSERT INTO noblecrm_2d_revision
                (quotation_id, inquiry_id, revision_no, change_notes, feedback_ids, created_by, created_role, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insFile = $conn->prepare("INSERT INTO noblecrm_2d_revision_file (revision_id, path, original_name) VALUES (?, ?, ?)");

        // Unang revision → i-save muna ang kasalukuyang 2D bilang "Original" (Rev 0) para hindi mawala
        if ((int) $mx['c'] === 0) {
            $origFiles = revCurrent2dFiles($conn, $q);
            if ($origFiles) {
                $zero = 0;
                $nullNotes = null;
                $nullFb = null;
                $origBy = !empty($q['design_2d_uploaded_by']) ? (int) $q['design_2d_uploaded_by'] : null;
                $origRole = $q['design_2d_uploaded_role'] ?? null;
                $origAt = $origFiles[0]['uploaded_at'] ?? ($q['design_2d_uploaded_at'] ?? null);
                if (empty($origAt)) $origAt = $conn->query("SELECT NOW() AS n")->fetch_assoc()['n'];
                $insRev->bind_param("iiississ", $qid, $inquiryId, $zero, $nullNotes, $nullFb, $origBy, $origRole, $origAt);
                $insRev->execute();
                $origRevId = (int) $insRev->insert_id;
                foreach ($origFiles as $of) {
                    $oname = $of['original_name'] ?: basename($of['path']);
                    $insFile->bind_param("iss", $origRevId, $of['path'], $oname);
                    $insFile->execute();
                }
            }
            $nextNo = 1;
        } else {
            $nextNo = (int) $mx['m'] + 1;
        }

        // Ang bagong revision
        $fbCsv = implode(',', $answerIds);
        $now = $conn->query("SELECT NOW() AS n")->fetch_assoc()['n']; // kaparehong oras ng DB (gaya ng ibang NOW())
        $insRev->bind_param("iiississ", $qid, $inquiryId, $nextNo, $notes, $fbCsv, $currentUserId, $roleLabel, $now);
        $insRev->execute();
        $revId = (int) $insRev->insert_id;
        foreach ($saved as $s) {
            $insFile->bind_param("iss", $revId, $s['path'], $s['name']);
            $insFile->execute();
        }
        $insRev->close();
        $insFile->close();

        // Gawing "current" ang bagong revision sa 2D slot (para ito ang makita ng Cutting at ng ibang pages).
        // Hindi binubura ang lumang files sa disk — nasa revision history pa rin sila.
        $del = $conn->prepare("DELETE FROM noblecrm_2dquotation_files WHERE quotation_id = ? AND slot = '2d'");
        $del->bind_param("i", $qid);
        $del->execute();
        $del->close();

        $insCur = $conn->prepare("
            INSERT INTO noblecrm_2dquotation_files
                (quotation_id, inquiry_id, slot, path, original_name, uploaded_by, uploaded_role)
            VALUES (?, ?, '2d', ?, ?, ?, ?)
        ");
        foreach ($saved as $s) {
            $insCur->bind_param("iissis", $qid, $inquiryId, $s['path'], $s['name'], $currentUserId, $roleLabel);
            $insCur->execute();
        }
        $insCur->close();

        // design_2d_path = unang file; done ulit; bagong file → kailangang i-verify ulit ng Cutting
        $firstPath = $saved[0]['path'];
        $upd = $conn->prepare("
            UPDATE noblecrm_2dquotation
            SET design_2d_path = ?, design_2d_done = 1,
                design_2d_uploaded_by = ?, design_2d_uploaded_role = ?, design_2d_uploaded_at = NOW(),
                design_2d_verified_by_cutting = NULL, design_2d_verified_at = NULL
            WHERE id = ?
        ");
        $upd->bind_param("sisi", $firstPath, $currentUserId, $roleLabel, $qid);
        $upd->execute();
        $upd->close();

        // I-resolve lang ang feedback na pinili
        $in = implode(',', $answerIds);
        $conn->query("UPDATE noblecrm_2d_feedback SET is_resolved = 1 WHERE quotation_id = {$qid} AND id IN ({$in})");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($saved as $s) @unlink(ROOT_PATH . '/' . $s['path']);
        revRespond(false, 'Could not save the revision: ' . $e->getMessage());
    }

    // Abisuhan ang mga taga-Cutting na nagbigay ng feedback (hindi dapat pumalya ang save kapag pumalya ito)
    $warning = null;
    try {
        $inq = $conn->prepare("SELECT control_no, client_name FROM noblecrminquiry WHERE id = ? LIMIT 1");
        $inq->bind_param("i", $inquiryId);
        $inq->execute();
        $inqRow = $inq->get_result()->fetch_assoc() ?: ['control_no' => '', 'client_name' => ''];
        $inq->close();

        $res = $conn->query("SELECT DISTINCT created_by FROM noblecrm_2d_feedback WHERE id IN ({$in}) AND created_by IS NOT NULL");
        $recipients = array_filter(array_map(fn($r) => (int) $r['created_by'], $res->fetch_all(MYSQLI_ASSOC)), fn($id) => $id > 0 && $id !== $currentUserId);

        if ($recipients) {
            $msg = "Designer uploaded 2D Rev {$nextNo} for {$inqRow['client_name']} (Control No. {$inqRow['control_no']})";
            $link = "/crmcuttinglist";
            $n = $conn->prepare("
                INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
                VALUES (?, ?, ?, 'crm_2drevision', ?, 0, NOW(), ?, ?)
            ");
            foreach ($recipients as $uid) {
                $n->bind_param("iissis", $uid, $inquiryId, $inqRow['control_no'], $msg, $currentUserId, $link);
                $n->execute();
            }
            $n->close();
        }
    } catch (Throwable $e) {
        $warning = 'Saved, but Cutting could not be notified: ' . $e->getMessage();
    }

    revRespond(true, "Rev {$nextNo} sent to Cutting.", ['revision_no' => $nextNo, 'warning' => $warning]);
}

revRespond(false, 'Unknown action.');