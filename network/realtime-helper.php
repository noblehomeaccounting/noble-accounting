<?php
// network/realtime-helper.php
// Supabase Realtime (Broadcast) bilang "doorbell": pagkatapos mag-save sa MySQL, magpapadala ang PHP
// ng maliit na signal ("may bago, i-refresh"). Ang browser na naka-subscribe ay kukuha ulit ng data
// sa sarili nating PHP endpoint (may session check) — walang data ng request na dumadaan sa Supabase.

require_once ROOT_PATH . '/network/supabase-config.php';

// Pangalan ng channel ng isang user (hal. nh-budget-1a2b3c...) — hindi mahuhulaan dahil may salt
if (!function_exists('nhRealtimeTopic')) {
    function nhRealtimeTopic(int $userId, string $kind = 'budget'): string
    {
        return 'nh-' . $kind . '-' . substr(hash_hmac('sha256', $kind . ':' . $userId, NH_REALTIME_SALT), 0, 20);
    }
}

// Channel ng isang grupo/role (hal. lahat ng Accounting Staff) — iisang channel para sa grupo
if (!function_exists('nhRealtimeRoleTopic')) {
    function nhRealtimeRoleTopic(string $group): string
    {
        $group = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group));
        return 'nh-role-' . $group . '-' . substr(hash_hmac('sha256', 'role:' . $group, NH_REALTIME_SALT), 0, 20);
    }
}

// Magpadala ng signal sa kahit anong channel.
// Hindi pinaghihintay ang user: ipinipila muna, tapos ipinapadala PAGKATAPOS maibalik ang sagot sa browser
// (fastcgi_finish_request / litespeed_finish_request), at sabay-sabay (curl_multi) kapag marami.
// Kapag pumalya ang Supabase, naka-log lang — tuloy pa rin ang mismong save.
if (!function_exists('nhRealtimeSend')) {
    function nhRealtimeSend(string $topic, string $event, array $payload = []): bool
    {
        if ($topic === '' || !function_exists('curl_init') || str_contains(NH_SUPABASE_URL, 'YOUR-PROJECT-REF') || NH_SUPABASE_SECRET_KEY === '') {
            return false;
        }
        $GLOBALS['__nhRealtimeQueue'][$topic . '|' . $event] = [$topic, $event, $payload + ['at' => time()]];
        if (empty($GLOBALS['__nhRealtimeHooked'])) {
            $GLOBALS['__nhRealtimeHooked'] = true;
            register_shutdown_function('nhRealtimeFlush');
        }
        return true;
    }
}

if (!function_exists('nhRealtimeFlush')) {
    function nhRealtimeFlush(): void
    {
        $queue = $GLOBALS['__nhRealtimeQueue'] ?? [];
        $GLOBALS['__nhRealtimeQueue'] = [];
        if (!$queue)
            return;

        // ibalik muna ang sagot sa browser, saka magpadala sa Supabase
        while (ob_get_level() > 0)
            @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($queue as [$topic, $event, $payload]) {
            $ch = curl_init(rtrim(NH_SUPABASE_URL, '/') . '/realtime/v1/api/broadcast/'
                . rawurlencode($topic) . '/events/' . rawurlencode($event));
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . NH_SUPABASE_SECRET_KEY,
                    'Authorization: Bearer ' . NH_SUPABASE_SECRET_KEY,
                    'Content-Type: application/json',
                ],
                // signal lang — id at oras; walang pangalan, halaga o ibang detalye
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = [$ch, $topic, $event];
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running)
                curl_multi_select($mh, 1.0);
        } while ($running && $status === CURLM_OK);

        foreach ($handles as [$ch, $topic, $event]) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code < 200 || $code >= 300) {
                error_log("nhRealtimeSend: HTTP {$code} " . curl_error($ch) . " ({$event} → {$topic})");
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
}

// Signal sa isang user (hal. ang approver na pinadalhan ng request)
if (!function_exists('nhRealtimeNotify')) {
    function nhRealtimeNotify(int $userId, string $event, array $payload = [], string $kind = 'budget'): bool
    {
        return $userId > 0 ? nhRealtimeSend(nhRealtimeTopic($userId, $kind), $event, $payload) : false;
    }
}

