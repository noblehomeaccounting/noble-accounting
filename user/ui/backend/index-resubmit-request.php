<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/network/cache-helper.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id = intval($body['id'] ?? 0);
$user_id = intval($_SESSION['account_id'] ?? 0);

// Dapat sa kanya ang request AT rejected pa
$stmt = $conn->prepare("SELECT * FROM noblebudgetrequest
                        WHERE id = ? AND user_id = ? AND status = 'rejected' LIMIT 1");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();

if (!$old) {
    echo json_encode(['success' => false, 'error' => 'Request not found or not rejected']);
    exit;
}

$requestor_name = trim($body['requestor_name'] ?? '');
$purpose = trim($body['purpose'] ?? '');
$items = json_encode($body['items'] ?? []);
$sent_to = intval($old['sent_to']);

if (!$requestor_name || !$purpose) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$attachment_status = in_array($body['attachment_status'] ?? '', ['attached', 'follow_up'])
    ? $body['attachment_status'] : 'attached';

$request_category = trim($body['request_category'] ?? '');
if (!in_array($request_category, ['project', 'client', 'nhcc']))
    $request_category = null;
$request_reference = ($request_category === 'nhcc' || $request_category === null)
    ? null : trim($body['request_reference'] ?? '');

// ── Attachments: kept (existing) + bago ──
$oldPaths = json_decode($old['attachments'] ?? '[]', true) ?: [];
$keep = array_values(array_intersect($oldPaths, $body['keep_attachments'] ?? [])); // safe: dapat galing sa old
$savedPaths = $keep;

$uploadDir = ROOT_PATH . '/uploads/attachments/';
if (!is_dir($uploadDir))
    mkdir($uploadDir, 0755, true);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

foreach (($body['attachments'] ?? []) as $file) {
    if (!preg_match('#^data:([\w]+/[\w+\-]+);base64,#', $file['data'], $m))
        continue;
    if (!in_array($m[1], $allowed))
        continue;
    $binary = base64_decode(preg_replace('#^data:[\w]+/[\w+\-]+;base64,#', '', $file['data']));
    if (!$binary)
        continue;
    $ext = $m[1] === 'application/pdf' ? 'pdf' : 'webp';
    $filename = uniqid('att_', true) . '.' . $ext;
    file_put_contents($uploadDir . $filename, $binary);
    $savedPaths[] = 'uploads/attachments/' . $filename;
}
$attachmentsJson = json_encode($savedPaths);

// ── I-save muna ang lumang reject reason sa history ──
$h = $conn->prepare("INSERT INTO noblebudgetrequesthistory (request_id, reject_comment, items) VALUES (?, ?, ?)");
$h->bind_param("iss", $id, $old['reject_comment'], $old['items']);
$h->execute();

// ── Balik sa pending, SAME control_no ──
$u = $conn->prepare("UPDATE noblebudgetrequest SET
        requestor_name = ?, purpose = ?, items = ?, attachments = ?, attachment_status = ?,
        request_category = ?, request_reference = ?,
        date_requested = CURDATE(),
        status = 'pending', reject_comment = NULL,
        approved_by = NULL, approved_at = NULL, approver_signature_path = NULL,
        resubmit_count = resubmit_count + 1
    WHERE id = ? AND user_id = ? AND status = 'rejected'");
$u->bind_param(
    "sssssssii",
    $requestor_name,
    $purpose,
    $items,
    $attachmentsJson,
    $attachment_status,
    $request_category,
    $request_reference,
    $id,
    $user_id
);
$ok = $u->execute();

if ($ok && $u->affected_rows > 0) {
    clearCache("budget_requests_{$sent_to}");

    $message = "Request {$old['control_no']} was revised and resubmitted by {$requestor_name}.";
    $link = '/accounting';
    $n = $conn->prepare("INSERT INTO noblenotification (user_id, request_id, message, link) VALUES (?, ?, ?, ?)");
    $n->bind_param("iiss", $sent_to, $id, $message, $link);
    $n->execute();
}

echo json_encode(['success' => $ok && $u->affected_rows > 0, 'error' => $conn->error]);