<?php
// adminprofile.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_IT, ROLE_OPERATIONS, ROLE_HR, ROLE_SALES, ROLE_GRAPHIC, ROLE_DESIGNER, ROLE_SUPERADMIN, ROLE_CUTTING, ROLE_PO, ROLE_PC];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers (unique ang pangalan para walang conflict sa sidebar/ibang include) ─
function apEsc($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function apImgUrl(?string $p): string
{
    return $p ? BASE_URL . '/' . ltrim($p, '/') : '';
}
// URL lang kung nag-eexist talaga ang file (para hindi broken image)
function apImgUrlIfExists(?string $p): string
{
    if (!$p || !is_file(ROOT_PATH . '/' . ltrim($p, '/'))) {
        return '';
    }
    return apImgUrl($p);
}
// Check kung may column sa table (para hindi mag-error sa optional columns)
function apHasCol(mysqli $conn, string $table, string $col): bool
{
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}
function apFlash(string $type, string $msg, string $tab): void
{
    $_SESSION['ap_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#' . $tab);
    exit;
}
// Isang "info tile" (icon + label + value)
function apTile(string $icon, string $label, string $value, string $extra = ''): string
{
    $v = trim($value) !== '' ? apEsc($value) : '<span class="text-gray-300 font-normal">Not set</span>';
    return '<div class="flex items-start gap-3 rounded-xl border border-gray-100 bg-slate-50/60 px-4 py-3 ' . $extra . '">'
        . '<div class="w-9 h-9 rounded-lg bg-white border border-gray-100 flex items-center justify-center shrink-0"><i class="' . $icon . ' text-orange-500 text-sm"></i></div>'
        . '<div class="min-w-0"><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">' . apEsc($label) . '</p>'
        . '<p class="text-sm font-semibold text-gray-800 mt-0.5 break-words">' . $v . '</p></div></div>';
}

// ── Hanapin ang noblerole.id ng naka-login na admin ───────────
// PALITAN ang session key kung ibang key ang gamit ng admin login mo para sa noblerole.id
$roleId = (int) ($_SESSION['admin_id'] ?? $_SESSION['role_id'] ?? $_SESSION['user_id'] ?? 0);

// Fallback: hanapin ang noblerole.id gamit ang email sa session
if ($roleId <= 0 && !empty($_SESSION['email'])) {
    $q = $conn->prepare("SELECT id FROM noblerole WHERE email = ? LIMIT 1");
    $q->bind_param("s", $_SESSION['email']);
    $q->execute();
    $found = $q->get_result()->fetch_assoc();
    $q->close();
    $roleId = (int) ($found['id'] ?? 0);
}

// ── noblerole row (account info) ──────────────────────────────
$acct = null;
if ($roleId > 0) {
    $stmt = $conn->prepare("SELECT id, name, email, role, position, branch, created_at, last_active FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

// ── Kunin ang ID na naka-link sa role na ito (nobleid.role_id) ─
$emp = null;
if ($roleId > 0) {
    $stmt = $conn->prepare("SELECT * FROM nobleid WHERE role_id = ? LIMIT 1");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

// Optional columns sa nobleid
$hasEmgName = apHasCol($conn, 'nobleid', 'emergency_name');
$hasEmgNo   = apHasCol($conn, 'nobleid', 'emergency_contact');

// ── POST handlers ─────────────────────────────────────────────
if (empty($_SESSION['ap_csrf'])) {
    $_SESSION['ap_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acct) {
    if (!hash_equals($_SESSION['ap_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        apFlash('error', 'Invalid request. Please refresh and try again.', 'personal');
    }
    $action = $_POST['action'] ?? '';

    // 1) Update personal info (contact, address, emergency lang — HR ang may hawak ng iba)
    if ($action === 'update_info') {
        if (!$emp) {
            apFlash('error', 'No ID linked to your account yet.', 'personal');
        }
        $contact = trim($_POST['contactno'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) {
            apFlash('error', 'Invalid phone number format.', 'personal');
        }

        $sql    = "UPDATE nobleid SET contactno = ?, address = ?";
        $types  = "ss";
        $params = [$contact, $address];
        if ($hasEmgName) {
            $sql .= ", emergency_name = ?";
            $types .= "s";
            $params[] = trim($_POST['emergency_name'] ?? '');
        }
        if ($hasEmgNo) {
            $sql .= ", emergency_contact = ?";
            $types .= "s";
            $params[] = trim($_POST['emergency_contact'] ?? '');
        }
        $sql .= " WHERE role_id = ?";
        $types .= "i";
        $params[] = $roleId;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        apFlash($ok ? 'success' : 'error', $ok ? 'Personal information updated.' : 'Failed to update information.', 'personal');
    }

    // 2) Change profile photo (nobleid.photo — ito rin ang gamit sa ID card)
    if ($action === 'change_photo') {
        if (!$emp) {
            apFlash('error', 'No ID linked to your account yet.', 'personal');
        }
        $f = $_FILES['photo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            apFlash('error', 'Upload failed. Please try again.', 'personal');
        }
        if ($f['size'] > 3 * 1024 * 1024) {
            apFlash('error', 'Photo must be 3MB or smaller.', 'personal');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($exts[$mime])) {
            apFlash('error', 'Only JPG, PNG, or WEBP allowed.', 'personal');
        }

        $relDir = 'uploads/profile';
        $absDir = ROOT_PATH . '/' . $relDir;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }
        $fname = 'emp_' . $roleId . '_' . time() . '.' . $exts[$mime];
        if (!move_uploaded_file($f['tmp_name'], $absDir . '/' . $fname)) {
            apFlash('error', 'Could not save the photo.', 'personal');
        }

        // burahin ang lumang photo kung nasa uploads/profile
        $old = (string) ($emp['photo'] ?? '');
        if ($old && strpos($old, $relDir . '/') === 0 && is_file(ROOT_PATH . '/' . $old)) {
            @unlink(ROOT_PATH . '/' . $old);
        }

        $path = $relDir . '/' . $fname;
        $stmt = $conn->prepare("UPDATE nobleid SET photo = ? WHERE role_id = ?");
        $stmt->bind_param("si", $path, $roleId);
        $ok = $stmt->execute();
        $stmt->close();
        apFlash($ok ? 'success' : 'error', $ok ? 'Profile photo updated.' : 'Failed to update photo.', 'personal');
    }
}

// ── Flash ─────────────────────────────────────────────────────
$flash = $_SESSION['ap_flash'] ?? null;
unset($_SESSION['ap_flash']);

// ── View data ─────────────────────────────────────────────────
$qr = $signature = $photo = '';
$started = $joined = '';
$emgName = $emgNo = '';
$isHead = false;
$isOfficial = false;
$tenure = '';

if ($emp) {
    $qr         = apImgUrlIfExists($emp['qr'] ?? '');
    $signature  = apImgUrlIfExists($emp['signature'] ?? '');
    $photo      = apImgUrlIfExists($emp['photo'] ?? '');
    $emgName    = $emp['emergency_name'] ?? '';             // optional column
    $emgNo      = $emp['emergency_contact'] ?? '';          // optional column
    $started    = !empty($emp['datestarted']) ? date('m/d/y', strtotime($emp['datestarted'])) : '';
    $joined     = !empty($emp['datestarted']) ? date('F j, Y', strtotime($emp['datestarted'])) : '';
    $isHead     = (($emp['emp_level'] ?? 'staff') === 'head');
    $isOfficial = !empty($emp['is_official']);

    // Gaano na katagal sa company
    if (!empty($emp['datestarted'])) {
        try {
            $diff = (new DateTime($emp['datestarted']))->diff(new DateTime('today'));
            if ($diff->invert) {
                $tenure = 'Starting soon';
            } elseif ($diff->y > 0) {
                $tenure = $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m ? ' ' . $diff->m . ' mo' : '');
            } elseif ($diff->m > 0) {
                $tenure = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
            } else {
                $tenure = $diff->d . ' day' . ($diff->d === 1 ? '' : 's');
            }
        } catch (Throwable $e) {
            $tenure = '';
        }
    }
}

$fullName   = $emp['fullname'] ?? ($acct['name'] ?? 'User');
$email      = $acct['email'] ?? '';
$roleLabel  = !empty($acct['role']) ? ucwords(strtolower(str_replace(['_', '-'], ' ', $acct['role']))) : '';
$position   = $emp['position'] ?? ($acct['position'] ?? '');
$department = $emp['department'] ?? $roleLabel;            // gagamit ng nobleid.department kung meron
$contactNo  = $emp['contactno'] ?? '';
$address    = $emp['address'] ?? '';
$empId      = $emp['employeeid'] ?? '';
$branch     = $acct['branch'] ?? '';
$createdAt  = !empty($acct['created_at']) ? date('F j, Y g:i A', strtotime($acct['created_at'])) : '—';
$lastActive = !empty($acct['last_active']) ? date('F j, Y g:i A', strtotime($acct['last_active'])) : '—';
$initials   = strtoupper(substr(trim($fullName), 0, 1));
$csrf       = $_SESSION['ap_csrf'];

// ── Profile completeness ──────────────────────────────────────
$checks = [
    'Employee ID linked' => (bool) $emp,
    'Profile photo'      => $photo !== '',
    'Phone number'       => trim($contactNo) !== '',
    'Address'            => trim($address) !== '',
    'Signature'          => $signature !== '',
];
if ($hasEmgName || $hasEmgNo) {
    $checks['Emergency contact'] = trim((string) $emgName) !== '' || trim((string) $emgNo) !== '';
}
$doneCount = count(array_filter($checks));
$percent   = (int) round($doneCount / max(1, count($checks)) * 100);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <style>
        /* ───── Page ───── */
        .ap-hero {
            background:
                radial-gradient(circle at 85% 20%, rgba(255, 255, 255, .18) 0, transparent 40%),
                linear-gradient(120deg, #c2570c 0%, #f97316 55%, #fb923c 100%);
        }

        .ap-hero-logo {
            position: absolute;
            right: -30px;
            bottom: -70px;
            width: 300px;
            height: auto;
            pointer-events: none;
            -webkit-mask-image: linear-gradient(180deg, #000 55%, transparent 100%);
            mask-image: linear-gradient(180deg, #000 55%, transparent 100%);
        }

        @media (max-width: 767px) {
            .ap-hero-logo { width: 200px; right: -40px; bottom: -40px; }
        }

        .ap-hero-pattern {
            background-image: repeating-linear-gradient(135deg, rgba(255, 255, 255, .07) 0, rgba(255, 255, 255, .07) 2px, transparent 2px, transparent 14px);
        }

        .ap-ring {
            background: conic-gradient(from -90deg, #fb923c 0, #ea580c calc(var(--p) * 1%), #f1f5f9 0);
        }

        /* ───── ID CARD ───── */
        /* 1u = laki ng unit; buong card = 100u x 150u (2:3) */
        .id-stage { --u: min(3.4px, 0.85vw); perspective: 1400px; display: flex; justify-content: center; padding: 20px 0 10px; touch-action: none; user-select: none; -webkit-user-select: none; cursor: grab; }
        .id-stage.dragging { cursor: grabbing; }
        .id-card { position: relative; width: calc(var(--u) * 100); height: calc(var(--u) * 150); transform-style: preserve-3d; will-change: transform; }
        .id-face { position: absolute; inset: 0; border-radius: calc(var(--u) * 5); overflow: hidden; backface-visibility: hidden; -webkit-backface-visibility: hidden; box-shadow: 0 calc(var(--u) * 3) calc(var(--u) * 10) rgba(0, 0, 0, .45); font-family: 'Segoe UI', system-ui, sans-serif; }
        .id-back { transform: rotateY(180deg); }

        /* glare na gumagalaw habang umiikot */
        .id-shine { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(115deg, transparent calc(var(--sx, 50%) - 18%), rgba(255, 255, 255, .28) var(--sx, 50%), transparent calc(var(--sx, 50%) + 18%)); mix-blend-mode: soft-light; z-index: 20; }
        .id-ground { width: calc(var(--u) * 70); height: calc(var(--u) * 6); margin: calc(var(--u) * 10) auto 0; border-radius: 50%; background: radial-gradient(ellipse at center, rgba(0, 0, 0, .35), transparent 70%); filter: blur(4px); }

        /* FRONT */
        .id-front { background: linear-gradient(180deg, #f9ddbf 0%, #f5be86 50%, #ec8a2c 100%); color: #fff; }
        .f-building { position: absolute; left: 0; right: 0; bottom: 0; height: calc(var(--u) * 85); background-size: cover; background-position: center bottom; opacity: .75; z-index: 0; -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 100%); mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 100%); }
        .f-corner, .f-corner2 { position: absolute; top: 0; right: 0; pointer-events: none; }
        .f-corner2 { width: calc(var(--u) * 40); height: calc(var(--u) * 40); background: #f5a65b; opacity: .55; clip-path: polygon(10% 0, 100% 0, 100% 90%); }
        .f-corner { width: calc(var(--u) * 32); height: calc(var(--u) * 32); background: #e8791a; clip-path: polygon(35% 0, 100% 0, 100% 65%); }
        .f-dots { position: absolute; left: calc(var(--u) * 5); top: calc(var(--u) * 5); width: calc(var(--u) * 18); height: calc(var(--u) * 18); background-image: radial-gradient(circle, rgba(232, 121, 26, .6) calc(var(--u) * .55), transparent calc(var(--u) * .65)); background-size: calc(var(--u) * 3.2) calc(var(--u) * 3.2); pointer-events: none; }
        .f-ring, .f-ring2 { position: absolute; left: 50%; top: calc(var(--u) * 14); transform: translate(-50%, -50%); border-radius: 50%; pointer-events: none; }
        .f-ring { width: calc(var(--u) * 44); height: calc(var(--u) * 44); border: calc(var(--u) * .35) solid rgba(255, 255, 255, .6); }
        .f-ring2 { width: calc(var(--u) * 60); height: calc(var(--u) * 60); border: calc(var(--u) * .3) dashed rgba(232, 121, 26, .28); }
        .f-stripes { position: absolute; left: 0; right: 0; top: calc(var(--u) * 36); height: calc(var(--u) * 42); background: repeating-linear-gradient(135deg, rgba(255, 255, 255, .28) 0, rgba(255, 255, 255, .28) calc(var(--u) * .8), transparent calc(var(--u) * .8), transparent calc(var(--u) * 3.4)); -webkit-mask-image: linear-gradient(180deg, transparent, #000 30%, #000 70%, transparent); mask-image: linear-gradient(180deg, transparent, #000 30%, #000 70%, transparent); pointer-events: none; }
        .f-logo { position: absolute; top: calc(var(--u) * 5); left: 50%; transform: translateX(-50%); width: calc(var(--u) * 25); text-align: center; color: #e8791a; font-weight: 900; font-size: calc(var(--u) * 9); }
        .f-logo img { width: 100%; display: block; }
        .f-info { position: absolute; left: calc(var(--u) * 8); top: calc(var(--u) * 41); width: calc(var(--u) * 62); z-index: 3; color: #1b2433; }
        .f-top { display: flex; align-items: flex-end; justify-content: space-between; gap: calc(var(--u) * 2); padding-bottom: calc(var(--u) * 2); margin-bottom: calc(var(--u) * 2.4); border-bottom: calc(var(--u) * .35) solid rgba(27, 36, 51, .18); }
        .f-idbox { display: flex; flex-direction: column; min-width: 0; }
        .f-idlabel { font-size: calc(var(--u) * 2.2); font-weight: 700; letter-spacing: .22em; color: rgba(27, 36, 51, .55); }
        .f-empid { margin-top: calc(var(--u) * .6); font-size: calc(var(--u) * 3.9); font-weight: 700; letter-spacing: .08em; color: #1b2433; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .f-name { font-size: calc(var(--u) * 7.6); line-height: 1.08; font-weight: 800; letter-spacing: .01em; text-transform: uppercase; color: #1b2433; overflow-wrap: break-word; }
        .f-pos { max-width: calc(var(--u) * 26); margin-top: calc(var(--u) * 2.4); font-size: calc(var(--u) * 3.4); font-weight: 700; letter-spacing: .08em; line-height: 1.25; text-transform: uppercase; color: #a85410; }
        .f-level { flex: none; display: inline-flex; align-items: center; gap: calc(var(--u) * 1.4); padding: calc(var(--u) * 1.3) calc(var(--u) * 3); border-radius: calc(var(--u) * 1.4); font-size: calc(var(--u) * 2.8); font-weight: 700; letter-spacing: .14em; line-height: 1; white-space: nowrap; background: #fff; color: #a85410; box-shadow: inset 0 0 0 calc(var(--u) * .35) rgba(168, 84, 16, .35); }
        .f-level i { font-size: calc(var(--u) * 2.6); }
        .f-level.head { background: #c2570c; color: #fff; box-shadow: none; }
        .f-level.head i { color: #ffe2c4; }
        .f-qr { position: absolute; left: calc(var(--u) * 8); top: calc(var(--u) * 81); width: calc(var(--u) * 23); height: calc(var(--u) * 23); background: transparent; padding: 0; overflow: hidden; z-index: 3; }
        .f-qr img { width: 100%; height: 100%; object-fit: contain; display: block; mix-blend-mode: multiply; transform: scale(1.15); }
        .f-photo { position: absolute; right: 0; bottom: calc(var(--u) * 12); width: calc(var(--u) * 66); height: calc(var(--u) * 78); display: flex; align-items: flex-end; justify-content: center; z-index: 2; -webkit-mask-image: linear-gradient(180deg, #000 80%, transparent); mask-image: linear-gradient(180deg, #000 80%, transparent); }
        .f-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top center; }
        .f-photo .ph { font-size: calc(var(--u) * 40); color: rgba(255, 255, 255, .8); }
        .f-sign { position: absolute; left: calc(var(--u) * 8); bottom: calc(var(--u) * 13); width: calc(var(--u) * 50); z-index: 4; text-align: center; }
        .f-sign .box { height: calc(var(--u) * 13.5); display: flex; align-items: flex-end; justify-content: center; }
        .f-sign img { max-height: 100%; max-width: 80%; object-fit: contain; background: rgba(255, 255, 255, .78); border-radius: calc(var(--u) * 1.8); padding: calc(var(--u) * .8); }
        .f-sign .line { border-top: calc(var(--u) * .45) solid #fff; margin-top: calc(var(--u) * 1); padding-top: calc(var(--u) * 1.2); font-size: calc(var(--u) * 3.3); font-weight: 600; letter-spacing: .08em; }
        .f-band { position: absolute; left: 0; right: 0; bottom: 0; height: calc(var(--u) * 10); z-index: 5; display: flex; align-items: center; justify-content: center; gap: calc(var(--u) * 2); overflow: hidden; background: repeating-linear-gradient(135deg, rgba(255, 255, 255, .10) 0, rgba(255, 255, 255, .10) calc(var(--u) * .9), transparent calc(var(--u) * .9), transparent calc(var(--u) * 3.2)), linear-gradient(180deg, #f08f2d, #e8791a); border-top: calc(var(--u) * .5) solid rgba(255, 255, 255, .7); box-shadow: 0 calc(var(--u) * -1) calc(var(--u) * 3) rgba(232, 121, 26, .45); }
        .f-band::before, .f-band::after { content: ''; position: absolute; top: 0; bottom: 0; pointer-events: none; }
        .f-band::before { left: 0; width: calc(var(--u) * 22); background: #f5a65b; opacity: .75; clip-path: polygon(0 0, 100% 0, 70% 100%, 0 100%); }
        .f-band::after { right: 0; width: calc(var(--u) * 22); background: #c96a12; opacity: .65; clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%); }
        .f-band .txt { position: relative; z-index: 1; font-size: calc(var(--u) * 2.9); font-weight: 700; letter-spacing: .14em; color: #fff; text-shadow: 0 1px 2px rgba(0, 0, 0, .3); white-space: nowrap; }
        .f-band .dot { position: relative; z-index: 1; width: calc(var(--u) * 1.3); height: calc(var(--u) * 1.3); border-radius: 50%; background: #fff; }

        /* BACK */
        .id-back { background: linear-gradient(135deg, #e48a2a, #c96a12); color: #fff; text-align: center; }
        .b-wrap { position: absolute; inset: 0; padding: calc(var(--u) * 5.5) calc(var(--u) * 6.7) calc(var(--u) * 4.5); display: flex; flex-direction: column; }
        .b-important { border: calc(var(--u) * .45) solid #fff; padding: calc(var(--u) * 2.5) calc(var(--u) * 2); }
        .b-important h4 { font-size: calc(var(--u) * 5.2); font-weight: 600; line-height: 1.2; margin: 0 0 calc(var(--u) * 1); }
        .b-important p { font-size: calc(var(--u) * 3.3); line-height: 1.36; margin: 0; }
        .b-label { font-size: calc(var(--u) * 4.2); font-weight: 600; line-height: 1.2; margin-top: calc(var(--u) * 4.5); }
        .b-label + .b-val + .b-label { margin-top: calc(var(--u) * 3); }
        .b-val { font-size: calc(var(--u) * 3.6); line-height: 1.25; word-break: break-word; }
        .b-band { margin: calc(var(--u) * 3) calc(var(--u) * -6.7) 0; padding: calc(var(--u) * 1.6) 0; background: rgba(0, 0, 0, .22); font-size: calc(var(--u) * 3.6); line-height: 1.33; }
        .b-rule { border-top: calc(var(--u) * .45) solid rgba(255, 255, 255, .85); margin: calc(var(--u) * 3) calc(var(--u) * -6.7) 0; }
        .b-emg { font-size: calc(var(--u) * 3.3); font-weight: 600; margin-top: calc(var(--u) * 3.3); opacity: .92; }
        .b-emg-name { font-size: calc(var(--u) * 3.9); font-weight: 600; margin-top: calc(var(--u) * 1.2); }
        .b-ceo { margin-top: auto; }
        .b-ceo .sig { height: calc(var(--u) * 9); display: flex; align-items: flex-end; justify-content: center; }
        .b-ceo .sig img { height: 100%; object-fit: contain; filter: brightness(0) invert(1); }
        .b-ceo .line { border-top: calc(var(--u) * .4) solid #fff; width: 60%; margin: calc(var(--u) * .5) auto 0; padding-top: calc(var(--u) * 1.2); font-size: calc(var(--u) * 3.6); font-weight: 600; }
        .b-foot { margin-top: calc(var(--u) * 2); font-size: calc(var(--u) * 3.3); line-height: 1.27; opacity: .92; }
        .b-foot strong { display: block; font-weight: 600; letter-spacing: .01em; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <div class="max-w-6xl mx-auto space-y-5">

            <!-- Breadcrumb / title -->
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-orange-500">Account</p>
                    <h1 class="text-2xl font-extrabold text-gray-900 leading-tight">My Profile</h1>
                    <p class="text-xs text-gray-500">Manage your personal information and account settings.</p>
                </div>
            </div>

            <?php if ($flash): ?>
                <div id="ap-flash" class="flex items-center gap-2 rounded-xl px-4 py-3 text-xs font-semibold border <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <?= apEsc($flash['msg']) ?>
                </div>
            <?php endif; ?>

            <?php if (!$acct): ?>
                <div class="max-w-md mx-auto mt-10 rounded-2xl bg-white p-6 text-center shadow-sm border border-gray-100">
                    <i class="fa-solid fa-user-slash text-3xl text-orange-500 mb-2"></i>
                    <h2 class="text-base font-bold text-gray-800">Account not found</h2>
                    <p class="text-xs text-gray-500 mt-1">We couldn't find your account. Please log in again.</p>
                </div>
            <?php else: ?>

                <!-- ═════════ HERO ═════════ -->
                <section class="relative rounded-2xl overflow-hidden shadow-sm border border-orange-200/40">
                    <div class="ap-hero h-32 md:h-36 relative">
                        <div class="absolute inset-0 ap-hero-pattern"></div>
                        <div class="absolute -right-16 -bottom-24 w-72 h-72 rounded-full bg-white/10"></div>

                        <!-- NH logo watermark -->
                        <svg class="ap-hero-logo" viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <g fill="#fff" fill-opacity=".22">
                                <polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
                                <polygon points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
                            </g>
                            <g fill="#7c2d12" fill-opacity=".22">
                                <polygon points="410,722 702,518 706,1088" />
                                <polygon points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
                            </g>
                        </svg>

                        <!-- brand text -->
                        <div class="absolute left-8 top-5 hidden md:flex items-center gap-2 text-white/85">
                            <span class="w-6 h-px bg-white/60"></span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.35em]">NobleHome</span>
                            <span class="text-[10px] font-medium text-white/60 tracking-wider">· Employee Profile</span>
                        </div>
                    </div>

                    <div class="relative z-10 bg-white px-5 md:px-8 pb-5">
                        <div class="flex flex-col md:flex-row md:items-start gap-4 md:gap-6">

                            <!-- Avatar -->
                            <form method="post" enctype="multipart/form-data" id="ap-photo-form" class="relative shrink-0 self-center md:self-auto -mt-14">
                                <input type="hidden" name="csrf" value="<?= apEsc($csrf) ?>">
                                <input type="hidden" name="action" value="change_photo">
                                <input type="file" name="photo" id="ap-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                                <div class="w-28 h-28 rounded-2xl border-4 border-white shadow-lg bg-orange-100 overflow-hidden flex items-center justify-center">
                                    <?php if ($photo): ?>
                                        <img src="<?= apEsc($photo) ?>" alt="<?= apEsc($fullName) ?>" class="w-full h-full object-cover object-top">
                                    <?php else: ?>
                                        <span class="text-4xl font-extrabold text-orange-500"><?= apEsc($initials) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($emp): ?>
                                    <button type="button" id="ap-photo-btn" class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-gray-900 text-white text-xs flex items-center justify-center border-2 border-white hover:bg-orange-600 transition" title="Change photo">
                                        <i class="fa-solid fa-camera"></i>
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- Name + badges -->
                            <div class="flex-1 min-w-0 text-center md:text-left md:pt-4">
                                <div class="flex items-center justify-center md:justify-start gap-2 flex-wrap">
                                    <h2 class="text-xl md:text-2xl font-extrabold text-gray-900 leading-tight capitalize"><?= apEsc($fullName) ?></h2>
                                    <?php if ($isOfficial): ?>
                                        <span class="relative inline-flex items-center justify-center w-5 h-5 shrink-0" title="Official Employee · Verified">
                                            <i class="fa-solid fa-certificate text-sky-500 text-[20px]"></i>
                                            <i class="fa-solid fa-check absolute text-white text-[9px]"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5 capitalize">
                                    <?= apEsc($position ?: 'No position') ?><?php if ($department): ?> <span class="text-gray-300 mx-1">•</span> <?= apEsc($department) ?><?php endif; ?>
                                </p>

                                <div class="mt-3 flex items-center justify-center md:justify-start gap-2 flex-wrap">
                                    <?php if ($emp): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider <?= $isHead ? 'bg-orange-600 text-white' : 'bg-orange-50 text-orange-700 border border-orange-200' ?>">
                                            <i class="fa-solid <?= $isHead ? 'fa-user-tie' : 'fa-user' ?>"></i> <?= $isHead ? 'Head' : 'Staff' ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isOfficial): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                            <i class="fa-solid fa-shield-halved"></i> Official Employee
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($branch): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold bg-gray-100 text-gray-600 capitalize">
                                            <i class="fa-solid fa-location-dot"></i> <?= apEsc($branch) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($empId): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold bg-gray-100 text-gray-600">
                                            <i class="fa-solid fa-hashtag"></i> <?= apEsc($empId) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions + verified -->
                            <div class="flex flex-col items-center md:items-end gap-2.5 md:pt-4 shrink-0">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($emp): ?>
                                        <button type="button" data-goto="idcard" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                                            <i class="fa-solid fa-id-card text-orange-500"></i> View My ID
                                        </button>
                                        <button type="button" id="ap-photo-btn2" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 shadow-sm transition">
                                            <i class="fa-solid fa-camera"></i> Change Photo
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isOfficial): ?>
                                    <div class="flex items-center gap-2.5 rounded-xl border border-sky-200 bg-sky-50 pl-2.5 pr-3.5 py-2">
                                        <span class="relative inline-flex items-center justify-center w-7 h-7 shrink-0">
                                            <i class="fa-solid fa-certificate text-sky-500 text-[26px]"></i>
                                            <i class="fa-solid fa-check absolute text-white text-[11px]"></i>
                                        </span>
                                        <div class="leading-tight text-left">
                                            <p class="text-xs font-bold text-sky-800">Official Employee · Verified</p>
                                            <p class="text-[10px] text-sky-700">Verified by HR</p>
                                        </div>
                                    </div>
                                <?php elseif ($emp): ?>
                                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                                        <i class="fa-solid fa-hourglass-half text-gray-400 text-xs"></i>
                                        <p class="text-[11px] font-semibold text-gray-500">Not yet verified by HR</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Stats strip -->
                    <div class="grid grid-cols-2 md:grid-cols-4 border-t border-gray-100 bg-white divide-x divide-y md:divide-y-0 divide-gray-100">
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Employee ID</p>
                            <p class="text-sm font-bold text-gray-900 mt-1 truncate"><?= $empId !== '' ? apEsc($empId) : '<span class="text-gray-300">—</span>' ?></p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Date Joined</p>
                            <p class="text-sm font-bold text-gray-900 mt-1"><?= $joined !== '' ? apEsc($joined) : '<span class="text-gray-300">—</span>' ?></p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">With Company</p>
                            <p class="text-sm font-bold text-gray-900 mt-1"><?= $tenure !== '' ? apEsc($tenure) : '<span class="text-gray-300">—</span>' ?></p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Last Active</p>
                            <p class="text-sm font-bold text-gray-900 mt-1 truncate"><?= apEsc($lastActive) ?></p>
                        </div>
                    </div>
                </section>

                <?php if (!$emp): ?>
                    <!-- Walang naka-link na ID -->
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-amber-800">Your employee ID is not linked yet</p>
                            <p class="text-xs text-amber-700 mt-0.5">Employee ID, contact details, photo and your digital ID will appear once HR links your employee record to this account.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-5 items-start">

                    <!-- ═════════ LEFT ═════════ -->
                    <aside class="space-y-5 lg:sticky lg:top-5">

                        <!-- Profile completeness -->
                        <?php
                        $itemIcons = [
                            'Employee ID linked' => 'fa-solid fa-id-badge',
                            'Profile photo'      => 'fa-solid fa-camera',
                            'Phone number'       => 'fa-solid fa-phone',
                            'Address'            => 'fa-solid fa-location-dot',
                            'Signature'          => 'fa-solid fa-signature',
                            'Emergency contact'  => 'fa-solid fa-heart-pulse',
                        ];
                        if ($percent >= 100) {
                            $cState = ['All set!', 'Your profile is complete.', 'text-green-600', 'bg-green-50 border-green-200'];
                        } elseif ($percent >= 60) {
                            $cState = ['Almost there', 'Just a few more details.', 'text-orange-600', 'bg-orange-50 border-orange-200'];
                        } else {
                            $cState = ['Getting started', 'Complete your profile details.', 'text-gray-600', 'bg-gray-50 border-gray-200'];
                        }
                        ?>
                        <div class="rounded-2xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                            <div class="p-5 flex items-center gap-4">
                                <div class="ap-ring relative w-[72px] h-[72px] rounded-full flex items-center justify-center shrink-0" style="--p: <?= $percent ?>;">
                                    <div class="w-[58px] h-[58px] rounded-full bg-white flex flex-col items-center justify-center shadow-inner">
                                        <span class="text-base font-extrabold text-gray-900 leading-none"><?= $percent ?><span class="text-[10px] text-gray-400">%</span></span>
                                    </div>
                                    <?php if ($percent >= 100): ?>
                                        <span class="absolute -bottom-0.5 -right-0.5 w-6 h-6 rounded-full bg-green-500 border-2 border-white flex items-center justify-center">
                                            <i class="fa-solid fa-check text-white text-[10px]"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">Profile Completeness</p>
                                    <h3 class="text-base font-extrabold leading-tight <?= $cState[2] ?>"><?= $cState[0] ?></h3>
                                    <p class="text-xs text-gray-500"><?= $doneCount ?> of <?= count($checks) ?> items &middot; <?= $cState[1] ?></p>
                                </div>
                            </div>

                            <!-- progress segments -->
                            <div class="px-5 pb-4 flex gap-1">
                                <?php foreach ($checks as $ok): ?>
                                    <span class="h-1.5 flex-1 rounded-full <?= $ok ? 'bg-gradient-to-r from-orange-400 to-orange-500' : 'bg-gray-100' ?>"></span>
                                <?php endforeach; ?>
                            </div>

                            <ul class="border-t border-gray-100 divide-y divide-gray-50">
                                <?php foreach ($checks as $label => $ok): ?>
                                    <li class="flex items-center gap-3 px-5 py-2.5">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 <?= $ok ? 'bg-orange-50 text-orange-500' : 'bg-gray-50 text-gray-300' ?>">
                                            <i class="<?= $itemIcons[$label] ?? 'fa-solid fa-circle' ?> text-xs"></i>
                                        </span>
                                        <span class="flex-1 text-xs font-medium <?= $ok ? 'text-gray-700' : 'text-gray-400' ?>"><?= apEsc($label) ?></span>
                                        <?php if ($ok): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-green-600">
                                                <i class="fa-solid fa-circle-check"></i> Done
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-semibold text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">Missing</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($percent < 100): ?>
                                <div class="px-5 py-3 bg-slate-50 border-t border-gray-100">
                                    <p class="text-[11px] text-gray-500"><i class="fa-solid fa-circle-info text-orange-400 mr-1"></i> Missing items can be updated in <b>Contact Information</b> or through HR.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contact -->
                        <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
                            <h3 class="text-sm font-extrabold text-gray-900 mb-3">Contact</h3>
                            <div class="space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center shrink-0"><i class="fa-regular fa-envelope text-orange-500 text-xs"></i></div>
                                    <div class="min-w-0"><p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Email</p><p class="text-xs font-semibold text-gray-800 break-all"><?= $email !== '' ? apEsc($email) : '—' ?></p></div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center shrink-0"><i class="fa-solid fa-phone text-orange-500 text-xs"></i></div>
                                    <div class="min-w-0"><p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Phone</p><p class="text-xs font-semibold text-gray-800"><?= $contactNo !== '' ? apEsc($contactNo) : '<span class="text-gray-300 font-normal">Not set</span>' ?></p></div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center shrink-0"><i class="fa-solid fa-location-dot text-orange-500 text-xs"></i></div>
                                    <div class="min-w-0"><p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Address</p><p class="text-xs font-semibold text-gray-800"><?= $address !== '' ? apEsc($address) : '<span class="text-gray-300 font-normal">Not set</span>' ?></p></div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- ═════════ RIGHT: TABS ═════════ -->
                    <section class="min-w-0">

                        <nav class="rounded-2xl bg-white shadow-sm border border-gray-100 p-1.5 mb-5 overflow-x-auto flex gap-1" id="ap-tabs">
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors" data-tab="personal"><i class="fa-regular fa-user"></i> Personal Information</button>
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors" data-tab="account"><i class="fa-solid fa-shield-halved"></i> Account Info</button>
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors" data-tab="activity"><i class="fa-solid fa-clock-rotate-left"></i> Activity Log</button>
                            <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors" data-tab="idcard"><i class="fa-solid fa-id-card"></i> My ID</button>
                        </nav>

                        <!-- ───── PERSONAL INFORMATION ───── -->
                        <div id="panel-personal" data-panel class="space-y-5">

                            <!-- Employment (read-only, HR-managed) -->
                            <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5 md:p-6">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fa-solid fa-briefcase text-orange-500"></i></div>
                                        <div>
                                            <h3 class="text-base font-extrabold text-gray-900 leading-tight">Employment Details</h3>
                                            <p class="text-xs text-gray-500">Managed by HR</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-[10px] font-semibold text-gray-500"><i class="fa-solid fa-lock"></i> Read-only</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?= apTile('fa-regular fa-user', 'Full Name', $fullName) ?>
                                    <?= apTile('fa-solid fa-id-badge', 'Employee ID', $empId) ?>
                                    <?= apTile('fa-regular fa-building', 'Department', $department) ?>
                                    <?= apTile('fa-solid fa-briefcase', 'Position', $position) ?>
                                    <?= apTile('fa-solid fa-layer-group', 'Employee Level', $emp ? ($isHead ? 'Head' : 'Staff') : '') ?>
                                    <?= apTile('fa-solid fa-code-branch', 'Branch', ucfirst((string) $branch)) ?>
                                    <?= apTile('fa-regular fa-calendar', 'Date Joined', $joined) ?>
                                    <?= apTile('fa-regular fa-envelope', 'Email Address', $email) ?>
                                </div>
                            </div>

                            <!-- Contact (editable) -->
                            <form method="post" id="ap-info-form" class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5 md:p-6">
                                <input type="hidden" name="csrf" value="<?= apEsc($csrf) ?>">
                                <input type="hidden" name="action" value="update_info">

                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fa-solid fa-address-book text-orange-500"></i></div>
                                        <div>
                                            <h3 class="text-base font-extrabold text-gray-900 leading-tight">Contact Information</h3>
                                            <p class="text-xs text-gray-500">You can update these details yourself.</p>
                                        </div>
                                    </div>
                                    <?php if ($emp): ?>
                                        <button type="button" id="ap-edit-btn" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 border border-orange-200 transition">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $inp = 'w-full pl-10 pr-3 py-2.5 text-sm text-gray-800 bg-slate-50 border border-gray-100 rounded-lg outline-none transition focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 read-only:cursor-default';
                                $ico = 'absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none';
                                $lab = 'block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5';
                                ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="<?= $lab ?>">Phone Number</label>
                                        <div class="relative"><i class="fa-solid fa-phone <?= $ico ?>"></i><input class="<?= $inp ?>" data-edit name="contactno" value="<?= apEsc($contactNo) ?>" maxlength="20" placeholder="09XX XXX XXXX" readonly></div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="<?= $lab ?>">Address</label>
                                        <div class="relative"><i class="fa-solid fa-location-dot <?= $ico ?>"></i><input class="<?= $inp ?>" data-edit name="address" value="<?= apEsc($address) ?>" placeholder="House no., Street, Barangay, City" readonly></div>
                                    </div>
                                </div>

                                <?php if ($hasEmgName || $hasEmgNo): ?>
                                    <div class="mt-6 pt-5 border-t border-gray-100">
                                        <p class="text-xs font-extrabold text-gray-900 mb-3 flex items-center gap-2"><i class="fa-solid fa-heart-pulse text-red-500"></i> Emergency Contact</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php if ($hasEmgName): ?>
                                                <div>
                                                    <label class="<?= $lab ?>">Contact Name</label>
                                                    <div class="relative"><i class="fa-solid fa-user-shield <?= $ico ?>"></i><input class="<?= $inp ?>" data-edit name="emergency_name" value="<?= apEsc($emgName) ?>" placeholder="Full name" readonly></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($hasEmgNo): ?>
                                                <div>
                                                    <label class="<?= $lab ?>">Contact No.</label>
                                                    <div class="relative"><i class="fa-solid fa-phone-volume <?= $ico ?>"></i><input class="<?= $inp ?>" data-edit name="emergency_contact" value="<?= apEsc($emgNo) ?>" maxlength="20" placeholder="09XX XXX XXXX" readonly></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$emp): ?>
                                    <p class="mt-4 text-xs text-gray-400"><i class="fa-solid fa-circle-info mr-1"></i> Editing is available once your employee ID is linked.</p>
                                <?php endif; ?>

                                <div id="ap-edit-actions" class="hidden mt-6 pt-4 border-t border-gray-100 flex justify-end gap-2">
                                    <button type="button" id="ap-cancel-btn" class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-200 transition">Cancel</button>
                                    <button type="submit" class="px-5 py-2 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 shadow-sm transition">
                                        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- ───── ACCOUNT INFO ───── -->
                        <div class="hidden space-y-5" id="panel-account" data-panel>
                            <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5 md:p-6">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fa-solid fa-shield-halved text-orange-500"></i></div>
                                    <div>
                                        <h3 class="text-base font-extrabold text-gray-900 leading-tight">Account Info</h3>
                                        <p class="text-xs text-gray-500">Details of your login account.</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?= apTile('fa-regular fa-envelope', 'Login Email', $email) ?>
                                    <?= apTile('fa-solid fa-user-gear', 'Role', $roleLabel) ?>
                                    <?= apTile('fa-solid fa-briefcase', 'Account Position', ucfirst((string) ($acct['position'] ?? ''))) ?>
                                    <?= apTile('fa-solid fa-code-branch', 'Branch', ucfirst((string) $branch)) ?>
                                    <?= apTile('fa-regular fa-calendar-plus', 'Account Created', $createdAt) ?>
                                    <?= apTile('fa-regular fa-clock', 'Last Active', $lastActive) ?>
                                </div>
                            </div>
                        </div>

                        <!-- ───── ACTIVITY LOG ───── -->
                        <div class="hidden" id="panel-activity" data-panel>
                            <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5 md:p-6">
                                <div class="flex items-center gap-3 mb-5">
                                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-orange-500"></i></div>
                                    <div>
                                        <h3 class="text-base font-extrabold text-gray-900 leading-tight">Activity Log</h3>
                                        <p class="text-xs text-gray-500">Recent account activity.</p>
                                    </div>
                                </div>
                                <?php
                                $acts = [['fa-solid fa-signal', 'Last active', $lastActive, 'bg-green-100 text-green-600']];
                                if ($isOfficial) {
                                    $acts[] = ['fa-solid fa-certificate', 'Verified as official employee', !empty($emp['official_at']) ? date('F j, Y g:i A', strtotime($emp['official_at'])) : '—', 'bg-sky-100 text-sky-600'];
                                }
                                if ($joined) {
                                    $acts[] = ['fa-solid fa-briefcase', 'Started with the company', $joined, 'bg-orange-100 text-orange-600'];
                                }
                                $acts[] = ['fa-solid fa-user-plus', 'Account created', $createdAt, 'bg-gray-100 text-gray-500'];
                                ?>
                                <ol class="relative">
                                    <?php foreach ($acts as $i => [$icon, $title, $when, $tone]): ?>
                                        <li class="relative flex gap-4 <?= $i < count($acts) - 1 ? 'pb-6' : '' ?>">
                                            <?php if ($i < count($acts) - 1): ?>
                                                <span class="absolute left-[17px] top-9 bottom-0 w-px bg-gray-200"></span>
                                            <?php endif; ?>
                                            <span class="relative z-10 w-9 h-9 rounded-full flex items-center justify-center shrink-0 <?= $tone ?>"><i class="<?= $icon ?> text-xs"></i></span>
                                            <div class="pt-1.5">
                                                <p class="text-sm font-bold text-gray-800"><?= apEsc($title) ?></p>
                                                <p class="text-xs text-gray-500"><?= apEsc($when) ?></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>

                        <!-- ───── MY ID (3D card) ───── -->
                        <div class="hidden" id="panel-idcard" data-panel>
                            <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5 md:p-6">
                                <?php if (!$emp): ?>
                                    <div class="max-w-md mx-auto text-center py-10">
                                        <div class="w-16 h-16 rounded-2xl bg-orange-50 flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-id-card text-2xl text-orange-500"></i></div>
                                        <h2 class="text-base font-bold text-gray-800">No ID linked yet</h2>
                                        <p class="text-xs text-gray-500 mt-1">Your ID will appear here once HR links your profile to your role.</p>
                                    </div>
                                <?php else: ?>

                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center"><i class="fa-solid fa-id-card text-orange-500"></i></div>
                                        <div>
                                            <h3 class="text-base font-extrabold text-gray-900 leading-tight">My Employee ID</h3>
                                            <p class="text-xs text-gray-500">Drag the card to rotate it.</p>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-slate-50 border border-gray-100 mt-4 pb-6">
                                        <div class="id-stage" id="id-stage">
                                            <div class="id-card" id="id-card">

                                                <!-- FRONT -->
                                                <div class="id-face id-front">
                                                    <div class="f-building" style="background-image:url('<?= BASE_URL ?>/icon/building2.png')"></div>

                                                    <div class="f-stripes"></div>
                                                    <div class="f-dots"></div>
                                                    <div class="f-corner2"></div>
                                                    <div class="f-corner"></div>
                                                    <div class="f-ring2"></div>
                                                    <div class="f-ring"></div>

                                                    <div class="f-logo">
                                                        <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome" onerror="this.replaceWith(document.createTextNode('NH'))">
                                                    </div>

                                                    <div class="f-info">
                                                        <div class="f-top">
                                                            <div class="f-idbox">
                                                                <span class="f-idlabel">EMPLOYEE ID</span>
                                                                <span class="f-empid"><?= apEsc($emp['employeeid']) ?></span>
                                                            </div>
                                                            <span class="f-level<?= $isHead ? ' head' : '' ?>"><i class="fa-solid <?= $isHead ? 'fa-user-tie' : 'fa-user' ?>"></i><?= $isHead ? 'HEAD' : 'STAFF' ?></span>
                                                        </div>
                                                        <div class="f-name"><?= apEsc($emp['fullname']) ?></div>
                                                        <div class="f-pos"><?= apEsc($emp['position']) ?></div>
                                                    </div>

                                                    <?php if ($qr): ?>
                                                        <div class="f-qr"><img src="<?= apEsc($qr) ?>" alt="QR"></div>
                                                    <?php endif; ?>

                                                    <div class="f-photo">
                                                        <?php if ($photo): ?>
                                                            <img src="<?= apEsc($photo) ?>" alt="<?= apEsc($emp['fullname']) ?>">
                                                        <?php else: ?>
                                                            <i class="fa-solid fa-user ph"></i>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="f-sign">
                                                        <div class="box">
                                                            <?php if ($signature): ?><img src="<?= apEsc($signature) ?>" alt="Signature"><?php endif; ?>
                                                        </div>
                                                        <div class="line">SIGNATURE</div>
                                                    </div>

                                                    <div class="f-band">
                                                        <span class="dot"></span>
                                                        <span class="txt">NOBLEHOME CONSTRUCTION</span>
                                                        <span class="dot"></span>
                                                    </div>
                                                    <div class="id-shine"></div>
                                                </div>

                                                <!-- BACK -->
                                                <div class="id-face id-back">
                                                    <div class="b-wrap">
                                                        <div class="b-important">
                                                            <h4>IMPORTANT</h4>
                                                            <p>This is to certify that the person whose name, photo, and signature appears here on is an employee of Noblehome Construction Corporation, and not valid unless signed</p>
                                                        </div>

                                                        <div class="b-label">CONTACT NO.</div>
                                                        <div class="b-val"><?= apEsc($emp['contactno']) ?></div>

                                                        <div class="b-label">ADDRESS</div>
                                                        <div class="b-val"><?= apEsc($emp['address']) ?></div>

                                                        <div class="b-band">
                                                            <?php if (!empty($emp['sss'])): ?>SSS: <?= apEsc($emp['sss']) ?><br><?php endif; ?>
                                                            <?php if (!empty($emp['tin'])): ?>TIN: <?= apEsc($emp['tin']) ?><br><?php endif; ?>
                                                            Date Started: <?= apEsc($started) ?>
                                                        </div>

                                                        <div class="b-rule"></div>

                                                        <?php if ($emgName || $emgNo): ?>
                                                            <div class="b-emg">IN CASE OF EMERGENCY PLEASE CONTACT</div>
                                                            <div class="b-emg-name"><?= apEsc(strtoupper($emgName)) ?></div>
                                                            <div class="b-val"><?= apEsc($emgNo) ?></div>
                                                        <?php endif; ?>

                                                        <div class="b-ceo">
                                                            <div class="sig">
                                                                <img src="<?= BASE_URL ?>/icon/ceo-signature.png" alt="" onerror="this.style.display='none'">
                                                            </div>
                                                            <div class="line">KEN YANG – CEO</div>
                                                            <div class="b-foot">
                                                                <strong>NOBLEHOME CONSTRUCTION CORPORATION</strong>
                                                                2nd Floor MC Premier Building - 1181 EDSA<br>
                                                                Balintawak, Quezon City.<br>
                                                                Tel: (02) 8822-1285 / +63992-239-4563
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="id-shine"></div>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="id-ground" style="--u:min(3.4px,.85vw)"></div>

                                        <div class="flex items-center justify-center gap-3 mt-6">
                                            <button type="button" id="id-flip"
                                                class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 shadow transition">
                                                <i class="fa-solid fa-rotate mr-1"></i> Flip
                                            </button>
                                            <button type="button" id="id-reset"
                                                class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-200 shadow-sm transition">
                                                <i class="fa-solid fa-arrows-to-dot mr-1"></i> Reset
                                            </button>
                                        </div>
                                    </div>

                                <?php endif; ?>
                            </div>
                        </div>

                    </section>
                </div>

                <script>
                    /* ───── Tabs (naka-sync sa URL hash) ───── */
                    (function () {
                        const tabs = document.querySelectorAll('[data-tab]');
                        const panels = document.querySelectorAll('[data-panel]');
                        const ON = ['bg-orange-500', 'text-white', 'shadow-sm'];
                        const OFF = ['text-gray-500', 'hover:text-orange-600', 'hover:bg-orange-50'];

                        function show(name) {
                            if (!document.getElementById('panel-' + name)) name = 'personal';
                            tabs.forEach(t => {
                                const on = t.dataset.tab === name;
                                t.classList.remove(...(on ? OFF : ON));
                                t.classList.add(...(on ? ON : OFF));
                            });
                            panels.forEach(p => p.classList.toggle('hidden', p.id !== 'panel-' + name));
                            if (history.replaceState) history.replaceState(null, '', '#' + name);
                        }
                        tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));
                        document.querySelectorAll('[data-goto]').forEach(b => b.addEventListener('click', () => {
                            show(b.dataset.goto);
                            document.getElementById('ap-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }));
                        show((location.hash || '#personal').slice(1));

                        const flash = document.getElementById('ap-flash');
                        if (flash) setTimeout(() => { flash.style.transition = 'opacity .4s'; flash.style.opacity = 0; setTimeout(() => flash.remove(), 400); }, 4500);
                    })();

                    /* ───── Edit toggle ───── */
                    (function () {
                        const btn = document.getElementById('ap-edit-btn');
                        if (!btn) return;
                        const cancel = document.getElementById('ap-cancel-btn');
                        const actions = document.getElementById('ap-edit-actions');
                        const inputs = document.querySelectorAll('[data-edit]');
                        const original = Array.from(inputs).map(i => i.value);

                        function setEdit(on) {
                            inputs.forEach(i => {
                                i.readOnly = !on;
                                i.classList.toggle('bg-white', on);
                                i.classList.toggle('border-orange-300', on);
                                i.classList.toggle('bg-slate-50', !on);
                                i.classList.toggle('border-gray-100', !on);
                            });
                            actions.classList.toggle('hidden', !on);
                            btn.classList.toggle('hidden', on);
                            if (on && inputs[0]) inputs[0].focus();
                        }
                        btn.addEventListener('click', () => setEdit(true));
                        cancel.addEventListener('click', () => { inputs.forEach((i, n) => i.value = original[n]); setEdit(false); });
                    })();

                    /* ───── Photo upload ───── */
                    (function () {
                        const input = document.getElementById('ap-photo-input');
                        if (!input) return;
                        ['ap-photo-btn', 'ap-photo-btn2'].forEach(id => {
                            const b = document.getElementById(id);
                            if (b) b.addEventListener('click', () => input.click());
                        });
                        input.addEventListener('change', () => {
                            const f = input.files[0];
                            if (!f) return;
                            if (f.size > 3 * 1024 * 1024) { alert('Photo must be 3MB or smaller.'); input.value = ''; return; }
                            document.getElementById('ap-photo-form').submit();
                        });
                    })();
                </script>

                <?php if ($emp): ?>
                    <script>
                        /* ───── 3D ID card ───── */
                        (function () {
                            const stage = document.getElementById('id-stage');
                            const card = document.getElementById('id-card');
                            const shines = card.querySelectorAll('.id-shine');

                            let rx = 6, ry = -18;
                            let vx = 0, vy = 0;
                            let dragging = false, lastX = 0, lastY = 0;
                            let target = null;
                            let idleAt = performance.now();

                            function render() {
                                card.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
                                const sx = 50 + Math.sin(ry * Math.PI / 180) * 60;
                                shines.forEach(s => s.style.setProperty('--sx', sx + '%'));
                            }

                            function tick(now) {
                                if (!dragging) {
                                    if (target) {
                                        ry += (target.ry - ry) * 0.12;
                                        rx += (target.rx - rx) * 0.12;
                                        if (Math.abs(target.ry - ry) < 0.1 && Math.abs(target.rx - rx) < 0.1) {
                                            ry = target.ry; rx = target.rx; target = null; idleAt = now;
                                        }
                                    } else {
                                        ry += vy; rx += vx;
                                        vy *= 0.95; vx *= 0.95;
                                        rx += (6 - rx) * 0.02;
                                        if (Math.abs(vy) < 0.02 && now - idleAt > 2500) {
                                            ry += Math.sin(now / 1500) * 0.08;
                                        }
                                    }
                                    rx = Math.max(-40, Math.min(40, rx));
                                }
                                render();
                                requestAnimationFrame(tick);
                            }

                            stage.addEventListener('pointerdown', e => {
                                dragging = true; target = null;
                                lastX = e.clientX; lastY = e.clientY; vx = vy = 0;
                                stage.classList.add('dragging');
                                stage.setPointerCapture(e.pointerId);
                            });

                            stage.addEventListener('pointermove', e => {
                                if (!dragging) return;
                                const dx = e.clientX - lastX, dy = e.clientY - lastY;
                                lastX = e.clientX; lastY = e.clientY;
                                vy = dx * 0.5; vx = -dy * 0.35;
                                ry += vy; rx += vx;
                                rx = Math.max(-40, Math.min(40, rx));
                            });

                            function endDrag() {
                                dragging = false; idleAt = performance.now();
                                stage.classList.remove('dragging');
                            }
                            stage.addEventListener('pointerup', endDrag);
                            stage.addEventListener('pointercancel', endDrag);

                            document.getElementById('id-flip').addEventListener('click', () => {
                                vx = vy = 0;
                                target = { ry: (Math.round(ry / 180) + 1) * 180, rx: 0 };
                            });
                            document.getElementById('id-reset').addEventListener('click', () => {
                                vx = vy = 0;
                                target = { ry: Math.round(ry / 360) * 360, rx: 0 };
                            });

                            requestAnimationFrame(tick);
                        })();
                    </script>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </main>
</body>

</html>