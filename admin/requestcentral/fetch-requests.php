<?php
// admin/requestcentral/fetch-requests.php
// Gamit: /fetchrequests?month=YYYY-MM  (kapag walang month, ibabalik ang lahat tulad ng dati)
//        &fresh=1  → huwag gamitin ang cache (ginagamit kapag may realtime signal)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

// gzip kung kaya ng browser (JSON lumiliit nang 80-90%)
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

header('Content-Type: application/json');

$user_id = intval($_SESSION['account_id'] ?? 0);
session_write_close(); // bitawan ang session lock

if (!$user_id) {
    echo json_encode([]);
    exit;
}

// ── Optional na filter kada buwan ──────────────────────────
$month = $_GET['month'] ?? '';
$where = "b.sent_to = $user_id";

if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    // ligtas ito dahil tinanggap lang ng regex ang format na YYYY-MM
    $start  = $month . '-01';
    $where .= " AND b.date_requested >= '$start'
                AND b.date_requested < DATE_ADD('$start', INTERVAL 1 MONTH)";
    $cacheKey = "budget_requests_{$user_id}_{$month}";
} else {
    $cacheKey = "budget_requests_{$user_id}_all";
}

// May realtime signal (may bagong request / may nagbago) → laktawan ang cache para siguradong bago ang data
$fresh = ($_GET['fresh'] ?? '') === '1';

$cached = $fresh ? false : getCache($cacheKey, 20);
if ($cached !== false) {
    echo $cached;
    exit;
}

$result = $conn->query("SELECT b.*,
    n.name as sender_name,
    n.email as sender_email,
    r.name as approver_name,
    r.role as approver_role,
    CASE WHEN b.status = 'approved'
         THEN (SELECT path FROM noblesignature WHERE user_id = b.approved_by AND is_active = 1 LIMIT 1)
         ELSE NULL
    END as approver_signature,
    recv.name as receiver_name,
    (SELECT path FROM noblesignature WHERE user_id = b.received_by AND is_active = 1 LIMIT 1) as receiver_signature_path
    FROM noblebudgetrequest b
    LEFT JOIN nobleaccount n ON b.user_id = n.id
    LEFT JOIN noblerole r ON b.approved_by = r.id
    LEFT JOIN noblerole recv ON b.received_by = recv.id
    WHERE $where
    ORDER BY b.created_at DESC");

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true);
    $data[] = $row;
}

$json = json_encode($data);
setCache($cacheKey, $json);

echo $json;