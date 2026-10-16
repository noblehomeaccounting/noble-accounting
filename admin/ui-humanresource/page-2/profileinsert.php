<?php
// profileinsert.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// CSRF token para sa form
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-orange-400 focus:ring-2 focus:ring-orange-100 focus:outline-none transition';
$labelClass = 'block text-xs font-semibold text-gray-600 mb-1';

// ── Saved profiles + accounts + roles (para sa linking / editing) ─────
$profiles = [];
$res = $conn->query("
    SELECT n.*,
           a.name  AS acc_name,  a.email  AS acc_email,
           r.name  AS role_name, r.email  AS role_email, r.role AS role_type
    FROM nobleid n
    LEFT JOIN nobleaccount a ON a.id = n.account_id
    LEFT JOIN noblerole    r ON r.id = n.role_id
    ORDER BY n.fullname ASC
");
while ($row = $res->fetch_assoc()) {
    $profiles[] = $row;
}

$accounts = [];
$res = $conn->query("SELECT id, name, email FROM nobleaccount ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $accounts[] = $row;
}

$roles = [];
$res = $conn->query("SELECT id, name, email, role FROM noblerole ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $roles[] = $row;
}

$linkedIds = [];      // account_id na naka-link na
$linkedRoleIds = [];  // role_id na naka-link na
foreach ($profiles as $p) {
    if (!empty($p['account_id'])) {
        $linkedIds[(int) $p['account_id']] = true;
    }
    if (!empty($p['role_id'])) {
        $linkedRoleIds[(int) $p['role_id']] = true;
    }
}

// JSON na ilalagay sa data-profile ng bawat row (para sa Edit modal)
function profileJson(array $p): string
{
    return htmlspecialchars(json_encode([
        'employeeid'    => $p['employeeid'] ?? '',
        'fullname'      => $p['fullname'] ?? '',
        'position'      => $p['position'] ?? '',
        'emp_level'     => (($p['emp_level'] ?? 'staff') === 'head') ? 'head' : 'staff',
        'contactno'     => $p['contactno'] ?? '',
        'address'       => $p['address'] ?? '',
        'sss'           => $p['sss'] ?? '',
        'tin'           => $p['tin'] ?? '',
        'datestarted'   => $p['datestarted'] ?? '',
        'photo_url'     => !empty($p['photo']) ? BASE_URL . '/' . ltrim($p['photo'], '/') : '',
        'signature_url' => !empty($p['signature']) ? BASE_URL . '/' . ltrim($p['signature'], '/') : '',
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-5">
                <h1 class="text-xl font-bold text-gray-800">My Profile</h1>
                <p class="text-xs text-gray-500">Fill in the employee information, then link it to a user account and/or a role.</p>
            </div>

            <!-- Success -->
            <div id="profile-success"
                class="hidden mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <i class="fa-solid fa-circle-check mr-1"></i>
                <span id="profile-success-msg"></span>
            </div>

            <!-- Errors -->
            <div id="profile-errors" class="hidden mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Please fix the following:</p>
                <ul id="profile-errors-list" class="list-disc pl-5 space-y-0.5 text-xs"></ul>
            </div>

            <!-- ═════ Two containers: Form (left) + Saved Profiles (right) ═════ -->
            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">

                <!-- ───────── LEFT: Form ───────── -->
                <form id="profile-form" enctype="multipart/form-data" autocomplete="off"
                    class="xl:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-100">

                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

                    <!-- Employee info -->
                    <section class="p-4 md:p-5">
                        <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-id-badge text-orange-500"></i> Employee Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="<?= $labelClass ?>" for="employeeid">Employee ID <span class="text-red-500">*</span></label>
                                <input type="text" id="employeeid" name="employeeid" maxlength="50" required
                                    class="<?= $inputClass ?>" placeholder="e.g. NH-0001">
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>" for="datestarted">Date Started <span class="text-red-500">*</span></label>
                                <input type="date" id="datestarted" name="datestarted" required class="<?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>" for="fullname">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" id="fullname" name="fullname" maxlength="150" required
                                    class="<?= $inputClass ?>" placeholder="Juan Dela Cruz">
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>" for="position">Position <span class="text-red-500">*</span></label>
                                <input type="text" id="position" name="position" maxlength="100" required
                                    class="<?= $inputClass ?>" placeholder="e.g. Accounting Staff">
                            </div>

                            <!-- Staff / Head -->
                            <div class="md:col-span-2">
                                <span class="<?= $labelClass ?>">Employee Level <span class="text-red-500">*</span></span>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="emp_level" value="staff" class="peer sr-only" checked>
                                        <div class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-500 transition hover:border-orange-300 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-200">
                                            <i class="fa-solid fa-user"></i> Staff
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="emp_level" value="head" class="peer sr-only">
                                        <div class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-500 transition hover:border-orange-300 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-200">
                                            <i class="fa-solid fa-user-tie"></i> Head
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Contact & Government -->
                    <section class="p-4 md:p-5">
                        <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-address-book text-orange-500"></i> Contact &amp; Government Numbers
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="<?= $labelClass ?>" for="contactno">Contact No. <span class="text-red-500">*</span></label>
                                <input type="tel" id="contactno" name="contactno" maxlength="20" required
                                    class="<?= $inputClass ?>" placeholder="09XX XXX XXXX">
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>" for="sss">SSS No.</label>
                                <input type="text" id="sss" name="sss" maxlength="20" inputmode="numeric"
                                    class="<?= $inputClass ?>" placeholder="00-0000000-0">
                            </div>
                            <div>
                                <label class="<?= $labelClass ?>" for="tin">TIN</label>
                                <input type="text" id="tin" name="tin" maxlength="20" inputmode="numeric"
                                    class="<?= $inputClass ?>" placeholder="000-000-000-000">
                            </div>
                            <div class="md:col-span-3">
                                <label class="<?= $labelClass ?>" for="address">Address <span class="text-red-500">*</span></label>
                                <textarea id="address" name="address" rows="2" maxlength="255" required
                                    class="<?= $inputClass ?>" placeholder="House no., Street, Barangay, City"></textarea>
                            </div>
                        </div>
                    </section>

                    <!-- Photo, QR & Signature -->
                    <section class="p-4 md:p-5">
                        <h2 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-image-portrait text-orange-500"></i> Photo, QR &amp; Signature
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                            <!-- Photo -->
                            <div>
                                <label class="<?= $labelClass ?>">Photo</label>
                                <label for="photo"
                                    class="flex flex-col items-center justify-center h-28 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40 cursor-pointer transition overflow-hidden">
                                    <img id="photo-preview" class="hidden max-h-full max-w-full object-contain" alt="">
                                    <div id="photo-placeholder" class="text-center text-gray-400 px-2">
                                        <i class="fa-solid fa-camera text-xl mb-1"></i>
                                        <p class="text-[10px]">Click to upload (JPG, PNG, WEBP · max 10MB)</p>
                                    </div>
                                </label>
                                <input type="file" id="photo" name="photo"
                                    accept="image/jpeg,image/png,image/webp" class="hidden"
                                    data-preview="photo-preview" data-placeholder="photo-placeholder">
                            </div>

                            <!-- QR (auto-generated) -->
                            <div>
                                <label class="<?= $labelClass ?>">QR Code</label>
                                <div class="flex flex-col items-center justify-center h-28 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 text-center text-gray-400 px-3">
                                    <i class="fa-solid fa-qrcode text-xl mb-1"></i>
                                    <p class="text-[10px]">Auto-generated from the Employee ID when you save.</p>
                                </div>
                            </div>

                            <!-- Signature -->
                            <div>
                                <label class="<?= $labelClass ?>">Signature</label>
                                <label for="signature"
                                    class="flex flex-col items-center justify-center h-28 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40 cursor-pointer transition overflow-hidden">
                                    <img id="signature-preview" class="hidden max-h-full max-w-full object-contain" alt="">
                                    <div id="signature-placeholder" class="text-center text-gray-400 px-2">
                                        <i class="fa-solid fa-signature text-xl mb-1"></i>
                                        <p class="text-[10px]">Click to upload (JPG, PNG, WEBP · max 10MB)</p>
                                    </div>
                                </label>
                                <input type="file" id="signature" name="signature"
                                    accept="image/jpeg,image/png,image/webp" class="hidden"
                                    data-preview="signature-preview" data-placeholder="signature-placeholder">
                            </div>
                        </div>
                    </section>

                    <!-- Actions -->
                    <div class="px-4 md:px-5 py-3 flex items-center justify-end gap-3 bg-gray-50/60 rounded-b-2xl">
                        <button type="reset" id="profile-clear"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">
                            Clear
                        </button>
                        <button type="submit" id="profile-submit"
                            class="px-5 py-2 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> <span id="profile-submit-text">Save Profile</span>
                        </button>
                    </div>
                </form>

                <!-- ───────── RIGHT: Saved profiles + Link / Edit ───────── -->
                <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm xl:sticky xl:top-5">
                    <div class="p-4 md:p-5 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-link text-orange-500"></i> Saved Profiles
                            <span class="ml-1 text-[11px] font-semibold text-gray-400">(<?= count($profiles) ?>)</span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">Edit a profile, or link it to a user account and/or a role.</p>
                    </div>

                    <?php if (!$profiles): ?>
                        <p class="p-6 text-xs text-gray-400 text-center">No saved profiles yet.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-100 max-h-[65vh] overflow-y-auto" id="link-list">
                            <?php foreach ($profiles as $p): ?>
                                <?php
                                $linked = !empty($p['account_id']);
                                $roleLinked = !empty($p['role_id']);
                                $isHead = (($p['emp_level'] ?? 'staff') === 'head');
                                $isOfficial = !empty($p['is_official']);
                                ?>
                                <li class="p-4 flex flex-col gap-3"
                                    data-employeeid="<?= htmlspecialchars($p['employeeid']) ?>"
                                    data-profile="<?= profileJson($p) ?>">

                                    <div class="min-w-0 flex items-center gap-3">
                                        <?php if (!empty($p['photo'])): ?>
                                            <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim($p['photo'], '/')) ?>"
                                                alt="" class="w-10 h-10 rounded-full object-cover border border-gray-200 shrink-0">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center shrink-0">
                                                <i class="fa-solid fa-user text-sm"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-800 truncate flex items-center gap-1.5">
                                                <span class="truncate"><?= htmlspecialchars($p['fullname']) ?></span>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide <?= $isHead ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-500' ?>">
                                                    <?= $isHead ? 'Head' : 'Staff' ?>
                                                </span>
                                            </p>
                                            <p class="text-[11px] text-gray-500 truncate">
                                                <?= htmlspecialchars($p['employeeid']) ?> · <?= htmlspecialchars($p['position']) ?>
                                            </p>
                                        </div>
                                        <button type="button" data-action="edit"
                                            class="shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                        </button>
                                    </div>

                                    <!-- Link to user account (nobleaccount) -->
                                    <div class="flex items-center gap-2 min-w-0">
                                        <?php if ($linked): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 border border-green-200 px-3 py-1 text-[11px] font-semibold text-green-700 truncate"
                                                title="Linked user account">
                                                <i class="fa-solid fa-circle-check"></i>
                                                <?= htmlspecialchars($p['acc_name'] ?? '') ?>
                                                <span class="font-normal text-green-600">(<?= htmlspecialchars($p['acc_email'] ?? '') ?>)</span>
                                            </span>
                                            <button type="button" data-action="unlink"
                                                class="ml-auto shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-red-600 hover:bg-red-50 transition">
                                                Unlink
                                            </button>
                                        <?php else: ?>
                                            <select class="link-select <?= $inputClass ?> !py-1.5 !text-xs">
                                                <option value="">— Select account —</option>
                                                <?php foreach ($accounts as $a): ?>
                                                    <?php if (isset($linkedIds[(int) $a['id']])) continue; ?>
                                                    <option value="<?= (int) $a['id'] ?>">
                                                        <?= htmlspecialchars($a['name'] . ' — ' . $a['email']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" data-action="link"
                                                class="shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-white bg-orange-500 hover:bg-orange-600 transition disabled:opacity-60">
                                                Link
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Link to role (noblerole) -->
                                    <div class="flex items-center gap-2 min-w-0">
                                        <?php if ($roleLinked): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-1 text-[11px] font-semibold text-indigo-700 truncate"
                                                title="Linked role">
                                                <i class="fa-solid fa-user-shield"></i>
                                                <?= htmlspecialchars($p['role_name'] ?? '') ?>
                                                <span class="font-normal text-indigo-500">(<?= htmlspecialchars($p['role_type'] ?? '') ?>)</span>
                                            </span>
                                            <button type="button" data-action="unlink_role"
                                                class="ml-auto shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-red-600 hover:bg-red-50 transition">
                                                Unlink
                                            </button>
                                        <?php else: ?>
                                            <select class="role-select <?= $inputClass ?> !py-1.5 !text-xs">
                                                <option value="">— Select role —</option>
                                                <?php foreach ($roles as $r): ?>
                                                    <?php if (isset($linkedRoleIds[(int) $r['id']])) continue; ?>
                                                    <option value="<?= (int) $r['id'] ?>">
                                                        <?= htmlspecialchars($r['name'] . ' — ' . $r['role'] . ' (' . $r['email'] . ')') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" data-action="link_role"
                                                class="shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-white bg-indigo-500 hover:bg-indigo-600 transition disabled:opacity-60">
                                                Link Role
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Official employee (verified) -->
                                    <div class="flex items-center gap-2 min-w-0">
                                        <?php if ($isOfficial): ?>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 border border-sky-200 px-3 py-1 text-[11px] font-semibold text-sky-700">
                                                <i class="fa-solid fa-circle-check"></i> Official Employee · Verified
                                            </span>
                                            <button type="button" data-action="unofficial"
                                                class="ml-auto shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-gray-500 hover:bg-gray-100 transition">
                                                Remove
                                            </button>
                                        <?php else: ?>
                                            <button type="button" data-action="official"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 transition disabled:opacity-60">
                                                <i class="fa-solid fa-certificate"></i> Official
                                            </button>
                                            <span class="text-[10px] text-gray-400">Mark as an official employee</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            </div> <!-- end grid -->
        </div> <!-- end max-w-7xl -->
    </main>

    <!-- ───────── Edit modal ───────── -->
    <div id="edit-modal" class="hidden fixed inset-0 z-[100] flex items-start md:items-center justify-center p-4 overflow-y-auto">
        <div class="absolute inset-0 bg-black/50" data-close="1"></div>

        <form id="edit-form" enctype="multipart/form-data" autocomplete="off"
            class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl my-6">

            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="orig_employeeid" id="e-orig">

            <div class="flex items-center justify-between px-5 md:px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-orange-500"></i> Edit Profile
                </h3>
                <button type="button" data-close="1" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>

            <div class="p-5 md:p-6 space-y-4">
                <div id="edit-errors" class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Please fix the following:</p>
                    <ul id="edit-errors-list" class="list-disc pl-5 space-y-0.5 text-xs"></ul>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="<?= $labelClass ?>" for="e-employeeid">Employee ID <span class="text-red-500">*</span></label>
                        <input type="text" id="e-employeeid" name="employeeid" maxlength="50" required class="<?= $inputClass ?>">
                        <p class="text-[10px] text-gray-400 mt-1">Changing this will regenerate the QR code.</p>
                    </div>
                    <div>
                        <label class="<?= $labelClass ?>" for="e-datestarted">Date Started <span class="text-red-500">*</span></label>
                        <input type="date" id="e-datestarted" name="datestarted" required class="<?= $inputClass ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label class="<?= $labelClass ?>" for="e-fullname">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="e-fullname" name="fullname" maxlength="150" required class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="<?= $labelClass ?>" for="e-position">Position <span class="text-red-500">*</span></label>
                        <input type="text" id="e-position" name="position" maxlength="100" required class="<?= $inputClass ?>">
                    </div>

                    <!-- Staff / Head -->
                    <div>
                        <span class="<?= $labelClass ?>">Employee Level <span class="text-red-500">*</span></span>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="emp_level" value="staff" class="peer sr-only" checked>
                                <div class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-500 transition hover:border-orange-300 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600">
                                    <i class="fa-solid fa-user"></i> Staff
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="emp_level" value="head" class="peer sr-only">
                                <div class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-500 transition hover:border-orange-300 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600">
                                    <i class="fa-solid fa-user-tie"></i> Head
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="<?= $labelClass ?>" for="e-contactno">Contact No. <span class="text-red-500">*</span></label>
                        <input type="tel" id="e-contactno" name="contactno" maxlength="20" required class="<?= $inputClass ?>">
                    </div>
                    <div></div>
                    <div class="md:col-span-2">
                        <label class="<?= $labelClass ?>" for="e-address">Address <span class="text-red-500">*</span></label>
                        <textarea id="e-address" name="address" rows="2" maxlength="255" required class="<?= $inputClass ?>"></textarea>
                    </div>
                    <div>
                        <label class="<?= $labelClass ?>" for="e-sss">SSS No.</label>
                        <input type="text" id="e-sss" name="sss" maxlength="20" inputmode="numeric" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="<?= $labelClass ?>" for="e-tin">TIN</label>
                        <input type="text" id="e-tin" name="tin" maxlength="20" inputmode="numeric" class="<?= $inputClass ?>">
                    </div>

                    <!-- Photo -->
                    <div>
                        <label class="<?= $labelClass ?>">Photo</label>
                        <label for="e-photo"
                            class="flex flex-col items-center justify-center h-32 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40 cursor-pointer transition overflow-hidden">
                            <img id="e-photo-preview" class="hidden max-h-full max-w-full object-contain" alt="">
                            <div id="e-photo-placeholder" class="text-center text-gray-400 px-2">
                                <i class="fa-solid fa-camera text-2xl mb-1"></i>
                                <p class="text-[11px]">Click to upload (JPG, PNG, WEBP · max 10MB)</p>
                            </div>
                        </label>
                        <input type="file" id="e-photo" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <p class="text-[10px] text-gray-400 mt-1">Leave empty to keep the current photo.</p>
                    </div>

                    <!-- Signature -->
                    <div>
                        <label class="<?= $labelClass ?>">Signature</label>
                        <label for="e-signature"
                            class="flex flex-col items-center justify-center h-32 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-orange-300 hover:bg-orange-50/40 cursor-pointer transition overflow-hidden">
                            <img id="e-signature-preview" class="hidden max-h-full max-w-full object-contain" alt="">
                            <div id="e-signature-placeholder" class="text-center text-gray-400 px-2">
                                <i class="fa-solid fa-signature text-2xl mb-1"></i>
                                <p class="text-[11px]">Click to upload (JPG, PNG, WEBP · max 10MB)</p>
                            </div>
                        </label>
                        <input type="file" id="e-signature" name="signature" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <p class="text-[10px] text-gray-400 mt-1">Leave empty to keep the current signature.</p>
                    </div>
                </div>
            </div>

            <div class="px-5 md:px-6 py-4 flex items-center justify-end gap-3 bg-gray-50/60 rounded-b-2xl">
                <button type="button" data-close="1"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" id="edit-submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> <span id="edit-submit-text">Save Changes</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('profile-form');
            const submitBtn = document.getElementById('profile-submit');
            const submitText = document.getElementById('profile-submit-text');
            const successBox = document.getElementById('profile-success');
            const successMsg = document.getElementById('profile-success-msg');
            const errorBox = document.getElementById('profile-errors');
            const errorList = document.getElementById('profile-errors-list');
            const CSRF = form.querySelector('input[name="csrf"]').value;

            // ── Image preview (photo + signature) ─────────────
            function resetOne(input) {
                const img = document.getElementById(input.dataset.preview);
                const ph = document.getElementById(input.dataset.placeholder);
                img.src = '';
                img.classList.add('hidden');
                ph.classList.remove('hidden');
            }

            document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) { resetOne(input); return; }

                    if (file.size > 10 * 1024 * 1024) {
                        alert('Max file size is 10MB.');
                        input.value = '';
                        resetOne(input);
                        return;
                    }

                    const img = document.getElementById(input.dataset.preview);
                    const ph = document.getElementById(input.dataset.placeholder);
                    img.src = URL.createObjectURL(file);
                    img.classList.remove('hidden');
                    ph.classList.add('hidden');
                });
            });

            document.getElementById('profile-clear').addEventListener('click', () => {
                document.querySelectorAll('input[type="file"][data-preview]').forEach(resetOne);
                hideMessages();
            });

            // ── Messages ──────────────────────────────────────
            function hideMessages() {
                successBox.classList.add('hidden');
                errorBox.classList.add('hidden');
                errorList.innerHTML = '';
            }

            function fillErrors(box, list, errors) {
                list.innerHTML = '';
                errors.forEach(msg => {
                    const li = document.createElement('li');
                    li.textContent = msg; // textContent: ligtas sa XSS
                    list.appendChild(li);
                });
                box.classList.remove('hidden');
            }

            function showErrors(errors) {
                fillErrors(errorBox, errorList, errors);
                errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function showSuccess(msg) {
                successMsg.textContent = msg;
                successBox.classList.remove('hidden');
                successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // ── Submit (insert) ───────────────────────────────
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                hideMessages();

                submitBtn.disabled = true;
                submitText.textContent = 'Saving...';

                try {
                    const res = await fetch(BASE_URL + '/profileajax', {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    const data = await res.json();

                    if (data.success) {
                        showSuccess(data.message || 'Saved.');
                        form.reset(); // ibabalik din sa "Staff" ang level
                        document.querySelectorAll('input[type="file"][data-preview]').forEach(resetOne);
                        setTimeout(() => location.reload(), 1000); // i-refresh ang Saved Profiles list
                    } else {
                        showErrors(data.errors && data.errors.length ? data.errors : [data.message || 'Something went wrong.']);
                    }
                } catch (err) {
                    console.error(err);
                    showErrors(['Cannot reach the server. Please try again.']);
                } finally {
                    submitBtn.disabled = false;
                    submitText.textContent = 'Save Profile';
                }
            });

            // ── Edit modal ────────────────────────────────────
            const modal = document.getElementById('edit-modal');
            const editForm = document.getElementById('edit-form');
            const editSubmit = document.getElementById('edit-submit');
            const editSubmitText = document.getElementById('edit-submit-text');
            const editErrBox = document.getElementById('edit-errors');
            const editErrList = document.getElementById('edit-errors-list');

            const eSig = document.getElementById('e-signature');
            const ePhoto = document.getElementById('e-photo');

            // Generic preview setter para sa photo at signature
            function setPreview(prefix, url) {
                const img = document.getElementById(prefix + '-preview');
                const ph = document.getElementById(prefix + '-placeholder');
                if (url) {
                    img.src = url;
                    img.classList.remove('hidden');
                    ph.classList.add('hidden');
                } else {
                    img.src = '';
                    img.classList.add('hidden');
                    ph.classList.remove('hidden');
                }
            }

            function openEdit(p) {
                editErrBox.classList.add('hidden');
                editErrList.innerHTML = '';
                editForm.reset();

                document.getElementById('e-orig').value = p.employeeid;
                ['employeeid', 'fullname', 'position', 'contactno', 'address', 'sss', 'tin', 'datestarted'].forEach(k => {
                    document.getElementById('e-' + k).value = p[k] || '';
                });

                // Staff / Head
                const level = p.emp_level === 'head' ? 'head' : 'staff';
                const levelRadio = editForm.querySelector('input[name="emp_level"][value="' + level + '"]');
                if (levelRadio) levelRadio.checked = true;

                eSig.value = '';
                ePhoto.value = '';
                setPreview('e-signature', p.signature_url);
                setPreview('e-photo', p.photo_url);

                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeEdit() {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            modal.addEventListener('click', e => {
                if (e.target.closest('[data-close]')) closeEdit();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeEdit();
            });

            // Preview kapag may pinili sa edit modal
            [['e-signature', eSig], ['e-photo', ePhoto]].forEach(([prefix, input]) => {
                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) return;
                    if (file.size > 10 * 1024 * 1024) {
                        alert('Max file size is 10MB.');
                        input.value = '';
                        return;
                    }
                    setPreview(prefix, URL.createObjectURL(file));
                });
            });

            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                editErrBox.classList.add('hidden');

                editSubmit.disabled = true;
                editSubmitText.textContent = 'Saving...';

                try {
                    const res = await fetch(BASE_URL + '/profileupdateajax', {
                        method: 'POST',
                        body: new FormData(editForm)
                    });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        fillErrors(editErrBox, editErrList,
                            data.errors && data.errors.length ? data.errors : [data.message || 'Something went wrong.']);
                    }
                } catch (err) {
                    console.error(err);
                    fillErrors(editErrBox, editErrList, ['Cannot reach the server. Please try again.']);
                } finally {
                    editSubmit.disabled = false;
                    editSubmitText.textContent = 'Save Changes';
                }
            });

            // ── Link / Unlink (account + role) / Official / Edit (row buttons) ────────
            const list = document.getElementById('link-list');
            if (list) {
                list.addEventListener('click', async (e) => {
                    const btn = e.target.closest('button[data-action]');
                    if (!btn) return;

                    const row = btn.closest('li');
                    const action = btn.dataset.action;

                    if (action === 'edit') {
                        openEdit(JSON.parse(row.dataset.profile));
                        return;
                    }

                    const fd = new FormData();
                    fd.append('csrf', CSRF);
                    fd.append('action', action);
                    fd.append('employeeid', row.dataset.employeeid);

                    const isOfficialAction = (action === 'official' || action === 'unofficial');

                    if (action === 'link') {
                        const sel = row.querySelector('.link-select');
                        if (!sel.value) { showErrors(['Please select an account to link.']); return; }
                        fd.append('account_id', sel.value);
                    } else if (action === 'link_role') {
                        const sel = row.querySelector('.role-select');
                        if (!sel.value) { showErrors(['Please select a role to link.']); return; }
                        fd.append('role_id', sel.value);
                    } else if (action === 'official') {
                        const nm = JSON.parse(row.dataset.profile).fullname;
                        if (!confirm('Mark ' + nm + ' as an official employee? A verified badge will show on their account.')) return;
                    } else if (action === 'unofficial') {
                        if (!confirm('Remove the official employee status?')) return;
                    } else if (action === 'unlink_role') {
                        if (!confirm('Unlink this profile from its role?')) return;
                    } else if (!confirm('Unlink this profile from its account?')) {
                        return;
                    }

                    hideMessages();
                    btn.disabled = true;

                    try {
                        const endpoint = isOfficialAction ? '/profileofficialajax' : '/profilelinkajax';
                        const res = await fetch(BASE_URL + endpoint, { method: 'POST', body: fd });
                        const data = await res.json();

                        if (data.success) {
                            location.reload();
                        } else {
                            showErrors(data.errors && data.errors.length ? data.errors : [data.message || 'Something went wrong.']);
                            btn.disabled = false;
                        }
                    } catch (err) {
                        console.error(err);
                        showErrors(['Cannot reach the server. Please try again.']);
                        btn.disabled = false;
                    }
                });
            }
        })();
    </script>
</body>

</html>