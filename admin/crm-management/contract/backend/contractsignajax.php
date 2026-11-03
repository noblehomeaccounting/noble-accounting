<?php
// contractsignajax.php — PUBLIC (walang login): pirma ng client mula sa link
// Kailangan: valid na token, status "For Client Signing", pangalan, e-signature (PNG), at picture ng ID.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include_once ROOT_PATH . '/admin/crm-management/contract/contract-template.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function nhcsRespond(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    nhcsRespond(false, 'Invalid request.');
}
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    nhcsRespond(false, 'The ID photo is too large. Please take a smaller photo and try again.');
}

$token = (string) ($_POST['t'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    nhcsRespond(false, 'This signing link is invalid.');
}

$c = nhcLoad($conn, 'c.client_token = ?', 's', [$token]);
if (!$c || $c['status'] !== 'For Client Signing') {
    nhcsRespond(false, 'This signing link is no longer active. Please contact your NobleHome sales representative.');
}

// ── validation ──
$name = trim(preg_replace('/\s+/', ' ', (string) ($_POST['client_name'] ?? '')));
if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    nhcsRespond(false, 'Please type your full name.');
}
if (empty($_POST['agree'])) {
    nhcsRespond(false, 'Please confirm that you have read and agree to the contract.');
}

// Signature (data:image/png;base64,...)
$sigData = (string) ($_POST['signature'] ?? '');
if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $sigData, $m)) {
    nhcsRespond(false, 'Please sign in the signature box.');
}
$sigBin = base64_decode($m[1], true);
if ($sigBin === false || strlen($sigBin) < 200 || strlen($sigBin) > 2 * 1024 * 1024) {
    nhcsRespond(false, 'The signature could not be read. Please sign again.');
}
$sigInfo = @getimagesizefromstring($sigBin);
if (!$sigInfo || $sigInfo['mime'] !== 'image/png') {
    nhcsRespond(false, 'The signature could not be read. Please sign again.');
}

// ID photo
$f = $_FILES['id_photo'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    nhcsRespond(false, 'Please take or upload a photo of your valid ID.');
}
if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    nhcsRespond(false, 'The ID photo could not be uploaded. Please try again.');
}
if ($f['size'] > 10 * 1024 * 1024) {
    nhcsRespond(false, 'The ID photo is too large (max 10MB).');
}
$idInfo = @getimagesize($f['tmp_name']);
if (!$idInfo || !in_array($idInfo['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    nhcsRespond(false, 'The ID must be a photo (JPG, PNG or WEBP).');
}

// ── save sa private folder ──
$dirRel = trim(NHC_PRIVATE_DIR, '/');
$dirAbs = ROOT_PATH . '/' . $dirRel;
if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true)) {
    nhcsRespond(false, 'Could not save your signature. Please try again later.');
}
if (!is_file($dirAbs . '/.htaccess')) {
    @file_put_contents($dirAbs . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
}

$base = 'contract_' . (int) $c['inquiry_id'] . '_' . time() . '_' . bin2hex(random_bytes(6));
$sigRel = $dirRel . '/' . $base . '_sig.png';
$idRel = '';

if (file_put_contents(ROOT_PATH . '/' . $sigRel, $sigBin) === false) {
    nhcsRespond(false, 'Could not save your signature. Please try again later.');
}

// I-re-encode ang ID (tinatanggal ang metadata/GPS ng camera) kung may GD; kung wala, kopya lang
$saved = false;
if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
    $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if ($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1800;
        if ($w > $max || $h > $max) {
            $scale = $max / max($w, $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $res = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($res, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $res;
        }
        $idRel = $dirRel . '/' . $base . '_id.jpg';
        $saved = imagejpeg($img, ROOT_PATH . '/' . $idRel, 85);
        imagedestroy($img);
    }
}
if (!$saved) {
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$idInfo['mime']];
    $idRel = $dirRel . '/' . $base . '_id.' . $ext;
    $saved = move_uploaded_file($f['tmp_name'], ROOT_PATH . '/' . $idRel);
}
if (!$saved) {
    @unlink(ROOT_PATH . '/' . $sigRel);
    nhcsRespond(false, 'Could not save your ID photo. Please try again.');
}

$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$id = (int) $c['id'];
$st = $conn->prepare("
    UPDATE noblecrm_contract
    SET status = 'For GM Signing', client_signed_name = ?, client_signature_path = ?, client_id_path = ?,
        client_signed_at = NOW(), client_ip = ?, updated_at = NOW()
    WHERE id = ? AND status = 'For Client Signing' AND client_token = ?
");
$st->bind_param("ssssis", $name, $sigRel, $idRel, $ip, $id, $token);
$st->execute();
$ok = $st->affected_rows === 1;
$st->close();

if (!$ok) {
    @unlink(ROOT_PATH . '/' . $sigRel);
    @unlink(ROOT_PATH . '/' . $idRel);
    nhcsRespond(false, 'This signing link is no longer active. Please contact your NobleHome sales representative.');
}

// Abiso: Superadmin (GM) para sa huling pirma + Sales/Designer
try {
    $ids = [];
    $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ?");
    $r = ROLE_SUPERADMIN;
    $st->bind_param("s", $r);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $ids[] = (int) $row['id'];
    $st->close();
    $ids[] = (int) $c['sales_user_id'];
    $ids[] = (int) $c['designer_user_id'];

    $inq = (int) $c['inquiry_id'];
    $ctrl = (string) $c['control_no'];
    $msg = "Client signed the contract for {$ctrl}. Waiting for the General Manager's signature.";
    $link = "/crmcontracts?inquiry={$inq}";
    $sender = 0;
    $st = $conn->prepare("
        INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm_contract', ?, 0, NOW(), ?, ?)
    ");
    $uid = 0;
    $st->bind_param("iissis", $uid, $inq, $ctrl, $msg, $sender, $link);
    foreach (array_unique(array_filter($ids)) as $uid) {
        $st->execute();
    }
    $st->close();
} catch (Throwable $e) {
    error_log('contractsignajax notify: ' . $e->getMessage());
}

try {
    include_once ROOT_PATH . '/network/realtime-helper.php';
    nhRtInquiry($conn, (int) $c['inquiry_id'], [[ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_SUPERADMIN, null]], 'crm_contract');
} catch (Throwable $e) {
    error_log('contractsignajax realtime: ' . $e->getMessage());
}

nhcsRespond(true, 'Thank you! Your signature has been received.');