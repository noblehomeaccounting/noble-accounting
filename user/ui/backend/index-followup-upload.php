<?php
// index-followup-upload.php
// Route (suggested): /uploadfollowup  (POST, JSON)
// Nagdadagdag ng attachment sa request na naka "follow_up" pa.

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$user_id     = intval($_SESSION['account_id'] ?? 0);
$request_id  = intval($body['request_id'] ?? 0);
$attachments = $body['attachments'] ?? [];

if (!$user_id || !$request_id || empty($attachments) || !is_array($attachments)) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

// ── Siguraduhing sa user ang request at follow_up pa ──
$stmt = $conn->prepare("
    SELECT control_no, requestor_name, purpose, sent_to, attachments, attachment_status
    FROM noblebudgetrequest
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$stmt->bind_result($control_no, $requestor_name, $purpose, $sent_to, $existingJson, $attachment_status);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Request not found']);
    exit;
}
if ($attachment_status !== 'follow_up') {
    echo json_encode(['success' => false, 'error' => 'This request already has attachments']);
    exit;
}

// ── Save files ───────────────────────────────────────
$uploadDir = ROOT_PATH . '/uploads/attachments/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowed   = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$maxBytes  = 10 * 1024 * 1024; // 10MB bawat file
$maxFiles  = 10;

$savedPaths = [];
$savedFiles = []; // para ma-delete kung pumalya ang DB update

foreach (array_slice($attachments, 0, $maxFiles) as $file) {
    $data = $file['data'] ?? '';
    if (!preg_match('#^data:([\w]+/[\w+\-]+);base64,#', $data, $m)) continue;

    $mime = $m[1];
    if (!in_array($mime, $allowed, true)) continue;

    $binary = base64_decode(preg_replace('#^data:[\w]+/[\w+\-]+;base64,#', '', $data), true);
    if (!$binary || strlen($binary) > $maxBytes) continue;

    $ext      = $mime === 'application/pdf' ? 'pdf' : 'webp';
    $filename = uniqid('att_', true) . '.' . $ext;

    if (file_put_contents($uploadDir . $filename, $binary) === false) continue;

    $savedFiles[] = $uploadDir . $filename;
    $savedPaths[] = 'uploads/attachments/' . $filename;
}

if (empty($savedPaths)) {
    echo json_encode(['success' => false, 'error' => 'No valid files to upload']);
    exit;
}

// ── Update request ───────────────────────────────────
$existing    = json_decode($existingJson ?? '[]', true) ?: [];
$merged      = json_encode(array_values(array_merge($existing, $savedPaths)));

$upd = $conn->prepare("
    UPDATE noblebudgetrequest
    SET attachments = ?, attachment_status = 'attached', followup_uploaded_at = NOW()
    WHERE id = ? AND user_id = ? AND attachment_status = 'follow_up'
");
$upd->bind_param("sii", $merged, $request_id, $user_id);
$ok = $upd->execute() && $upd->affected_rows > 0;
$upd->close();

if (!$ok) {
    foreach ($savedFiles as $f) @unlink($f);
    echo json_encode(['success' => false, 'error' => 'Could not save attachments']);
    exit;
}

// ── Clear cache ng approver + abisuhan siya ──────────
include_once ROOT_PATH . '/network/cache-helper.php';
clearCache("budget_requests_{$sent_to}");

$message = "Attachment added for {$control_no} ({$requestor_name}): {$purpose}";
$link    = '/accounting';
$notif = $conn->prepare("
    INSERT INTO noblenotification (user_id, request_id, message, link)
    VALUES (?, ?, ?, ?)
");
$notif->bind_param("iiss", $sent_to, $request_id, $message, $link);
$notif->execute();
$notif->close();

echo json_encode([
    'success' => true,
    'files'   => count($savedPaths),
]);