// Channel ayon sa ROLE (at puwedeng POSITION) mula sa index-roles.php
//   nhRealtimeGroupTopic(ROLE_ACCOUNTING, POSITION_STAFF) → lahat ng Accounting Staff
//   nhRealtimeGroupTopic(ROLE_CUTTING)                    → buong Cutting department
if (!function_exists('nhRealtimeGroupTopic')) {
    function nhRealtimeGroupTopic(string $role, ?string $position = null): string
    {
        return nhRealtimeRoleTopic($role . ($position !== null && $position !== '' ? ' ' . $position : ''));
    }
}

// Signal sa isang role / role+position (gamit ang constants sa index-roles.php)
if (!function_exists('nhRealtimeNotifyGroup')) {
    function nhRealtimeNotifyGroup(string $role, ?string $position, string $event, array $payload = []): bool
    {
        return nhRealtimeSend(nhRealtimeGroupTopic($role, $position), $event, $payload);
    }
}

// Signal sa isang grupo gamit ang sariling pangalan (hal. 'accounting-staff')
if (!function_exists('nhRealtimeNotifyRole')) {
    function nhRealtimeNotifyRole(string $group, string $event, array $payload = []): bool
    {
        return nhRealtimeSend(nhRealtimeRoleTopic($group), $event, $payload);
    }
}

// ═══════════════════════════════════════════════════════════════
//  CRM: signal para sa isang inquiry
//  Kusang hinahanap ang Sales na may-ari at ang Designer na naka-assign, saka sila pinapadalhan.
//    nhRtInquiry($conn, $inquiryId);                                      // sales + designer
//    nhRtInquiry($conn, $inquiryId, [[ROLE_DESIGNER, POSITION_HEAD]]);    // + lahat ng Designer Head
//    nhRtInquiry($conn, $inquiryId, [], 'crm_reset', [$oldDesignerId]);   // + dagdag na user
//  Hindi kailanman pinipigilan ang mismong save: kapag pumalya, naka-log lang.
// ═══════════════════════════════════════════════════════════════
if (!function_exists('nhRtInquiry')) {
    function nhRtInquiry(mysqli $conn, int $inquiryId, array $groups = [], string $type = 'crm', array $extraUserIds = []): void
    {
        if ($inquiryId <= 0) {
            return;
        }
        try {
            $users = $extraUserIds;
            $st = $conn->prepare("SELECT sales_staff_id, designer_id FROM noblecrminquiry WHERE id = ? LIMIT 1");
            $st->bind_param('i', $inquiryId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                $users[] = (int) ($row['sales_staff_id'] ?? 0);
                $users[] = (int) ($row['designer_id'] ?? 0);
            }

            $payload = ['type' => $type, 'inquiry_id' => $inquiryId];
            foreach (array_unique(array_filter(array_map('intval', $users), fn($u) => $u > 0)) as $uid) {
                nhRealtimeNotify($uid, 'changed', $payload);
            }
            foreach ($groups as $g) {
                if (!empty($g[0])) {
                    nhRealtimeNotifyGroup((string) $g[0], isset($g[1]) ? (string) $g[1] : null, 'changed', $payload);
                }
            }
        } catch (Throwable $e) {
            error_log('nhRtInquiry: ' . $e->getMessage());
        }
    }
}

// Parehong nhRtInquiry, pero galing sa noblecrm_2dquotation.id (Cutting List, E-Woodfile, BOM)
if (!function_exists('nhRtQuotation')) {
    function nhRtQuotation(mysqli $conn, int $quotationId, array $groups = [], string $type = 'crm'): void
    {
        if ($quotationId <= 0) {
            return;
        }
        try {
            $st = $conn->prepare("SELECT inquiry_id FROM noblecrm_2dquotation WHERE id = ? LIMIT 1");
            $st->bind_param('i', $quotationId);
            $st->execute();
            $inq = (int) ($st->get_result()->fetch_assoc()['inquiry_id'] ?? 0);
            $st->close();
            if ($inq > 0) {
                nhRtInquiry($conn, $inq, $groups, $type);
            } else {
                foreach ($groups as $g) {
                    if (!empty($g[0]))
                        nhRealtimeNotifyGroup((string) $g[0], isset($g[1]) ? (string) $g[1] : null, 'changed', ['type' => $type]);
                }
            }
        } catch (Throwable $e) {
            error_log('nhRtQuotation: ' . $e->getMessage());
        }
    }
}