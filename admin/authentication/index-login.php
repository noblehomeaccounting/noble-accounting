<?php
// index-login.php

include ROOT_PATH . '/network/connect.php';

$error = '';

// ── Cloudflare Turnstile (human check bago mag-login) ──
// Galing ang keys sa .env → index.php (TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY constants)
// Kapag walang laman sa .env, gagamitin ang TEST keys ng Cloudflare (laging pumapasa) — para sa localhost lang.
$tsSiteKey   = defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== '' ? TURNSTILE_SITE_KEY : '1x00000000000000000000AA';
$tsSecretKey = defined('TURNSTILE_SECRET_KEY') && TURNSTILE_SECRET_KEY !== '' ? TURNSTILE_SECRET_KEY : '1x0000000000000000000000000000000AA';

/**
 * Sine-check sa Cloudflare kung totoo ang token na galing sa widget.
 * Ibinabalik ang true kapag pumasa.
 */
function turnstileVerify(string $secret, string $token): bool
{
    if ($token === '' || strlen($token) > 2048) return false;

    // Kung naka-proxy ang site sa Cloudflare, nasa CF-Connecting-IP ang totoong IP ng user
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $post = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    } else {
        $body = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $post,
            'timeout' => 8,
        ]]));
    }
    if ($body === false) {
        error_log('Turnstile: could not reach Cloudflare');
        return false;   // hindi maabot ang Cloudflare → huwag papasukin
    }
    $res = json_decode($body, true);
    if (empty($res['success'])) {
        error_log('Turnstile failed: ' . implode(',', $res['error-codes'] ?? []));
        return false;
    }
    // Siguraduhing galing sa login widget ang token (hindi sa ibang form)
    if (isset($res['action']) && $res['action'] !== '' && $res['action'] !== 'login') return false;
    return true;
}

