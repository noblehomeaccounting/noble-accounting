<?php
//index-cashreleasevoucher.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTOASSISTANT];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

include ROOT_PATH . '/network/mailer.php';
include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$voucher_id = intval($body['voucher_id'] ?? 0);
$user_id = intval($_SESSION['account_id'] ?? 0);
$manual_name = trim($body['manual_name'] ?? '');
$manual_date = trim($body['manual_date'] ?? '');
if ($manual_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $manual_date)) {
    $manual_date = ''; // hindi valid na petsa → ngayong araw na lang
}
$manual_name = mb_substr($manual_name, 0, 150);

if (!$voucher_id || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Kunin ang voucher + request info + requestor email
$stmtV = $conn->prepare("
    SELECT v.*, b.control_no, b.purpose, b.user_id as requestor_id,
           a.email as requestor_email, a.name as requestor_name
    FROM noblevoucher v
    LEFT JOIN noblebudgetrequest b ON v.request_id = b.id
    LEFT JOIN nobleaccount a ON b.user_id = a.id
    WHERE v.id = ? LIMIT 1
");
$stmtV->bind_param('i', $voucher_id);
$stmtV->execute();
$vRow = $stmtV->get_result()->fetch_assoc();
$stmtV->close();

if (!$vRow) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

if (($vRow['status'] ?? '') !== 'ready_to_release') {
    echo json_encode(['success' => false, 'error' => 'This voucher is not ready to release, or it was already released.']);
    exit;
}

// Kunin ang signature ng custodian assistant na nag-release
$stmtSig = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmtSig->bind_param('i', $user_id);
$stmtSig->execute();
$sigRes  = $stmtSig->get_result();
$sigPath = $sigRes->num_rows ? $sigRes->fetch_assoc()['path'] : null;
$stmtSig->close();

if ($manual_name) {
    // Manual receiver
    $stmt = $conn->prepare("UPDATE noblevoucher 
        SET status = 'released', released_by = ?, released_at = NOW(), released_signature = ?,
            manual_receiver_name = ?, manual_receiver_date = ?
        WHERE id = ? AND status = 'ready_to_release'");
    $manual_date_val = $manual_date ?: date('Y-m-d');
    $stmt->bind_param("isssi", $user_id, $sigPath, $manual_name, $manual_date_val, $voucher_id);
} else {
    $stmt = $conn->prepare("UPDATE noblevoucher 
        SET status = 'released', released_by = ?, released_at = NOW(), released_signature = ?
        WHERE id = ? AND status = 'ready_to_release'");
    $stmt->bind_param("isi", $user_id, $sigPath, $voucher_id);
}

$success = $stmt->execute() && $stmt->affected_rows > 0; // 0 = na-release na ng iba (sabay na click)
$stmt->close();

if (!$success) {
    echo json_encode(['success' => false, 'error' => 'This voucher was already released.']);
    exit;
}

// Laging i-clear ang cache kapag nag-succeed (kahit walang email)
if ($success) {
    clearCache('cashvoucher_all');
    clearCache('custodian_received_requests');
    clearCache('staff_approved_requests');
    clearCache('staff_acknowledged_requests');
}

// Ibalik agad ang sagot at ipadala ang realtime signal BAGO ang email (para hindi maghintay sa mailer)
include_once ROOT_PATH . '/network/realtime-helper.php';
nhRealtimeNotifyGroup(ROLE_ACCOUNTING, null, 'changed', ['type' => 'voucher_released', 'id' => $voucher_id]);
nhRealtimeNotify(intval($vRow['requestor_id'] ?? 0), 'changed', ['type' => 'voucher_released', 'id' => $voucher_id]);
echo json_encode(['success' => true]);
nhRealtimeFlush(); // ibinabalik ang sagot sa browser, tapos padala sa Supabase

// Send email sa requestor
if ($success && $vRow['requestor_email']) {

    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $control_no = $vRow['control_no'];
    $purpose = $h($vRow['purpose']);
    $name = $h($vRow['requestor_name']);

    $subject = "Cash Voucher Released $control_no";
    // Kunin ang receiver name
    $receiverName = $h($manual_name ?: ($vRow['receiver_name'] ?? 'the designated receiver'));

    $emailBody = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:#f97316;padding:20px;text-align:center;'>
        <h2 style='color:white;margin:0;'>NobleHome Accounting</h2>
    </div>
    <div style='padding:30px;background:#fff;border:1px solid #eee;'>
        <p style='font-size:15px;'>Hi <strong>$name</strong>,</p>
        <p style='font-size:14px;color:#555;'>Your cash voucher request has been <strong style='color:#16a34a;'>released</strong>.</p>
        <div style='background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:20px 0;'>
            <p style='margin:0 0 8px;font-size:13px;'><strong>Control No.:</strong> {$h($control_no)}</p>
            <p style='margin:0 0 8px;font-size:13px;'><strong>Purpose:</strong> $purpose</p>
            <p style='margin:0;font-size:13px;'><strong>Received By:</strong> $receiverName</p>
        </div>
        <p style='font-size:13px;color:#888;'>Please log in to the system to view and accept your voucher.</p>
    </div>
    <div style='padding:15px;text-align:center;font-size:11px;color:#aaa;'>
        &copy; " . date('Y') . " NobleHome Construction Corporation
    </div>
</div>";

    sendMail($vRow['requestor_email'], $vRow['requestor_name'], $subject, $emailBody);
}