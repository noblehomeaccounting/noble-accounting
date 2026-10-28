<?php
// index-accounting-crmlistajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Departments that get notified once a submission is marked Notice to
// Proceed (production-side departments — they can now start work).
// ⚠️ verify this is the exact set the business wants notified.
const ACCT_NTP_NOTIFY_ROLES = [ROLE_CUTTING, ROLE_OPERATIONS, ROLE_DESIGNER];

// Where deposit slip uploads are stored. ⚠️ adjust to match wherever the
// rest of the system keeps uploaded files (e.g. an existing upload
// helper/constant), if different from this.
const ACCT_DEPOSIT_UPLOAD_DIR = ROOT_PATH . '/uploads/deposit-slips';
const ACCT_DEPOSIT_UPLOAD_URL_PREFIX = '/uploads/deposit-slips';
const ACCT_DEPOSIT_ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
const ACCT_DEPOSIT_MAX_BYTES = 10 * 1024 * 1024; // 10MB
const ACCT_PAYMENT_MAX_AMOUNT = 999999999999.99;

// May table na ba para sa maraming bayad (noblecrm_deposit_payment.sql)? Kapag wala pa, gagana pa rin ang page.
function acctPaymentsReady(mysqli $conn): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $r = $conn->query("SHOW TABLES LIKE 'noblecrm_deposit_payment'");
            $ready = $r && $r->num_rows > 0;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

function acctJson(array $d): void
{
    echo json_encode($d);
    exit;
}

// I-save ang resibo; ibinabalik ang path o nag-e-exit na may error
function acctSaveSlip(array $file, int $quotationId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) acctJson(['success' => false, 'message' => 'Please attach the receipt / deposit slip.']);
    if ($file['size'] > ACCT_DEPOSIT_MAX_BYTES) acctJson(['success' => false, 'message' => 'File is too large (max 10MB).']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ACCT_DEPOSIT_ALLOWED_EXT, true)) acctJson(['success' => false, 'message' => 'Only PDF, JPG, or PNG files are allowed.']);
    // tingnan din ang laman, hindi lang ang pangalan
    $mime = function_exists('mime_content_type') ? (string) @mime_content_type($file['tmp_name']) : '';
    if ($mime !== '' && !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
        acctJson(['success' => false, 'message' => 'The file does not look like a PDF, JPG or PNG.']);
    }
    if (!is_dir(ACCT_DEPOSIT_UPLOAD_DIR)) mkdir(ACCT_DEPOSIT_UPLOAD_DIR, 0755, true);
    $filename = 'deposit_' . $quotationId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], ACCT_DEPOSIT_UPLOAD_DIR . '/' . $filename)) {
        acctJson(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    }
    return ACCT_DEPOSIT_UPLOAD_URL_PREFIX . '/' . $filename;
}

