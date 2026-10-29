<?php
// index-cashvoucherprepared.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$body       = json_decode(file_get_contents('php://input'), true);
$voucher_id = intval($body['voucher_id'] ?? 0);
$user_id    = intval($_SESSION['account_id'] ?? 0);

if (!$voucher_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Accounting Staff lang ang pwedeng mag-prepare (role + position)
if (($_SESSION['role'] ?? '') !== ROLE_ACCOUNTING || ($_SESSION['position'] ?? '') !== POSITION_STAFF) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed.']);
    exit;
}

// Signature ng user
$stmtSig = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmtSig->bind_param("i", $user_id);
$stmtSig->execute();
$sigRow  = $stmtSig->get_result()->fetch_assoc();
$sigPath = $sigRow['path'] ?? null;

// prepared_by IS NULL = hindi mao-overwrite kapag na-prepare na (double click, dalawang staff)
$stmt = $conn->prepare("UPDATE noblevoucher
    SET prepared_by = ?, prepared_at = NOW(), prepared_signature = ?
    WHERE id = ? AND prepared_by IS NULL");
$stmt->bind_param("isi", $user_id, $sigPath, $voucher_id);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    echo json_encode(['success' => false, 'error' => 'Voucher already prepared or not found.']);
    exit;
}

clearCache('cashvoucher_all');
clearCache('custodian_received_requests');

// Control no. ng voucher
$stmtV = $conn->prepare("SELECT request_id, control_no FROM noblevoucher WHERE id = ? LIMIT 1");
$stmtV->bind_param("i", $voucher_id);
$stmtV->execute();
$vRow       = $stmtV->get_result()->fetch_assoc();
$control_no = $vRow['control_no'] ?? '';
$request_id = intval($vRow['request_id'] ?? 0);

$message = "Cash voucher $control_no has been prepared and is ready for your approval. check your approval cash voucher list.";
$link    = '/cashvoucherdashboard'; // page ng head

// Notify HEAD
$heads = $conn->query("
    SELECT id FROM noblerole
    WHERE role = 'ACCOUNTING AND FINANCE DEPARTMENT'
    AND position = 'head'
");

if ($heads) {
    $stmt2 = $conn->prepare("
        INSERT INTO noblenotification (user_id, request_id, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 0, NOW(), ?, ?)
    ");
    while ($h = $heads->fetch_assoc()) {
        $hid = intval($h['id']);
        $stmt2->bind_param("iisis", $hid, $request_id, $message, $user_id, $link);
        $stmt2->execute();
    }
}

// Realtime: buong Accounting — "To approve" ng Head, at nawawala sa "To prepare" ng ibang Staff
include_once ROOT_PATH . '/network/realtime-helper.php';
nhRealtimeNotifyGroup(ROLE_ACCOUNTING, null, 'changed', ['type' => 'voucher_prepared', 'id' => $voucher_id]);

echo json_encode(['success' => true]);