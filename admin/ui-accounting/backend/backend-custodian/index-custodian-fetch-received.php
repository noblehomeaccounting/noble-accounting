<?php
// index-custodian-fetch-received.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$cached = getCache('custodian_received_requests', 30);
if ($cached !== false) {
    echo $cached;
    exit;
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
    WHERE b.received_by IS NOT NULL
    ORDER BY b.received_at DESC");

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true);
    $data[] = $row;
}

$json = json_encode($data);
setCache('custodian_received_requests', $json);
echo $json;