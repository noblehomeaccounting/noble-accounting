<?php
// network/crm-project-stage.php — "Nasaan na ang project?" pagkatapos ma-approve ang BOM, hanggang Turnover.
// Ginagamit ng crmlistajax.php (Sales) para makita nila ang galaw ng project hanggang ma-turn over.
//
// Isang inquiry → puwedeng maraming BOM. Bawat BOM ay isang "linya":
//   BOM → Purchase Request → Transmittal → DR → Labor → Timeline → Billing → Punch list → Turnover
// Ang stage ng buong inquiry = ang pinakamabagal na linya. "Turned over" lang kapag tapos LAHAT ng linya.
//
// Kinukuwenta mula sa mga table (walang hiwalay na status column na puwedeng hindi tumugma).
// Kapag wala pa ang mga table ng Operations (noblepc_*), bumabalik lang sa mas simpleng query.

if (!function_exists('crmProjectStages')) {
    // Mga hakbang pagkatapos ng "Cutting" (step 6) sa Track ng CRM List
    define('CRM_PROJECT_STEPS', [7 => 'Purchasing', 8 => 'Delivery', 9 => 'Installation', 10 => 'Billing', 11 => 'Turnover']);

    /**
     * @param int[] $ids  inquiry ids
     * @return array  [inquiryId => ['phase','phase_label','status','tone','note','done','lines'=>[...]]]
     *                (kasama lang ang inquiry na lampas na sa Cutting — may approved na BOM ang lahat ng linya)
     */
    function crmProjectStages(mysqli $conn, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);   // int lang, ligtas
        // Isang PR = lahat ng BOM na sabay ginawa sa Cutting (kapag naka-install ang po-bom-group)
        @include_once ROOT_PATH . '/network/po-bom-group.php';
        $prOf = function_exists('poPrOfBomSql') ? poPrOfBomSql($conn, 'b.id') : '(SELECT MAX(p2.id) FROM noblepurchaserequest p2 WHERE p2.bom_id = b.id)';

        $base = "SELECT q.inquiry_id, b.id AS bom_id, b.progression_id, b.bom_number, b.approved_at AS bom_approved_at,
                        pr.id AS pr_id, pr.req_no, pr.status AS pr_status, pr.approved_at AS pr_approved_at, pr.reviewed_at,
                        pr.staff_received_at, pr.released_at, pr.paid_at,
                        t.id AS tr_id, t.ref_no, t.delivered_at";
        $from = " FROM noblecrm_bom b
                  JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                  LEFT JOIN noblepurchaserequest pr ON pr.id = $prOf
                  LEFT JOIN nobletransmittal t ON t.id = (SELECT MAX(t2.id) FROM nobletransmittal t2 WHERE t2.pr_id = pr.id)";
        $where = " WHERE q.inquiry_id IN ($in) AND b.bom_number NOT LIKE 'TEMP-%'
                   ORDER BY q.inquiry_id, b.id";

        $ops = ",
                d.id AS dr_id, d.dr_no, COALESCE(d.status, 'pending') AS dr_status,
                f.id AS foreman_id, lq.status AS lq_status, lq.total AS lq_total,
                tl.date_started, tl.date_finish,
                (SELECT COUNT(*) FROM noblepc_billing x WHERE x.dr_id = d.id) AS bill_n,
                (SELECT x.status FROM noblepc_billing x WHERE x.dr_id = d.id ORDER BY x.seq DESC LIMIT 1) AS bill_last,
                (SELECT x.change_orders FROM noblepc_billing x WHERE x.dr_id = d.id ORDER BY x.seq DESC LIMIT 1) AS bill_co,
                (SELECT COALESCE(SUM(x.this_period), 0) FROM noblepc_billing x WHERE x.dr_id = d.id AND x.status = 'approved') AS bill_approved,
                (SELECT COUNT(*) FROM noblepc_punch x WHERE x.dr_id = d.id AND x.status <> 'verified') AS punch_open,
                tv.status AS to_status, tv.accepted_date";
        $opsFrom = "
                  LEFT JOIN noblepc_dr d ON d.id = (SELECT MAX(d2.id) FROM noblepc_dr d2 WHERE d2.transmittal_id = t.id)
                  LEFT JOIN noblepc_foreman f ON f.dr_id = d.id
                  LEFT JOIN noblepc_laborquote lq ON lq.dr_id = d.id
                  LEFT JOIN noblepc_timeline tl ON tl.dr_id = d.id
                  LEFT JOIN noblepc_turnover tv ON tv.dr_id = d.id";

        $rows = null;
        try {
            $res = $conn->query($base . $ops . $from . $opsFrom . $where);
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : null;
        } catch (Throwable $e) {
            $rows = null;
        }
        if ($rows === null) {
            // wala pa ang mga table ng Operations → hanggang Delivery lang
            try {
                $res = $conn->query($base . $from . $where);
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            } catch (Throwable $e) {
                error_log('crmProjectStages: ' . $e->getMessage());
                return [];
            }
        }

        $byInq = [];
        foreach ($rows as $r) {
            // Iisang PR (iisang order) → iisang linya, kahit ilang BOM ang kasama
            $inq = (int) $r['inquiry_id'];
            $key = !empty($r['pr_id']) ? 'pr' . $r['pr_id'] : (!empty($r['progression_id']) && !empty($r['bom_approved_at']) ? 'up' . $r['progression_id'] : 'bom' . $r['bom_id']);
            if (isset($byInq[$inq][$key])) {
                $byInq[$inq][$key]['bom_number'] .= ', ' . $r['bom_number'];
                continue;
            }
            $byInq[$inq][$key] = crmProjectLine($r);
        }

        $out = [];
        foreach ($byInq as $inqId => $lines) {
            $lines = array_values($lines);
            $min = min(array_column($lines, 'phase'));
            if ($min < 7) {
                continue;   // may BOM pa sa Cutting → ang dating Track (crmlistprogressajax) ang bahala
            }
            $n = count($lines);
            $closed = count(array_filter($lines, fn($l) => $l['done']));

            if ($closed === $n) {
                $dates = array_filter(array_column($lines, 'accepted_date'));
                $out[$inqId] = [
                    'phase' => 11, 'phase_label' => 'Turnover', 'status' => 'Turned over', 'tone' => 'green', 'done' => true,
                    'note' => 'Project closed' . ($dates ? ' · accepted by the client ' . date('M j, Y', strtotime(max($dates))) : ''),
                    'lines' => $lines,
                ];
                continue;
            }

            // pinakamabagal na linya; kapag tabla, inuuna ang may problema (pula)
            $open = array_values(array_filter($lines, fn($l) => !$l['done']));
            usort($open, fn($a, $b) => [$a['phase'], $a['tone'] === 'red' ? 0 : 1] <=> [$b['phase'], $b['tone'] === 'red' ? 0 : 1]);
            $slow = $open[0];
            $note = $slow['note'];
            if ($n > 1) {
                $note = ($slow['bom_number'] ? $slow['bom_number'] . ': ' : '') . $note . ' · ' . $closed . ' of ' . $n . ' turned over';
            }
            $out[$inqId] = [
                'phase' => $slow['phase'], 'phase_label' => CRM_PROJECT_STEPS[$slow['phase']] ?? '', 'status' => $slow['status'],
                'tone' => $slow['tone'], 'done' => false, 'note' => $note, 'lines' => $lines,
            ];
        }
        return $out;
    }

    // Stage ng isang linya (isang BOM)
    function crmProjectLine(array $r): array
    {
        $L = fn(int $phase, string $status, string $tone, string $note = '', bool $done = false) => [
            'bom_number' => $r['bom_number'] ?? '', 'phase' => $phase, 'phase_label' => CRM_PROJECT_STEPS[$phase] ?? 'Cutting',
            'status' => $status, 'tone' => $tone, 'note' => $note, 'done' => $done, 'accepted_date' => $r['accepted_date'] ?? null,
        ];
        $ago = fn($t) => $t ? date('M j', strtotime($t)) : '';

        // 6. Cutting / BOM
        if (empty($r['bom_approved_at'])) {
            return $L(6, 'BOM for approval', 'blue', 'Waiting for the Superadmin to approve the BOM');
        }
        // 7. Purchasing
        if (empty($r['pr_id'])) {
            return $L(7, 'Preparing purchase request', 'amber', 'BOM approved · Purchasing is preparing the PR');
        }
        if (($r['pr_status'] ?? '') === 'Rejected') {
            return $L(7, 'Purchase request returned', 'red', 'Returned by the CEO · Purchasing will make a new one');
        }
        if (($r['pr_status'] ?? '') !== 'Approved') {
            return $L(7, 'PR for CEO approval', 'blue', 'Purchase request ' . ($r['req_no'] ?? '') . ' is waiting for approval');
        }
        if (empty($r['paid_at'])) {
            $step = !empty($r['released_at']) ? 'Budget released, waiting to be paid'
                : (!empty($r['staff_received_at']) ? 'Received by Accounting' : (!empty($r['reviewed_at']) ? 'Reviewed by the Accounting Head' : 'Approved · for Accounting review'));
            return $L(7, 'Purchasing materials', 'blue', $step);
        }
        if (empty($r['tr_id'])) {
            return $L(7, 'Paid · preparing transmittal', 'amber', 'Materials paid ' . $ago($r['paid_at']));
        }
        // 8. Delivery
        if (empty($r['delivered_at'])) {
            return $L(8, 'For delivery', 'blue', 'Transmittal ' . ($r['ref_no'] ?? '') . ' issued');
        }
        if (!array_key_exists('dr_id', $r)) {
            return $L(8, 'Delivered', 'green', 'Delivered ' . $ago($r['delivered_at']));   // walang Operations tables
        }
        if (empty($r['dr_id'])) {
            return $L(8, 'Delivered · DR being prepared', 'blue', 'Delivered ' . $ago($r['delivered_at']));
        }
        if ($r['dr_status'] === 'returned') {
            return $L(8, 'DR being fixed', 'red', 'Delivery receipt returned by the Superadmin');
        }
        if ($r['dr_status'] !== 'approved') {
            return $L(8, 'DR for signing', 'blue', 'DR# ' . $r['dr_no'] . ' is waiting for the Superadmin');
        }
        // 9. Installation (Labor)
        if (empty($r['foreman_id']) || empty($r['lq_status'])) {
            return $L(9, 'Preparing labor', 'amber', 'DR signed · Project Coordinator is preparing the foreman and labor quotation');
        }
        if ($r['lq_status'] === 'returned') {
            return $L(9, 'Labor quotation being fixed', 'red', 'Returned by the Superadmin');
        }
        if ($r['lq_status'] !== 'approved') {
            return $L(9, 'Labor quotation for signing', 'blue', 'Waiting for the Superadmin');
        }
        $today = date('Y-m-d');
        $tlNote = empty($r['date_started']) ? 'Timeline not set yet'
            : ($today < $r['date_started'] ? 'Starts ' . date('M j', strtotime($r['date_started']))
                : ($today <= $r['date_finish'] ? 'On-going until ' . date('M j', strtotime($r['date_finish'])) : 'Timeline ended ' . date('M j', strtotime($r['date_finish']))));
        $billN = (int) ($r['bill_n'] ?? 0);
        $contract = round((float) $r['lq_total'] + (float) ($r['bill_co'] ?? 0), 2);
        $approved = (float) ($r['bill_approved'] ?? 0);
        $fully = $billN > 0 && $contract > 0 && $approved >= $contract - 0.004;
        if ($billN === 0) {
            return $L(9, 'Installation', 'blue', $tlNote);
        }
        // 10. Billing
        if (!$fully) {
            $pct = $contract > 0 ? round($approved / $contract * 100) : 0;
            $last = $r['bill_last'] ?? '';
            if ($last === 'returned') return $L(10, 'Billing being fixed', 'red', $pct . '% billed · ' . $tlNote);
            if ($last === 'pending_head' || $last === 'pending_sa') return $L(10, 'Billing for signing', 'blue', $pct . '% billed · ' . $tlNote);
            return $L(10, $pct . '% billed', 'blue', $tlNote);
        }
        // 11. Punch list & Turnover
        $to = $r['to_status'] ?? null;
        if ($to === 'closed') {
            return $L(11, 'Turned over', 'green', 'Accepted ' . ($r['accepted_date'] ? date('M j, Y', strtotime($r['accepted_date'])) : ''), true);
        }
        if ($to === 'approved') return $L(11, 'Waiting for client acceptance', 'blue', 'Turnover letter signed · for the client to sign');
        if ($to === 'pending_sa') return $L(11, 'Turnover letter for signing', 'blue', 'Waiting for the Superadmin');
        if ($to === 'returned') return $L(11, 'Turnover letter being fixed', 'red', 'Returned by the Superadmin');
        $open = (int) ($r['punch_open'] ?? 0);
        return $open > 0
            ? $L(11, 'Punch list', 'amber', $open . ' item' . ($open === 1 ? '' : 's') . ' to fix before turnover')
            : $L(11, 'Preparing turnover', 'amber', 'Fully billed · preparing the turnover letter');
    }
}