<?php
// user/ui/index-fetch-my-vouchers.php
//
// Usage:
//   /fetchmyvouchers                -> buwan ngayon (default) + lahat ng PENDING pa ang acceptance
//   /fetchmyvouchers?month=2026-09  -> released nung Setyembre 2026 lang
//   /fetchmyvouchers?month=all      -> lahat ng released vouchers

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
    SELECT v.*, b.control_no, b.purpose, b.requestor_name, b.date_requested, b.items,
           prep.name AS prepared_name, v.prepared_at,
           cert.name AS certified_name, v.certified_at,
           appr.name AS approver_name, v.approved_at,
           rel.name  AS released_by_name,
           recv.name AS receiver_name, v.received_at,
           v.prepared_signature,
           v.certified_signature,
           v.approved_signature AS approver_signature
    FROM noblevoucher v
    LEFT JOIN noblebudgetrequest b ON v.request_id = b.id
    LEFT JOIN noblerole    prep ON v.prepared_by  = prep.id
    LEFT JOIN noblerole    cert ON v.certified_by = cert.id
    LEFT JOIN noblerole    appr ON v.approved_by  = appr.id
    LEFT JOIN noblerole    rel  ON v.released_by  = rel.id
    LEFT JOIN nobleaccount recv ON v.received_by  = recv.id
    WHERE b.user_id = ?
      AND v.status = 'released'
";

// Hindi pa na-accept (para hindi mawala sa listahan ang kailangan pang i-claim)
$pending = "(
        (v.received_by IS NULL OR v.received_by = 0)
        AND (v.manual_receiver_name IS NULL OR v.manual_receiver_name = '')
    )";

if ($month === 'all') {
    // Lahat (tinatawag lang kapag pinindot ang "View All")
    $sql .= " ORDER BY v.released_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Invalid format? balik sa buwan ngayon
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $month = date('Y-m');
    }

    // Range filter (hindi MONTH()/YEAR()) para gumana pa rin ang index sa released_at
    $start = $month . '-01';
    $end   = date('Y-m-d', strtotime($start . ' +1 month'));

    if ($month === date('Y-m')) {
        // Buwan ngayon: kasama pa rin ang mga lumang voucher na hindi pa na-accept
        $sql .= " AND ((v.released_at >= ? AND v.released_at < ?) OR $pending)";
    } else {
        // Ibang buwan: eksaktong buwan lang
        $sql .= " AND v.released_at >= ? AND v.released_at < ?";
    }
    $sql .= " ORDER BY v.released_at DESC";

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