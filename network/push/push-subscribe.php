<?php
// push-subscribe.php — ise-save / buburahin ang subscription ng device na ito
//   Ini-include ng admin/navigation/push-subscribe-admin.php ($pushType = 'admin')
//   at ng user/navigation/push-subscribe-user.php ($pushType = 'user')
//   POST JSON: { action: 'subscribe' | 'unsubscribe', subscription: { endpoint, keys: { p256dh, auth } } }

include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json; charset=utf-8');

function psOut(array $d, int $c = 200): void { http_response_code($c); echo json_encode($d); exit; }

$pushType = $pushType ?? '';
$uid = (int) ($_SESSION['account_id'] ?? 0);   // admin: noblerole.id · user: nobleaccount.id
if (!in_array($pushType, ['user', 'admin'], true)) psOut(['success' => false, 'message' => 'Invalid.'], 400);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') psOut(['success' => false, 'message' => 'Invalid request.'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? 'subscribe';
$sub = $in['subscription'] ?? [];
$endpoint = trim((string) ($sub['endpoint'] ?? ''));
$p256 = trim((string) ($sub['keys']['p256dh'] ?? ''));
$auth = trim((string) ($sub['keys']['auth'] ?? ''));

if ($endpoint === '' || !preg_match('~^https://~', $endpoint) || strlen($endpoint) > 1000) psOut(['success' => false, 'message' => 'Invalid subscription.'], 422);
$hash = hash('sha256', $endpoint);

try {
    if ($action === 'unsubscribe') {
        $st = $conn->prepare("DELETE FROM noblepush_subscription WHERE endpoint_hash = ?");
        $st->bind_param('s', $hash);
        $st->execute();
        $st->close();
        psOut(['success' => true]);
    }

    if ($uid <= 0) psOut(['success' => false, 'message' => 'Please log in first.'], 401);
    if ($p256 === '' || $auth === '') psOut(['success' => false, 'message' => 'Invalid subscription.'], 422);

    // Iisang device = iisang may-ari: kung may ibang nag-login sa device na ito, sa kanya na lilipat
    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $st = $conn->prepare("INSERT INTO noblepush_subscription (account_type, account_id, endpoint, endpoint_hash, p256dh, auth, user_agent, last_seen_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE account_type = VALUES(account_type), account_id = VALUES(account_id),
                              p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), last_seen_at = NOW()");
    $st->bind_param('sisssss', $pushType, $uid, $endpoint, $hash, $p256, $auth, $ua);
    $st->execute();
    $st->close();
    psOut(['success' => true]);
} catch (Throwable $e) {
    error_log('push-subscribe: ' . $e->getMessage());
    psOut(['success' => false, 'message' => 'Could not save.'], 500);
}