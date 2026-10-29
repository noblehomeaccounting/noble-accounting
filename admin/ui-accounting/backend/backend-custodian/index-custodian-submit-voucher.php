<?php
// index-custodian-submit-voucher.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_STAFF];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$request_id = intval($body['request_id'] ?? 0);
$payee = trim($body['payee'] ?? '');
$address = trim($body['address'] ?? '');
$user_id = intval($_SESSION['account_id'] ?? 0);
$purpose = trim($body['purpose'] ?? '');
$title = trim($body['title'] ?? '');
$second_no = trim($body['second_no'] ?? '');
$payment_method = trim($body['payment_method'] ?? '');

if (!$request_id || !$payee || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$stmtReq = $conn->prepare("SELECT control_no FROM noblebudgetrequest WHERE id = ? LIMIT 1");
$stmtReq->bind_param('i', $request_id);
$stmtReq->execute();
$row = $stmtReq->get_result()->fetch_assoc();
$stmtReq->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

$stmtCheck = $conn->prepare("SELECT id FROM noblevoucher WHERE request_id = ? LIMIT 1");
$stmtCheck->bind_param('i', $request_id);
$stmtCheck->execute();
$checkResult = $stmtCheck->get_result();
if ($checkResult->num_rows > 0) {
    $stmtCheck->close();
    echo json_encode(['success' => false, 'error' => 'Already submitted']);
    exit;
}
$stmtCheck->close();

// Active signature ng nag-submit (staff = preparer)
$stmtSig = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmtSig->bind_param('i', $user_id);
$stmtSig->execute();
$sigResult = $stmtSig->get_result();
$sigPath = $sigResult->num_rows ? $sigResult->fetch_assoc()['path'] : null;
$stmtSig->close();

// prepared_* na ang gamit (dating certified_*)
$stmt = $conn->prepare("INSERT INTO noblevoucher 
    (request_id, control_no, payee, address, purpose, title, second_no, payment_method,
     prepared_by, prepared_at, prepared_signature, created_at, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), 'voucher_approval')");
$stmt->bind_param(
    "isssssssis",
    $request_id,
    $row['control_no'],
    $payee,
    $address,
    $purpose,
    $title,
    $second_no,
    $payment_method,
    $user_id,
    $sigPath
);
$success = $stmt->execute();
$stmt->close();

// Notify HEAD
if ($success) {

    clearCache('cashvoucher_all');
    clearCache('custodian_received_requests');
    clearCache('staff_approved_requests');
    clearCache('staff_acknowledged_requests');

    $control_no = $row['control_no'];
    $message = "Cash voucher for $control_no has been submitted and is ready for your approval. Check Approval Cash Voucher.";
    $link = '/cashvoucherdashboard'; // page ng head

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
        $stmt2->close();
    }

    // Realtime: buong Accounting (Head, Staff, Custodian, Custodian Assistant) — iisang signal.
    // Sa sidebar: badge + bell ng Head; sa Cash Vouchers page: "For approval" agad sa lahat.
    include_once ROOT_PATH . '/network/realtime-helper.php';
    nhRealtimeNotifyGroup(ROLE_ACCOUNTING, null, 'changed', ['type' => 'voucher_submitted', 'id' => $request_id]);
}

// Save to Sheet Two (noblepettycashcustodiantwo) kung may department
if ($success && $second_no) {
    $stmtDetails = $conn->prepare("SELECT date_requested, items FROM noblebudgetrequest WHERE id = ? LIMIT 1");
    $stmtDetails->bind_param('i', $request_id);
    $stmtDetails->execute();
    $reqDetails = $stmtDetails->get_result()->fetch_assoc();
    $stmtDetails->close();

    $items = json_decode($reqDetails['items'] ?? '[]', true) ?: [];

    // Particulars galing sa items
    $particularsArr = [];
    foreach ($items as $item) {
        if (empty($item['description']))
            continue;
        $particularsArr[] = $item['description'] . (!empty($item['purpose']) ? ' — ' . $item['purpose'] : '');
    }
    $particulars = implode(', ', $particularsArr) ?: $purpose;

    // Total amount
    $total = array_sum(array_map(fn($i) => floatval($i['amount'] ?? 0), $items));

    $dateRequested = $reqDetails['date_requested'] ?? date('Y-m-d');
    $voucherControlNo = $row['control_no'];

    // Pangalan ng nag-submit para sa inserted_by
    $stmtUser = $conn->prepare("SELECT name FROM nobleaccount WHERE id = ? LIMIT 1");
    $stmtUser->bind_param('i', $user_id);
    $stmtUser->execute();
    $userRow = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();
    $insertedBy = $userRow['name'] ?? '';

    $stmt3 = $conn->prepare("
        INSERT INTO noblepettycashcustodiantwo 
        (date, reference_no, account_title, particulars, project_department, actual, inserted_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt3->bind_param(
        "sssssds",
        $dateRequested,
        $voucherControlNo,
        $title,
        $particulars,
        $second_no,
        $total,
        $insertedBy
    );
    $stmt3->execute();
    $stmt3->close();
}

echo json_encode(['success' => $success]);