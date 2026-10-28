<?php
// crmdesignerfeedbackajax.php


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

function dfbRespond(bool $success, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function dfbFmt(?string $dt): ?string
{
    return !empty($dt) ? date('M d, Y g:i A', strtotime($dt)) : null;
}

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action !== 'summary') {
    dfbRespond(false, 'Invalid action.');
}
if ($currentUserId <= 0) {
    dfbRespond(false, 'Session expired. Please log in again.');
}

// ── 1. Pinakabagong Initial entry ng bawat inquiry na naka-assign sa designer ──
$stmt = $conn->prepare("
    SELECT q.id, q.inquiry_id, q.status, q.revision_source, q.reviewed_at, q.created_at,
           q.design_2d_review_status, q.design_2d_remarks,
           q.quotation_review_status, q.quotation_remarks,
           q.include_3d, q.design_3d_review_status, q.design_3d_remarks, q.design_3d_stage
    FROM noblecrm_2dquotation q
    INNER JOIN noblecrminquiry i ON i.id = q.inquiry_id
    WHERE i.designer_id = ? AND q.stage = 'Initial'
    ORDER BY q.inquiry_id ASC, q.created_at DESC, q.id DESC
");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$res = $stmt->get_result();

$latest = [];  // inquiry_id => row
while ($row = $res->fetch_assoc()) {
    $iid = (int) $row['inquiry_id'];
    if (!isset($latest[$iid])) {
        $latest[$iid] = $row;   // una = pinakabago (dahil sa ORDER BY)
    }
}
$stmt->close();

if (!$latest) {
    dfbRespond(true, '', ['items' => new stdClass()]);
}

// ── 2. Bukas na feedback ng Cutting para sa mga entry na iyon ──
$qidToInquiry = [];
foreach ($latest as $iid => $row) {
    $qidToInquiry[(int) $row['id']] = $iid;
}
$in = implode(',', array_map('intval', array_keys($qidToInquiry)));

$cutting = [];  // inquiry_id => [messages...]
$fbRes = $conn->query("
    SELECT f.id, f.quotation_id, f.message, f.created_at, r.name AS created_by_name
    FROM noblecrm_2d_feedback f
    LEFT JOIN noblerole r ON r.id = f.created_by
    WHERE f.quotation_id IN ({$in}) AND f.is_resolved = 0
    ORDER BY f.id DESC
");
while ($f = $fbRes->fetch_assoc()) {
    $iid = $qidToInquiry[(int) $f['quotation_id']] ?? null;
    if ($iid === null) continue;
    $cutting[$iid][] = [
        'id' => (int) $f['id'],
        'message' => (string) $f['message'],
        'by' => $f['created_by_name'] ?: 'Cutting',
        'at' => dfbFmt($f['created_at']),
        'at_raw' => $f['created_at'],
    ];
}

// ── 3. Buuin ang sagot ──
$slotLabels = ['design_2d_' => '2D Design', 'quotation_' => 'Quotation', 'design_3d_' => '3D Design'];
$items = [];

foreach ($latest as $iid => $q) {
    $entries = [];

    // Cutting
    if (!empty($cutting[$iid])) {
        $list = $cutting[$iid];
        $entries[] = [
            'kind' => 'cutting',
            'label' => 'Cutting feedback',
            'count' => count($list),
            'at_raw' => $list[0]['at_raw'],
            'at' => $list[0]['at'],
            'messages' => array_slice($list, 0, 5),
        ];
    }

    // Designer Head / Customer revision (buong entry ibinalik)
    if ($q['status'] === 'For Revision') {
        $source = ($q['revision_source'] ?? '') === 'Customer' ? 'customer' : 'head';
        $slots = [];
        foreach ($slotLabels as $p => $label) {
            if ($p === 'design_3d_' && (int) ($q['include_3d'] ?? 0) !== 1) continue;
            if (($q[$p . 'review_status'] ?? '') === 'For Revision') {
                $slots[] = ['label' => $label, 'remarks' => (string) ($q[$p . 'remarks'] ?? '')];
            }
        }
        $entries[] = [
            'kind' => $source,
            'label' => $source === 'customer' ? 'Customer asked for revision' : 'Returned by Designer Head',
            'count' => max(1, count($slots)),
            'at_raw' => $q['reviewed_at'] ?: $q['created_at'],
            'at' => dfbFmt($q['reviewed_at'] ?: $q['created_at']),
            'slots' => $slots,
        ];
    } elseif (($q['design_3d_stage'] ?? '') === 'For Revision') {
        // Standalone 3D lang ang ibinalik
        $entries[] = [
            'kind' => '3d',
            'label' => '3D design needs revision',
            'count' => 1,
            'at_raw' => $q['reviewed_at'] ?: $q['created_at'],
            'at' => dfbFmt($q['reviewed_at'] ?: $q['created_at']),
            'slots' => [['label' => '3D Design', 'remarks' => (string) ($q['design_3d_remarks'] ?? '')]],
        ];
    }

    if ($entries) {
        $items[(string) $iid] = [
            'total' => array_sum(array_column($entries, 'count')),
            'entries' => $entries,
        ];
    }
}

dfbRespond(true, '', ['items' => $items ?: new stdClass()]);