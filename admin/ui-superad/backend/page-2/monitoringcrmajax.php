<?php
// monitoringcrmajax.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

function monRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Lahat ng status na pwedeng lumabas sa history (label => color). Ito rin ang gamit ng filter chips.
const MON_STATUSES = [
    'CONTACTING CLIENT'       => 'amber',
    'WAITING FOR CLIENT'      => 'amber',
    'NOT PROCEEDING'          => 'red',
    'WAITING FOR MEASUREMENT' => 'amber',
    'REVISIT NEEDED'          => 'amber',
    'SITE VISIT DONE'         => 'green',
    '2D DESIGN'               => 'green',
    '2D & QUOTATION'          => 'green',
    'INITIAL FOR APPROVAL'    => 'blue',
    'INITIAL REVISION'        => 'red',
    'INITIAL APPROVED'        => 'green',
    'WAITING FOR 3D'          => 'amber',
    '3D FOR APPROVAL'         => 'blue',
    '3D REVISION'             => 'red',
    'FOR CLIENT REVIEW'       => 'blue',
    'CUSTOMER REVISION'       => 'red',
    'CUSTOMER APPROVED'       => 'green',
    'FINAL IN PROGRESS'       => 'green',
    'FINAL FOR APPROVAL'      => 'blue',
    'FINAL REVISION'          => 'red',
    'FINAL APPROVED'          => 'green',
    // ── Notice to Proceed → Delivery (galing sa accounting / production / purchasing) ──
    'NOTICE TO PROCEED'       => 'green',
    'CUTTING LIST UPLOADED'   => 'blue',
    'CUTTING LIST REJECTED'   => 'red',
    'CUTTING LIST APPROVED'   => 'green',
    'BOM SUBMITTED'           => 'blue',
    'BOM APPROVED'            => 'green',
    'BOM RECEIVED'            => 'green',
    'PR SUBMITTED'            => 'amber',
    'PR RETURNED'             => 'red',
    'PR APPROVED'             => 'green',
    'PR REVIEWED'             => 'blue',
    'PR RECEIVED BY ACCOUNTING' => 'blue',
    'PR RELEASED'             => 'blue',
    'PR PAID'                 => 'green',
    'TRANSMITTAL ISSUED'      => 'amber',
    'DELIVERED'               => 'green',
    'DR CREATED'              => 'green',
    // ── Operations (Project Coordinator) → Turnover ──
    'DR FOR APPROVAL'         => 'blue',
    'DR RETURNED'             => 'red',
    'DR APPROVED'             => 'green',
    'FOREMAN ASSIGNED'        => 'green',
    'LABOR QUOTE SUBMITTED'   => 'blue',
    'LABOR QUOTE RETURNED'    => 'red',
    'LABOR QUOTE APPROVED'    => 'green',
    'TIMELINE SET'            => 'green',
    'BILLING SUBMITTED'       => 'amber',
    'BILLING RETURNED'        => 'red',
    'BILLING NOTED'           => 'blue',
    'BILLING APPROVED'        => 'green',
    'FULLY BILLED'            => 'green',
    'PUNCH LIST OPEN'         => 'amber',
    'PUNCH LIST CLOSED'       => 'green',
    'TURNOVER SUBMITTED'      => 'blue',
    'TURNOVER RETURNED'       => 'red',
    'TURNOVER SIGNED'         => 'green',
    'PARTLY TURNED OVER'      => 'amber',
    'TURNED OVER'             => 'green',
];

// Mga status ng Operations (Project Coordinator): DR approval → Turnover
const MON_OPS = [
    'DR FOR APPROVAL', 'DR RETURNED', 'DR APPROVED', 'FOREMAN ASSIGNED',
    'LABOR QUOTE SUBMITTED', 'LABOR QUOTE RETURNED', 'LABOR QUOTE APPROVED', 'TIMELINE SET',
    'BILLING SUBMITTED', 'BILLING RETURNED', 'BILLING NOTED', 'BILLING APPROVED', 'FULLY BILLED',
    'PUNCH LIST OPEN', 'PUNCH LIST CLOSED', 'TURNOVER SUBMITTED', 'TURNOVER RETURNED', 'TURNOVER SIGNED', 'PARTLY TURNED OVER', 'TURNED OVER',
];

// Mga status na galing sa production / purchasing (para sa divider sa Status History)
const MON_PIPELINE = [
    'NOTICE TO PROCEED', 'CUTTING LIST UPLOADED', 'CUTTING LIST REJECTED', 'CUTTING LIST APPROVED', 'BOM SUBMITTED', 'BOM APPROVED', 'BOM RECEIVED',
    'PR SUBMITTED', 'PR RETURNED', 'PR APPROVED', 'PR REVIEWED', 'PR RECEIVED BY ACCOUNTING', 'PR RELEASED', 'PR PAID',
    'TRANSMITTAL ISSUED', 'DELIVERED', 'DR CREATED',
    'DR FOR APPROVAL', 'DR RETURNED', 'DR APPROVED', 'FOREMAN ASSIGNED',
    'LABOR QUOTE SUBMITTED', 'LABOR QUOTE RETURNED', 'LABOR QUOTE APPROVED', 'TIMELINE SET',
    'BILLING SUBMITTED', 'BILLING RETURNED', 'BILLING NOTED', 'BILLING APPROVED', 'FULLY BILLED',
    'PUNCH LIST OPEN', 'PUNCH LIST CLOSED', 'TURNOVER SUBMITTED', 'TURNOVER RETURNED', 'TURNOVER SIGNED', 'PARTLY TURNED OVER', 'TURNED OVER',
];

