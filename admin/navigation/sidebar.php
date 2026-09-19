<?php
$currentUrl = $_SERVER['REQUEST_URI'];
$role = $_SESSION['role'] ?? '';
$position = $_SESSION['position'] ?? POSITION_STAFF;
$isHead = $position === POSITION_HEAD;
$isStaff = $position === POSITION_STAFF;
$isCustodian = $position === POSITION_CUSTODIAN;
$isCustooAssistant = $position === POSITION_CUSTOASSISTANT;
$isSuperAdmin = $position === POSITION_SUPERADMIN;

function isActive(string $path): string
{
    global $currentUrl;
    $currentPath = rtrim(parse_url($currentUrl, PHP_URL_PATH), '/');
    $path = rtrim($path, '/');
    return $currentPath === $path || str_ends_with($currentPath, $path)
        ? 'bg-gray-300 text-black font-semibold'
        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 font-small';
}

$user_id = intval($_SESSION['account_id'] ?? 0);
$onlineResult = $conn->query("
    SELECT last_active FROM noblerole WHERE id = $user_id LIMIT 1
");
$onlineRow = $onlineResult->fetch_assoc();
$isOnline = $onlineRow && $onlineRow['last_active'] &&
    (time() - strtotime($onlineRow['last_active'])) < 60;

$sessionBranch = $_SESSION['branch'] ?? '';
$isMainBranch = false;
if ($sessionBranch !== '') {
    $branchCheckStmt = $conn->prepare("SELECT is_main FROM noblebranch WHERE name = ? LIMIT 1");
    $branchCheckStmt->bind_param("s", $sessionBranch);
    $branchCheckStmt->execute();
    $branchCheckStmt->bind_result($branchIsMainRaw);
    if ($branchCheckStmt->fetch()) {
        $isMainBranch = ((int) $branchIsMainRaw === 1);
    }
    $branchCheckStmt->close();
}

$roleColors = [
    ROLE_IT => '#2563EB', // blue
    ROLE_DESIGNER => '#0D9488', // teal
    ROLE_ACCOUNTING => '#16A34A', // green
    ROLE_CUTTING => '#CA8A04', // yellow/gold
    ROLE_OPERATIONS => '#EA580C', // orange
    ROLE_SALES => '#DC2626', // red
    ROLE_GRAPHIC => '#DB2777', // pink/magenta
    ROLE_HR => '#9333EA', // purple
    ROLE_SUPERADMIN => '#1F2937', // neutral dark gray
];
$currentRoleColor = $roleColors[$role] ?? '#6B7280'; // default gray fallback
?>

<style>
    nav {
        scrollbar-width: thin;
        /* Firefox */
        scrollbar-color: #d1d5db transparent;
        /* thumb / track */
    }

    nav::-webkit-scrollbar {
        width: 4px;
        /* Chrome/Edge/Safari */
    }

    nav::-webkit-scrollbar-track {
        background: transparent;
    }

    nav::-webkit-scrollbar-thumb {
        background-color: #d1d5db;
        border-radius: 9999px;
    }

    nav::-webkit-scrollbar-thumb:hover {
        background-color: #9ca3af;
    }

    /* ── Sidebar Desktop Transition ─────────────────────────── */
    #sidebar {
        transition: width 0.25s ease;
        overflow: hidden;
    }

    #sidebar.collapsed {
        width: 56px !important;
    }

    #sidebar.collapsed .sidebar-label,
    #sidebar.collapsed .sidebar-section-label,
    #sidebar.collapsed .sidebar-logo-text,
    #sidebar.collapsed .sidebar-divider,
    #sidebar.collapsed .user-text,
    #sidebar.collapsed #sidebar-notif-badge-wrap {
        display: none !important;
    }

    #sidebar.collapsed nav a,
    #sidebar.collapsed nav button {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }

    #sidebar.collapsed #sidebar-logo-area {
        align-items: center;
        padding-left: 0;
        padding-right: 0;
    }

    #sidebar.collapsed #sidebar-logo-row {
        justify-content: center;
    }

    #sidebar.collapsed #sidebar-user-block {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }

    #sidebar.collapsed #sidebar-user-block .logout-btn {
        display: none;
    }

    /* Desktop tooltips when collapsed */
    #sidebar.collapsed nav a,
    #sidebar.collapsed nav button {
        position: relative;
    }

    #sidebar.collapsed nav a:hover::after,
    #sidebar.collapsed nav button:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: calc(100% + 8px);
        top: 50%;
        transform: translateY(-50%);
        background: #1f2937;
        color: #fff;
        font-size: 11px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 6px;
        white-space: nowrap;
        pointer-events: none;
        z-index: 999;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
    }

    #sidebar.collapsed nav a:hover::before,
    #sidebar.collapsed nav button:hover::before {
        content: '';
        position: absolute;
        left: calc(100% + 3px);
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-right-color: #1f2937;
        pointer-events: none;
        z-index: 999;
    }

    #sidebar-toggle {
        transition: transform 0.25s ease;
    }

    #sidebar.collapsed #sidebar-toggle {
        transform: rotate(180deg);
    }

    /* ── Mobile Drawer ───────────────────────────────────────── */
    #mobile-drawer {
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #mobile-drawer.open {
        transform: translateX(0);
    }

    #mobile-overlay {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    #mobile-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }

    /* ── Bottom Nav Active State ─────────────────────────────── */
    .bottom-nav-item.active {
        color: #f97316;
    }

    .bottom-nav-item.active .bottom-nav-dot {
        display: block;
    }

    /* ── Safe area for notch phones ──────────────────────────── */
    #mobile-bottom-nav {
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
</style>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  DESKTOP SIDEBAR (hidden on mobile)                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<aside id="sidebar"
    class="hidden md:flex fixed top-0 left-0 h-screen w-56 bg-white border-r border-gray-100 flex-col z-50 shadow-sm">

    <!-- Logo -->
    <div id="sidebar-logo-area" class="px-4 py-4 border-b border-gray-100 flex flex-col gap-2">
        <div id="sidebar-logo-row" class="flex items-center gap-2">
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
                <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-8 w-auto object-contain">
                <button id="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Sidebar"
                    class="w-5 h-5 bg-gray-100 border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition-all">
                    <i class="fa-solid fa-chevron-left text-[8px]"></i>
                </button>
            </div>
            <div class="sidebar-divider-v w-px h-10 bg-gray-300 sidebar-logo-text"></div>
            <span
                class="sidebar-logo-text text-base font-bold text-gray-800 tracking-tight whitespace-nowrap leading-tight">
                Noble<span class="text-amber-500">Home</span><br>
                <span class="text-gray-400 font-normal text-xs">Department</span>
            </span>
        </div>

        <?php if (!empty($sessionBranch)): ?>
            <div class="sidebar-label flex items-center gap-1.5 px-1">

                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide truncate">
                    <?= htmlspecialchars($sessionBranch) ?>
                    <?php if ($isMainBranch): ?>
                        <span class="text-amber-600">(Main)</span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Nav Links -->
    <nav class="flex-1 px-2 py-4 space-y-0.5 overflow-y-auto overflow-x-hidden">
        <p class="sidebar-section-label text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">
            Main</p>

        <?php $role = $_SESSION['role'] ?? ''; ?>

        <?php if ($role === ROLE_ACCOUNTING): ?>

            <?php if ($isHead): ?>

                <a href="<?= BASE_URL ?>/generalannouncement" data-tooltip="General Announce"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/generalannouncement') ?>">
                    <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">General Announce</span>
                </a>
                <a href="<?= BASE_URL ?>/accountinggraph" data-tooltip="Dashboard"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountinggraph') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a href="<?= BASE_URL ?>/accounting" data-tooltip="Requests List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accounting') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Requests List</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-tooltip="Petty Cash"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodianpettycash') ?>">
                    <i class="fa-solid fa-coins w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Petty Cash</span>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/announcementdashboard" data-tooltip="Announce List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/announcementdashboard') ?>">
                    <i class="fa-solid fa-sign-hanging w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Announce List</span>
                </a>
                <a href="<?= BASE_URL ?>/announcement" data-tooltip="Announce List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/announcement') ?>">
                    <i class="fa-solid fa-pen-to-square w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Announcement</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingmonitoring" data-tooltip="Monitoring Project"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingmonitoring') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Monitoring Project</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingtracking" data-tooltip="Accounting Tracking"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingtracking') ?>">
                    <i class="fa-solid fa-timeline w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Tracking Req & Vouch</span>
                </a>
                <a href="<?= BASE_URL ?>/accountinggeneralsheet" data-tooltip="General Sheet"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountinggeneralsheet') ?>">
                    <i class="fa-solid fa-clipboard-list w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">General Sheet</span>
                </a>

                <a href="<?= BASE_URL ?>/cashvoucherdashboard" data-tooltip="Approval Cash Voucher"
                    class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-all <?= isActive('/cashvoucherdashboard') ?>">
                    <i class="fa-solid fa-ticket-simple w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Approval & Voucher</span>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">CRM Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/crmaccounting" data-tooltip="CRM List"
                    class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-all <?= isActive('/crmaccounting') ?>">
                    <i class="fa-solid fa-folder-tree w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">CRM List</span>
                </a>

            <?php endif; ?>

            <?php if ($isStaff): ?>
                <a href="<?= BASE_URL ?>/accountingstaffdashboard" data-tooltip="Dashboard Records"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingstaffdashboard') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard Records</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingstaff" data-tooltip="Acknowledge Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingstaff') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Acknowledge Request</span>
                </a>

                <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-circle-dollar-to-slot w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Cash Voucher Request</span>
                </a>

                 <a href="<?= BASE_URL ?>/projectmonitor" data-tooltip="Project Monitor"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/projectmonitor') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Project Monitor</span>
                </a>

                 <a href="<?= BASE_URL ?>/cashvoucherdashboard" data-tooltip="Approval Cash Voucher"
                    class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-all <?= isActive('/cashvoucherdashboard') ?>">
                    <i class="fa-solid fa-ticket-simple w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Approval Cash Voucher</span>
                </a>
            <?php endif; ?>

            <?php if ($isCustodian): ?>
                <a href="<?= BASE_URL ?>/accountingcustodiandashboard" data-tooltip="Dashboard Records"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodiandashboard') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard Records</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Cash Voucher Request</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-tooltip="Petty Cash"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodianpettycash') ?>">
                    <i class="fa-solid fa-coins w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Petty Cash</span>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/projectmonitor" data-tooltip="Project Monitor"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/projectmonitor') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Project Monitor</span>
                </a>

                <a href="<?= BASE_URL ?>/cashvoucherdashboard" data-tooltip="Approval Cash Voucher"
                    class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-all <?= isActive('/cashvoucherdashboard') ?>">
                    <i class="fa-solid fa-ticket-simple w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Approval Cash Voucher</span>
                </a>
            <?php endif; ?>


            <?php if ($isCustooAssistant): ?>
                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
                </div>

                 <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-circle-dollar-to-slot w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Cash Voucher Request</span>
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (in_array($role, [ROLE_HR])): ?>
            <a href="<?= BASE_URL ?>/humanresourcerequest" data-tooltip="Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-sm transition-all <?= isActive('/humanresourcerequest') ?>">
                <i class="fa-solid fa-address-book w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Request</span>
            </a>
        <?php endif; ?>


        <?php if ($role === ROLE_SALES): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/salesmarket" data-tooltip="Sales Market"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/salesmarket') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Budget Request list</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/crmsales" data-tooltip="CRM Main"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/crmsales') ?>">
                <i class="fa-solid fa-users w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">CRM Main</span>
            </a>

            <a href="<?= BASE_URL ?>/crmsaleslist" data-tooltip="CRM List"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/crmsaleslist') ?>">
                <i class="fa-solid fa-list-ol w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">CRM List</span>
            </a>
        <?php endif; ?>


        <?php if ($role === ROLE_DESIGNER): ?>
            <?php if ($isMainBranch): ?>
                <?php if ($isHead): ?>
                    <a href="<?= BASE_URL ?>/designer" data-tooltip="Dashboard"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-sm group transition-all <?= isActive('/designer') ?>">
                        <i class="fa-sharp fa-solid fa-chart-bar w-4 text-center text-sm flex-shrink-0"></i>
                        <span class="sidebar-label">Budget Request</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <div class="sidebar-section-label pt-4">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
            </div>

            <a href="<?= BASE_URL ?>/crmdesigner" data-tooltip="CRM Designer"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-sm group transition-all <?= isActive('/crmdesigner') ?>">
                <i class="fa-solid fa-folder-tree w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">CRM Designer</span>
            </a>

        <?php endif; ?>

        <?php if ($role === ROLE_SUPERADMIN): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/crm-main" data-tooltip="2D & Quotation Approval"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/crm-main') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">2D & Quotation</span>
                </a>
            <?php endif; ?>

        <a href="<?= BASE_URL ?>/crmewoodapproval" data-tooltip="E-Wood Management"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/crmewoodapproval') ?>">
                <i class="fa-solid fa-business-time w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">E-Wood Management</span>
            </a>

            <div class="sidebar-section-label pt-4">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Tracking</p>
            </div>

            <a href="<?= BASE_URL ?>/monitoring" data-tooltip="Monitoring"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/monitoring') ?>">
                <i class="fa-solid fa-chart-simple w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Monitoring & WorkBench</span>
            </a>
    

        <?php endif; ?>


        <?php if ($role === ROLE_CUTTING): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/crmcuttinglist" data-tooltip="2D & Quotation Approval"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/crmcuttinglist') ?>">
                    <i class="fa-solid fa-pen-ruler w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Cutting List</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/crmewood" data-tooltip="Wood Working"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/crmewood') ?>">
                <i class="fa-solid fa-file-circle-question w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">E-Woodfile</span>
            </a>

            <a href="<?= BASE_URL ?>/billofmaterial" data-tooltip="Bill of Material"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/billofmaterial') ?>">
                <i class="fa-solid fa-file-circle-plus w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Bill of Material</span>
            </a>

        <?php endif; ?>


        <?php if ($role === ROLE_IT): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/it" data-tooltip="Dashboard"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-sm group transition-all <?= isActive('/it') ?>">
                    <i class="fa-sharp fa-solid fa-chart-bar w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>
            <?php endif; ?>

            <a href="#" data-tooltip="Maintenance"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all">
                <i class="fa-solid fa-wrench w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Maintenance</span>
            </a>

            <div class="sidebar-section-label pt-4">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
            </div>

            <a href="<?= BASE_URL ?>/superad" data-tooltip="Manage Accounts"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/superad') ?>">
                <i class="fa-solid fa-user-plus w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Create Account</span>
            </a>

            <a href="<?= BASE_URL ?>/createaccount" data-tooltip="Account Management"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/createaccount') ?>">
                <i class="fa-solid fa-user-gear w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Account Management</span>
            </a>
        <?php endif; ?>


        <div class="sidebar-section-label pt-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Notifications</p>
        </div>

        <button onclick="toggleSidebarNotif()" data-tooltip="Notifications"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 text-gray-500 font-medium text-sm transition-all">
            <div class="relative w-4 flex items-center justify-center flex-shrink-0">
                <i class="fa-regular fa-bell text-sm"></i>
                <span id="sidebar-notif-badge"
                    class="hidden absolute -top-1.5 -right-1.5 min-w-[14px] h-3.5 bg-red-500 rounded-full text-[8px] font-bold text-white flex items-center justify-center px-0.5"></span>
            </div>
            <span id="sidebar-notif-badge-wrap"
                class="sidebar-label flex-1 text-left flex items-center gap-2">Notifications</span>
            <i class="fa-solid fa-chevron-right text-[9px] opacity-40 transition-transform duration-200 sidebar-label"
                id="sidebar-notif-chevron"></i>
        </button>

        <div class="sidebar-section-label pt-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Settings</p>
        </div>

        <?php if (($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF])) || $role === ROLE_SUPERADMIN): ?>

            <a href="<?= BASE_URL ?>/accountingsignatured" data-tooltip="Signatured Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingsignatured') ?>">
                <i class="fa-solid fa-signature w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Signatured Request</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- User Profile Block (Department + User Info, dikit) -->
    <div class="border-t border-gray-100">
        <?php if (!empty($_SESSION['role'])): ?>
            <div class="px-3 py-1.5 text-center overflow-hidden" style="background-color: <?= $currentRoleColor ?>15;">
                <span
                    class="text-[10px] font-bold uppercase tracking-wide inline-flex items-center justify-center gap-1 max-w-full"
                    style="color: <?= $currentRoleColor ?>;" title="<?= htmlspecialchars($_SESSION['role']) ?>">
                    <span class="truncate"><?= htmlspecialchars($_SESSION['role']) ?></span>
                    <i class="fa-solid fa-user-tag text-xs flex-shrink-0"></i>
                </span>
            </div>
        <?php endif; ?>

        <div id="sidebar-user-block"
            class="relative group flex items-center gap-3 px-3 py-3 hover:bg-gray-50 cursor-pointer transition-all overflow-visible">
            <div class="relative flex-shrink-0">
                <div class="w-7 h-7 rounded-full flex items-center justify-center"
                    style="background-color: <?= $currentRoleColor ?>1A; border: 1.5px solid <?= $currentRoleColor ?>;">
                    <i class="fa-sharp fa-solid fa-user-tie text-xs" style="color: <?= $currentRoleColor ?>;"></i>
                </div>
                <span id="presence-dot"
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </div>
            <div class="user-text flex-1 min-w-0">
                <p class="text-xs font-semibold truncate" style="color: <?= $currentRoleColor ?>;">
                    <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin' ?>
                </p>
                <p class="text-[10px] text-gray-400 truncate">
                    <?= isset($_SESSION['position']) ? htmlspecialchars(ucfirst($_SESSION['position'])) . '  ' : '' ?>
                    <?php if (!empty($_SESSION['branch'])): ?>
                        &middot; <?= htmlspecialchars($_SESSION['branch']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/logout"
                class="logout-btn flex-shrink-0 text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
            </a>
        </div>
    </div>

</aside>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MOBILE TOP BAR (visible on mobile only)                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<header class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-100 shadow-sm">
    <div class="flex items-center justify-between px-4 h-14">

        <!-- Hamburger -->
        <button onclick="openMobileDrawer()"
            class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-600">
            <i class="fa-solid fa-bars text-base"></i>
        </button>

        <!-- Logo center -->
        <div class="flex items-center gap-2">
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-7 w-auto object-contain">
            <span class="text-sm font-bold text-gray-800">Noble<span class="text-amber-500">Home</span></span>
        </div>

        <!-- Right: Notif + Avatar -->
        <div class="flex items-center gap-2">
            <button onclick="toggleSidebarNotif()"
                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-600 relative">
                <i class="fa-regular fa-bell text-base"></i>
                <span id="mobile-notif-badge"
                    class="hidden absolute top-1 right-1 min-w-[14px] h-3.5 bg-red-500 rounded-full text-[8px] font-bold text-white flex items-center justify-center px-0.5"></span>
            </button>
            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center relative">
                <i class="fa-sharp fa-solid fa-user-tie text-black text-xs"></i>
                <span
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </div>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MOBILE DRAWER                                             -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="mobile-overlay" onclick="closeMobileDrawer()"
    class="md:hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px]"></div>

<div id="mobile-drawer" class="md:hidden fixed top-0 left-0 h-screen w-72 bg-white z-[70] flex flex-col shadow-2xl">

    <!-- Drawer Header -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="NobleHome Logo" class="h-8 w-auto object-contain">
            <span class="text-base font-bold text-gray-800 leading-tight">
                Noble<span class="text-amber-500">Home</span><br>
                <span class="text-gray-400 font-normal text-xs">Department</span>
            </span>
        </div>
        <button onclick="closeMobileDrawer()"
            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition text-gray-400">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- Drawer User Block -->
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center gap-3">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center">
                    <i class="fa-sharp fa-solid fa-user-tie text-black text-sm"></i>
                </div>
                <span
                    class="absolute -bottom-0.5 -right-0.5 w-3 h-3 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">
                    <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin' ?>
                </p>
                <p class="text-[11px] text-gray-400">
                    <?= isset($_SESSION['position']) ? htmlspecialchars(ucfirst($_SESSION['position'])) : '' ?>
                    <span
                        class="ml-1.5 inline-flex items-center gap-1 <?= $isOnline ? 'text-green-500' : 'text-gray-400' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?>"></span>
                        <?= $isOnline ? 'Online' : 'Away' ?>
                    </span>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/logout"
                class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition"
                title="Logout">
                <i class="fa-solid fa-right-from-bracket text-sm"></i>
            </a>
        </div>
    </div>

    <!-- Drawer Nav -->
    <nav class="flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2 mt-1">Main</p>

        <?php $role = $_SESSION['role'] ?? ''; ?>

        <?php if ($role === ROLE_ACCOUNTING): ?>
            <a href="<?= BASE_URL ?>/generalannouncement" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/generalannouncement') ?>">
                <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                General Announce
            </a>

            <?php if ($isHead): ?>
                <a href="<?= BASE_URL ?>/accountinggraph" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountinggraph') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    Dashboard
                </a>
                <a href="<?= BASE_URL ?>/accounting" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accounting') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center text-sm flex-shrink-0"></i>
                    Requests List
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodianpettycash') ?>">
                    <i class="fa-solid fa-coins w-4 text-center text-sm flex-shrink-0"></i>
                    Petty Cash
                </a>
            <?php endif; ?>

            <?php if ($isStaff): ?>
                <a href="<?= BASE_URL ?>/accountingstaffdashboard" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingstaffdashboard') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    Dashboard Records
                </a>
                <a href="<?= BASE_URL ?>/accountingstaff" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingstaff') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-sm flex-shrink-0"></i>
                    Acknowledge Request
                </a>
            <?php endif; ?>

            <?php if ($isCustodian): ?>
                <a href="<?= BASE_URL ?>/accountingcustodiandashboard" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodiandashboard') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    Dashboard Records
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodian" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-sm flex-shrink-0"></i>
                    Cash Voucher Request
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodianpettycash') ?>">
                    <i class="fa-solid fa-coins w-4 text-center text-sm flex-shrink-0"></i>
                    Petty Cash
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_HR])): ?>
            <a href="<?= BASE_URL ?>/humanresource" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/humanresource') ?>">
                <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/humanresourcerequest" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/humanresourcerequest') ?>">
                <i class="fa-solid fa-address-book w-4 text-center text-sm flex-shrink-0"></i>
                Request
            </a>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_IT])): ?>
            <a href="<?= BASE_URL ?>/it" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/informationtech') ?>">
                <i class="fa-sharp fa-solid fa-chart-bar w-4 text-center text-sm flex-shrink-0"></i>
                Dashboard
            </a>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_SALES])): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/salesmarket" onclick="closeMobileDrawer()"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/salesmarket') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    Budget Request list
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/crm-main" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/crm-main') ?>">
                <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                CRM Main
            </a>
        <?php endif; ?>

        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2 mt-4">Manage</p>

        <?php if ($isStaff): ?>
            <a href="<?= BASE_URL ?>/accountingstaffannouncement" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingstaffannouncement') ?>">
                <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                Insert-Announcement
            </a>
        <?php endif; ?>

        <?php if ($isCustooAssistant): ?>
            <a href="<?= BASE_URL ?>/accountingcustodianassistant" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodianassistant') ?>">
                <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                Insert-Announcement
            </a>
        <?php endif; ?>

        <?php if ($isCustodian): ?>
            <a href="<?= BASE_URL ?>/announcementcustodian" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/announcementcustodian') ?>">
                <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                Insert-Announcement
            </a>
            <a href="<?= BASE_URL ?>/projectmonitor" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/projectmonitor') ?>">
                <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                Project Monitor
            </a>
        <?php endif; ?>

        <?php if ($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD])): ?>
            <a href="<?= BASE_URL ?>/announcementdashboard" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/announcementdashboard') ?>">
                <i class="fa-solid fa-sign-hanging w-4 text-center text-sm flex-shrink-0"></i>
                Announce List
            </a>
            <a href="<?= BASE_URL ?>/accountingmonitoring" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingmonitoring') ?>">
                <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                Monitoring Project
            </a>
            <a href="<?= BASE_URL ?>/accountingtracking" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingtracking') ?>">
                <i class="fa-solid fa-timeline w-4 text-center text-sm flex-shrink-0"></i>
                Tracking Req & Vouch
            </a>
            <a href="<?= BASE_URL ?>/accountinggeneralsheet" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountinggeneralsheet') ?>">
                <i class="fa-solid fa-clipboard-list w-4 text-center text-sm flex-shrink-0"></i>
                General Sheet
            </a>
        <?php endif; ?>

        <?php if ($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT])): ?>
            <a href="<?= BASE_URL ?>/cashvoucherdashboard" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/cashvoucherdashboard') ?>">
                <i class="fa-solid fa-ticket-simple w-4 text-center text-sm flex-shrink-0"></i>
                Approval Cash Voucher
            </a>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_HR])): ?>
            <a href="<?= BASE_URL ?>/superad" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/superad') ?>">
                <i class="fa-solid fa-user-plus w-4 text-center text-sm flex-shrink-0"></i>
                Manage Accounts
            </a>
        <?php endif; ?>

        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2 mt-4">Settings</p>

        <?php if ($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF])): ?>
            <a href="<?= BASE_URL ?>/accountingsignatured" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingsignatured') ?>">
                <i class="fa-solid fa-signature w-4 text-center text-sm flex-shrink-0"></i>
                Signatured Request
            </a>
        <?php endif; ?>

    </nav>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SHARED: Notification Overlay + Panel                      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="notif-overlay" onclick="toggleSidebarNotif()" class="hidden fixed inset-0 z-40 bg-black/20"></div>