// Approved na record (para sa payment / NTP)
function acctApprovedRecord(mysqli $conn, int $id): array
{
    $st = $conn->prepare("
        SELECT q.id, q.status, q.deposit_status, q.inquiry_id, i.control_no, i.client_name, i.contract_amount
        FROM noblecrm_2dquotation q
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE q.id = ? LIMIT 1
    ");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) acctJson(['success' => false, 'message' => 'Record not found.']);
    if ($row['status'] !== 'Approved') acctJson(['success' => false, 'message' => 'This submission is not fully approved yet.']);
    return $row;
}

function acctPaymentTotals(mysqli $conn, int $quotationId): array
{
    $st = $conn->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM noblecrm_deposit_payment WHERE quotation_id = ?");
    $st->bind_param('i', $quotationId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return ['count' => (int) $r['n'], 'total' => (float) $r['total']];
}

// Same labeling convention as check2dquotationajax.php's chk2dRoleLabel().
function acctListRoleLabel(?string $role): string
{
    if ($role === 'sales') return 'Sales';
    if ($role === 'designer') return 'Designer';
    if ($role === 'accounting') return 'Accounting';
    return '—';
}

function acctListBaseQuery(): string
{
    global $conn;
    $paySql = acctPaymentsReady($conn)
        ? "(SELECT COALESCE(SUM(dp.amount), 0) FROM noblecrm_deposit_payment dp WHERE dp.quotation_id = q.id) AS paid_total,
           (SELECT COUNT(*) FROM noblecrm_deposit_payment dp WHERE dp.quotation_id = q.id) AS payment_count,"
        : "COALESCE(q.deposit_amount, 0) AS paid_total, IF(q.deposit_amount > 0, 1, 0) AS payment_count,";

    return "
        SELECT
            {$paySql}
            q.id, q.inquiry_id,
            q.design_2d_path, q.design_2d_uploaded_by, q.design_2d_uploaded_role,
            q.design_2d_review_status, q.design_2d_remarks,
            q.quotation_path, q.quotation_uploaded_by, q.quotation_uploaded_role,
            q.quotation_review_status, q.quotation_remarks,
            q.include_3d, q.design_3d_stage, q.design_3d_path,
            q.design_3d_uploaded_by, q.design_3d_uploaded_role,
            q.design_3d_review_status, q.design_3d_remarks,
            q.status, q.reviewed_at, q.submitted_at,
            q.deposit_status, q.deposit_slip_path, q.deposit_amount,
            q.deposit_payment_method_id, q.deposit_uploaded_by,
            q.deposit_uploaded_role, q.deposit_uploaded_at,
            i.control_no, i.client_name, i.contact_number, i.project_type, i.branch,
            i.address, i.project_scope, i.measuring_space, i.measurement_datetime,
            i.contract_amount, i.designer_id, i.sales_staff_id,
            d.name AS designer_name,
            s.name AS sales_staff_name,
            d2u.name AS design_2d_uploader_name,
            qtu.name AS quotation_uploader_name,
            d3u.name AS design_3d_uploader_name,
            depu.name AS deposit_uploader_name,
            pm.name AS deposit_payment_method_name,
            -- Iisang notification lang bawat inquiry (unread muna, saka pinakabago).
            -- Dati ay LEFT JOIN, kaya nadodoble ang row kapag may 2+ notification ang user sa iisang inquiry.
            (SELECT n.id FROM noblenotification n
              WHERE n.request_id = q.inquiry_id AND n.user_id = ?
              ORDER BY n.is_read ASC, n.id DESC LIMIT 1) AS notification_id,
            (SELECT n.is_read FROM noblenotification n
              WHERE n.request_id = q.inquiry_id AND n.user_id = ?
              ORDER BY n.is_read ASC, n.id DESC LIMIT 1) AS is_read
        FROM noblecrm_2dquotation q
        JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM noblecrm_2dquotation
            GROUP BY inquiry_id
        ) latest ON latest.inquiry_id = q.inquiry_id AND latest.max_id = q.id
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole d ON d.id = i.designer_id
        LEFT JOIN noblerole s ON s.id = i.sales_staff_id
        LEFT JOIN noblerole d2u ON d2u.id = q.design_2d_uploaded_by
        LEFT JOIN noblerole qtu ON qtu.id = q.quotation_uploaded_by
        LEFT JOIN noblerole d3u ON d3u.id = q.design_3d_uploaded_by
        LEFT JOIN noblerole depu ON depu.id = q.deposit_uploaded_by
        LEFT JOIN noblecrm_paymentmethod pm ON pm.id = q.deposit_payment_method_id
    ";
}

function acctListFormatRow(array $row): array
{
    // Whether the 3D section is worth showing at all — either this
    // submission bundled a 3D file, or the sequential 3D stage has moved
    // past its initial 'Locked' default.
    $show3d = ((int) $row['include_3d'] === 1) || (($row['design_3d_stage'] ?? 'Locked') !== 'Locked');

    return [
        'id'                    => (int) $row['id'],
        'inquiry_id'            => (int) $row['inquiry_id'],
        'control_no'            => $row['control_no'],
        'client_name'           => $row['client_name'],
        'contact_number'        => $row['contact_number'],
        'project_type'          => $row['project_type'],
        'branch'                => $row['branch'],
        'address'               => $row['address'],
        'project_scope'         => $row['project_scope'],
        'measuring_space'       => $row['measuring_space'],
        'measurement_datetime'  => $row['measurement_datetime'],
        'contract_amount'       => $row['contract_amount'],
        'designer_name'         => $row['designer_name'],
        'sales_staff_name'      => $row['sales_staff_name'],

        'design_2d_path'          => $row['design_2d_path'],
        'design_2d_uploader_name' => $row['design_2d_uploader_name'] ?? '—',
        'design_2d_uploaded_role' => acctListRoleLabel($row['design_2d_uploaded_role']),
        'design_2d_review_status' => $row['design_2d_review_status'] ?? 'Pending',
        'design_2d_remarks'       => $row['design_2d_remarks'],

        'quotation_path'          => $row['quotation_path'],
        'quotation_uploader_name' => $row['quotation_uploader_name'] ?? '—',
        'quotation_uploaded_role' => acctListRoleLabel($row['quotation_uploaded_role']),
        'quotation_review_status' => $row['quotation_review_status'] ?? 'Pending',
        'quotation_remarks'       => $row['quotation_remarks'],

        'include_3d'              => (bool) $row['include_3d'],
        'show_3d'                 => $show3d,
        'design_3d_stage'         => $row['design_3d_stage'] ?? 'Locked',
        'design_3d_path'          => $row['design_3d_path'],
        'design_3d_uploader_name' => $row['design_3d_uploader_name'] ?? '—',
        'design_3d_uploaded_role' => acctListRoleLabel($row['design_3d_uploaded_role']),
        'design_3d_review_status' => $row['design_3d_review_status'] ?? 'Pending',
        'design_3d_remarks'       => $row['design_3d_remarks'],

        'status'                => $row['status'],
        'reviewed_at'           => $row['reviewed_at'],
        'submitted_at'          => $row['submitted_at'],

        // Deposit slip / Notice to Proceed
        'deposit_status'              => $row['deposit_status'] ?? 'Hold',
        'deposit_slip_path'           => $row['deposit_slip_path'],
        'deposit_amount'              => $row['deposit_amount'],
        'deposit_payment_method_id'   => $row['deposit_payment_method_id'] !== null ? (int) $row['deposit_payment_method_id'] : null,
        'deposit_payment_method_name' => $row['deposit_payment_method_name'],
        'deposit_uploader_name'       => $row['deposit_uploader_name'] ?? '—',
        'deposit_uploaded_role'       => acctListRoleLabel($row['deposit_uploaded_role']),
        'deposit_uploaded_at'         => $row['deposit_uploaded_at'],

        // Mga bayad (downpayment + dagdag na bayad)
        'paid_total'            => (float) ($row['paid_total'] ?? 0),
        'payment_count'         => (int) ($row['payment_count'] ?? 0),
        'balance'               => max(0, (float) ($row['contract_amount'] ?? 0) - (float) ($row['paid_total'] ?? 0)),

        // Walang notification row (n.id null) = matagal nang record bago
        // pa naidagdag ang notification hook — treat as read na lang.
        'is_read'               => $row['notification_id'] === null ? 1 : (int) $row['is_read'],
    ];
}

// Notifies every member of each given department role that a submission
// is now Notice to Proceed and ready for production. Same "whole
// department, not just head" pattern as check2dquotationajax.php's
// chk2dNotifyRoleRevision().
function acctListNotifyDepartmentsNTP(mysqli $conn, int $inquiryId, array $record, int $senderId): void
{
    $message = "Notice to Proceed: {$record['client_name']} (Control No. {$record['control_no']}) — deposit received, ready for production.";
    $link = "/crmcuttinglist?id={$inquiryId}"; // ⚠️ verify actual destination route per department
    $controlNo = $record['control_no'];

    $stmt = $conn->prepare("SELECT id FROM noblerole WHERE role = ?"); // ⚠️ verify column name `role`
    $insertStmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_deposit_ntp', ?, 0, NOW(), ?, ?)
    ");

    foreach (ACCT_NTP_NOTIFY_ROLES as $role) {
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($members as $member) {
            $memberId = (int) $member['id'];
            $insertStmt->bind_param('iissis', $memberId, $inquiryId, $controlNo, $message, $senderId, $link);
            $insertStmt->execute();
        }
    }

    $stmt->close();
    $insertStmt->close();
}

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');
    $filter = trim($_GET['filter'] ?? 'unread');

    $sql = acctListBaseQuery() . " WHERE q.status = 'Approved' ";
    $types = 'ii';
    $params = [$currentUserId, $currentUserId];

    if ($filter === 'unread') {
        $sql .= " AND EXISTS (SELECT 1 FROM noblenotification n2
                              WHERE n2.request_id = q.inquiry_id AND n2.user_id = ? AND n2.is_read = 0) ";
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($search !== '') {
        $sql .= " AND (i.control_no LIKE ? OR i.client_name LIKE ? OR i.contact_number LIKE ? OR i.branch LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY (q.reviewed_at IS NULL), q.reviewed_at DESC LIMIT 200 ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = acctListFormatRow($row);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'rows'    => $rows,
        'count'   => count($rows),
        'server_time' => date('c'),
    ]);
    exit;
}