// Ano ang susunod pagkatapos ng huling production / purchasing status: [note, expected next]
const MON_PIPELINE_NEXT = [
    'NOTICE TO PROCEED'         => ['Deposit received, production can start', 'Cutting List'],
    'CUTTING LIST UPLOADED'     => ['Cutting list uploaded, waiting for QR approval', 'Cutting List Approval'],
    'CUTTING LIST REJECTED'     => ['Cutting list was rejected, needs re-upload', 'Re-upload Cutting List'],
    'CUTTING LIST APPROVED'     => ['Cutting list approved', 'BOM'],
    'BOM SUBMITTED'             => ['BOM submitted, waiting for Superadmin approval', 'BOM Approval'],
    'BOM APPROVED'              => ['BOM approved, waiting for Purchasing to receive it', 'Purchasing'],
    'BOM RECEIVED'              => ['BOM received by Purchasing', 'Purchase Request'],
    'PR SUBMITTED'              => ['Purchase request waiting for CEO approval', 'PR Approval'],
    'PR RETURNED'               => ['Purchase request was returned by the CEO', 'New Purchase Request'],
    'PR APPROVED'               => ['Purchase request approved, for Accounting review', 'Accounting Review'],
    'PR REVIEWED'               => ['Reviewed by the Accounting Head', 'Accounting Staff'],
    'PR RECEIVED BY ACCOUNTING' => ['Received by Accounting Staff', 'Release'],
    'PR RELEASED'               => ['Budget released, waiting to be paid', 'Payment'],
    'PR PAID'                   => ['Purchase request paid', 'Transmittal'],
    'TRANSMITTAL ISSUED'        => ['Transmittal issued, for delivery', 'Delivery'],
    'DELIVERED'                 => ['Items delivered to the client', 'Delivery Receipt'],
    'DR CREATED'                => ['Delivery receipt prepared by the Project Coordinator', 'DR Approval'],
    'DR FOR APPROVAL'           => ['Delivery receipt waiting for the Superadmin\'s signature', 'DR Approval'],
    'DR RETURNED'               => ['Delivery receipt returned to the Project Coordinator', 'Fix & resubmit DR'],
    'DR APPROVED'               => ['Delivery receipt signed, can be printed', 'Foreman ID'],
    'FOREMAN ASSIGNED'          => ['Foreman and ID recorded', 'Labor Quotation'],
    'LABOR QUOTE SUBMITTED'     => ['Labor quotation waiting for the Superadmin\'s signature', 'Labor Quote Approval'],
    'LABOR QUOTE RETURNED'      => ['Labor quotation returned to the Project Coordinator', 'Fix & resubmit Labor Quote'],
    'LABOR QUOTE APPROVED'      => ['Labor quotation approved', 'Timeline'],
    'TIMELINE SET'              => ['Project timeline prepared', 'Progress Billing'],
    'BILLING SUBMITTED'         => ['Progress billing waiting for the Accounting Head', 'Accounting Head (Noted by)'],
    'BILLING RETURNED'          => ['Progress billing returned to the Project Coordinator', 'Fix & resubmit Billing'],
    'BILLING NOTED'             => ['Noted by the Accounting Head, waiting for the Superadmin', 'Superadmin (Approved by)'],
    'BILLING APPROVED'          => ['Progress billing approved', 'Next Billing'],
    'FULLY BILLED'              => ['Contract is fully billed and approved', 'Punch List'],
    'PUNCH LIST OPEN'           => ['Punch list items being fixed', 'Verify Punch List'],
    'PUNCH LIST CLOSED'         => ['All punch list items verified', 'Turnover Letter'],
    'TURNOVER SUBMITTED'        => ['Turnover letter waiting for the Superadmin\'s signature', 'Turnover Approval'],
    'TURNOVER RETURNED'         => ['Turnover letter returned to the Project Coordinator', 'Fix & resubmit Turnover'],
    'TURNOVER SIGNED'           => ['Turnover letter signed, waiting for the client\'s signature', 'Client Acceptance'],
    'PARTLY TURNED OVER'        => ['Some BOMs are turned over, the rest are still in progress', 'Finish the remaining BOMs'],
    'TURNED OVER'               => ['Project turned over to the client and closed', '—'],
];

// Stage ng bawat inquiry. Returns [label, color, note, expected next].
function monStage(array $r): array
{
    $cs = $r['client_status'] ?? null;
    $hasDesigner = !empty($r['designer_id']);
    $initial = $r['initial_status'] ?? null;
    $final = $r['final_status'] ?? null;
    $readyForQuotation = ($r['mode'] ?? 'site_visit') === 'ready_for_quotation';

    // 1-2. Wala pang designer
    if (!$hasDesigner) {
        if ($cs === 'no')
            return ['NOT PROCEEDING', 'red', 'Client is not proceeding', '—'];
        if ($cs === 'tentative')
            return ['WAITING FOR CLIENT', 'amber', 'Client has not decided yet', 'Site Visit'];
        return ['CONTACTING CLIENT', 'amber', 'Contacting client for site visit schedule', 'Site Visit'];
    }

    // Final
    if ($final !== null) {
        switch ($final) {
            case 'Approved':
                return ['FINAL APPROVED', 'green', 'Final 2D & Quotation approved', 'Cutting'];
            case 'Waiting for Approval':
                return ['FINAL FOR APPROVAL', 'blue', 'Waiting for Superadmin approval', 'Final Approval'];
            case 'For Revision':
                return ['FINAL REVISION', 'red', 'Final submission needs revision', 'Re-submit Final'];
            default: // Draft
                $contractMissing = empty($r['contract_amount']) || (float) $r['contract_amount'] <= 0 || empty($r['contract_file']);
                return [
                    'FINAL IN PROGRESS',
                    'green',
                    $contractMissing ? 'Contract not yet set by Sales' : 'Preparing Final 2D & Quotation',
                    'Final Submission',
                ];
        }
    }

    // Initial
    if ($initial !== null) {
        switch ($initial) {
            case 'Approved':
                $include3d = (int) ($r['include_3d'] ?? 0) === 1;
                $stage3d = $r['initial_3d_stage'] ?? 'Locked';

                // Standalone 3D pa ang hinihintay — hindi pa pwedeng ipa-check sa customer.
                if (!$include3d && $stage3d !== 'Approved') {
                    if ($stage3d === 'Waiting for Approval') {
                        return ['3D FOR APPROVAL', 'blue', '2D & Quotation approved · 3D file waiting for Designer Head approval', '3D Approval'];
                    }
                    if ($stage3d === 'For Revision') {
                        return ['3D REVISION', 'red', '3D file needs revision', 'Re-submit 3D'];
                    }
                    return ['WAITING FOR 3D', 'amber', '2D & Quotation approved · waiting for the 3D file', '3D Submission'];
                }

                // Fully approved na ng Head → customer na ang magche-check.
                if ((int) ($r['customer_ok'] ?? 0) >= 3) {
                    return ['CUSTOMER APPROVED', 'green', 'Customer approved all Initial files', 'Final Submission'];
                }
                return ['FOR CLIENT REVIEW', 'blue', 'Fully approved by Designer Head, waiting for the customer\'s decision', 'Customer Decision'];
            case 'Waiting for Approval':
                return ['INITIAL FOR APPROVAL', 'blue', 'Waiting for Designer Head approval', 'Initial Approval'];
            case 'For Revision':
                if (($r['revision_source'] ?? 'Head') === 'Customer') {
                    return ['CUSTOMER REVISION', 'red', 'Customer requested changes on the Initial files', 'Re-submit Initial'];
                }
                return ['INITIAL REVISION', 'red', 'Initial submission needs revision', 'Re-submit Initial'];
            default: // Draft
                return ['2D & QUOTATION', 'green', 'Preparing Initial 2D & Quotation', 'Initial Submission'];
        }
    }

    // Walang site visit para sa ready_for_quotation
    if ($readyForQuotation) {
        return ['2D & QUOTATION', 'green', 'Preparing Initial 2D & Quotation', 'Initial Submission'];
    }

    // Site visit pa lang
    if ((int) ($r['sv_count'] ?? 0) === 0) {
        return ['WAITING FOR MEASUREMENT', 'amber', 'Site visit scheduled, waiting for measurement', 'Site Visit Record'];
    }

    // Na-save ang form pero hindi natuloy ang visit
    if (($r['sv_visited'] ?? 'yes') === 'no') {
        return ['REVISIT NEEDED', 'amber', 'Site was not visited, schedule a revisit', 'Site Visit Record'];
    }

    // Tapos ang site visit → 2D design (progress tracker lang; hindi na hinihintay ang customer confirmation).
    $progress = (string) ($r['design_progress'] ?? '0');
    $note = $progress === '100'
        ? 'Design 100%, ready to submit 2D & Quotation'
        : 'Site visit done · Design progress ' . $progress . '%';
    return ['2D DESIGN', 'green', $note, 'Initial Submission'];
}