function getRouteFor($role, $position, $branch)
{
    $isMainBranch = (strtolower(trim($branch)) === 'main branch'); // i-adjust base sa actual value sa DB mo

    if ($role === 'ACCOUNTING AND FINANCE DEPARTMENT') {
        return match ($position) {
            'head' => 'accounting',
            'staff' => 'accountingstaff',
            'custodian' => 'accountingcustodian',
            'custoassistant' => 'accountingcustodian',
            default => 'accounting',
        };
    }

    if ($role === 'PURCHASE ORDER DEPARTMENT') {
        return $isMainBranch ? 'purchaseorder' : 'crmpurchaseorder';
    }

    if ($role === 'PROJECT COORDINATOR DEPARTMENT') {
        return $isMainBranch ? 'projectcoordinator' : 'coordinator';
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
        'IT DEPARTMENT' => 'it',
        'HUMAN RESOURCES DEPARTMENT' => 'hrprofile',
        'OPERATIONS DEPARTMENT' => 'operation',
        'GRAPHIC DESIGN DEPARTMENT' => 'graphicdesign',
    ];

    return $roleRoutes[$role] ?? 'loginadmin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!turnstileVerify($tsSecretKey, (string) ($_POST['cf-turnstile-response'] ?? ''))) {
        // Inuuna ang human check bago galawin ang database
        $error = 'Please complete the security check and try again.';
    } elseif (empty($email) || empty($password)) {
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
            $_SESSION['username'] = $account['name'];
            $_SESSION['email'] = $account['email'];
            $_SESSION['role'] = $account['role'];
            $_SESSION['position'] = $account['position'];
            $_SESSION['branch'] = $account['branch'];
            $_SESSION['logged_in'] = true;

            $position = $account['position'] ?? '';
            $role = $account['role'];
            $branch = $account['branch'] ?? '';

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
    $role = $_SESSION['role'] ?? '';
    $branch = $_SESSION['branch'] ?? '';

    $route = getRouteFor($role, $position, $branch);

    header('Location: ' . BASE_URL . '/' . $route);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>Admin Login — Noblehome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>

<body class="min-h-screen min-h-[100dvh] m-0 bg-gray-50">

    <div class="min-h-screen min-h-[100dvh] grid grid-cols-1 lg:grid-cols-2">

        
        <!-- LEFT: Brand panel -->
        <div class="relative hidden lg:flex flex-col min-h-screen bg-cover bg-center text-white overflow-hidden"
            style="background-image: url('<?= BASE_URL ?>/icon/building2.png');">

            <!-- Dark overlay -->
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/65 to-black/40"></div>

            <div class="relative z-10 flex flex-col flex-1 pt-12 px-16 xl:px-24">

                <!-- Logo -->
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome logo"
                    class="w-48 h-auto object-contain self-start">

                <!-- Heading + features -->
                <div class="flex-1 flex flex-col justify-center py-5">
                    <h1 class="text-4xl xl:text-5xl font-extrabold tracking-tight leading-tight">
                        Budget Request <span class="text-[#ff9a1f]">Management</span>
                    </h1>

                    <p class="mt-3 text-lg xl:text-xl text-gray-100 leading-snug max-w-md">
                        Streamline budget requests, track submissions, and manage approvals efficiently.
                    </p>

                    <ul class="mt-8 space-y-3">
                        <li class="flex items-center gap-5">
                            <span
                                class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-file-circle-plus"></i>
                            </span>
                            <span class="text-base">Budget Request Submission</span>
                        </li>

                        <li class="flex items-center gap-5">
                            <span
                                class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-list-check"></i>
                            </span>
                            <span class="text-base">Request Tracking & Review</span>
                        </li>

                        <li class="flex items-center gap-5">
                            <span
                                class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-file-circle-check"></i>
                            </span>
                            <span class="text-base">Budget Approval Management</span>
                        </li>

                        <li class="flex items-center gap-5">
                            <span
                                class="w-14 h-14 rounded-xl bg-white/10 border border-white/10 backdrop-blur-sm flex items-center justify-center text-[#ff7a00] text-xl">
                                <i class="fa-solid fa-chart-pie"></i>
                            </span>
                            <span class="text-base">Budget Monitoring & Reports</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tagline bar -->
            <div class="relative z-10 bg-black/70 px-16 xl:px-24 py-10">
                <div class="w-12 h-1 bg-[#ff7a00] mb-6"></div>

                <p class="text-sm tracking-[0.25em] uppercase leading-8 text-gray-100">
                    Smarter budgeting.<br>
                    Better decisions.
                </p>
                
            </div>
        </div>
   

        <!-- RIGHT: Login -->
        <div
            class="relative flex items-center justify-center min-h-screen min-h-[100dvh] px-5 sm:px-8 py-8 sm:py-10 overflow-hidden bg-gray-50">

            <!-- Orange shapes (top-right) -->
            <div class="absolute -top-24 -right-24 w-44 h-44 sm:-top-28 sm:-right-28 sm:w-80 sm:h-80 rotate-45 bg-gradient-to-br from-orange-400 to-[#ff7a00]">
            </div>
            <div
                class="hidden sm:block absolute -top-20 right-24 w-28 h-64 rotate-45 bg-gradient-to-b from-orange-300/70 to-orange-200/20">
            </div>

            <!-- Orange shapes (bottom-right) -->
            <div
                class="absolute -bottom-24 -right-24 w-44 h-44 sm:-bottom-28 sm:-right-28 sm:w-80 sm:h-80 rotate-45 bg-gradient-to-tl from-orange-400 to-[#ff7a00]">
            </div>
            <div
                class="hidden sm:block absolute -bottom-20 right-24 w-28 h-64 rotate-45 bg-gradient-to-t from-orange-300/70 to-orange-200/20">
            </div>

            <!-- Card -->
            <div class="relative z-10 w-full max-w-[540px] px-0 sm:px-10 py-4 sm:py-10">

                <div class="flex justify-center">
                    <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Noblehome logo" class="w-32 sm:w-44 h-auto object-contain">
                </div>

                <h2 class="mt-5 sm:mt-6 text-center text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900">
                    NOBLE<span class="text-[#ff9a1f]">HOME</span> ACCOUNTING
                </h2>
                <p class="mt-2 mb-6 sm:mb-7 text-center text-[13px] sm:text-sm text-gray-500">Please sign in to access your admin account.</p>

                <?php if (!empty($error)): ?>
                    <div role="alert"
                        class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
                        <span
                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-200 text-red-700 font-bold text-xs shrink-0">!</span>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <!-- Email -->
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email Address</label>
                        <div class="relative">
                            <i
                                class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none"></i>
                            <input type="email" id="email" name="email" autocomplete="email"
                                placeholder="Enter your email address"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                                class="w-full h-12 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff7a00] focus:ring-4 focus:ring-orange-500/15 transition">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5">Only <span
                                class="text-[#ff7a00] font-medium">@noble.com</span> emails are accepted.</p>
                    </div>

                    <!-- Password -->
                    <div class="mb-5">
                        <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">Password</label>
                        <div class="relative">
                            <i
                                class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-700 pointer-events-none"></i>
                            <input type="password" id="password" name="password" autocomplete="current-password"
                                placeholder="Enter your password" required
                                class="w-full h-12 pl-11 pr-12 text-sm text-gray-900 placeholder-gray-400 bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff7a00] focus:ring-4 focus:ring-orange-500/15 transition">
                            <button type="button" id="togglePassword" aria-label="Show password"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 flex items-center justify-center text-gray-700 hover:text-gray-900 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#ff7a00]">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>


                    <!-- Security check (Cloudflare Turnstile) -->
                    <div class="mb-5">
                        <div class="cf-turnstile min-h-[65px]"
                            data-sitekey="<?= htmlspecialchars($tsSiteKey) ?>"
                            data-action="login"
                            data-size="flexible"
                            data-theme="light"
                            data-callback="nhTurnstileOk"
                            data-expired-callback="nhTurnstileReset"
                            data-error-callback="nhTurnstileReset"></div>
                        <p id="tsHint" class="text-[11px] text-gray-400 mt-1.5">Verifying you're human… the Sign In button unlocks once it's done.</p>
                    </div>

                    <button type="submit" id="signInBtn" disabled
                        class="disabled:opacity-50 disabled:cursor-not-allowed w-full h-12 sm:h-14 flex items-center justify-center gap-3 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-[#ff7a00] to-orange-400 rounded-lg shadow-md hover:from-[#e56d00] hover:to-orange-500 active:translate-y-px transition">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </button>
                </form>

                <div class="flex items-center gap-3 sm:gap-4 mt-6 sm:mt-7 text-xs sm:text-sm text-gray-500">
                    <span class="flex-1 h-px bg-gray-200"></span>
                    <span class="whitespace-nowrap">NobleHome Admin Panel</span>
                    <span class="flex-1 h-px bg-gray-200"></span>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Show / hide password
        (function () {
            var pwd = document.getElementById('password');
            var btn = document.getElementById('togglePassword');
            var icon = document.getElementById('eyeIcon');

            btn.addEventListener('click', function () {
                var show = pwd.type === 'password';
                pwd.type = show ? 'text' : 'password';
                icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();

        // Remember me — email lang ang sine-save (hindi ang password)
        (function () {
            var emailInput = document.getElementById('email');
            var remember = document.getElementById('remember');
            var KEY = 'noble_remember_email';

            try {
                var saved = localStorage.getItem(KEY);
                if (saved && !emailInput.value) {
                    emailInput.value = saved;
                    remember.checked = true;
                }
            } catch (e) { }

            emailInput.form.addEventListener('submit', function () {
                try {
                    if (remember.checked) {
                        localStorage.setItem(KEY, emailInput.value);
                    } else {
                        localStorage.removeItem(KEY);
                    }
                } catch (e) { }
            });
        })();

        // Cloudflare Turnstile: bukas lang ang Sign In kapag pumasa na ang check
        function nhTurnstileOk() {
            document.getElementById('signInBtn').disabled = false;
            document.getElementById('tsHint').textContent = 'Security check passed.';
        }
        function nhTurnstileReset() {
            document.getElementById('signInBtn').disabled = true;
            document.getElementById('tsHint').textContent = 'Security check expired or failed. Please wait for it to refresh.';
        }

        sessionStorage.clear();
    </script>
</body>

</html>