<div id="notif-panel"
    class="fixed top-0 z-50 h-screen w-72 bg-white border-r border-gray-100 shadow-xl flex flex-col transition-all duration-300 ease-in-out"
    style="right: -288px; left: auto; border-r: none; border-left: 1px solid #f3f4f6;">

    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <i class="fa-regular fa-bell text-gray-600 text-sm"></i>
            <span class="text-sm font-bold text-gray-800">Notifications</span>
            <span id="notif-panel-badge"
                class="hidden bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"></span>
        </div>
        <button onclick="toggleSidebarNotif()" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50">
        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Recent</span>
        <button onclick="sidebarMarkAllRead()"
            class="text-[10px] text-orange-500 hover:text-orange-600 font-semibold transition-colors">Mark all
            read</button>
    </div>

    <div id="sidebar-notif-list" class="overflow-y-auto divide-y divide-gray-100"
        style="max-height: calc(100vh - 120px);">
        <div class="px-4 py-8 text-center text-gray-400 text-[11px]">
            <i class="fa-regular fa-bell mb-2 block text-2xl"></i>
            No notifications
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  SCRIPTS                                                   -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
    var CURRENT_USER_ID = <?= intval($_SESSION['account_id'] ?? 0) ?>;
</script>
<script src="<?= BASE_URL ?>/js/global-notif.js"></script>

