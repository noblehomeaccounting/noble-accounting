<?php
include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$user_id = intval($_SESSION['account_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM noblebudgetrequest
                        WHERE user_id = ? AND status = 'rejected'
                        ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $r['items'] = json_decode($r['items'] ?? '[]', true) ?: [];
    $r['attachments'] = json_decode($r['attachments'] ?? '[]', true) ?: [];
    $rows[] = $r;
}
echo json_encode($rows);