if ($action === 'detail') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $sql = acctListBaseQuery() . " WHERE q.id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $currentUserId, $currentUserId, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    if ($row['status'] !== 'Approved') {
        echo json_encode(['success' => false, 'message' => 'Not yet approved.']);
        exit;
    }

    // Basahin na LAHAT ng notification ng user na ito para sa inquiry (para hindi bumalik ang "New")
    if ($row['notification_id'] !== null && (int) $row['is_read'] === 0) {
        $inqId = (int) $row['inquiry_id'];
        $stmt = $conn->prepare("UPDATE noblenotification SET is_read = 1 WHERE request_id = ? AND user_id = ? AND is_read = 0");
        $stmt->bind_param('ii', $inqId, $currentUserId);
        $stmt->execute();
        $stmt->close();
        $row['is_read'] = 1;
    }

    $payments = [];
    if (acctPaymentsReady($conn)) {
        $st = $conn->prepare("
            SELECT dp.id, dp.amount, dp.paid_on, dp.reference_no, dp.note, dp.slip_path, dp.created_at,
                   pm.name AS method_name, u.name AS created_by_name
            FROM noblecrm_deposit_payment dp
            LEFT JOIN noblecrm_paymentmethod pm ON pm.id = dp.payment_method_id
            LEFT JOIN noblerole u ON u.id = dp.created_by
            WHERE dp.quotation_id = ?
            ORDER BY COALESCE(dp.paid_on, DATE(dp.created_at)) ASC, dp.id ASC
        ");
        $st->bind_param('i', $id);
        $st->execute();
        $payments = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        foreach ($payments as &$pp) {
            $pp['id'] = (int) $pp['id'];
            $pp['amount'] = (float) $pp['amount'];
        }
        unset($pp);
    } elseif (!empty($row['deposit_amount'])) {
        // wala pang table: ang dating isang deposit ang ipapakita
        $payments[] = [
            'id' => 0, 'amount' => (float) $row['deposit_amount'], 'paid_on' => substr((string) $row['deposit_uploaded_at'], 0, 10),
            'reference_no' => null, 'note' => null, 'slip_path' => $row['deposit_slip_path'], 'created_at' => $row['deposit_uploaded_at'],
            'method_name' => $row['deposit_payment_method_name'], 'created_by_name' => $row['deposit_uploader_name'],
        ];
    }

    echo json_encode([
        'success' => true,
        'record'  => acctListFormatRow($row),
        'payments' => $payments,
        'payments_ready' => acctPaymentsReady($conn),
    ]);
    exit;
}

