<?php
// profileupdateajax.php — i-update ang existing nobleid record (HR only)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

require_once ROOT_PATH . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message = '', array $errors = []): void
{
    echo json_encode(['success' => $success, 'message' => $message, 'errors' => $errors]);
    exit;
}

/** Burahin ang file kung nasa uploads/nobleid lang. */
function removeUpload(?string $rel): void
{
    if (!$rel || strpos($rel, 'uploads/nobleid/') !== 0) {
        return;
    }
    $full = ROOT_PATH . '/' . $rel;
    if (is_file($full)) {
        unlink($full);
    }
}

/** Validate + i-save ang uploaded image. Return relative path o null. */
function saveImage(string $field, string $prefix, array &$errors): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null; // optional
    }

    $file = $_FILES[$field];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = ucfirst($field) . ': file is larger than the server limit.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ucfirst($field) . ': upload failed.';
        return null;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        $errors[] = ucfirst($field) . ': max file size is 10MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $errors[] = ucfirst($field) . ': only JPG, PNG or WEBP allowed.';
        return null;
    }

    $dir = ROOT_PATH . '/uploads/nobleid/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $errors[] = 'Cannot create upload folder.';
        return null;
    }

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        $errors[] = ucfirst($field) . ': cannot save file.';
        return null;
    }

    return 'uploads/nobleid/' . $name;
}

/** Mag-generate ng QR galing sa text. PNG kung may GD, SVG kung wala. */
function generateQr(string $text, string $prefix, array &$errors): ?string
{
    $dir = ROOT_PATH . '/uploads/nobleid/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $errors[] = 'Cannot create upload folder.';
        return null;
    }

    $usePng = extension_loaded('gd');

    try {
        $options = new QROptions([
            'outputType'   => $usePng ? QRCode::OUTPUT_IMAGE_PNG : QRCode::OUTPUT_MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel'     => QRCode::ECC_M,
            'scale'        => 10,
        ]);
        $out = (new QRCode($options))->render($text);
    } catch (Throwable $e) {
        error_log('QR generate failed: ' . $e->getMessage());
        $errors[] = 'QR: cannot generate code.';
        return null;
    }

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . ($usePng ? '.png' : '.svg');
    if (file_put_contents($dir . $name, $out) === false) {
        $errors[] = 'QR: cannot save file.';
        return null;
    }

    return 'uploads/nobleid/' . $name;
}

// ── Method + CSRF ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(403);
    respond(false, 'Invalid request. Please refresh the page and try again.');
}

// ── Collect ───────────────────────────────────────────────────
$orig = trim($_POST['orig_employeeid'] ?? '');
if ($orig === '') {
    respond(false, 'Invalid request.');
}

$data = [];
foreach (['employeeid', 'fullname', 'position', 'emp_level', 'contactno', 'address', 'sss', 'tin', 'datestarted'] as $k) {
    $data[$k] = trim($_POST[$k] ?? '');
}

$errors = [];

$required = [
    'employeeid' => 'Employee ID',
    'fullname' => 'Full name',
    'position' => 'Position',
    'emp_level' => 'Employee level',
    'contactno' => 'Contact no.',
    'address' => 'Address',
    'datestarted' => 'Date started',
];
foreach ($required as $k => $label) {
    if ($data[$k] === '') {
        $errors[] = "$label is required.";
    }
}

if ($data['emp_level'] !== '' && !in_array($data['emp_level'], ['staff', 'head'], true)) {
    $errors[] = 'Employee level must be Staff or Head.';
}
if ($data['contactno'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $data['contactno'])) {
    $errors[] = 'Contact no. is invalid.';
}
if ($data['sss'] !== '' && !preg_match('/^[0-9\-]{8,20}$/', $data['sss'])) {
    $errors[] = 'SSS no. is invalid (numbers and dashes only).';
}
if ($data['tin'] !== '' && !preg_match('/^[0-9\-]{9,20}$/', $data['tin'])) {
    $errors[] = 'TIN is invalid (numbers and dashes only).';
}
$d = DateTime::createFromFormat('Y-m-d', $data['datestarted']);
if ($data['datestarted'] !== '' && (!$d || $d->format('Y-m-d') !== $data['datestarted'])) {
    $errors[] = 'Date started is invalid.';
}

if ($errors) {
    respond(false, '', $errors);
}

// ── Kunin ang existing record ─────────────────────────────────
$sel = $conn->prepare("SELECT qr, photo, signature FROM nobleid WHERE employeeid = ? LIMIT 1");
$sel->bind_param("s", $orig);
$sel->execute();
$old = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$old) {
    respond(false, '', ['Profile not found. Please refresh the page.']);
}

// ── Duplicate check kung pinalitan ang employee ID ────────────
$idChanged = $data['employeeid'] !== $orig;

if ($idChanged) {
    $chk = $conn->prepare("SELECT 1 FROM nobleid WHERE employeeid = ? LIMIT 1");
    $chk->bind_param("s", $data['employeeid']);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();

    if ($exists) {
        respond(false, '', ['Employee ID already exists.']);
    }
}

// ── Files ─────────────────────────────────────────────────────
$newQr    = null;
$newPhoto = null;
$newSig   = null;

if ($idChanged || empty($old['qr'])) {
    // pareho ng format sa profileajax.php (link papunta sa verifyid)
    $newQr = generateQr(BASE_URL . '/verifyid?id=' . rawurlencode($data['employeeid']), 'qr', $errors);
}
$newPhoto = saveImage('photo', 'photo', $errors);
$newSig   = saveImage('signature', 'sig', $errors);

if ($errors) {
    removeUpload($newQr);
    removeUpload($newPhoto);
    removeUpload($newSig);
    respond(false, '', $errors);
}

$qrPath        = $newQr ?? $old['qr'];
$photoPath     = $newPhoto ?? $old['photo'];
$signaturePath = $newSig ?? $old['signature'];

// ── Update ────────────────────────────────────────────────────
$sss = $data['sss'] !== '' ? $data['sss'] : null;
$tin = $data['tin'] !== '' ? $data['tin'] : null;

$stmt = $conn->prepare("
    UPDATE nobleid
    SET employeeid = ?, fullname = ?, position = ?, emp_level = ?, qr = ?, photo = ?, signature = ?,
        contactno = ?, address = ?, sss = ?, tin = ?, datestarted = ?
    WHERE employeeid = ?
");
$stmt->bind_param(
    "sssssssssssss",
    $data['employeeid'],
    $data['fullname'],
    $data['position'],
    $data['emp_level'],
    $qrPath,
    $photoPath,
    $signaturePath,
    $data['contactno'],
    $data['address'],
    $sss,
    $tin,
    $data['datestarted'],
    $orig
);

if ($stmt->execute()) {
    $stmt->close();

    // matagumpay: burahin ang mga napalitang lumang file
    if ($newQr)    { removeUpload($old['qr']); }
    if ($newPhoto) { removeUpload($old['photo']); }
    if ($newSig)   { removeUpload($old['signature']); }

    respond(true, 'Profile updated successfully.');
}

$stmt->close();

// failed: burahin ang mga bagong gawang file
removeUpload($newQr);
removeUpload($newPhoto);
removeUpload($newSig);
respond(false, '', ['Database error. Please try again.']);