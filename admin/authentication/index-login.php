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
            $_SESSION['branch']     = $account['branch'];   // <-- IDINAGDAG
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
    <title>Sign in — Noble Accounting</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-12 relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">

    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/50 z-0"></div>

    <div class="w-full max-w-md relative z-10">

        <!-- Brand -->
        <div class="flex items-center justify-center gap-4 mb-8">
            <div class="flex items-center justify-center w-14 h-14 rounded-lg shrink-0">
                <img src="<?= BASE_URL ?>/icon/logo.png" class="object-contain bg-white rounded-md p-1" alt="error">
            </div>
            <div class="w-px h-12 bg-white"></div>
            <div class="">
                <h1 class="text-xl font-bold tracking-wide text-white uppercase leading-tight">
                    Noble<span class="text-yellow-500">Home</span> Accounting
                </h1>
                <p class="text-xs text-white tracking-widest uppercase mt-0.5">Management System</p>
            </div>
        </div>

        <!-- Card -->
        <div class="rounded shadow-sm px-8 py-10">

            <h2 class="text-lg font-medium text-white mb-1">Sign in to your account</h2>
            <p class="text-sm text-gray-200 mb-6">Enter your credentials to continue.</p>

            <?php if (!empty($error)): ?>
                <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded px-4 py-3 mb-6">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-200 text-red-700 font-bold text-xs shrink-0">!</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- Email -->
                <div class="mb-5">
                    <label for="email" class="block text-xs font-medium tracking-widest uppercase text-white mb-1.5">
                        <i class="fa-solid fa-envelope mr-1"></i> Email
                    </label>
                    <input type="email" id="email" name="email" autocomplete="email" placeholder="yourname@noble.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                        class="w-full px-3 py-2.5 text-sm text-gray-800 bg-gray-50 border border-gray-300 rounded focus:outline-none focus:border-yellow-500 focus:bg-white transition placeholder-gray-400">
                    <p class="text-[11px] text-gray-400 mt-1.5">Only <span class="text-yellow-400 font-medium">@noble.com</span> emails are accepted.</p>
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-xs font-medium tracking-widest uppercase text-white mb-1.5">
                        <i class="fa-solid fa-key mr-1"></i> Password
                    </label>
                    <input type="password" id="password" name="password" autocomplete="current-password"
                        placeholder="Enter password" required
                        class="w-full px-3 py-2.5 text-sm text-gray-800 bg-gray-50 border border-gray-300 rounded focus:outline-none focus:border-yellow-500 focus:bg-white transition">
                </div>

                <button type="submit"
                    class="w-full py-2.5 text-sm font-medium tracking-widest uppercase text-white bg-yellow-700 rounded hover:bg-yellow-600 active:opacity-80 transition">
                    Sign In
                </button>

            </form>

            <div class="text-center text-xs text-white border-t border-gray-100 pt-5 mt-7">
                &copy; <?= date('Y') ?> Noble Accounting. All rights reserved. Version 1.0
            </div>

            <script>
                sessionStorage.clear();
            </script>

        </div>
    </div>
</body>

</html>