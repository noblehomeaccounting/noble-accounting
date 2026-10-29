<?php
// index-submit-request.php
include ROOT_PATH . '/network/connect.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

error_log(print_r($body, true));

$monthYear = date('mY');
$conn->query("INSERT INTO noblerequestsequences () VALUES ()");
$seq = $conn->insert_id;
$control_no = 'NHREQUEST-' . $monthYear . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);


$user_id        = intval($_SESSION['account_id'] ?? 0);
$requestor_name = trim($body['requestor_name'] ?? '');
$purpose        = trim($body['purpose'] ?? '');
$date_requested = trim($body['date_requested'] ?? '');
$sent_to        = intval($body['sent_to'] ?? 0);
$items          = json_encode($body['items'] ?? []);

// ── Attachments ──────────────────────────────────────
$attachments     = $body['attachments'] ?? [];
$savedPaths      = [];

if (!empty($attachments)) {
    $uploadDir = ROOT_PATH . '/uploads/attachments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

  foreach ($attachments as $file) {
    // ✅ Match both image/* and application/pdf
    if (!preg_match('#^data:([\w]+/[\w+\-]+);base64,#', $file['data'], $matches)) continue;
    
    $mime = $matches[1];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array($mime, $allowed)) continue;

    $base64 = preg_replace('#^data:[\w]+/[\w+\-]+;base64,#', '', $file['data']);
    $binary = base64_decode($base64);
    if (!$binary) continue;

    // ✅ Correct extension per MIME
    $ext = match($mime) {
        'application/pdf' => 'pdf',
        default           => 'webp',
    };

    $filename = uniqid('att_', true) . '.' . $ext;
    file_put_contents($uploadDir . $filename, $binary);
    $savedPaths[] = 'uploads/attachments/' . $filename;
}
}

$attachmentsJson = json_encode($savedPaths);
// ─────────────────────────────────────────────────────

if (!$control_no || !$requestor_name || !$purpose || !$sent_to || !$user_id) {
    echo json_encode([
        'success' => false,
        'error'   => 'Missing fields',
        'debug'   => [
            'control_no'     => $control_no,
            'user_id'        => $user_id,
            'requestor_name' => $requestor_name,
            'purpose'        => $purpose,
            'sent_to'        => $sent_to,
        ]
    ]);
    exit;
}

$attachment_status = trim($body['attachment_status'] ?? 'attached');
if (!in_array($attachment_status, ['attached', 'follow_up'])) {
    $attachment_status = 'attached';
}

$request_category  = trim($body['request_category'] ?? '');
$request_reference = trim($body['request_reference'] ?? '');

// Validate category
if (!in_array($request_category, ['project', 'client', 'nhcc'])) {
    $request_category = null;
}

// Reference only needed for project/client
if ($request_category === 'nhcc' || $request_category === null) {
    $request_reference = null;
}

if (!$control_no || !$requestor_name || !$purpose || !$sent_to || !$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO noblebudgetrequest 
    (control_no, user_id, requestor_name, purpose, date_requested, sent_to, items, attachments, attachment_status, request_category, request_reference) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sisssisssss",
    $control_no,
    $user_id,
    $requestor_name,
    $purpose,
    $date_requested,
    $sent_to,
    $items,
    $attachmentsJson,
    $attachment_status,
    $request_category,
    $request_reference
);

$result = $stmt->execute();

if ($result && $stmt->affected_rows > 0) {
    $new_request_id = $conn->insert_id;

    // ─── CLEAR CACHE NG APPROVER ─────────────────────
    include ROOT_PATH . '/network/cache-helper.php';
    clearCache("budget_requests_{$sent_to}");
    // ─────────────────────────────────────────────────

    $message = "New budget request from {$requestor_name}: {$purpose} check your request list.";
    
    $link = '/accounting';
    
    $notif_stmt = $conn->prepare("
        INSERT INTO noblenotification (user_id, request_id, message, link) 
        VALUES (?, ?, ?, ?)
    ");
    $notif_stmt->bind_param("iiss", $sent_to, $new_request_id, $message, $link);
    $notif_stmt->execute();

    // ─── REALTIME: "doorbell" sa approver para mag-refresh agad ang Budget Requests niya ───
    include_once ROOT_PATH . '/network/realtime-helper.php';
    nhRealtimeNotify($sent_to, 'changed', ['type' => 'new', 'id' => (int) $new_request_id]);
}

echo json_encode([
    'success'  => $result,
    'error'    => $conn->error,
    'affected' => $stmt->affected_rows
]);