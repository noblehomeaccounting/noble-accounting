<?php
// verify.php — public na page pagkatapos i-scan ang QR ng employee ID
include ROOT_PATH . '/network/connect.php';

function ve($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$id  = trim($_GET['id'] ?? '');
$emp = null;

if ($id !== '' && strlen($id) <= 50) {
    $stmt = $conn->prepare("SELECT employeeid, fullname, position, photo FROM nobleid WHERE employeeid = ? LIMIT 1");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$photo = '';
if ($emp && !empty($emp['photo']) && is_file(ROOT_PATH . '/' . ltrim($emp['photo'], '/'))) {
    $photo = BASE_URL . '/' . ltrim($emp['photo'], '/');
}

$logo = BASE_URL . '/icon/logo.webp';
date_default_timezone_set('Asia/Manila');
$checkedAt = date('M j, Y · g:i A');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Employee Verification — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .vf-page { background: radial-gradient(1200px 600px at 50% -10%, #fff1e6 0%, #f8fafc 55%, #f1f5f9 100%); }
        /* malaking transparent na logo sa likod ng card */
        .vf-mark { position: absolute; right: -48px; bottom: -40px; width: 260px; opacity: .07; pointer-events: none; user-select: none; }
        .vf-ring { background: conic-gradient(from 200deg, #fb923c, #ea580c, #fdba74, #fb923c); }
        .vf-in { animation: vfIn .55s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes vfIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    </style>
</head>

<body class="vf-page min-h-screen flex items-center justify-center p-5">
    <main class="w-full max-w-[360px]">

        <?php if ($emp): ?>
            <section class="vf-in relative overflow-hidden bg-white rounded-[28px] shadow-[0_20px_60px_-20px_rgba(15,23,42,.25)] ring-1 ring-gray-900/5">
                <img src="<?= ve($logo) ?>" alt="" aria-hidden="true" class="vf-mark">

                <!-- brand -->
                <div class="relative flex items-center justify-between px-6 pt-6">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <img src="<?= ve($logo) ?>" alt="NobleHome" class="w-9 h-9 object-contain shrink-0">
                        <div class="leading-tight min-w-0">
                            <p class="text-[12px] font-bold text-gray-900 tracking-wide">NOBLEHOME</p>
                            <p class="text-[9px] font-medium text-gray-400 tracking-[0.16em] truncate">CONSTRUCTION CORPORATION</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-50 text-green-700 text-[10px] font-bold tracking-wide ring-1 ring-green-600/15">
                        <i class="fa-solid fa-circle-check text-[10px]"></i> VERIFIED
                    </span>
                </div>

                <!-- profile -->
                <div class="relative px-6 pt-8 pb-6 text-center">
                    <div class="vf-ring mx-auto w-[124px] h-[124px] rounded-full p-[3px]">
                        <div class="w-full h-full rounded-full bg-white p-[3px]">
                            <?php if ($photo): ?>
                                <img src="<?= ve($photo) ?>" alt="<?= ve($emp['fullname']) ?>" class="w-full h-full rounded-full object-cover bg-gray-100">
                            <?php else: ?>
                                <div class="w-full h-full rounded-full bg-gray-100 text-gray-300 flex items-center justify-center"><i class="fa-solid fa-user text-4xl"></i></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h1 class="mt-5 text-[22px] font-bold text-gray-900 leading-tight"><?= ve(ucwords(strtolower($emp['fullname']))) ?></h1>
                    <?php if (!empty($emp['position'])): ?>
                        <p class="mt-1 text-sm font-medium text-orange-600"><?= ve($emp['position']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- details -->
                <dl class="relative mx-6 divide-y divide-gray-100 border-y border-gray-100 text-sm">
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-400 text-xs">Employee ID</dt>
                        <dd class="font-mono font-semibold text-gray-800 tracking-wide"><?= ve($emp['employeeid']) ?></dd>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-gray-400 text-xs">Status</dt>
                        <dd class="inline-flex items-center gap-1.5 font-semibold text-gray-800">
                            <span class="relative flex w-2 h-2"><span class="absolute inline-flex w-full h-full rounded-full bg-green-400 opacity-75 animate-ping"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-green-500"></span></span>
                            Active
                        </dd>
                    </div>
                </dl>

                <p class="relative px-6 py-5 text-center text-[11px] leading-relaxed text-gray-500">
                    This person is a verified employee of<br><span class="font-semibold text-gray-700">NobleHome Construction Corporation</span>.
                </p>

                <div class="relative bg-gray-50/80 px-6 py-3 text-center text-[10px] text-gray-400">
                    Checked <?= ve($checkedAt) ?>
                </div>
            </section>

        <?php else: ?>
            <section class="vf-in relative overflow-hidden bg-white rounded-[28px] shadow-[0_20px_60px_-20px_rgba(15,23,42,.25)] ring-1 ring-gray-900/5 text-center">
                <img src="<?= ve($logo) ?>" alt="" aria-hidden="true" class="vf-mark">
                <div class="relative px-8 pt-10 pb-8">
                    <img src="<?= ve($logo) ?>" alt="NobleHome" class="w-12 h-12 object-contain mx-auto">
                    <div class="mt-6 mx-auto w-14 h-14 rounded-full bg-red-50 ring-1 ring-red-200 text-red-500 flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </div>
                    <h1 class="mt-4 text-lg font-bold text-gray-900">ID not verified</h1>
                    <p class="mt-1.5 text-xs text-gray-500 leading-relaxed">This ID is invalid or no longer active.<br>Please contact NobleHome HR to confirm.</p>
                    <?php if ($id !== ''): ?>
                        <p class="mt-5 inline-block font-mono text-[11px] text-gray-500 bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-1.5"><?= ve(mb_substr($id, 0, 50)) ?></p>
                    <?php endif; ?>
                </div>
                <div class="relative bg-gray-50/80 px-6 py-3 text-[10px] text-gray-400">Checked <?= ve($checkedAt) ?></div>
            </section>
        <?php endif; ?>

        <p class="mt-5 text-center text-[10px] text-gray-400">Official verification page · NobleHome Construction Corporation</p>
    </main>
</body>

</html>