// Active payment methods for the deposit form's dropdown.
if ($action === 'payment_methods') {
    $stmt = $conn->prepare("SELECT id, name FROM noblecrm_paymentmethod WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// PAYMENTS — isang bayad bawat resibo (puwedeng maraming beses)
// ═══════════════════════════════════════════════════════════
if ($action === 'add_payment') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') acctJson(['success' => false, 'message' => 'Invalid request.']);
    if (!acctPaymentsReady($conn)) acctJson(['success' => false, 'message' => 'Run noblecrm_deposit_payment.sql first to enable payments.']);

    $id = intval($_POST['id'] ?? 0);
    $amountRaw = str_replace(',', '', trim((string) ($_POST['amount'] ?? '')));
    $methodId = intval($_POST['payment_method_id'] ?? 0);
    $paidOn = trim((string) ($_POST['paid_on'] ?? ''));
    $refNo = mb_substr(trim((string) ($_POST['reference_no'] ?? '')), 0, 100);
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);

    if ($id <= 0) acctJson(['success' => false, 'message' => 'Invalid record.']);
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) acctJson(['success' => false, 'message' => 'Please enter a valid amount.']);
    $amount = round((float) $amountRaw, 2);
    if ($amount > ACCT_PAYMENT_MAX_AMOUNT) acctJson(['success' => false, 'message' => 'The amount is too large.']);
    if ($methodId <= 0) acctJson(['success' => false, 'message' => 'Please select a payment method.']);
    $d = DateTime::createFromFormat('Y-m-d', $paidOn);
    if (!$d || $d->format('Y-m-d') !== $paidOn) acctJson(['success' => false, 'message' => 'Please enter the payment date.']);
    if ($paidOn > date('Y-m-d')) acctJson(['success' => false, 'message' => 'The payment date cannot be in the future.']);

    $rec = acctApprovedRecord($conn, $id);

    $st = $conn->prepare("SELECT id FROM noblecrm_paymentmethod WHERE id = ? AND is_active = 1 LIMIT 1");
    $st->bind_param('i', $methodId);
    $st->execute();
    $pmOk = (bool) $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pmOk) acctJson(['success' => false, 'message' => 'Please select a valid payment method.']);

    $slipPath = acctSaveSlip($_FILES['slip'] ?? [], $id);
    $inquiryId = (int) $rec['inquiry_id'];
    $refNo = $refNo !== '' ? $refNo : null;
    $note = $note !== '' ? $note : null;

    $st = $conn->prepare("
        INSERT INTO noblecrm_deposit_payment
            (quotation_id, inquiry_id, amount, payment_method_id, paid_on, reference_no, note, slip_path, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $st->bind_param('iidissssi', $id, $inquiryId, $amount, $methodId, $paidOn, $refNo, $note, $slipPath, $currentUserId);
    $ok = $st->execute();
    $st->close();
    if (!$ok) acctJson(['success' => false, 'message' => 'Failed to save the payment.']);

    $tot = acctPaymentTotals($conn, $id);
    // Kapag NTP na: panatilihing tama ang kabuuang deposit sa record
    if ($rec['deposit_status'] === 'Notice to Proceed') {
        $st = $conn->prepare("UPDATE noblecrm_2dquotation SET deposit_amount = ? WHERE id = ?");
        $st->bind_param('di', $tot['total'], $id);
        $st->execute();
        $st->close();
    }

    acctJson(['success' => true, 'message' => 'Payment recorded.', 'paid_total' => $tot['total'], 'payment_count' => $tot['count']]);
}

// Burahin ang maling naitalang bayad — habang hindi pa Notice to Proceed lang
if ($action === 'delete_payment') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') acctJson(['success' => false, 'message' => 'Invalid request.']);
    if (!acctPaymentsReady($conn)) acctJson(['success' => false, 'message' => 'Payments are not enabled yet.']);
    $id = intval($_POST['id'] ?? 0);
    $paymentId = intval($_POST['payment_id'] ?? 0);
    if ($id <= 0 || $paymentId <= 0) acctJson(['success' => false, 'message' => 'Invalid payment.']);

    $rec = acctApprovedRecord($conn, $id);
    if ($rec['deposit_status'] === 'Notice to Proceed') {
        acctJson(['success' => false, 'message' => 'Payments are locked after the Notice to Proceed.']);
    }

    $st = $conn->prepare("SELECT slip_path FROM noblecrm_deposit_payment WHERE id = ? AND quotation_id = ? LIMIT 1");
    $st->bind_param('ii', $paymentId, $id);
    $st->execute();
    $pay = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pay) acctJson(['success' => false, 'message' => 'Payment not found.']);

    $st = $conn->prepare("DELETE FROM noblecrm_deposit_payment WHERE id = ? AND quotation_id = ?");
    $st->bind_param('ii', $paymentId, $id);
    $st->execute();
    $st->close();

    // burahin din ang file, kung nasa deposit-slips folder at wala nang ibang gumagamit
    $path = (string) ($pay['slip_path'] ?? '');
    if ($path !== '' && str_starts_with($path, ACCT_DEPOSIT_UPLOAD_URL_PREFIX . '/')) {
        $st = $conn->prepare("SELECT
            (SELECT COUNT(*) FROM noblecrm_deposit_payment WHERE slip_path = ?) +
            (SELECT COUNT(*) FROM noblecrm_2dquotation WHERE deposit_slip_path = ?) AS n");
        $st->bind_param('ss', $path, $path);
        $st->execute();
        $still = (int) ($st->get_result()->fetch_assoc()['n'] ?? 0);
        $st->close();
        $abs = ACCT_DEPOSIT_UPLOAD_DIR . '/' . basename($path);
        if ($still === 0 && is_file($abs)) @unlink($abs);
    }

    $tot = acctPaymentTotals($conn, $id);
    acctJson(['success' => true, 'message' => 'Payment removed.', 'paid_total' => $tot['total'], 'payment_count' => $tot['count']]);
}

