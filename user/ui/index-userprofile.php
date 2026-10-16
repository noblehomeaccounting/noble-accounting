<?php
// index-userprofile.php
include ROOT_PATH . '/network/connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// ── Helpers (naka-guard para walang conflict sa ibang include) ─
if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('imgUrl')) {
    function imgUrl(?string $p): string
    {
        return $p ? BASE_URL . '/' . ltrim($p, '/') : '';
    }
}
if (!function_exists('imgUrlIfExists')) {
    function imgUrlIfExists(?string $p): string
    {
        if (!$p || !is_file(ROOT_PATH . '/' . ltrim($p, '/'))) {
            return '';
        }
        return imgUrl($p);
    }
}
// SSS/TIN: huling 3 character lang ang kita
if (!function_exists('maskId')) {
    function maskId(?string $v): string
    {
        $v = (string) $v;
        if ($v === '') return '';
        $keep = 3;
        return preg_replace('/[0-9A-Za-z]/', '•', substr($v, 0, -$keep)) . substr($v, -$keep);
    }
}

// ── Account ng naka-login ─────────────────────────────────────
// PALITAN kung ibang session key ang gamit mo para sa nobleaccount.id
$accountId = (int) ($_SESSION['account_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$sessName  = $_SESSION['name'] ?? 'User';
$sessEmail = $_SESSION['email'] ?? '';

$emp = null;
if ($accountId > 0) {
    $stmt = $conn->prepare("SELECT * FROM nobleid WHERE account_id = ? LIMIT 1");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$qr = $signature = $photo = $emgName = $emgNo = $started = $startedLong = '';
$isHead = $isOfficial = false;
if ($emp) {
    $qr          = imgUrlIfExists($emp['qr'] ?? '');
    $signature   = imgUrlIfExists($emp['signature'] ?? '');
    $photo       = imgUrlIfExists($emp['photo'] ?? '');
    $emgName     = $emp['emergency_name'] ?? '';
    $emgNo       = $emp['emergency_contact'] ?? '';
    $started     = !empty($emp['datestarted']) ? date('m/d/y', strtotime($emp['datestarted'])) : '';
    $startedLong = !empty($emp['datestarted']) ? date('F j, Y', strtotime($emp['datestarted'])) : '';
    $isHead      = (($emp['emp_level'] ?? 'staff') === 'head');
    $isOfficial  = !empty($emp['is_official']);
}

$displayName = $emp['fullname'] ?? $sessName;
$initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($displayName)), 0, 2))));

// Tenure (gaano na katagal sa NobleHome)
$tenure = '';
if (!empty($emp['datestarted'])) {
    $diff = (new DateTime($emp['datestarted']))->diff(new DateTime());
    $tenure = $diff->y > 0
        ? $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m ? ' ' . $diff->m . ' mo' : '')
        : ($diff->m > 0 ? $diff->m . ' mo' . ($diff->m > 1 ? 's' : '') : $diff->d . ' days');
}

