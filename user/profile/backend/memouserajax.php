<?php
// memouserajax.php (route: memouserajax) — user side (nobleaccount)
//   GET list · get   ·   POST respond (sagot sa Notice to Explain)

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json; charset=utf-8');
function uo(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d); exit; }
function ufail(string $m, int $code = 400): void { uo(['success' => false, 'message' => $m], $code); }

// ID ng naka-login na employee (nobleaccount.id) — kapareho ng top.php
$uid = (int) ($_SESSION['account_id'] ?? 0);
if ($uid <= 0) ufail('Session expired. Please log in again.', 401);

function u_url(?string $p): ?string
{
    if (!$p) return null;
    return preg_match('~^(https?:)?//|^/~', $p) ? $p : BASE_URL . '/' . ltrim($p, '/');
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'list':
            $st = $conn->prepare("SELECT m.id, m.memo_no, m.type, m.subject, m.memo_date, m.sent_at, m.response_due,
                                         x.read_at, x.response_at
                                  FROM noblememo_recipient x JOIN noblememo m ON m.id = x.memo_id
                                  WHERE x.account_id = ? AND m.status = 'sent'
                                  ORDER BY m.sent_at DESC, m.id DESC");
            $st->bind_param('i', $uid); $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
            uo(['success' => true, 'rows' => $rows]);

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            $st = $conn->prepare("SELECT m.*, r.memo_no AS ref_no, x.read_at, x.response, x.response_at, a.name AS my_name
                                  FROM noblememo_recipient x
                                  JOIN noblememo m ON m.id = x.memo_id
                                  LEFT JOIN noblememo r ON r.id = m.ref_memo_id
                                  LEFT JOIN nobleaccount a ON a.id = x.account_id
                                  WHERE x.memo_id = ? AND x.account_id = ? AND m.status = 'sent'");
            $st->bind_param('ii', $id, $uid); $st->execute();
            $m = $st->get_result()->fetch_assoc(); $st->close();
            if (!$m) ufail('Memo not found.', 404);

            if (!$m['read_at']) {   // markahan na nabasa
                $now = date('Y-m-d H:i:s');
                $st = $conn->prepare("UPDATE noblememo_recipient SET read_at = ? WHERE memo_id = ? AND account_id = ? AND read_at IS NULL");
                $st->bind_param('sii', $now, $id, $uid); $st->execute(); $st->close();
                $m['read_at'] = $now;
            }
            foreach (['prepared', 'verified', 'approved'] as $k) $m[$k . '_sig_url'] = u_url($m[$k . '_sig'] ?? null);
            // Huwag ipakita sa employee ang listahan ng iba pang nakatanggap
            unset($m['recipient_ids'], $m['return_remarks'], $m['returned_name'], $m['returned_by']);
            $m['to_label'] = $m['my_name'];
            uo(['success' => true, 'memo' => $m]);

        case 'respond':
            $id  = (int) ($_POST['id'] ?? 0);
            $txt = trim((string) ($_POST['response'] ?? ''));
            if (mb_strlen($txt) < 10) ufail('Please write your explanation (at least a few sentences).');
            if (mb_strlen($txt) > 5000) ufail('Your explanation is too long (max 5,000 characters).');

            $st = $conn->prepare("SELECT m.type, x.response_at FROM noblememo_recipient x JOIN noblememo m ON m.id = x.memo_id
                                  WHERE x.memo_id = ? AND x.account_id = ? AND m.status = 'sent'");
            $st->bind_param('ii', $id, $uid); $st->execute();
            $r = $st->get_result()->fetch_assoc(); $st->close();
            if (!$r) ufail('Memo not found.', 404);
            if ($r['type'] !== 'nte') ufail('Only a Notice to Explain needs a response.');
            if ($r['response_at']) ufail('You already sent your response.', 409);

            $now = date('Y-m-d H:i:s');
            $st = $conn->prepare("UPDATE noblememo_recipient SET response = ?, response_at = ? WHERE memo_id = ? AND account_id = ? AND response_at IS NULL");
            $st->bind_param('ssii', $txt, $now, $id, $uid); $st->execute(); $st->close();
            uo(['success' => true, 'message' => 'Your response was sent to HR.']);

        default:
            ufail('Unknown action.');
    }
} catch (Throwable $e) {
    ufail('Server error: ' . $e->getMessage(), 500);
}