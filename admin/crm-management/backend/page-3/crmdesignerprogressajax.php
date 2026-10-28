<?php
// crmdesignerprogressajax.php
// Para sa Assigned Inquiries (site.php) ng Designer: nasaang bahagi na ang bawat inquiry
// (Site visit → Initial → Customer review → Final → Cutting), para makita agad sa listahan.
// Parehong lohika ng monStage() sa monitoringcrmajax.php.
//
//   GET ?action=summary  →  { success, items: { "<inquiry_id>": { phase, phase_label, status, tone, note, next } } }

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function dprRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// phase: 1 Site visit · 2 Initial · 3 Customer review · 4 Final · 5 Cutting
// tone: amber (ginagawa pa / hinihintay), blue (nasa iba ang bola), red (may babaguhin), green (tapos)
function dprStage(array $r): array
{
    $initial = $r['initial_status'] ?? null;
    $final = $r['final_status'] ?? null;
    $mk = fn($phase, $status, $tone, $note, $next) => [
        'phase' => $phase,
        'phase_label' => [1 => 'Site visit', 2 => 'Initial', 3 => 'Customer review', 4 => 'Final', 5 => 'Cutting'][$phase],
        'status' => $status,
        'tone' => $tone,
        'note' => $note,
        'next' => $next,
    ];

    // ── Final ──
    if ($final !== null) {
        switch ($final) {
            case 'Approved':
                return $mk(5, 'Final approved', 'green', 'Final 2D & Quotation approved — now with Cutting.', 'Cutting');
            case 'Waiting for Approval':
                return $mk(4, 'For approval', 'blue', 'Final submission is waiting for Superadmin approval.', 'Final approval');
            case 'For Revision':
                return $mk(4, 'Revision', 'red', 'Final submission needs revision.', 'Re-submit Final');
            default:
                $noContract = empty($r['contract_amount']) || (float) $r['contract_amount'] <= 0 || empty($r['contract_file']);
                return $mk(4, 'In progress', 'amber', $noContract ? 'Preparing Final · contract not yet set by Sales.' : 'Preparing Final 2D & Quotation.', 'Final submission');
        }
    }

    // ── Initial ──
    if ($initial !== null) {
        switch ($initial) {
            case 'Approved':
                $include3d = (int) ($r['include_3d'] ?? 0) === 1;
                $stage3d = $r['initial_3d_stage'] ?? 'Locked';
                if (!$include3d && $stage3d !== 'Approved') {
                    if ($stage3d === 'Waiting for Approval') {
                        return $mk(2, '3D for approval', 'blue', '2D & Quotation approved · 3D waiting for Designer Head.', '3D approval');
                    }
                    if ($stage3d === 'For Revision') {
                        return $mk(2, '3D revision', 'red', 'The 3D file needs revision.', 'Re-submit 3D');
                    }
                    return $mk(2, 'Waiting for 3D', 'amber', '2D & Quotation approved · upload the 3D file.', '3D submission');
                }
                if ((int) ($r['customer_ok'] ?? 0) >= 3) {
                    return $mk(4, 'Ready for Final', 'amber', 'Customer approved all Initial files. Start the Final.', 'Final submission');
                }
                return $mk(3, 'With customer', 'blue', 'Approved by Designer Head · waiting for the customer\'s decision.', 'Customer decision');
            case 'Waiting for Approval':
                return $mk(2, 'For approval', 'blue', 'Initial is waiting for Designer Head approval.', 'Initial approval');
            case 'For Revision':
                if (($r['revision_source'] ?? 'Head') === 'Customer') {
                    return $mk(3, 'Customer revision', 'red', 'The customer asked for changes on the Initial files.', 'Re-submit Initial');
                }
                return $mk(2, 'Revision', 'red', 'Designer Head returned the Initial for revision.', 'Re-submit Initial');
            default:
                return $mk(2, 'Draft', 'amber', 'Preparing the Initial 2D & Quotation.', 'Initial submission');
        }
    }

    // ── Wala pang 2D & Quotation ──
    if (($r['mode'] ?? 'site_visit') === 'ready_for_quotation') {
        return $mk(2, 'Not started', 'amber', 'No site visit needed · prepare the Initial 2D & Quotation.', 'Initial submission');
    }
    if ((int) ($r['sv_count'] ?? 0) === 0) {
        return $mk(1, 'For measurement', 'amber', 'Do the site visit and record the measurements.', 'Site visit record');
    }
    if (($r['sv_visited'] ?? 'yes') === 'no') {
        return $mk(1, 'Revisit needed', 'red', 'The site was not visited — schedule a revisit.', 'Site visit record');
    }
    $progress = (string) ($r['design_progress'] ?? '0');
    return $mk(2, 'Not started', 'amber', 'Site visit done · design progress ' . $progress . '%.', 'Initial submission');
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
if (($_GET['action'] ?? '') !== 'summary') {
    dprRespond(false, 'Invalid action.');
}
if ($currentUserId <= 0) {
    dprRespond(false, 'Session expired. Please log in again.');
}

$stmt = $conn->prepare("
    SELECT i.id, i.mode, i.design_progress, i.contract_amount, i.contract_file,
           q.status AS initial_status, q.include_3d, q.design_3d_stage AS initial_3d_stage, q.revision_source,
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
    WHERE i.designer_id = ?
");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($r = $res->fetch_assoc()) {
    $items[(string) $r['id']] = dprStage($r);
}
$stmt->close();

dprRespond(true, '', ['items' => $items ?: new stdClass()]);