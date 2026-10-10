<?php
// user/authentication/index-login.php

include ROOT_PATH . '/network/connect.php';

// Already logged in
if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/userhome');
    exit;
}

// Generate CSRF state token
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build Google OAuth 2.0 Authorization URL
$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => BASE_URL . '/callback',  // adjust to your actual callback route
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $state,
]);

$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;

$errors = [
    'state_mismatch' => 'Security check failed. Please try again.',
    'token_failed' => 'Could not verify your Google account. Please try again.',
    'domain_mismatch' => 'Access denied. Only @noble.com accounts are allowed.',
    'not_registered' => 'Your account is not registered in the system.',
    'access_denied' => 'Sign-in was cancelled. Please try again.',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .card-shadow {
            box-shadow:
                0 0 0 1px rgba(249, 115, 22, 0.15),
                0 25px 60px -15px rgba(249, 115, 22, 0.25),
                0 10px 30px -10px rgba(0, 0, 0, 0.15);
        }

        .btn-orange {
            background: linear-gradient(90deg, #f97316, #fb923c);
            box-shadow: 0 8px 20px -6px rgba(249, 115, 22, 0.55);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        }

        .btn-orange:hover {
            filter: brightness(1.05);
            box-shadow: 0 10px 24px -6px rgba(249, 115, 22, 0.65);
        }

        .btn-orange:active {
            transform: scale(0.99);
        }

        .btn-orange:focus-visible {
            outline: 3px solid #fdba74;
            outline-offset: 2px;
        }

        .dots {
            background-image: radial-gradient(#fdba74 1.5px, transparent 1.5px);
            background-size: 12px 12px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            animation: spin 0.7s linear infinite;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp 0.5s ease both;
        }

        @media (prefers-reduced-motion: reduce) {

            .fade-up,
            .spinner {
                animation: none;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-white text-neutral-900 antialiased">

    <div class="relative min-h-screen overflow-hidden"
        style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">

        <!-- Dark fade overlay (left side readable, image shows on right) -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/75 to-black/40"></div>

        <!-- Orange blob decorations (top-left / bottom-left) -->
        <div class="absolute -top-24 -left-24 w-72 h-72 rounded-full bg-orange-500 opacity-90 blur-[2px]"></div>
        <div class="absolute -top-10 -left-10 w-48 h-48 rounded-full bg-orange-200/70"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-orange-500 opacity-90"></div>
        <div class="absolute -bottom-16 -left-10 w-64 h-64 rounded-full bg-orange-300/60"></div>

        <!-- Secure access (top right) -->
        <div class="absolute top-6 right-6 lg:right-10 z-20 hidden sm:flex items-center gap-4 text-sm text-neutral-200">
            <span class="hidden md:block w-20 h-px bg-orange-400"></span>
            <span>Secure Access</span>
            <i class="fa-solid fa-shield-halved text-orange-500 text-lg"></i>
            <span class="dots w-10 h-12 hidden lg:block"></span>
        </div>

        <div
            class="relative z-10 min-h-screen max-w-7xl mx-auto px-6 lg:px-10 py-10 grid lg:grid-cols-2 gap-10 items-center">

            <!-- ========== LEFT: Welcome / Features ========== -->
            <section class="hidden lg:flex flex-col justify-center pl-4 py-6">

                <!-- Brand -->
                <div class="flex items-center gap-6 mb-14">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome logo"
                        class="h-24 w-auto object-contain bg-white rounded-lg p-2">

                    <div class="w-px h-16 bg-white/40"></div>

                    <p class="text-xs text-neutral-300 tracking-[0.3em] leading-7 uppercase">
                        Budget.<br>
                        Planning.<br>
                        Management.
                    </p>
                </div>

                <h1 class="text-5xl font-extrabold leading-tight text-white">
                    <span class="text-orange-500">Budget Request</span><br>
                    Management
                </h1>

                <p class="mt-5 text-lg text-neutral-200 leading-relaxed max-w-md">
                    Manage budget requests, cash vouchers, CRM inquiries and quotations — all in one place.
                </p>

                <!-- Features -->
                <ul class="mt-10 space-y-5">
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Budget Request</p>
                            <p class="text-sm text-neutral-300">Submit requests and track their approval</p>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Cash Vouchers</p>
                            <p class="text-sm text-neutral-300">Prepare, approve and release payments</p>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-file-invoice"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">CRM &amp; Quotation</p>
                            <p class="text-sm text-neutral-300">Follow clients from inquiry to approved quotation</p>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-chart-line"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Reports</p>
                            <p class="text-sm text-neutral-300">View sales and accounting summaries</p>
                        </div>
                    </li>
                </ul>
                <div class="mt-12 flex items-center gap-4 text-xs text-neutral-300 tracking-[0.25em] uppercase">
                    <span class="w-14 h-0.5 bg-orange-500"></span>
                    NobleHome Accounting
                </div>
            </section>

            <!-- ========== RIGHT: Sign-in card ========== -->
            <section class="flex justify-center lg:justify-end">
                <div
                    class="card-shadow fade-up w-full max-w-sm bg-white/95 backdrop-blur rounded-[1.5rem] border border-orange-100 px-6 sm:px-8 py-7">

                    <!-- Logo -->
                    <div class="flex justify-center mb-4">
                        <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome logo"
                            class="h-16 w-auto object-contain">
                    </div>

                    <div class="text-center mb-6">
                        <h2 class="text-2xl font-bold text-neutral-900">
                            Sign <span class="text-orange-500">In</span>
                        </h2>
                        <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                            Use your organization Google account<br class="hidden sm:block"> to access the system.
                        </p>
                    </div>

                    <!-- Error messages from callback redirect -->
                    <?php if (!empty($_GET['error'])): ?>
                        <div role="alert"
                            class="mb-5 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3">
                            <i class="fa-solid fa-circle-exclamation shrink-0 mt-0.5"></i>
                            <span class="leading-relaxed">
                                <?= htmlspecialchars($errors[$_GET['error']] ?? 'An error occurred. Please try again.') ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Google Button — plain anchor, no AJAX needed -->
                    <a href="<?= htmlspecialchars($googleAuthUrl) ?>" id="googleBtn" onclick="handleClick(this)"
                        class="btn-orange w-full flex items-center justify-center gap-3 text-white text-base font-semibold py-3 px-4 rounded-xl">
                        <span class="flex items-center justify-center w-7 h-7 bg-white rounded-full shrink-0">
                            <svg width="16" height="16" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"
                                    fill="#4285F4" />
                                <path
                                    d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"
                                    fill="#34A853" />
                                <path
                                    d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"
                                    fill="#FBBC05" />
                                <path
                                    d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.163 6.656 3.58 9 3.58z"
                                    fill="#EA4335" />
                            </svg>
                        </span>
                        <span id="btnText">Sign in with Google</span>
                        <svg id="btnSpinner" class="hidden spinner w-5 h-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    </a>

                    <p class="text-center text-xs text-neutral-600 mt-5">
                        Don't have an account?
                        <a href="<?= BASE_URL ?>/register"
                            class="font-semibold text-orange-500 hover:text-orange-600 transition-colors ml-1">Register</a>
                    </p>

                    <!-- Footer divider -->
                    <div class="flex items-center gap-3 mt-6 text-xs text-neutral-600">
                        <span class="flex-1 h-px bg-neutral-200"></span>
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-lock text-neutral-500"></i>
                            NobleHome Accounting
                        </span>
                        <span class="flex-1 h-px bg-neutral-200"></span>
                    </div>
                </div>
            </section>

        </div>

        <!-- Copyright -->
        <p class="absolute bottom-4 inset-x-0 z-10 text-center text-xs text-neutral-300">
            &copy; <?= date('Y') ?> Noble Accounting. All rights reserved.
        </p>
    </div>

    <script>
        function handleClick(el) {
            document.getElementById('btnText').textContent = 'Redirecting\u2026';
            document.getElementById('btnSpinner').classList.remove('hidden');
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.8';
        }
    </script>

</body>

</html>