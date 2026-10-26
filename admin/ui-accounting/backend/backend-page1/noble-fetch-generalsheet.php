<?php
// noble-fetch-generalsheet.php — listahan ng General Sheet para sa isang buwan (JSON)

ob_start(); // sinasalo ang anumang stray output (warnings) para JSON lang ang lumabas

function gsfJson($data, int $code = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("[noble-fetch-generalsheet] PHP warning: $message @ $file:$line");
    return true;
});
set_exception_handler(function (Throwable $e) {
    error_log('[noble-fetch-generalsheet] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gsfJson(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
});

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Buwan: YYYY-MM (01–12 lang); kapag mali, ang buwan ngayon
$month = (string) ($_GET['month'] ?? '');
if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}
$start = $month . '-01';
$end = date('Y-m-d', strtotime($start . ' +1 month'));

// Range sa halip na DATE_FORMAT(payment_date) — mas mabilis at nagagamit ang index
$stmt = $conn->prepare("
    SELECT
        id,
        payment_date,
        collection_receipt_no,
        type_of_sale,
        deposit_reference_no,
        payee,
        payor,
        particulars,
        sales_person,
        department,
        reference_mode,
        amount,
        inserted_by,
        created_at
    FROM noblegeneralsheet
    WHERE payment_date >= ? AND payment_date < ?
    ORDER BY payment_date ASC, id ASC
");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

gsfJson($rows);