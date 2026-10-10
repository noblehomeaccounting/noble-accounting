<?php
// user/authentication/index-register.php

include ROOT_PATH . '/network/connect.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require ROOT_PATH . '/vendor/autoload.php';

// Already logged in
if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/userhome');
    exit;
}

$isLocalhost = str_contains($_SERVER['HTTP_HOST'], 'localhost') ||
    str_contains($_SERVER['HTTP_HOST'], '127.0.0.1');
$ALLOWED_DOMAIN = 'gmail.com';

$error = '';
$success = '';

// ─── Send verification email via PHPMailer ────────────────────────────────────
function sendVerificationEmail(string $toEmail, string $toName, string $verifyUrl): bool
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_FROM'];
        $mail->Password = $_ENV['MAIL_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender & recipient
        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_NAME'] ?? 'NobleHome Accounting');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify your NobleHome account';
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#e8e8e8;font-family:\'DM Sans\',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#e8e8e8;padding:40px 16px;">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden;max-width:480px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="padding:32px 40px 24px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.05);">
              <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.3px;">
                Noble<span style="color:#f59e0b;">Home</span>
              </div>
              <div style="color:#e8e8e8;font-size:10px;letter-spacing:0.15em;text-transform:uppercase;margin-top:4px;">
                Accounting System
              </div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:32px 40px;">
              <p style="color:#e8e8e8;font-size:14px;margin:0 0 8px;">Hi <strong style="color:#ffffff;">' . htmlspecialchars($toName) . '</strong>,</p>
              <p style="color:#e8e8e8;font-size:14px;line-height:1.6;margin:0 0 28px;">
                You\'re almost there! Click the button below to verify your email address and activate your NobleHome account.
              </p>

              <!-- CTA Button -->
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <a href="' . $verifyUrl . '"
                       style="display:inline-block;background:linear-gradient(135deg,#d97706,#b45309);color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:14px 32px;border-radius:10px;letter-spacing:0.01em;">
                      Verify my account →
                    </a>
                  </td>
                </tr>
              </table>

              <p style="color:#e8e8e8;font-size:12px;line-height:1.6;margin:28px 0 0;text-align:center;">
                This link expires in <strong style="color:#525252;">24 hours</strong>.<br>
                If you didn\'t create an account, you can safely ignore this email.
              </p>
            </td>
          </tr>

          <!-- Link fallback -->
          <tr>
            <td style="padding:0 40px 24px;">
              <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:12px 16px;">
                <p style="color:#e8e8e8;font-size:11px;margin:0 0 4px;">Or copy this link:</p>
                <p style="color:#d97706;font-size:11px;margin:0;word-break:break-all;">' . $verifyUrl . '</p>
              </div>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,0.05);text-align:center;">
              <p style="color:#e8e8e8;font-size:11px;margin:0;">
                &copy; ' . date('Y') . ' Noble Accounting. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

        $mail->AltBody = "Hi {$toName},\n\nVerify your NobleHome account:\n\n{$verifyUrl}\n\nThis link expires in 24 hours.\n\n© " . date('Y') . " Noble Accounting.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($name) || empty($email)) {
        $error = 'Please fill in all fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (!$isLocalhost && !str_ends_with($email, '@' . $ALLOWED_DOMAIN)) {
        $error = 'Only @' . $ALLOWED_DOMAIN . ' email accounts are allowed.';

    } else {
        // Check if already registered
        $stmt = $conn->prepare("SELECT id, verified FROM nobleaccount WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['verified']) {
            $error = 'This email is already registered. Please sign in.';

        } else {
            // Rate limit — bago mag-generate ng token
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM nobleaccount WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result['cnt'] >= 1) {
                $error = 'Please wait 1 minute before requesting another verification email.';
            } else {

                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                if ($existing && !$existing['verified']) {
                    // Resend — update token
                    $stmt = $conn->prepare("UPDATE nobleaccount SET name = ?, token = ?, token_expires = ? WHERE email = ?");
                    $stmt->bind_param("ssss", $name, $token, $expires, $email);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // New account
                    $stmt = $conn->prepare("INSERT INTO nobleaccount (name, email, token, token_expires, verified, role) VALUES (?, ?, ?, ?, 0, 'user')");
                    $stmt->bind_param("ssss", $name, $email, $token, $expires);
                    $stmt->execute();
                    $stmt->close();
                }

                $verifyUrl = BASE_URL . '/verify?token=' . $token;

                $sent = sendVerificationEmail($email, $name, $verifyUrl);
                $success = $sent ? 'sent' : 'mail_error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — NobleHome</title>
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

        .btn-orange:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .input-field {
            background: #fff;
            border: 1px solid #e5e5e5;
            color: #171717;
            transition: border-color .2s, box-shadow .2s;
        }

        .input-field:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }

        .input-field::placeholder {
            color: #a3a3a3;
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

        @keyframes pulse-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(249, 115, 22, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(249, 115, 22, 0);
            }
        }

        .pulse {
            animation: pulse-ring 2s infinite;
        }

        @media (prefers-reduced-motion: reduce) {

            .fade-up,
            .spinner,
            .pulse {
                animation: none;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-white text-neutral-900 antialiased">

    <div class="relative min-h-screen overflow-hidden"
        style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">

        <!-- Dark fade overlay -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/75 to-black/40"></div>

        <!-- Orange blob decorations -->
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
                    <span class="text-orange-500">Create Your</span><br>
                    Account
                </h1>

                <p class="mt-5 text-lg text-neutral-200 leading-relaxed max-w-md">
                    Join NobleHome Accounting and manage budget requests, cash vouchers, CRM inquiries and quotations
                    — all in one place.
                </p>

                <!-- Steps -->
                <ul class="mt-10 space-y-5">
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-user-pen"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Enter your details</p>
                            <p class="text-sm text-neutral-300">Provide your full name and email address</p>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-envelope-circle-check"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Verify your email</p>
                            <p class="text-sm text-neutral-300">We'll send a verification link to your inbox</p>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <span
                            class="w-14 h-14 rounded-xl bg-orange-500/20 text-orange-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-right-to-bracket"></i>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Sign in</p>
                            <p class="text-sm text-neutral-300">Access the system once your account is verified</p>
                        </div>
                    </li>
                </ul>
                <div class="mt-12 flex items-center gap-4 text-xs text-neutral-300 tracking-[0.25em] uppercase">
                    <span class="w-14 h-0.5 bg-orange-500"></span>
                    NobleHome Accounting
                </div>
            </section>

            <!-- ========== RIGHT: Register card ========== -->
            <section class="flex justify-center lg:justify-end">
                <div
                    class="card-shadow fade-up w-full max-w-sm bg-white/95 backdrop-blur rounded-[1.5rem] border border-orange-100 px-6 sm:px-8 py-7">

                    <!-- Logo -->
                    <div class="flex justify-center mb-4">
                        <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome logo"
                            class="h-16 w-auto object-contain">
                    </div>

                    <?php if ($success === 'sent'): ?>

                        <!-- Email sent -->
                        <div class="flex flex-col items-center text-center py-4">
                            <div
                                class="w-14 h-14 rounded-full bg-orange-50 border border-orange-200 flex items-center justify-center mb-4 pulse">
                                <i class="fa-solid fa-envelope text-orange-500 text-xl"></i>
                            </div>
                            <h2 class="text-neutral-900 text-xl font-bold mb-2">
                                Check your <span class="text-orange-500">email</span>
                            </h2>
                            <p class="text-neutral-600 text-sm leading-relaxed mb-4">
                                A verification link was sent to<br>
                                <span class="text-orange-500 font-semibold"><?= htmlspecialchars($_POST['email']) ?></span>
                            </p>
                            <p class="text-neutral-500 text-xs leading-relaxed">
                                Link expires in 24 hours. Check your spam folder if you don't see it.
                            </p>
                        </div>

                    <?php elseif ($success === 'mail_error'): ?>

                        <!-- Mail failed -->
                        <div class="flex flex-col items-center text-center py-4">
                            <div
                                class="w-14 h-14 rounded-full bg-red-50 border border-red-200 flex items-center justify-center mb-4">
                                <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl"></i>
                            </div>
                            <h2 class="text-neutral-900 text-xl font-bold mb-2">Email could not be sent</h2>
                            <p class="text-neutral-600 text-sm leading-relaxed mb-5">
                                Your account was created but we couldn't send the verification email. Please contact
                                your administrator.
                            </p>
                            <a href="<?= BASE_URL ?>/register"
                                class="font-semibold text-orange-500 text-sm hover:text-orange-600 transition-colors">
                                ← Try again
                            </a>
                        </div>

                    <?php else: ?>

                        <!-- Form -->
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-neutral-900">
                                Create <span class="text-orange-500">Account</span>
                            </h2>
                            <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                                Enter your details and we'll send<br class="hidden sm:block"> you a verification link.
                            </p>
                        </div>

                        <?php if ($error): ?>
                            <div role="alert"
                                class="mb-5 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3">
                                <i class="fa-solid fa-circle-exclamation shrink-0 mt-0.5"></i>
                                <span class="leading-relaxed"><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-4 mb-5">
                            <div>
                                <label for="inp-name"
                                    class="block text-neutral-700 text-xs font-semibold mb-1.5 tracking-wide uppercase">
                                    <i class="fa-solid fa-user pr-1 text-orange-500"></i>
                                    Full Name
                                </label>
                                <input type="text" name="name" id="inp-name"
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Juan dela Cruz"
                                    class="input-field w-full text-sm rounded-xl px-4 py-3">
                            </div>
                            <div>
                                <label for="inp-email"
                                    class="block text-neutral-700 text-xs font-semibold mb-1.5 tracking-wide uppercase">
                                    <i class="fa-solid fa-envelope pr-1 text-orange-500"></i>
                                    Email Address
                                </label>
                                <input type="email" name="email" id="inp-email"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    placeholder="<?= $isLocalhost ? 'you@example.com' : 'you@' . $ALLOWED_DOMAIN ?>"
                                    class="input-field w-full text-sm rounded-xl px-4 py-3">
                            </div>
                        </div>

                        <button onclick="handleSubmit()" id="submitBtn"
                            class="btn-orange w-full flex items-center justify-center gap-3 text-white text-base font-semibold py-3 px-4 rounded-xl">
                            <i class="fa-solid fa-paper-plane text-sm"></i>
                            <span id="btnText">Send Verification Link</span>
                            <svg id="btnSpinner" class="hidden spinner w-5 h-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        </button>

                    <?php endif; ?>

                    <p class="text-center text-xs text-neutral-600 mt-5">
                        Already have an account?
                        <a href="<?= BASE_URL ?>/loginuser"
                            class="font-semibold text-orange-500 hover:text-orange-600 transition-colors ml-1">Sign
                            in</a>
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
        function handleSubmit() {
            const name = document.getElementById('inp-name')?.value.trim();
            const email = document.getElementById('inp-email')?.value.trim();
            if (!name || !email) return;

            const btn = document.getElementById('submitBtn');
            document.getElementById('btnText').textContent = 'Sending\u2026';
            document.getElementById('btnSpinner').classList.remove('hidden');
            btn.disabled = true;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            [['name', name], ['email', email]].forEach(([k, v]) => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = k; inp.value = v;
                form.appendChild(inp);
            });

            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Enter' && document.getElementById('submitBtn')) handleSubmit();
        });
    </script>

</body>

</html>