// ═══════════════════════════════════════════════════════════
// NOTICE TO PROCEED — huling hakbang (double confirmation sa page)
// ═══════════════════════════════════════════════════════════
if ($action === 'give_ntp') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') acctJson(['success' => false, 'message' => 'Invalid request.']);
    if (!acctPaymentsReady($conn)) acctJson(['success' => false, 'message' => 'Run noblecrm_deposit_payment.sql first to enable payments.']);
    $id = intval($_POST['id'] ?? 0);
    if (($_POST['confirm'] ?? '') !== 'NTP') acctJson(['success' => false, 'message' => 'Please confirm the Notice to Proceed.']);
    if ($id <= 0) acctJson(['success' => false, 'message' => 'Invalid record.']);

    $rec = acctApprovedRecord($conn, $id);
    if ($rec['deposit_status'] === 'Notice to Proceed') acctJson(['success' => false, 'message' => 'The Notice to Proceed was already given.']);

    $tot = acctPaymentTotals($conn, $id);
    if ($tot['count'] < 1 || $tot['total'] <= 0) acctJson(['success' => false, 'message' => 'Record at least one payment with its receipt first.']);

    // pinakahuling bayad = "deposit" na makikita ng ibang page
    $st = $conn->prepare("SELECT payment_method_id, slip_path FROM noblecrm_deposit_payment WHERE quotation_id = ? ORDER BY id DESC LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $last = $st->get_result()->fetch_assoc();
    $st->close();

    $uploadedRole = 'accounting';
    $methodId = $last['payment_method_id'] !== null ? (int) $last['payment_method_id'] : null;
    $slip = $last['slip_path'];
    $st = $conn->prepare("
        UPDATE noblecrm_2dquotation
        SET deposit_status = 'Notice to Proceed',
            deposit_amount = ?, deposit_slip_path = ?, deposit_payment_method_id = ?,
            deposit_uploaded_by = ?, deposit_uploaded_role = ?, deposit_uploaded_at = NOW()
        WHERE id = ? AND status = 'Approved' AND COALESCE(deposit_status, 'Hold') <> 'Notice to Proceed'
    ");
    $st->bind_param('dsiisi', $tot['total'], $slip, $methodId, $currentUserId, $uploadedRole, $id);
    $st->execute();
    $changed = $st->affected_rows;
    $st->close();
    if ($changed !== 1) acctJson(['success' => false, 'message' => 'The Notice to Proceed was already given. Please reload.']);

    acctListNotifyDepartmentsNTP($conn, (int) $rec['inquiry_id'], [
        'control_no' => $rec['control_no'],
        'client_name' => $rec['client_name'],
    ], $currentUserId);

    acctJson(['success' => true, 'message' => 'Notice to Proceed given. Cutting List, Operations and Design have been notified.']);
}

// Dating isang-hakbang na deposit → pinalitan na ng add_payment + give_ntp
if ($action === 'upload_deposit') {
    acctJson(['success' => false, 'message' => 'Please reload the page — deposits are now recorded as payments, then the Notice to Proceed is given separately.']);
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);