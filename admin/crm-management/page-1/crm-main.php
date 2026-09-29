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
    .crm-doc .crm-step-panel {
        display: none;
        animation: crmFadeSlide .3s ease forwards;
    }

    .crm-doc .crm-step-panel.active {
        display: block;
    }

    @keyframes crmFadeSlide {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .crm-doc .crm-confirm-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        border-bottom: 1px solid #EEE8D9;
        font-size: .8125rem;
    }

    .crm-doc .crm-confirm-row:last-child {
        border-bottom: none;
    }

    .crm-doc .crm-confirm-label {
        color: #9CA3AF;
        white-space: nowrap;
    }

    .crm-doc .crm-confirm-value {
        color: #111827;
        font-weight: 500;
        text-align: right;
    }
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
    <div class="bg-[#FBF9F4] rounded-xl shadow-sm border border-[#E4DFD1] overflow-hidden">

        <!-- Document Header / Letterhead -->
        <div class="px-8 pt-7 pb-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>/icon/logo.png" class="h-11 w-11 object-contain" alt="logo">
                    <div>
                        <p class="text-amber-700 text-[11px] font-semibold tracking-[0.15em] uppercase mb-1">
                            CRM Inquiry Form
                        </p>
                        <h1 class="crm-serif text-gray-900 text-[26px] leading-tight font-semibold">
                            New Client Inquiry
                        </h1>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[10px] text-gray-400 uppercase tracking-[0.15em] mb-1">Reference No.</p>
                    <p class="text-gray-900 font-mono font-semibold text-sm">
                        <?= !empty($generatedControlNo)
                            ? htmlspecialchars($generatedControlNo)
                            : 'NHCC-CRM' . date('Y') . '-XXXXX' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Thick divider -->
        <div class="h-[2px] bg-gray-900 mx-8"></div>

        <!-- Document Meta Row -->
        <div
            class="px-8 py-3 border-b border-[#E4DFD1] flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
            <span>Date Filed: <span class="font-medium text-gray-700"><?= date('F d, Y') ?></span></span>
            <span>Branch: <span
                    class="font-medium text-gray-700"><?= htmlspecialchars($_SESSION['branch'] ?? '—') ?></span></span>
            <span>Filed by: <span
                    class="font-medium text-gray-700"><?= htmlspecialchars($currentSalesName) ?></span></span>
        </div>

        <!-- Step Indicator -->
        <div class="flex items-center gap-0 px-8 pt-6 pb-1">
            <?php
            $crmSteps = ['Client Info', 'Project Details', 'Assignment', 'Review'];
            foreach ($crmSteps as $i => $label):
                $n = $i + 1;
                ?>
                <div class="flex flex-col items-center">
                    <div id="crm-dot-<?= $n ?>"
                        class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 <?= $n === 1 ? 'border-amber-700 text-amber-700 bg-amber-50' : 'border-gray-300 text-gray-400 bg-white' ?> z-10 transition-all duration-300">
                        <?= $n ?>
                    </div>
                    <span class="text-[11px] text-gray-400 mt-1 whitespace-nowrap"><?= $label ?></span>
                </div>
                <?php if ($n < count($crmSteps)): ?>
                    <div id="crm-line-<?= $n ?>" class="flex-1 h-0.5 bg-gray-200 transition-all duration-300 mb-4">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Form Body -->
        <form method="POST" action="" id="crmInquiryForm" class="p-8 pt-4 space-y-6">

            <?php
            include $crmStepsPath . '/step1.php';
            include $crmStepsPath . '/step2.php';
            include $crmStepsPath . '/step3.php';
            include $crmStepsPath . '/step4.php';
            ?>

        </form>

        <!-- Document footer strip -->
        <div class="px-8 py-3 bg-[#F3EEE1] border-t border-[#E4DFD1] flex items-center justify-between">
            <p class="text-[11px] text-[#B0664A] italic">This record is maintained by the Sales Department.</p>
            <p class="text-[11px] text-gray-400 font-mono">
                <?= !empty($generatedControlNo)
                    ? htmlspecialchars($generatedControlNo)
                    : 'NHCC-CRM' . date('Y') . '-XXXXX' ?>
            </p>
        </div>
    </div>
</div>

