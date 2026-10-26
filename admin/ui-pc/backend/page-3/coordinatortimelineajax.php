<?php
// coordinatortimelineajax.php  —  Project Coordinator: Timeline (pagkatapos ng approved na Labor Quotation)

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

const TL_MAX_DAYS = 730;     // pinakamahabang timeline (2 taon)
const TL_MAX_TASKS = 300;

function tlOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[coordinatortimelineajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function tlAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function tlOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    return tlAll($conn, $sql, $types, $args)[0] ?? null;
}
function tlStr(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}
function tlDate($d): ?string
{
    $d = trim((string) $d);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
    return $d;
}
function tlDays(string $a, string $b): int
{
    return (int) (new DateTime($a))->diff(new DateTime($b))->format('%r%a');
}
function tlUserName(mysqli $conn, int $uid): string
{
    return tlOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid])['name'] ?? '';
}
// Bantay: dapat approved na ang labor quotation ng DR
function tlEligible(mysqli $conn, int $drId): array
{
    $r = tlOne($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name, d.address, t.ref_no, t.project_name,
               q.id AS quote_id, q.quote_no, q.status AS quote_status, q.project_name AS quote_project, q.location AS quote_location,
               q.subject, q.quote_date, q.duration_days, q.subcontractor
        FROM noblepc_dr d
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id
        WHERE d.id = ? LIMIT 1", 'i', [$drId]);
    if (!$r) tlOut(['success' => false, 'message' => 'DR not found.']);
    if (($r['quote_status'] ?? '') !== 'approved') tlOut(['success' => false, 'message' => 'The labor quotation must be approved by the Superadmin first.']);
    return $r;
}

// ================= LIST: DR na may approved labor quote + estado ng timeline =================
if ($action === 'list') {
    $rows = tlAll($conn, "
        SELECT d.id AS dr_id, d.dr_no, d.client_name, t.ref_no, t.project_name,
               q.quote_no, q.duration_days AS quote_days, q.approved_at AS quote_approved_at, f.name AS foreman_name,
               tl.id AS timeline_id, tl.date_started, tl.date_finish, tl.updated_at, tl.updated_by,
               (SELECT COUNT(*) FROM noblepc_timeline_task x WHERE x.timeline_id = tl.id) AS task_count
        FROM noblepc_laborquote q
        JOIN noblepc_dr d ON d.id = q.dr_id
        JOIN nobletransmittal t ON t.id = d.transmittal_id
        LEFT JOIN noblepc_foreman f ON f.dr_id = d.id
        LEFT JOIN noblepc_timeline tl ON tl.dr_id = d.id
        WHERE q.status = 'approved'
        ORDER BY (tl.id IS NOT NULL), q.approved_at DESC, d.id DESC
    ");
    foreach ($rows as &$r) {
        $r['dr_id'] = (int) $r['dr_id'];
        $r['task_count'] = (int) $r['task_count'];
        $r['timeline_id'] = $r['timeline_id'] !== null ? (int) $r['timeline_id'] : null;
    }
    unset($r);
    tlOut(['success' => true, 'rows' => $rows, 'today' => date('Y-m-d')]);
}

// ================= GET: detalye para sa editor =================
if ($action === 'get') {
    $drId = intval($_GET['dr_id'] ?? 0);
    $info = tlEligible($conn, $drId);
    $tl = tlOne($conn, "SELECT * FROM noblepc_timeline WHERE dr_id = ? LIMIT 1", 'i', [$drId]);
    if ($tl) {
        $tl['tasks'] = tlAll($conn, "SELECT phase_no, phase_name, task_no, task, start_date, end_date, duration FROM noblepc_timeline_task WHERE timeline_id = ? ORDER BY phase_no, task_no", 'i', [(int) $tl['id']]);
    }
    tlOut(['success' => true, 'info' => $info, 'timeline' => $tl, 'today' => date('Y-m-d')]);
}

// ================= SAVE =================
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') tlOut(['success' => false, 'message' => 'Invalid request.']);
    $drId = intval($_POST['dr_id'] ?? 0);
    $info = tlEligible($conn, $drId);

    $client = tlStr('client_name') ?: ($info['quote_project'] ?: $info['client_name']);
    $site   = tlStr('site_location');
    $scope  = tlStr('project_scope');
    if ($client === '') tlOut(['success' => false, 'message' => 'Client name is required.', 'field' => 'client_name']);

    // tasks: may phase bawat row; i-group ayon sa pagkakasunod
    $rows = [];
    $phaseOrder = [];
    foreach ((array) ($_POST['task'] ?? []) as $i => $task) {
        $task = mb_substr(trim((string) $task), 0, 255);
        if ($task === '') continue;
        $phase = mb_substr(trim((string) ($_POST['phase'][$i] ?? '')), 0, 150);
        if ($phase === '') tlOut(['success' => false, 'message' => "Give a phase name for \"$task\" (e.g. PLAN)."]);
        $s = tlDate($_POST['start'][$i] ?? '');
        $e = tlDate($_POST['end'][$i] ?? '');
        if (!$s) tlOut(['success' => false, 'message' => "Enter the start date of \"$task\"."]);
        if (!$e) tlOut(['success' => false, 'message' => "Enter the end date of \"$task\"."]);
        if ($e < $s) tlOut(['success' => false, 'message' => "The end date of \"$task\" cannot be before its start date."]);
        $dur = tlDays($s, $e) + 1;
        if ($dur > TL_MAX_DAYS) tlOut(['success' => false, 'message' => "\"$task\" is too long (max " . TL_MAX_DAYS . ' days).']);
        $key = mb_strtoupper($phase);
        if (!isset($phaseOrder[$key])) $phaseOrder[$key] = count($phaseOrder) + 1;
        $rows[] = [$phaseOrder[$key], $key, mb_strtoupper($task), $s, $e, $dur];
    }
    if (!$rows) tlOut(['success' => false, 'message' => 'Add at least one task with dates.']);
    if (count($rows) > TL_MAX_TASKS) tlOut(['success' => false, 'message' => 'Too many tasks (max ' . TL_MAX_TASKS . ').']);
    usort($rows, fn($a, $b) => $a[0] <=> $b[0]);
    $start = min(array_column($rows, 3));
    $finish = max(array_column($rows, 4));
    if (tlDays($start, $finish) + 1 > TL_MAX_DAYS) tlOut(['success' => false, 'message' => 'The whole timeline is too long (max ' . TL_MAX_DAYS . ' days).']);
    $me = tlUserName($conn, $currentUserId);

    $conn->begin_transaction();
    try {
        $cur = tlOne($conn, "SELECT id FROM noblepc_timeline WHERE dr_id = ? FOR UPDATE", 'i', [$drId]);
        if ($cur) {
            $tid = (int) $cur['id'];
            $st = $conn->prepare("UPDATE noblepc_timeline SET client_name = ?, site_location = ?, project_scope = ?, date_started = ?, date_finish = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $st->bind_param('ssssssi', $client, $site, $scope, $start, $finish, $me, $tid);
            $st->execute();
            $st->close();
            $del = $conn->prepare("DELETE FROM noblepc_timeline_task WHERE timeline_id = ?");
            $del->bind_param('i', $tid);
            $del->execute();
            $del->close();
        } else {
            $st = $conn->prepare("INSERT INTO noblepc_timeline (dr_id, client_name, site_location, project_scope, date_started, date_finish, created_by, created_at, updated_by, updated_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())");
            $st->bind_param('isssssis', $drId, $client, $site, $scope, $start, $finish, $currentUserId, $me);
            $st->execute();
            $tid = $st->insert_id;
            $st->close();
        }
        $it = $conn->prepare("INSERT INTO noblepc_timeline_task (timeline_id, phase_no, phase_name, task_no, task, start_date, end_date, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $n = [];
        foreach ($rows as [$pNo, $pName, $task, $s, $e, $dur]) {
            $n[$pNo] = ($n[$pNo] ?? 0) + 1;
            $tNo = $n[$pNo];
            $it->bind_param('iisisssi', $tid, $pNo, $pName, $tNo, $task, $s, $e, $dur);
            $it->execute();
        }
        $it->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[coordinatortimelineajax save] ' . $e->getMessage());
        tlOut(['success' => false, 'message' => 'Could not save the timeline: ' . $e->getMessage()]);
    }
    tlOut(['success' => true, 'date_started' => $start, 'date_finish' => $finish, 'tasks' => count($rows)]);
}

tlOut(['success' => false, 'message' => 'Unknown action.']);