// ── Bilang ng budget requests ayon sa status ─────────────────
$reqStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$followups = [];
if ($accountId > 0) {
    try {
        $st = $conn->prepare("SELECT LOWER(status) AS s, COUNT(*) AS c FROM noblebudgetrequest WHERE user_id = ? GROUP BY LOWER(status)");
        $st->bind_param("i", $accountId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $reqStats['total'] += (int) $r['c'];
            if (isset($reqStats[$r['s']])) $reqStats[$r['s']] += (int) $r['c'];
        }
        $st->close();

        // Follow-ups: mga request na wala pang attachment
        $st = $conn->prepare("SELECT control_no, purpose, date_requested, status
                              FROM noblebudgetrequest
                              WHERE user_id = ? AND attachment_status = 'follow_up'
                              ORDER BY date_requested DESC");
        $st->bind_param("i", $accountId);
        $st->execute();
        $followups = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    } catch (Throwable $ex) { /* tahimik lang kapag may kulang na column */ }
}
$fuCount = count($followups);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <style>
        /* 1u = laki ng unit; buong card = 100u x 150u (2:3) */
        .id-stage {
            --u: min(3.1px, 0.8vw);
            perspective: 1400px;
            display: flex;
            justify-content: center;
            padding: 20px 0 10px;
            touch-action: none;
            user-select: none;
            -webkit-user-select: none;
            cursor: grab;
        }

        .id-stage.dragging {
            cursor: grabbing;
        }

        .id-card {
            position: relative;
            width: calc(var(--u) * 100);
            height: calc(var(--u) * 150);
            transform-style: preserve-3d;
            will-change: transform;
        }

        .id-face {
            position: absolute;
            inset: 0;
            border-radius: calc(var(--u) * 5);
            overflow: hidden;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            box-shadow: 0 calc(var(--u) * 3) calc(var(--u) * 10) rgba(0, 0, 0, .45);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .id-back {
            transform: rotateY(180deg);
        }

        /* glare na gumagalaw habang umiikot */
        .id-shine {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(115deg, transparent calc(var(--sx, 50%) - 18%), rgba(255, 255, 255, .28) var(--sx, 50%), transparent calc(var(--sx, 50%) + 18%));
            mix-blend-mode: soft-light;
            z-index: 20;
        }

        .id-ground {
            width: calc(var(--u) * 70);
            height: calc(var(--u) * 6);
            margin: calc(var(--u) * 10) auto 0;
            border-radius: 50%;
            background: radial-gradient(ellipse at center, rgba(0, 0, 0, .55), transparent 70%);
            filter: blur(4px);
        }

        /* ───── FRONT ───── */
        .id-front {
            background: linear-gradient(180deg, #f9ddbf 0%, #f5be86 50%, #ec8a2c 100%);
            color: #fff;
        }

        /* Building: buong lapad, mga kalahati ng card, nasa ilalim, fade pataas */
        .f-building {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: calc(var(--u) * 85);
            background-size: cover;
            background-position: center bottom;
            opacity: .75;
            z-index: 0;
            -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 100%);
            mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 100%);
        }

        /* ── Design sa taas ng card ── */
        .f-corner,
        .f-corner2 {
            position: absolute;
            top: 0;
            right: 0;
            pointer-events: none;
        }

        .f-corner2 {
            width: calc(var(--u) * 40);
            height: calc(var(--u) * 40);
            background: #f5a65b;
            opacity: .55;
            clip-path: polygon(10% 0, 100% 0, 100% 90%);
        }

        .f-corner {
            width: calc(var(--u) * 32);
            height: calc(var(--u) * 32);
            background: #e8791a;
            clip-path: polygon(35% 0, 100% 0, 100% 65%);
        }

        .f-dots {
            position: absolute;
            left: calc(var(--u) * 5);
            top: calc(var(--u) * 5);
            width: calc(var(--u) * 18);
            height: calc(var(--u) * 18);
            background-image: radial-gradient(circle, rgba(232, 121, 26, .6) calc(var(--u) * .55), transparent calc(var(--u) * .65));
            background-size: calc(var(--u) * 3.2) calc(var(--u) * 3.2);
            pointer-events: none;
        }

        .f-ring,
        .f-ring2 {
            position: absolute;
            left: 50%;
            top: calc(var(--u) * 14);
            transform: translate(-50%, -50%);
            border-radius: 50%;
            pointer-events: none;
        }

        .f-ring {
            width: calc(var(--u) * 44);
            height: calc(var(--u) * 44);
            border: calc(var(--u) * .35) solid rgba(255, 255, 255, .6);
        }

        .f-ring2 {
            width: calc(var(--u) * 60);
            height: calc(var(--u) * 60);
            border: calc(var(--u) * .3) dashed rgba(232, 121, 26, .28);
        }

        .f-stripes {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(var(--u) * 36);
            height: calc(var(--u) * 42);
            background: repeating-linear-gradient(135deg, rgba(255, 255, 255, .28) 0, rgba(255, 255, 255, .28) calc(var(--u) * .8), transparent calc(var(--u) * .8), transparent calc(var(--u) * 3.4));
            -webkit-mask-image: linear-gradient(180deg, transparent, #000 30%, #000 70%, transparent);
            mask-image: linear-gradient(180deg, transparent, #000 30%, #000 70%, transparent);
            pointer-events: none;
        }

        .f-accent {
            display: flex;
            align-items: center;
            gap: calc(var(--u) * 1.2);
            margin-top: calc(var(--u) * 3);
        }

        .f-accent::before {
            content: '';
            width: calc(var(--u) * 14);
            height: calc(var(--u) * 1);
            border-radius: 99px;
            background: #e8791a;
        }

        .f-accent::after {
            content: '';
            width: calc(var(--u) * 1.4);
            height: calc(var(--u) * 1.4);
            border-radius: 50%;
            background: #fff;
        }

        .f-logo {
            position: absolute;
            top: calc(var(--u) * 5);
            left: 50%;
            transform: translateX(-50%);
            width: calc(var(--u) * 25);
            text-align: center;
            color: #e8791a;
            font-weight: 900;
            font-size: calc(var(--u) * 9);
        }

        .f-logo img {
            width: 100%;
            display: block;
        }

        /* ── Info block: malinis at madaling basahin (dark text sa peach) ── */
        .f-info {
            position: absolute;
            left: calc(var(--u) * 8);
            top: calc(var(--u) * 41);
            width: calc(var(--u) * 62);
            z-index: 3;
            color: #1b2433;
        }

        .f-top {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: calc(var(--u) * 2);
            padding-bottom: calc(var(--u) * 2);
            margin-bottom: calc(var(--u) * 2.4);
            border-bottom: calc(var(--u) * .35) solid rgba(27, 36, 51, .18);
        }

        .f-idbox {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .f-idlabel {
            font-size: calc(var(--u) * 2.2);
            font-weight: 700;
            letter-spacing: .22em;
            color: rgba(27, 36, 51, .55);
        }

        .f-empid {
            margin-top: calc(var(--u) * .6);
            font-size: calc(var(--u) * 3.9);
            font-weight: 700;
            letter-spacing: .08em;
            color: #1b2433;
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }

        .f-name {
            font-size: calc(var(--u) * 7.6);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: .01em;
            text-transform: uppercase;
            color: #1b2433;
            overflow-wrap: break-word;
        }

        .f-posrow {
            display: flex;
            align-items: center;
            gap: calc(var(--u) * 2);
            margin-top: calc(var(--u) * 2.6);
        }

        .f-bar {
            flex: none;
            width: calc(var(--u) * 7);
            height: calc(var(--u) * .9);
            border-radius: 99px;
            background: #e8791a;
        }

        .f-pos {
            font-size: calc(var(--u) * 3.4);
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #a85410;
        }

        /* Staff / Head badge — flat, solid, walang gradient/glow */
        .f-level {
            flex: none;
            display: inline-flex;
            align-items: center;
            gap: calc(var(--u) * 1.4);
            padding: calc(var(--u) * 1.3) calc(var(--u) * 3);
            border-radius: calc(var(--u) * 1.4);
            font-size: calc(var(--u) * 2.8);
            font-weight: 700;
            letter-spacing: .14em;
            line-height: 1;
            white-space: nowrap;
            background: #fff;
            color: #a85410;
            box-shadow: inset 0 0 0 calc(var(--u) * .35) rgba(168, 84, 16, .35);
        }

        .f-level i {
            font-size: calc(var(--u) * 2.6);
        }

        .f-level.head {
            background: #c2570c;
            color: #fff;
            box-shadow: none;
        }

        .f-level.head i {
            color: #ffe2c4;
        }

        .f-qr {
            position: absolute;
            left: calc(var(--u) * 8);
            top: calc(var(--u) * 81);
            width: calc(var(--u) * 23);
            height: calc(var(--u) * 23);
            background: #fff;
            padding: calc(var(--u) * 1.2);
            border-radius: calc(var(--u) * 1.8);
            z-index: 3;
        }

        .f-qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .f-photo {
            position: absolute;
            right: 0;
            bottom: calc(var(--u) * 12);
            width: calc(var(--u) * 66);
            height: calc(var(--u) * 78);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            z-index: 2;
            -webkit-mask-image: linear-gradient(180deg, #000 80%, transparent);
            mask-image: linear-gradient(180deg, #000 80%, transparent);
        }

        .f-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
        }

        .f-photo .ph {
            font-size: calc(var(--u) * 40);
            color: rgba(255, 255, 255, .8);
        }

        .f-sign {
            position: absolute;
            left: calc(var(--u) * 8);
            bottom: calc(var(--u) * 13);
            width: calc(var(--u) * 50);
            z-index: 4;
            text-align: center;
        }

        .f-sign .box {
            height: calc(var(--u) * 13.5);
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .f-sign img {
            max-height: 100%;
            max-width: 80%;
            object-fit: contain;
            background: rgba(255, 255, 255, .78);
            border-radius: calc(var(--u) * 1.8);
            padding: calc(var(--u) * .8);
        }

        .f-sign .line {
            border-top: calc(var(--u) * .45) solid #fff;
            margin-top: calc(var(--u) * 1);
            padding-top: calc(var(--u) * 1.2);
            font-size: calc(var(--u) * 3.3);
            font-weight: 600;
            letter-spacing: .08em;
        }

        .f-band {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: calc(var(--u) * 10);
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: calc(var(--u) * 2);
            overflow: hidden;
            background:
                repeating-linear-gradient(135deg, rgba(255, 255, 255, .10) 0, rgba(255, 255, 255, .10) calc(var(--u) * .9), transparent calc(var(--u) * .9), transparent calc(var(--u) * 3.2)),
                linear-gradient(180deg, #f08f2d, #e8791a);
            border-top: calc(var(--u) * .5) solid rgba(255, 255, 255, .7);
            box-shadow: 0 calc(var(--u) * -1) calc(var(--u) * 3) rgba(232, 121, 26, .45);
        }

        /* mas matingkad na hugis sa kaliwa at mas madilim sa kanan */
        .f-band::before,
        .f-band::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            pointer-events: none;
        }

        .f-band::before {
            left: 0;
            width: calc(var(--u) * 22);
            background: #f5a65b;
            opacity: .75;
            clip-path: polygon(0 0, 100% 0, 70% 100%, 0 100%);
        }

        .f-band::after {
            right: 0;
            width: calc(var(--u) * 22);
            background: #c96a12;
            opacity: .65;
            clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
        }

        .f-band .txt {
            position: relative;
            z-index: 1;
            font-size: calc(var(--u) * 2.9);
            font-weight: 700;
            letter-spacing: .14em;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, .3);
            white-space: nowrap;
        }

        .f-band .dot {
            position: relative;
            z-index: 1;
            width: calc(var(--u) * 1.3);
            height: calc(var(--u) * 1.3);
            border-radius: 50%;
            background: #fff;
        }

        /* ───── BACK ───── */
        .id-back {
            background: linear-gradient(135deg, #e48a2a, #c96a12);
            color: #fff;
            text-align: center;
        }

        .b-wrap {
            position: absolute;
            inset: 0;
            padding: calc(var(--u) * 5.5) calc(var(--u) * 6.7) calc(var(--u) * 4.5);
            display: flex;
            flex-direction: column;
        }

        .b-important {
            border: calc(var(--u) * .45) solid #fff;
            padding: calc(var(--u) * 2.5) calc(var(--u) * 2);
        }

        .b-important h4 {
            font-size: calc(var(--u) * 5.2);
            font-weight: 600;
            line-height: 1.2;
            margin: 0 0 calc(var(--u) * 1);
        }

        .b-important p {
            font-size: calc(var(--u) * 3.3);
            line-height: 1.36;
            margin: 0;
        }

        .b-label {
            font-size: calc(var(--u) * 4.2);
            font-weight: 600;
            line-height: 1.2;
            margin-top: calc(var(--u) * 4.5);
        }

        .b-label + .b-val + .b-label {
            margin-top: calc(var(--u) * 3);
        }

        .b-val {
            font-size: calc(var(--u) * 3.6);
            line-height: 1.25;
            word-break: break-word;
        }

        .b-band {
            margin: calc(var(--u) * 3) calc(var(--u) * -6.7) 0;
            padding: calc(var(--u) * 1.6) 0;
            background: rgba(0, 0, 0, .22);
            font-size: calc(var(--u) * 3.6);
            line-height: 1.33;
        }

        .b-rule {
            border-top: calc(var(--u) * .45) solid rgba(255, 255, 255, .85);
            margin: calc(var(--u) * 3) calc(var(--u) * -6.7) 0;
        }

        .b-emg {
            font-size: calc(var(--u) * 3.3);
            font-weight: 600;
            margin-top: calc(var(--u) * 3.3);
            opacity: .92;
        }

        .b-emg-name {
            font-size: calc(var(--u) * 3.9);
            font-weight: 600;
            margin-top: calc(var(--u) * 1.2);
        }

        .b-ceo {
            margin-top: auto;
        }

        .b-ceo .sig {
            height: calc(var(--u) * 9);
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .b-ceo .sig img {
            height: 100%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .b-ceo .line {
            border-top: calc(var(--u) * .4) solid #fff;
            width: 60%;
            margin: calc(var(--u) * .5) auto 0;
            padding-top: calc(var(--u) * 1.2);
            font-size: calc(var(--u) * 3.6);
            font-weight: 600;
        }

        .b-foot {
            margin-top: calc(var(--u) * 2);
            font-size: calc(var(--u) * 3.3);
            line-height: 1.27;
            opacity: .92;
        }

        .b-foot strong {
            display: block;
            font-weight: 600;
            letter-spacing: .01em;
        }

        /* sukat ng ID sa desktop: sumusunod sa taas ng screen */
        @media (min-width: 1024px) {
            .id-stage, .id-ground {
                --u: clamp(1.5px, calc((100vh - var(--pf-top, 138px) - 300px) / 150), 3px) !important;
            }
            .id-stage { padding: 10px 0 0; }
        }
    </style>
</head>

<body class="relative lg:overflow-hidden"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-attachment: fixed;">
    <div class="fixed inset-0 bg-black/60 z-0"></div>

    <div id="pf-page" class="relative z-10 max-w-[1320px] mx-auto px-4 pb-6 lg:pb-4 lg:h-screen lg:flex lg:flex-col lg:gap-3.5"
        style="padding-top: var(--pf-top, 138px)">

        <!-- ═════ HERO ═════ -->
        <section class="relative overflow-hidden rounded-2xl bg-[#1f1f1f] text-white shadow-2xl shrink-0">
            <div class="absolute inset-x-0 top-0 h-1 bg-[#F7941D]"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-28 lg:w-40 opacity-85 bg-[repeating-linear-gradient(-45deg,#F7941D_0_14px,transparent_14px_28px)] [mask-image:linear-gradient(90deg,transparent,#000_75%)]"></div>

            <div class="relative flex flex-col lg:flex-row lg:items-center gap-4 px-5 py-5 lg:px-6 lg:py-3.5">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-16 h-16 lg:w-14 lg:h-14 shrink-0 rounded-full overflow-hidden bg-[#2f2f2f] ring-2 ring-[#F7941D] ring-offset-2 ring-offset-[#1f1f1f] flex items-center justify-center text-lg font-extrabold text-[#F7941D]">
                        <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="<?= e($displayName) ?>" class="w-full h-full object-cover object-top"><?php else: ?><?= e($initials ?: 'NH') ?><?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#F7941D]">My Profile</p>
                        <div class="flex flex-wrap items-center gap-2 mt-0.5">
                            <h1 class="text-xl font-extrabold leading-tight"><?= e($displayName) ?></h1>
                            <?php if ($isOfficial): ?>
                                <span class="inline-flex items-center gap-1 rounded-full border border-sky-400/40 bg-sky-400/15 px-2 py-0.5 text-[11px] font-semibold text-sky-300">
                                    <i class="fa-solid fa-circle-check"></i> Official Employee
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1 mt-1.5 text-[12.5px] text-neutral-400">
                            <?php if ($emp): ?>
                                <span class="rounded-md px-2 py-0.5 text-[10.5px] font-bold tracking-[0.14em] <?= $isHead ? 'bg-[#F7941D] text-white' : 'bg-white text-[#a85410]' ?>"><?= $isHead ? 'HEAD' : 'STAFF' ?></span>
                                <?php if (!empty($emp['position'])): ?><span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-helmet-safety text-neutral-500 text-xs"></i><?= e($emp['position']) ?></span><?php endif; ?>
                                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-id-badge text-neutral-500 text-xs"></i><?= e($emp['employeeid']) ?></span>
                                <?php if ($tenure): ?><span class="inline-flex items-center gap-1.5"><i class="fa-regular fa-clock text-neutral-500 text-xs"></i><?= e($tenure) ?> with NobleHome</span><?php endif; ?>
                            <?php endif; ?>
                            <?php if ($sessEmail): ?><span class="inline-flex items-center gap-1.5"><i class="fa-regular fa-envelope text-neutral-500 text-xs"></i><?= e($sessEmail) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- quick stats -->
                <div class="grid grid-cols-4 gap-2 lg:flex lg:ml-auto lg:mr-32 shrink-0">
                    <?php
                    $hs = [
                        ['n' => $reqStats['total'],    'l' => 'Requests',   'href' => '/requesthistory',      'alert' => false],
                        ['n' => $reqStats['pending'],  'l' => 'Pending',    'href' => '/requesthistory',      'alert' => false],
                        ['n' => $reqStats['approved'], 'l' => 'Approved',   'href' => '/requesthistory',      'alert' => false],
                        ['n' => $fuCount,              'l' => 'Follow-ups', 'href' => '/followupattachments', 'alert' => $fuCount > 0],
                    ];
                    foreach ($hs as $h): ?>
                        <a href="<?= BASE_URL . $h['href'] ?>"
                            class="flex flex-col justify-center rounded-xl border px-3 py-2 lg:min-w-[84px] transition <?= $h['alert'] ? 'border-red-500/40 bg-red-500/15 hover:bg-red-500/25' : 'border-white/10 bg-white/5 hover:bg-white/10' ?>">
                            <span class="text-lg font-extrabold leading-tight tabular-nums <?= $h['alert'] ? 'text-red-300' : '' ?>"><?= (int) $h['n'] ?></span>
                            <span class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-neutral-400"><?= $h['l'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ═════ 3 COLUMNS ═════ -->
        <div class="grid gap-3.5 mt-3.5 lg:mt-0 lg:flex-1 lg:min-h-0 lg:grid-cols-[auto_290px_1fr] lg:grid-rows-[minmax(0,1fr)]">

            <!-- ID -->
            <section class="order-1 flex flex-col min-h-0 overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="flex items-center gap-2 text-[13px] font-bold uppercase tracking-wider text-gray-900"><i class="fa-solid fa-id-card text-[#F7941D]"></i>Employee ID</h3>
                    <?php if ($emp): ?><span class="text-[11px] text-gray-400">Drag to rotate</span><?php endif; ?>
                </div>
                <div class="flex-1 min-h-0 flex flex-col justify-center px-4 pb-3 bg-[radial-gradient(circle_at_50%_30%,#3a3a3a,#1f1f1f_70%)]">
                    <?php if (!$emp): ?>
                        <div class="py-8 px-5 text-center text-sm text-neutral-400">
                            <i class="fa-solid fa-id-card text-3xl text-[#F7941D] mb-2 block"></i>
                            <b class="block text-white text-base mb-1">No ID linked yet</b>
                            Your ID will appear here once HR links your profile to your account.
                        </div>
                    <?php else: ?>
            <div class="id-stage" id="id-stage">
                <div class="id-card" id="id-card">

                    <!-- FRONT -->
                    <div class="id-face id-front">
                        <div class="f-building" style="background-image:url('<?= BASE_URL ?>/icon/building2.png')"></div>

                        <!-- design sa taas -->
                        <div class="f-stripes"></div>
                        <div class="f-dots"></div>
                        <div class="f-corner2"></div>
                        <div class="f-corner"></div>
                        <div class="f-ring2"></div>
                        <div class="f-ring"></div>

                        <div class="f-logo">
                            <!-- PALITAN ang path ng logo mo -->
                            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome" onerror="this.replaceWith(document.createTextNode('NH'))">
                        </div>

                        <div class="f-info">
                            <div class="f-top">
                                <div class="f-idbox">
                                    <span class="f-idlabel">EMPLOYEE ID</span>
                                    <span class="f-empid"><?= e($emp['employeeid']) ?></span>
                                </div>
                                <span class="f-level<?= $isHead ? ' head' : '' ?>"><i class="fa-solid <?= $isHead ? 'fa-user-tie' : 'fa-user' ?>"></i><?= $isHead ? 'HEAD' : 'STAFF' ?></span>
                            </div>
                            <div class="f-name"><?= e($emp['fullname']) ?></div>
                            <div class="f-posrow">
                                <span class="f-bar"></span>
                                <span class="f-pos"><?= e($emp['position']) ?></span>
                            </div>
                        </div>

                        <?php if ($qr): ?>
                            <div class="f-qr"><img src="<?= e($qr) ?>" alt="QR"></div>
                        <?php endif; ?>

                        <div class="f-photo">
                            <?php if ($photo): ?>
                                <img src="<?= e($photo) ?>" alt="<?= e($emp['fullname']) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-user ph"></i>
                            <?php endif; ?>
                        </div>

                        <div class="f-sign">
                            <div class="box">
                                <?php if ($signature): ?><img src="<?= e($signature) ?>" alt="Signature"><?php endif; ?>
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
                            <div class="b-val"><?= e($emp['contactno']) ?></div>

                            <div class="b-label">ADDRESS</div>
                            <div class="b-val"><?= e($emp['address']) ?></div>

                            <div class="b-band">
                                <?php if (!empty($emp['sss'])): ?>SSS: <?= e($emp['sss']) ?><br><?php endif; ?>
                                <?php if (!empty($emp['tin'])): ?>TIN: <?= e($emp['tin']) ?><br><?php endif; ?>
                                Date Started: <?= e($started) ?>
                            </div>

                            <div class="b-rule"></div>

                            <?php if ($emgName || $emgNo): ?>
                                <div class="b-emg">IN CASE OF EMERGENCY PLEASE CONTACT</div>
                                <div class="b-emg-name"><?= e(strtoupper($emgName)) ?></div>
                                <div class="b-val"><?= e($emgNo) ?></div>
                            <?php endif; ?>

                            <div class="b-ceo">
                                <div class="sig">
                                    <!-- optional: ilagay ang CEO signature sa icon/ceo-signature.png -->
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
            <div class="id-ground"></div>
                        <div class="flex justify-center gap-2 mt-2">
                            <button type="button" id="id-flip" class="inline-flex items-center gap-1.5 rounded-lg bg-[#F7941D] hover:bg-orange-600 px-3.5 py-1.5 text-xs font-semibold text-white transition"><i class="fa-solid fa-rotate"></i>Flip</button>
                            <button type="button" id="id-reset" class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3.5 py-1.5 text-xs font-semibold text-white transition"><i class="fa-solid fa-arrows-to-dot"></i>Reset</button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Personal Info -->
            <?php if ($emp): ?>
            <section class="order-3 lg:order-2 flex flex-col min-h-0 overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="px-5 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="flex items-center gap-2 text-[13px] font-bold uppercase tracking-wider text-gray-900"><i class="fa-solid fa-user text-[#F7941D]"></i>Personal Info</h3>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto px-5 divide-y divide-dashed divide-gray-200">
                    <?php
                    $info = [
                        ['fa-solid fa-phone',        'Contact No.',  $emp['contactno'] ?: '—', null],
                        ['fa-solid fa-location-dot', 'Address',      $emp['address'] ?: '—',   null],
                        ['fa-regular fa-calendar',   'Date Started', $startedLong ?: '—',      null],
                    ];
                    if (!empty($emp['sss'])) $info[] = ['fa-solid fa-shield-halved', 'SSS No.', $emp['sss'], 'secret'];
                    if (!empty($emp['tin'])) $info[] = ['fa-solid fa-shield-halved', 'TIN',     $emp['tin'], 'secret'];
                    if ($emgName || $emgNo)  $info[] = ['fa-solid fa-kit-medical', 'Emergency Contact', trim($emgName . ($emgName && $emgNo ? ' · ' : '') . $emgNo), null];
                    foreach ($info as [$ic, $k, $v, $type]): ?>
                        <div class="flex gap-3 py-2.5">
                            <span class="w-8 h-8 shrink-0 rounded-lg bg-orange-50 text-[#F7941D] flex items-center justify-center text-xs"><i class="<?= $ic ?>"></i></span>
                            <div class="min-w-0">
                                <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-400"><?= e($k) ?></p>
                                <?php if ($type === 'secret'): ?>
                                    <p class="text-[13.5px] font-medium text-gray-900 tabular-nums">
                                        <span class="pf-secret" data-full="<?= e($v) ?>" data-mask="<?= e(maskId($v)) ?>"><?= e(maskId($v)) ?></span>
                                        <button type="button" class="pf-eye ml-1 text-gray-400 hover:text-[#F7941D] text-xs" aria-label="Show"><i class="fa-regular fa-eye"></i></button>
                                    </p>
                                <?php else: ?>
                                    <p class="text-[13.5px] font-medium text-gray-900 break-words"><?= e($v) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- My Activity -->
            <section class="order-2 lg:order-3 flex flex-col min-h-0 overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="px-5 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="flex items-center gap-2 text-[13px] font-bold uppercase tracking-wider text-gray-900"><i class="fa-solid fa-clipboard-list text-[#F7941D]"></i>My Activity</h3>
                </div>

                <div class="flex gap-1 px-3 border-b border-gray-100 shrink-0 overflow-x-auto overflow-y-hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
                    <?php
                    $tabs = [
                        ['req', 'fa-regular fa-file-lines', 'Budget Requests', $reqStats['total'], false],
                        ['vou', 'fa-solid fa-ticket',       'Vouchers',        '…',               false],
                        ['fu',  'fa-solid fa-paperclip',    'Follow-ups',      $fuCount,          $fuCount > 0],
                    ];
                    foreach ($tabs as $i => [$id, $ic, $label, $cnt, $alert]): ?>
                        <button type="button" data-tab="<?= $id ?>"
                            class="pf-tab relative inline-flex items-center gap-2 whitespace-nowrap px-3 py-3.5 text-[13.5px] font-semibold transition <?= $i === 0 ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900' ?>">
                            <i class="<?= $ic ?>"></i><?= $label ?>
                            <span id="cnt-<?= $id ?>" class="pf-cnt min-w-[20px] rounded-full px-1.5 text-center text-[10.5px] font-bold <?= $alert ? 'bg-red-100 text-red-700' : ($i === 0 ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600') ?>"><?= $cnt ?></span>
                            <span class="pf-bar absolute inset-x-2.5 bottom-0 h-[3px] rounded-t bg-[#F7941D] <?= $i === 0 ? '' : 'hidden' ?>"></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php
                $skeleton = str_repeat('<li class="flex items-center gap-3.5 px-5 py-3 animate-pulse"><span class="w-10 h-10 rounded-xl bg-gray-100"></span><div class="flex-1 space-y-2"><div class="h-3 w-2/5 rounded bg-gray-100"></div><div class="h-3 w-3/5 rounded bg-gray-100"></div></div></li>', 4);
                $listCls = 'flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100 [scrollbar-width:thin] [scrollbar-color:#fdba74_transparent]';
                $footCls = 'shrink-0 flex items-center justify-between gap-3 px-5 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-500';
                $linkCls = 'font-semibold text-orange-700 hover:underline';
                ?>

                <!-- Requests -->
                <div class="pf-panel flex flex-col flex-1 min-h-0" id="panel-req">
                    <ul class="<?= $listCls ?>" id="list-req"><?= $skeleton ?></ul>
                    <div class="<?= $footCls ?>"><span id="sum-req">&nbsp;</span><a class="<?= $linkCls ?>" href="<?= BASE_URL ?>/requesthistory">View all requests →</a></div>
                </div>

                <!-- Vouchers -->
                <div class="pf-panel hidden flex-col flex-1 min-h-0" id="panel-vou">
                    <ul class="<?= $listCls ?>" id="list-vou"><?= $skeleton ?></ul>
                    <div class="<?= $footCls ?>"><span id="sum-vou">&nbsp;</span><a class="<?= $linkCls ?>" href="<?= BASE_URL ?>/myvouchers">View all vouchers →</a></div>
                </div>

                <!-- Follow-ups -->
                <div class="pf-panel hidden flex-col flex-1 min-h-0" id="panel-fu">
                    <ul class="<?= $listCls ?>">
                        <?php if (!$followups): ?>
                            <li class="py-12 px-5 text-center text-sm text-gray-500"><i class="fa-solid fa-circle-check block text-2xl text-green-300 mb-2"></i>You're all caught up. All your requests have attachments.</li>
                        <?php else: foreach ($followups as $f): ?>
                            <li class="flex flex-wrap sm:flex-nowrap items-center gap-x-3.5 gap-y-2 px-5 py-3 hover:bg-gray-50 transition">
                                <span class="w-10 h-10 shrink-0 rounded-xl bg-red-50 text-red-700 flex items-center justify-center"><i class="fa-solid fa-paperclip"></i></span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-mono text-[13px] font-bold text-gray-900"><?= e($f['control_no']) ?></p>
                                    <p class="text-[12.5px] text-gray-500 truncate"><?= e($f['purpose'] ?: '—') ?></p>
                                </div>
                                <span class="text-[11.5px] text-gray-400 shrink-0"><?= !empty($f['date_requested']) ? e(date('M j, Y', strtotime($f['date_requested']))) : '' ?></span>
                                <a href="<?= BASE_URL ?>/followupattachments" class="inline-flex items-center justify-center gap-1.5 min-w-[110px] rounded-lg bg-gray-900 hover:bg-black px-3 py-1.5 text-xs font-semibold text-white shrink-0"><i class="fa-solid fa-upload"></i>Upload</a>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                    <div class="<?= $footCls ?>"><span><?= $fuCount ? $fuCount . ' request' . ($fuCount > 1 ? 's' : '') . ' still need files' : 'Nothing pending' ?></span><a class="<?= $linkCls ?>" href="<?= BASE_URL ?>/followupattachments">Open Follow-up Files →</a></div>
                </div>
            </section>
        </div>
    </div>

    <script>
        // Sukatin ang navbar para hindi dumikit ang header
        (function () {
            function fitTop() {
                const page = document.getElementById('pf-page');
                if (!page) return;
                let bottom = 0;
                document.querySelectorAll('body *').forEach(el => {
                    if (page.contains(el) || el.contains(page)) return;
                    const cs = getComputedStyle(el);
                    if (cs.position !== 'fixed' && cs.position !== 'sticky') return;
                    const r = el.getBoundingClientRect();
                    if (r.top < 40 && r.height > 30 && r.height < 220 && r.width > window.innerWidth * 0.5) bottom = Math.max(bottom, r.bottom);
                });
                if (bottom) document.documentElement.style.setProperty('--pf-top', Math.ceil(bottom + 18) + 'px');
            }
            fitTop();
            window.addEventListener('load', fitTop);
            window.addEventListener('resize', fitTop);
        })();

        (function () {
            const BASE = '<?= BASE_URL ?>';
            const LIMIT = 30;
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const peso = n => '₱ ' + (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const total = row => (row.items ?? []).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
            const fmtDate = d => {
                if (!d) return '';
                const x = new Date(String(d).replace(' ', 'T'));
                return isNaN(x) ? '' : x.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
            };
            const empty = (icon, msg) => `<li class="py-12 px-5 text-center text-sm text-gray-500"><i class="${icon} block text-2xl text-gray-300 mb-2"></i>${msg}</li>`;
            const statusTag = (label, color) =>
                `<span class="inline-flex items-center gap-1.5 w-[110px] shrink-0 text-xs font-semibold text-gray-700"><span class="w-1.5 h-1.5 rounded-full" style="background:${color}"></span>${esc(label)}</span>`;
            const row = ({ icon, iconCls, no, sub, mid, amt, date }) => `
                <li class="flex flex-wrap sm:flex-nowrap items-center gap-x-3.5 gap-y-2 px-5 py-3 hover:bg-gray-50 transition">
                    <span class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center ${iconCls}"><i class="${icon}"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-mono text-[13px] font-bold text-gray-900">${no}</p>
                        <p class="text-[12.5px] text-gray-500 truncate">${sub}</p>
                    </div>
                    ${mid}
                    <div class="text-right shrink-0 ml-auto sm:ml-0">
                        <p class="text-[13.5px] font-bold text-gray-900 tabular-nums">${amt}</p>
                        <p class="text-[11.5px] text-gray-400">${date}</p>
                    </div>
                </li>`;

            // ── Tabs ──
            const tabs = document.querySelectorAll('.pf-tab');
            tabs.forEach(t => t.addEventListener('click', () => {
                tabs.forEach(x => {
                    const on = x === t;
                    x.classList.toggle('text-gray-900', on);
                    x.classList.toggle('text-gray-500', !on);
                    x.querySelector('.pf-bar').classList.toggle('hidden', !on);
                    const c = x.querySelector('.pf-cnt');
                    if (!c.classList.contains('bg-red-100')) {
                        c.classList.toggle('bg-gray-900', on); c.classList.toggle('text-white', on);
                        c.classList.toggle('bg-gray-100', !on); c.classList.toggle('text-gray-600', !on);
                    }
                });
                document.querySelectorAll('.pf-panel').forEach(p => {
                    const on = p.id === 'panel-' + t.dataset.tab;
                    p.classList.toggle('hidden', !on);
                    p.classList.toggle('flex', on);
                });
            }));

            // ── SSS / TIN show-hide ──
            document.querySelectorAll('.pf-eye').forEach(b => b.addEventListener('click', () => {
                const s = b.previousElementSibling, shown = s.textContent === s.dataset.full;
                s.textContent = shown ? s.dataset.mask : s.dataset.full;
                b.innerHTML = `<i class="fa-regular ${shown ? 'fa-eye' : 'fa-eye-slash'}"></i>`;
            }));

            // ── Budget Requests ──
            const reqStatus = {
                pending:  ['Pending',  '#f59e0b'],
                approved: ['Approved', '#16a34a'],
                rejected: ['Revised',  '#ef4444'],
            };
            fetch(BASE + '/fetchmyrequests?month=all')
                .then(r => r.json())
                .then(data => {
                    const rows = Array.isArray(data) ? data : [];
                    const list = document.getElementById('list-req');
                    if (!rows.length) { list.innerHTML = empty('fa-regular fa-file-lines', 'No budget requests yet.'); return; }
                    rows.sort((a, b) => new Date(b.date_requested || b.created_at || 0) - new Date(a.date_requested || a.created_at || 0));
                    list.innerHTML = rows.slice(0, LIMIT).map(r => {
                        const [lbl, col] = reqStatus[String(r.status).toLowerCase()] ?? [r.status || '—', '#9ca3af'];
                        const n = (r.items ?? []).length;
                        return row({
                            icon: 'fa-regular fa-file-lines', iconCls: 'bg-stone-100 text-stone-600',
                            no: esc(r.control_no),
                            sub: esc(r.purpose || '—') + (n ? ` · ${n} item${n > 1 ? 's' : ''}` : ''),
                            mid: statusTag(lbl, col),
                            amt: peso(total(r)), date: fmtDate(r.date_requested || r.created_at)
                        });
                    }).join('');
                    const sum = rows.reduce((s, r) => s + total(r), 0);
                    document.getElementById('sum-req').innerHTML = `Total requested: <b class="text-gray-900">${peso(sum)}</b>`;
                })
                .catch(() => { document.getElementById('list-req').innerHTML = empty('fa-solid fa-triangle-exclamation', 'Could not load requests.'); });

            // ── Vouchers ──
            const isReceived = v => !!v.receiver_name || !!v.manual_receiver_name;
            fetch(BASE + '/fetchmyvouchers?month=all')
                .then(r => r.json())
                .then(data => {
                    const rows = Array.isArray(data) ? data : [];
                    const list = document.getElementById('list-vou');
                    const toAccept = rows.filter(v => !isReceived(v)).length;
                    const cnt = document.getElementById('cnt-vou');
                    cnt.textContent = rows.length;
                    if (toAccept) { cnt.className = cnt.className.replace(/bg-gray-\d+|text-gray-\d+|text-white/g, '') + ' bg-red-100 text-red-700'; }
                    if (!rows.length) { list.innerHTML = empty('fa-solid fa-ticket', 'No vouchers released to you yet.'); return; }

                    const dateOf = v => v.released_at || v.date_released || v.release_date || v.created_at;
                    rows.sort((a, b) => (isReceived(a) - isReceived(b)) || (new Date(dateOf(b) || 0) - new Date(dateOf(a) || 0)));
                    list.innerHTML = rows.slice(0, LIMIT).map(v => {
                        const rec = isReceived(v);
                        return row({
                            icon: 'fa-solid fa-ticket', iconCls: rec ? 'bg-stone-100 text-stone-600' : 'bg-orange-50 text-orange-700',
                            no: esc(v.voucher_no || v.cv_no || v.control_no || '—'),
                            sub: esc(v.purpose || '—') + (v.payee ? ' · ' + esc(v.payee) : ''),
                            mid: rec ? statusTag('Received', '#16a34a')
                                     : `<a href="${BASE}/myvouchers" class="inline-flex items-center justify-center gap-1.5 w-[110px] shrink-0 rounded-lg bg-gray-900 hover:bg-black px-3 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-hand-holding-dollar"></i>Accept</a>`,
                            amt: peso(total(v)), date: fmtDate(dateOf(v))
                        });
                    }).join('');
                    document.getElementById('sum-vou').innerHTML = toAccept
                        ? `<b class="text-orange-700">${toAccept}</b> voucher${toAccept > 1 ? 's' : ''} waiting for your acceptance`
                        : 'All vouchers received';
                })
                .catch(() => { document.getElementById('list-vou').innerHTML = empty('fa-solid fa-triangle-exclamation', 'Could not load vouchers.'); });
        })();
    </script>

<?php if ($emp): ?>
            <script>
                (function () {
                    const stage = document.getElementById('id-stage');
                    const card = document.getElementById('id-card');
                    const shines = card.querySelectorAll('.id-shine');

                    let rx = 6, ry = -18;      // current rotation (deg)
                    let vx = 0, vy = 0;        // velocity (inertia)
                    let dragging = false, lastX = 0, lastY = 0;
                    let target = null;         // {rx, ry} para sa Flip/Reset
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
                                rx += (6 - rx) * 0.02;                 // bumabalik sa bahagyang tilt
                                if (Math.abs(vy) < 0.02 && now - idleAt > 2500) {
                                    ry += Math.sin(now / 1500) * 0.08; // mahinang idle sway
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
</body>

</html>