<!-- Reusable Modal para sa Manage Options (CRUD) ng Measuring Space / Scope of Project -->
<div id="optionsModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 id="optionsModalTitle" class="text-sm font-semibold text-gray-800">Manage Options</h3>
            <button type="button" onclick="closeOptionsModal()"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex gap-2">
                <input id="optionsNewLabel" type="text" placeholder="Add new option"
                    class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-amber-600">
                <button type="button" onclick="addOption()"
                    class="px-3 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800">
                    Add
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

    function crmGoToStep(n) {
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
                alert(`Please fill out: ${label}`);
                el.focus();
                return;
            }
        }

        const contactEl = document.getElementById('crm_contact_number');
        if (!/^[0-9]{11}$/.test(contactEl.value.trim())) {
            alert('Contact Number must be exactly 11 digits (e.g. 09XXXXXXXXX).');
            contactEl.focus();
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
            alert('Please select Project Nature (Modular or Material Only).');
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
            dot.className = 'flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border-2 z-10 transition-all duration-300';
            if (i < crmCurrentStep) {
                dot.classList.add('border-amber-700', 'bg-amber-700', 'text-white');
            } else if (i === crmCurrentStep) {
                dot.classList.add('border-amber-700', 'text-amber-700', 'bg-amber-50');
            } else {
                dot.classList.add('border-gray-300', 'text-gray-400', 'bg-white');
            }
        }
        for (let i = 1; i < CRM_TOTAL_STEPS; i++) {
            const line = document.getElementById(`crm-line-${i}`);
            if (i < crmCurrentStep) {
                line.classList.remove('bg-gray-200');
                line.classList.add('bg-amber-700');
            } else {
                line.classList.remove('bg-amber-700');
                line.classList.add('bg-gray-200');
            }
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

        const rows = [
            ['Client Name', document.getElementById('crm_client_name').value || '—'],
            ['Address', addressParts.join(', ') || '—'],
            ['Contact Number', document.getElementById('crm_contact_number').value || '—'],
            ['Project Nature', crmSelectedNatureLabel()],
            ['Mode', crmSelectedModeLabel()],
            ['Type of Project', document.getElementById('crm_project_type').value || '—'],
            ['Scope of Project', crmCheckedValues('project_scope_checkboxes')],
            ['Measuring Space', crmCheckedValues('measuring_space_checkboxes')],

        ];

        document.getElementById('crm_review_content').innerHTML = rows.map(([label, val]) => `
            <div class="crm-confirm-row">
                <span class="crm-confirm-label">${label}</span>
                <span class="crm-confirm-value">${val}</span>
            </div>
        `).join('');
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

        localStorage.setItem(CRM_DRAFT_KEY, JSON.stringify(data));
    }

    async function crmRestoreDraft() {
        const raw = localStorage.getItem(CRM_DRAFT_KEY);
        if (!raw) return;

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
        localStorage.removeItem('crmInquiryDraft_v1');
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
        li.className = 'flex items-center justify-between gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2';
        li.innerHTML = `
            <span class="text-sm text-gray-700 flex-1 option-label">${escapeHtml(item.label)}</span>
            <button type="button" class="text-gray-400 hover:text-amber-700 text-xs font-medium">Edit</button>
            <button type="button" class="text-gray-400 hover:text-red-600 text-xs font-medium">Delete</button>
        `;
        const [editBtn, deleteBtn] = li.querySelectorAll('button');
        editBtn.addEventListener('click', () => editOption(item.id, li));
        deleteBtn.addEventListener('click', () => deleteOption(item.id));
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

        const res = await fetch('crmajax', { method: 'POST', body: formData });
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

    async function editOption(id, li) {
        const span = li.querySelector('.option-label');
        const currentLabel = span.textContent;
        const newLabel = prompt('Edit option:', currentLabel);
        if (newLabel === null) return;
        const trimmed = newLabel.trim();
        if (trimmed === '' || trimmed === currentLabel) return;

        const formData = new FormData();
        formData.append('action', 'edit');
        formData.append('type', currentOptionsType);
        formData.append('id', id);
        formData.append('label', trimmed);

        const res = await fetch('crmajax', { method: 'POST', body: formData });
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
        if (!confirm('Delete this option?')) return;

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
            container.innerHTML = '<p class="text-xs text-gray-400">Wala pang options. I-click ang gear para magdagdag.</p>';
            return;
        }
        data.items.forEach(item => {
            const label = document.createElement('label');
            label.className = 'inline-flex items-center gap-1.5 text-sm text-gray-700';
            label.innerHTML = `
                <input type="checkbox" name="${inputName}" value="${escapeHtml(item.label)}"
                    class="rounded border-gray-300 text-amber-700 focus:ring-amber-600">
                ${escapeHtml(item.label)}
            `;
            if (checkedValues.includes(item.label)) {
                label.querySelector('input').checked = true;
            }
            container.appendChild(label);
        });
        crmSaveDraft();
    }

    // I-close ang modal pag na-click sa labas nito
    document.getElementById('optionsModal').addEventListener('click', function (e) {
        if (e.target === this) closeOptionsModal();
    });
</script>