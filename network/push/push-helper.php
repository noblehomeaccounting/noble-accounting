<?php
// push-helper.php — magpadala ng push notification sa phone / browser
//
//   Sa user (nobleaccount):   nhPush($conn, 'user',  [12, 15], 'New memo from HR', 'Notice to Explain', '/memouser?memo=7');
//   Sa admin (noblerole):     nhPush($conn, 'admin', 21, 'Sales PO waiting', 'NHPO-2026-004 needs your signature', '/salespoapproval?po=4');
//   Sa buong department:      nhPushRole($conn, ROLE_SUPERADMIN, null, 'Memo for approval', 'NTE-2026-003', '/memoapproval');
//   Department + position:    nhPushRole($conn, ROLE_ACCOUNTING, POSITION_HEAD, ...);
//
// Hindi kailanman nag-e-error ang tawag — kapag may mali, naka-log lang (para hindi mapigilan ang save).
// Ang $url ay relative sa BASE_URL (hal. '/memouser').

include_once ROOT_PATH . '/network/push/push-config.php';

if (!function_exists('nhPush')) {
    function nhPush(mysqli $conn, string $type, $ids, string $title, string $body = '', string $url = '', string $tag = ''): int
    {
        try {
            if (!in_array($type, ['user', 'admin'], true) || !nhPushEnabled()) return 0;
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
            if (!$ids) return 0;
            require_once ROOT_PATH . '/vendor/autoload.php';

            $in = implode(',', $ids);
            $st = $conn->prepare("SELECT id, endpoint, p256dh, auth FROM noblepush_subscription
                                  WHERE account_type = ? AND account_id IN ($in)");
            $st->bind_param('s', $type);
            $st->execute();
            $subs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            if (!$subs) return 0;

            $push = new Minishlink\WebPush\WebPush([
                'VAPID' => ['subject' => NH_PUSH_SUBJECT, 'publicKey' => NH_PUSH_PUBLIC_KEY, 'privateKey' => NH_PUSH_PRIVATE_KEY],
            ], ['TTL' => 86400, 'urgency' => 'high']);
            $push->setReuseVAPIDHeaders(true);

            $payload = json_encode([
                'title' => mb_substr($title, 0, 80),
                'body'  => mb_substr($body, 0, 180),
                'url'   => BASE_URL . '/' . ltrim($url, '/'),
                'tag'   => $tag,
                'icon'  => BASE_URL . '/icon/icon.png',
                'badge' => BASE_URL . '/icon/icon.png',
            ]);

            $byEndpoint = [];
            foreach ($subs as $s) {
                $byEndpoint[$s['endpoint']] = (int) $s['id'];
                $push->queueNotification(
                    Minishlink\WebPush\Subscription::create(['endpoint' => $s['endpoint'], 'keys' => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']]]),
                    $payload
                );
            }

            $sent = 0; $dead = []; $ok = [];
            foreach ($push->flush() as $report) {
                $id = $byEndpoint[$report->getEndpoint()] ?? 0;
                if ($report->isSuccess()) { $sent++; $ok[] = $id; }
                elseif ($report->isSubscriptionExpired()) $dead[] = $id;   // na-uninstall / pinatay ang notification
                else error_log('nhPush: ' . $report->getReason());
            }
            if ($dead) $conn->query("DELETE FROM noblepush_subscription WHERE id IN (" . implode(',', array_map('intval', $dead)) . ")");
            if ($ok)   $conn->query("UPDATE noblepush_subscription SET last_push_at = NOW() WHERE id IN (" . implode(',', array_map('intval', $ok)) . ")");
            return $sent;
        } catch (Throwable $e) {
            error_log('nhPush: ' . $e->getMessage());
            return 0;
        }
    }

    // Lahat ng admin sa isang department (at position kung may binigay)
    function nhPushRole(mysqli $conn, string $role, ?string $position, string $title, string $body = '', string $url = '', string $tag = ''): int
    {
        try {
            if ($position === null) {
                $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ?");
                $st->bind_param('s', $role);
            } else {
                $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
                $st->bind_param('ss', $role, $position);
            }
            $st->execute();
            $ids = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
            $st->close();
            return nhPush($conn, 'admin', $ids, $title, $body, $url, $tag);
        } catch (Throwable $e) {
            error_log('nhPushRole: ' . $e->getMessage());
            return 0;
        }
    }
}