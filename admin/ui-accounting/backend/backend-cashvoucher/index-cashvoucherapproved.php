<?php
// index-cashvoucherapproved.php

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

// Accounting Head lang ang pwedeng mag-approve (role + position)
if (($_SESSION['role'] ?? '') !== ROLE_ACCOUNTING || ($_SESSION['position'] ?? '') !== POSITION_HEAD) {
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

// Nasa WHERE na ang rules: dapat prepared na, at hindi pa approved.
// Isang query lang, kaya walang race sa pagitan ng check at update.
$stmt = $conn->prepare("UPDATE noblevoucher
    SET approved_by = ?, approved_at = NOW(), approved_signature = ?, status = 'ready_to_release'
    WHERE id = ? AND prepared_by IS NOT NULL AND approved_by IS NULL");
$stmt->bind_param("isi", $user_id, $sigPath, $voucher_id);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    echo json_encode(['success' => false, 'error' => 'Voucher must be prepared first, or it is already approved.']);
    exit;
}

clearCache('cashvoucher_all');
clearCache('custodian_received_requests');

$stmtV = $conn->prepare("
    SELECT v.request_id, b.control_no
    FROM noblevoucher v
    LEFT JOIN noblebudgetrequest b ON v.request_id = b.id
    WHERE v.id = ? LIMIT 1
");
$stmtV->bind_param("i", $voucher_id);
$stmtV->execute();
$vRow       = $stmtV->get_result()->fetch_assoc();
$control_no = $vRow['control_no'] ?? '';
$request_id = intval($vRow['request_id'] ?? 0);

$message = "Cash voucher $control_no has been approved and is ready to release. check your cash voucher request list.";
$link    = '/accountingcustodian'; // page ng custodian

$custodians = $conn->query("
    SELECT id FROM noblerole
    WHERE role = 'ACCOUNTING AND FINANCE DEPARTMENT'
    AND position = 'custodian'
");

if ($custodians) {
    $stmt2 = $conn->prepare("
        INSERT INTO noblenotification (user_id, request_id, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 0, NOW(), ?, ?)
    ");
    while ($c = $custodians->fetch_assoc()) {
        $cid = intval($c['id']);
        $stmt2->bind_param("iisis", $cid, $request_id, $message, $user_id, $link);
        $stmt2->execute();
    }
}

// Realtime: buong Accounting — "Ready to release" agad sa Custodian / Custodian Assistant
include_once ROOT_PATH . '/network/realtime-helper.php';
nhRealtimeNotifyGroup(ROLE_ACCOUNTING, null, 'changed', ['type' => 'voucher_approved', 'id' => $voucher_id]);

echo json_encode(['success' => true]);