// Shared SELECT (walang WHERE) — gamit ng list at ng monLoadRow
function monBaseSql(): string
{
    return "
        SELECT i.id, i.control_no, i.client_name, i.contact_number, i.project_type, i.mode, i.status,
               i.client_status, i.measurement_datetime, i.designer_id, i.sales_staff_id, i.created_at,
               i.design_progress, i.design_confirmed,
               i.contract_amount, i.contract_file,
               d.name AS designer_name, s.name AS sales_name,
               q.status AS initial_status, q.include_3d, q.design_3d_stage AS initial_3d_stage,
               q.revision_source,
               (SELECT COUNT(*) FROM noblecrm_2dquotation_customer_review cr
                 WHERE cr.quotation_id = q.id AND cr.decision = 'Okay'
                   AND cr.id = (SELECT MAX(cr2.id) FROM noblecrm_2dquotation_customer_review cr2
                                 WHERE cr2.quotation_id = cr.quotation_id AND cr2.slot = cr.slot)
               ) AS customer_ok,
               f.status AS final_status,
               (SELECT COUNT(*) FROM noblecrm_sitevisit sv WHERE sv.inquiry_id = i.id) AS sv_count,
               (SELECT sv.visited FROM noblecrm_sitevisit sv
                 WHERE sv.inquiry_id = i.id
                 ORDER BY sv.created_at DESC, sv.id DESC LIMIT 1) AS sv_visited
        FROM noblecrminquiry i
        LEFT JOIN noblerole d ON d.id = i.designer_id
        LEFT JOIN noblerole s ON s.id = i.sales_staff_id
        LEFT JOIN noblecrm_2dquotation q ON q.id = (
            SELECT q2.id FROM noblecrm_2dquotation q2
            WHERE q2.inquiry_id = i.id AND q2.stage = 'Initial'
            ORDER BY q2.created_at DESC, q2.id DESC LIMIT 1
        )
        LEFT JOIN noblecrm_2dquotation_final f ON f.id = (
            SELECT f2.id FROM noblecrm_2dquotation_final f2
            WHERE f2.inquiry_id = i.id
            ORDER BY f2.created_at DESC, f2.id DESC LIMIT 1
        )
    ";
}

