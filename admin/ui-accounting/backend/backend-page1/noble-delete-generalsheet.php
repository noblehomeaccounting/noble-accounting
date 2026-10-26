<?php
// deletenoblegeneralsheet.php — burahin ang isang General Sheet entry (JSON)

ob_start();

function gsdJson(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("[deletenoblegeneralsheet] PHP warning: $message @ $file:$line");
    return true;
});
set_exception_handler(function (Throwable $e) {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) { }
    }
    error_log('[deletenoblegeneralsheet] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gsdJson(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
});

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gsdJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$id = is_array($body) ? (int) ($body['id'] ?? 0) : 0;
if ($id <= 0) {
    gsdJson(['success' => false, 'message' => 'ID is required.'], 422);
}

$conn->begin_transaction();

// Burahin din ang project billing na awtomatikong ginawa mula sa entry na ito
// (galing sa auto-link ng savenoblegeneralsheet), para walang maiwang billing na wala nang pinanggalingan.
$bs = $conn->prepare("DELETE FROM nobleprojectbilling WHERE generalsheet_source_id = ?");
$bs->bind_param('i', $id);
$bs->execute();
$billingRemoved = $bs->affected_rows;
$bs->close();

$stmt = $conn->prepare("DELETE FROM noblegeneralsheet WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted < 1) {
    $conn->rollback();
    gsdJson(['success' => false, 'message' => 'Entry not found — it may have been deleted already. Refresh the page.'], 404);
}

$conn->commit();
gsdJson(['success' => true, 'billing_removed' => max(0, $billingRemoved)]);