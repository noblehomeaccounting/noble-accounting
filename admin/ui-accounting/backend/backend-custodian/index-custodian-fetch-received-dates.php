<?php
// index-custodian-fetch-received-dates.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

$user_id = intval($_SESSION['account_id'] ?? 0);
session_write_close();

if (!ob_start('ob_gzhandler')) {
    ob_start();
}
header('Content-Type: application/json');

$mine  = ($_GET['mine'] ?? '') === '1';
$where = 'b.received_by IS NOT NULL AND b.date_requested IS NOT NULL';
if ($mine) {
    $where .= " AND (v.status = 'ready_to_release'
                 OR (v.status = 'released' AND v.released_by = $user_id))";
}

$cacheKey = 'custodian_received_dates_' . ($mine ? "mine_$user_id" : 'all');
if (!isset($_GET['_'])) {
    $cached = getCache($cacheKey, 30);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

$result = $conn->query("
    SELECT DATE(b.date_requested) AS d, COALESCE(v.status, 'none') AS s, COUNT(*) AS c
    FROM noblebudgetrequest b
    LEFT JOIN noblevoucher v ON b.id = v.request_id
    WHERE $where
    GROUP BY d, s
    ORDER BY d DESC
");

$byDate = [];
while ($row = $result->fetch_assoc()) {
    $d = $row['d'];
    if (!isset($byDate[$d])) {
        $byDate[$d] = ['date' => $d, 'voucher_approval' => 0, 'ready_to_release' => 0,
                       'released' => 0, 'none' => 0, 'total' => 0];
    }
    if (isset($byDate[$d][$row['s']])) {
        $byDate[$d][$row['s']] = (int)$row['c'];
    }
    $byDate[$d]['total'] += (int)$row['c'];
}

$json = json_encode(array_values($byDate));
setCache($cacheKey, $json);
echo $json;