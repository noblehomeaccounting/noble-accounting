<?php
// index-custodian-fetch-received.php
// Gamit: /fetchreceived?month=YYYY-MM[&mine=1][&_=timestamp]

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

$user_id = intval($_SESSION['account_id'] ?? 0);
session_write_close(); // bitawan ang session lock

if (!ob_start('ob_gzhandler')) {
    ob_start();
}
header('Content-Type: application/json');

function sendJson(string $json): void
{
    $etag = '"' . md5($json) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, no-cache');
    if (str_contains($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', $etag)) {
        http_response_code(304);
        return;
    }
    echo $json;
}

// ── Filters ────────────────────────────────────────────────
$where = 'b.received_by IS NOT NULL';
$month = $_GET['month'] ?? '';
$mine  = ($_GET['mine'] ?? '') === '1';   // custodian assistant view
$ttl   = 30;

if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $start  = $month . '-01'; // ligtas, regex-validated
    $where .= " AND b.date_requested >= '$start'
                AND b.date_requested < DATE_ADD('$start', INTERVAL 1 MONTH)";
    if ($month < date('Y-m')) $ttl = 300; // lumang buwan, bihira nang magbago
} else {
    $month = 'all';
}

if ($mine) {
    $where .= " AND (v.status = 'ready_to_release'
                 OR (v.status = 'released' AND v.released_by = $user_id))";
}

$cacheKey = "custodian_received_{$month}_" . ($mine ? "mine_$user_id" : 'all');

// ?_= (force refresh after submit/release) → laktawan ang cache
$force = isset($_GET['_']);
if (!$force) {
    $cached = getCache($cacheKey, $ttl);
    if ($cached !== false) {
        sendJson($cached);
        exit;
    }
}

$result = $conn->query("SELECT b.*, 
    b.control_no as budget_control_no,
    b.request_category,
    b.request_reference,
    b.received_by as budget_received_by,
    b.received_at as budget_received_at,
    n.name as requestor_name,
    n.email as sender_email,
    v.id as voucher_id,
    v.control_no as voucher_control_no,
    v.manual_receiver_name,
    v.manual_receiver_date,
    v.payee as voucher_payee,
    v.address as voucher_address,
    v.status as voucher_status,
    v.prepared_by,
    v.prepared_at,
    v.approved_by,
    v.approved_at,
    v.released_by,
    v.released_at,
    v.received_by,
    v.received_at,
    v.purpose as voucher_purpose,
    v.title as voucher_title,
    v.second_no as voucher_second_no,
    v.payment_method as voucher_payment_method,
    prep.name as prepared_name,
    appr.name as approver_name,
    rel.name as released_name,
    recv.name as receiver_name,
    v.prepared_signature,
    v.approved_signature as approver_signature,
    v.released_signature as released_signature
    FROM noblebudgetrequest b
    LEFT JOIN nobleaccount n ON b.user_id = n.id
    LEFT JOIN noblevoucher v ON b.id = v.request_id
    LEFT JOIN noblerole prep ON v.prepared_by = prep.id
    LEFT JOIN noblerole appr ON v.approved_by = appr.id
    LEFT JOIN noblerole rel ON v.released_by = rel.id
    LEFT JOIN nobleaccount recv ON v.received_by = recv.id
    WHERE $where
    ORDER BY b.received_at DESC");

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true);
    $data[] = $row;
}

$json = json_encode($data);
setCache($cacheKey, $json);
sendJson($json);