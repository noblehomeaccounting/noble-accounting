<?php
// coordinatorlaborajax.php  —  Project Coordinator: Step 3 (Foreman ID → Labor Quotation)
// Para lang sa DR na approved na ng Superadmin. Ang quotation ay papasok sa Superadmin (superadmin-labor.php).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Realtime doorbell (Supabase Broadcast): sinasabihan ang ibang bukas na page na may nagbago.
// Hindi kailanman pinipigilan ang save — kapag pumalya, naka-log lang.
if (!function_exists('pcRealtime')) {
    function pcRealtime(mysqli $conn, string $type, array $groups, array $payload = []): void
    {
        try {
            include_once ROOT_PATH . '/network/realtime-helper.php';
            $payload = ['type' => $type, 'by' => intval($_SESSION['account_id'] ?? 0)] + $payload;
            foreach ($groups as [$role, $pos]) {
                nhRealtimeNotifyGroup((string) $role, $pos !== null ? (string) $pos : null, 'changed', $payload);
            }
            // Sales + Designer ng inquiry → makikita agad sa CRM List ang galaw ng project hanggang Turnover
            {
                $drId = (int) ($payload['dr_id'] ?? 0);
                foreach (['quote_id' => 'noblepc_laborquote', 'billing_id' => 'noblepc_billing', 'turnover_id' => 'noblepc_turnover'] as $key => $table) {
                    if ($drId > 0 || empty($payload[$key])) continue;
                    $st = $conn->prepare("SELECT dr_id FROM $table WHERE id = ? LIMIT 1");
                    $id = (int) $payload[$key];
                    $st->bind_param('i', $id);
                    $st->execute();
                    $drId = (int) ($st->get_result()->fetch_assoc()['dr_id'] ?? 0);
                    $st->close();
                }
                if ($drId > 0) {
                    $st = $conn->prepare("SELECT q.inquiry_id FROM noblepc_dr d
                                          JOIN nobletransmittal t ON t.id = d.transmittal_id
                                          JOIN noblepurchaserequest p ON p.id = t.pr_id
                                          JOIN noblecrm_bom b ON b.id = p.bom_id
                                          JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                                          WHERE d.id = ? LIMIT 1");
                    $st->bind_param('i', $drId);
                    $st->execute();
                    $inq = (int) ($st->get_result()->fetch_assoc()['inquiry_id'] ?? 0);
                    $st->close();
                    if ($inq > 0) nhRtInquiry($conn, $inq, [], 'crm_project');
                }
            }
        } catch (Throwable $e) {
            error_log('pcRealtime: ' . $e->getMessage());
        }
    }
}

function pcOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[coordinatorlaborajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function pcAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function pcOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return pcAll($conn, $sql, $types, $args)[0] ?? null;
}
function pcStr(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}
function pcDate(?string $d): ?string
{
    $d = trim((string) $d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null;
}
function pcUserName(mysqli $conn, int $uid): string
{
    return pcOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function pcSigUrl(?string $path): ?string
{
    return $path ? rtrim(BASE_URL, '/') . '/' . ltrim($path, '/') : null;
}

// ================= LIST: lahat ng approved DR + estado ng Step 3 =================
if ($action === 'list') {
    $rows = pcAll($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.dr_date, d.client_name, d.address, d.approved_at, d.permit_required,
               t.ref_no, t.project_name,
               f.name AS foreman_name,
               q.id AS quote_id, q.quote_no, q.status AS quote_status, q.total AS quote_total, q.return_remarks AS quote_remarks,
               q.approved_by AS quote_approved_by, q.approved_at AS quote_approved_at, COALESCE(q.submitted_at, q.created_at) AS quote_submitted_at
        FROM noblepc_dr d
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_foreman f ON f.dr_id = d.id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id
        WHERE d.status = 'approved'
        ORDER BY FIELD(CASE WHEN f.id IS NULL THEN 'foreman' WHEN q.id IS NULL THEN 'quote' ELSE q.status END, 'returned', 'foreman', 'quote', 'pending', 'approved'),
                 d.approved_at DESC, d.id DESC
    ");
    foreach ($rows as &$r) {
        $r['dr_id'] = (int) $r['dr_id'];
        $r['quote_total'] = $r['quote_total'] !== null ? (float) $r['quote_total'] : null;
        $r['permit_required'] = $r['permit_required'] !== null ? (int) $r['permit_required'] : null;
        // foreman → quote → pending / returned / approved
        $r['labor_state'] = !$r['foreman_name'] ? 'foreman' : (!$r['quote_id'] ? 'quote' : $r['quote_status']);
    }
    unset($r);
    pcOut(['success' => true, 'rows' => $rows]);
}

// =====================================================================
// STEP 3 — FOREMAN + LABOR QUOTATION (para sa DR na approved na)
// =====================================================================
const LQ_PHOTO_DIR = 'uploads/pcforeman';
const LQ_PHOTO_MAX = 5 * 1024 * 1024;

function pcApprovedDr(mysqli $conn, int $drId): array
{
    $dr = pcOne($conn, "SELECT d.id, d.dr_no, d.dr_date, d.client_name, d.address, COALESCE(d.status, 'pending') AS status, t.ref_no, t.project_name
                        FROM noblepc_dr d JOIN nobletransmittal t ON t.id = d.transmittal_id WHERE d.id = ? LIMIT 1", 'i', [$drId]);
    if (!$dr) pcOut(['success' => false, 'message' => 'DR not found.']);
    if ($dr['status'] !== 'approved') pcOut(['success' => false, 'message' => 'The DR must be approved by the Superadmin first.']);
    return $dr;
}
function pcForeman(mysqli $conn, int $drId): ?array
{
    $f = pcOne($conn, "SELECT id, dr_id, name, contact_no, id_type, id_number, id_photo, updated_at, created_at FROM noblepc_foreman WHERE dr_id = ? LIMIT 1", 'i', [$drId]);
    if ($f) $f['id_photo_url'] = pcSigUrl($f['id_photo']);
    return $f;
}
function pcQuote(mysqli $conn, int $drId): ?array
{
    $q = pcOne($conn, "SELECT * FROM noblepc_laborquote WHERE dr_id = ? LIMIT 1", 'i', [$drId]);
    if (!$q) return null;
    $q['items'] = pcAll($conn, "SELECT section_no, section_name, item_no, item, qty, unit, unit_cost FROM noblepc_laborquote_item WHERE quote_id = ? ORDER BY section_no, item_no", 'i', [(int) $q['id']]);
    $q['signature_url'] = $q['status'] === 'approved' ? pcSigUrl($q['approval_signature']) : null;
    unset($q['approval_signature']);
    return $q;
}

// ================= LABOR: lahat ng kailangan sa Step 3 ng isang DR =================
if ($action === 'labor_get') {
    $drId = intval($_GET['dr_id'] ?? 0);
    $dr = pcApprovedDr($conn, $drId);
    pcOut(['success' => true, 'dr' => $dr, 'foreman' => pcForeman($conn, $drId), 'quote' => pcQuote($conn, $drId),
           'me' => pcUserName($conn, $currentUserId), 'quote_preview' => 'LQ-' . date('Y') . '-xxx']);
}

// ================= SAVE FOREMAN (may picture ng ID) =================
if ($action === 'save_foreman') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pcOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    pcApprovedDr($conn, $drId);
    $q = pcOne($conn, "SELECT status FROM noblepc_laborquote WHERE dr_id = ?", 'i', [$drId]);
    if ($q && $q['status'] === 'approved') pcOut(['success' => false, 'message' => 'The labor quotation is already approved. The foreman can no longer be changed.']);

    $name    = pcStr('name');
    $contact = pcStr('contact_no', 50);
    $idType  = pcStr('id_type', 100);
    $idNo    = pcStr('id_number', 100);
    if (mb_strlen($name) < 2) pcOut(['success' => false, 'message' => "Enter the foreman's full name.", 'field' => 'name']);
    if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) pcOut(['success' => false, 'message' => 'Enter a valid contact number.', 'field' => 'contact_no']);

    $old = pcForeman($conn, $drId);
    $newPath = null;
    $file = $_FILES['id_photo'] ?? null;
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) pcOut(['success' => false, 'message' => $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? 'The ID photo is too large (max 5 MB).' : 'The ID photo could not be uploaded. Please try again.', 'field' => 'id_photo']);
        if ($file['size'] > LQ_PHOTO_MAX) pcOut(['success' => false, 'message' => 'The ID photo is too large (max 5 MB).', 'field' => 'id_photo']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext || !@getimagesize($file['tmp_name'])) pcOut(['success' => false, 'message' => 'The ID photo must be a JPG, PNG or WEBP image.', 'field' => 'id_photo']);
        $dir = ROOT_PATH . '/' . LQ_PHOTO_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) pcOut(['success' => false, 'message' => 'Could not create the upload folder on the server.']);
        $newPath = LQ_PHOTO_DIR . '/foreman-dr' . $drId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $newPath)) pcOut(['success' => false, 'message' => 'Could not save the ID photo on the server.']);
    }
    if (!$newPath && !$old) pcOut(['success' => false, 'message' => "Upload a photo of the foreman's ID.", 'field' => 'id_photo']);
    $photo = $newPath ?: $old['id_photo'];

    try {
        if ($old) {
            $st = $conn->prepare("UPDATE noblepc_foreman SET name = ?, contact_no = ?, id_type = ?, id_number = ?, id_photo = ?, updated_at = NOW() WHERE dr_id = ?");
            $st->bind_param('sssssi', $name, $contact, $idType, $idNo, $photo, $drId);
        } else {
            $st = $conn->prepare("INSERT INTO noblepc_foreman (dr_id, name, contact_no, id_type, id_number, id_photo, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $st->bind_param('isssssi', $drId, $name, $contact, $idType, $idNo, $photo, $currentUserId);
        }
        $st->execute();
        $st->close();
    } catch (Throwable $e) {
        if ($newPath) @unlink(ROOT_PATH . '/' . $newPath);
        error_log('[coordenatorajax save_foreman] ' . $e->getMessage());
        pcOut(['success' => false, 'message' => 'Could not save the foreman. Please try again.']);
    }
    // burahin ang lumang picture kapag napalitan
    if ($newPath && $old && $old['id_photo'] !== $newPath && strpos($old['id_photo'], LQ_PHOTO_DIR . '/') === 0) @unlink(ROOT_PATH . '/' . $old['id_photo']);
    pcRealtime($conn, 'pc_foreman', [[ROLE_PC, null]], ['dr_id' => $drId]);
    pcOut(['success' => true, 'foreman' => pcForeman($conn, $drId)]);
}

