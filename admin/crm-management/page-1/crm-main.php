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

// ═══════════════════════════════════════════════════════════
// EDIT MODE — ?edit=<id>  (same form, naka-prefill, UPDATE imbes na INSERT)
// ═══════════════════════════════════════════════════════════
$crmEditId = intval($_GET['edit'] ?? 0);
$crmEditMode = $crmEditId > 0;
$crmEditRow = null;
$crmEditBlocked = '';      // may laman = hindi puwedeng i-edit (hindi kanya / wala)
$crmNatureLocked = '';     // may laman = bawal palitan ang Project Nature (umusad na)
$crmEditAddr = ['house_street' => '', 'barangay' => '', 'city' => '', 'province' => '', 'region' => '', 'parsed' => false];

// Hinihiwalay ang naka-save na address ("House, Brgy. X, City, Province, Region")
// pabalik sa mga parte para ma-prefill ang Region/Province/City/Barangay dropdowns.
function crmSplitAddress(string $address): array
{
    $out = ['house_street' => '', 'barangay' => '', 'city' => '', 'province' => '', 'region' => '', 'parsed' => false];
    $parts = array_map('trim', explode(',', $address));
    $brgyIdx = null;
    foreach ($parts as $i => $p) {
        if (preg_match('/^Brgy\.\s+/i', $p)) $brgyIdx = $i; // huling "Brgy." ang gamitin
    }
    if ($brgyIdx === null || $brgyIdx === 0) {
        $out['house_street'] = $address;
        return $out;
    }
    $after = array_slice($parts, $brgyIdx + 1);
    if (count($after) < 2 || count($after) > 3) {
        $out['house_street'] = $address;
        return $out;
    }
    $out['house_street'] = implode(', ', array_slice($parts, 0, $brgyIdx));
    $out['barangay'] = trim(preg_replace('/^Brgy\.\s+/i', '', $parts[$brgyIdx]));
    $out['city'] = $after[0];
    $out['province'] = count($after) === 3 ? $after[1] : '';
    $out['region'] = end($after);
    $out['parsed'] = true;
    return $out;
}

function crmInquiryHasProgress(mysqli $conn, array $row): string
{
    if ((int) ($row['designer_id'] ?? 0) > 0) return 'A designer is already assigned';
    foreach (['noblecrm_sitevisit' => 'The site visit has started', 'noblecrm_2dquotation' => 'The 2D & Quotation has started'] as $table => $why) {
        try {
            $st = $conn->prepare("SELECT 1 FROM {$table} WHERE inquiry_id = ? LIMIT 1");
            $st->bind_param('i', $row['id']);
            $st->execute();
            $has = (bool) $st->get_result()->fetch_row();
            $st->close();
            if ($has) return $why;
        } catch (Throwable $e) {
            // walang table → walang progress doon
        }
    }
    return '';
}

