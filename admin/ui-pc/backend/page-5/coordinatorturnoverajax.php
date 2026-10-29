<?php
// coordinatorturnoverajax.php  —  Project Coordinator: Step 7 Punch List + Step 8 Turnover & Closeout
// Turnover letter: PC → Superadmin (pirma ni GM) → print → pirma ng client → Complete Turnover (Closed)

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

const TO_UPLOAD_DIR = 'uploads/pcturnover';
const TO_UPLOAD_MAX = 10 * 1024 * 1024;

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

function tOut(array $d): void
{
    echo json_encode($d);
    exit;
}
set_exception_handler(function (Throwable $e) {
    error_log('[coordinatorturnoverajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});
function tAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function tOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return tAll($conn, $sql, $types, $args)[0] ?? null;
}
function tExec(mysqli $conn, string $sql, string $types, array $args): int
{
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$args);
    $st->execute();
    $n = $st->affected_rows;
    $st->close();
    return $n;
}
function tStr(string $k, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
}
function tDate($d): ?string
{
    $d = trim((string) $d);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
    return $d;
}
function tUrl(?string $p): ?string
{
    return $p ? rtrim(BASE_URL, '/') . '/' . ltrim($p, '/') : null;
}
function tUserName(mysqli $conn, int $uid): string
{
    return tOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
function tProject(mysqli $conn, int $drId): array
{
    $p = tOne($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name AS owner, d.address, t.ref_no, t.project_name AS tr_project,
               q.id AS quote_id, q.quote_no, q.status AS quote_status, q.project_name, q.location, q.subcontractor, q.total AS contract_amount
        FROM noblepc_dr d JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id WHERE d.id = ? LIMIT 1", 'i', [$drId]);
    if (!$p) tOut(['success' => false, 'message' => 'Project not found.']);
    if (($p['quote_status'] ?? '') !== 'approved') tOut(['success' => false, 'message' => 'The labor quotation must be approved first.']);
    $p['contract_amount'] = (float) $p['contract_amount'];
    return $p;
}
// Readiness checklist (Step 1–7)
function tReadiness(mysqli $conn, int $drId, float $contract): array
{
    $today = date('Y-m-d');
    $tl = tOne($conn, "SELECT date_started, date_finish FROM noblepc_timeline WHERE dr_id = ?", 'i', [$drId]);
    $pu = tOne($conn, "SELECT COUNT(*) AS n, SUM(status = 'verified') AS v, SUM(status = 'open') AS o FROM noblepc_punch WHERE dr_id = ?", 'i', [$drId]);
    $bi = tOne($conn, "SELECT COUNT(*) AS n, SUM(status = 'approved') AS a, COALESCE(SUM(this_period), 0) AS billed,
                              COALESCE(SUM(CASE WHEN status = 'approved' THEN this_period END), 0) AS approved,
                              (SELECT change_orders FROM noblepc_billing x WHERE x.dr_id = ? ORDER BY seq DESC LIMIT 1) AS co
                       FROM noblepc_billing WHERE dr_id = ?", 'ii', [$drId, $drId]);
    $sum = round($contract + (float) ($bi['co'] ?? 0), 2);
    $punchN = (int) $pu['n'];
    $punchV = (int) $pu['v'];
    return [
        ['key' => 'timeline', 'label' => 'Timeline is done (100%)', 'ok' => $tl && $tl['date_finish'] <= $today, 'required' => false,
         'note' => !$tl ? 'No timeline yet' : ($tl['date_finish'] <= $today ? 'Finished ' . date('M j, Y', strtotime($tl['date_finish'])) : 'Ends ' . date('M j, Y', strtotime($tl['date_finish'])) . ' (can still turn over if the work is done early)')],
        ['key' => 'punch', 'label' => 'Punch list is closed (all verified)', 'ok' => $punchN === $punchV, 'required' => true,
         'note' => $punchN ? "$punchV of $punchN verified" : 'No punch item (no defects found)'],
        ['key' => 'billing', 'label' => 'Billing is complete and approved', 'ok' => (int) $bi['n'] > 0 && (int) $bi['n'] === (int) $bi['a'] && (float) $bi['approved'] >= $sum - 0.004, 'required' => true,
         'note' => '₱' . number_format((float) $bi['approved'], 2) . ' of ₱' . number_format($sum, 2) . ' approved' . (((int) $bi['n'] - (int) $bi['a']) > 0 ? ' · ' . ((int) $bi['n'] - (int) $bi['a']) . ' still being signed' : '')],
    ];
}
function tReadyOk(array $r): bool
{
    foreach ($r as $c) if ($c['required'] && !$c['ok']) return false;
    return true;
}
function tTurnover(mysqli $conn, int $drId): ?array
{
    $t = tOne($conn, "SELECT * FROM noblepc_turnover WHERE dr_id = ? LIMIT 1", 'i', [$drId]);
    if (!$t) return null;
    $t['scope'] = tAll($conn, "SELECT item_no, scope, area, status FROM noblepc_turnover_scope WHERE turnover_id = ? ORDER BY item_no", 'i', [(int) $t['id']]);
    $t['sa_signature_url'] = in_array($t['status'], ['approved', 'closed'], true) ? tUrl($t['sa_signature']) : null;
    $t['signed_copy_url'] = tUrl($t['signed_copy']);
    $t['client_signed'] = (int) $t['client_signed'];
    unset($t['sa_signature']);
    return $t;
}

// ================= LIST =================
if ($action === 'list') {
    $rows = tAll($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name AS owner, q.quote_no, q.project_name, q.location, q.subcontractor, q.total AS contract_amount,
               (SELECT COUNT(*) FROM noblepc_punch p WHERE p.dr_id = d.id) AS punch_total,
               (SELECT COUNT(*) FROM noblepc_punch p WHERE p.dr_id = d.id AND p.status = 'verified') AS punch_verified,
               (SELECT COUNT(*) FROM noblepc_punch p WHERE p.dr_id = d.id AND p.status <> 'verified' AND p.target_date < CURDATE()) AS punch_overdue,
               tv.status AS to_status, tv.return_remarks AS to_remarks, tv.closed_at, tv.sa_at
        FROM noblepc_laborquote q
        JOIN noblepc_dr d ON d.id = q.dr_id
        LEFT JOIN noblepc_turnover tv ON tv.dr_id = d.id
        WHERE q.status = 'approved'
        ORDER BY (tv.status = 'closed'), d.id DESC
    ");
    foreach ($rows as &$r) {
        $r['dr_id'] = (int) $r['dr_id'];
        foreach (['punch_total', 'punch_verified', 'punch_overdue'] as $k) $r[$k] = (int) $r[$k];
        $r['contract_amount'] = (float) $r['contract_amount'];
        $ready = tReadiness($conn, $r['dr_id'], $r['contract_amount']);
        $r['ready'] = tReadyOk($ready);
        $r['billing_ok'] = $ready[2]['ok'];
    }
    unset($r);
    tOut(['success' => true, 'rows' => $rows, 'today' => date('Y-m-d')]);
}

// ================= GET =================
if ($action === 'get') {
    $drId = intval($_GET['dr_id'] ?? 0);
    $p = tProject($conn, $drId);
    $items = tAll($conn, "SELECT id, area, deficiency, responsible, target_date, status, remarks, created_by, created_at, fixed_by, fixed_at, verified_by, verified_at FROM noblepc_punch WHERE dr_id = ? ORDER BY FIELD(status, 'open', 'fixed', 'verified'), COALESCE(target_date, '9999-12-31'), id", 'i', [$drId]);
    foreach ($items as &$i) $i['id'] = (int) $i['id'];
    unset($i);
    // mungkahing scope mula sa labor quotation (bawat section: "CLOSET (WARDROBE, INNER CABINET…)")
    $suggest = [];
    foreach (tAll($conn, "SELECT section_no, section_name, item FROM noblepc_laborquote_item WHERE quote_id = ? ORDER BY section_no, item_no", 'i', [(int) $p['quote_id']]) as $q) {
        $k = (int) $q['section_no'];
        $suggest[$k]['name'] = $q['section_name'];
        $suggest[$k]['items'][] = mb_strtoupper($q['item']);
    }
    $suggest = array_values(array_map(fn($s) => $s['name'] . (count(array_unique($s['items'])) ? ' (' . implode(', ', array_unique($s['items'])) . ')' : ''), $suggest));
    tOut(['success' => true, 'project' => $p, 'punch' => $items, 'turnover' => tTurnover($conn, $drId), 'readiness' => tReadiness($conn, $drId, $p['contract_amount']),
          'suggest_scope' => $suggest, 'me' => tUserName($conn, $currentUserId), 'today' => date('Y-m-d')]);
}

// ================= PUNCH: save (add / edit) =================
if ($action === 'punch_save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    tProject($conn, $drId);
    $t = tOne($conn, "SELECT status FROM noblepc_turnover WHERE dr_id = ?", 'i', [$drId]);
    if ($t && in_array($t['status'], ['approved', 'closed'], true)) tOut(['success' => false, 'message' => 'The turnover letter is already signed. The punch list is locked.']);
    $id = intval($_POST['id'] ?? 0);
    $area = tStr('area', 150);
    $def = tStr('deficiency', 1000);
    $resp = tStr('responsible', 150);
    $target = tDate($_POST['target_date'] ?? '');
    $remarks = tStr('remarks', 1000);
    if ($area === '') tOut(['success' => false, 'message' => 'Enter the area or location.', 'field' => 'area']);
    if ($def === '') tOut(['success' => false, 'message' => 'Describe the deficiency.', 'field' => 'deficiency']);
    $me = tUserName($conn, $currentUserId);
    if ($id) {
        $n = tExec($conn, "UPDATE noblepc_punch SET area = ?, deficiency = ?, responsible = ?, target_date = ?, remarks = ? WHERE id = ? AND dr_id = ?", 'sssssii', [$area, $def, $resp, $target, $remarks, $id, $drId]);
        if ($n < 0) tOut(['success' => false, 'message' => 'Item not found.']);
    } else {
        tExec($conn, "INSERT INTO noblepc_punch (dr_id, area, deficiency, responsible, target_date, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 'issssss', [$drId, $area, $def, $resp, $target, $remarks, $me]);
    }
    pcRealtime($conn, 'pc_punch', [[ROLE_PC, null]], ['dr_id' => $drId]);
    tOut(['success' => true]);
}

// ================= PUNCH: status (open → fixed → verified, o ibalik sa open) =================
if ($action === 'punch_status') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $to = $_POST['status'] ?? '';
    if (!in_array($to, ['open', 'fixed', 'verified'], true)) tOut(['success' => false, 'message' => 'Invalid status.']);
    $it = tOne($conn, "SELECT p.dr_id, p.status, tv.status AS to_status FROM noblepc_punch p LEFT JOIN noblepc_turnover tv ON tv.dr_id = p.dr_id WHERE p.id = ?", 'i', [$id]);
    if (!$it) tOut(['success' => false, 'message' => 'Item not found.']);
    if (in_array($it['to_status'], ['approved', 'closed'], true)) tOut(['success' => false, 'message' => 'The turnover letter is already signed. The punch list is locked.']);
    if ($to === 'verified' && $it['status'] !== 'fixed') tOut(['success' => false, 'message' => 'Mark the item as fixed first, then verify it.']);
    $me = tUserName($conn, $currentUserId);
    if ($to === 'fixed') tExec($conn, "UPDATE noblepc_punch SET status = 'fixed', fixed_by = ?, fixed_at = NOW(), verified_by = NULL, verified_at = NULL WHERE id = ?", 'si', [$me, $id]);
    elseif ($to === 'verified') tExec($conn, "UPDATE noblepc_punch SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?", 'si', [$me, $id]);
    else tExec($conn, "UPDATE noblepc_punch SET status = 'open', fixed_by = NULL, fixed_at = NULL, verified_by = NULL, verified_at = NULL WHERE id = ?", 'i', [$id]);
    pcRealtime($conn, 'pc_punch', [[ROLE_PC, null]], ['dr_id' => (int) $it['dr_id']]);
    tOut(['success' => true]);
}

if ($action === 'punch_delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tOut(['success' => false, 'message' => 'Invalid request.']);
    $id = intval($_POST['id'] ?? 0);
    $it = tOne($conn, "SELECT p.dr_id, tv.status AS to_status FROM noblepc_punch p LEFT JOIN noblepc_turnover tv ON tv.dr_id = p.dr_id WHERE p.id = ?", 'i', [$id]);
    if (!$it) tOut(['success' => false, 'message' => 'Item not found.']);
    if (in_array($it['to_status'], ['approved', 'closed'], true)) tOut(['success' => false, 'message' => 'The turnover letter is already signed. The punch list is locked.']);
    tExec($conn, "DELETE FROM noblepc_punch WHERE id = ?", 'i', [$id]);
    pcRealtime($conn, 'pc_punch', [[ROLE_PC, null]], ['dr_id' => (int) $it['dr_id']]);
    tOut(['success' => true]);
}

// ================= TURNOVER LETTER: save & submit to Superadmin =================
if ($action === 'turnover_save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    $p = tProject($conn, $drId);
    $ready = tReadiness($conn, $drId, $p['contract_amount']);
    foreach ($ready as $c) if ($c['required'] && !$c['ok']) tOut(['success' => false, 'message' => 'Not ready for turnover: ' . $c['label'] . ' (' . $c['note'] . ').']);

    $date = tDate($_POST['letter_date'] ?? '') ?: date('Y-m-d');
    $project = tStr('project');
    $location = tStr('location');
    $contractor = tStr('contractor') ?: 'Noblehome Construction Company';
    $client = tStr('client_name');
    $ctitle = tStr('client_title', 100) ?: 'Owner';
    $signedBy = tStr('signed_by') ?: 'Mr. Ken Yang';
    $stitle = tStr('signed_title', 100) ?: 'General Manager';
    $docs = tStr('documents', 2000);
    if ($project === '') tOut(['success' => false, 'message' => 'Enter the project.', 'field' => 'project']);
    if ($client === '') tOut(['success' => false, 'message' => 'Enter the client who will sign (Conforme).', 'field' => 'client_name']);
    $scope = [];
    foreach ((array) ($_POST['scope'] ?? []) as $i => $s) {
        $s = mb_substr(trim((string) $s), 0, 500);
        if ($s === '') continue;
        $scope[] = [$s, mb_substr(trim((string) ($_POST['area'][$i] ?? '')), 0, 150), mb_substr(trim((string) ($_POST['sstatus'][$i] ?? '')), 0, 50) ?: 'Accomplished'];
    }
    if (!$scope) tOut(['success' => false, 'message' => 'Add at least one completed work.']);
    $me = tUserName($conn, $currentUserId);

    $conn->begin_transaction();
    try {
        $cur = tOne($conn, "SELECT id, status FROM noblepc_turnover WHERE dr_id = ? FOR UPDATE", 'i', [$drId]);
        if ($cur && in_array($cur['status'], ['approved', 'closed'], true)) throw new RuntimeException('The turnover letter is already signed and can no longer be edited.');
        if ($cur) {
            $tid = (int) $cur['id'];
            tExec($conn, "UPDATE noblepc_turnover SET letter_date = ?, project = ?, location = ?, contractor = ?, client_name = ?, client_title = ?, signed_by = ?, signed_title = ?, documents = ?,
                                 status = 'pending_sa', prepared_by = ?, prepared_by_id = ?, submitted_at = NOW() WHERE id = ?", 'ssssssssssii',
                [$date, $project, $location, $contractor, $client, $ctitle, $signedBy, $stitle, $docs, $me, $currentUserId, $tid]);
            tExec($conn, "DELETE FROM noblepc_turnover_scope WHERE turnover_id = ?", 'i', [$tid]);
        } else {
            $st = $conn->prepare("INSERT INTO noblepc_turnover (dr_id, letter_date, project, location, contractor, client_name, client_title, signed_by, signed_title, documents, status, prepared_by, prepared_by_id, created_at, submitted_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_sa', ?, ?, NOW(), NOW())");
            $st->bind_param('issssssssssi', $drId, $date, $project, $location, $contractor, $client, $ctitle, $signedBy, $stitle, $docs, $me, $currentUserId);
            $st->execute();
            $tid = $st->insert_id;
            $st->close();
        }
        $ins = $conn->prepare("INSERT INTO noblepc_turnover_scope (turnover_id, item_no, scope, area, status) VALUES (?, ?, ?, ?, ?)");
        foreach ($scope as $k => [$s, $a, $stt]) {
            $no = $k + 1;
            $ins->bind_param('iisss', $tid, $no, $s, $a, $stt);
            $ins->execute();
        }
        $ins->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        tOut(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the letter: ' . $e->getMessage()]);
    }
    pcRealtime($conn, 'pc_turnover', [[ROLE_PC, null], [ROLE_SUPERADMIN, null]], ['dr_id' => $drId]);
    tOut(['success' => true]);
}

// ================= COMPLETE TURNOVER (client signed) → Closed =================
if ($action === 'complete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    $p = tProject($conn, $drId);
    $t = tOne($conn, "SELECT id, status, letter_date FROM noblepc_turnover WHERE dr_id = ?", 'i', [$drId]);
    if (!$t || $t['status'] !== 'approved') tOut(['success' => false, 'message' => 'The turnover letter must be signed by the Superadmin first.']);
    foreach (tReadiness($conn, $drId, $p['contract_amount']) as $c) if ($c['required'] && !$c['ok']) tOut(['success' => false, 'message' => 'Not ready: ' . $c['label'] . '.']);
    $acc = tDate($_POST['accepted_date'] ?? '');
    if (!$acc) tOut(['success' => false, 'message' => 'Enter the date the client accepted.', 'field' => 'accepted_date']);
    if ($acc < $t['letter_date']) tOut(['success' => false, 'message' => 'The accepted date cannot be before the letter date.', 'field' => 'accepted_date']);
    if ($acc > date('Y-m-d')) tOut(['success' => false, 'message' => 'The accepted date cannot be in the future.', 'field' => 'accepted_date']);
    if (($_POST['client_signed'] ?? '') !== '1') tOut(['success' => false, 'message' => 'Confirm that the client signed the turnover letter.', 'field' => 'client_signed']);

    $path = null;
    $f = $_FILES['signed_copy'] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > TO_UPLOAD_MAX) tOut(['success' => false, 'message' => 'The signed copy could not be uploaded (max 10 MB).', 'field' => 'signed_copy']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime] ?? null;
        if (!$ext) tOut(['success' => false, 'message' => 'The signed copy must be a JPG, PNG, WEBP or PDF.', 'field' => 'signed_copy']);
        $dir = ROOT_PATH . '/' . TO_UPLOAD_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) tOut(['success' => false, 'message' => 'Could not create the upload folder on the server.']);
        $path = TO_UPLOAD_DIR . '/turnover-dr' . $drId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], ROOT_PATH . '/' . $path)) tOut(['success' => false, 'message' => 'Could not save the signed copy on the server.']);
    }
    $me = tUserName($conn, $currentUserId);
    $n = tExec($conn, "UPDATE noblepc_turnover SET status = 'closed', accepted_date = ?, client_signed = 1, signed_copy = COALESCE(?, signed_copy), closed_by = ?, closed_at = NOW() WHERE id = ? AND status = 'approved'",
        'sssi', [$acc, $path, $me, (int) $t['id']]);
    if ($n !== 1) { if ($path) @unlink(ROOT_PATH . '/' . $path); tOut(['success' => false, 'message' => 'The turnover was changed by someone else. Please reload.']); }
    pcRealtime($conn, 'pc_turnover', [[ROLE_PC, null], [ROLE_SUPERADMIN, null]], ['dr_id' => $drId]);
    tOut(['success' => true]);
}

tOut(['success' => false, 'message' => 'Unknown action.']);