// ================= SAVE LABOR QUOTATION → papasok sa Superadmin =================
if ($action === 'save_quote') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pcOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    $dr = pcApprovedDr($conn, $drId);
    if (!pcForeman($conn, $drId)) pcOut(['success' => false, 'message' => "Add the foreman's ID first before making the quotation."]);

    $sub      = pcStr('subcontractor');
    $subject  = pcStr('subject') ?: 'Quotation for Works (Labor Only)';
    $date     = pcDate($_POST['quote_date'] ?? '') ?: date('Y-m-d');
    $project  = pcStr('project_name') ?: ($dr['client_name'] ?? '');
    $location = pcStr('location') ?: ($dr['address'] ?? '');
    $durRaw   = trim((string) ($_POST['duration_days'] ?? ''));
    $dpRaw    = trim((string) ($_POST['downpayment_pct'] ?? ''));
    $duration = $durRaw === '' ? null : (int) $durRaw;
    $dp       = $dpRaw === '' ? null : round((float) $dpRaw, 2);
    if ($sub === '') pcOut(['success' => false, 'message' => 'Subcontractor is required.', 'field' => 'subcontractor']);
    if ($duration !== null && ($duration < 1 || $duration > 3650)) pcOut(['success' => false, 'message' => 'Duration must be between 1 and 3650 days.', 'field' => 'duration_days']);
    if ($dp !== null && ($dp < 0 || $dp > 100)) pcOut(['success' => false, 'message' => 'Down payment must be 0 to 100%.', 'field' => 'downpayment_pct']);

    // items: may section bawat row; i-group ayon sa pagkakasunod (A, B, C…)
    $rows = [];
    $secOrder = [];
    foreach ((array) ($_POST['item'] ?? []) as $i => $item) {
        $item = mb_substr(trim((string) $item), 0, 255);
        if ($item === '') continue;
        $sec = mb_substr(trim((string) ($_POST['section'][$i] ?? '')), 0, 150);
        if ($sec === '') pcOut(['success' => false, 'message' => "Give a section name for \"$item\" (e.g. CLOSET)."]);
        $qty  = (float) str_replace(',', '', (string) ($_POST['qty'][$i] ?? 0));
        $cost = (float) str_replace(',', '', (string) ($_POST['unit_cost'][$i] ?? 0));
        if ($qty <= 0) pcOut(['success' => false, 'message' => "Enter the quantity for \"$item\"."]);
        if ($cost < 0) pcOut(['success' => false, 'message' => "The unit cost for \"$item\" cannot be negative."]);
        $key = mb_strtoupper($sec);
        if (!isset($secOrder[$key])) $secOrder[$key] = count($secOrder) + 1;
        $rows[] = [$secOrder[$key], $key, $item, round($qty, 2), mb_substr(trim((string) ($_POST['unit'][$i] ?? '')), 0, 30), round($cost, 2)];
    }
    if (!$rows) pcOut(['success' => false, 'message' => 'Add at least one item.']);
    usort($rows, fn($a, $b) => $a[0] <=> $b[0]);   // stable sa PHP 8
    $total = round(array_sum(array_map(fn($r) => round($r[3] * $r[5], 2), $rows)), 2);
    $me = pcUserName($conn, $currentUserId);

    $conn->begin_transaction();
    try {
        $cur = pcOne($conn, "SELECT id, quote_no, status FROM noblepc_laborquote WHERE dr_id = ? FOR UPDATE", 'i', [$drId]);
        if ($cur && $cur['status'] === 'approved') throw new RuntimeException('This labor quotation is already approved. It can no longer be edited.');
        if ($cur) {
            $qid = (int) $cur['id'];
            $qno = $cur['quote_no'];
            $st = $conn->prepare("UPDATE noblepc_laborquote SET project_name = ?, location = ?, subcontractor = ?, subject = ?, quote_date = ?, duration_days = ?, downpayment_pct = ?, total = ?,
                                         status = 'pending', submitted_at = NOW() WHERE id = ?");
            $st->bind_param('sssssiddi', $project, $location, $sub, $subject, $date, $duration, $dp, $total, $qid);
            $st->execute();
            $st->close();
            $del = $conn->prepare("DELETE FROM noblepc_laborquote_item WHERE quote_id = ?");
            $del->bind_param('i', $qid);
            $del->execute();
            $del->close();
        } else {
            $prefix = 'LQ-' . date('Y', strtotime($date)) . '-';
            $last = pcOne($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(quote_no, ?) AS UNSIGNED)), 0) AS n FROM noblepc_laborquote WHERE quote_no LIKE CONCAT(?, '%') FOR UPDATE",
                'is', [strlen($prefix) + 1, $prefix]);
            $qno = $prefix . str_pad((string) (((int) $last['n']) + 1), 3, '0', STR_PAD_LEFT);
            $st = $conn->prepare("INSERT INTO noblepc_laborquote (dr_id, quote_no, project_name, location, subcontractor, subject, quote_date, duration_days, downpayment_pct, total,
                                         prepared_by, created_by, created_at, status, submitted_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', NOW())");
            $st->bind_param('issssssiddsi', $drId, $qno, $project, $location, $sub, $subject, $date, $duration, $dp, $total, $me, $currentUserId);
            $st->execute();
            $qid = $st->insert_id;
            $st->close();
        }
        $it = $conn->prepare("INSERT INTO noblepc_laborquote_item (quote_id, section_no, section_name, item_no, item, qty, unit, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $n = [];
        foreach ($rows as [$secNo, $secName, $item, $qty, $unit, $cost]) {
            $n[$secNo] = ($n[$secNo] ?? 0) + 1;
            $itemNo = $n[$secNo];
            $it->bind_param('iisisdsd', $qid, $secNo, $secName, $itemNo, $item, $qty, $unit, $cost);
            $it->execute();
        }
        $it->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if (!$e instanceof RuntimeException) error_log('[coordenatorajax save_quote] ' . $e->getMessage());
        pcOut(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the quotation. Please try again.']);
    }
    pcRealtime($conn, 'pc_labor', [[ROLE_PC, null], [ROLE_SUPERADMIN, null]], ['dr_id' => $drId]);
    pcOut(['success' => true, 'quote_no' => $qno, 'was' => $cur['status'] ?? null]);
}

pcOut(['success' => false, 'message' => 'Unknown action.']);