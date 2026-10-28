<?php
// crmlistprogressajax.php


include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function slpRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// phase: 1 Schedule · 2 Site visit · 3 Initial 2D · 4 Customer · 5 Final & Contract · 6 Cutting
// tone:  amber = si Sales ang may gagawin · blue = nasa iba (designer / head / superadmin)
//        red   = may babaguhin · green = tapos · gray = huminto
function slpStage(array $r): array
{
    $mk = fn($phase, $status, $tone, $note, $contract = 'later') => [
        'phase' => $phase,
        'phase_label' => [0 => 'Stopped', 1 => 'Schedule', 2 => 'Site visit', 3 => 'Initial 2D', 4 => 'Customer', 5 => 'Final & Contract', 6 => 'Cutting'][$phase],
        'status' => $status,
        'tone' => $tone,
        'note' => $note,
        'contract' => $contract,
    ];
    $hasContract = (float) ($r['contract_amount'] ?? 0) > 0 && trim((string) ($r['contract_file'] ?? '')) !== '';
    $missing = [];
    if ((float) ($r['contract_amount'] ?? 0) <= 0) $missing[] = 'amount';
    if (trim((string) ($r['contract_file'] ?? '')) === '') $missing[] = 'file';
    $missingTxt = implode(' and ', $missing);

    if (($r['client_status'] ?? '') === 'no') {
        return $mk(0, 'Not proceeding', 'gray', 'The client is not proceeding.');
    }

    $final = $r['final_status'] ?? null;
    $initial = $r['initial_status'] ?? null;

    // ── Final ──
    if ($final !== null) {
        switch ($final) {
            case 'Approved':
                return $mk(6, 'Final approved', 'green', 'Final 2D & Quotation approved — now with Cutting.', 'locked');
            case 'Waiting for Approval':
                return $mk(5, 'Final for approval', 'blue', 'The Final was submitted and is waiting for Superadmin approval. The contract is locked.', 'locked');
            case 'For Revision':
                return $hasContract
                    ? $mk(5, 'Final revision', 'red', 'The Final was sent back for revision. Check the contract while the designer fixes the files.', 'set')
                    : $mk(5, 'Final revision', 'red', "The Final was sent back. Add the contract {$missingTxt} in Final 2D & Quotation.", 'needed');
            default:
                return $hasContract
                    ? $mk(5, 'Preparing Final', 'blue', 'Contract is set. The designer is preparing the Final files.', 'set')
                    : $mk(5, 'Set the contract', 'amber', "Add the contract {$missingTxt} in Final 2D & Quotation.", 'needed');
        }
    }

    // ── Initial ──
    if ($initial !== null) {
        switch ($initial) {
            case 'Approved':
                $include3d = (int) ($r['include_3d'] ?? 0) === 1;
                $stage3d = $r['initial_3d_stage'] ?? 'Locked';
                if (!$include3d && $stage3d !== 'Approved') {
                    if ($stage3d === 'Waiting for Approval') return $mk(3, '3D for approval', 'blue', '2D & Quotation approved · 3D waiting for the Designer Head.');
                    if ($stage3d === 'For Revision') return $mk(3, '3D revision', 'red', 'The designer is revising the 3D file.');
                    return $mk(3, 'Waiting for 3D', 'blue', '2D & Quotation approved · the designer is preparing the 3D.');
                }
                if ((int) ($r['customer_ok'] ?? 0) >= 3) {
                    return $hasContract
                        ? $mk(5, 'Ready for Final', 'blue', 'The customer approved all Initial files. Contract is set — the Final can be prepared.', 'set')
                        : $mk(5, 'Set the contract', 'amber', "The customer approved all Initial files. Add the contract {$missingTxt} in Final 2D & Quotation.", 'needed');
                }
                return $mk(4, 'With customer', 'amber', 'Approved by the Designer Head. Present it to the customer and record their decision.');
            case 'Waiting for Approval':
                return $mk(3, 'For approval', 'blue', 'The Initial 2D & Quotation is waiting for Designer Head approval.');
            case 'For Revision':
                if (($r['revision_source'] ?? 'Head') === 'Customer') {
                    return $mk(4, 'Customer revision', 'red', 'The customer asked for changes · the designer is revising.');
                }
                return $mk(3, 'Revision', 'red', 'The Designer Head returned the Initial · the designer is revising.');
            default:
                return $mk(3, 'Draft', 'blue', 'The designer is preparing the Initial 2D & Quotation.');
        }
    }

    // ── Wala pang 2D & Quotation ──
    if ((int) ($r['designer_id'] ?? 0) <= 0) {
        if (($r['client_status'] ?? '') === 'tentative') {
            return $mk(1, 'Tentative', 'amber', 'The client is tentative. Follow up, then schedule and assign a designer.');
        }
        return $mk(1, 'To schedule', 'amber', 'Call the client, then set the schedule and assign a designer.');
    }
    if (($r['mode'] ?? 'site_visit') === 'ready_for_quotation') {
        return $mk(3, 'Not started', 'blue', 'No site visit needed · the designer will prepare the Initial 2D & Quotation.');
    }
    if ((int) ($r['sv_count'] ?? 0) === 0) {
        return $mk(2, 'For site visit', 'blue', 'Waiting for the designer\'s site visit and measurements.');
    }
    if (($r['sv_visited'] ?? 'yes') === 'no') {
        return $mk(2, 'Revisit needed', 'red', 'The site was not visited — set a new schedule with the client.');
    }
    return $mk(3, 'Not started', 'blue', 'Site visit done · the designer is starting the Initial 2D & Quotation.');
}

if (($_GET['action'] ?? '') !== 'summary') {
    slpRespond(false, 'Invalid action.');
}
if (intval($_SESSION['account_id'] ?? 0) <= 0) {
    slpRespond(false, 'Session expired. Please log in again.');
}

// Mga inquiry na nasa listahan ng page (max 500)
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))), fn($v) => $v > 0)));
$ids = array_slice($ids, 0, 500);
if (!$ids) {
    slpRespond(true, '', ['items' => new stdClass()]);
}
$in = implode(',', $ids); // puro integer na

$res = $conn->query("
    SELECT i.id, i.mode, i.designer_id, i.client_status, i.contract_amount, i.contract_file,
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
    WHERE i.id IN ($in)
");

$items = [];
while ($r = $res->fetch_assoc()) {
    $items[(string) $r['id']] = slpStage($r);
}

slpRespond(true, '', ['items' => $items ?: new stdClass()]);