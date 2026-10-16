<?php
// profileofficialajax.php — i-mark / i-unmark ang employee bilang "Official Employee" (HR only)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message = '', array $errors = []): void
{
    echo json_encode(['success' => $success, 'message' => $message, 'errors' => $errors]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(403);
    respond(false, 'Invalid request. Please refresh the page and try again.');
}

$action     = $_POST['action'] ?? '';
$employeeid = trim($_POST['employeeid'] ?? '');

if ($employeeid === '' || !in_array($action, ['official', 'unofficial'], true)) {
    respond(false, '', ['Invalid request.']);
}

// Existing ba ang profile?
$chk = $conn->prepare("SELECT 1 FROM nobleid WHERE employeeid = ? LIMIT 1");
$chk->bind_param("s", $employeeid);
$chk->execute();
$chk->store_result();
$exists = $chk->num_rows > 0;
$chk->close();

if (!$exists) {
    respond(false, '', ['Profile not found. Please refresh the page.']);
}

if ($action === 'official') {
    $stmt = $conn->prepare("UPDATE nobleid SET is_official = 1, official_at = NOW() WHERE employeeid = ?");
} else {
    $stmt = $conn->prepare("UPDATE nobleid SET is_official = 0, official_at = NULL WHERE employeeid = ?");
}
$stmt->bind_param("s", $employeeid);

if ($stmt->execute()) {
    $stmt->close();
    respond(true, $action === 'official' ? 'Marked as official employee.' : 'Official status removed.');
}

$stmt->close();
respond(false, '', ['Database error. Please try again.']);