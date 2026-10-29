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
            'ewoodPending' => 0,
            'designerReview' => 0,
            'finalReview' => 0,
            'depositWaiting' => 0,
            'poToReview' => 0,
            'billingPending' => 0,
            'poTodo' => 0,
            'designerTodo' => 0,
            'cuttingTodo' => 0,
            'prApproval' => 0,
            'prToReceive' => 0,
            'prToRelease' => 0,
            'pcDrTodo' => 0,
            'pcLaborTodo' => 0,
            'pcTimelineTodo' => 0,
            'pcBillingTodo' => 0,
            'pcTurnoverTodo' => 0,
            'saDrPending' => 0,
            'saLaborPending' => 0,
            'saBillingPending' => 0,
            'saTurnoverPending' => 0,
            'poForDelivery' => 0,
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

        // Cutting List (Cutting): may NTP na pero hindi pa na-verify ng Cutting ang 2D file
        // (bago ito, nakatago pa ang files sa Cutting List kaya walang gagawin). Pinakabagong 2D bawat inquiry.
        if ($role === ROLE_CUTTING) {
            try {
                $c['cuttingTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*)
                     FROM noblecrm_2dquotation q
                     JOIN (SELECT inquiry_id, MAX(id) AS max_id FROM noblecrm_2dquotation GROUP BY inquiry_id) latest
                         ON latest.max_id = q.id
                     JOIN noblecrminquiry i ON i.id = q.inquiry_id
                     WHERE q.status = 'Approved'
                       AND q.deposit_status = 'Notice to Proceed'
                       AND COALESCE(q.design_2d_verified_by_cutting, 0) = 0
                       AND COALESCE(q.design_2d_path, '') <> ''"
                );
            } catch (Throwable $e) {
                error_log('sidebar cuttingTodo: ' . $e->getMessage());
            }
        }

        // E-Wood Management (Superadmin): mga upload ng Cutting na naghihintay ng QR approval
        // (parehong kondisyon ng "To review" sa ewood.php)
        if ($role === ROLE_SUPERADMIN) {
            try {
                $c['ewoodPending'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblecrm_cuttinglistprogression
                     WHERE COALESCE(qr_approved, 0) = 0
                       AND COALESCE(qr_rejected, 0) = 0
                       AND photos IS NOT NULL AND photos <> ''"
                );
            } catch (Throwable $e) {
                error_log('sidebar ewoodPending: ' . $e->getMessage());
            }
        }

        // Check Designer Quotation (Designer Head): Initial 2D & Quotation na kailangang i-review —
        // "Queuing" (Waiting for Approval) + approved na 2D na may 3D na naghihintay ng approval
        if ($role === ROLE_DESIGNER && $isHead) {
            try {
                $c['designerReview'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblecrm_2dquotation
                     WHERE status = 'Waiting for Approval'
                        OR (status = 'Approved' AND design_3d_stage = 'Waiting for Approval')"
                );
            } catch (Throwable $e) {
                error_log('sidebar designerReview: ' . $e->getMessage());
            }
        }

        // CRM Designer (bawat Designer, pati Head): mga inquiry na naka-assign sa kanya na may gagawin siya —
        //   • site visit na hindi pa nagagawa (bagong assign)
        //   • Initial na ibinalik (Designer Head o Customer) · standalone 3D na ibinalik
        //   • Final na ibinalik (Superadmin) · bukas na feedback ng Cutting sa 2D
        // Hindi kasama ang "Not proceeding" na client.
        if ($role === ROLE_DESIGNER) {
            try {
                // may `stage` column ba ang feedback? (Initial at Final ay magkaibang table → puwedeng magkapareho ang id)
                static $fbHasStage = null;
                if ($fbHasStage === null) {
                    $chk = @$conn->query("SHOW COLUMNS FROM noblecrm_2d_feedback LIKE 'stage'");
                    $fbHasStage = $chk && $chk->num_rows > 0;
                }
                $fbStage = $fbHasStage ? " AND COALESCE(fb.stage, 'Initial') = 'Initial'" : '';

                $c['designerTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*)
                     FROM noblecrminquiry i
                     LEFT JOIN noblecrm_2dquotation q ON q.id = (
                         SELECT q2.id FROM noblecrm_2dquotation q2
                         WHERE q2.inquiry_id = i.id AND q2.stage = 'Initial'
                         ORDER BY q2.created_at DESC, q2.id DESC LIMIT 1)
                     LEFT JOIN noblecrm_2dquotation_final f ON f.id = (
                         SELECT f2.id FROM noblecrm_2dquotation_final f2
                         WHERE f2.inquiry_id = i.id
                         ORDER BY f2.created_at DESC, f2.id DESC LIMIT 1)
                     WHERE i.designer_id = ?
                       AND COALESCE(i.client_status, '') <> 'no'
                       AND (
                           (i.status = 'Pending' AND COALESCE(i.mode, 'site_visit') <> 'ready_for_quotation')
                           OR q.status = 'For Revision'
                           OR (q.status = 'Approved' AND COALESCE(q.include_3d, 0) = 0 AND q.design_3d_stage = 'For Revision')
                           OR f.status = 'For Revision'
                           OR EXISTS (SELECT 1 FROM noblecrm_2d_feedback fb
                                      WHERE fb.quotation_id = q.id AND fb.is_resolved = 0{$fbStage})
                       )",
                    'i',
                    [$user_id]
                );
            } catch (Throwable $e) {
                error_log('sidebar designerTodo: ' . $e->getMessage());
            }
        }

        // Purchase Order Approval (Superadmin/CEO): Purchase Request na naghihintay ng approval
        // (parehong "For Approval" ng PurchaseOrder.php)
        if ($role === ROLE_SUPERADMIN) {
            try {
                $c['prApproval'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepurchaserequest WHERE status = 'Pending'"
                );
            } catch (Throwable $e) {
                error_log('sidebar prApproval: ' . $e->getMessage());
            }
        }

        // Receive Purchase (Accounting Staff): na-review na ng Accounting Head pero hindi pa nare-receive
        // (parehong "To Receive" ng index-staff-purchase.php)
        if ($role === ROLE_ACCOUNTING && $isStaff) {
            try {
                $c['prToReceive'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepurchaserequest
                     WHERE status = 'Approved' AND reviewed_at IS NOT NULL AND staff_received_at IS NULL"
                );
            } catch (Throwable $e) {
                error_log('sidebar prToReceive: ' . $e->getMessage());
            }
        }

        // Purchase Request (Custodian / Custodian Assistant): na-receive na ng Staff pero hindi pa tapos —
        // ire-release pa, o released na pero hindi pa Mark as Paid ("To Release" + "Waiting to be Paid")
        if ($role === ROLE_ACCOUNTING && ($isCustodian || $isCustoAssist)) {
            try {
                $c['prToRelease'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepurchaserequest
                     WHERE status = 'Approved' AND staff_received_at IS NOT NULL AND paid_at IS NULL"
                );
            } catch (Throwable $e) {
                error_log('sidebar prToRelease: ' . $e->getMessage());
            }
        }

        // 2D & Quotation (Superadmin, Final): pinakabagong Final ng bawat inquiry na may "Review" —
        // Waiting for Approval, o approved na may 3D na naghihintay (parehong review_target ng check2dquotationajax)
        if ($role === ROLE_SUPERADMIN) {
            try {
                $c['finalReview'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*)
                     FROM noblecrm_2dquotation_final q
                     JOIN (SELECT inquiry_id, MAX(id) AS max_id FROM noblecrm_2dquotation_final GROUP BY inquiry_id) latest
                         ON latest.max_id = q.id
                     JOIN noblecrminquiry i ON i.id = q.inquiry_id
                     WHERE q.status = 'Waiting for Approval'
                        OR (q.status = 'Approved' AND COALESCE(q.design_3d_stage, 'Locked') = 'Waiting for Approval')"
                );
            } catch (Throwable $e) {
                error_log('sidebar finalReview: ' . $e->getMessage());
            }
        }

        // CRM List (Accounting Head): approved na 2D & Quotation na naghihintay ng deposit
        // (parehong "Waiting for deposit" ng index-accounting-crmlist.php — pinakabagong record bawat inquiry)
        if ($role === ROLE_ACCOUNTING && $isHead) {
            try {
                $c['depositWaiting'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*)
                     FROM noblecrm_2dquotation q
                     JOIN (SELECT inquiry_id, MAX(id) AS max_id FROM noblecrm_2dquotation GROUP BY inquiry_id) latest
                         ON latest.max_id = q.id
                     JOIN noblecrminquiry i ON i.id = q.inquiry_id
                     WHERE q.status = 'Approved'
                       AND COALESCE(q.deposit_status, 'Hold') <> 'Notice to Proceed'"
                );
            } catch (Throwable $e) {
                error_log('sidebar depositWaiting: ' . $e->getMessage());
            }
        }

        if ($role === ROLE_ACCOUNTING && $isHead) {
            // Purchase Order List: approved na ng CEO pero hindi pa nire-review/pinipirmahan ng Accounting Head
            try {
                $c['poToReview'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepurchaserequest WHERE status = 'Approved' AND reviewed_at IS NULL"
                );
            } catch (Throwable $e) {
                error_log('sidebar poToReview: ' . $e->getMessage());
            }
            // Progress Billing: ipinasa ng PC, naghihintay ng pirma ng Accounting Head (Noted by)
            try {
                $c['billingPending'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepc_billing WHERE status = 'pending_head'"
                );
            } catch (Throwable $e) {
                error_log('sidebar billingPending: ' . $e->getMessage());
            }
        }

        // Purchase Order (PO): approved BOM na may gagawin pa ang PO — parehong mga button ng index-purchaseorder-main.php:
        //   Receive (hindi pa na-receive) · Next: Purchase Request (wala pa / ibinalik ang huling PR)
        //   · Next: Transmittal (bayad na ang PR pero wala pang transmittal)
        // Bilang ng ORDER (mga BOM na sabay ginawa sa Cutting = isang row sa Purchase Orders)
        if ($role === ROLE_PO) {
            try {
                include_once ROOT_PATH . '/network/po-bom-group.php';
                $prOf = poPrOfBomSql($conn, 'b.id');
                $gk = poGroupKeySql('b');
                $c['poTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(DISTINCT $gk)
                     FROM noblecrm_bom b
                     LEFT JOIN noblepurchaserequest pr ON pr.id = $prOf
                     WHERE b.approved_at IS NOT NULL AND b.bom_number NOT LIKE 'TEMP-%'
                       AND (
                           b.received_at IS NULL
                           OR pr.id IS NULL
                           OR pr.status = 'Rejected'
                           OR (pr.status = 'Approved' AND pr.paid_at IS NOT NULL
                               AND NOT EXISTS (SELECT 1 FROM nobletransmittal t WHERE t.pr_id = pr.id))
                       )"
                );
            } catch (Throwable $e) {
                error_log('sidebar poTodo: ' . $e->getMessage());
            }
        }

        // Transmittal Summary (PO): na-save na ang transmittal pero hindi pa na-mark na Delivered ("For Delivery")
        if ($role === ROLE_PO) {
            try {
                $c['poForDelivery'] = sidebarScalar($conn, "SELECT COUNT(*) FROM nobletransmittal WHERE delivered_at IS NULL");
            } catch (Throwable $e) {
                error_log('sidebar poForDelivery: ' . $e->getMessage());
            }
        }

        // ── Superadmin: mga papel ng PC na naghihintay ng pirma mo (pareho ng "For signing" ng bawat page) ──
        if ($role === ROLE_SUPERADMIN) {
            foreach ([
                'saDrPending' => "SELECT COUNT(*) FROM noblepc_dr WHERE COALESCE(status, 'pending') = 'pending'",
                'saLaborPending' => "SELECT COUNT(*) FROM noblepc_laborquote WHERE status = 'pending'",
                'saBillingPending' => "SELECT COUNT(*) FROM noblepc_billing WHERE status = 'pending_sa'",   // na-note na ng Accounting Head
                'saTurnoverPending' => "SELECT COUNT(*) FROM noblepc_turnover WHERE status = 'pending_sa'",
            ] as $key => $sql) {
                try {
                    $c[$key] = sidebarScalar($conn, $sql);
                } catch (Throwable $e) {
                    error_log("sidebar $key: " . $e->getMessage());
                }
            }
        }

        // ── Project Coordinator: may gagawin ang PC sa bawat page (pareho ng "To do" ng mga page) ──
        if ($role === ROLE_PC) {
            // Coordinator (Delivery Receipts): Delivered na ang transmittal pero wala pang DR, o ibinalik ng Superadmin ang DR
            try {
                $c['pcDrTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM nobletransmittal t
                     LEFT JOIN noblepc_dr d ON d.transmittal_id = t.id
                     WHERE (d.id IS NULL AND t.delivered_at IS NOT NULL) OR d.status = 'returned'"
                );
            } catch (Throwable $e) {
                error_log('sidebar pcDrTodo: ' . $e->getMessage());
            }
            // Labor Quotation: approved na DR na wala pang foreman / labor quotation, o ibinalik ang quotation
            try {
                $c['pcLaborTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepc_dr d
                     LEFT JOIN noblepc_foreman f ON f.dr_id = d.id
                     LEFT JOIN noblepc_laborquote q ON q.dr_id = d.id
                     WHERE d.status = 'approved' AND (f.id IS NULL OR q.id IS NULL OR q.status = 'returned')"
                );
            } catch (Throwable $e) {
                error_log('sidebar pcLaborTodo: ' . $e->getMessage());
            }
            // Timeline: approved na labor quotation na wala pang timeline ("No timeline yet")
            try {
                $c['pcTimelineTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM noblepc_laborquote q
                     JOIN noblepc_dr d ON d.id = q.dr_id
                     LEFT JOIN noblepc_timeline tl ON tl.dr_id = d.id
                     WHERE q.status = 'approved' AND tl.id IS NULL"
                );
            } catch (Throwable $e) {
                error_log('sidebar pcTimelineTodo: ' . $e->getMessage());
            }
            // Billing: ibinalik ang huling billing, o puwede nang mag-bill (approved ang huli / wala pa) at may balanse pa
            try {
                $c['pcBillingTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM (
                         SELECT q.total AS contract,
                                (SELECT b.status FROM noblepc_billing b WHERE b.dr_id = q.dr_id ORDER BY b.seq DESC LIMIT 1) AS last_status,
                                (SELECT b.change_orders FROM noblepc_billing b WHERE b.dr_id = q.dr_id ORDER BY b.seq DESC LIMIT 1) AS co,
                                (SELECT COALESCE(SUM(b.this_period), 0) FROM noblepc_billing b WHERE b.dr_id = q.dr_id AND b.status = 'approved') AS approved_total,
                                (SELECT COUNT(*) FROM noblepc_billing b WHERE b.dr_id = q.dr_id) AS n
                         FROM noblepc_laborquote q
                         JOIN noblepc_dr d ON d.id = q.dr_id
                         WHERE q.status = 'approved'
                     ) x
                     WHERE x.last_status = 'returned'
                        OR ((x.last_status IS NULL OR x.last_status = 'approved')
                            AND (x.n = 0 OR x.approved_total < x.contract + COALESCE(x.co, 0) - 0.004))"
                );
            } catch (Throwable $e) {
                error_log('sidebar pcBillingTodo: ' . $e->getMessage());
            }
            // Turnover Letter: ibinalik ang letter, pirmado na ng Superadmin (Complete Turnover na lang),
            // o handa na (verified lahat ng punch item + kumpleto at approved ang billing) pero wala pang letter
            try {
                $c['pcTurnoverTodo'] = sidebarScalar(
                    $conn,
                    "SELECT COUNT(*) FROM (
                         SELECT q.total AS contract, tv.status AS to_status,
                                (SELECT COUNT(*) FROM noblepc_punch p WHERE p.dr_id = q.dr_id AND p.status <> 'verified') AS punch_open,
                                (SELECT COUNT(*) FROM noblepc_billing b WHERE b.dr_id = q.dr_id) AS bill_n,
                                (SELECT COUNT(*) FROM noblepc_billing b WHERE b.dr_id = q.dr_id AND b.status = 'approved') AS bill_a,
                                (SELECT COALESCE(SUM(b.this_period), 0) FROM noblepc_billing b WHERE b.dr_id = q.dr_id AND b.status = 'approved') AS approved_total,
                                (SELECT b.change_orders FROM noblepc_billing b WHERE b.dr_id = q.dr_id ORDER BY b.seq DESC LIMIT 1) AS co
                         FROM noblepc_laborquote q
                         JOIN noblepc_dr d ON d.id = q.dr_id
                         LEFT JOIN noblepc_turnover tv ON tv.dr_id = q.dr_id
                         WHERE q.status = 'approved'
                     ) x
                     WHERE x.to_status IN ('returned', 'approved')
                        OR (x.to_status IS NULL AND x.punch_open = 0 AND x.bill_n > 0 AND x.bill_n = x.bill_a
                            AND x.approved_total >= x.contract + COALESCE(x.co, 0) - 0.004)"
                );
            } catch (Throwable $e) {
                error_log('sidebar pcTurnoverTodo: ' . $e->getMessage());
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