<script>
    const BASE_URL = '<?= BASE_URL ?>';

    // Auto-cleanup expired announcements — runs on every page load
    fetch('<?= BASE_URL ?>/cleanupannouncements', { method: 'POST' });

    setInterval(() => {
        fetch('<?= BASE_URL ?>/cleanupannouncements', { method: 'POST' });
    }, 3000);

    // ── Mobile Drawer ─────────────────────────────────────────
    function openMobileDrawer() {
        document.getElementById('mobile-drawer').classList.add('open');
        document.getElementById('mobile-overlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileDrawer() {
        document.getElementById('mobile-drawer').classList.remove('open');
        document.getElementById('mobile-overlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close drawer on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) closeMobileDrawer();
    });

    // ── Desktop Sidebar Collapse ──────────────────────────────
    const SIDEBAR_KEY = 'sidebar_collapsed';
    let sidebarCollapsed = localStorage.getItem(SIDEBAR_KEY) === 'true';

    // User tooltip (desktop only)
    const userTooltip = document.createElement('div');
    userTooltip.id = 'user-tooltip';
    userTooltip.className = 'fixed z-[9999] hidden pointer-events-none';
    userTooltip.innerHTML = `
        <div style="display:flex;align-items:center;gap:0;">
            <div style="width:0;height:0;border-top:6px solid transparent;border-bottom:6px solid transparent;border-right:6px solid #111827;flex-shrink:0;"></div>
            <div style="background:#111827;color:white;border-radius:8px;padding:8px 12px;white-space:nowrap;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $isOnline ? '#4ade80' : '#9ca3af' ?>;flex-shrink:0;"></span>
                    <span style="font-size:11px;font-weight:600;color:<?= $isOnline ? '#4ade80' : '#9ca3af' ?>"><?= $isOnline ? 'Online' : 'Away' ?></span>
                </div>
                <p style="font-size:12px;font-weight:600;margin:0;"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                <p style="font-size:10px;color:#9ca3af;margin:2px 0 0;"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></p>
            </div>
        </div>`;
    document.body.appendChild(userTooltip);

    const userBlock = document.getElementById('sidebar-user-block');
    if (userBlock) {
        userBlock.addEventListener('mouseenter', () => {
            const rect = userBlock.getBoundingClientRect();
            userTooltip.style.visibility = 'hidden';
            userTooltip.classList.remove('hidden');
            const tipHeight = userTooltip.offsetHeight;
            let top = rect.top + (rect.height / 2) - (tipHeight / 2);
            if (top + tipHeight > window.innerHeight - 20) top = rect.bottom - tipHeight;
            userTooltip.style.left = (rect.right + 8) + 'px';
            userTooltip.style.top = top + 'px';
            userTooltip.style.visibility = 'visible';
        });
        userBlock.addEventListener('mouseleave', () => userTooltip.classList.add('hidden'));
    }

    function applySidebarState() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebarCollapsed ? sidebar.classList.add('collapsed') : sidebar.classList.remove('collapsed');
        const main = document.getElementById('main-content') || document.querySelector('main') || document.querySelector('.main-content');
        if (main) {
            // Desktop: shift main content; mobile: no margin (full width under top bar)
            if (window.innerWidth >= 768) {
                main.style.marginLeft = sidebarCollapsed ? '56px' : '224px';
            } else {
                main.style.marginLeft = '0';
            }
            main.style.transition = 'margin-left 0.25s ease';
        }
    }

    function toggleSidebar() {
        sidebarCollapsed = !sidebarCollapsed;
        localStorage.setItem(SIDEBAR_KEY, sidebarCollapsed);
        applySidebarState();
    }

    // Apply on load + on resize
    applySidebarState();
    window.addEventListener('resize', applySidebarState);

    // ── Heartbeat ─────────────────────────────────────────────
    let notifPanelOpen = false;
    let sidebarLastUnread = -1;

    function sendHeartbeat() {
        fetch('<?= BASE_URL ?>/heartbeat', { method: 'POST' })
            .then(() => setDot(true))
            .catch(() => setDot(false));
    }

    function setDot(online) {
        document.querySelectorAll('#presence-dot').forEach(dot => {
            dot.className = `absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 ${online ? 'bg-green-400' : 'bg-gray-300'} border-2 border-white rounded-full`;
        });
    }

    sendHeartbeat();
    setInterval(sendHeartbeat, 10_000);

    // ── Notifications ─────────────────────────────────────────
    function toggleSidebarNotif() {
        notifPanelOpen = !notifPanelOpen;
        const panel = document.getElementById('notif-panel');
        const overlay = document.getElementById('notif-overlay');
        const chevron = document.getElementById('sidebar-notif-chevron');

        if (notifPanelOpen) {
            panel.style.left = '0';
            overlay.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
            // Close mobile drawer if open
            closeMobileDrawer();
        } else {
            panel.style.left = '-288px';
            overlay.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
        }
    }


    function sidebarFetchNotifications() {
        fetch('<?= BASE_URL ?>/fetchnotifications')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('sidebar-notif-badge');
                const mobileBadge = document.getElementById('mobile-notif-badge');
                const panelBadge = document.getElementById('notif-panel-badge');
                const list = document.getElementById('sidebar-notif-list');
                const unread = data.filter(n => n.is_read == 0);

                if (unread.length > 0) {
                    const count = unread.length > 9 ? '9+' : unread.length;
                    [badge, mobileBadge].forEach(b => {
                        if (b) { b.textContent = count; b.classList.remove('hidden'); }
                    });
                    panelBadge.textContent = count;
                    panelBadge.classList.remove('hidden');
                    if (sidebarLastUnread !== -1 && unread.length > sidebarLastUnread) sidebarPlaySound();
                } else {
                    [badge, mobileBadge].forEach(b => { if (b) b.classList.add('hidden'); });
                    panelBadge.classList.add('hidden');
                }
                sidebarLastUnread = unread.length;

                if (!data.length) {
                    list.innerHTML = `<div class="px-4 py-8 text-center text-black text-[11px]">
                        <i class="fa-regular fa-bell mb-2 block text-2xl"></i>No notifications</div>`;
                    return;
                }

                list.innerHTML = data.map(n => {
                    const isPing = n.message.includes('');
                    const typeLabel = sidebarNotifTypeLabel(n.type);
                    let message = n.message;
                    if (n.control_no) {
                        message = message.replace(n.control_no,
                            `<span style="background:#fff3e0;color:#c2410c;font-weight:600;padding:1px 5px;border-radius:4px;font-size:10px;">${n.control_no}</span>`);
                    }
                    const requestDate = n.date_requested ?? '';
                    return `
<div onclick="sidebarClickNotif(${n.id}, '${n.link ?? ''}', ${n.request_id ?? 0}, '${requestDate}')"
     class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 ${n.is_read == 0
                            ? (isPing ? 'bg-red-50 border-l-[3px] border-red-400' : 'bg-orange-50 border-l-[3px] border-orange-400')
                            : 'border-l-[3px] border-transparent'}">
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold ${n.is_read == 0 ? 'text-gray-900' : 'text-gray-700'}">${n.control_no ?? ''}</p>
            ${typeLabel ? `<span class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded ${typeLabel === 'CRM' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'}">${typeLabel}</span>` : ''}
        </div>
        ${n.sender_name ? `<p class="text-[10px] ${isPing ? 'text-red-400' : 'text-orange-400'} font-medium mt-0.5">${n.sender_name}</p>` : ''}
        <p class="text-[11px] ${n.is_read == 0 ? 'text-gray-600' : 'text-gray-400'} leading-snug mt-0.5">${message}</p>
        <p class="text-[10px] text-gray-400 mt-1">${sidebarTimeAgo(n.created_at)}</p>
    </div>
    ${n.is_read == 0 ? `<div class="w-2 h-2 ${isPing ? 'bg-red-500' : 'bg-orange-500'} rounded-full mt-1.5 shrink-0"></div>` : ''}
</div>`;
                }).join('');
            })
            .catch(err => console.error('Notif error:', err));
    }

    function sidebarNotifTypeLabel(type) {
        if (type === 'crm_inquiry' || type === 'crm_2dquotation') return 'CRM';
        if (type === 'budget') return 'Budget';
        return null;
    }

    function sidebarClickNotif(id, link, requestId, requestDate) {
        console.log('link:', link, 'requestId:', requestId);
        fetch('<?= BASE_URL ?>/marknotificationsread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(() => {
            if (link) {
                const separator = link.includes('?') ? '&' : '?';
                let url = '<?= BASE_URL ?>' + link + separator + 'highlight=' + requestId;
                if (requestDate) url += '&date=' + requestDate;
                window.location.href = url;
            } else {
                sidebarFetchNotifications();
            }
        });
    }

    function sidebarMarkAllRead() {
        fetch('<?= BASE_URL ?>/marknotificationsread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: 0 })
        }).then(() => sidebarFetchNotifications());
    }

    function sidebarTimeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function sidebarPlaySound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) { }
    }

    sidebarFetchNotifications();
    setInterval(sidebarFetchNotifications, 5000);
</script>