// Kunin ang lahat ng kailangan para sa stage ng isang inquiry.
// customer_ok = ilang slot (2D / Quotation / 3D) ang "Okay" ang pinakabagong decision ng customer
function monLoadRow(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(monBaseSql() . " WHERE i.id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}

// Bulk load ng raw data para sa history (4 queries para sa lahat ng inquiry, hindi bawat row).
function monHistoryData(mysqli $conn, array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $out[(int) $id] = ['sv' => [], 'initials' => [], 'reviews' => [], 'finals' => [],
                           'cutting' => [], 'boms' => [], 'prs' => [], 'trs' => [], 'drs' => [],
                           'dr_ap' => [], 'foremen' => [], 'lqs' => [], 'timelines' => [], 'billings' => [], 'punch' => [], 'turnovers' => []];
    }
    if (!$out) {
        return $out;
    }
    $in = implode(',', array_map('intval', array_keys($out)));
    // DR → inquiry (sa pamamagitan ng transmittal → PR → BOM → 2D quotation)
    $opsJoinFromDr = "JOIN nobletransmittal t ON t.id = d.transmittal_id
                      JOIN noblepurchaserequest p ON p.id = t.pr_id
                      JOIN noblecrm_bom b ON b.id = p.bom_id
                      JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                      WHERE q.inquiry_id IN ($in)";
    $opsJoin = "FROM noblepc_dr d " . $opsJoinFromDr;

    $queries = [
        'sv'       => "SELECT inquiry_id, created_at, visited FROM noblecrm_sitevisit WHERE inquiry_id IN ($in) ORDER BY created_at ASC, id ASC",
        'initials' => "SELECT * FROM noblecrm_2dquotation WHERE inquiry_id IN ($in) AND stage = 'Initial' ORDER BY id ASC",
        'reviews'  => "SELECT inquiry_id, slot, decision, decided_at FROM noblecrm_2dquotation_customer_review WHERE inquiry_id IN ($in) ORDER BY id ASC",
        'finals'   => "SELECT * FROM noblecrm_2dquotation_final WHERE inquiry_id IN ($in) ORDER BY id ASC",

        // ── Cutting list → Delivery (lahat naka-link sa inquiry sa pamamagitan ng 2D quotation) ──
        'cutting'  => "SELECT q.inquiry_id, p.*, u.name AS uploaded_by_name, a.name AS approved_by_name, rj.name AS rejected_by_name
                       FROM noblecrm_cuttinglistprogression p
                       JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
                       LEFT JOIN noblerole u  ON u.id  = p.uploaded_by
                       LEFT JOIN noblerole a  ON a.id  = p.approved_by
                       LEFT JOIN noblerole rj ON rj.id = p.rejected_by
                       WHERE q.inquiry_id IN ($in) ORDER BY p.id ASC",
        'boms'     => "SELECT q.inquiry_id, b.id, b.bom_number, b.created_at, b.requested_by, b.approved_at, b.approved_by, b.received_at, b.received_by
                       FROM noblecrm_bom b
                       JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                       WHERE q.inquiry_id IN ($in) AND b.bom_number NOT LIKE 'TEMP-%' ORDER BY b.id ASC",
        'prs'      => "SELECT q.inquiry_id, p.req_no, p.status, p.created_at, p.prepared_by, p.approved_at, p.approved_by,
                              p.rejected_at, p.rejection_remarks, p.reviewed_at, p.reviewed_by, p.staff_received_at, p.staff_received_by,
                              p.released_at, p.released_by, p.paid_at
                       FROM noblepurchaserequest p
                       JOIN noblecrm_bom b ON b.id = p.bom_id
                       JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                       WHERE q.inquiry_id IN ($in) ORDER BY p.id ASC",
        'trs'      => "SELECT q.inquiry_id, t.ref_no, t.created_at, t.issued_by, t.delivered_at, t.delivery_mode
                       FROM nobletransmittal t
                       JOIN noblepurchaserequest p ON p.id = t.pr_id
                       JOIN noblecrm_bom b ON b.id = p.bom_id
                       JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                       WHERE q.inquiry_id IN ($in) ORDER BY t.id ASC",
        'drs'      => "SELECT q.inquiry_id, d.dr_no, d.created_at, d.prepared_by
                       FROM noblepc_dr d
                       JOIN nobletransmittal t ON t.id = d.transmittal_id
                       JOIN noblepurchaserequest p ON p.id = t.pr_id
                       JOIN noblecrm_bom b ON b.id = p.bom_id
                       JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
                       WHERE q.inquiry_id IN ($in) ORDER BY d.id ASC",

        // ── Operations (Project Coordinator) → Turnover; hiwalay na query bawat table para kapag wala pa ang table, iyon lang ang laktaw ──
        'dr_ap'     => "SELECT q.inquiry_id, d.id AS dr_id, d.dr_no, d.created_at, d.status, d.submitted_at, d.approved_at, d.approved_by,
                               d.returned_at, d.returned_by, d.return_remarks, d.permit_required
                        $opsJoin ORDER BY d.id ASC",
        'foremen'   => "SELECT q.inquiry_id, d.dr_no, x.name, x.created_at
                        FROM noblepc_foreman x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr ORDER BY x.id ASC",
        'lqs'       => "SELECT q.inquiry_id, d.dr_no, x.quote_no, x.total, x.subcontractor, x.created_at, x.submitted_at, x.status,
                               x.approved_at, x.approved_by, x.returned_at, x.returned_by, x.return_remarks
                        FROM noblepc_laborquote x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr ORDER BY x.id ASC",
        'timelines' => "SELECT q.inquiry_id, d.dr_no, x.created_at, x.updated_at, x.date_started, x.date_finish,
                               (SELECT COUNT(*) FROM noblepc_timeline_task k WHERE k.timeline_id = x.id) AS task_count
                        FROM noblepc_timeline x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr ORDER BY x.id ASC",
        'billings'  => "SELECT q.inquiry_id, d.id AS dr_id, d.dr_no, x.ref_no, x.billing_type, x.app_no, x.this_period, x.contract_amount, x.change_orders,
                               x.created_at, x.submitted_at, x.status, x.head_at, x.head_by, x.sa_at, x.sa_by, x.returned_at, x.returned_by, x.returned_stage, x.return_remarks
                        FROM noblepc_billing x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr ORDER BY x.dr_id ASC, x.seq ASC",
        'punch'     => "SELECT q.inquiry_id, d.dr_no, MIN(x.created_at) AS first_at, COUNT(*) AS n, SUM(x.status = 'verified') AS v, MAX(x.verified_at) AS last_verified
                        FROM noblepc_punch x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr GROUP BY q.inquiry_id, d.id, d.dr_no",
        'turnovers' => "SELECT q.inquiry_id, d.dr_no, x.created_at, x.submitted_at, x.status, x.sa_at, x.sa_by, x.returned_at, x.returned_by, x.return_remarks,
                               x.closed_at, x.closed_by, x.accepted_date, x.client_name
                        FROM noblepc_turnover x JOIN noblepc_dr d ON d.id = x.dr_id $opsJoinFromDr ORDER BY x.id ASC",
    ];
    foreach ($queries as $key => $sql) {
        // kapag wala pa ang table (hal. noblepc_dr), laktawan lang — huwag sirain ang buong monitoring
        try {
            $res = $conn->query($sql);
        } catch (Throwable $e) {
            $res = false;
        }
        if (!$res) {
            continue;
        }
        while ($row = $res->fetch_assoc()) {
            $out[(int) $row['inquiry_id']][$key][] = $row;
        }
    }
    return $out;
}

// Buuin ang timeline ng lahat ng status na dinaanan ng project, galing sa mga timestamp sa DB.
// $data = preloaded galing monHistoryData (optional; kapag wala, isang inquiry lang ang kukunin).
function monBuildHistory(mysqli $conn, array $r, ?array $data = null): array
{
    $id = (int) $r['id'];
    $data = $data ?? monHistoryData($conn, [$id])[$id];

    $ev = [];
    $seq = 0;
    $add = function (string $label, string $color, ?string $time, string $note = '', ?string $sort = null, string $ref = '') use (&$ev, &$seq) {
        $key = $sort ?? $time;
        $ev[] = [
            'label' => $label, 'color' => $color, 'time' => $time, 'note' => $note, 'ref' => $ref,
            '_sort' => $key ? (strtotime($key) ?: 0) : 0, '_seq' => $seq++,
        ];
    };
    // production / purchasing event (laktaw kapag walang petsa)
    $addP = function (string $label, ?string $time, string $note = '', string $ref = '') use ($add) {
        if (empty($time)) return;
        // DATE lang (walang oras) → dulo ng araw, para hindi mauna sa mga nangyari sa parehong araw
        $sort = strlen($time) <= 10 ? $time . ' 23:59:59' : null;
        $add($label, MON_STATUSES[$label] ?? 'blue', $time, $note, $sort, $ref);
    };
    $by = fn($name) => $name ? 'by ' . $name : '';
    $slotLabel = ['2d' => '2D', 'quotation' => 'Quotation', '3d' => '3D'];
    $readyMode = ($r['mode'] ?? 'site_visit') === 'ready_for_quotation';

    // 1. Inquiry filed
    if ($readyMode) {
        $add('2D & QUOTATION', 'green', $r['created_at'], 'Ready for quotation — no site visit needed');
    } else {
        $add('CONTACTING CLIENT', 'amber', $r['created_at'], 'Inquiry filed');

        $cs = $r['client_status'] ?? null;
        if ($cs === 'no') {
            $add('NOT PROCEEDING', 'red', null, 'Client is not proceeding', $r['created_at']);
        } elseif ($cs === 'tentative') {
            $add('WAITING FOR CLIENT', 'amber', null, 'Client has not decided yet', $r['created_at']);
        } elseif ($cs === 'confirmed' && !empty($r['measurement_datetime'])) {
            $add('WAITING FOR MEASUREMENT', 'amber', null,
                'Site visit scheduled for ' . date('M d, Y g:i A', strtotime($r['measurement_datetime'])), $r['created_at']);
        }

        // 2. Site visits
        $designAdded = false;
        foreach ($data['sv'] as $sv) {
            if (($sv['visited'] ?? 'yes') === 'no') {
                $add('REVISIT NEEDED', 'amber', $sv['created_at'], 'Site was not visited');
            } else {
                $add('SITE VISIT DONE', 'green', $sv['created_at'], 'Measurement recorded');
                if (!$designAdded) {
                    $add('2D DESIGN', 'green', $sv['created_at'], 'Design started');
                    $designAdded = true;
                }
            }
        }
    }

    // 3. Initial submissions (bawat entry = isang draft/submission round)
    foreach ($data['initials'] as $idx => $q) {
        $add('2D & QUOTATION', 'green', $q['created_at'], $idx === 0 ? 'Preparing Initial 2D & Quotation' : 'Preparing revised files');

        if (!empty($q['submitted_at'])) {
            $note = 'Submitted for Designer Head approval';
            if (!empty($q['is_late'])) $note .= ' · Late submission';
            if (!empty($q['include_3d'])) $note .= ' · with 3D';
            $add('INITIAL FOR APPROVAL', 'blue', $q['submitted_at'], $note);
        }
        if (!empty($q['reviewed_at'])) {
            $fromCustomer = ($q['status'] === 'For Revision' && ($q['revision_source'] ?? 'Head') === 'Customer');
            if ($q['status'] === 'Approved' || $fromCustomer) {
                $add('INITIAL APPROVED', 'green', $q['reviewed_at'], 'Approved by Designer Head');
            } elseif ($q['status'] === 'For Revision') {
                $add('INITIAL REVISION', 'red', $q['reviewed_at'], 'Sent back by Designer Head');
            }
        }
    }

    // 4. Customer decisions — isang event kada round (carried-over na "Okay" ay hindi na uulitin)
    $rounds = [];
    $seen = [];
    foreach ($data['reviews'] as $c) {
        $k = $c['slot'] . '|' . $c['decision'] . '|' . $c['decided_at'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $rounds[$c['decided_at']][] = $c;
    }
    foreach ($rounds as $when => $items) {
        $revise = [];
        $okay = [];
        foreach ($items as $it) {
            if ($it['decision'] === 'Revise') $revise[] = $slotLabel[$it['slot']] ?? $it['slot'];
            else $okay[] = $slotLabel[$it['slot']] ?? $it['slot'];
        }
        if ($revise) {
            $note = 'Customer asked for changes: ' . implode(', ', $revise);
            if ($okay) $note .= ' · Okay: ' . implode(', ', $okay);
            $add('CUSTOMER REVISION', 'red', $when, $note);
        } else {
            $add('CUSTOMER APPROVED', 'green', $when, 'Customer approved: ' . implode(', ', $okay));
        }
    }

    // 5. Final — hindi sigurado ang lahat ng column, kaya chine-check muna kung meron
    foreach ($data['finals'] as $f) {
        if (!empty($f['created_at'])) $add('FINAL IN PROGRESS', 'green', $f['created_at'], 'Preparing Final 2D & Quotation');
        if (!empty($f['submitted_at'])) $add('FINAL FOR APPROVAL', 'blue', $f['submitted_at'], 'Submitted for Superadmin approval');
        if (!empty($f['reviewed_at'])) {
            if (($f['status'] ?? '') === 'Approved') $add('FINAL APPROVED', 'green', $f['reviewed_at'], 'Final 2D & Quotation approved');
            elseif (($f['status'] ?? '') === 'For Revision') $add('FINAL REVISION', 'red', $f['reviewed_at'], 'Final submission sent back for revision');
        }
    }

    // 6. Deposit (Accounting) → Notice to Proceed
    foreach ($data['initials'] as $q) {
        if (($q['deposit_status'] ?? '') === 'Notice to Proceed') {
            $amt = isset($q['deposit_amount']) && $q['deposit_amount'] !== null ? 'Deposit ₱' . number_format((float) $q['deposit_amount'], 2) : 'Deposit received';
            $addP('NOTICE TO PROCEED', $q['deposit_uploaded_at'] ?? null, $amt);
        }
    }

    // 7. Cutting list → BOM → Purchase Request → Transmittal → Delivery Receipt
    foreach ($data['cutting'] ?? [] as $c) {
        $addP('CUTTING LIST UPLOADED', $c['created_at'] ?? ($c['uploaded_at'] ?? null), $by($c['uploaded_by_name'] ?? ''));
        if ((int) ($c['qr_rejected'] ?? 0) === 1)
            $addP('CUTTING LIST REJECTED', $c['rejected_at'] ?? null, trim($by($c['rejected_by_name'] ?? '') . (!empty($c['rejection_remarks']) ? ' — “' . $c['rejection_remarks'] . '”' : '')));
        if ((int) ($c['qr_approved'] ?? 0) === 1)
            $addP('CUTTING LIST APPROVED', $c['approved_at'] ?? null, $by($c['approved_by_name'] ?? ''));
    }
    foreach ($data['boms'] ?? [] as $b) {
        $addP('BOM SUBMITTED', $b['created_at'], $by($b['requested_by']), $b['bom_number']);
        $addP('BOM APPROVED', $b['approved_at'], $by($b['approved_by']), $b['bom_number']);
        $addP('BOM RECEIVED', $b['received_at'], 'Received by Purchasing' . ($b['received_by'] ? ' · ' . $b['received_by'] : ''), $b['bom_number']);
    }
    foreach ($data['prs'] ?? [] as $p) {
        $addP('PR SUBMITTED', $p['created_at'], 'Purchase request ' . $by($p['prepared_by']), $p['req_no']);
        if (($p['status'] ?? '') === 'Rejected')
            $addP('PR RETURNED', $p['rejected_at'], !empty($p['rejection_remarks']) ? '“' . $p['rejection_remarks'] . '”' : 'Returned by the CEO', $p['req_no']);
        $addP('PR APPROVED', $p['approved_at'], $by($p['approved_by']), $p['req_no']);
        $addP('PR REVIEWED', $p['reviewed_at'], 'Accounting Head ' . $by($p['reviewed_by']), $p['req_no']);
        $addP('PR RECEIVED BY ACCOUNTING', $p['staff_received_at'], $by($p['staff_received_by']), $p['req_no']);
        $addP('PR RELEASED', $p['released_at'], $by($p['released_by']), $p['req_no']);
        $addP('PR PAID', $p['paid_at'], 'Paid stamp applied', $p['req_no']);
    }
    foreach ($data['trs'] ?? [] as $t) {
        $addP('TRANSMITTAL ISSUED', $t['created_at'], $by($t['issued_by']), $t['ref_no']);
        $addP('DELIVERED', $t['delivered_at'], 'Transmittal delivered' . ($t['delivery_mode'] ? ' via ' . $t['delivery_mode'] : ''), $t['ref_no']);
    }
    foreach ($data['drs'] ?? [] as $d) {
        $addP('DR CREATED', $d['created_at'], $by($d['prepared_by']), 'DR# ' . $d['dr_no']);
    }

    // 8. Operations (Project Coordinator) → Turnover
    // isang "cycle": submitted → (returned → resubmitted) → approved
    $cycle = function (array $L, ?string $created, ?string $submitted, ?string $returned, string $retNote, ?string $approved, string $okNote, string $ref, string $subNote, bool $skipFirst = false) use ($addP) {
        if (!$skipFirst) $addP($L[0], $created ?: $submitted, $subNote, $ref);
        if ($returned) {
            $addP($L[1], $returned, $retNote, $ref);
            if ($submitted && strtotime($submitted) > strtotime($returned)) $addP($L[0], $submitted, 'Resubmitted after changes', $ref);
        }
        if ($approved) $addP($L[2], $approved, $okNote, $ref);
    };
    $quoteNote = fn($rem) => !empty($rem) ? ' — “' . $rem . '”' : '';
    $peso = fn($v) => '₱' . number_format((float) $v, 2);

    foreach ($data['dr_ap'] ?? [] as $d) {
        if (empty($d['status'])) continue;
        $permit = $d['permit_required'] === null ? '' : ((int) $d['permit_required'] === 1 ? ' · Needs permit' : ' · No permit needed');
        $cycle(['DR FOR APPROVAL', 'DR RETURNED', 'DR APPROVED'], $d['created_at'], $d['submitted_at'], $d['returned_at'],
            trim($by($d['returned_by']) . $quoteNote($d['return_remarks'])), $d['approved_at'], 'Signed ' . $by($d['approved_by']),
            'DR# ' . $d['dr_no'], 'Sent to the Superadmin for signing' . $permit);
    }
    foreach ($data['foremen'] ?? [] as $f) {
        $addP('FOREMAN ASSIGNED', $f['created_at'], 'Foreman: ' . $f['name'], 'DR# ' . $f['dr_no']);
    }
    foreach ($data['lqs'] ?? [] as $l) {
        $cycle(['LABOR QUOTE SUBMITTED', 'LABOR QUOTE RETURNED', 'LABOR QUOTE APPROVED'], $l['created_at'], $l['submitted_at'], $l['returned_at'],
            trim($by($l['returned_by']) . $quoteNote($l['return_remarks'])), $l['approved_at'], 'Signed ' . $by($l['approved_by']) . ' · ' . $peso($l['total']),
            $l['quote_no'], ($l['subcontractor'] ? $l['subcontractor'] . ' · ' : '') . $peso($l['total']));
    }
    foreach ($data['timelines'] ?? [] as $tl) {
        $days = (strtotime($tl['date_finish']) - strtotime($tl['date_started'])) / 86400 + 1;
        $addP('TIMELINE SET', $tl['created_at'], date('M j, Y', strtotime($tl['date_started'])) . ' → ' . date('M j, Y', strtotime($tl['date_finish'])) . ' · ' . (int) $days . ' days · ' . (int) $tl['task_count'] . ' tasks', 'DR# ' . $tl['dr_no']);
    }
    $billedByDr = [];
    foreach ($data['billings'] ?? [] as $b) {
        $what = $b['billing_type'] === 'dp' ? 'Down payment' : 'Application for Payment ' . (int) $b['app_no'];
        $stage = ($b['returned_stage'] ?? '') === 'sa' ? 'Superadmin' : 'Accounting Head';
        $cycle(['BILLING SUBMITTED', 'BILLING RETURNED', 'BILLING NOTED'], $b['created_at'], $b['submitted_at'], $b['returned_at'],
            trim('by the ' . $stage . ($b['returned_by'] ? ' (' . $b['returned_by'] . ')' : '') . $quoteNote($b['return_remarks'])), $b['head_at'], 'Noted ' . $by($b['head_by']),
            $b['ref_no'], $what . ' · ' . $peso($b['this_period']));
        if ($b['status'] === 'approved') {
            $addP('BILLING APPROVED', $b['sa_at'], $what . ' · ' . $peso($b['this_period']) . ' · ' . $by($b['sa_by']), $b['ref_no']);
            $k = (int) $b['dr_id'];
            $billedByDr[$k]['sum'] = ($billedByDr[$k]['sum'] ?? 0) + (float) $b['this_period'];
            $billedByDr[$k]['contract'] = (float) $b['contract_amount'] + (float) $b['change_orders'];
            $billedByDr[$k]['at'] = $b['sa_at'];
            $billedByDr[$k]['dr'] = $b['dr_no'];
        }
    }
    foreach ($billedByDr as $bd) {
        if ($bd['contract'] > 0 && $bd['sum'] >= $bd['contract'] - 0.004) $addP('FULLY BILLED', $bd['at'], $peso($bd['sum']) . ' billed and approved', 'DR# ' . $bd['dr']);
    }
    foreach ($data['punch'] ?? [] as $pu) {
        $addP('PUNCH LIST OPEN', $pu['first_at'], (int) $pu['n'] . ' item(s) found during inspection', 'DR# ' . $pu['dr_no']);
        if ((int) $pu['n'] > 0 && (int) $pu['v'] === (int) $pu['n']) $addP('PUNCH LIST CLOSED', $pu['last_verified'], 'All ' . (int) $pu['n'] . ' item(s) verified', 'DR# ' . $pu['dr_no']);
    }
    foreach ($data['turnovers'] ?? [] as $tv) {
        $cycle(['TURNOVER SUBMITTED', 'TURNOVER RETURNED', 'TURNOVER SIGNED'], $tv['created_at'], $tv['submitted_at'], $tv['returned_at'],
            trim($by($tv['returned_by']) . $quoteNote($tv['return_remarks'])), $tv['sa_at'], 'Signed ' . $by($tv['sa_by']), 'DR# ' . $tv['dr_no'], 'Turnover letter sent for signing');
        $addP('TURNED OVER', $tv['closed_at'], 'Accepted by ' . $tv['client_name'] . ($tv['accepted_date'] ? ' on ' . date('M j, Y', strtotime($tv['accepted_date'])) : '') . ' · project closed', 'DR# ' . $tv['dr_no']);
    }

    usort($ev, fn($a, $b) => [$a['_sort'], $a['_seq']] <=> [$b['_sort'], $b['_seq']]);

    // Maraming BOM: "TURNED OVER" lang kapag tapos LAHAT. Kung may natitira pa, "PARTLY TURNED OVER" ang kasalukuyan.
    $lineCount = count(array_filter($data['boms'] ?? [], fn($b) => !empty($b['approved_at'])));
    $closedCount = count(array_filter($data['turnovers'] ?? [], fn($t) => ($t['status'] ?? '') === 'closed'));
    if ($ev && end($ev)['label'] === 'TURNED OVER' && $lineCount > $closedCount) {
        $add('PARTLY TURNED OVER', 'amber', null, $closedCount . ' of ' . $lineCount . ' BOMs turned over · the rest are still in progress', null);
        $ev[count($ev) - 1]['_sort'] = time() + 1;   // ngayon (pagkatapos ng lahat ng event)
    }

    // Kasalukuyang stage: kung iba sa huling event, idagdag bilang "ngayon".
    // Kapag nasa production / purchasing na, ang huling event doon ang kasalukuyan.
    [$curLabel, $curColor, $curNote] = monStage($r);
    $last = count($ev) - 1;
    $inPipeline = $last >= 0 && in_array($ev[$last]['label'], MON_PIPELINE, true);
    if (!$inPipeline && ($last < 0 || $ev[$last]['label'] !== $curLabel)) {
        $ev[] = ['label' => $curLabel, 'color' => $curColor, 'time' => null, 'note' => $curNote, '_sort' => PHP_INT_MAX, '_seq' => $seq];
        $last++;
    }
    foreach ($ev as $i => &$e) {
        $e['current'] = ($i === $last);
        // ts = unix time ng event (para sa date filter); "ngayon" ang current na walang oras
        $e['ts'] = $e['_sort'] === PHP_INT_MAX ? time() : ($e['_sort'] ?: null);
        $e['group'] = in_array($e['label'], MON_OPS, true) ? 'ops' : (in_array($e['label'], MON_PIPELINE, true) ? 'pipeline' : 'crm');
        unset($e['_sort'], $e['_seq']);
    }
    unset($e);

    return $ev;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'filter_options') {
    $projects = array_column($conn->query("
        SELECT DISTINCT project_type FROM noblecrminquiry
        WHERE project_type IS NOT NULL AND project_type <> '' ORDER BY project_type
    ")->fetch_all(MYSQLI_ASSOC), 'project_type');

    $designers = $conn->query("
        SELECT DISTINCT r.id, r.name FROM noblecrminquiry i
        JOIN noblerole r ON r.id = i.designer_id ORDER BY r.name
    ")->fetch_all(MYSQLI_ASSOC);

    $sales = $conn->query("
        SELECT DISTINCT r.id, r.name FROM noblecrminquiry i
        JOIN noblerole r ON r.id = i.sales_staff_id ORDER BY r.name
    ")->fetch_all(MYSQLI_ASSOC);

    monRespond(true, '', [
        'projects'  => $projects,
        'designers' => $designers,
        'sales'     => $sales,
        'statuses'  => MON_STATUSES,
    ]);
}

if ($action === 'list') {
    $q        = trim($_GET['q'] ?? '');
    $project  = trim($_GET['project'] ?? '');
    $salesId  = intval($_GET['sales'] ?? 0);
    $designer = trim($_GET['designer'] ?? '');          // '' | 'none' | id
    $mode     = trim($_GET['mode'] ?? '');
    $cstatus  = trim($_GET['client_status'] ?? '');     // confirmed | tentative | no | none
    $from     = trim($_GET['from'] ?? '');              // date filed
    $to       = trim($_GET['to'] ?? '');
    $sort     = $_GET['sort'] ?? 'newest';

    // Status-history filters
    $statusSel = array_values(array_intersect(
        array_filter(explode(',', $_GET['status'] ?? '')),
        array_keys(MON_STATUSES)
    ));
    $matchAll  = ($_GET['status_match'] ?? 'any') === 'all';
    $current   = trim($_GET['current'] ?? '');
    $sFromRaw  = trim($_GET['status_from'] ?? '');
    $sToRaw    = trim($_GET['status_to'] ?? '');
    $sFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sFromRaw) ? strtotime($sFromRaw . ' 00:00:00') : null;
    $sTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sToRaw) ? strtotime($sToRaw . ' 23:59:59') : null;

    $where = [];
    $types = '';
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(i.control_no LIKE ? OR i.client_name LIKE ? OR i.contact_number LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    if ($project !== '') {
        $where[] = 'i.project_type = ?';
        $types .= 's';
        $params[] = $project;
    }
    if ($salesId > 0) {
        $where[] = 'i.sales_staff_id = ?';
        $types .= 'i';
        $params[] = $salesId;
    }
    if ($designer === 'none') {
        $where[] = '(i.designer_id IS NULL OR i.designer_id = 0)';
    } elseif (ctype_digit($designer) && (int) $designer > 0) {
        $where[] = 'i.designer_id = ?';
        $types .= 'i';
        $params[] = (int) $designer;
    }
    if (in_array($mode, ['site_visit', 'ready_for_quotation'], true)) {
        $where[] = "COALESCE(i.mode, 'site_visit') = ?";
        $types .= 's';
        $params[] = $mode;
    }
    if (in_array($cstatus, ['confirmed', 'tentative', 'no'], true)) {
        $where[] = 'i.client_status = ?';
        $types .= 's';
        $params[] = $cstatus;
    } elseif ($cstatus === 'none') {
        $where[] = "(i.client_status IS NULL OR i.client_status = '')";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = 'DATE(i.created_at) >= ?';
        $types .= 's';
        $params[] = $from;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = 'DATE(i.created_at) <= ?';
        $types .= 's';
        $params[] = $to;
    }

    $orderBy = [
        'newest'  => 'i.created_at DESC, i.id DESC',
        'oldest'  => 'i.created_at ASC, i.id ASC',
        'client'  => 'i.client_name ASC',
        'control' => 'i.control_no ASC',
    ][$sort] ?? 'i.created_at DESC, i.id DESC';

    $sql = monBaseSql() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $orderBy;
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // History ng bawat inquiry (bulk) → dito ang status filters at counts
    $hist = monHistoryData($conn, array_column($all, 'id'));
    $hasDateRange = ($sFrom !== null || $sTo !== null);

    $statusCounts = array_fill_keys(array_keys(MON_STATUSES), 0);
    $rows = [];

    foreach ($all as $r) {
        $events = monBuildHistory($conn, $r, $hist[(int) $r['id']]);
        $curEvent = $events[count($events) - 1];
        $curLabel = $curEvent['label'];

        // Current status filter
        if ($current !== '' && $curLabel !== $current) {
            continue;
        }

        // Mga status na dinaanan (sa loob ng status date range, kung meron)
        $reached = [];
        foreach ($events as $e) {
            if ($hasDateRange) {
                $ts = $e['ts'];
                if ($ts === null) continue;
                if ($sFrom !== null && $ts < $sFrom) continue;
                if ($sTo !== null && $ts > $sTo) continue;
            }
            $reached[$e['label']] = true;
        }

        // Counts para sa chips (hindi kasama ang mismong status selection)
        foreach (array_keys($reached) as $l) {
            if (isset($statusCounts[$l])) $statusCounts[$l]++;
        }

        // Passed-through filter
        if ($statusSel) {
            $hits = 0;
            foreach ($statusSel as $l) {
                if (isset($reached[$l])) $hits++;
            }
            if ($matchAll ? $hits < count($statusSel) : $hits === 0) {
                continue;
            }
        } elseif ($hasDateRange && !$reached) {
            continue; // walang status change sa range
        }

        $rows[] = [
            'id'             => (int) $r['id'],
            'control_no'     => $r['control_no'],
            'client_name'    => $r['client_name'],
            'contact_number' => $r['contact_number'],
            'project_type'   => $r['project_type'],
            'sales_name'     => $r['sales_name'] ?? '—',
            'designer_name'  => $r['designer_name'] ?? '—',
            'created_at'     => $r['created_at'],
            'stage'          => $curLabel,
            'stage_color'    => $curEvent['color'],
        ];
    }

    monRespond(true, '', [
        'rows'          => $rows,
        'count'         => count($rows),
        'total'         => count($all),
        'status_counts' => $statusCounts,
    ]);
}

if ($action === 'status_detail') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        monRespond(false, 'Invalid inquiry.');
    }

    $r = monLoadRow($conn, $id);
    if (!$r) {
        monRespond(false, 'Inquiry not found.');
    }

    [$label, $color, $note, $next] = monStage($r);

    // Kapag nasa Cutting list → Delivery na, iyon ang ipapakita
    $hist = monBuildHistory($conn, $r);
    $cur = $hist[count($hist) - 1] ?? null;
    if ($cur && in_array($cur['group'] ?? '', ['pipeline', 'ops'], true)) {
        $label = $cur['label'];
        $color = $cur['color'];
        [$note, $next] = MON_PIPELINE_NEXT[$label] ?? [$cur['note'], '—'];
        if (!empty($cur['ref'])) $note .= ' (' . $cur['ref'] . ')';
    }

    monRespond(true, '', ['item' => [
        'id'          => (int) $r['id'],
        'control_no'  => $r['control_no'],
        'client_name' => $r['client_name'],
        'stage'       => $label,
        'color'       => $color,
        'note'        => $note,
        'next'        => $next,
        'designer'    => $r['designer_name'] ?: null,
        'started'     => $r['created_at'],
        'site_visit'  => ($r['client_status'] === 'confirmed') ? $r['measurement_datetime'] : null,
    ]]);
}

if ($action === 'status_history') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        monRespond(false, 'Invalid inquiry.');
    }

    $r = monLoadRow($conn, $id);
    if (!$r) {
        monRespond(false, 'Inquiry not found.');
    }

    monRespond(true, '', [
        'control_no' => $r['control_no'],
        'events'     => monBuildHistory($conn, $r),
    ]);
}

monRespond(false, 'Unknown action.');