<?php
// push-dispatch-lib.php — ipinapadala sa phone ang mga BAGONG notification ng lahat ng module
//   noblenotification      (admin / noblerole.id)    → push sa 'admin'
//   nobleusernotification  (user  / nobleaccount.id) → push sa 'user'
//
// Tinatawag ng:
//   • network/realtime-helper.php  (kusang tumatakbo tuwing may Supabase "doorbell" — instant)
//   • network/push/push-dispatch.php (Cron, pang-salo kung may notification na walang doorbell)
// Hindi magdodoble: bawat row ay "kinukuha" muna (pushed_at) bago ipadala.

include_once ROOT_PATH . '/network/push/push-helper.php';

if (!function_exists('nhPushDispatch')) {
    function nhPushDispatch(mysqli $conn, int $max = 50): int
    {
        if (!nhPushEnabled()) return 0;
        $total = 0;
        foreach (['noblenotification' => 'admin', 'nobleusernotification' => 'user'] as $t => $type) {
            try {
                // bago lang (huling 1 araw) — hindi dadagsa ang luma
                $res = $conn->query("SELECT * FROM `$t`
                                     WHERE pushed_at IS NULL AND created_at >= NOW() - INTERVAL 1 DAY
                                     ORDER BY id ASC LIMIT " . (int) $max);
                if (!$res) { error_log("nhPushDispatch: $t " . $conn->error); continue; }

                foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
                    $id = (int) $r['id'];
                    // kunin muna ang row — kapag may naunang ibang request, laktawan
                    $conn->query("UPDATE `$t` SET pushed_at = NOW() WHERE id = $id AND pushed_at IS NULL");
                    if ($conn->affected_rows !== 1) continue;

                    $uid = (int) ($r['user_id'] ?? 0);
                    if ($uid <= 0) continue;

                    $msg   = trim(strip_tags((string) ($r['message'] ?? '')));
                    $ctrl  = trim((string) ($r['control_no'] ?? ''));
                    $title = $ctrl !== '' ? $ctrl : 'NobleHome';
                    if (preg_match('/\breject|declin|disapprov/i', $msg))  $title .= ' · Rejected';
                    elseif (preg_match('/\bapprov/i', $msg))               $title .= ' · Approved';

                    // link → relative sa BASE_URL ("budgetrequest?id=5", "/x", o buong URL ng site)
                    $link = trim((string) ($r['link'] ?? ''));
                    if ($link !== '' && stripos($link, BASE_URL) === 0) $link = substr($link, strlen(BASE_URL));
                    if (preg_match('~^https?://~i', $link)) $link = '';

                    $total += nhPush($conn, $type, $uid, $title,
                                     $msg !== '' ? $msg : 'You have a new notification.', $link, "$t-$id");
                }
            } catch (Throwable $e) {
                // hal. wala pang pushed_at column → patakbuhin ang push-notif.sql
                error_log("nhPushDispatch: $t " . $e->getMessage());
            }
        }
        return $total;
    }
}