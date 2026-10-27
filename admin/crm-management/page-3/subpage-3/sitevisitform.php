<?php
// crmsitevisit.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


$svError = '';
$svFormError = '';          // error sa form mismo (ipinapakita sa taas ng form, hindi tinatago ang page)
$svSuccess = false;
$svOld = [];                // para hindi mawala ang tinype kapag may error
$currentDesignerId = intval($_SESSION['account_id'] ?? 0);
// NOTE: adjust this to whatever session key actually holds the designer's display name.
$currentDesignerName = $_SESSION['account_name'] ?? $_SESSION['fullname'] ?? ('Designer #' . $currentDesignerId);

$inquiryId = intval($_GET['id'] ?? $_POST['inquiry_id'] ?? 0);
$inquiry = null;

if ($inquiryId <= 0) {
    $svError = 'Missing or invalid inquiry reference.';
} else {
    // Fetch the inquiry, making sure it belongs to the logged-in designer
    // ("mode" is included so we can skip this page entirely when the client
    // already provided a 2D — see redirect guard below).
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, address, contact_number, status, deadline, mode
        FROM noblecrminquiry
        WHERE id = ? AND designer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        $svError = 'Inquiry not found, or not assigned to you.';
    }
}

if (empty($svError) && $inquiry && ($inquiry['mode'] ?? 'site_visit') === 'ready_for_quotation') {
    header('Location: ' . BASE_URL . '/crm2dquotation?id=' . $inquiryId);
    exit;
}

// When this form is opened, mark the related notification as read
if (empty($svError) && $inquiryId > 0) {
    $notifStmt = $conn->prepare("
        UPDATE noblenotification SET is_read = 1
        WHERE request_id = ? AND user_id = ?
    ");
    $notifStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $notifStmt->execute();
    $notifStmt->close();
}

// Convert each uploaded picture to WEBP using GD
function svConvertToWebp(string $tmpPath, string $ext): ?string
{
    $ext = strtolower($ext);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg($tmpPath);
            break;
        case 'png':
            $src = @imagecreatefrompng($tmpPath);
            if ($src) {
                // Preserve transparency for PNG
                imagepalettetotruecolor($src);
                imagealphablending($src, true);
                imagesavealpha($src, true);
            }
            break;
        case 'webp':
            $src = @imagecreatefromwebp($tmpPath);
            break;
        default:
            $src = false;
    }

    if (!$src) {
        return null;
    }

    $webpTmp = tempnam(sys_get_temp_dir(), 'svwebp_') . '.webp';
    $ok = imagewebp($src, $webpTmp, 82); // 82 = quality
    imagedestroy($src);

    return $ok ? $webpTmp : null;
}

// --- Handle submit ---
if (empty($svError) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sitevisit'])) {

    $visitAddress = trim($inquiry['address'] ?? '');
    $visitDatetimeRaw = trim($_POST['visit_datetime'] ?? '');
    $visited = trim($_POST['visited'] ?? '');
    $measurements = ''; // legacy text column — hindi na ginagamit ng form (PDF upload na)
    $siteConditions = trim($_POST['site_conditions'] ?? '');
    $clientRequirements = trim($_POST['client_requirements'] ?? '');
    $existingStructure = trim($_POST['existing_structure'] ?? '');

    $svOld = [
        'visit_datetime' => $visitDatetimeRaw,
        'visited' => $visited,
        'deadline' => trim($_POST['deadline'] ?? ''),
        'site_conditions' => $siteConditions,
        'client_requirements' => $clientRequirements,
        'existing_structure' => $existingStructure,
    ];

    // datetime-local (Y-m-d\TH:i) → Y-m-d H:i:s para sa MySQL strict mode
    $visitDatetime = '';
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $visitDatetimeRaw);
        if ($dt && $dt->format($fmt) === $visitDatetimeRaw) {
            $visitDatetime = $dt->format('Y-m-d H:i:s');
            break;
        }
    }

    $deadlineRaw = $svOld['deadline'];
    $deadline = null;
    if ($deadlineRaw !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $deadlineRaw);
        if ($d && $d->format('Y-m-d') === $deadlineRaw) {
            $deadline = $deadlineRaw;
        }
    }

    if (empty($visitAddress)) {
        $svFormError = 'This inquiry has no registered address. Ask the sales staff to update it first.';
    } elseif ($visitDatetimeRaw === '' || !in_array($visited, ['yes', 'no'], true)) {
        $svFormError = 'Please fill out all required fields.';
    } elseif ($visitDatetime === '') {
        $svFormError = 'The date & time of visit is not valid.';
    } elseif ($deadlineRaw !== '' && $deadline === null) {
        $svFormError = 'The deadline date is not valid.';
    } else {

        // --- Upload pictures (optional, multiple allowed) ---
        $uploadedPaths = [];
        $uploadDir = ROOT_PATH . '/uploads/crm-sitevisit/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            foreach ($_FILES['photos']['name'] as $i => $fileName) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK || $fileName === '') {
                    continue;
                }
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    continue; // skip non-image files
                }

                $tmpName = $_FILES['photos']['tmp_name'][$i];
                $safeName = 'sv_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.webp';
                $destPath = $uploadDir . $safeName;

                $webpTmp = svConvertToWebp($tmpName, $ext);

                if ($webpTmp && rename($webpTmp, $destPath)) {
                    $uploadedPaths[] = 'uploads/crm-sitevisit/' . $safeName;
                } elseif ($webpTmp) {
                    @unlink($webpTmp);
                }
                // If conversion fails, just skip that picture
            }
        }

        // --- Upload measurement PDFs (optional, multiple allowed) ---
        $measurementPaths = [];
        $maxPdfBytes = 15 * 1024 * 1024; // 15MB bawat file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if (!empty($_FILES['measurement_pdfs']) && is_array($_FILES['measurement_pdfs']['name'])) {
            foreach ($_FILES['measurement_pdfs']['name'] as $i => $fileName) {
                if ($_FILES['measurement_pdfs']['error'][$i] !== UPLOAD_ERR_OK || $fileName === '') {
                    continue;
                }
                $tmpName = $_FILES['measurement_pdfs']['tmp_name'][$i];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($ext !== 'pdf' || finfo_file($finfo, $tmpName) !== 'application/pdf') {
                    continue; // skip non-PDF
                }
                if ($_FILES['measurement_pdfs']['size'][$i] > $maxPdfBytes) {
                    continue; // skip lampas sa 15MB
                }

                $safeName = 'svm_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.pdf';
                if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
                    $measurementPaths[] = 'uploads/crm-sitevisit/' . $safeName;
                }
            }
        }
        finfo_close($finfo);

        $photosCsv = implode(',', $uploadedPaths);
        $measurementFilesCsv = implode(',', $measurementPaths);


        $stmt = $conn->prepare("
            INSERT INTO noblecrm_sitevisit
                (inquiry_id, designer_id, address, visit_datetime, visited, photos, measurement_files,
                 measurements, site_conditions, client_requirements, existing_structure,
                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iisssssssss",
            $inquiryId,
            $currentDesignerId,
            $visitAddress,
            $visitDatetime,
            $visited,
            $photosCsv,
            $measurementFilesCsv,
            $measurements,
            $siteConditions,
            $clientRequirements,
            $existingStructure
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Mark the original inquiry as In Progress now that it has proceeded,
            // and save the deadline for 2D & Quotation on the inquiry itself.
            $updateStmt = $conn->prepare("
                UPDATE noblecrminquiry SET status = 'In Progress', deadline = ?
                WHERE id = ? AND designer_id = ?
            ");
            $updateStmt->bind_param("sii", $deadline, $inquiryId, $currentDesignerId);
            $updateStmt->execute();
            $updateStmt->close();

            $_SESSION['sv_flash_success'] = 'Site visit details saved.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $svFormError = 'Something went wrong while saving. Please try again.';
            $stmt->close();
        }
    }
}

