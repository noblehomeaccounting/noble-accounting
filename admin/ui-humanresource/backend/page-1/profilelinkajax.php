<?php
// profilelinkajax.php — i-link / i-unlink ang nobleid record sa nobleaccount AT/O noblerole

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

// action => [target, operation]
// target config: column sa nobleid, table na lilinkan, POST field, label
$actions = [
    'link'        => ['account', 'link'],
    'unlink'      => ['account', 'unlink'],
    'link_role'   => ['role',    'link'],
    'unlink_role' => ['role',    'unlink'],
];

// Fixed whitelist lang (kaya ligtas i-interpolate ang column/table names sa SQL)
$targets = [
    'account' => ['col' => 'account_id', 'table' => 'nobleaccount', 'field' => 'account_id', 'label' => 'account'],
    'role'    => ['col' => 'role_id',    'table' => 'noblerole',    'field' => 'role_id',    'label' => 'role'],
];

$action     = $_POST['action'] ?? '';
$employeeId = trim($_POST['employeeid'] ?? '');

if ($employeeId === '' || !isset($actions[$action])) {
    respond(false, 'Invalid request.');
}

[$targetKey, $op] = $actions[$action];
$t = $targets[$targetKey];

// Existing ba ang profile?
$chk = $conn->prepare("SELECT 1 FROM nobleid WHERE employeeid = ? LIMIT 1");
$chk->bind_param("s", $employeeId);
$chk->execute();
$chk->store_result();
$found = $chk->num_rows > 0;
$chk->close();

if (!$found) {
    respond(false, 'Profile not found.');
}

// ── Unlink ────────────────────────────────────────────────────
if ($op === 'unlink') {
    $stmt = $conn->prepare("UPDATE nobleid SET {$t['col']} = NULL WHERE employeeid = ?");
    $stmt->bind_param("s", $employeeId);
    $ok = $stmt->execute();
    $stmt->close();

    $ok ? respond(true, 'Profile unlinked from ' . $t['label'] . '.')
        : respond(false, '', ['Database error. Please try again.']);
}

// ── Link ──────────────────────────────────────────────────────
$targetId = (int) ($_POST[$t['field']] ?? 0);
if ($targetId <= 0) {
    respond(false, '', ['Please select a ' . $t['label'] . ' to link.']);
}

// Existing ba ang account / role?
$ex = $conn->prepare("SELECT 1 FROM {$t['table']} WHERE id = ? LIMIT 1");
$ex->bind_param("i", $targetId);
$ex->execute();
$ex->store_result();
$targetExists = $ex->num_rows > 0;
$ex->close();

if (!$targetExists) {
    respond(false, '', [ucfirst($t['label']) . ' not found.']);
}

// May ibang profile na ba na naka-link dito?
$dup = $conn->prepare("SELECT 1 FROM nobleid WHERE {$t['col']} = ? AND employeeid <> ? LIMIT 1");
$dup->bind_param("is", $targetId, $employeeId);
$dup->execute();
$dup->store_result();
$taken = $dup->num_rows > 0;
$dup->close();

if ($taken) {
    respond(false, '', ['That ' . $t['label'] . ' is already linked to another profile.']);
}

$stmt = $conn->prepare("UPDATE nobleid SET {$t['col']} = ? WHERE employeeid = ?");
$stmt->bind_param("is", $targetId, $employeeId);
$ok = $stmt->execute();
$stmt->close();

$ok ? respond(true, 'Profile linked to ' . $t['label'] . '.')
    : respond(false, '', ['Database error. Please try again.']);