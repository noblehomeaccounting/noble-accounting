<?php
// admin/requestcentral/fetch-request-dates.php  → route: /fetchrequestdates
// Ibinabalik lang ang petsa + bilang kada status (ilang bytes lang, kahit libo ang request)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

if (!ob_start('ob_gzhandler')) {
    ob_start();
}

header('Content-Type: application/json');

$user_id = intval($_SESSION['account_id'] ?? 0);
session_write_close();

if (!$user_id) {
    echo json_encode([]);
    exit;
}

$cacheKey = "budget_request_dates_{$user_id}";
$cached = getCache($cacheKey, 20);
if ($cached !== false) {
    echo $cached;
    exit;
}

$result = $conn->query("
    SELECT DATE(date_requested) AS d, status, COUNT(*) AS c
    FROM noblebudgetrequest
    WHERE sent_to = $user_id AND date_requested IS NOT NULL
    GROUP BY d, status
    ORDER BY d DESC
");

$byDate = [];
while ($row = $result->fetch_assoc()) {
    $d = $row['d'];
    if (!isset($byDate[$d])) {
        $byDate[$d] = ['date' => $d, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
    }
    $status = $row['status'];
    if (isset($byDate[$d][$status])) {
        $byDate[$d][$status] = (int)$row['c'];
    }
    $byDate[$d]['total'] += (int)$row['c'];
}

$json = json_encode(array_values($byDate));
setCache($cacheKey, $json);
echo $json;