if (!empty($_SESSION['sv_flash_success'])) {
    $svSuccess = $_SESSION['sv_flash_success'];
    unset($_SESSION['sv_flash_success']);
}

$svHistory = [];
if (empty($svError) && $inquiryId > 0) {
    $histStmt = $conn->prepare("
        SELECT id, address, visit_datetime, visited, photos, measurement_files,
               measurements, site_conditions, client_requirements, existing_structure,
               created_at
        FROM noblecrm_sitevisit
        WHERE inquiry_id = ? AND designer_id = ?
        ORDER BY created_at DESC
    ");
    $histStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $histStmt->execute();
    $svHistory = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $histStmt->close();
}

$crmDesignerListUrl = BASE_URL . '/crmdesigner';
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation?id=' . $inquiryId;

$svAlreadyDone = !empty($svHistory);
$svOldVal = fn($k) => htmlspecialchars($svOld[$k] ?? '');

// Mga helper para sa view
function svDaysLabel(?string $date): ?array
{
    if (empty($date)) return null;
    $ts = strtotime($date);
    if (!$ts) return null;
    $days = (int) round((strtotime(date('Y-m-d', $ts)) - strtotime('today')) / 86400);
    if ($days === 0) return ['Due today', 'amber'];
    if ($days === 1) return ['Due tomorrow', 'amber'];
    if ($days > 1) return [$days . ' days left', $days <= 3 ? 'amber' : 'gray'];
    return [abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue', 'red'];
}
function svNotes(?string $txt): string
{
    return !empty($txt)
        ? '<p class="text-[13px] text-gray-800 leading-relaxed">' . nl2br(htmlspecialchars($txt)) . '</p>'
        : '<p class="text-[13px] text-gray-400 italic">None provided</p>';
}
$svToneText = ['amber' => 'text-amber-700', 'red' => 'text-red-600', 'gray' => 'text-gray-500'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Visit Record</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/crm-management/css/crm/crmsitevisit.css?v=<?= @filemtime(ROOT_PATH . '/admin/crm-management/css/crm/crmsitevisit.css') ?: 1 ?>">
</head>

<body class="bg-gray-50 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-7 sv-scope bg-[#F5F6FA] overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <?php if (!empty($svError)): ?>

                <div class="sv-card overflow-hidden">
                    <div class="sv-hazard h-1.5"></div>
                    <div class="px-6 py-10 text-center">
                        <span class="w-12 h-12 mx-auto rounded-xl bg-red-50 text-red-500 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($svError) ?></p>
                        <a href="<?= htmlspecialchars($crmDesignerListUrl) ?>"
                            class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                            <i class="fa-solid fa-arrow-left"></i> Back to assigned list
                        </a>
                    </div>
                </div>

            <?php elseif ($inquiry): ?>

                <?php
                $dl = svDaysLabel($inquiry['deadline'] ?? null);
                $latestVisit = $svAlreadyDone ? $svHistory[0] : null;
                $latestVisited = $latestVisit && $latestVisit['visited'] === 'yes';
                $stepState = $svAlreadyDone ? ($latestVisited ? 2 : 1) : 1; // 0 assigned, 1 site visit, 2 2D & quotation
                ?>

                <!-- ═══ HEADER ═══ -->
                <section class="sv-card overflow-hidden mb-4">
                    <div class="sv-hazard h-1.5"></div>
                    <div class="p-5 flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-ruler-combined text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM · Designer · Site Visit</p>
                                <h1 class="text-gray-900 text-lg font-semibold leading-tight truncate capitalize"><?= htmlspecialchars($inquiry['client_name']) ?></h1>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="font-mono text-[11px] font-semibold text-amber-700"><?= htmlspecialchars($inquiry['control_no']) ?></span>
                                    <?php if ($svAlreadyDone): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border <?= $latestVisited ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $latestVisited ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span><?= $latestVisited ? 'Site visit done' : 'Not visited' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>For site visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?= htmlspecialchars($crmDesignerListUrl) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                                <i class="fa-solid fa-arrow-left"></i> Assigned list
                            </a>
                            <?php if ($svAlreadyDone && $inquiry['status'] === 'In Progress'): ?>
                                <a href="<?= htmlspecialchars($crm2dQuotationUrl) ?>"
                                    class="inline-flex items-center gap-2 text-xs font-semibold text-white bg-blue-700 rounded-lg px-3.5 py-2 hover:bg-blue-800 shadow-sm">
                                    <i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stepper -->
                    <div class="px-5 pb-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold">
                            <?php
                            $steps = [['fa-user-check', 'Assigned'], ['fa-ruler-combined', 'Site visit'], ['fa-file-pen', '2D & Quotation']];
                            foreach ($steps as $si => [$ic, $lbl]):
                                $done = $si < $stepState || ($si === 1 && $svAlreadyDone && $latestVisited);
                                $cur = $si === $stepState && !$done;
                                ?>
                                <?php if ($si > 0): ?><span class="sv-step-line <?= $si <= $stepState ? 'sv-done' : '' ?>"></span><?php endif; ?>
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap <?= $done ? 'text-amber-800' : ($cur ? 'text-gray-900' : 'text-gray-400') ?>">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] <?= $done ? 'bg-amber-700 text-white' : ($cur ? 'bg-amber-50 text-amber-700 ring-2 ring-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                                        <i class="fa-solid <?= $done ? 'fa-check' : $ic ?>"></i>
                                    </span>
                                    <span class="hidden sm:inline"><?= htmlspecialchars($lbl) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- ═══ INFO STRIP ═══ -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div class="sv-card px-4 py-3 lg:col-span-2 min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-location-dot mr-1"></i>Site address</p>
                        <p class="text-[13px] font-medium text-gray-900 mt-0.5 break-words"><?= htmlspecialchars($inquiry['address'] ?: '—') ?></p>
                        <?php if (!empty($inquiry['address'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($inquiry['address']) ?>" target="_blank" rel="noopener"
                                class="text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-map-location-dot mr-1"></i>Open in Maps</a>
                        <?php endif; ?>
                    </div>
                    <div class="sv-card px-4 py-3 min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-phone mr-1"></i>Contact no.</p>
                        <?php if (!empty($inquiry['contact_number'])): ?>
                            <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', $inquiry['contact_number'])) ?>" class="block text-[13px] font-semibold text-amber-700 hover:underline mt-0.5 truncate"><?= htmlspecialchars($inquiry['contact_number']) ?></a>
                        <?php else: ?>
                            <p class="text-[13px] text-gray-400 mt-0.5">—</p>
                        <?php endif; ?>
                        <p class="text-[11px] text-gray-400 mt-0.5 truncate">Designer: <?= htmlspecialchars($currentDesignerName) ?></p>
                    </div>
                    <div class="sv-card px-4 py-3 min-w-0 <?= $dl && $dl[1] === 'red' && $inquiry['status'] !== 'Approved' ? 'border-red-200 bg-red-50' : '' ?>">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-regular fa-flag mr-1"></i>2D &amp; Quotation deadline</p>
                        <?php if (!empty($inquiry['deadline'])): ?>
                            <p class="text-[13px] font-semibold text-gray-900 mt-0.5"><?= htmlspecialchars(date('M d, Y', strtotime($inquiry['deadline']))) ?></p>
                            <?php if ($dl && $inquiry['status'] !== 'Approved'): ?><p class="text-[11px] font-semibold <?= $svToneText[$dl[1]] ?>"><?= htmlspecialchars($dl[0]) ?></p><?php endif; ?>
                        <?php else: ?>
                            <p class="text-[13px] text-gray-400 mt-0.5"><?= $svAlreadyDone ? 'Not set' : 'Set it in the form below' ?></p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($svAlreadyDone): ?>

                    <?php
                    $latestPhotos = array_values(array_filter(explode(',', $latestVisit['photos'] ?? '')));
                    $latestMeasFiles = array_values(array_filter(explode(',', $latestVisit['measurement_files'] ?? '')));
                    ?>

                    <!-- Next step -->
                    <div class="rounded-xl border px-4 py-3.5 mb-4 flex flex-wrap items-center gap-3 <?= $latestVisited ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
                        <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid <?= $latestVisited ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold"><?= $latestVisited ? 'Ready to proceed' : 'Site not yet visited' ?></p>
                            <p class="text-xs opacity-80"><?= $latestVisited
                                ? 'The site visit is recorded. You can now prepare the 2D layout and quotation.'
                                : 'Schedule a revisit with the client so this inquiry can proceed.' ?></p>
                        </div>
                        <?php if ($latestVisited && $inquiry['status'] === 'In Progress'): ?>
                            <a href="<?= htmlspecialchars($crm2dQuotationUrl) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg px-3.5 py-2 shrink-0">
                                Go to 2D &amp; Quotation <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="grid lg:grid-cols-3 gap-4">

                        <!-- Left column -->
                        <div class="lg:col-span-2 space-y-4 min-w-0">

                            <!-- Visit details -->
                            <section class="sv-card overflow-hidden">
                                <p class="px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-clipboard-check mr-1.5 text-amber-700"></i>Visit details</p>
                                <dl class="grid sm:grid-cols-2 gap-px bg-gray-100">
                                    <div class="bg-white px-5 py-3">
                                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Date &amp; time of visit</dt>
                                        <dd class="text-[13px] font-semibold text-gray-900 mt-0.5"><?= htmlspecialchars(date('F d, Y · g:i A', strtotime($latestVisit['visit_datetime']))) ?></dd>
                                    </div>
                                    <div class="bg-white px-5 py-3">
                                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Visited</dt>
                                        <dd class="mt-1">
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $latestVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                                <i class="fa-solid <?= $latestVisited ? 'fa-check' : 'fa-xmark' ?>"></i><?= $latestVisited ? 'Yes — proceed to 2D & Quotation' : 'No — not visited' ?>
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="bg-white px-5 py-3 sm:col-span-2">
                                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Address visited</dt>
                                        <dd class="text-[13px] text-gray-900 mt-0.5 break-words"><?= htmlspecialchars($latestVisit['address']) ?></dd>
                                    </div>
                                    <div class="bg-white px-5 py-3 sm:col-span-2">
                                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Logged on</dt>
                                        <dd class="text-[12.5px] text-gray-500 mt-0.5"><?= htmlspecialchars(date('F d, Y · g:i A', strtotime($latestVisit['created_at']))) ?></dd>
                                    </div>
                                </dl>
                            </section>

                            <!-- Site notes -->
                            <section class="sv-card overflow-hidden">
                                <p class="px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-note-sticky mr-1.5 text-amber-700"></i>Site notes</p>
                                <div class="p-5 grid sm:grid-cols-3 gap-4">
                                    <?php foreach ([
                                        ['fa-mountain-sun', 'Site conditions', $latestVisit['site_conditions']],
                                        ['fa-user-pen', 'Client requirements', $latestVisit['client_requirements']],
                                        ['fa-house-chimney-crack', 'Existing structure', $latestVisit['existing_structure']],
                                    ] as [$ic, $lbl, $val]): ?>
                                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-3.5 min-w-0">
                                            <p class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5"><i class="fa-solid <?= $ic ?> mr-1 text-gray-400"></i><?= $lbl ?></p>
                                            <div class="break-words"><?= svNotes($val) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <!-- Measurements -->
                            <section class="sv-card overflow-hidden">
                                <p class="px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-ruler mr-1.5 text-amber-700"></i>Measurements</p>
                                <div class="p-5">
                                    <?php if (!empty($latestMeasFiles)): ?>
                                        <div class="grid sm:grid-cols-2 gap-2">
                                            <?php foreach ($latestMeasFiles as $mi => $mPath): ?>
                                                <a href="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" target="_blank" rel="noopener"
                                                    class="flex items-center gap-3 rounded-xl border border-gray-200 px-3.5 py-2.5 hover:border-amber-600 hover:bg-amber-50/40 transition-colors min-w-0">
                                                    <span class="w-9 h-9 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-pdf"></i></span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-[13px] font-semibold text-gray-900">Measurement PDF <?= $mi + 1 ?></span>
                                                        <span class="block text-[11px] text-gray-400 truncate"><?= htmlspecialchars(basename($mPath)) ?></span>
                                                    </span>
                                                    <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-400"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($latestVisit['measurements'])): ?>
                                        <?= svNotes($latestVisit['measurements']) ?>
                                    <?php else: ?>
                                        <p class="text-[13px] text-gray-400 italic">No measurement files were uploaded.</p>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <!-- Prior entries -->
                            <?php if (count($svHistory) > 1): ?>
                                <section class="sv-card overflow-hidden">
                                    <p class="px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-clock-rotate-left mr-1.5 text-amber-700"></i>Prior entries</p>
                                    <ul class="divide-y divide-gray-100">
                                        <?php foreach (array_slice($svHistory, 1) as $entry): ?>
                                            <?php
                                            $entryVisited = $entry['visited'] === 'yes';
                                            $entryPhotos = array_filter(explode(',', $entry['photos'] ?? ''));
                                            ?>
                                            <li class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px]">
                                                <span class="font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($entry['visit_datetime']))) ?></span>
                                                <span class="text-gray-500 min-w-0 flex-1 truncate"><?= htmlspecialchars($entry['address']) ?></span>
                                                <span class="text-gray-400 whitespace-nowrap"><i class="fa-regular fa-image mr-1"></i><?= count($entryPhotos) ?></span>
                                                <span class="inline-flex items-center text-[11px] font-semibold px-2.5 py-0.5 rounded-full <?= $entryVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                                    <?= $entryVisited ? 'Visited' : 'Not visited' ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endif; ?>

                        </div>

                        <!-- Right column -->
                        <div class="space-y-4 min-w-0">
                            <section class="sv-card overflow-hidden">
                                <p class="px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                    <i class="fa-solid fa-camera mr-1.5 text-amber-700"></i>Photographs
                                    <?php if (!empty($latestPhotos)): ?><span class="text-gray-400 font-semibold">(<?= count($latestPhotos) ?>)</span><?php endif; ?>
                                </p>
                                <div class="p-4">
                                    <?php if (empty($latestPhotos)): ?>
                                        <div class="rounded-xl border-2 border-dashed border-gray-200 px-4 py-8 text-center">
                                            <i class="fa-regular fa-image text-xl text-gray-300"></i>
                                            <p class="text-[12.5px] text-gray-400 mt-1">No photographs were submitted for this visit.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-3 gap-2">
                                            <?php foreach ($latestPhotos as $pi => $photoPath): ?>
                                                <button type="button" data-sv-photo="<?= $pi ?>"
                                                    class="block aspect-square rounded-lg overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600 transition">
                                                    <img src="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>" alt="Site photo <?= $pi + 1 ?>" loading="lazy"
                                                        class="w-full h-full object-cover">
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>

                    </div>

                    <!-- Photo lightbox -->
                    <?php if (!empty($latestPhotos)): ?>
                        <div id="svLightbox" class="fixed inset-0 z-[9998] hidden bg-black/80 flex-col items-center justify-center p-4" role="dialog" aria-modal="true">
                            <div class="w-full max-w-4xl flex items-center justify-between text-white mb-3">
                                <span id="svLbCount" class="text-xs font-semibold opacity-80"></span>
                                <div class="flex items-center gap-1">
                                    <a id="svLbOpen" href="#" target="_blank" rel="noopener" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10" aria-label="Open original"><i class="fa-solid fa-up-right-from-square text-sm"></i></a>
                                    <button type="button" id="svLbClose" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                                </div>
                            </div>
                            <div class="relative w-full max-w-4xl flex items-center justify-center">
                                <button type="button" id="svLbPrev" class="absolute left-0 w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
                                <img id="svLbImg" src="" alt="" class="max-w-full rounded-lg object-contain">
                                <button type="button" id="svLbNext" class="absolute right-0 w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <script>
                            window.SV_PHOTOS = <?= json_encode(array_map(fn($p) => BASE_URL . '/' . $p, $latestPhotos)) ?>;
                        </script>
                    <?php endif; ?>

                <?php else: ?>

                    <!-- ═══ NEW RECORD FORM ═══ -->
                    <form id="svForm" method="POST" action="" enctype="multipart/form-data" novalidate
                        class="grid lg:grid-cols-3 gap-4 items-start">

                        <input type="hidden" name="inquiry_id" value="<?= (int) $inquiry['id'] ?>">
                        <input type="hidden" name="submit_sitevisit" value="1">

                        <div class="lg:col-span-2 space-y-4 min-w-0">

                            <?php if (!empty($svFormError)): ?>
                                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 flex items-start gap-2.5">
                                    <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5"></i>
                                    <p class="text-[13px] text-red-800"><?= htmlspecialchars($svFormError) ?> <span class="text-red-600">Please re-attach your files.</span></p>
                                </div>
                            <?php endif; ?>

                            <!-- 1. Visit -->
                            <section class="sv-card p-5">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">1</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Visit details</h2>
                                        <p class="text-[11px] text-gray-400">When did you go, and did the visit happen?</p>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="sv-label" for="sv_datetime">Date &amp; time of visit <span class="text-red-500">*</span></label>
                                        <input id="sv_datetime" type="datetime-local" name="visit_datetime" required
                                            value="<?= $svOldVal('visit_datetime') ?>" class="sv-field h-[40px]">
                                        <p class="sv-err hidden text-[11px] text-red-600 mt-1">Enter the date and time of the visit.</p>
                                    </div>
                                    <div>
                                        <label class="sv-label" for="sv_deadline">Initial deadline for 2D &amp; Quotation</label>
                                        <input id="sv_deadline" type="date" name="deadline" value="<?= $svOldVal('deadline') ?>"
                                            class="sv-field h-[40px]" min="<?= htmlspecialchars(date('Y-m-d')) ?>">
                                        <p id="sv_deadline_hint" class="text-[11px] text-gray-400 mt-1">Optional — when you expect to finish the 2D &amp; quotation.</p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <p class="sv-label">Did the site visit happen? <span class="text-red-500">*</span></p>
                                    <div id="sv_visited_group" class="grid sm:grid-cols-2 gap-2">
                                        <label class="sv-choice cursor-pointer">
                                            <input type="radio" name="visited" value="yes" class="sr-only" <?= ($svOld['visited'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                            <span class="sv-choice-box"><i class="fa-solid fa-circle-check text-emerald-600"></i><span><b class="font-semibold">Yes, visited</b><span class="block text-[11px] opacity-75 font-normal">Proceed to 2D &amp; Quotation</span></span></span>
                                        </label>
                                        <label class="sv-choice cursor-pointer">
                                            <input type="radio" name="visited" value="no" class="sr-only" <?= ($svOld['visited'] ?? '') === 'no' ? 'checked' : '' ?>>
                                            <span class="sv-choice-box"><i class="fa-solid fa-circle-xmark text-amber-600"></i><span><b class="font-semibold">No, not visited</b><span class="block text-[11px] opacity-75 font-normal">Client unavailable, rescheduled, etc.</span></span></span>
                                        </label>
                                    </div>
                                    <p class="sv-err hidden text-[11px] text-red-600 mt-1">Choose Yes or No.</p>
                                </div>

                                <div class="mt-4">
                                    <label class="sv-label">Address</label>
                                    <input type="text" name="visit_address" readonly value="<?= htmlspecialchars($inquiry['address']) ?>" class="sv-field h-[40px]">
                                    <p class="text-[11px] text-gray-400 mt-1"><i class="fa-solid fa-lock mr-1"></i>Taken from the inquiry.</p>
                                </div>
                            </section>

                            <!-- 2. Notes -->
                            <section class="sv-card p-5">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">2</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Site notes</h2>
                                        <p class="text-[11px] text-gray-400">What you saw and what the client asked for.</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <?php foreach ([
                                        ['site_conditions', 'Site conditions / notes', 'Terrain, access, utilities, hazards...'],
                                        ['client_requirements', 'Client requirements', 'Preferences, budget notes, must-haves...'],
                                        ['existing_structure', 'Existing structure', 'Age, condition, materials, structures on site...'],
                                    ] as [$nm, $lbl, $ph]): ?>
                                        <div>
                                            <label class="sv-label" for="sv_<?= $nm ?>"><?= $lbl ?></label>
                                            <textarea id="sv_<?= $nm ?>" name="<?= $nm ?>" rows="5" class="sv-field resize-y min-h-[110px]" placeholder="<?= htmlspecialchars($ph) ?>"><?= $svOldVal($nm) ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <!-- 3. Files -->
                            <section class="sv-card p-5">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">3</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Measurements &amp; photos</h2>
                                        <p class="text-[11px] text-gray-400">Click or drag files into the boxes. You can add more than once.</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div class="min-w-0">
                                        <p class="sv-label">Measurements (PDF)</p>
                                        <label for="sv_meas_pdfs" id="sv_meas_drop" class="sv-drop">
                                            <span class="w-9 h-9 rounded-lg bg-red-50 text-red-500 flex items-center justify-center"><i class="fa-solid fa-file-pdf"></i></span>
                                            <span class="text-[12.5px] font-semibold text-gray-700">Upload measurement PDF(s)</span>
                                            <span class="text-[11px] text-gray-400">PDF only · max 15MB each</span>
                                        </label>
                                        <input id="sv_meas_pdfs" type="file" name="measurement_pdfs[]" accept="application/pdf" multiple class="hidden">
                                        <ul id="sv_meas_list" class="mt-2 space-y-1.5"></ul>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="sv-label">Photographs</p>
                                        <label for="sv_photos" id="sv_photo_drop" class="sv-drop">
                                            <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-camera"></i></span>
                                            <span class="text-[12.5px] font-semibold text-gray-700">Upload site photos</span>
                                            <span class="text-[11px] text-gray-400">JPG, PNG, WEBP</span>
                                        </label>
                                        <input id="sv_photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                                        <div id="sv_photo_preview" class="mt-2 grid grid-cols-4 sm:grid-cols-5 gap-1.5"></div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- Sidebar: checklist + save -->
                        <aside class="space-y-4 lg:sticky lg:top-4 min-w-0">
                            <section class="sv-card overflow-hidden">
                                <div class="sv-hazard h-1"></div>
                                <div class="p-5">
                                    <h2 class="text-sm font-semibold text-gray-900">Record checklist</h2>
                                    <p class="text-[11px] text-gray-400 mb-3">Required items must be done before saving.</p>
                                    <ul id="sv_checklist" class="space-y-2 text-[12.5px]">
                                        <?php foreach ([
                                            ['datetime', 'Date & time of visit', true],
                                            ['visited', 'Visited: Yes or No', true],
                                            ['deadline', '2D & Quotation deadline', false],
                                            ['notes', 'Site notes', false],
                                            ['meas', 'Measurement PDF', false],
                                            ['photos', 'Site photos', false],
                                        ] as [$k, $lbl, $req]): ?>
                                            <li data-check="<?= $k ?>" class="flex items-center gap-2 text-gray-500">
                                                <span class="sv-ck w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-[9px] text-transparent shrink-0"><i class="fa-solid fa-check"></i></span>
                                                <span class="flex-1"><?= htmlspecialchars($lbl) ?></span>
                                                <span class="text-[10px] font-semibold uppercase <?= $req ? 'text-red-500' : 'text-gray-300' ?>"><?= $req ? 'Required' : 'Optional' ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <div id="sv_save_area" class="mt-5">
                                        <button type="button" id="sv_save_btn"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm">
                                            <i class="fa-solid fa-floppy-disk"></i> Save site visit record
                                        </button>
                                    </div>

                                    <!-- Inline confirm -->
                                    <div id="sv_confirm" class="hidden mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3.5">
                                        <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>Save this record?</p>
                                        <p id="sv_confirm_text" class="text-[11.5px] text-amber-800 mt-1"></p>
                                        <p class="text-[11px] text-amber-700 mt-1">The record can't be edited after saving.</p>
                                        <div class="grid grid-cols-2 gap-2 mt-3">
                                            <button type="button" id="sv_confirm_no" class="px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                                            <button type="button" id="sv_confirm_yes" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg">
                                                <i class="fa-solid fa-check"></i> Yes, save
                                            </button>
                                        </div>
                                    </div>

                                    <p id="sv_draft_note" class="hidden text-[11px] text-gray-400 mt-3"><i class="fa-solid fa-cloud mr-1"></i>Draft saved on this device. Files are not saved in drafts.</p>
                                </div>
                            </section>
                        </aside>
                    </form>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <!-- Toast container: bottom-right -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <script>
            function crmShowToast(message, type = 'success', duration = 4000) {
                const container = document.getElementById('crmToastContainer');
                const palette = type === 'success'
                    ? { icon: 'bg-emerald-500', symbol: '✓' }
                    : { icon: 'bg-amber-500', symbol: '!' };

                const toast = document.createElement('div');
                toast.className = `pointer-events-auto flex items-start gap-2.5 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-800
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white ${palette.icon}">${palette.symbol}</span>
            <span class="flex-1 leading-relaxed"></span>
            <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>
        `;
                toast.querySelector('.flex-1').textContent = message;
                container.appendChild(toast);
                requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
                const remove = () => {
                    toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                    setTimeout(() => toast.remove(), 300);
                };
                toast.querySelector('button').addEventListener('click', remove);
                if (duration > 0) setTimeout(remove, duration);
            }

            const SV_INQUIRY_ID = <?= (int) $inquiryId ?>;
            const SV_DRAFT_KEY = 'svDraft_' + SV_INQUIRY_ID;

            <?php if ($svSuccess): ?>
                try { localStorage.removeItem(SV_DRAFT_KEY); } catch (e) { }
                crmShowToast(<?= json_encode($svSuccess) ?>, 'success');
            <?php endif; ?>

            // ═══════════════ PHOTO LIGHTBOX (saved record) ═══════════════
            (function () {
                const lb = document.getElementById('svLightbox');
                if (!lb || !window.SV_PHOTOS) return;
                const img = document.getElementById('svLbImg');
                let idx = 0;
                const show = i => {
                    idx = (i + SV_PHOTOS.length) % SV_PHOTOS.length;
                    img.src = SV_PHOTOS[idx];
                    document.getElementById('svLbOpen').href = SV_PHOTOS[idx];
                    document.getElementById('svLbCount').textContent = `Photo ${idx + 1} of ${SV_PHOTOS.length}`;
                    const multi = SV_PHOTOS.length > 1;
                    document.getElementById('svLbPrev').style.display = multi ? '' : 'none';
                    document.getElementById('svLbNext').style.display = multi ? '' : 'none';
                };
                const open = i => { show(i); lb.classList.remove('hidden'); lb.classList.add('flex'); };
                const close = () => { lb.classList.add('hidden'); lb.classList.remove('flex'); };
                document.querySelectorAll('[data-sv-photo]').forEach(b => b.addEventListener('click', () => open(+b.dataset.svPhoto)));
                document.getElementById('svLbClose').addEventListener('click', close);
                document.getElementById('svLbPrev').addEventListener('click', () => show(idx - 1));
                document.getElementById('svLbNext').addEventListener('click', () => show(idx + 1));
                lb.addEventListener('click', e => { if (e.target === lb) close(); });
                document.addEventListener('keydown', e => {
                    if (lb.classList.contains('hidden')) return;
                    if (e.key === 'Escape') close();
                    if (e.key === 'ArrowLeft') show(idx - 1);
                    if (e.key === 'ArrowRight') show(idx + 1);
                });
            })();

            // ═══════════════ NEW RECORD FORM ═══════════════
            (function () {
                const form = document.getElementById('svForm');
                if (!form) return;

                const fmtSize = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
                const MAX_PDF = 15 * 1024 * 1024;

                // Drag & drop sa label
                function svWireDrop(dropId, onFiles) {
                    const drop = document.getElementById(dropId);
                    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('sv-drag'); }));
                    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('sv-drag'); }));
                    drop.addEventListener('drop', e => onFiles(Array.from(e.dataTransfer.files || [])));
                }

                // ── Photos ──
                const svPhotosInput = document.getElementById('sv_photos');
                const svPhotoDt = new DataTransfer();
                const svPhotoPreview = document.getElementById('sv_photo_preview');
                const svPhotoUrls = new Map();
                const svPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];

                function svRenderPhotoPreview() {
                    svPhotoPreview.innerHTML = '';
                    Array.from(svPhotoDt.files).forEach((file, idx) => {
                        if (!svPhotoUrls.has(file)) svPhotoUrls.set(file, URL.createObjectURL(file));
                        const wrap = document.createElement('div');
                        wrap.className = 'relative';
                        const img = document.createElement('img');
                        img.src = svPhotoUrls.get(file);
                        img.title = file.name;
                        img.className = 'w-full aspect-square object-cover rounded-lg border border-gray-200';
                        wrap.appendChild(img);
                        const rm = document.createElement('button');
                        rm.type = 'button';
                        rm.setAttribute('aria-label', 'Remove photo');
                        rm.className = 'absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-gray-800 text-white flex items-center justify-center hover:bg-red-600 shadow';
                        rm.innerHTML = '<i class="fa-solid fa-xmark text-[10px] leading-none"></i>';
                        rm.addEventListener('click', () => {
                            const removed = svPhotoDt.files[idx];
                            URL.revokeObjectURL(svPhotoUrls.get(removed));
                            svPhotoUrls.delete(removed);
                            svPhotoDt.items.remove(idx);
                            svPhotosInput.files = svPhotoDt.files;
                            svRenderPhotoPreview();
                        });
                        wrap.appendChild(rm);
                        svPhotoPreview.appendChild(wrap);
                    });
                    svUpdateChecklist();
                }
                function svAddPhotos(files) {
                    let skipped = 0;
                    files.forEach(file => {
                        const isDup = Array.from(svPhotoDt.files).some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                        if (!svPhotoTypes.includes(file.type)) { skipped++; return; }
                        if (!isDup) svPhotoDt.items.add(file);
                    });
                    svPhotosInput.files = svPhotoDt.files;
                    svRenderPhotoPreview();
                    if (skipped) crmShowToast(`${skipped} file(s) skipped — only JPG, PNG or WEBP photos are allowed.`, 'warning');
                }
                svPhotosInput.addEventListener('change', function () { svAddPhotos(Array.from(this.files)); });
                svWireDrop('sv_photo_drop', svAddPhotos);

                // ── Measurement PDFs ──
                const svMeasInput = document.getElementById('sv_meas_pdfs');
                const svMeasDt = new DataTransfer();
                const svMeasList = document.getElementById('sv_meas_list');

                function svRenderMeasList() {
                    svMeasList.innerHTML = '';
                    Array.from(svMeasDt.files).forEach((file, idx) => {
                        const li = document.createElement('li');
                        li.className = 'flex items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 min-w-0';
                        li.innerHTML = '<span class="w-7 h-7 rounded-md bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-pdf text-xs"></i></span>';
                        const info = document.createElement('span');
                        info.className = 'min-w-0 flex-1';
                        const nm = document.createElement('span');
                        nm.className = 'block text-[12px] font-medium text-gray-800 truncate';
                        nm.textContent = file.name;
                        const sz = document.createElement('span');
                        sz.className = 'block text-[10.5px] text-gray-400';
                        sz.textContent = fmtSize(file.size);
                        info.append(nm, sz);
                        li.appendChild(info);
                        const rm = document.createElement('button');
                        rm.type = 'button';
                        rm.className = 'inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 shrink-0';
                        rm.setAttribute('aria-label', 'Remove file');
                        rm.innerHTML = '<i class="fa-solid fa-xmark text-sm leading-none"></i>';
                        rm.addEventListener('click', () => {
                            svMeasDt.items.remove(idx);
                            svMeasInput.files = svMeasDt.files;
                            svRenderMeasList();
                        });
                        li.appendChild(rm);
                        svMeasList.appendChild(li);
                    });
                    svUpdateChecklist();
                }
                function svAddPdfs(files) {
                    let notPdf = 0, tooBig = 0;
                    files.forEach(file => {
                        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                        if (!isPdf) { notPdf++; return; }
                        if (file.size > MAX_PDF) { tooBig++; return; }
                        const isDup = Array.from(svMeasDt.files).some(f => f.name === file.name && f.size === file.size);
                        if (!isDup) svMeasDt.items.add(file);
                    });
                    svMeasInput.files = svMeasDt.files;
                    svRenderMeasList();
                    if (notPdf) crmShowToast(`${notPdf} file(s) skipped — measurements must be PDF.`, 'warning');
                    if (tooBig) crmShowToast(`${tooBig} PDF(s) skipped — over 15MB.`, 'warning');
                }
                svMeasInput.addEventListener('change', function () { svAddPdfs(Array.from(this.files)); });
                svWireDrop('sv_meas_drop', svAddPdfs);

                // ── Checklist ──
                const elDt = document.getElementById('sv_datetime');
                const elDeadline = document.getElementById('sv_deadline');
                const notesEls = ['site_conditions', 'client_requirements', 'existing_structure'].map(n => document.getElementById('sv_' + n));
                const visitedVal = () => (form.querySelector('input[name="visited"]:checked') || {}).value || '';

                function svSetCheck(key, ok) {
                    const li = document.querySelector(`#sv_checklist [data-check="${key}"]`);
                    if (!li) return;
                    const ck = li.querySelector('.sv-ck');
                    li.classList.toggle('text-gray-900', ok);
                    li.classList.toggle('text-gray-500', !ok);
                    ck.className = 'sv-ck w-5 h-5 rounded-full flex items-center justify-center text-[9px] shrink-0 ' +
                        (ok ? 'bg-emerald-500 border border-emerald-500 text-white' : 'border border-gray-300 text-transparent');
                }
                function svUpdateChecklist() {
                    svSetCheck('datetime', !!elDt.value);
                    svSetCheck('visited', !!visitedVal());
                    svSetCheck('deadline', !!elDeadline.value);
                    svSetCheck('notes', notesEls.some(t => t.value.trim() !== ''));
                    svSetCheck('meas', svMeasDt.files.length > 0);
                    svSetCheck('photos', svPhotoDt.files.length > 0);
                }

                // Deadline hint (ilang araw pa)
                function svDeadlineHint() {
                    const hint = document.getElementById('sv_deadline_hint');
                    if (!elDeadline.value) { hint.textContent = 'Optional — when you expect to finish the 2D & quotation.'; hint.className = 'text-[11px] text-gray-400 mt-1'; return; }
                    const a = new Date(); a.setHours(0, 0, 0, 0);
                    const b = new Date(elDeadline.value + 'T00:00:00');
                    const d = Math.round((b - a) / 864e5);
                    hint.textContent = d === 0 ? 'Due today' : d === 1 ? 'Due tomorrow' : d > 1 ? `${d} days from today` : 'This date has passed';
                    hint.className = 'text-[11px] mt-1 font-semibold ' + (d < 0 ? 'text-red-600' : d <= 3 ? 'text-amber-700' : 'text-gray-500');
                }

                // ── Draft (text fields only) ──
                const draftFields = ['visit_datetime', 'deadline', 'site_conditions', 'client_requirements', 'existing_structure'];
                const hasServerOld = <?= !empty($svOld) ? 'true' : 'false' ?>;
                let draftTimer = null;
                function svSaveDraft() {
                    clearTimeout(draftTimer);
                    draftTimer = setTimeout(() => {
                        const data = { visited: visitedVal() };
                        draftFields.forEach(n => data[n] = form.elements[n].value);
                        const empty = Object.values(data).every(v => !v);
                        try {
                            if (empty) localStorage.removeItem(SV_DRAFT_KEY);
                            else localStorage.setItem(SV_DRAFT_KEY, JSON.stringify(data));
                            document.getElementById('sv_draft_note').classList.toggle('hidden', empty);
                        } catch (e) { }
                    }, 400);
                }
                function svLoadDraft() {
                    if (hasServerOld) return; // galing sa server (may error) ang laman — mas bago iyon
                    let data = null;
                    try { data = JSON.parse(localStorage.getItem(SV_DRAFT_KEY) || 'null'); } catch (e) { }
                    if (!data) return;
                    draftFields.forEach(n => { if (data[n]) form.elements[n].value = data[n]; });
                    if (data.visited) {
                        const r = form.querySelector(`input[name="visited"][value="${data.visited}"]`);
                        if (r) r.checked = true;
                    }
                    if (elDeadline.value && elDeadline.value < elDeadline.min) elDeadline.value = '';
                    document.getElementById('sv_draft_note').classList.remove('hidden');
                    crmShowToast('Your unsaved draft was restored. Re-attach files if you had any.', 'success', 5000);
                }

                // ── Validation ──
                function svClearErr(el) {
                    const wrap = el.closest('div');
                    el.classList.remove('sv-invalid');
                    const err = wrap && wrap.querySelector('.sv-err'); if (err) err.classList.add('hidden');
                }
                function svValidate() {
                    let ok = true, first = null;
                    if (!elDt.value) {
                        ok = false; first = first || elDt;
                        elDt.classList.add('sv-invalid');
                        elDt.parentElement.querySelector('.sv-err').classList.remove('hidden');
                    }
                    if (!visitedVal()) {
                        ok = false; first = first || document.getElementById('sv_visited_group');
                        document.querySelectorAll('.sv-choice').forEach(c => c.classList.add('sv-invalid'));
                        document.getElementById('sv_visited_group').parentElement.querySelector('.sv-err').classList.remove('hidden');
                    }
                    if (elDeadline.value && elDeadline.value < elDeadline.min) {
                        ok = false; first = first || elDeadline;
                        elDeadline.classList.add('sv-invalid');
                    }
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return ok;
                }

                form.addEventListener('input', e => {
                    if (e.target.classList) svClearErr(e.target);
                    if (e.target === elDeadline) svDeadlineHint();
                    svUpdateChecklist(); svSaveDraft();
                });
                form.addEventListener('change', e => {
                    if (e.target.name === 'visited') {
                        document.querySelectorAll('.sv-choice').forEach(c => c.classList.remove('sv-invalid'));
                        document.getElementById('sv_visited_group').parentElement.querySelector('.sv-err').classList.add('hidden');
                    }
                    if (e.target === elDeadline) { elDeadline.classList.remove('sv-invalid'); svDeadlineHint(); }
                    svUpdateChecklist(); svSaveDraft();
                });
                form.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); // walang aksidenteng submit
                });

                // ── Save → inline confirm → submit ──
                const saveArea = document.getElementById('sv_save_area');
                const confirmBox = document.getElementById('sv_confirm');
                document.getElementById('sv_save_btn').addEventListener('click', () => {
                    if (!svValidate()) { crmShowToast('Please complete the required fields.', 'warning'); return; }
                    const when = new Date(elDt.value).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                    const v = visitedVal() === 'yes' ? 'Visited' : 'Not visited';
                    const files = `${svMeasDt.files.length} PDF · ${svPhotoDt.files.length} photo${svPhotoDt.files.length === 1 ? '' : 's'}`;
                    document.getElementById('sv_confirm_text').textContent = `${v} on ${when} — ${files}.`;
                    saveArea.classList.add('hidden');
                    confirmBox.classList.remove('hidden');
                });
                document.getElementById('sv_confirm_no').addEventListener('click', () => {
                    confirmBox.classList.add('hidden');
                    saveArea.classList.remove('hidden');
                });
                document.getElementById('sv_confirm_yes').addEventListener('click', function () {
                    this.disabled = true;
                    document.getElementById('sv_confirm_no').disabled = true;
                    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
                    form.submit();
                });

                svLoadDraft();
                svDeadlineHint();
                svUpdateChecklist();
            })();
        </script>

    </main>
</body>

</html>