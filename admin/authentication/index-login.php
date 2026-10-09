<?php
// index-login.php

include ROOT_PATH . '/network/connect.php';

$error = '';

function getRouteFor($role, $position, $branch) {
    $isMainBranch = (strtolower(trim($branch)) === 'main branch'); // i-adjust base sa actual value sa DB mo

    if ($role === 'ACCOUNTING AND FINANCE DEPARTMENT') {
        return match($position) {
            'head'           => 'accounting',
            'staff'          => 'accountingstaff',
            'custodian'      => 'accountingcustodian',
            'custoassistant' => 'accountingcustodianassistant',
            default          => 'accounting',
        };
    }

    if ($role === 'SALES AND MARKETING DEPARTMENT') {
        return $isMainBranch ? 'salesmarket' : 'crmsales';
    }

    if ($role === 'DESIGN DEPARTMENT') {
        return $isMainBranch ? 'designer' : 'crmdesigner';
    }

    if ($role === 'SUPER ADMIN') {
        return $isMainBranch ? 'superadmin' : 'crm-main';
    }

    if ($role === 'ORDER PROCESSING/CUTTING LIST DEPARTMENT') {
        return $isMainBranch ? 'cuttinglist' : 'crmcuttinglist';
    }

    $roleRoutes = [
        'IT DEPARTMENT'                            => 'it',
        'HUMAN RESOURCES DEPARTMENT'               => 'humanresource',
        'OPERATIONS DEPARTMENT'                    => 'operation',
        'GRAPHIC DESIGN DEPARTMENT'                => 'graphicdesign',
    ];

    return $roleRoutes[$role] ?? 'loginadmin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } elseif (!str_ends_with(strtolower($email), '@noble.com')) {
        $error = 'Only @noble.com email addresses are allowed.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM noblerole WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result->fetch_assoc();
        $stmt->close();

        if ($account && password_verify($password, $account['password'])) {
            $_SESSION['account_id'] = $account['id'];
            $_SESSION['username']   = $account['name'];
            $_SESSION['email']      = $account['email'];
            $_SESSION['role']       = $account['role'];
            $_SESSION['position']   = $account['position'];
            $_SESSION['branch']     = $account['branch'];
            $_SESSION['logged_in']  = true;

            $position = $account['position'] ?? '';
            $role     = $account['role'];
            $branch   = $account['branch'] ?? '';

            $route = getRouteFor($role, $position, $branch);

            header('Location: ' . BASE_URL . '/' . $route);
            exit;

        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Already logged in — redirect sa tamang page
if (!empty($_SESSION['logged_in'])) {
    $position = $_SESSION['position'] ?? '';
    $role     = $_SESSION['role'] ?? '';
    $branch   = $_SESSION['branch'] ?? '';

    $route = getRouteFor($role, $position, $branch);

    header('Location: ' . BASE_URL . '/' . $route);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Noblehome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="min-h-screen m-0 bg-gray-50">

    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">

        <!-- LEFT: Brand panel -->
        <div class="relative hidden lg:flex flex-col min-h-screen bg-cover bg-center text-white overflow-hidden"
            style="background-image: url('<?= BASE_URL ?>/icon/building2.png');">

            <!-- Dark overlay -->
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/65 to-black/40"></div>

            <div class="relative z-10 flex flex-col flex-1 pt-12 px-16 xl:px-24">

                <!-- Logo -->
                <img src="<?= BASE_URL ?>/icon/logo.png" alt="Noblehome logo"
                    class="w-48 h-auto object-contain self-start">

                <!-- Welcome + features -->
                <div class="flex-1 flex flex-col justify-center py-5">
                    <h1 class="text-5xl xl:text-6xl font-extrabold tracking-tight leading-tight">
                        Welcome <span class="text-[#ff9a1f]">Back!</span>
                    </h1>
                    <p class="mt-3 text-lg xl:text-xl text-gray-100 leading-snug max-w-md">
                        Access your admin dashboard and manage your accounting efficiently.
                    </p>

                    <ul class="mt-8 space-y-3">
                        <li class="flex items-center gap-5">
                            <span class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-chart-simple"></i>
                            </span>
                            <span class="text-base">Manage Request</span>
                        </li>
                        <li class="flex items-center gap-5">
                            <span class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-cube"></i>
                            </span>
                            <span class="text-base">Budget Request</span>
                        </li>
                        <li class="flex items-center gap-5">
                            <span class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-users"></i>
                            </span>
                            <span class="text-base">User Management</span>
                        </li>
                        <li class="flex items-center gap-5">
                            <span class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-gear"></i>
                            </span>
                            <span class="text-base">System Settings</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tagline bar -->
            <div class="relative z-10 bg-black/70 px-16 xl:px-24 py-10">
                <div class="w-12 h-1 bg-[#ff7a00] mb-6"></div>
                <p class="text-sm tracking-[0.25em] uppercase leading-8 text-gray-100">
                    Better management.<br>Easy to Manage.
                </p>
            </div>
        </div>

        <!-- RIGHT: Login -->
        <div class="relative flex items-center justify-center min-h-screen px-4 sm:px-8 py-10 overflow-hidden bg-gray-50">

            <!-- Orange shapes (top-right) -->
            <div class="absolute -top-28 -right-28 w-80 h-80 rotate-45 bg-gradient-to-br from-orange-400 to-[#ff7a00]"></div>
            <div class="absolute -top-20 right-24 w-28 h-64 rotate-45 bg-gradient-to-b from-orange-300/70 to-orange-200/20"></div>

            <!-- Orange shapes (bottom-right) -->
            <div class="absolute -bottom-28 -right-28 w-80 h-80 rotate-45 bg-gradient-to-tl from-orange-400 to-[#ff7a00]"></div>
            <div class="absolute -bottom-20 right-24 w-28 h-64 rotate-45 bg-gradient-to-t from-orange-300/70 to-orange-200/20"></div>

            <!-- Card -->
            <div class="relative z-10 w-full max-w-[540px] px-8 sm:px-10 py-10">

                <div class="flex justify-center">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="Noblehome logo" class="w-44 h-auto object-contain">
                </div>

                <h2 class="mt-6 text-center text-3xl font-extrabold tracking-tight text-gray-900">
                    NOBLE<span class="text-[#ff9a1f]">HOME</span> ACCOUNTING
                </h2>
                <p class="mt-2 mb-7 text-center text-sm text-gray-500">Please sign in to access your admin account.</p>

                <?php if (!empty($error)): ?>
                    <div role="alert" class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-200 text-red-700 font-bold text-xs shrink-0">!</span>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <!-- Email -->
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email Address</label>
                        <div class="relative">
                            <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none"></i>
                            <input type="email" id="email" name="email" autocomplete="email"
                                placeholder="Enter your email address"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                                class="w-full h-12 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff7a00] focus:ring-4 focus:ring-orange-500/15 transition">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5">Only <span class="text-[#ff7a00] font-medium">@noble.com</span> emails are accepted.</p>
                    </div>

                    <!-- Password -->
                    <div class="mb-5">
                        <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none"></i>
                            <input type="password" id="password" name="password" autocomplete="current-password"
                                placeholder="Enter your password" required
                                class="w-full h-12 pl-11 pr-12 text-sm text-gray-900 placeholder-gray-400 bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff7a00] focus:ring-4 focus:ring-orange-500/15 transition">
                            <button type="button" id="togglePassword" aria-label="Show password"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 flex items-center justify-center text-gray-700 hover:text-gray-900 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#ff7a00]">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>


                    <button type="submit"
                        class="w-full h-14 flex items-center justify-center gap-3 text-lg font-semibold text-white bg-gradient-to-r from-[#ff7a00] to-orange-400 rounded-lg shadow-md hover:from-[#e56d00] hover:to-orange-500 active:translate-y-px transition">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </button>
                </form>

                <div class="flex items-center gap-4 mt-7 text-sm text-gray-500">
                    <span class="flex-1 h-px bg-gray-200"></span>
                    <span>NobleHome Admin Panel</span>
                    <span class="flex-1 h-px bg-gray-200"></span>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Show / hide password
        (function() {
            var pwd = document.getElementById('password');
            var btn = document.getElementById('togglePassword');
            var icon = document.getElementById('eyeIcon');

            btn.addEventListener('click', function() {
                var show = pwd.type === 'password';
                pwd.type = show ? 'text' : 'password';
                icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();

        // Remember me — email lang ang sine-save (hindi ang password)
        (function() {
            var emailInput = document.getElementById('email');
            var remember = document.getElementById('remember');
            var KEY = 'noble_remember_email';

            try {
                var saved = localStorage.getItem(KEY);
                if (saved && !emailInput.value) {
                    emailInput.value = saved;
                    remember.checked = true;
                }
            } catch (e) {}

            emailInput.form.addEventListener('submit', function() {
                try {
                    if (remember.checked) {
                        localStorage.setItem(KEY, emailInput.value);
                    } else {
                        localStorage.removeItem(KEY);
                    }
                } catch (e) {}
            });
        })();

        sessionStorage.clear();
    </script>
</body>

</html>