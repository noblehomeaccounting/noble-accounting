<?php
// action-request.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

header('Content-Type: application/json');

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$id      = intval($body['id'] ?? 0);
$action  = in_array($body['action'] ?? '', ['approved', 'rejected'], true) ? $body['action'] : null;
$user_id = intval($_SESSION['account_id'] ?? 0);
$comment = trim($body['comment'] ?? '');

if (!$id || !$action || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

if ($action === 'approved') {
    // Kunin ang active signature ng approver
    $sigRow  = $conn->query("SELECT path FROM noblesignature WHERE user_id = $user_id AND is_active = 1 LIMIT 1");
    $sigPath = ($sigRow && $sigRow->num_rows) ? $sigRow->fetch_assoc()['path'] : null;

    $stmt = $conn->prepare("UPDATE noblebudgetrequest
        SET status = ?, approved_by = ?, approved_at = NOW(), reject_comment = NULL, approver_signature_path = ?
        WHERE id = ? AND sent_to = ?");
    $stmt->bind_param("sisii", $action, $user_id, $sigPath, $id, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE noblebudgetrequest
        SET status = ?, reject_comment = ?, approved_by = NULL, approved_at = NULL
        WHERE id = ? AND sent_to = ?");
    $stmt->bind_param("ssii", $action, $comment, $id, $user_id);
}

$success  = $stmt->execute();
$affected = $stmt->affected_rows;

if ($success && $affected > 0) {

    // Kunin ang detalye ng request (kasama ang date_requested para sa cache key)
    $req = $conn->query("SELECT control_no, user_id, date_requested FROM noblebudgetrequest WHERE id = $id LIMIT 1");
    $reqRow       = $req ? $req->fetch_assoc() : [];
    $control_no   = $reqRow['control_no'] ?? '';
    $requestor_id = intval($reqRow['user_id'] ?? 0);

    // ── I-clear ang cache (exact keys, kasi md5 ang filename) ──
    $reqMonth = substr($reqRow['date_requested'] ?? '', 0, 7); // YYYY-MM
    if ($reqMonth) {
        clearCache("budget_requests_{$user_id}_{$reqMonth}");
    }
    clearCache("budget_requests_{$user_id}_all");
    clearCache("budget_request_dates_{$user_id}");

    $message = $action === 'approved'
        ? "Request $control_no has been approved."
        : "Request $control_no has been rejected. Reason: $comment";

    // Notify ang requestor
    if ($requestor_id) {
        $stmtUserNotif = $conn->prepare("
            INSERT INTO nobleusernotification (user_id, request_id, message, is_read, created_at, sender_id)
            VALUES (?, ?, ?, 0, NOW(), ?)
        ");
        $stmtUserNotif->bind_param("iisi", $requestor_id, $id, $message, $user_id);
        $stmtUserNotif->execute();
    }

    // Notify ang accounting staff
    $staffResult = $conn->query("
        SELECT id FROM noblerole
        WHERE role = 'ACCOUNTING AND FINANCE DEPARTMENT'
        AND position = 'staff'
    ");

    if ($staffResult) {
        $link  = '/accountingstaff';
        $stmt2 = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 0, NOW(), ?, ?)
        ");
        while ($staff = $staffResult->fetch_assoc()) {
            $staff_id = intval($staff['id']);
            $stmt2->bind_param("iisis", $staff_id, $id, $message, $user_id, $link);
            $stmt2->execute();
        }
    }
}

echo json_encode([
    'success'  => $success && $affected > 0,
    'affected' => $affected,
    'message'  => ($success && $affected > 0) ? '' : 'Walang na-update. Maaaring hindi sa iyo ang request na ito.',
]);