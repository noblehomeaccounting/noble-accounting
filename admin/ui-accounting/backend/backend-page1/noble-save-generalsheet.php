<?php
// savenoblegeneralsheet.php — Add / Edit ng entry sa Noble General Sheet (JSON)

// Lahat ng output ay dapat JSON lang. Sinasalo ang anumang warning/echo na
// nauuna (ito ang dahilan ng "Network error / Server error" sa page).
ob_start();

function gsJson(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ang warnings/notices ay ilo-log lang (hindi ilalabas), para hindi masira ang JSON
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("[savenoblegeneralsheet] PHP warning: $message @ $file:$line");
    return true;
});
// Kahit fatal error, JSON pa rin ang isasagot
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        gsJson(['success' => false, 'message' => 'Server error: ' . $err['message']], 500);
    }
});
set_exception_handler(function (Throwable $e) {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) { }
    }
    error_log('[savenoblegeneralsheet] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gsJson(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
});

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // mysqli errors → exception (sinasalo sa itaas)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gsJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    gsJson(['success' => false, 'message' => 'Invalid request data.'], 400);
}

// ─── Helpers ─────────────────────────────────────────────
function gsStr(array $b, string $key, int $max = 255): ?string
{
    $v = trim((string) ($b[$key] ?? ''));
    if ($v === '') return null;
    return mb_substr($v, 0, $max);
}

// ─── Validation (server-side) ────────────────────────────
$id = !empty($body['id']) ? (int) $body['id'] : null;
$payment_date = trim((string) ($body['payment_date'] ?? ''));
$particulars = gsStr($body, 'particulars', 500);

$errors = [];
$d = DateTime::createFromFormat('Y-m-d', $payment_date);
if (!$d || $d->format('Y-m-d') !== $payment_date) {
    $errors[] = 'Payment date is missing or invalid.';
}
if ($particulars === null) {
    $errors[] = 'Particulars is required.';
}

$amountRaw = $body['amount'] ?? 0;
if ($amountRaw === '' || $amountRaw === null) $amountRaw = 0;
if (!is_numeric($amountRaw) || (float) $amountRaw < 0) {
    $errors[] = 'Amount must be a number, 0 or more.';
}
$amount = round((float) $amountRaw, 2);
if ($amount > 9999999999.99) {
    $errors[] = 'Amount is too large.';
}

if ($errors) {
    gsJson(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$collection_receipt_no = gsStr($body, 'collection_receipt_no', 100);
$deposit_reference_no = gsStr($body, 'deposit_reference_no', 100);
$reference_mode = in_array($body['reference_mode'] ?? '', ['collection', 'deposit'], true) ? $body['reference_mode'] : 'collection';
// Ang reference ay kinukuha dito mismo sa server (hindi pinagkakatiwalaan ang galing sa browser)
$reference = $reference_mode === 'deposit' ? $deposit_reference_no : $collection_receipt_no;
$department = gsStr($body, 'department', 150);
$type_of_sale = gsStr($body, 'type_of_sale', 100);
$payee = gsStr($body, 'payee', 150);
$payor = gsStr($body, 'payor', 150);
$sales_person = gsStr($body, 'sales_person', 150);
$inserted_by = $_SESSION['username'] ?? 'Unknown';

// ─── Save (transaction: general sheet + project billing) ─
$conn->begin_transaction();

if ($id) {
    // Siguraduhing may ganitong entry; huwag palitan ang payment date ng lumang entry
    $chk = $conn->prepare("SELECT payment_date FROM noblegeneralsheet WHERE id = ? FOR UPDATE");
    $chk->bind_param('i', $id);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$existing) {
        $conn->rollback();
        gsJson(['success' => false, 'message' => 'This entry no longer exists. Refresh the page.'], 404);
    }
    $payment_date = $existing['payment_date'] ?: $payment_date;

    $stmt = $conn->prepare("
        UPDATE noblegeneralsheet SET
            payment_date          = ?,
            collection_receipt_no = ?,
            deposit_reference_no  = ?,
            reference_mode        = ?,
            reference             = ?,
            department            = ?,
            type_of_sale          = ?,
            payee                 = ?,
            payor                 = ?,
            particulars           = ?,
            sales_person          = ?,
            amount                = ?
        WHERE id = ?
    ");
    // 11 strings, amount (d), id (i)
    $stmt->bind_param(
        'sssssssssssdi',
        $payment_date,
        $collection_receipt_no,
        $deposit_reference_no,
        $reference_mode,
        $reference,
        $department,
        $type_of_sale,
        $payee,
        $payor,
        $particulars,
        $sales_person,
        $amount,
        $id
    );
} else {
    $stmt = $conn->prepare("
        INSERT INTO noblegeneralsheet
            (payment_date, collection_receipt_no, deposit_reference_no,
             reference_mode, reference, department,
             type_of_sale, payee, payor,
             particulars, sales_person, amount, inserted_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    // 11 strings, amount (d), inserted_by (s)
    $stmt->bind_param(
        'sssssssssssds',
        $payment_date,
        $collection_receipt_no,
        $deposit_reference_no,
        $reference_mode,
        $reference,
        $department,
        $type_of_sale,
        $payee,
        $payor,
        $particulars,
        $sales_person,
        $amount,
        $inserted_by
    );
}

$stmt->execute();
$saved_id = $id ?: (int) $conn->insert_id;
$stmt->close();

// ── AUTO-LINK TO PROJECT BILLING ──────────────────────────
if ($department) {
    $linked_project_id = null;
    $ps = $conn->prepare("SELECT id FROM nobleprojectmonitor WHERE project_name = ? LIMIT 1");
    $ps->bind_param('s', $department);
    $ps->execute();
    $ps->bind_result($linked_project_id);
    $ps->fetch();
    $ps->close();

    if ($linked_project_id) {
        $existing_billing_id = null;
        $chk = $conn->prepare("SELECT id FROM nobleprojectbilling WHERE generalsheet_source_id = ? LIMIT 1");
        $chk->bind_param('i', $saved_id);
        $chk->execute();
        $chk->bind_result($existing_billing_id);
        $chk->fetch();
        $chk->close();

        if ($existing_billing_id) {
            // particulars (s), amount (d), payment_date (s), reference (s), project_id (i), id (i)
            $bs = $conn->prepare("UPDATE nobleprojectbilling
                                  SET particulars = ?, amount = ?, payment_date = ?, reference = ?, project_id = ?
                                  WHERE id = ?");
            $bs->bind_param('sdssii', $particulars, $amount, $payment_date, $reference, $linked_project_id, $existing_billing_id);
            $bs->execute();
            $bs->close();
        } else {
            $bs = $conn->prepare("INSERT INTO nobleprojectbilling
                                  (project_id, particulars, amount, payment_date, reference, generalsheet_source_id)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $bs->bind_param('isdssi', $linked_project_id, $particulars, $amount, $payment_date, $reference, $saved_id);
            $bs->execute();
            $bs->close();
        }
    }
}

$conn->commit();

gsJson(['success' => true, 'id' => $saved_id]);