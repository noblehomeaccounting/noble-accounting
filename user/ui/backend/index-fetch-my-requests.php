<?php
// index-fetch-my-requests.php
//
// Usage:
//   /fetchmyrequests                -> buwan ngayon lang (default)
//   /fetchmyrequests?month=2026-09  -> Setyembre 2026 lang
//   /fetchmyrequests?month=all      -> lahat ng request ng user

include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode([]);
    exit;
}

// Siguraduhing Manila time ang "buwan ngayon" (tanggalin kung naka-set na sa connect.php)
date_default_timezone_set('Asia/Manila');

$user_id = intval($_SESSION['account_id'] ?? 0);

$month = $_GET['month'] ?? date('Y-m');

$sql = "
    SELECT b.*,
           r.name  AS approver_name,
           rc.name AS receiver_name,
           CASE WHEN b.status = 'approved'
                THEN (SELECT path FROM noblesignature WHERE user_id = b.approved_by AND is_active = 1 LIMIT 1)
                ELSE NULL
           END AS approver_signature,
           (SELECT path FROM noblesignature WHERE user_id = b.received_by AND is_active = 1 LIMIT 1) AS receiver_signature_path
    FROM noblebudgetrequest b
    LEFT JOIN noblerole r  ON b.approved_by = r.id
    LEFT JOIN noblerole rc ON b.received_by = rc.id
    WHERE b.user_id = ?
";

if ($month === 'all') {
    // Lahat ng request (tinatawag lang kapag pinindot ang "View All")
    $sql .= " ORDER BY b.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Invalid format? balik sa buwan ngayon
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $month = date('Y-m');
    }

    // Range filter (hindi MONTH()/YEAR()) para gumana pa rin ang index sa date_requested
    $start = $month . '-01';
    $end   = date('Y-m-d', strtotime($start . ' +1 month'));

    $sql .= " AND b.date_requested >= ? AND b.date_requested < ? ORDER BY b.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start, $end);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true);
    $data[] = $row;
}
$stmt->close();

echo json_encode($data);