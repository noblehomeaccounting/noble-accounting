<?php
/**
 * Single source ng lahat ng badge counts sa sidebar.
 * Ginagamit ng sidebar.php (initial render) at ng /sidebarcounts endpoint (polling).
 */

if (!function_exists('sidebarScalar')) {
    function sidebarScalar(mysqli $conn, string $sql, string $types = '', array $params = []): int
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $n = 0;
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }
}

if (!function_exists('getSidebarCounts')) {
    function getSidebarCounts(mysqli $conn, string $role, string $position, int $user_id): array
    {
        $isHead = $position === POSITION_HEAD;
        $isStaff = $position === POSITION_STAFF;
        $isCustodian = $position === POSITION_CUSTODIAN;
        $isCustoAssist = $position === POSITION_CUSTOASSISTANT;

        $c = [
            'crmPending' => 0,
            'toReceive' => 0,
            'requestPending' => 0,
            'toPrepare' => 0,
            'unpreparedVoucher' => 0,
            'toRelease' => 0,
            'followupWaiting' => 0,
            'ewoodTodo' => 0,
        ];

        // Pending na inquiry ng sales user (CRM List)
        if ($role === ROLE_SALES) {
            $c['crmPending'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblecrminquiry WHERE sales_staff_id = ? AND status = 'Pending'",
                'i',
                [$user_id]
            );
        }

        // Approved na request na hindi pa nare-receive (Acknowledge Request)
        if ($role === ROLE_ACCOUNTING && $isStaff) {
            $c['toReceive'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblebudgetrequest
                 WHERE status = 'approved' AND (received_by IS NULL OR received_by = 0)"
            );
        }

        // Pending na budget request na naghihintay ng approval (Requests List)
        if ($role === ROLE_ACCOUNTING && $isHead) {
            $c['requestPending'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblebudgetrequest WHERE sent_to = ? AND status = 'pending'",
                'i',
                [$user_id]
            );
        }

        // Na-receive na request na wala pang cash voucher (Cash Voucher Request)
        if ($role === ROLE_ACCOUNTING && ($isStaff || $isCustodian || $isCustoAssist)) {
            $c['toPrepare'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblebudgetrequest b
                 WHERE b.received_by IS NOT NULL
                   AND NOT EXISTS (SELECT 1 FROM noblevoucher v WHERE v.request_id = b.id)"
            );
        }

        // Voucher na naka-submit pero wala pang nag-prepare (Approval Cash Voucher)
        if ($role === ROLE_ACCOUNTING && ($isStaff || $isCustodian || $isHead)) {
            $sql = $isHead
                ? "SELECT COUNT(*) FROM noblevoucher
           WHERE status = 'voucher_approval'
             AND prepared_by IS NOT NULL AND prepared_by <> 0
             AND (approved_by IS NULL OR approved_by = 0)"
                : "SELECT COUNT(*) FROM noblevoucher
           WHERE status = 'voucher_approval' AND (prepared_by IS NULL OR prepared_by = 0)";

            $c['unpreparedVoucher'] = sidebarScalar($conn, $sql);
        }
        // Voucher na ready_to_release (Custodian Assistant)
        if ($role === ROLE_ACCOUNTING && $isCustoAssist) {
            $c['toRelease'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblevoucher WHERE status = 'ready_to_release'"
            );
        }

        // Request na naghihintay ng follow-up attachment (Follow-ups)
        if ($role === ROLE_ACCOUNTING && $isHead) {
            $c['followupWaiting'] = sidebarScalar(
                $conn,
                "SELECT COUNT(*) FROM noblebudgetrequest WHERE attachment_status = 'follow_up'"
            );
        }

        // E-Woodfile (Cutting): na-verify na ang 2D sa Cutting List at may NTP, at may gagawin pa ang Cutting —
        // wala pang upload, naibalik (rejected), o approved pero hindi pa Completed.
        // Hindi kasama ang naghihintay sa Superadmin at ang Completed na.
        if ($role === ROLE_CUTTING) {
            try {
                $c['ewoodTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*)
                     FROM noblecrm_2dquotation q
                     JOIN (SELECT inquiry_id, MAX(id) AS max_id FROM noblecrm_2dquotation GROUP BY inquiry_id) latest
                         ON latest.max_id = q.id
                     WHERE q.status = 'Approved'
                       AND q.deposit_status = 'Notice to Proceed'
                       AND COALESCE(q.design_2d_verified_by_cutting, 0) <> 0
                       AND NOT EXISTS (
                           SELECT 1 FROM noblecrm_cuttinglistprogression p
                           WHERE p.id = (SELECT MAX(p2.id) FROM noblecrm_cuttinglistprogression p2 WHERE p2.quotation_id = q.id)
                             AND (
                                 (COALESCE(p.qr_approved, 0) = 0 AND COALESCE(p.qr_rejected, 0) = 0)
                                 OR (COALESCE(p.qr_approved, 0) = 1 AND p.status = 'Completed')
                             )
                       )"
                );
            } catch (Throwable $e) {
                error_log('sidebar ewoodTodo: ' . $e->getMessage());
            }
        }

        return $c;
    }
}

if (!function_exists('sidebarBadge')) {
    /**
     * Laging nire-render ang span (nakatago kapag 0) para may mapag-update ang JS.
     */
    function sidebarBadge(string $key, int $count): string
    {
        $text = $count > 99 ? '99+' : (string) $count;
        $state = $count > 0 ? 'flex' : 'hidden';
        return '<span data-badge="' . htmlspecialchars($key) . '" '
            . 'class="sidebar-label flex-shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 '
            . 'text-white text-[10px] font-bold items-center justify-center ' . $state . '">'
            . $text . '</span>';
    }
}