<?php
// index-fetch-staff.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$cached = getCache('accounting_staff_list', 20); // 1 minute
if ($cached !== false) {
    echo $cached;
    exit;
}

$result = $conn->query("
    SELECT r.id, n.name, n.email,
    sig.path as signature
    FROM noblerole r
    LEFT JOIN nobleaccount n ON r.account_id = n.id
    LEFT JOIN noblesignature sig ON sig.user_id = r.account_id AND sig.is_active = 1
    WHERE r.role = 'ACCOUNTING AND FINANCE DEPARTMENT'
    AND r.position = 'staff'
");

$staff = [];
while ($row = $result->fetch_assoc()) {
    $staff[] = $row;
}

$json = json_encode($staff);
setCache('accounting_staff_list', $json);

echo $json;