if ($crmEditMode) {
    $st = $conn->prepare("SELECT * FROM noblecrminquiry WHERE id = ? LIMIT 1");
    $st->bind_param('i', $crmEditId);
    $st->execute();
    $crmEditRow = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$crmEditRow) {
        $crmEditBlocked = 'This inquiry no longer exists.';
    } elseif ((int) $crmEditRow['sales_staff_id'] !== $currentSalesId) {
        $crmEditBlocked = 'Only the sales staff who filed this inquiry can edit it.';
    } else {
        $why = crmInquiryHasProgress($conn, $crmEditRow);
        if ($why !== '') $crmNatureLocked = $why . ', so the project nature can no longer be changed.';
        $crmEditAddr = crmSplitAddress((string) $crmEditRow['address']);
    }
}
$crmListUrl = BASE_URL . '/crmsaleslist';

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
if (!$crmEditMode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
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

// --- Handle update (edit mode) ---
if ($crmEditMode && $crmEditRow && $crmEditBlocked === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inquiry'])) {
    $clean = fn($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
    $toList = function ($v) use ($clean): string {
        $items = is_array($v) ? $v : explode(',', (string) $v);
        return implode(', ', array_values(array_unique(array_filter(array_map($clean, $items), fn($x) => $x !== ''))));
    };

    $clientName = $clean($_POST['client_name'] ?? '');
    $contactNumber = preg_replace('/\D/', '', (string) ($_POST['contact_number'] ?? ''));
    $projectType = $clean($_POST['project_type'] ?? '');
    $projectScope = $toList($_POST['project_scope'] ?? []);
    $measuringSpace = $toList($_POST['measuring_space'] ?? []);

    $houseStreet = $clean($_POST['house_street'] ?? '');
    $regionName = $clean($_POST['region_name'] ?? '');
    $provinceName = $clean($_POST['province_name'] ?? '');
    $cityName = $clean($_POST['city_name'] ?? '');
    $barangayName = $clean($_POST['barangay_name'] ?? '');
    $keepAddress = ($_POST['keep_address'] ?? '') === '1';

    // Project Nature: kapag naka-lock, laging yung naka-save (hindi galing sa POST)
    $oldNature = (string) $crmEditRow['project_nature'];
    $projectNature = $crmNatureLocked !== '' ? $oldNature : trim($_POST['project_nature'] ?? '');
    if (!in_array($projectNature, ['modular', 'material'], true)) $projectNature = $crmNatureLocked !== '' ? $oldNature : '';

    if ($keepAddress && $cityName === '' && $barangayName === '') {
        // hindi tumugma sa dropdowns ang lumang address at hindi ginalaw — panatilihin
        $address = (string) $crmEditRow['address'];
    } else {
        $keepAddress = false;
        $address = implode(', ', array_filter([
            $houseStreet,
            $barangayName !== '' ? 'Brgy. ' . $barangayName : '',
            $cityName,
            $provinceName,
            $regionName,
        ]));
    }

    if ($clientName === '' || $contactNumber === '' || $projectNature === ''
        || (!$keepAddress && ($houseStreet === '' || $cityName === '' || $barangayName === ''))) {
        $formError = 'Please fill out all required fields.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contactNumber)) {
        $formError = 'Contact Number must be exactly 11 digits.';
    } elseif (mb_strlen($clientName) > 150) {
        $formError = 'Client name is too long (max 150 characters).';
    } elseif (mb_strlen($address) > 255) {
        $formError = 'Address is too long (max 255 characters).';
    } elseif (mb_strlen($projectType) > 100) {
        $formError = 'Type of project is too long (max 100 characters).';
    } elseif (mb_strlen($projectScope) > 1000 || mb_strlen($measuringSpace) > 1000) {
        $formError = 'Too many scope items selected.';
    } else {
        // Mode/Status: binabago lang kapag talagang pinalitan ang Project Nature (at hindi pa naka-lock)
        $mode = (string) ($crmEditRow['mode'] ?? '');
        $status = (string) ($crmEditRow['status'] ?? '');
        if ($projectNature !== $oldNature || $mode === '') {
            $mode = $projectNature === 'material' ? 'ready_for_quotation' : 'site_visit';
            if ($projectNature !== $oldNature) $status = $mode === 'ready_for_quotation' ? 'In Progress' : 'Pending';
        }

        $st = $conn->prepare("
            UPDATE noblecrminquiry
            SET client_name = ?, contact_number = ?, address = ?, project_type = ?, project_nature = ?,
                project_scope = ?, measuring_space = ?, mode = ?, status = ?
            WHERE id = ? AND sales_staff_id = ?
        ");
        $st->bind_param('sssssssssii', $clientName, $contactNumber, $address, $projectType, $projectNature,
            $projectScope, $measuringSpace, $mode, $status, $crmEditId, $currentSalesId);
        if ($st->execute()) {
            $st->close();
            $_SESSION['crm_flash_success'] = 'Inquiry updated.';
            $_SESSION['crm_flash_control_no'] = (string) $crmEditRow['control_no'];
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $st->close();
        $formError = 'Something went wrong. Please try again.';
    }
}

// Mga value na ipapakita sa form: POST (kapag may error) → naka-save na record (edit) → blank (new)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crmForm = $_POST;
} elseif ($crmEditRow && $crmEditBlocked === '') {
    $splitList = fn($v) => array_values(array_filter(array_map('trim', explode(',', (string) $v)), fn($x) => $x !== ''));
    $crmForm = [
        'client_name' => (string) $crmEditRow['client_name'],
        'contact_number' => (string) $crmEditRow['contact_number'],
        'house_street' => $crmEditAddr['house_street'],
        'project_type' => (string) $crmEditRow['project_type'],
        'project_nature' => (string) $crmEditRow['project_nature'],
        'inquiry_mode' => ($crmEditRow['project_nature'] ?? '') === 'material' ? 'ready_for_quotation' : 'site_visit',
        'project_scope' => $splitList($crmEditRow['project_scope']),
        'measuring_space' => $splitList($crmEditRow['measuring_space']),
    ];
} else {
    $crmForm = [];
}

// Ginagamit sa pag-check ng mga naka-checked na checkbox (failed submit o edit prefill)
$postedMeasuringSpace = (array) ($crmForm['measuring_space'] ?? []);
$postedProjectScope = (array) ($crmForm['project_scope'] ?? []);
$postedMode = $crmForm['inquiry_mode'] ?? '';
$postedNature = $crmForm['project_nature'] ?? '';

// Kapag may naka-save na scope/area na wala na sa options list, ipakita pa rin (naka-check)
foreach ([[&$projectScopeOptions, $postedProjectScope], [&$measuringSpaceOptions, $postedMeasuringSpace]] as $pair) {
    $labels = array_map(fn($o) => mb_strtolower($o['label']), $pair[0]);
    foreach ($pair[1] as $val) {
        if (!in_array(mb_strtolower($val), $labels, true)) {
            $pair[0][] = ['id' => 0, 'label' => $val];
            $labels[] = mb_strtolower($val);
        }
    }
}
unset($pair);

// Folder kung saan nakatago ang bawat step's markup (palitan kung iba ang gusto mong path)
$crmStepsPath = ROOT_PATH . '/admin/crm-management/page-1';

?>


<?php
// Tailwind-only na class strings — ginagamit ng step1–step4 at ng JS (walang <style> sa page na ito)
$crmInputCls = 'w-full h-11 px-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-xl outline-none transition placeholder:text-gray-400 focus:border-amber-600 focus:ring-2 focus:ring-amber-100';
$crmSelectCls = $crmInputCls . ' appearance-none pr-9 cursor-pointer disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed';
$crmChipCls = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] text-gray-700 bg-white border border-gray-300 select-none transition hover:border-amber-400 peer-checked:bg-amber-700 peer-checked:border-amber-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-amber-300 [&>i]:hidden peer-checked:[&>i]:inline';
$crmSteps = [
    ['Client Info', 'Name & address', 'fa-user'],
    ['Project Details', 'Nature & scope', 'fa-ruler-combined'],
    ['Assignment', 'Who handles it', 'fa-users-gear'],
    ['Review', $crmEditMode ? 'Check & save' : 'Check & submit', 'fa-clipboard-check'],
];
?>

<!-- Flash data mula sa PHP session, ginagamit ng toast script sa ibaba -->
<script>
    const crmToastData = {
        error: <?= json_encode($formError) ?>,
        success: <?= json_encode($formSuccess) ?>,
        controlNo: <?= json_encode($generatedControlNo) ?>
    };
    const CRM_CHIP_CLS = <?= json_encode($crmChipCls) ?>;
    // Edit mode: naka-prefill ang form mula sa naka-save na inquiry (walang localStorage draft)
    const CRM_EDIT = <?= json_encode(($crmEditRow && $crmEditBlocked === '') ? [
        'id' => $crmEditId,
        'control_no' => (string) $crmEditRow['control_no'],
        'address' => (string) $crmEditRow['address'],
        'addr' => $_SERVER['REQUEST_METHOD'] === 'POST' ? [
            'house_street' => (string) ($_POST['house_street'] ?? ''),
            'region' => (string) ($_POST['region_name'] ?? ''),
            'province' => (string) ($_POST['province_name'] ?? ''),
            'city' => (string) ($_POST['city_name'] ?? ''),
            'barangay' => (string) ($_POST['barangay_name'] ?? ''),
            'parsed' => ($_POST['city_name'] ?? '') !== '' && ($_POST['barangay_name'] ?? '') !== '',
        ] : $crmEditAddr,
        'failed_post' => $_SERVER['REQUEST_METHOD'] === 'POST',
        'nature_locked' => $crmNatureLocked,
    ] : null, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<?php if ($crmEditMode && $crmEditBlocked !== ''): ?>
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
        <div class="p-6 text-center">
            <span class="w-12 h-12 mx-auto rounded-xl bg-red-50 text-red-600 border border-red-100 flex items-center justify-center"><i class="fa-solid fa-lock"></i></span>
            <h1 class="mt-3 text-base font-semibold text-gray-900">Can't edit this inquiry</h1>
            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($crmEditBlocked) ?></p>
            <a href="<?= htmlspecialchars($crmListUrl) ?>" class="mt-4 inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-gray-900 hover:bg-black text-white text-sm font-semibold"><i class="fa-solid fa-arrow-left text-[11px]"></i> Back to Sales &amp; Market List</a>
        </div>
    </div>
</div>
<?php else: ?>

<div class="crm-doc max-w-6xl mx-auto">

    <div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-4 items-start">

        <!-- ═══ FORM CARD ═══ -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden min-w-0">
            <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>

            <!-- Header -->
            <div class="relative px-5 md:px-7 pt-5 pb-4 border-b border-gray-100 overflow-hidden">
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="" aria-hidden="true"
                    class="absolute -right-6 -bottom-10 w-40 opacity-[0.05] pointer-events-none select-none">
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-12 h-12 rounded-xl bg-white border border-gray-200 shadow-sm flex items-center justify-center shrink-0">
                            <img src="<?= BASE_URL ?>/icon/logo.webp" class="h-9 w-9 object-contain" alt="logo">
                        </span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-bold tracking-[0.18em] uppercase">CRM · <?= $crmEditMode ? 'Edit Inquiry' : 'Inquiry Form' ?></p>
                            <h1 class="text-gray-900 text-xl leading-tight font-semibold"><?= $crmEditMode ? 'Update Client Inquiry' : 'New Client Inquiry' ?></h1>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500"><i class="fa-regular fa-calendar text-amber-700"></i> <?= $crmEditMode ? 'Filed ' . htmlspecialchars(date('F d, Y', strtotime((string) $crmEditRow['created_at']) ?: time())) : date('F d, Y') ?></span>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500 capitalize"><i class="fa-solid fa-code-branch text-amber-700"></i> <?= htmlspecialchars($_SESSION['branch'] ?? '—') ?></span>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-gray-50 border border-gray-200 text-gray-500"><i class="fa-regular fa-user text-amber-700"></i> <?= htmlspecialchars($currentSalesName) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0 rounded-xl bg-gray-50 border border-gray-200 px-3.5 py-2">
                        <p class="text-[9px] text-gray-400 uppercase tracking-[0.16em]">Reference No.</p>
                        <p class="text-gray-900 font-mono font-semibold text-sm">
                            <?= $crmEditMode
                                ? htmlspecialchars((string) $crmEditRow['control_no'])
                                : (!empty($generatedControlNo)
                                    ? htmlspecialchars($generatedControlNo)
                                    : '<span class="text-gray-400 font-sans text-xs">Assigned on submit</span>') ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($crmEditMode): ?>
                <div class="mx-5 md:mx-7 mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-2.5">
                    <i class="fa-solid fa-pen-to-square text-amber-700"></i>
                    <p class="flex-1 min-w-[12rem] text-[12px] text-amber-900 leading-relaxed">You're editing an existing inquiry. Changes are saved to the same record and show up everywhere it appears.</p>
                    <a href="<?= htmlspecialchars($crmListUrl) ?>" class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-amber-800 px-2.5 py-1 rounded-lg bg-white border border-amber-200 hover:bg-amber-100"><i class="fa-solid fa-arrow-left text-[10px]"></i> Back to list</a>
                </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="flex items-start px-5 md:px-8 pt-5 pb-1">
                <?php foreach ($crmSteps as $i => [$label, $sub, $icon]):
                    $n = $i + 1; ?>
                    <button type="button" onclick="crmStepClick(<?= $n ?>)" class="flex flex-col items-center group min-w-0" aria-label="Step <?= $n ?>: <?= $label ?>">
                        <span id="crm-dot-<?= $n ?>" class="relative z-[1] w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all duration-300
                            <?= $n === 1 ? 'border-amber-700 text-amber-700 bg-amber-50 ring-4 ring-amber-100' : 'border-gray-300 text-gray-400 bg-white' ?>"><?= $n ?></span>
                        <span id="crm-label-<?= $n ?>" class="mt-1.5 text-[11px] font-semibold whitespace-nowrap transition-colors <?= $n === 1 ? 'text-gray-900' : 'text-gray-400' ?>"><?= $label ?></span>
                        <span class="hidden sm:block text-[10px] text-gray-400 whitespace-nowrap"><?= $sub ?></span>
                    </button>
                    <?php if ($n < count($crmSteps)): ?>
                        <div id="crm-line-<?= $n ?>" class="flex-1 h-0.5 bg-gray-200 mx-1.5 mt-[18px] rounded overflow-hidden"><span class="block h-full w-0 bg-amber-700 transition-all duration-500"></span></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Form Body -->
            <form method="POST" action="" id="crmInquiryForm" class="px-5 md:px-7 pt-4 pb-6 bg-slate-50/50" novalidate>
                <?php
                include $crmStepsPath . '/step1.php';
                include $crmStepsPath . '/step2.php';
                include $crmStepsPath . '/step3.php';
                include $crmStepsPath . '/step4.php';
                ?>
            </form>

            <!-- footer strip -->
            <div class="px-5 md:px-7 py-3 bg-gray-50 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <p class="text-[11px] text-amber-800/80"><i class="fa-solid fa-shield-halved mr-1"></i>This record is maintained by the Sales Department.</p>
                <p class="text-[11px] text-gray-400">NobleHome Construction Corporation</p>
            </div>
        </div>

        <!-- ═══ LIVE SUMMARY (sticky) ═══ -->
        <aside class="lg:sticky lg:top-4 space-y-3 min-w-0">
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                    <i class="fa-solid fa-file-lines text-amber-700"></i>
                    <p class="text-[12.5px] font-semibold text-gray-900 flex-1">Inquiry summary</p>
                    <span id="crm_draft_tag" class="hidden inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 border border-green-200 text-green-700 text-[10px] font-semibold">
                        <i class="fa-solid fa-cloud"></i> Draft saved
                    </span>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[10.5px] font-semibold text-gray-500 mb-1"><span>Completion</span><span id="crm_sum_pct">0%</span></div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden"><div id="crm_sum_bar" class="h-full bg-amber-600 rounded-full transition-all duration-300" style="width:0%"></div></div>
                    </div>
                    <dl id="crm_summary" class="space-y-2.5 text-[12px]"></dl>
                </div>
            </section>
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white/60 px-4 py-3 text-[11px] text-gray-500 leading-relaxed">
                <p class="font-semibold text-gray-700 mb-1"><i class="fa-solid fa-lightbulb text-amber-600 mr-1"></i>Tip</p>
                <?= $crmEditMode
                    ? 'Nothing changes until you press <b>Save changes</b> on the Review step. Designer, schedule and contract stay in the Sales &amp; Market List.'
                    : 'Your progress is saved on this device as you type, so you can refresh or come back later.' ?>
            </section>
        </aside>
    </div>
</div>

<!-- Reusable Modal para sa Manage Options (CRUD) ng Measuring Space / Scope of Project -->
<div id="optionsModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-gear text-xs"></i></span>
                <h3 id="optionsModalTitle" class="text-sm font-semibold text-gray-800 truncate">Manage Options</h3>
            </div>
            <button type="button" onclick="closeOptionsModal()" aria-label="Close"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex gap-2">
                <input id="optionsNewLabel" type="text" placeholder="Add new option"
                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); addOption(); }"
                    class="flex-1 h-10 px-3 text-sm border border-gray-300 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                <button type="button" onclick="addOption()"
                    class="h-10 px-4 text-sm font-semibold text-white bg-gray-900 rounded-lg hover:bg-black">
                    <i class="fa-solid fa-plus text-xs mr-1"></i>Add
                </button>
            </div>
            <ul id="optionsList" class="space-y-2 max-h-64 overflow-y-auto [scrollbar-width:thin]"></ul>
        </div>
    </div>
</div>

<!-- Toast container -->
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
            ? { wrap: 'bg-white border-gray-200 text-gray-800', icon: 'bg-emerald-500 text-white', symbol: '✓' }
            : { wrap: 'bg-white border-gray-200 text-gray-800', icon: 'bg-red-600 text-white', symbol: '!' };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-xl shadow-lg px-4 py-3 text-sm
            ${palette.wrap}
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;

        toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">
                ${palette.symbol}
            </span>
            <span class="flex-1 leading-relaxed">${message}</span>
            <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>
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
    const CRM_INVALID_CLS = ['crm-invalid', '!border-red-500', 'ring-2', 'ring-red-100'];
    function crmSetInvalid(el, on) { if (el) CRM_INVALID_CLS.forEach(c => el.classList.toggle(c, on)); }
    function crmShowError(key, msg) {
        const p = document.querySelector(`#crmInquiryForm [data-error-for="${key}"]`);
        if (!p) return;
        p.textContent = msg || '';
        p.classList.toggle('hidden', !msg);
    }
    function crmClearErrors() {
        document.querySelectorAll('#crmInquiryForm .crm-invalid').forEach(el => crmSetInvalid(el, false));
        document.querySelectorAll('#crmInquiryForm .crm-error').forEach(p => { p.classList.add('hidden'); p.textContent = ''; });
        const ng = document.getElementById('crm_nature_group');
        if (ng) ng.classList.remove('ring-2', 'ring-red-200', 'ring-offset-2');
    }
    function crmFieldError(id, msg) {
        const el = document.getElementById(id);
        if (el) { crmSetInvalid(el, true); el.focus({ preventScroll: true }); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        crmShowError(id, msg);
        crmShowToast(msg, 'error', 3500);
    }
    // tanggalin ang pula kapag inayos na ng user
    document.getElementById('crmInquiryForm').addEventListener('input', e => {
        if (!e.target.classList.contains('crm-invalid')) return;
        crmSetInvalid(e.target, false);
        crmShowError(e.target.id, '');
    });
    document.getElementById('crmInquiryForm').addEventListener('change', e => {
        if (e.target.name === 'project_nature') {
            crmShowError('project_nature', '');
            const ng = document.getElementById('crm_nature_group');
            if (ng) ng.classList.remove('ring-2', 'ring-red-200', 'ring-offset-2');
        }
        if (e.target.classList.contains('crm-invalid')) {
            crmSetInvalid(e.target, false);
            crmShowError(e.target.id, '');
        }
    });

    function crmContactCount() {
        const el = document.getElementById('crm_contact_number');
        const cc = document.getElementById('crm_contact_count');
        if (!el || !cc) return;
        const n = el.value.length;
        cc.textContent = n + '/11';
        cc.className = 'absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold ' + (n === 11 ? 'text-green-600' : n ? 'text-amber-600' : 'text-gray-300');
    }

    function crmSetProjectType(v) {
        const el = document.getElementById('crm_project_type');
        el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Pinindot ang step sa itaas: pwedeng bumalik; pasulong ay dadaan pa rin sa validation
    function crmStepClick(n) {
        if (n === crmCurrentStep) return;
        if (n < crmCurrentStep) return crmGoToStep(n);
        if (crmCurrentStep === 1) return crmGoToStep2();
        if (crmCurrentStep === 2) return crmGoToStep3();
        if (crmCurrentStep === 3) return crmGoToStep4();
    }

    function crmShowStep(n) {
        document.querySelectorAll('#crmInquiryForm .crm-step-panel').forEach(p => p.classList.add('hidden'));
        const panel = document.getElementById(`crm-step-${n}`);
        panel.classList.remove('hidden');
        panel.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 250, easing: 'ease-out' });
    }

    function crmGoToStep(n) {
        crmClearErrors();
        crmCurrentStep = n;
        crmShowStep(n);
        crmUpdateDots();
        crmSaveDraft();
        crmUpdateSummary();
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
            if (crmKeepAddress() && ['crm_house_street', 'crm_city', 'crm_barangay'].includes(id)) continue;
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
        const note = document.getElementById('crm_next_mode_note');
        if (note) {
            note.classList.toggle('hidden', !nature);
            note.innerHTML = nature === 'material'
                ? '<i class="fa-solid fa-file-invoice mr-1"></i>Material Only — no site visit needed. Goes straight to 2D &amp; Quotation once a designer is assigned.'
                : '<i class="fa-solid fa-person-walking mr-1"></i>Modular — the designer will visit the site to take measurements first.';
        }
        crmSaveDraft();
    }

    function crmGoToStep3() {
        const nature = document.querySelector('input[name="project_nature"]:checked');
        if (!nature) {
            crmShowError('project_nature', 'Please choose Modular or Material Only.');
            const ng = document.getElementById('crm_nature_group');
            if (ng) { ng.classList.add('ring-2', 'ring-red-200', 'ring-offset-2'); ng.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
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
        const base = 'relative z-[1] w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all duration-300 ';
        for (let i = 1; i <= CRM_TOTAL_STEPS; i++) {
            const dot = document.getElementById(`crm-dot-${i}`);
            const lbl = document.getElementById(`crm-label-${i}`);
            const done = i < crmCurrentStep, cur = i === crmCurrentStep;
            dot.className = base + (done ? 'border-amber-700 bg-amber-700 text-white'
                : cur ? 'border-amber-700 text-amber-700 bg-amber-50 ring-4 ring-amber-100'
                    : 'border-gray-300 text-gray-400 bg-white');
            dot.innerHTML = done ? '<i class="fa-solid fa-check text-[11px]"></i>' : i;
            if (lbl) lbl.className = 'mt-1.5 text-[11px] font-semibold whitespace-nowrap transition-colors ' + (i <= crmCurrentStep ? 'text-gray-900' : 'text-gray-400');
        }
        for (let i = 1; i < CRM_TOTAL_STEPS; i++) {
            document.getElementById(`crm-line-${i}`).firstElementChild.style.width = i < crmCurrentStep ? '100%' : '0';
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

    // Awaitable na loaders (ginagamit ng listener, ng draft restore, at ng edit prefill)
    async function crmOnRegionChange() {
        const code = document.getElementById('crm_region').value;
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
    }

    async function crmOnProvinceChange() {
        const code = document.getElementById('crm_province').value;
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
    }

    async function crmOnCityChange() {
        const cityEl = document.getElementById('crm_city');
        const code = cityEl.value;
        crmResetSelect(document.getElementById('crm_barangay'), '— Select Barangay —');
        const sel = cityEl.options[cityEl.selectedIndex];
        document.getElementById('crm_postal_code').value = sel.dataset.zip || '';
        if (!code) return;
        try {
            const barangays = await crmFetchJSON(`${CRM_PSGC}/cities-municipalities/${code}/barangays`);
            barangays.sort((a, b) => a.name.localeCompare(b.name));
            crmPopulateSelect(document.getElementById('crm_barangay'), barangays, 'code', 'name', '— Select Barangay —');
        } catch (e) { console.error('crm barangays:', e); }
    }

    document.getElementById('crm_region').addEventListener('change', crmOnRegionChange);
    document.getElementById('crm_province').addEventListener('change', crmOnProvinceChange);
    document.getElementById('crm_city').addEventListener('change', crmOnCityChange);

    // ── Edit mode: "Keep saved address" kapag hindi tumugma sa dropdowns ang lumang address ──
    function crmKeepAddress() {
        return document.getElementById('crm_keep_address')?.value === '1';
    }
    function crmSetKeepAddress(on) {
        const k = document.getElementById('crm_keep_address');
        if (k) k.value = on ? '1' : '';
        const note = document.getElementById('crm_keep_address_note');
        if (note) note.classList.toggle('hidden', !on);
    }
    // kapag ang user mismo ang pumili ng bagong Region/City/Barangay → bagong address na ang gagamitin
    ['crm_region', 'crm_province', 'crm_city', 'crm_barangay'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => { if (crmKeepAddress()) crmSetKeepAddress(false); }));

    const crmNorm = v => String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();
    // piliin ang option ayon sa pangalan; true kapag may tumugma
    async function crmPickByName(id, name, after) {
        const el = document.getElementById(id);
        const want = crmNorm(name);
        if (!want || el.disabled) return false;
        const opt = Array.from(el.options).find(o => o.value && crmNorm(o.text) === want);
        if (!opt) return false;
        el.value = opt.value;
        if (after) await after();
        return true;
    }

    async function crmPrefillEdit() {
        const a = CRM_EDIT.addr || {};
        let ok = !!a.parsed;
        if (ok) ok = await crmPickByName('crm_region', a.region, crmOnRegionChange);
        if (ok && a.province) ok = await crmPickByName('crm_province', a.province, crmOnProvinceChange);
        if (ok) ok = await crmPickByName('crm_city', a.city, crmOnCityChange);
        if (ok) ok = await crmPickByName('crm_barangay', a.barangay, null);

        if (!ok) {
            // ibalik sa blangko ang dropdowns; ang lumang address ay mananatili hangga't walang bagong pinipili
            document.getElementById('crm_region').value = '';
            await crmOnRegionChange();
            if (!a.parsed && !CRM_EDIT.failed_post) document.getElementById('crm_house_street').value = '';
            const saved = document.getElementById('crm_keep_address_saved');
            if (saved) saved.textContent = CRM_EDIT.address || '—';
            crmSetKeepAddress(!!CRM_EDIT.address);
        }
        crmSyncAddressHiddenFields();
        crmUpdateAddressPreview();
        crmContactCount();
        crmSyncModeFromNature();
        crmUpdateSummary();
    }

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
        if (crmKeepAddress() && CRM_EDIT) parts.splice(0, parts.length, CRM_EDIT.address);
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
                ['Address', (crmKeepAddress() && CRM_EDIT ? CRM_EDIT.address : addressParts.join(', ')) || '—'],
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
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600"><i class="fa-solid ${g.icon} text-amber-700 mr-1.5"></i>${g.title}</p>
                    <button type="button" onclick="crmGoToStep(${g.step})" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-900 px-2 py-1 rounded-md hover:bg-amber-50"><i class="fa-solid fa-pen text-[9px]"></i>Edit</button>
                </div>
                ${g.rows.map(([label, val]) => `
                    <div class="flex justify-between gap-4 px-4 py-2.5 border-b border-gray-100 last:border-b-0 text-[13px]">
                        <span class="text-gray-400 whitespace-nowrap">${label}</span>
                        <span class="text-right font-medium min-w-0 break-words ${val === '—' ? 'text-gray-300' : 'text-gray-900'}">${escapeHtml(val)}</span>
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
        if (CRM_EDIT) return; // edit mode: walang draft — ang naka-save na record ang source
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
            await crmOnRegionChange();
        }
        if (data.crm_province) {
            const provinceSel = document.getElementById('crm_province');
            if (!provinceSel.disabled) {
                provinceSel.value = data.crm_province;
                await crmOnProvinceChange();
            }
        }
        if (data.crm_city) {
            const citySel = document.getElementById('crm_city');
            if (!citySel.disabled) {
                citySel.value = data.crm_city;
                await crmOnCityChange();
            }
        }
        if (data.crm_barangay) {
            const brgySel = document.getElementById('crm_barangay');
            if (!brgySel.disabled) brgySel.value = data.crm_barangay;
        }
        crmSyncAddressHiddenFields();
        crmUpdateAddressPreview();
        crmContactCount();

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
            crmCurrentStep = data.step;
            crmShowStep(crmCurrentStep);
            crmUpdateDots();

            // Kung Step 4 (Review) ang na-restore, i-build ulit yung review content
            if (crmCurrentStep === 4) {
                crmBuildReview();
            }
        }
        crmUpdateSummary();
    }

    // Submit: sa Review lang, naka-check ang confirm, at isang beses lang
    document.getElementById('crmInquiryForm').addEventListener('submit', function (e) {
        const chk = document.getElementById('crm_review_confirm');
        if (crmCurrentStep !== CRM_TOTAL_STEPS || (chk && !chk.checked)) { e.preventDefault(); return; }
        crmSyncAddressHiddenFields();
        const btn = document.getElementById('crm_submit_btn');
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-[11px]"></i> ${CRM_EDIT ? 'Saving…' : 'Submitting…'}`;
    });
    // Enter sa text field: huwag i-submit ang form (lumipat lang sa susunod na field)
    document.getElementById('crmInquiryForm').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') e.preventDefault();
    });

    // ═══════════════════════════════════════════════════════════
    // LIVE SUMMARY (kanang panel)
    // ═══════════════════════════════════════════════════════════
    function crmUpdateSummary() {
        const box = document.getElementById('crm_summary');
        if (!box) return;
        const v = id => (document.getElementById(id)?.value || '').trim();
        const checked = name => Array.from(document.querySelectorAll(`input[name="${name}"]:checked`)).map(c => c.value);
        const keep = crmKeepAddress() && !!CRM_EDIT;
        const addr = keep ? CRM_EDIT.address : [v('crm_house_street'), crmSelectedText('crm_barangay') ? 'Brgy. ' + crmSelectedText('crm_barangay') : '', crmSelectedText('crm_city'), crmSelectedText('crm_province')].filter(Boolean).join(', ');
        const addrOk = keep || !!(v('crm_house_street') && crmSelectedText('crm_city') && crmSelectedText('crm_barangay'));
        const nature = document.querySelector('input[name="project_nature"]:checked')?.value || '';
        const scope = checked('project_scope[]');
        const area = checked('measuring_space[]');
        const phoneOk = /^[0-9]{11}$/.test(v('crm_contact_number'));

        const required = [!!v('crm_client_name'), phoneOk, keep || !!v('crm_house_street'), keep || !!crmSelectedText('crm_city'), keep || !!crmSelectedText('crm_barangay'), !!nature];
        const pct = Math.round(required.filter(Boolean).length / required.length * 100);
        document.getElementById('crm_sum_pct').textContent = pct + '%';
        document.getElementById('crm_sum_bar').style.width = pct + '%';
        document.getElementById('crm_sum_bar').className = 'h-full rounded-full transition-all duration-300 ' + (pct === 100 ? 'bg-green-600' : 'bg-amber-600');

        const row = (icon, label, value, ok) => `
            <div class="flex items-start gap-2.5">
                <span class="w-6 h-6 rounded-md ${ok ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'} flex items-center justify-center shrink-0 text-[10px]"><i class="fa-solid ${ok ? 'fa-check' : icon}"></i></span>
                <div class="min-w-0 flex-1">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">${label}</dt>
                    <dd class="${value ? 'text-gray-800 font-medium' : 'text-gray-300'} break-words">${value ? escapeHtml(value) : 'Not yet filled'}</dd>
                </div>
            </div>`;
        const chips = list => list.length
            ? `<dd class="flex flex-wrap gap-1 mt-0.5">${list.map(s => `<span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 text-[10.5px] font-medium">${escapeHtml(s)}</span>`).join('')}</dd>`
            : '<dd class="text-gray-300">None selected</dd>';

        box.innerHTML = [
            row('fa-user', 'Client', v('crm_client_name'), !!v('crm_client_name')),
            row('fa-phone', 'Contact', v('crm_contact_number') + (v('crm_contact_number') && !phoneOk ? ' (needs 11 digits)' : ''), phoneOk),
            row('fa-location-dot', 'Address', addr, addrOk),
            row('fa-cubes', 'Project nature', nature ? (nature === 'modular' ? 'Modular · Site visit needed' : 'Material Only · Ready for quotation') : '', !!nature),
            row('fa-building', 'Type of project', v('crm_project_type'), !!v('crm_project_type')),
            `<div class="pt-1 border-t border-gray-100"><dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mt-1.5">Scope of project</dt>${chips(scope)}</div>`,
            `<div><dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Scope area</dt>${chips(area)}</div>`,
        ].join('');

        // bilang ng napili sa bawat chip group
        [['project_scope_checkboxes', scope.length], ['measuring_space_checkboxes', area.length]].forEach(([id, n]) => {
            const el = document.querySelector(`[data-chip-count="${id}"]`);
            if (el) el.textContent = n ? `· ${n} selected` : '';
        });
    }

    // I-save kada may binabago sa form (typing, select, checkbox)
    document.getElementById('crmInquiryForm').addEventListener('input', () => { crmSaveDraft(); crmUpdateSummary(); });
    document.getElementById('crmInquiryForm').addEventListener('change', () => { crmSaveDraft(); crmUpdateSummary(); });

    // I-trigger ang toast base sa flash data mula sa PHP session, tapos i-restore ang draft
    if (crmToastData.error) {
        crmShowToast(crmToastData.error, 'error');
    }
    if (crmToastData.success) {
        const msg = crmToastData.controlNo
            ? `${crmToastData.success} Reference No. <strong>${crmToastData.controlNo}</strong>`
            : crmToastData.success;
        crmShowToast(msg, 'success');
        if (!CRM_EDIT) { try { localStorage.removeItem('crmInquiryDraft_v1'); } catch (e) { } }
    }

    crmLoadRegions().then(CRM_EDIT ? crmPrefillEdit : crmRestoreDraft);

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
                <span class="${CRM_CHIP_CLS}"><i class="fa-solid fa-check text-[10px]"></i>${escapeHtml(item.label)}</span>
            `;
            if (checkedValues.includes(item.label)) {
                label.querySelector('input').checked = true;
            }
            container.appendChild(label);
        });
        crmSaveDraft();
        crmUpdateSummary();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOptionsModal(); });

    // I-close ang modal pag na-click sa labas nito
    document.getElementById('optionsModal').addEventListener('click', function (e) {
        if (e.target === this) closeOptionsModal();
    });
</script>
<?php endif; ?>