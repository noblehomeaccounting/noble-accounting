<?php
//it-registration-account.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_IT];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

/* =========================================================
   AJAX HANDLER — Department & Branch CRUD (settings modal)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => 'Invalid action.'];

    switch ($action) {

        case 'dept_add':
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $response['message'] = 'Department name is required.';
            } else {
                $stmt = $conn->prepare("INSERT INTO nobledepartment (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'id' => $stmt->insert_id, 'name' => $name, 'message' => 'Department added.'];
                } else {
                    $response['message'] = 'Failed to add department. It might already exist.';
                }
                $stmt->close();
            }
            break;

        case 'dept_edit':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($id === 0 || $name === '') {
                $response['message'] = 'Invalid department data.';
            } else {
                $stmt = $conn->prepare("UPDATE nobledepartment SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $name, $id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'id' => $id, 'name' => $name, 'message' => 'Department updated.'];
                } else {
                    $response['message'] = 'Failed to update department.';
                }
                $stmt->close();
            }
            break;

        case 'dept_delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id === 0) {
                $response['message'] = 'Invalid department.';
            } else {
                $check = $conn->prepare(
                    "SELECT COUNT(*) FROM noblerole WHERE role = (SELECT name FROM nobledepartment WHERE id = ?)"
                );
                $check->bind_param("i", $id);
                $check->execute();
                $check->bind_result($inUseCount);
                $check->fetch();
                $check->close();

                if ($inUseCount > 0) {
                    $response['message'] = 'Cannot delete: department is in use by ' . $inUseCount . ' account(s).';
                } else {
                    $stmt = $conn->prepare("DELETE FROM nobledepartment WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'id' => $id, 'message' => 'Department deleted.'];
                    } else {
                        $response['message'] = 'Failed to delete department.';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'branch_add':
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $response['message'] = 'Branch name is required.';
            } else {
                $stmt = $conn->prepare("INSERT INTO noblebranch (name, created_at) VALUES (?, NOW())");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'id' => $stmt->insert_id, 'name' => $name, 'is_main' => 0, 'message' => 'Branch added.'];
                } else {
                    $response['message'] = 'Failed to add branch. It might already exist.';
                }
                $stmt->close();
            }
            break;

        case 'branch_edit':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($id === 0 || $name === '') {
                $response['message'] = 'Invalid branch data.';
            } else {
                $stmt = $conn->prepare("UPDATE noblebranch SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $name, $id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'id' => $id, 'name' => $name, 'message' => 'Branch updated.'];
                } else {
                    $response['message'] = 'Failed to update branch.';
                }
                $stmt->close();
            }
            break;

        case 'branch_delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id === 0) {
                $response['message'] = 'Invalid branch.';
            } else {
                // Prevent deleting the Main Branch
                $mainCheck = $conn->prepare("SELECT is_main FROM noblebranch WHERE id = ?");
                $mainCheck->bind_param("i", $id);
                $mainCheck->execute();
                $mainCheck->bind_result($isMain);
                $mainCheck->fetch();
                $mainCheck->close();

                if ($isMain == 1) {
                    $response['message'] = 'Cannot delete: this is the Main Branch.';
                    echo json_encode($response);
                    exit;
                }

                // noblerole.branch ay pangalan ng branch (hindi id)
                $check = $conn->prepare("SELECT COUNT(*) FROM noblerole WHERE branch = (SELECT name FROM noblebranch WHERE id = ?)");
                $check->bind_param("i", $id);
                $check->execute();
                $check->bind_result($inUseCount);
                $check->fetch();
                $check->close();

                if ($inUseCount > 0) {
                    $response['message'] = 'Cannot delete: branch is in use by ' . $inUseCount . ' account(s).';
                } else {
                    $stmt = $conn->prepare("DELETE FROM noblebranch WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'id' => $id, 'message' => 'Branch deleted.'];
                    } else {
                        $response['message'] = 'Failed to delete branch.';
                    }
                    $stmt->close();
                }
            }
            break;

        case 'branch_set_main':
            $id = intval($_POST['id'] ?? 0);
            if ($id === 0) {
                $response['message'] = 'Invalid branch.';
            } else {
                $conn->begin_transaction();
                try {
                    $conn->query("UPDATE noblebranch SET is_main = 0");
                    $stmt = $conn->prepare("UPDATE noblebranch SET is_main = 1 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();
                    $conn->commit();
                    $response = ['success' => true, 'id' => $id, 'message' => 'Main branch updated.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = 'Failed to set main branch.';
                }
            }
            break;
    }

    echo json_encode($response);
    exit;
}

$success = '';
$error = '';

$departments = [];
$dept_result = $conn->query("SELECT id, name FROM nobledepartment ORDER BY name ASC");
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$branches = [];
$branch_result = $conn->query("SELECT id, name, is_main FROM noblebranch ORDER BY is_main DESC, name ASC");
if ($branch_result) {
    while ($row = $branch_result->fetch_assoc()) {
        $branches[] = $row;
    }
}

// Determine main branch id (for default selection in the dropdown)
$mainBranchId = null;
foreach ($branches as $b) {
    if (!empty($b['is_main'])) {
        $mainBranchId = $b['id'];
        break;
    }
}

if (!empty($_SESSION['reg_success'])) {
    $success = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']); // clear agad
}

/* =========================================================
   MAIN REGISTRATION SUBMIT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $name = trim($_POST['name'] ?? '');
    $email_prefix = trim($_POST['email_prefix'] ?? '');
    $email = $email_prefix . '@noble.com';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $dept_id = intval($_POST['department_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $position = $_POST['position'] ?? 'staff';
    if (!in_array($position, ['staff', 'head', 'custodian', 'custoassistant'], true)) $position = 'staff';

    if (empty($name) || empty($email_prefix) || empty($password) || empty($confirm) || $dept_id === 0 || $branch_id === 0) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9._\-]+$/', $email_prefix)) {
        $error = 'Email prefix contains invalid characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check duplicate email
        $check = $conn->prepare("SELECT id FROM noblerole WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'That email is already taken.';
            $check->close();
        } else {
            $check->close();

            $dept_stmt = $conn->prepare("SELECT name FROM nobledepartment WHERE id = ?");
            $dept_stmt->bind_param("i", $dept_id);
            $dept_stmt->execute();
            $dept_stmt->bind_result($dept_name);
            $dept_stmt->fetch();
            $dept_stmt->close();

            $branch_stmt = $conn->prepare("SELECT name FROM noblebranch WHERE id = ?");
            $branch_stmt->bind_param("i", $branch_id);
            $branch_stmt->execute();
            $branch_stmt->bind_result($branch_name);
            $branch_stmt->fetch();
            $branch_stmt->close();

            if (empty($dept_name)) {
                $error = 'Selected department is invalid.';
            } elseif (empty($branch_name)) {
                $error = 'Selected branch is invalid.';
            } else {
                $role = $dept_name;
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare("INSERT INTO noblerole (name, email, role, password, branch, position) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $name, $email, $role, $hashed, $branch_name, $position);

                if ($stmt->execute()) {
                    // Store success message in session THEN redirect
                    // (ini-escape na sa pag-display, kaya raw dito)
                    $_SESSION['reg_success'] = 'Account for "' . $name . '" registered as ' . $role . ' · ' . ucfirst($position === 'custoassistant' ? 'custodian assistant' : $position) . ' (' . $branch_name . ').';
                    header('Location: ' . BASE_URL . '/superad');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>Create Account</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .reg-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px); }
        .reg-inp {
            width: 100%; height: 42px; padding: 0 12px 0 38px; font-size: 13px; color: #111827; background: #fff;
            border: 1px solid #e5e7eb; border-radius: 10px; outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .reg-inp:focus { border-color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
        .reg-inp.bad { border-color: #fca5a5; box-shadow: 0 0 0 3px #fee2e2; }
        select.reg-inp { appearance: none; padding-right: 34px; cursor: pointer; }
        .reg-ico { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 13px; pointer-events: none; }
        @media (max-width: 639px) { .reg-inp { height: 44px; } }
        .reg-lbl { display: block; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
        .reg-pos[data-on="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; color: #92400e; }
        .reg-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 px-3 pb-6 sm:px-4 md:p-8">
        <div class="max-w-5xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-xl mb-5 overflow-hidden">
                <div class="reg-hazard h-1.5"></div>
                <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-plus text-lg"></i></span>
                        <div>
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">IT · Accounts</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Create Account</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Register a new admin account for a department and branch.</p>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/createaccount" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-300 hover:text-amber-700">
                        <i class="fa-solid fa-users-gear"></i> Manage Accounts
                    </a>
                </div>
            </section>

            <!-- ═══ ALERTS ═══ -->
            <?php if ($success): ?>
                <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 mb-4 text-sm">
                    <i class="fa-solid fa-circle-check mt-0.5 text-emerald-600"></i>
                    <span class="flex-1"><?= htmlspecialchars($success) ?></span>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
                    <span class="flex-1"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-4 sm:gap-5 items-start">

                <!-- ═══ FORM ═══ -->
                <form method="POST" action="" id="registerForm" novalidate class="bg-white border border-gray-200 rounded-xl overflow-hidden">

                    <!-- 1 · Account -->
                    <div class="px-4 sm:px-6 pt-5 sm:pt-6 pb-5">
                        <p class="flex items-center gap-2 text-[12px] font-bold text-gray-800 mb-4">
                            <span class="w-6 h-6 rounded-md bg-gray-900 text-amber-400 text-[11px] flex items-center justify-center">1</span> Account details
                        </p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="name" class="reg-lbl">Full name <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <i class="fa-regular fa-user reg-ico"></i>
                                    <input type="text" id="name" name="name" autocomplete="off" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Juan dela Cruz" class="reg-inp">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="email_prefix" class="reg-lbl">Email <span class="text-red-500">*</span></label>
                                <div class="relative flex">
                                    <i class="fa-regular fa-envelope reg-ico"></i>
                                    <input type="text" id="email_prefix" name="email_prefix" autocomplete="off" value="<?= htmlspecialchars($_POST['email_prefix'] ?? '') ?>" placeholder="juan.delacruz"
                                        class="reg-inp !rounded-r-none">
                                    <span class="shrink-0 inline-flex items-center px-3 text-[13px] font-semibold text-gray-500 bg-gray-50 border border-l-0 border-gray-200 rounded-r-[10px] select-none">@noble.com</span>
                                </div>
                                <p id="emailHint" class="text-[11px] text-gray-400 mt-1.5">Letters, numbers, dot, dash or underscore only.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 2 · Assignment -->
                    <div class="px-4 sm:px-6 py-5 border-t border-gray-100">
                        <p class="flex items-center gap-2 text-[12px] font-bold text-gray-800 mb-4">
                            <span class="w-6 h-6 rounded-md bg-gray-900 text-amber-400 text-[11px] flex items-center justify-center">2</span> Department &amp; branch
                        </p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="department_id" class="reg-lbl">Department <span class="text-red-500">*</span></label>
                                    <button type="button" onclick="openManageModal('dept')" class="text-[11px] font-semibold text-gray-400 hover:text-amber-700 mb-1.5"><i class="fa-solid fa-gear mr-1"></i>Manage</button>
                                </div>
                                <div class="relative">
                                    <i class="fa-regular fa-building reg-ico"></i>
                                    <select id="department_id" name="department_id" class="reg-inp">
                                        <option value="">Select department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"></i>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1.5">Used as the account's role.</p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="branch_id" class="reg-lbl">Branch <span class="text-red-500">*</span></label>
                                    <button type="button" onclick="openManageModal('branch')" class="text-[11px] font-semibold text-gray-400 hover:text-amber-700 mb-1.5"><i class="fa-solid fa-gear mr-1"></i>Manage</button>
                                </div>
                                <div class="relative">
                                    <i class="fa-solid fa-location-dot reg-ico"></i>
                                    <select id="branch_id" name="branch_id" class="reg-inp">
                                        <option value="">Select branch</option>
                                        <?php foreach ($branches as $branch):
                                            $isSelected = isset($_POST['branch_id']) ? ($_POST['branch_id'] == $branch['id']) : (!empty($branch['is_main'])); ?>
                                            <option value="<?= $branch['id'] ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?><?= !empty($branch['is_main']) ? ' (Main)' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"></i>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1.5">The Main branch is picked by default.</p>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="reg-lbl">Position</span>
                                <?php $posNow = $_POST['position'] ?? 'staff'; ?>
                                <input type="hidden" name="position" id="position" value="<?= htmlspecialchars($posNow) ?>">
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" id="posGroup">
                                    <?php foreach ([['staff', 'Staff', 'fa-user'], ['head', 'Head', 'fa-user-tie'], ['custodian', 'Custodian', 'fa-vault'], ['custoassistant', 'Custodian Asst.', 'fa-hand-holding-dollar']] as [$pv, $pl, $pi]): ?>
                                        <button type="button" data-pos="<?= $pv ?>" data-on="<?= $posNow === $pv ? 1 : 0 ?>"
                                            class="reg-pos flex items-center justify-center gap-2 h-10 rounded-lg border border-gray-200 bg-white text-[12px] font-semibold text-gray-600 hover:border-amber-300 transition">
                                            <i class="fa-solid <?= $pi ?> text-[12px]"></i><?= $pl ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1.5">Custodian positions are for Accounting. You can change this later in Manage Accounts.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 3 · Password -->
                    <div class="px-4 sm:px-6 py-5 border-t border-gray-100">
                        <p class="flex items-center gap-2 text-[12px] font-bold text-gray-800 mb-4">
                            <span class="w-6 h-6 rounded-md bg-gray-900 text-amber-400 text-[11px] flex items-center justify-center">3</span> Password
                        </p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="reg-lbl">Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <i class="fa-solid fa-lock reg-ico"></i>
                                    <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Min. 6 characters" class="reg-inp !pr-10">
                                    <button type="button" data-eye="password" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md text-gray-400 hover:text-gray-700" title="Show password"><i class="fa-regular fa-eye text-xs"></i></button>
                                </div>
                                <div class="flex gap-1 mt-2" id="pwBars"><span class="h-1 flex-1 rounded-full bg-gray-100"></span><span class="h-1 flex-1 rounded-full bg-gray-100"></span><span class="h-1 flex-1 rounded-full bg-gray-100"></span><span class="h-1 flex-1 rounded-full bg-gray-100"></span></div>
                                <p id="pwText" class="text-[11px] text-gray-400 mt-1">Use 8+ characters with numbers for a stronger password.</p>
                            </div>
                            <div>
                                <label for="confirm_password" class="reg-lbl">Confirm password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <i class="fa-solid fa-shield-halved reg-ico"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Type it again" class="reg-inp !pr-10">
                                    <button type="button" data-eye="confirm_password" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md text-gray-400 hover:text-gray-700" title="Show password"><i class="fa-regular fa-eye text-xs"></i></button>
                                </div>
                                <p id="cfText" class="text-[11px] text-gray-400 mt-2">&nbsp;</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="px-4 sm:px-6 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pb-4 border-t border-gray-100 bg-gray-50/70 flex flex-wrap items-center justify-between gap-3">
                        <p id="formErr" class="text-[12px] text-red-600 font-medium empty:hidden sm:empty:block"></p>
                        <div class="flex items-center gap-2 w-full sm:w-auto sm:ml-auto">
                            <button type="reset" id="regReset" class="px-4 h-11 sm:h-10 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Clear</button>
                            <button type="submit" id="regSubmit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 h-11 sm:h-10 text-sm font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm disabled:opacity-60">
                                <i class="fa-solid fa-user-plus"></i> Register Account
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ═══ PREVIEW ═══ -->
                <aside class="lg:sticky lg:top-6 space-y-4">
                    <div class="bg-gray-900 rounded-xl overflow-hidden text-white">
                        <div class="reg-hazard h-1"></div>
                        <div class="p-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-400 mb-3">Preview</p>
                            <div class="flex items-center gap-3">
                                <span id="pvInit" class="w-12 h-12 rounded-xl bg-amber-500 text-gray-900 font-black text-lg flex items-center justify-center shrink-0">?</span>
                                <div class="min-w-0">
                                    <p id="pvName" class="font-semibold truncate text-white/40">Full name</p>
                                    <p id="pvEmail" class="text-[12px] text-white/50 truncate">—@noble.com</p>
                                </div>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-[11px]">
                                <div class="rounded-lg bg-white/5 px-3 py-2"><dt class="text-white/40 uppercase tracking-wider text-[9.5px] font-bold">Department</dt><dd id="pvDept" class="font-semibold truncate mt-0.5">—</dd></div>
                                <div class="rounded-lg bg-white/5 px-3 py-2"><dt class="text-white/40 uppercase tracking-wider text-[9.5px] font-bold">Branch</dt><dd id="pvBranch" class="font-semibold truncate mt-0.5">—</dd></div>
                                <div class="col-span-2 rounded-lg bg-white/5 px-3 py-2"><dt class="text-white/40 uppercase tracking-wider text-[9.5px] font-bold">Position</dt><dd id="pvPos" class="font-semibold mt-0.5">Staff</dd></div>
                            </dl>
                        </div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-list-check mr-1 text-amber-700"></i>Checklist</p>
                        <ul id="pvCheck" class="space-y-1.5 text-[12px]"></ul>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- ═══ MANAGE MODAL (Department / Branch) ═══ -->
    <div id="manageModalOverlay" class="fixed inset-0 bg-black/50 hidden items-end sm:items-center justify-center z-[9998] p-0 sm:p-4">
        <div class="bg-white w-full max-w-md rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[92dvh] overflow-y-auto pb-[env(safe-area-inset-bottom)]">
            <div class="reg-hazard h-1"></div>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <span id="manageIcon" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-regular fa-building"></i></span>
                    <h2 id="manageModalTitle" class="text-sm font-semibold text-gray-900">Manage Departments</h2>
                </div>
                <button type="button" onclick="closeManageModal()" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100" aria-label="Close"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
            </div>
            <div class="px-5 py-4">
                <div class="flex gap-2 mb-3">
                    <input type="text" id="manageNewName" placeholder="Enter name" class="flex-1 h-10 px-3 border border-gray-200 rounded-lg text-sm outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                    <button type="button" onclick="submitAdd()" class="inline-flex items-center gap-1.5 px-4 h-10 bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold rounded-lg"><i class="fa-solid fa-plus"></i> Add</button>
                </div>
                <div id="manageMsg" class="text-xs mb-2 hidden"></div>
                <div id="manageList" class="space-y-1.5 max-h-72 overflow-y-auto pr-1 reg-scroll"></div>
                <p id="manageEmpty" class="text-sm text-gray-400 text-center py-6 hidden">No items yet.</p>
            </div>
        </div>
    </div>

    <script>
        const departmentsData = <?= json_encode($departments) ?>;
        const branchesData = <?= json_encode($branches) ?>;

        let currentType = 'dept'; // 'dept' or 'branch'
        let currentItems = [];

        const typeConfig = {
            dept: { title: 'Manage Departments', icon: 'fa-regular fa-building', selectId: 'department_id', addAction: 'dept_add', editAction: 'dept_edit', deleteAction: 'dept_delete' },
            branch: { title: 'Manage Branches', icon: 'fa-solid fa-location-dot', selectId: 'branch_id', addAction: 'branch_add', editAction: 'branch_edit', deleteAction: 'branch_delete', setMainAction: 'branch_set_main' }
        };

        function escapeHtml(str) { const div = document.createElement('div'); div.textContent = str ?? ''; return div.innerHTML; }

        // ═══ FORM: preview, checklist, password ═══
        const regF = id => document.getElementById(id);
        const POS_LABEL = { staff: 'Staff', head: 'Head', custodian: 'Custodian', custoassistant: 'Custodian Assistant' };

        function pwScore(p) {
            let s = 0;
            if (p.length >= 6) s++;
            if (p.length >= 8) s++;
            if (/\d/.test(p) && /[a-z]/i.test(p)) s++;
            if (/[^a-z0-9]/i.test(p) || (/[a-z]/.test(p) && /[A-Z]/.test(p))) s++;
            return p.length < 6 ? (p.length ? 1 : 0) : s;
        }

        function regUpdate() {
            const name = regF('name').value.trim();
            const pre = regF('email_prefix').value.trim();
            const preOk = !pre || /^[a-zA-Z0-9._\-]+$/.test(pre);
            const dept = regF('department_id'), br = regF('branch_id');
            const pw = regF('password').value, cf = regF('confirm_password').value;

            regF('pvInit').textContent = name ? name[0].toUpperCase() : '?';
            regF('pvName').textContent = name || 'Full name';
            regF('pvName').classList.toggle('text-white/40', !name);
            regF('pvEmail').textContent = (pre || '—') + '@noble.com';
            regF('pvDept').textContent = dept.value ? dept.options[dept.selectedIndex].text.trim() : '—';
            regF('pvBranch').textContent = br.value ? br.options[br.selectedIndex].text.trim() : '—';
            regF('pvPos').textContent = POS_LABEL[regF('position').value] || 'Staff';

            regF('email_prefix').classList.toggle('bad', !preOk);
            regF('emailHint').className = `text-[11px] mt-1.5 ${preOk ? 'text-gray-400' : 'text-red-600 font-semibold'}`;
            regF('emailHint').textContent = preOk ? 'Letters, numbers, dot, dash or underscore only.' : 'Remove spaces or special characters.';

            const sc = pwScore(pw);
            const colors = ['bg-gray-100', 'bg-red-400', 'bg-amber-400', 'bg-lime-500', 'bg-emerald-500'];
            [...regF('pwBars').children].forEach((b, i) => b.className = `h-1 flex-1 rounded-full ${i < sc ? colors[sc] : 'bg-gray-100'}`);
            regF('pwText').textContent = !pw ? 'Use 8+ characters with numbers for a stronger password.' : pw.length < 6 ? 'Too short (min. 6).' : ['', 'Weak', 'Fair', 'Good', 'Strong'][sc];
            regF('pwText').className = `text-[11px] mt-1 ${pw && pw.length < 6 ? 'text-red-600 font-semibold' : 'text-gray-500'}`;
            const match = cf && pw === cf;
            regF('cfText').innerHTML = !cf ? '&nbsp;' : match ? '<span class="text-emerald-600 font-semibold"><i class="fa-solid fa-check mr-1"></i>Passwords match</span>' : '<span class="text-red-600 font-semibold"><i class="fa-solid fa-xmark mr-1"></i>Passwords do not match</span>';
            regF('confirm_password').classList.toggle('bad', !!cf && !match);

            const checks = [
                ['Full name', !!name],
                ['Email prefix', !!pre && preOk],
                ['Department', !!dept.value],
                ['Branch', !!br.value],
                ['Password (6+ characters)', pw.length >= 6],
                ['Passwords match', match],
            ];
            regF('pvCheck').innerHTML = checks.map(([l, ok]) => `<li class="flex items-center gap-2 ${ok ? 'text-gray-800' : 'text-gray-400'}">
                <i class="fa-solid ${ok ? 'fa-circle-check text-emerald-500' : 'fa-circle text-gray-200'} text-[12px]"></i>${l}</li>`).join('');
            return checks.every(c => c[1]);
        }

        ['name', 'email_prefix', 'password', 'confirm_password'].forEach(id => regF(id).addEventListener('input', regUpdate));
        ['department_id', 'branch_id'].forEach(id => regF(id).addEventListener('change', regUpdate));
        regF('posGroup').addEventListener('click', e => {
            const b = e.target.closest('[data-pos]'); if (!b) return;
            regF('position').value = b.dataset.pos;
            regF('posGroup').querySelectorAll('[data-pos]').forEach(x => x.dataset.on = x === b ? '1' : '0');
            regUpdate();
        });
        document.querySelectorAll('[data-eye]').forEach(btn => btn.addEventListener('click', () => {
            const inp = regF(btn.dataset.eye), show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.querySelector('i').className = `fa-regular ${show ? 'fa-eye-slash' : 'fa-eye'} text-xs`;
        }));
        regF('regReset').addEventListener('click', () => setTimeout(() => {
            regF('position').value = 'staff';
            regF('posGroup').querySelectorAll('[data-pos]').forEach(x => x.dataset.on = x.dataset.pos === 'staff' ? '1' : '0');
            const main = branchesData.find(b => Number(b.is_main) === 1);
            if (main) regF('branch_id').value = main.id;
            regF('formErr').textContent = '';
            regUpdate();
        }, 0));
        regF('registerForm').addEventListener('submit', e => {
            if (!regUpdate()) {
                e.preventDefault();
                regF('formErr').innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i>Complete the checklist first.';
                return;
            }
            regF('regSubmit').disabled = true;
            regF('regSubmit').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registering…';
        });
        regUpdate();

        // ═══ MANAGE MODAL (Department / Branch CRUD) ═══
        function openManageModal(type) {
            currentType = type;
            currentItems = (type === 'dept' ? departmentsData : branchesData).slice();
            regF('manageModalTitle').textContent = typeConfig[type].title;
            regF('manageIcon').innerHTML = `<i class="${typeConfig[type].icon}"></i>`;
            regF('manageNewName').value = '';
            regF('manageNewName').placeholder = type === 'dept' ? 'e.g. Human Resources Department' : 'e.g. Makati';
            hideMsg();
            renderList();
            const overlay = regF('manageModalOverlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            setTimeout(() => regF('manageNewName').focus(), 50);
        }

        function closeManageModal() {
            const overlay = regF('manageModalOverlay');
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }

        function showMsg(text, isError) {
            const el = regF('manageMsg');
            el.innerHTML = `<i class="fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'} mr-1"></i>${escapeHtml(text)}`;
            el.className = 'text-xs mb-2 rounded-lg px-3 py-2 ' + (isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700');
        }
        function hideMsg() { regF('manageMsg').classList.add('hidden'); }

        function renderList() {
            const listEl = regF('manageList'), emptyEl = regF('manageEmpty');
            listEl.innerHTML = '';
            if (currentItems.length === 0) { emptyEl.classList.remove('hidden'); return; }
            emptyEl.classList.add('hidden');

            currentItems.forEach(item => {
                const isBranch = currentType === 'branch';
                const isMain = isBranch && Number(item.is_main) === 1;
                const row = document.createElement('div');
                row.className = `flex items-center gap-1.5 border rounded-lg pl-3 pr-1.5 py-1.5 ${isMain ? 'border-amber-200 bg-amber-50/50' : 'border-gray-200'}`;
                row.id = 'row-' + item.id;
                const btn = (cls, onclick, title, icon) => `<button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 ${cls}" onclick="${onclick}" title="${title}"><i class="${icon} text-xs"></i></button>`;
                row.innerHTML = `
                    <span class="flex-1 min-w-0 text-[13px] text-gray-800 flex items-center gap-2" id="label-${item.id}">
                        <span class="truncate">${escapeHtml(item.name)}</span>
                        ${isMain ? '<span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide bg-amber-600 text-white px-1.5 py-0.5 rounded">Main</span>' : ''}
                    </span>
                    ${isBranch && !isMain ? btn('hover:text-amber-600 hover:bg-amber-50', `setMain(${item.id})`, 'Set as Main Branch', 'fa-regular fa-star') : ''}
                    ${btn('hover:text-gray-800 hover:bg-gray-100', `startEdit(${item.id})`, 'Edit', 'fa-solid fa-pen')}
                    ${isMain ? '<span class="w-8"></span>' : btn('hover:text-red-600 hover:bg-red-50', `deleteItem(${item.id})`, 'Delete', 'fa-regular fa-trash-can')}`;
                listEl.appendChild(row);
            });
        }

        function startEdit(id) {
            const item = currentItems.find(i => i.id == id);
            if (!item) return;
            const labelEl = regF('label-' + id);
            labelEl.outerHTML = `<input type="text" id="label-${id}" value="${escapeHtml(item.name).replace(/"/g, '&quot;')}"
                class="flex-1 min-w-0 h-8 px-2 border border-amber-300 rounded-md text-[13px] outline-none focus:ring-2 focus:ring-amber-100">`;
            const row = regF('row-' + id);
            row.querySelector('button[title="Edit"]').outerHTML = `<button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-emerald-600 hover:bg-emerald-50" onclick="saveEdit(${id})" title="Save"><i class="fa-solid fa-check text-xs"></i></button>`;
            const inp = regF('label-' + id);
            inp.focus(); inp.select();
            inp.addEventListener('keydown', e => { if (e.key === 'Enter') saveEdit(id); if (e.key === 'Escape') renderList(); });
        }

        function submitAdd() {
            const input = regF('manageNewName');
            const name = input.value.trim();
            if (!name) { showMsg('Name cannot be empty.', true); return; }
            const cfg = typeConfig[currentType];
            postAjax(cfg.addAction, { name }).then(res => {
                if (res.success) {
                    const newItem = { id: res.id, name: res.name };
                    if (currentType === 'branch') newItem.is_main = res.is_main ?? 0;
                    currentItems.push(newItem);
                    updateSourceData();
                    addSelectOption(res.id, res.name);
                    renderList();
                    input.value = '';
                    showMsg(res.message, false);
                } else showMsg(res.message, true);
            });
        }
        regF('manageNewName').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submitAdd(); } });

        function saveEdit(id) {
            const input = regF('label-' + id);
            const newName = input.value.trim();
            if (!newName) { showMsg('Name cannot be empty.', true); return; }
            const cfg = typeConfig[currentType];
            postAjax(cfg.editAction, { id, name: newName }).then(res => {
                if (res.success) {
                    const item = currentItems.find(i => i.id == id);
                    if (item) item.name = res.name;
                    updateSourceData();
                    updateSelectOption(id, res.name);
                    renderList();
                    showMsg(res.message, false);
                } else showMsg(res.message, true);
            });
        }

        function deleteItem(id) {
            const item = currentItems.find(i => i.id == id);
            if (!confirm(`Delete "${item ? item.name : 'this item'}"?`)) return;
            const cfg = typeConfig[currentType];
            postAjax(cfg.deleteAction, { id }).then(res => {
                if (res.success) {
                    currentItems = currentItems.filter(i => i.id != id);
                    updateSourceData();
                    removeSelectOption(id);
                    renderList();
                    showMsg(res.message, false);
                } else showMsg(res.message, true);
            });
        }

        function setMain(id) {
            const cfg = typeConfig[currentType];
            if (!cfg.setMainAction) return;
            postAjax(cfg.setMainAction, { id }).then(res => {
                if (res.success) {
                    currentItems.forEach(i => { i.is_main = (i.id == id) ? 1 : 0; });
                    updateSourceData();
                    updateSelectMainLabels();
                    renderList();
                    showMsg(res.message, false);
                } else showMsg(res.message, true);
            });
        }

        function updateSourceData() {
            const target = currentType === 'dept' ? departmentsData : branchesData;
            target.length = 0;
            target.push(...currentItems);
        }

        function addSelectOption(id, name) {
            const select = regF(typeConfig[currentType].selectId);
            const opt = document.createElement('option');
            opt.value = id; opt.textContent = name;
            select.appendChild(opt);
            regUpdate();
        }

        function updateSelectOption(id, name) {
            const select = regF(typeConfig[currentType].selectId);
            const opt = select.querySelector(`option[value="${id}"]`);
            if (opt) {
                const isMain = branchesData.find(b => b.id == id && Number(b.is_main) === 1);
                opt.textContent = name + (currentType === 'branch' && isMain ? ' (Main)' : '');
            }
            regUpdate();
        }

        function updateSelectMainLabels() {
            if (currentType !== 'branch') return;
            const select = regF('branch_id');
            branchesData.forEach(b => {
                const opt = select.querySelector(`option[value="${b.id}"]`);
                if (opt) opt.textContent = b.name + (Number(b.is_main) === 1 ? ' (Main)' : '');
            });
            regUpdate();
        }

        function removeSelectOption(id) {
            const select = regF(typeConfig[currentType].selectId);
            const opt = select.querySelector(`option[value="${id}"]`);
            if (opt) { if (opt.selected) select.value = ''; opt.remove(); }
            regUpdate();
        }

        function postAjax(action, data) {
            const body = new URLSearchParams({ ajax_action: action, ...data });
            return fetch('', { method: 'POST', body })
                .then(r => r.json())
                .catch(() => ({ success: false, message: 'Network error.' }));
        }

        regF('manageModalOverlay').addEventListener('click', function (e) { if (e.target === this) closeManageModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !regF('manageModalOverlay').classList.contains('hidden')) closeManageModal(); });
    </script>
</body>

</html>