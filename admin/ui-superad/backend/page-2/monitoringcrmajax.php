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
        $out[(int) $id] = ['sv' => [], 'initials' => [], 'reviews' => [], 'finals' => []];
    }
    if (!$out) {
        return $out;
    }
    $in = implode(',', array_map('intval', array_keys($out)));

    $queries = [
        'sv'       => "SELECT inquiry_id, created_at, visited FROM noblecrm_sitevisit WHERE inquiry_id IN ($in) ORDER BY created_at ASC, id ASC",
        'initials' => "SELECT * FROM noblecrm_2dquotation WHERE inquiry_id IN ($in) AND stage = 'Initial' ORDER BY id ASC",
        'reviews'  => "SELECT inquiry_id, slot, decision, decided_at FROM noblecrm_2dquotation_customer_review WHERE inquiry_id IN ($in) ORDER BY id ASC",
        'finals'   => "SELECT * FROM noblecrm_2dquotation_final WHERE inquiry_id IN ($in) ORDER BY id ASC",
    ];
    foreach ($queries as $key => $sql) {
        $res = $conn->query($sql);
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
    $add = function (string $label, string $color, ?string $time, string $note = '', ?string $sort = null) use (&$ev, &$seq) {
        $key = $sort ?? $time;
        $ev[] = [
            'label' => $label, 'color' => $color, 'time' => $time, 'note' => $note,
            '_sort' => $key ? (strtotime($key) ?: 0) : 0, '_seq' => $seq++,
        ];
    };
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

    usort($ev, fn($a, $b) => [$a['_sort'], $a['_seq']] <=> [$b['_sort'], $b['_seq']]);

    // Kasalukuyang stage: kung iba sa huling event, idagdag bilang "ngayon".
    [$curLabel, $curColor, $curNote] = monStage($r);
    $last = count($ev) - 1;
    if ($last < 0 || $ev[$last]['label'] !== $curLabel) {
        $ev[] = ['label' => $curLabel, 'color' => $curColor, 'time' => null, 'note' => $curNote, '_sort' => PHP_INT_MAX, '_seq' => $seq];
        $last++;
    }
    foreach ($ev as $i => &$e) {
        $e['current'] = ($i === $last);
        // ts = unix time ng event (para sa date filter); "ngayon" ang current na walang oras
        $e['ts'] = $e['_sort'] === PHP_INT_MAX ? time() : ($e['_sort'] ?: null);
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