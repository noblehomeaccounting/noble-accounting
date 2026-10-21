<?php
// crm-main.php

$formError = '';
$formSuccess = '';
$generatedControlNo = '';


if (!empty($_SESSION['crm_flash_success'])) {
    $formSuccess = $_SESSION['crm_flash_success'];
    $generatedControlNo = $_SESSION['crm_flash_control_no'] ?? '';
    unset($_SESSION['crm_flash_success'], $_SESSION['crm_flash_control_no']);
}

// --- Auto-assigned sales staff (currently logged-in user) ---
$currentSalesName = $_SESSION['username'] ?? '';
$currentSalesId = intval($_SESSION['account_id'] ?? 0);

function fetchCrmOptions($conn, $table)
{
    $items = [];
    $result = $conn->query("SELECT id, label FROM {$table} ORDER BY label ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

$measuringSpaceOptions = fetchCrmOptions($conn, 'noblecrm_measuring_space');
$projectScopeOptions = fetchCrmOptions($conn, 'noblecrm_project_scope');

function getBranchId($conn, $branchName)
{
    $id = null;
    $stmt = $conn->prepare("SELECT id FROM noblebranch WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $branchName);
    $stmt->execute();
    $stmt->bind_result($id);
    $branchId = $stmt->fetch() ? $id : 0;
    $stmt->close();
    return $branchId;
}


function generateCrmControlNo($conn, $branchId)
{
    $year = date('Y');
    $day = date('d');
    $month = date('m');
    $prefix = "{$branchId}{$year}{$day}{$month}";

    // Hanapin ang PINAKA-HULING inquiry ng branch na ito — WALANG date filter,
    // kaya kahit ibang araw/buwan na, makikita pa rin ang huling counter
    $stmt = $conn->prepare("
        SELECT control_no FROM noblecrminquiry
        WHERE control_no LIKE CONCAT(?, '%')
        ORDER BY id DESC LIMIT 1
    ");
    $branchPrefix = (string) $branchId;
    $stmt->bind_param("s", $branchPrefix);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $lastControlNo = $row['control_no'] ?? null;

    $nextNumber = 1;
    if ($lastControlNo) {
        // laging kunin yung HULING 5 digits — yun lang ang counter, kahit anong petsa nasa gitna
        $lastNumberPart = (int) substr($lastControlNo, -5);
        $nextNumber = $lastNumberPart + 1;
    }
    $stmt->close();

    return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
}

// --- Handle submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
    $clientName = trim($_POST['client_name'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $projectType = trim($_POST['project_type'] ?? '');

    // Project Nature: 'modular' (Site Visit Needed) o 'material' (Ready for Quotation).
    // Walang default — required field, kailangan piliin ng user.
    $projectNature = trim($_POST['project_nature'] ?? '');
    if (!in_array($projectNature, ['modular', 'material'], true)) {
        $projectNature = '';
    }

    $designerId = 0;
    $measurementDatetime = null;

    $targetCompletionDate = null; // Ise-set na lang mamaya sa Sales & Market List (Schedule & Assign), kasabay ng measurement datetime

    // Mode ay HINDI galing sa POST ng user — laging derived mula sa Project Nature,
    // para hindi ma-bypass kahit galawin ng user yung DOM/devtools.
    $inquiryMode = ($projectNature === 'material') ? 'ready_for_quotation' : 'site_visit';

    $initialStatus = ($inquiryMode === 'ready_for_quotation') ? 'In Progress' : 'Pending';

    // Address parts mula sa PSGC-driven region/province/city/barangay + house/street
    $houseStreet = trim($_POST['house_street'] ?? '');
    $regionName = trim($_POST['region_name'] ?? '');
    $provinceName = trim($_POST['province_name'] ?? '');
    $cityName = trim($_POST['city_name'] ?? '');
    $barangayName = trim($_POST['barangay_name'] ?? '');

    $addressParts = array_filter([
        $houseStreet,
        $barangayName !== '' ? 'Brgy. ' . $barangayName : '',
        $cityName,
        $provinceName,
        $regionName,
    ]);
    $address = implode(', ', $addressParts);

    // Measuring Space at Scope of Project ay mula na sa checkboxes (array), i-combine as comma-separated
    $measuringSpaceSelected = $_POST['measuring_space'] ?? [];
    $measuringSpace = is_array($measuringSpaceSelected)
        ? implode(', ', array_map('trim', $measuringSpaceSelected))
        : trim($measuringSpaceSelected);

    $projectScopeSelected = $_POST['project_scope'] ?? [];
    $projectScope = is_array($projectScopeSelected)
        ? implode(', ', array_map('trim', $projectScopeSelected))
        : trim($projectScopeSelected);

    if (
        empty($clientName) || empty($houseStreet) || empty($cityName) || empty($barangayName)
        || empty($contactNumber) || empty($projectNature)
    ) {
        $formError = 'Please fill out all required fields.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contactNumber)) {
        $formError = 'Contact Number must be exactly 11 digits.';
    } else {
        $branch = $_SESSION['branch'] ?? '';
        $branchId = getBranchId($conn, $branch);
        $controlNo = generateCrmControlNo($conn, $branchId);

        $stmt = $conn->prepare("
            INSERT INTO noblecrminquiry
                (control_no, client_name, address, project_type, project_nature, project_scope, measuring_space,
                 measurement_datetime, target_completion_date, contact_number, sales_staff_id, designer_id,
                 branch, mode, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "ssssssssssiisss",
            $controlNo,
            $clientName,
            $address,
            $projectType,
            $projectNature,
            $projectScope,
            $measuringSpace,
            $measurementDatetime,
            $targetCompletionDate,
            $contactNumber,
            $currentSalesId,
            $designerId,
            $branch,
            $inquiryMode,
            $initialStatus
        );

        if ($stmt->execute()) {
            $inquiryId = $stmt->insert_id;
            $stmt->close();


            $_SESSION['crm_flash_success'] = 'Inquiry submitted successfully.';
            $_SESSION['crm_flash_control_no'] = $controlNo;

            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $formError = 'Something went wrong. Please try again.';
            $stmt->close();
        }
    }
}

// Ginagamit sa pag-check ng mga naka-checked na checkbox pagkatapos ng failed submit
$postedMeasuringSpace = (array) ($_POST['measuring_space'] ?? []);
$postedProjectScope = (array) ($_POST['project_scope'] ?? []);
$postedMode = $_POST['inquiry_mode'] ?? '';
$postedNature = $_POST['project_nature'] ?? '';

// Folder kung saan nakatago ang bawat step's markup (palitan kung iba ang gusto mong path)
$crmStepsPath = ROOT_PATH . '/admin/crm-management/page-1';

?>


<style>
    .crm-doc .crm-step-panel { display: none; animation: crmFadeSlide .3s ease forwards; }
    .crm-doc .crm-step-panel.active { display: block; }
    @keyframes crmFadeSlide { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    /* step header */
    .crm-doc .crm-step-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .crm-doc .crm-step-icon { width: 40px; height: 40px; border-radius: 12px; background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; display: flex; align-items: center; justify-content: center; font-size: 15px; flex: none; }
    .crm-doc .crm-step-title { font-size: 17px; font-weight: 700; color: #111827; line-height: 1.2; }
    .crm-doc .crm-step-sub { font-size: 12px; color: #9ca3af; margin-top: 2px; }

    /* sections & fields */
    .crm-doc .crm-section { background: rgba(255, 255, 255, .55); border: 1px solid #EEE8D9; border-radius: 14px; padding: 16px; margin-bottom: 14px; }
    .crm-doc .crm-section-label { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #a8a29e; margin-bottom: 12px; }
    .crm-doc .crm-label { display: block; font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 6px; }
    .crm-doc .crm-field { position: relative; }
    .crm-doc .crm-field-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #c4b8a0; pointer-events: none; }
    .crm-doc .crm-input { width: 100%; height: 42px; padding: 0 12px; font-size: 14px; color: #1f2937; background: #fff; border: 1px solid #DCD5C3; border-radius: 10px; outline: none; transition: border-color .15s, box-shadow .15s; }
    .crm-doc .crm-input.pl-9 { padding-left: 34px; }
    .crm-doc .crm-input.pr-12 { padding-right: 48px; }
    .crm-doc .crm-input::placeholder { color: #c7c2b6; }
    .crm-doc .crm-input:focus { border-color: #b45309; box-shadow: 0 0 0 3px rgba(180, 83, 9, .12); }
    .crm-doc .crm-input:disabled { background: #f5f3ee; color: #a8a29e; cursor: not-allowed; }
    .crm-doc .crm-select { appearance: none; padding-right: 34px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23a8a29e'%3E%3Cpath d='M5.5 7.5 10 12l4.5-4.5'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 18px; }
    .crm-doc .crm-invalid { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, .12) !important; }
    .crm-doc .crm-error { display: none; margin-top: 6px; font-size: 11px; color: #dc2626; }
    .crm-doc .crm-error.show { display: block; }

    /* choice cards (Project Nature) */
    .crm-doc .crm-choice { display: flex; align-items: center; gap: 12px; padding: 14px; background: #fff; border: 1.5px solid #E4DFD1; border-radius: 12px; transition: all .15s; }
    .crm-doc label:hover > .crm-choice { border-color: #d6b98c; }
    .crm-doc .crm-choice-icon { width: 38px; height: 38px; border-radius: 10px; background: #f5f3ee; color: #78716c; display: flex; align-items: center; justify-content: center; flex: none; transition: all .15s; }
    .crm-doc .crm-choice-check { width: 20px; height: 20px; border-radius: 999px; border: 1.5px solid #d6d3d1; color: transparent; font-size: 10px; display: flex; align-items: center; justify-content: center; flex: none; transition: all .15s; }
    .crm-doc .peer:checked + .crm-choice { border-color: #b45309; background: #fffbf5; box-shadow: 0 0 0 3px rgba(180, 83, 9, .1); }
    .crm-doc .peer:checked + .crm-choice .crm-choice-icon { background: #b45309; color: #fff; }
    .crm-doc .peer:checked + .crm-choice .crm-choice-check { background: #b45309; border-color: #b45309; color: #fff; }
    .crm-doc .peer:focus-visible + .crm-choice, .crm-doc .peer:focus-visible + .crm-chip { outline: 2px solid #b45309; outline-offset: 2px; }

    /* mode chips (naka-lock) */
    .crm-doc .crm-mode-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; color: #a8a29e; background: #f5f3ee; border: 1px solid #E4DFD1; }
    .crm-doc .peer:checked + .crm-mode-chip { color: #fff; background: #1f2937; border-color: #1f2937; }

    /* option chips (Scope) */
    .crm-doc .crm-chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 12.5px; color: #44403c; background: #fff; border: 1px solid #DCD5C3; transition: all .15s; user-select: none; }
    .crm-doc label:hover > .crm-chip { border-color: #d6b98c; }
    .crm-doc .crm-chip-tick { display: none; font-size: 10px; }
    .crm-doc .peer:checked + .crm-chip { color: #fff; background: #b45309; border-color: #b45309; }
    .crm-doc .peer:checked + .crm-chip .crm-chip-tick { display: inline; }
    .crm-doc .crm-gear { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: #a8a29e; padding: 3px 8px; border-radius: 6px; transition: all .15s; }
    .crm-doc .crm-gear:hover { color: #b45309; background: #fff7ed; }

    .crm-doc .crm-note { display: flex; gap: 10px; font-size: 12px; color: #6b7280; background: #F6F2E8; border: 1px dashed #DCD5C3; border-radius: 12px; padding: 12px 14px; line-height: 1.5; }

    /* buttons */
    .crm-doc .crm-actions { display: flex; align-items: center; gap: 8px; margin-top: 24px; padding-top: 18px; border-top: 1px solid #EEE8D9; }
    .crm-doc .crm-btn-primary { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 20px; border-radius: 10px; background: #1f2937; color: #fff; font-size: 13px; font-weight: 600; transition: background .15s; }
    .crm-doc .crm-btn-primary:hover { background: #111827; }
    .crm-doc .crm-btn-ghost { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 16px; border-radius: 10px; color: #6b7280; font-size: 13px; font-weight: 600; border: 1px solid #DCD5C3; background: #fff; transition: all .15s; }
    .crm-doc .crm-btn-ghost:hover { color: #374151; border-color: #a8a29e; }
    .crm-doc .crm-btn-submit { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 22px; border-radius: 10px; background: #b45309; color: #fff; font-size: 13px; font-weight: 700; box-shadow: 0 6px 16px -6px rgba(180, 83, 9, .6); transition: all .15s; }
    .crm-doc .crm-btn-submit:hover:not(:disabled) { background: #92400e; }
    .crm-doc .crm-btn-submit:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; }

    /* stepper */
    .crm-doc .crm-dot { width: 34px; height: 34px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid #d6d3d1; color: #a8a29e; background: #fff; transition: all .3s; position: relative; z-index: 1; }
    .crm-doc .crm-dot.is-current { border-color: #b45309; color: #b45309; background: #fff7ed; box-shadow: 0 0 0 4px rgba(180, 83, 9, .12); }
    .crm-doc .crm-dot.is-done { border-color: #b45309; background: #b45309; color: #fff; }
    .crm-doc .crm-step-label { font-size: 11px; font-weight: 600; color: #a8a29e; margin-top: 6px; white-space: nowrap; transition: color .3s; }
    .crm-doc .crm-step-label.is-on { color: #1f2937; }
    .crm-doc .crm-line { flex: 1; height: 2px; background: #e7e5e4; margin: 0 6px 22px; border-radius: 2px; overflow: hidden; }
    .crm-doc .crm-line > span { display: block; height: 100%; width: 0; background: #b45309; transition: width .35s ease; }
    .crm-doc .crm-line.is-done > span { width: 100%; }

    /* review */
    .crm-doc .crm-review-card { background: #fff; border: 1px solid #E4DFD1; border-radius: 12px; overflow: hidden; }
    .crm-doc .crm-review-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #F6F2E8; border-bottom: 1px solid #EEE8D9; }
    .crm-doc .crm-confirm-row { display: flex; justify-content: space-between; gap: 16px; padding: 10px 14px; border-bottom: 1px solid #F3EEE1; font-size: .8125rem; }
    .crm-doc .crm-confirm-row:last-child { border-bottom: none; }
    .crm-doc .crm-confirm-label { color: #9CA3AF; white-space: nowrap; }
    .crm-doc .crm-confirm-value { color: #111827; font-weight: 500; text-align: right; }
</style>

<!-- Flash data mula sa PHP session, ginagamit ng Tailwind toast script sa ibaba -->
<script>
    const crmToastData = {
        error: <?= json_encode($formError) ?>,
        success: <?= json_encode($formSuccess) ?>,
        controlNo: <?= json_encode($generatedControlNo) ?>
    };
</script>

<div class="crm-doc max-w-3xl mx-auto">

    <!-- Document Card -->
    <div class="bg-[#FBF9F4] rounded-2xl shadow-[0_10px_40px_-18px_rgba(120,80,20,.35)] border border-[#E4DFD1] overflow-hidden">

        <!-- Header -->
        <div class="relative px-6 md:px-8 pt-6 pb-5 bg-gradient-to-br from-white to-[#F6F0E2] border-b border-[#E4DFD1] overflow-hidden">
            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="" aria-hidden="true"
                class="absolute -right-6 -bottom-8 w-40 opacity-[0.06] pointer-events-none select-none">
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-xl bg-white border border-[#E4DFD1] shadow-sm flex items-center justify-center">
                        <img src="<?= BASE_URL ?>/icon/logo.webp" class="h-9 w-9 object-contain" alt="logo">
                    </span>
                    <div>
                        <p class="text-amber-700 text-[10px] font-bold tracking-[0.18em] uppercase mb-0.5">CRM · Inquiry Form</p>
                        <h1 class="crm-serif text-gray-900 text-2xl leading-tight font-semibold">New Client Inquiry</h1>
                    </div>
                </div>
                <div class="text-right shrink-0 rounded-xl bg-white/80 border border-[#E4DFD1] px-3.5 py-2">
                    <p class="text-[9px] text-gray-400 uppercase tracking-[0.16em]">Reference No.</p>
                    <p class="text-gray-900 font-mono font-semibold text-sm">
                        <?= !empty($generatedControlNo)
                            ? htmlspecialchars($generatedControlNo)
                            : '<span class="text-gray-400">Assigned on submit</span>' ?>
                    </p>
                </div>
            </div>

            <!-- meta -->
            <div class="relative mt-4 flex flex-wrap items-center gap-2 text-[11px]">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[#E4DFD1] text-gray-500">
                    <i class="fa-regular fa-calendar text-amber-700"></i> <?= date('F d, Y') ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[#E4DFD1] text-gray-500">
                    <i class="fa-solid fa-code-branch text-amber-700"></i> <?= htmlspecialchars($_SESSION['branch'] ?? '—') ?>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-[#E4DFD1] text-gray-500">
                    <i class="fa-regular fa-user text-amber-700"></i> <?= htmlspecialchars($currentSalesName) ?>
                </span>
                <span id="crm_draft_tag" class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-50 border border-green-200 text-green-700">
                    <i class="fa-solid fa-cloud"></i> Draft saved
                </span>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="flex items-start px-6 md:px-10 pt-6 pb-2">
            <?php
            $crmSteps = ['Client Info', 'Project Details', 'Assignment', 'Review'];
            foreach ($crmSteps as $i => $label):
                $n = $i + 1;
                ?>
                <div class="flex flex-col items-center">
                    <div id="crm-dot-<?= $n ?>" class="crm-dot <?= $n === 1 ? 'is-current' : '' ?>"><?= $n ?></div>
                    <span id="crm-label-<?= $n ?>" class="crm-step-label <?= $n === 1 ? 'is-on' : '' ?>"><?= $label ?></span>
                </div>
                <?php if ($n < count($crmSteps)): ?>
                    <div id="crm-line-<?= $n ?>" class="crm-line mt-4"><span></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Form Body -->
        <form method="POST" action="" id="crmInquiryForm" class="px-6 md:px-8 pt-4 pb-7" novalidate>

            <?php
            include $crmStepsPath . '/step1.php';
            include $crmStepsPath . '/step2.php';
            include $crmStepsPath . '/step3.php';
            include $crmStepsPath . '/step4.php';
            ?>

        </form>

        <!-- Document footer strip -->
        <div class="px-6 md:px-8 py-3 bg-[#F3EEE1] border-t border-[#E4DFD1] flex items-center justify-between gap-3">
            <p class="text-[11px] text-[#B0664A]"><i class="fa-solid fa-shield-halved mr-1"></i>This record is maintained by the Sales Department.</p>
            <p class="text-[11px] text-gray-400">NobleHome Construction Corporation</p>
        </div>
    </div>
</div>

<!-- Reusable Modal para sa Manage Options (CRUD) ng Measuring Space / Scope of Project -->
<div id="optionsModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-gear text-xs"></i></span>
                <h3 id="optionsModalTitle" class="text-sm font-semibold text-gray-800">Manage Options</h3>
            </div>
            <button type="button" onclick="closeOptionsModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex gap-2">
                <input id="optionsNewLabel" type="text" placeholder="Add new option"
                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); addOption(); }"
                    class="flex-1 h-10 px-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-600/10">
                <button type="button" onclick="addOption()"
                    class="h-10 px-4 text-sm font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>Add
                </button>
            </div>
            <ul id="optionsList" class="space-y-2 max-h-64 overflow-y-auto"></ul>
        </div>
    </div>
</div>

<!-- Toast container: bottom-right, Tailwind-only, walang custom CSS -->
<div id="crmToastContainer"
    class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
</div>

<script>
    // ═══════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS (bottom-right, Tailwind-only)
    // ═══════════════════════════════════════════════════════════
    function crmShowToast(message, type = 'success', duration = 5000) {
        const container = document.getElementById('crmToastContainer');

        const palette = type === 'success'
            ? {
                wrap: 'bg-green-50 border-green-200 text-green-700',
                icon: 'bg-green-200 text-green-700',
                symbol: '✓'
            }
            : {
                wrap: 'bg-red-50 border-red-200 text-red-700',
                icon: 'bg-red-200 text-red-700',
                symbol: '!'
            };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm
            ${palette.wrap}
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;

        toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">
                ${palette.symbol}
            </span>
            <span class="flex-1 leading-relaxed">${message}</span>
            <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">
                &times;
            </button>
        `;

        container.appendChild(toast);

        // Trigger entrance animation sa susunod na frame
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95');
        });

        const remove = () => {
            toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
            setTimeout(() => toast.remove(), 300);
        };

        toast.querySelector('button').addEventListener('click', remove);
        if (duration > 0) setTimeout(remove, duration);
    }

    // ═══════════════════════════════════════════════════════════
    // STEP NAVIGATION
    // ═══════════════════════════════════════════════════════════
    let crmCurrentStep = 1;
    const CRM_TOTAL_STEPS = 4;

    // ── inline na error (walang alert) ──
    function crmClearErrors() {
        document.querySelectorAll('#crmInquiryForm .crm-invalid').forEach(el => el.classList.remove('crm-invalid'));
        document.querySelectorAll('#crmInquiryForm .crm-error').forEach(p => { p.classList.remove('show'); p.textContent = ''; });
    }
    function crmFieldError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.classList.add('crm-invalid'); el.focus({ preventScroll: true }); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        const p = document.querySelector(`#crmInquiryForm [data-error-for="${id}"]`);
        if (p) { p.textContent = msg; p.classList.add('show'); }
        crmShowToast(msg, 'error', 3500);
    }
    // tanggalin ang pula kapag inayos na ng user
    document.getElementById('crmInquiryForm').addEventListener('input', e => {
        if (!e.target.classList.contains('crm-invalid')) return;
        e.target.classList.remove('crm-invalid');
        const p = document.querySelector(`#crmInquiryForm [data-error-for="${e.target.id}"]`);
        if (p) p.classList.remove('show');
    });
    document.getElementById('crmInquiryForm').addEventListener('change', e => {
        if (e.target.name === 'project_nature') {
            const p = document.querySelector('#crmInquiryForm [data-error-for="project_nature"]');
            if (p) p.classList.remove('show');
        }
        if (e.target.classList.contains('crm-invalid')) {
            e.target.classList.remove('crm-invalid');
            const p = document.querySelector(`#crmInquiryForm [data-error-for="${e.target.id}"]`);
            if (p) p.classList.remove('show');
        }
    });

    function crmGoToStep(n) {
        crmClearErrors();
        document.getElementById(`crm-step-${crmCurrentStep}`).classList.remove('active');
        crmCurrentStep = n;
        document.getElementById(`crm-step-${crmCurrentStep}`).classList.add('active');
        crmUpdateDots();
        crmSaveDraft();
        window.scrollTo({ top: document.getElementById('crmInquiryForm').offsetTop - 20, behavior: 'smooth' });
    }

    function crmGoToStep2() {
        const required = [
            ['crm_client_name', 'Client Name'],
            ['crm_house_street', 'House No. / Street'],
            ['crm_city', 'City / Municipality'],
            ['crm_barangay', 'Barangay'],
            ['crm_contact_number', 'Contact Number'],
        ];
        for (const [id, label] of required) {
            const el = document.getElementById(id);
            if (!el.value.trim()) {
                crmClearErrors();
                crmFieldError(id, `Please fill out ${label}.`);
                return;
            }
        }

        const contactEl = document.getElementById('crm_contact_number');
        if (!/^[0-9]{11}$/.test(contactEl.value.trim())) {
            crmClearErrors();
            crmFieldError('crm_contact_number', 'Contact Number must be exactly 11 digits (e.g. 09XXXXXXXXX).');
            return;
        }

        crmSyncAddressHiddenFields();
        crmGoToStep(2);
    }

    // ═══════════════════════════════════════════════════════════
    // PROJECT NATURE -> MODE (auto-lock, no default — user must pick)
    // ═══════════════════════════════════════════════════════════
    function crmSyncModeFromNature() {
        const nature = document.querySelector('input[name="project_nature"]:checked')?.value;
        const siteVisitRadio = document.getElementById('crm_mode_sitevisit');
        const readyRadio = document.getElementById('crm_mode_ready');
        const siteVisitLabel = siteVisitRadio.closest('label');
        const readyLabel = readyRadio.closest('label');

        // Reset muna
        siteVisitLabel.classList.remove('opacity-40', 'pointer-events-none');
        readyLabel.classList.remove('opacity-40', 'pointer-events-none');
        siteVisitRadio.disabled = false;
        readyRadio.disabled = false;
        siteVisitRadio.checked = false;
        readyRadio.checked = false;

        if (nature === 'modular') {
            siteVisitRadio.checked = true;
            readyRadio.disabled = true;
            readyLabel.classList.add('opacity-40', 'pointer-events-none');
        } else if (nature === 'material') {
            readyRadio.checked = true;
            siteVisitRadio.disabled = true;
            siteVisitLabel.classList.add('opacity-40', 'pointer-events-none');
        }
        crmSaveDraft();
    }

    function crmGoToStep3() {
        const nature = document.querySelector('input[name="project_nature"]:checked');
        if (!nature) {
            const p = document.querySelector('#crmInquiryForm [data-error-for="project_nature"]');
            if (p) { p.textContent = 'Please choose Modular or Material Only.'; p.classList.add('show'); }
            crmShowToast('Please select Project Nature (Modular or Material Only).', 'error', 3500);
            return;
        }
        crmGoToStep(3);
    }

    function crmGoToStep4() {
        crmBuildReview();
        crmGoToStep(4);
    }

    function crmUpdateDots() {
        for (let i = 1; i <= CRM_TOTAL_STEPS; i++) {
            const dot = document.getElementById(`crm-dot-${i}`);
            const lbl = document.getElementById(`crm-label-${i}`);
            dot.classList.toggle('is-done', i < crmCurrentStep);
            dot.classList.toggle('is-current', i === crmCurrentStep);
            dot.innerHTML = i < crmCurrentStep ? '<i class="fa-solid fa-check text-[11px]"></i>' : i;
            if (lbl) lbl.classList.toggle('is-on', i <= crmCurrentStep);
        }
        for (let i = 1; i < CRM_TOTAL_STEPS; i++) {
            document.getElementById(`crm-line-${i}`).classList.toggle('is-done', i < crmCurrentStep);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PSGC — Region / Province / City / Barangay cascading
    // ═══════════════════════════════════════════════════════════
    const CRM_PSGC = 'https://psgc.cloud/api';
    const CRM_NCR_CODE = '1300000000';

    async function crmFetchJSON(url) {
        const res = await fetch(url);
        if (!res.ok) throw new Error('API error ' + res.status);
        return res.json();
    }

    function crmPopulateSelect(selectEl, items, valueKey, labelKey, placeholder) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[labelKey];
            if (item.zipCode) opt.dataset.zip = item.zipCode;
            selectEl.appendChild(opt);
        });
        selectEl.disabled = false;
    }

    function crmResetSelect(selectEl, placeholder) {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        selectEl.disabled = true;
    }

    async function crmLoadRegions() {
        try {
            const regions = await crmFetchJSON(`${CRM_PSGC}/regions`);
            regions.sort((a, b) => a.name.localeCompare(b.name));
            crmPopulateSelect(document.getElementById('crm_region'), regions, 'code', 'name', '— Select Region —');
        } catch (e) { console.error('crmLoadRegions:', e); }
    }

    document.getElementById('crm_region').addEventListener('change', async function () {
        const code = this.value;
        const isNCR = (code === CRM_NCR_CODE);
        const wrapper = document.getElementById('crm_province_wrapper');
        const provSel = document.getElementById('crm_province');

        crmResetSelect(provSel, '— Select Province —');
        crmResetSelect(document.getElementById('crm_city'), '— Select City / Municipality —');
        crmResetSelect(document.getElementById('crm_barangay'), '— Select Barangay —');
        document.getElementById('crm_postal_code').value = '';

        if (!code) { wrapper.style.display = ''; return; }

        if (isNCR) {
            wrapper.style.display = 'none';
            try {
                const [cities, munis] = await Promise.all([
                    crmFetchJSON(`${CRM_PSGC}/regions/${code}/cities`).catch(() => []),
                    crmFetchJSON(`${CRM_PSGC}/regions/${code}/municipalities`).catch(() => [])
                ]);
                crmPopulateSelect(document.getElementById('crm_city'),
                    [...cities, ...munis].sort((a, b) => a.name.localeCompare(b.name)),
                    'code', 'name', '— Select City / Municipality —');
            } catch (e) { console.error('crm NCR cities:', e); }
        } else {
            wrapper.style.display = '';
            try {
                const provinces = await crmFetchJSON(`${CRM_PSGC}/regions/${code}/provinces`);
                provinces.sort((a, b) => a.name.localeCompare(b.name));
                crmPopulateSelect(provSel, provinces, 'code', 'name', '— Select Province —');
            } catch (e) { console.error('crm provinces:', e); }
        }
    });

    document.getElementById('crm_province').addEventListener('change', async function () {
        const code = this.value;
        crmResetSelect(document.getElementById('crm_city'), '— Select City / Municipality —');
        crmResetSelect(document.getElementById('crm_barangay'), '— Select Barangay —');
        document.getElementById('crm_postal_code').value = '';
        if (!code) return;
        try {
            const [cities, munis] = await Promise.all([
                crmFetchJSON(`${CRM_PSGC}/provinces/${code}/cities`).catch(() => []),
                crmFetchJSON(`${CRM_PSGC}/provinces/${code}/municipalities`).catch(() => [])
            ]);
            crmPopulateSelect(document.getElementById('crm_city'),
                [...cities, ...munis].sort((a, b) => a.name.localeCompare(b.name)),
                'code', 'name', '— Select City / Municipality —');
        } catch (e) { console.error('crm cities:', e); }
    });

    document.getElementById('crm_city').addEventListener('change', async function () {
        const code = this.value;
        crmResetSelect(document.getElementById('crm_barangay'), '— Select Barangay —');
        const sel = this.options[this.selectedIndex];
        document.getElementById('crm_postal_code').value = sel.dataset.zip || '';
        if (!code) return;
        try {
            const barangays = await crmFetchJSON(`${CRM_PSGC}/cities-municipalities/${code}/barangays`);
            barangays.sort((a, b) => a.name.localeCompare(b.name));
            crmPopulateSelect(document.getElementById('crm_barangay'), barangays, 'code', 'name', '— Select Barangay —');
        } catch (e) { console.error('crm barangays:', e); }
    });

    function crmSelectedText(id) {
        const el = document.getElementById(id);
        const t = el.options[el.selectedIndex]?.text || '';
        return t.startsWith('—') ? '' : t;
    }

    function crmUpdateAddressPreview() {
        const el = document.getElementById('crm_address_preview');
        if (!el) return;
        const parts = [
            document.getElementById('crm_house_street').value.trim(),
            crmSelectedText('crm_barangay') ? 'Brgy. ' + crmSelectedText('crm_barangay') : '',
            crmSelectedText('crm_city'),
            crmSelectedText('crm_province'),
            crmSelectedText('crm_region'),
        ].filter(Boolean);
        el.textContent = parts.length ? parts.join(', ') : 'The full address will appear here as you fill it in.';
        el.classList.toggle('text-gray-400', !parts.length);
        el.classList.toggle('text-gray-700', !!parts.length);
        el.classList.toggle('font-medium', !!parts.length);
    }
    ['crm_house_street', 'crm_region', 'crm_province', 'crm_city', 'crm_barangay'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('input', crmUpdateAddressPreview); el.addEventListener('change', crmUpdateAddressPreview); }
    });

    function crmSyncAddressHiddenFields() {
        document.getElementById('crm_region_name').value = crmSelectedText('crm_region');
        document.getElementById('crm_province_name').value = crmSelectedText('crm_province');
        document.getElementById('crm_city_name').value = crmSelectedText('crm_city');
        document.getElementById('crm_barangay_name').value = crmSelectedText('crm_barangay');
    }

    // ═══════════════════════════════════════════════════════════
    // REVIEW (Step 4)
    // ═══════════════════════════════════════════════════════════
    function crmCheckedValues(containerId) {
        return Array.from(document.querySelectorAll(`#${containerId} input[type="checkbox"]:checked`))
            .map(cb => cb.value)
            .join(', ') || '—';
    }

    function crmFormatDate(value) {
        if (!value) return '—';
        const dt = new Date(value + 'T00:00:00');
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    function crmSelectedNatureLabel() {
        const checked = document.querySelector('input[name="project_nature"]:checked');
        if (!checked) return '—';
        return checked.value === 'modular' ? 'Modular' : 'Material Only';
    }

    function crmSelectedModeLabel() {
        const checked = document.querySelector('input[name="inquiry_mode"]:checked');
        return (checked && checked.value === 'ready_for_quotation')
            ? 'Ready for Quotation (No Site Visit)'
            : 'Site Visit Needed';
    }

    function crmBuildReview() {
        crmSyncAddressHiddenFields();

        const houseStreet = document.getElementById('crm_house_street').value || '';
        const addressParts = [
            houseStreet,
            crmSelectedText('crm_barangay') ? 'Brgy. ' + crmSelectedText('crm_barangay') : '',
            crmSelectedText('crm_city'),
            crmSelectedText('crm_province'),
            crmSelectedText('crm_region'),
        ].filter(Boolean);

        const groups = [
            { title: 'Client', icon: 'fa-user', step: 1, rows: [
                ['Client Name', document.getElementById('crm_client_name').value || '—'],
                ['Contact Number', document.getElementById('crm_contact_number').value || '—'],
                ['Address', addressParts.join(', ') || '—'],
            ] },
            { title: 'Project', icon: 'fa-ruler-combined', step: 2, rows: [
                ['Project Nature', crmSelectedNatureLabel()],
                ['Mode', crmSelectedModeLabel()],
                ['Type of Project', document.getElementById('crm_project_type').value || '—'],
                ['Scope of Project', crmCheckedValues('project_scope_checkboxes')],
                ['Scope Area', crmCheckedValues('measuring_space_checkboxes')],
            ] },
        ];

        document.getElementById('crm_review_content').innerHTML = groups.map(g => `
            <div class="crm-review-card">
                <div class="crm-review-head">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600"><i class="fa-solid ${g.icon} text-amber-700 mr-1.5"></i>${g.title}</p>
                    <button type="button" onclick="crmGoToStep(${g.step})" class="text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-pen text-[9px] mr-1"></i>Edit</button>
                </div>
                ${g.rows.map(([label, val]) => `
                    <div class="crm-confirm-row">
                        <span class="crm-confirm-label">${label}</span>
                        <span class="crm-confirm-value">${escapeHtml(val)}</span>
                    </div>`).join('')}
            </div>`).join('');

        // laging i-confirm ulit bago mag-submit
        const chk = document.getElementById('crm_review_confirm');
        if (chk) { chk.checked = false; document.getElementById('crm_submit_btn').disabled = true; }
    }

    // ═══════════════════════════════════════════════════════════
    // AUTO-SAVE DRAFT (localStorage) — para di mawala pag nag-refresh
    // ═══════════════════════════════════════════════════════════
    const CRM_DRAFT_KEY = 'crmInquiryDraft_v1';

    function crmSaveDraft() {
        const form = document.getElementById('crmInquiryForm');
        const data = {};

        // NOTE: crm_measurement_datetime / crm_designer_id / crm_contract_amount
        // removed — none of these are part of this form anymore.
        const simpleIds = [
            'crm_client_name', 'crm_house_street', 'crm_contact_number',
            'crm_project_type',
            'crm_region', 'crm_province', 'crm_city', 'crm_barangay'
        ];
        simpleIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) data[id] = el.value;
        });

        data.project_scope = Array.from(form.querySelectorAll('input[name="project_scope[]"]:checked')).map(cb => cb.value);
        data.measuring_space = Array.from(form.querySelectorAll('input[name="measuring_space[]"]:checked')).map(cb => cb.value);
        data.project_nature = (form.querySelector('input[name="project_nature"]:checked') || {}).value || '';
        data.inquiry_mode = (form.querySelector('input[name="inquiry_mode"]:checked') || {}).value || '';
        data.step = crmCurrentStep;

        try { localStorage.setItem(CRM_DRAFT_KEY, JSON.stringify(data)); } catch (e) { return; }
        const tag = document.getElementById('crm_draft_tag');
        if (tag) { tag.classList.remove('hidden'); clearTimeout(crmSaveDraft._t); crmSaveDraft._t = setTimeout(() => tag.classList.add('hidden'), 2000); }
    }

    async function crmRestoreDraft() {
        let raw = null;
        try { raw = localStorage.getItem(CRM_DRAFT_KEY); } catch (e) { }
        if (!raw) { crmUpdateAddressPreview(); return; }

        let data;
        try { data = JSON.parse(raw); } catch (e) { return; }

        // NOTE: crm_measurement_datetime / crm_designer_id / crm_contract_amount
        // removed from restore too.
        const simpleIds = ['crm_client_name', 'crm_house_street', 'crm_contact_number',
            'crm_project_type'];
        simpleIds.forEach(id => {
            const el = document.getElementById(id);
            if (el && data[id] !== undefined) el.value = data[id];
        });

        // Region -> Province -> City -> Barangay (sunod-sunod dahil async yung fetch)
        if (data.crm_region) {
            const regionSel = document.getElementById('crm_region');
            regionSel.value = data.crm_region;
            await new Promise(resolve => {
                regionSel.dispatchEvent(new Event('change'));
                setTimeout(resolve, 500);
            });
        }
        if (data.crm_province) {
            const provinceSel = document.getElementById('crm_province');
            if (!provinceSel.disabled) {
                provinceSel.value = data.crm_province;
                await new Promise(resolve => {
                    provinceSel.dispatchEvent(new Event('change'));
                    setTimeout(resolve, 500);
                });
            }
        }
        if (data.crm_city) {
            const citySel = document.getElementById('crm_city');
            if (!citySel.disabled) {
                citySel.value = data.crm_city;
                await new Promise(resolve => {
                    citySel.dispatchEvent(new Event('change'));
                    setTimeout(resolve, 500);
                });
            }
        }
        if (data.crm_barangay) {
            const brgySel = document.getElementById('crm_barangay');
            if (!brgySel.disabled) brgySel.value = data.crm_barangay;
        }
        crmSyncAddressHiddenFields();
        crmUpdateAddressPreview();
        const cc = document.getElementById('crm_contact_count');
        if (cc) cc.textContent = (document.getElementById('crm_contact_number').value || '').length + '/11';

        // Project Nature -> derives Mode automatically (no separate inquiry_mode restore needed)
        if (data.project_nature) {
            const natureInput = document.querySelector(`input[name="project_nature"][value="${CSS.escape(data.project_nature)}"]`);
            if (natureInput) {
                natureInput.checked = true;
                crmSyncModeFromNature();
            }
        }

        (data.project_scope || []).forEach(val => {
            const cb = document.querySelector(`input[name="project_scope[]"][value="${CSS.escape(val)}"]`);
            if (cb) cb.checked = true;
        });
        (data.measuring_space || []).forEach(val => {
            const cb = document.querySelector(`input[name="measuring_space[]"][value="${CSS.escape(val)}"]`);
            if (cb) cb.checked = true;
        });

        // Bumalik sa step kung saan sila huling na-iwan
        if (data.step && data.step >= 1 && data.step <= CRM_TOTAL_STEPS) {
            document.getElementById(`crm-step-${crmCurrentStep}`).classList.remove('active');
            crmCurrentStep = data.step;
            document.getElementById(`crm-step-${crmCurrentStep}`).classList.add('active');
            crmUpdateDots();

            // Kung Step 4 (Review) ang na-restore, i-build ulit yung review content
            if (crmCurrentStep === 4) {
                crmBuildReview();
            }
        }
    }

    // Submit: sa Review lang, naka-check ang confirm, at isang beses lang
    document.getElementById('crmInquiryForm').addEventListener('submit', function (e) {
        const chk = document.getElementById('crm_review_confirm');
        if (crmCurrentStep !== CRM_TOTAL_STEPS || (chk && !chk.checked)) { e.preventDefault(); return; }
        crmSyncAddressHiddenFields();
        const btn = document.getElementById('crm_submit_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[11px]"></i> Submitting…';
    });
    // Enter sa text field: huwag i-submit ang form (lumipat lang sa susunod na field)
    document.getElementById('crmInquiryForm').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') e.preventDefault();
    });

    // I-save kada may binabago sa form (typing, select, checkbox)
    document.getElementById('crmInquiryForm').addEventListener('input', crmSaveDraft);
    document.getElementById('crmInquiryForm').addEventListener('change', crmSaveDraft);

    // I-trigger ang toast base sa flash data mula sa PHP session, tapos i-restore ang draft
    if (crmToastData.error) {
        crmShowToast(crmToastData.error, 'error');
    }
    if (crmToastData.success) {
        const msg = crmToastData.controlNo
            ? `${crmToastData.success} Reference No. <strong>${crmToastData.controlNo}</strong>`
            : crmToastData.success;
        crmShowToast(msg, 'success');
        try { localStorage.removeItem('crmInquiryDraft_v1'); } catch (e) { }
    }

    crmLoadRegions().then(crmRestoreDraft);

    // ═══════════════════════════════════════════════════════════
    // OPTIONS MODAL (Scope of Project / Measuring Space CRUD)
    // ═══════════════════════════════════════════════════════════
    let currentOptionsType = '';

    function openOptionsModal(type) {
        currentOptionsType = type;
        document.getElementById('optionsModalTitle').textContent =
            type === 'measuring_space' ? 'Manage Measuring Space Options' : 'Manage Scope of Project Options';
        const modal = document.getElementById('optionsModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadOptions();
    }

    function closeOptionsModal() {
        const modal = document.getElementById('optionsModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    async function loadOptions() {
        const res = await fetch(`<?= BASE_URL ?>/crmajax?action=list&type=${currentOptionsType}`);
        const data = await res.json();
        const list = document.getElementById('optionsList');
        list.innerHTML = '';
        if (data.success) {
            data.items.forEach(item => list.appendChild(buildOptionRow(item)));
        }
    }

    function buildOptionRow(item) {
        const li = document.createElement('li');
        li.className = 'flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2';
        const view = () => {
            li.innerHTML = `
                <span class="text-sm text-gray-700 flex-1 option-label">${escapeHtml(item.label)}</span>
                <button type="button" data-a="edit" class="w-7 h-7 rounded-md text-gray-400 hover:text-amber-700 hover:bg-amber-50" title="Edit"><i class="fa-solid fa-pen text-[11px]"></i></button>
                <button type="button" data-a="del" class="w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete"><i class="fa-solid fa-trash text-[11px]"></i></button>`;
            li.querySelector('[data-a="edit"]').addEventListener('click', edit);
            li.querySelector('[data-a="del"]').addEventListener('click', askDelete);
        };
        const edit = () => {
            li.innerHTML = `
                <input type="text" class="flex-1 h-8 px-2 text-sm border border-amber-600 rounded-md focus:outline-none" value="${escapeHtml(item.label)}">
                <button type="button" data-a="save" class="h-8 px-2.5 rounded-md text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800">Save</button>
                <button type="button" data-a="cancel" class="h-8 px-2 rounded-md text-xs font-semibold text-gray-500 hover:bg-gray-100">Cancel</button>`;
            const input = li.querySelector('input');
            input.focus(); input.select();
            const save = () => editOption(item.id, input.value, item.label);
            li.querySelector('[data-a="save"]').addEventListener('click', save);
            li.querySelector('[data-a="cancel"]').addEventListener('click', view);
            input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); } if (e.key === 'Escape') view(); });
        };
        const askDelete = () => {
            li.className = 'flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2';
            li.innerHTML = `
                <span class="text-xs text-red-700 flex-1">Delete <b>${escapeHtml(item.label)}</b>?</span>
                <button type="button" data-a="yes" class="h-7 px-2.5 rounded-md text-xs font-semibold text-white bg-red-600 hover:bg-red-700">Delete</button>
                <button type="button" data-a="no" class="h-7 px-2 rounded-md text-xs font-semibold text-gray-500 hover:bg-white">Cancel</button>`;
            li.querySelector('[data-a="yes"]').addEventListener('click', () => deleteOption(item.id));
            li.querySelector('[data-a="no"]').addEventListener('click', () => { li.className = 'flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2'; view(); });
        };
        view();
        return li;
    }

    async function addOption() {
        const input = document.getElementById('optionsNewLabel');
        const label = input.value.trim();
        if (!label) return;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('type', currentOptionsType);
        formData.append('label', label);

        const res = await fetch('<?= BASE_URL ?>/crmajax', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            loadOptions();
            refreshCheckboxGroup();
            crmShowToast('Option added.', 'success', 3000);
        } else {
            crmShowToast(data.message || 'Failed to add.', 'error', 4000);
        }
    }

    async function editOption(id, newLabel, currentLabel) {
        const trimmed = (newLabel || '').trim();
        if (trimmed === '' || trimmed === currentLabel) { loadOptions(); return; }

        const formData = new FormData();
        formData.append('action', 'edit');
        formData.append('type', currentOptionsType);
        formData.append('id', id);
        formData.append('label', trimmed);

        const res = await fetch('<?= BASE_URL ?>/crmajax', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            loadOptions();
            refreshCheckboxGroup();
            crmShowToast('Option updated.', 'success', 3000);
        } else {
            crmShowToast(data.message || 'Failed to update.', 'error', 4000);
        }
    }

    async function deleteOption(id) {

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('type', currentOptionsType);
        formData.append('id', id);

        const res = await fetch('<?= BASE_URL ?>/crmajax', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            loadOptions();
            refreshCheckboxGroup();
            crmShowToast('Option deleted.', 'success', 3000);
        } else {
            crmShowToast(data.message || 'Failed to delete.', 'error', 4000);
        }
    }

    // I-refresh yung checkboxes sa form (walang page reload), habang naka-preserve yung mga naka-check na
    async function refreshCheckboxGroup() {
        const containerId = currentOptionsType === 'measuring_space' ? 'measuring_space_checkboxes' : 'project_scope_checkboxes';
        const inputName = currentOptionsType === 'measuring_space' ? 'measuring_space[]' : 'project_scope[]';
        const container = document.getElementById(containerId);

        const res = await fetch(`<?= BASE_URL ?>/crmajax?action=list&type=${currentOptionsType}`);
        const data = await res.json();
        if (!data.success) return;

        const checkedValues = Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);

        container.innerHTML = '';
        if (data.items.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400">No options yet. Click Manage to add one.</p>';
            return;
        }
        data.items.forEach(item => {
            const label = document.createElement('label');
            label.className = 'cursor-pointer';
            label.innerHTML = `
                <input type="checkbox" name="${inputName}" value="${escapeHtml(item.label)}" class="peer sr-only">
                <span class="crm-chip"><i class="fa-solid fa-check crm-chip-tick"></i>${escapeHtml(item.label)}</span>
            `;
            if (checkedValues.includes(item.label)) {
                label.querySelector('input').checked = true;
            }
            container.appendChild(label);
        });
        crmSaveDraft();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOptionsModal(); });

    // I-close ang modal pag na-click sa labas nito
    document.getElementById('optionsModal').addEventListener('click', function (e) {
        if (e.target === this) closeOptionsModal();
    });
</script>