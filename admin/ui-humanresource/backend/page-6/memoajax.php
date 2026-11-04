<?php
// memoajax.php (route: memoajax) — backend ng Memo (HR at Superadmin)
//   GET  list · get · employees
//   POST save · submit · verify · approve · return · delete

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json; charset=utf-8');

function mo(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d); exit; }
function mfail(string $msg, int $code = 400): void { mo(['success' => false, 'message' => $msg], $code); }

$meId   = (int) ($_SESSION['account_id'] ?? 0);
$meRole = $_SESSION['role'] ?? '';
$mePos  = $_SESSION['position'] ?? '';
if ($meId <= 0) mfail('Session expired. Please log in again.', 401);

$isHR     = $meRole === ROLE_HR;
$isHRHead = $isHR && $mePos === POSITION_HEAD;
$isSA     = $meRole === ROLE_SUPERADMIN;
if (!$isHR && !$isSA) mfail('Access denied.', 403);

// ── helpers ────────────────────────────────────────────────
function m_name(mysqli $c, int $id): string
{
    $st = $c->prepare("SELECT name FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r['name'] ?? ($_SESSION['username'] ?? 'User');
}
function m_sig(mysqli $c, int $id): ?string
{
    $st = $c->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
    $st->bind_param('i', $id); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r['path'] ?? null;
}
function m_url(?string $p): ?string
{
    if (!$p) return null;
    return preg_match('~^(https?:)?//|^/~', $p) ? $p : BASE_URL . '/' . ltrim($p, '/');
}
function m_get(mysqli $c, int $id): ?array
{
    $st = $c->prepare("SELECT m.*, r.memo_no AS ref_no FROM noblememo m LEFT JOIN noblememo r ON r.id = m.ref_memo_id WHERE m.id = ?");
    $st->bind_param('i', $id); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    return $m ?: null;
}
function m_date($v): ?string { $v = trim((string) $v); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null; }

// Sino ang susunod na pipirma, at ako ba iyon
function m_turn(array $m, bool $isHR, bool $isHRHead, bool $isSA): bool
{
    return match ($m['status']) {
        'draft', 'returned' => $isHR,
        'pending_verify'    => $isHRHead,
        'pending_approval'  => $isSA,
        default             => false,
    };
}

// Ipadala sa employees: gumawa ng row kada nobleaccount
function m_send(mysqli $c, array $m): int
{
    if ($m['audience'] === 'all') {
        $ids = [];
        $res = $c->query("SELECT id FROM nobleaccount");
        while ($r = $res->fetch_assoc()) $ids[] = (int) $r['id'];
    } else {
        $ids = array_values(array_unique(array_map('intval', json_decode($m['recipient_ids'] ?: '[]', true) ?: [])));
    }
    $st = $c->prepare("INSERT IGNORE INTO noblememo_recipient (memo_id, account_id) VALUES (?, ?)");
    foreach ($ids as $aid) { $st->bind_param('ii', $m['id'], $aid); $st->execute(); }
    $st->close();
    $now = date('Y-m-d H:i:s');
    $st = $c->prepare("UPDATE noblememo SET status = 'sent', sent_at = ?, memo_date = COALESCE(memo_date, CURDATE()) WHERE id = ?");
    $st->bind_param('si', $now, $m['id']); $st->execute(); $st->close();
    return count($ids);
}

$action = $_GET['action'] ?? '';
$P = $_POST;

try {
    switch ($action) {

        // ─── LIST ──────────────────────────────────────────
        case 'list':
            // Superadmin: yung mga memo lang na kailangan ng pirma niya
            $where = $isSA ? "WHERE m.need_superadmin = 1 AND (m.status = 'pending_approval' OR m.approved_at IS NOT NULL
                                 OR (m.status = 'returned' AND m.returned_by = $meId))" : '';
            $sql = "SELECT m.id, m.memo_no, m.type, m.audience, m.recipient_ids, m.subject, m.status, m.need_superadmin,
                           m.prepared_name, m.memo_date, m.sent_at, m.response_due, m.updated_at, m.created_at, m.return_remarks,
                           (SELECT COUNT(*) FROM noblememo_recipient x WHERE x.memo_id = m.id) AS rcpt,
                           (SELECT COUNT(*) FROM noblememo_recipient x WHERE x.memo_id = m.id AND x.read_at IS NOT NULL) AS seen,
                           (SELECT COUNT(*) FROM noblememo_recipient x WHERE x.memo_id = m.id AND x.response_at IS NOT NULL) AS answered
                    FROM noblememo m $where ORDER BY m.id DESC LIMIT 500";
            $rows = [];
            $res = $conn->query($sql);
            while ($r = $res->fetch_assoc()) {
                $r['my_turn'] = m_turn($r, $isHR, $isHRHead, $isSA);
                $r['selected_count'] = count(json_decode($r['recipient_ids'] ?: '[]', true) ?: []);
                unset($r['recipient_ids']);
                $rows[] = $r;
            }
            mo(['success' => true, 'rows' => $rows, 'me' => m_name($conn, $meId), 'my_signature' => m_url(m_sig($conn, $meId)),
                'is_hr' => $isHR, 'is_hr_head' => $isHRHead, 'is_sa' => $isSA]);

        // ─── EMPLOYEES (para sa pagpili) ───────────────────
        case 'employees':
            if (!$isHR) mfail('Access denied.', 403);
            $emp = $conn->query("SELECT id, name, email FROM nobleaccount ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            $nte = $conn->query("SELECT id, memo_no, subject FROM noblememo WHERE type = 'nte' AND status = 'sent' ORDER BY id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
            mo(['success' => true, 'employees' => $emp, 'ntes' => $nte]);

        // ─── GET ───────────────────────────────────────────
        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id === 0) {   // bagong memo
                $m = ['id' => 0, 'type' => 'nte', 'audience' => 'selected', 'recipient_ids' => '[]', 'subject' => '', 'body' => '',
                      'memo_date' => date('Y-m-d'), 'need_superadmin' => 0, 'status' => 'draft'];
            } else {
                $m = m_get($conn, $id);
                if (!$m) mfail('Memo not found.', 404);
                if ($isSA && !$isHR && (int) $m['need_superadmin'] !== 1) mfail('Access denied.', 403);
            }
            foreach (['prepared', 'verified', 'approved'] as $k) $m[$k . '_sig_url'] = m_url($m[$k . '_sig'] ?? null);
            $m['recipient_ids'] = json_decode($m['recipient_ids'] ?: '[]', true) ?: [];

            // Pangalan ng mga napiling employee
            $names = [];
            if ($m['recipient_ids']) {
                $in = implode(',', array_map('intval', $m['recipient_ids']));
                $names = $conn->query("SELECT id, name FROM nobleaccount WHERE id IN ($in) ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            }
            $m['to_label'] = $m['audience'] === 'all' ? 'All Employees'
                : (count($names) === 1 ? $names[0]['name'] : (count($names) ? count($names) . ' employees' : ''));

            // Mga nakatanggap + sagot
            $rcpt = [];
            if ($id && $m['status'] === 'sent') {
                $st = $conn->prepare("SELECT x.account_id, a.name, a.email, x.read_at, x.response, x.response_at
                                      FROM noblememo_recipient x LEFT JOIN nobleaccount a ON a.id = x.account_id
                                      WHERE x.memo_id = ? ORDER BY a.name");
                $st->bind_param('i', $id); $st->execute();
                $rcpt = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
            }

            $turn = m_turn($m, $isHR, $isHRHead, $isSA);
            $canEdit = $isHR && in_array($m['status'], ['draft', 'returned'], true);
            $signAs = !$turn || $canEdit ? null : ($m['status'] === 'pending_verify' ? 'verified' : 'approved');
            mo(['success' => true, 'memo' => $m, 'selected' => $names, 'recipients' => $rcpt,
                'can_edit' => $canEdit, 'sign_as' => $signAs,
                'me' => m_name($conn, $meId), 'my_signature' => m_url(m_sig($conn, $meId))]);

        // ─── SAVE (draft) / SUBMIT ─────────────────────────
        case 'save':
        case 'submit':
            if (!$isHR) mfail('Only HR can prepare memos.', 403);
            $id   = (int) ($P['id'] ?? 0);
            $type = in_array($P['type'] ?? '', ['nte', 'nod', 'coe'], true) ? $P['type'] : null;
            $aud  = ($P['audience'] ?? '') === 'all' ? 'all' : 'selected';
            $ids  = array_values(array_unique(array_filter(array_map('intval', json_decode($P['recipient_ids'] ?? '[]', true) ?: []))));
            $subj = trim((string) ($P['subject'] ?? ''));
            $body = trim((string) ($P['body'] ?? ''));
            $mdate = m_date($P['memo_date'] ?? '') ?? date('Y-m-d');
            $due  = $type === 'nte' ? m_date($P['response_due'] ?? '') : null;
            $ref  = $type === 'nod' && (int) ($P['ref_memo_id'] ?? 0) > 0 ? (int) $P['ref_memo_id'] : null;
            $needSa = !empty($P['need_superadmin']) && $P['need_superadmin'] !== '0' ? 1 : 0;

            if (!$type) mfail('Choose the memo type.');
            if ($type === 'coe' && $subj === '') $subj = 'Certificate of Employment';
            if ($action === 'submit') {
                if ($subj === '') mfail('Subject is required.');
                if ($body === '') mfail('The memo body is empty.');
                if ($aud === 'selected' && !$ids) mfail('Pick at least one employee, or send to all.');
                if ($type === 'nte' && !$due) mfail('Set the response deadline for the Notice to Explain.');
            }
            if ($id) {
                $old = m_get($conn, $id);
                if (!$old) mfail('Memo not found.', 404);
                if (!in_array($old['status'], ['draft', 'returned'], true)) mfail('This memo can no longer be edited.', 409);
            }
            $idsJson = json_encode($aud === 'all' ? [] : $ids);

            $conn->begin_transaction();
            if ($id) {
                $st = $conn->prepare("UPDATE noblememo SET type=?, audience=?, recipient_ids=?, subject=?, body=?, memo_date=?, response_due=?, ref_memo_id=?, need_superadmin=? WHERE id=?");
                $st->bind_param('sssssssiii', $type, $aud, $idsJson, $subj, $body, $mdate, $due, $ref, $needSa, $id);
            } else {
                $st = $conn->prepare("INSERT INTO noblememo (type, audience, recipient_ids, subject, body, memo_date, response_due, ref_memo_id, need_superadmin, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('sssssssiii', $type, $aud, $idsJson, $subj, $body, $mdate, $due, $ref, $needSa, $meId);
            }
            $st->execute();
            if (!$id) $id = $conn->insert_id;
            $st->close();

            if ($action === 'save') { $conn->commit(); mo(['success' => true, 'id' => $id, 'message' => 'Draft saved.']); }

            // Memo number (kapag wala pa)
            $m = m_get($conn, $id);
            if (!$m['memo_no']) {
                $prefix = strtoupper($type) . '-' . date('Y') . '-';
                $like = $prefix . '%';
                $st = $conn->prepare("SELECT memo_no FROM noblememo WHERE memo_no LIKE ? ORDER BY memo_no DESC LIMIT 1 FOR UPDATE");
                $st->bind_param('s', $like); $st->execute();
                $last = $st->get_result()->fetch_assoc(); $st->close();
                $no = $prefix . str_pad((string) ($last ? (int) substr($last['memo_no'], strlen($prefix)) + 1 : 1), 3, '0', STR_PAD_LEFT);
                $st = $conn->prepare("UPDATE noblememo SET memo_no = ? WHERE id = ?");
                $st->bind_param('si', $no, $id); $st->execute(); $st->close();
            }

            // Prepared by (ako) — kung HR Head ang gumawa, verified na rin agad
            $name = m_name($conn, $meId); $sig = m_sig($conn, $meId); $now = date('Y-m-d H:i:s');
            $next = $isHRHead ? ($needSa ? 'pending_approval' : 'sent') : 'pending_verify';
            if ($isHRHead) {
                $st = $conn->prepare("UPDATE noblememo SET status=?, prepared_by=?, prepared_name=?, prepared_sig=?, prepared_at=?,
                                      verified_by=?, verified_name=?, verified_sig=?, verified_at=?,
                                      returned_by=NULL, returned_name=NULL, returned_at=NULL WHERE id=?");
                $st->bind_param('sisssisssi', $next, $meId, $name, $sig, $now, $meId, $name, $sig, $now, $id);
            } else {
                $st = $conn->prepare("UPDATE noblememo SET status=?, prepared_by=?, prepared_name=?, prepared_sig=?, prepared_at=?,
                                      verified_by=NULL, verified_name=NULL, verified_sig=NULL, verified_at=NULL,
                                      returned_by=NULL, returned_name=NULL, returned_at=NULL WHERE id=?");
                $st->bind_param('sisssi', $next, $meId, $name, $sig, $now, $id);
            }
            $st->execute(); $st->close();

            $sent = $next === 'sent' ? m_send($conn, m_get($conn, $id)) : 0;
            $conn->commit();
            mo(['success' => true, 'id' => $id, 'message' => $next === 'sent' ? "Memo sent to $sent employee(s)."
                : ($next === 'pending_approval' ? 'Submitted. Waiting for the Superadmin.' : 'Submitted. Waiting for the HR Head.')]);

        // ─── VERIFY (HR Head) / APPROVE (Superadmin) ───────
        case 'verify':
        case 'approve':
            $id = (int) ($P['id'] ?? 0);
            $conn->begin_transaction();
            $st = $conn->prepare("SELECT * FROM noblememo WHERE id = ? FOR UPDATE");
            $st->bind_param('i', $id); $st->execute();
            $m = $st->get_result()->fetch_assoc(); $st->close();
            if (!$m) { $conn->rollback(); mfail('Memo not found.', 404); }

            $col = $action === 'verify' ? 'verified' : 'approved';
            $okRole = $action === 'verify' ? $isHRHead : $isSA;
            $okStat = $action === 'verify' ? 'pending_verify' : 'pending_approval';
            if (!$okRole) { $conn->rollback(); mfail('This step is not for your role.', 403); }
            if ($m['status'] !== $okStat) { $conn->rollback(); mfail('This memo was already updated. Reload.', 409); }

            $next = $action === 'verify' && (int) $m['need_superadmin'] === 1 ? 'pending_approval' : 'sent';
            $name = m_name($conn, $meId); $sig = m_sig($conn, $meId); $now = date('Y-m-d H:i:s');
            $st = $conn->prepare("UPDATE noblememo SET status=?, {$col}_by=?, {$col}_name=?, {$col}_sig=?, {$col}_at=? WHERE id=?");
            $st->bind_param('sisssi', $next, $meId, $name, $sig, $now, $id);
            $st->execute(); $st->close();

            $sent = $next === 'sent' ? m_send($conn, m_get($conn, $id)) : 0;
            $conn->commit();
            mo(['success' => true, 'message' => $next === 'sent' ? "Signed and sent to $sent employee(s)." : 'Verified. Sent to the Superadmin for approval.']);

        // ─── RETURN (ibalik sa HR) ─────────────────────────
        case 'return':
            $id = (int) ($P['id'] ?? 0);
            $rem = trim((string) ($P['remarks'] ?? ''));
            if (mb_strlen($rem) < 3) mfail('Tell HR what to fix.');
            $m = m_get($conn, $id);
            if (!$m) mfail('Memo not found.', 404);
            $ok = ($m['status'] === 'pending_verify' && $isHRHead) || ($m['status'] === 'pending_approval' && $isSA);
            if (!$ok) mfail('You cannot return this memo now.', 403);
            $name = m_name($conn, $meId); $now = date('Y-m-d H:i:s'); $stat = $m['status'];
            $st = $conn->prepare("UPDATE noblememo SET status='returned', returned_by=?, returned_name=?, returned_at=?, return_remarks=? WHERE id=? AND status=?");
            $st->bind_param('isssis', $meId, $name, $now, $rem, $id, $stat);
            $st->execute(); $st->close();
            mo(['success' => true, 'message' => 'Returned to HR.']);

        // ─── DELETE (draft lang) ───────────────────────────
        case 'delete':
            if (!$isHR) mfail('Access denied.', 403);
            $id = (int) ($P['id'] ?? 0);
            $st = $conn->prepare("DELETE FROM noblememo WHERE id = ? AND status = 'draft'");
            $st->bind_param('i', $id); $st->execute();
            $n = $st->affected_rows; $st->close();
            if ($n < 1) mfail('Only drafts can be deleted.', 409);
            mo(['success' => true, 'message' => 'Draft deleted.']);

        default:
            mfail('Unknown action.');
    }
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    mfail('Server error: ' . $e->getMessage(), 500);
}