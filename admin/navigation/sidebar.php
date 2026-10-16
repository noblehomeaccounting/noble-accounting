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

// ── Badge counts (iisang source: sidebar-counts.php) ─────────
require_once ROOT_PATH . '/admin/navigation/sidebar-counts.php';
$counts = getSidebarCounts($conn, $role, $position, $user_id);

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

<?php include ROOT_PATH . '/admin/navigation/sidebar-loading.php'; ?>

<style>
    nav,
    #sidebar-notif-list {
        scrollbar-width: thin;
        scrollbar-color: #d1d5db transparent;
    }

    nav::-webkit-scrollbar,
    #sidebar-notif-list::-webkit-scrollbar {
        width: 4px;
    }

    nav::-webkit-scrollbar-track,
    #sidebar-notif-list::-webkit-scrollbar-track {
        background: transparent;
    }

    nav::-webkit-scrollbar-thumb,
    #sidebar-notif-list::-webkit-scrollbar-thumb {
        background-color: #d1d5db;
        border-radius: 9999px;
    }

    nav::-webkit-scrollbar-thumb:hover,
    #sidebar-notif-list::-webkit-scrollbar-thumb:hover {
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

    #user-card {
        opacity: 0;
        visibility: hidden;
        transform: translateX(-6px);
        transition: opacity .15s ease, transform .15s ease, visibility .15s;
    }

    #user-card.show {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
    }

    /* invisible bridge para hindi mawala ang card pag nilipat ang mouse */
    #user-card::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 0;
        bottom: 0;
        width: 12px;
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

    /* ── Badge pop animation kapag nagbago ang count ─────────── */
    @keyframes badge-pop {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.35);
        }

        100% {
            transform: scale(1);
        }
    }

    [data-badge].badge-pop {
        animation: badge-pop 0.35s ease;
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

    /* ── Watermark logo sa likod ng sidebar ───────────────────── */
    .sb-watermark {
        position: absolute;
        left: -46px;
        bottom: 64px;
        width: 250px;
        pointer-events: none;
        user-select: none;
        z-index: 0;
        opacity: .09;
        -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 80%, transparent 100%);
        mask-image: linear-gradient(180deg, transparent 0%, #000 45%, #000 80%, transparent 100%);
        transition: opacity .25s ease;
    }

    .sb-watermark svg {
        width: 100%;
        height: auto;
        display: block;
    }

    /* soft na orange glow sa ilalim */
    .sb-glow {
        position: absolute;
        left: -60px;
        bottom: 40px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(249, 115, 22, .10) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    #sidebar.collapsed .sb-watermark,
    #sidebar.collapsed .sb-glow {
        opacity: 0;
    }

    /* para nasa ibabaw ng watermark ang laman */
    .sb-layer {
        position: relative;
        z-index: 1;
    }
</style>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  DESKTOP SIDEBAR (hidden on mobile)                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<aside id="sidebar"
    class="hidden md:flex fixed top-0 left-0 h-screen w-56 bg-white border-r border-gray-100 flex-col z-50 shadow-sm">
    <?php $sbWmId = 'D'; ?>
    <!-- Watermark logo (design lang) -->
    <div class="sb-glow" aria-hidden="true"></div>
    <div class="sb-watermark" aria-hidden="true">
        <svg viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="sbWmGrad<?= $sbWmId ?? '' ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fb923c" />
                    <stop offset="1" stop-color="#c2410c" />
                </linearGradient>
            </defs>
            <g fill="url(#sbWmGrad<?= $sbWmId ?? '' ?>)">
                <polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
                <polygon
                    points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
            </g>
            <g fill="#1f2937">
                <polygon points="410,722 702,518 706,1088" />
                <polygon
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </g>
        </svg>
    </div>

    <!-- Logo -->
    <div id="sidebar-logo-area" class="sb-layer px-4 py-4 border-b border-gray-100 flex flex-col gap-2">
        <div id="sidebar-logo-row" class="flex items-center gap-2">
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-8 w-auto object-contain">
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
    <nav class="sb-layer flex-1 px-2 py-4 space-y-0.5 overflow-y-auto overflow-x-hidden">
        <p class="sidebar-section-label text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">
            Main</p>

        <?php $role = $_SESSION['role'] ?? ''; ?>

        <?php if ($role === ROLE_ACCOUNTING): ?>

            <?php if ($isHead): ?>

                <a href="<?= BASE_URL ?>/generalannouncement" data-tooltip="General Announce"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/generalannouncement') ?>">
                    <i class="fa-solid fa-bullhorn w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">General Announce</span>
                </a>
                <a href="<?= BASE_URL ?>/accountinggraph" data-tooltip="Dashboard"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountinggraph') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>/accounting" data-tooltip="Requests List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accounting') ?>">
                    <i class="fa-solid fa-list-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label flex-1">Requests List</span>
                    <?= sidebarBadge('requestPending', $counts['requestPending']) ?>
                </a>

                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-tooltip="Petty Cash"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountingcustodianpettycash') ?>">
                    <i class="fa-solid fa-coins w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Petty Cash</span>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/announcementdashboard" data-tooltip="Announce List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/announcementdashboard') ?>">
                    <i class="fa-solid fa-sign-hanging w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Announce List</span>
                </a>
                <a href="<?= BASE_URL ?>/announcement" data-tooltip="Announcement"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/announcement') ?>">
                    <i class="fa-solid fa-pen-to-square w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Announcement</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingmonitoring" data-tooltip="Monitoring Project"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountingmonitoring') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Monitoring Project</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingtracking" data-tooltip="Accounting Tracking"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountingtracking') ?>">
                    <i class="fa-solid fa-timeline w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Tracking Req & Vouch</span>
                </a>
                <a href="<?= BASE_URL ?>/accountinggeneralsheet" data-tooltip="General Sheet"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountinggeneralsheet') ?>">
                    <i class="fa-solid fa-clipboard-list w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">General Sheet</span>
                </a>

                <a href="<?= BASE_URL ?>/cashvoucherdashboard" data-tooltip="Approval Cash Voucher"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/cashvoucherdashboard') ?>">
                    <i class="fa-solid fa-ticket-simple w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label flex-1">Approval & Voucher</span>
                    <?= sidebarBadge('unpreparedVoucher', $counts['unpreparedVoucher']) ?>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">CRM Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/crmaccounting" data-tooltip="CRM List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/crmaccounting') ?>">
                    <i class="fa-solid fa-folder-tree w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">CRM List</span>
                </a>

                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">PO Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/accountingpurchaseorder" data-tooltip="Purchase Order List"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountingpurchaseorder') ?>">
                    <i class="fa-solid fa-folder-tree w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Purchase Order List</span>
                </a>

            <?php endif; ?>

            <?php if ($isStaff): ?>
                <a href="<?= BASE_URL ?>/accountingstaffdashboard" data-tooltip="Dashboard Records"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingstaffdashboard') ?>">
                    <i class="fa-solid fa-chart-column w-4 text-center text-sm flex-shrink-0"></i>

                    <span class="sidebar-label">Dashboard Records</span>
                </a>

                <a href="<?= BASE_URL ?>/accountingstaff" data-tooltip="Acknowledge Request"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingstaff') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label flex-1 text-xs whitespace-nowrap">Acknowledge Request</span>
                    <?= sidebarBadge('toReceive', $counts['toReceive']) ?>
                </a>

                <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-circle-dollar-to-slot w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label flex-1 text-xs whitespace-nowrap">Cash Voucher Request</span>
                    <?= sidebarBadge('toPrepare', $counts['toPrepare']) ?>
                </a>

                <a href="<?= BASE_URL ?>/projectmonitor" data-tooltip="Project Monitor"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/projectmonitor') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Project Monitor</span>
                </a>

                <a href="<?= BASE_URL ?>/cashvoucherdashboard" data-tooltip="Approval Cash Voucher"
                    class="flex items-center gap-2 px-2 py-2 rounded-lg text-sm transition-all <?= isActive('/cashvoucherdashboard') ?>">
                    <i class="fa-solid fa-money-bills w-4 text-center text-sm flex-shrink-0" style="color: rgb(0, 0, 0); "></i>
                    <span class="sidebar-label flex-1 text-xs whitespace-nowrap">Approval Cash Voucher</span>
                    <?= sidebarBadge('unpreparedVoucher', $counts['unpreparedVoucher']) ?>
                </a>

                <a href="<?= BASE_URL ?>/accountingstaffpurchase" data-tooltip="Receive Purchase Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingstaffpurchase') ?>">
                    <i class="fa-solid fa-folder-tree w-4 text-center text-xs flex-shrink-0"></i>
                    <span class="sidebar-label">Receive Purchase</span>
                </a>
            <?php endif; ?>

            <?php if ($isCustodian): ?>
                <a href="<?= BASE_URL ?>/accountingcustodiandashboard" data-tooltip="Dashboard Records"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodiandashboard') ?>">
                    <i class="fa-solid fa-chart-line w-4 text-center text-xs flex-shrink-0"></i>
                    <span class="sidebar-label">Dashboard Records</span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-list w-4 text-center text-xs flex-shrink-0"></i>
                    <span class="sidebar-label flex-1">Cash Vouch Req</span>
                    <?= sidebarBadge('toPrepare', $counts['toPrepare']) ?>
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
                    <span class="sidebar-label flex-1">Approval Cash Voucher</span>
                    <?= sidebarBadge('unpreparedVoucher', $counts['unpreparedVoucher']) ?>
                </a>

                <a href="<?= BASE_URL ?>/accountingcustodianpurchaseorder" data-tooltip="Purchase Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodianpurchaseorder') ?>">
                    <i class="fa-solid fa-file-signature w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Purchase Request</span>
                </a>
            <?php endif; ?>


            <?php if ($isCustooAssistant): ?>
                <div class="sidebar-section-label pt-4">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
                </div>

                <a href="<?= BASE_URL ?>/accountingcustodian" data-tooltip="Cash Voucher Request"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                    <i class="fa-solid fa-circle-dollar-to-slot w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label flex-1 text-xs whitespace-nowrap">Cash Voucher Request</span>
                    <?= sidebarBadge('toRelease', $counts['toRelease']) ?>
                </a>

                <a href="<?= BASE_URL ?>/accountingcustodianassistantpurchaseorder" data-tooltip="Purchase Request"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingcustodianassistantpurchaseorder') ?>">
                    <i class="fa-solid fa-file-circle-check w-4 text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Purchase Request</span>
                </a>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (in_array($role, [ROLE_HR])): ?>

            <a href="<?= BASE_URL ?>/humanresourcerequest" data-tooltip="Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs transition-all <?= isActive('/humanresourcerequest') ?>">
                <i class="fa-solid fa-address-book w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Budget Request</span>
            </a>

            <div class="sidebar-section-label pt-4">
                <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Announcement</p>
            </div>

            <a href="<?= BASE_URL ?>/hrannouncement" data-tooltip="Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs transition-all <?= isActive('/hrannouncement') ?>">
                <i class="fa-solid fa-pen-to-square w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Insert Announcement</span>
            </a>

            <a href="<?= BASE_URL ?>/payrollannouncement" data-tooltip="Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs transition-all <?= isActive('/payrollannouncement') ?>">
                <i class="fa-solid fa-pen-to-square w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Payroll Announcement</span>
            </a>

            <div class="sidebar-section-label pt-4">
                <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Profile & Account
                    Management</p>
            </div>

            <a href="<?= BASE_URL ?>/hrprofile" data-tooltip="Profile"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs transition-all <?= isActive('/hrprofile') ?>">
                <i class="fa-solid fa-id-card w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Insert ID Profile</span>
            </a>

            <a href="<?= BASE_URL ?>/listaccount" data-tooltip="List of Accounts"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs transition-all <?= isActive('/listaccount') ?>">
                <i class="fa-solid fa-users-gear w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">List of Accounts</span>
            </a>
        <?php endif; ?>


        <?php if ($role === ROLE_SALES): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/salesmarket" data-tooltip="Sales Market"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-xs group transition-all <?= isActive('/salesmarket') ?>">
                    <i class="fa-solid fa-wallet" style="color: rgba(24, 80, 8, 1); text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Budget Request list</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/crmsales" data-tooltip="CRM Main"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-xs group transition-all <?= isActive('/crmsales') ?>">
                <i class="fa-solid fa-users w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">CRM Main</span>
            </a>

            <a href="<?= BASE_URL ?>/crmsaleslist" data-tooltip="CRM List"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-xs group transition-all <?= isActive('/crmsaleslist') ?>">
                <i class="fa-solid fa-list-ol w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label flex-1">CRM List</span>
                <?= sidebarBadge('crmPending', $counts['crmPending']) ?>
            </a>

            <a href="<?= BASE_URL ?>/quotation" data-tooltip="Quotation"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-xs group transition-all <?= isActive('/quotation') ?>">
                <i class="fa-solid fa-file-pen" style="color: rgb(0, 0, 0);"></i>
                <span class="sidebar-label">Quotation</span>
            </a>
        <?php endif; ?>


        <?php if ($role === ROLE_DESIGNER): ?>
            <?php if ($isMainBranch): ?>
                <?php if ($isHead): ?>
                    <a href="<?= BASE_URL ?>/designer" data-tooltip="Dashboard"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/designer') ?>">
                        <i class="fa-solid fa-wallet" style="color: rgba(24, 80, 8, 1); text-center text-sm flex-shrink-0"></i>
                        <span class="sidebar-label">Budget Request</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <div class="sidebar-section-label pt-4">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Manage</p>
            </div>

            <a href="<?= BASE_URL ?>/crmdesigner" data-tooltip="CRM Designer"
                class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/crmdesigner') ?>">
                <i class="fa-solid fa-folder-tree" style="color: rgba(0, 0, 0, 1); text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">CRM Designer</span>
            </a>

            <?php if ($isHead): ?>
                <a href="<?= BASE_URL ?>/checkdesignerquotation" data-tooltip="Check Designer Quotation"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-xs group transition-all <?= isActive('/checkdesignerquotation') ?>">
                    <i class="fa-solid fa-file-pdf" style="color: rgba(0, 0, 0, 1); text-center text-sm flex-shrink-0"></i>
                    <span class="sidebar-label">Check Designer Quotation</span>
                </a>
            <?php endif; ?>

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

            <a href="<?= BASE_URL ?>/purchaseorderapproval" data-tooltip="Purchase Order"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-xs group transition-all <?= isActive('/purchaseorderapproval') ?>">
                <i class="fa-solid fa-file-signature w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Purchase Order Approval</span>
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


        <?php if ($role === ROLE_PO): ?>
            <?php if ($isMainBranch): ?>
                <a href="<?= BASE_URL ?>/crmpurchaseorder" data-tooltip="Dashboard"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg font-semibold text-sm group transition-all <?= isActive('/crmpurchaseorder') ?>">
                    <i class="fa-solid fa-file-signature w-4 text-center text-xs flex-shrink-0"></i>
                    <span class="sidebar-label">Purchase Order</span>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/clientseries" data-tooltip="Client Series"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/clientseries') ?>">
                <i class="fa-solid fa-user-gear w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Client Series</span>
            </a>

            <a href="<?= BASE_URL ?>/transmittalsummary" data-tooltip="Transmittal Summary"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 hover:text-gray-800 font-medium text-sm group transition-all <?= isActive('/transmittalsummary') ?>">
                <i class="fa-solid fa-truck-pickup w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Transmittal Summary</span>
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
                class="sidebar-label flex-1 text-left flex items-center gap-2 text-xs">Notifications</span>
            <i class="fa-solid fa-chevron-right text-[9px] opacity-40 transition-transform duration-200 sidebar-label"
                id="sidebar-notif-chevron"></i>
        </button>



        <?php if (($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF]))): ?>
            <div class="sidebar-section-label pt-4">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Followups Requests
                </p>
            </div>
            <a href="<?= BASE_URL ?>/accountingfollowups" data-tooltip="Follow-ups"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all <?= isActive('/accountingfollowups') ?>">
                <i class="fa-solid fa-file-arrow-up w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label flex-1">Follow-ups</span>
                <?= sidebarBadge('followupWaiting', $counts['followupWaiting']) ?>
            </a>
        <?php endif; ?>


        <div class="sidebar-section-label pt-4">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2">Settings</p>
        </div>

        <?php $translateVariant = 'desktop';
        include ROOT_PATH . '/admin/navigation/settings/translate.php'; ?>

        <?php if (($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF,])) || $role === ROLE_SUPERADMIN || $role === ROLE_PO || $role === ROLE_CUTTING): ?>

            <a href="<?= BASE_URL ?>/accountingsignatured" data-tooltip="Signatured Request"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all <?= isActive('/accountingsignatured') ?>">
                <i class="fa-solid fa-signature w-4 text-center text-sm flex-shrink-0"></i>
                <span class="sidebar-label">Signatured Request</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- User Profile Block -->
    <div class="sb-layer border-t border-gray-100 bg-white/90 backdrop-blur-sm">
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
            class="relative flex items-center gap-3 px-3 py-3 hover:bg-gray-50 cursor-pointer transition-all"
            onmouseenter="showUserCard()" onmouseleave="hideUserCard()" onclick="toggleUserCard()">
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
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </p>
                <p class="text-[10px] text-gray-400 truncate">
                    <?= htmlspecialchars(ucfirst($_SESSION['position'] ?? '')) ?>
                    <?php if (!empty($_SESSION['branch'])): ?>
                        &middot; <?= htmlspecialchars($_SESSION['branch']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <i class="user-text fa-solid fa-ellipsis-vertical text-gray-300 text-xs"></i>
        </div>
    </div>

    <!-- User Hover Card -->
    <div id="user-card" onmouseenter="showUserCard()" onmouseleave="hideUserCard()"
        class="hidden md:block fixed z-[80] w-64">
        <div class="bg-white rounded-xl border border-gray-100 shadow-2xl overflow-hidden">

            <!-- Header -->
            <div class="px-4 py-3" style="background-color: <?= $currentRoleColor ?>12;">
                <div class="flex items-center gap-1.5 mb-2">
                    <span id="user-card-dot" class="w-2 h-2 rounded-full bg-gray-300"></span>
                    <span id="user-card-status" class="text-[11px] font-semibold text-gray-400">Away</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                        style="background-color: <?= $currentRoleColor ?>1A; border: 1.5px solid <?= $currentRoleColor ?>;">
                        <i class="fa-sharp fa-solid fa-user-tie text-sm" style="color: <?= $currentRoleColor ?>;"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                        </p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <?= htmlspecialchars(ucfirst($_SESSION['position'] ?? '')) ?>
                            <?php if (!empty($sessionBranch)): ?>&middot;
                                <?= htmlspecialchars($sessionBranch) ?><?php endif; ?>
                        </p>
                        <p class="text-[10px] font-bold uppercase tracking-wide truncate"
                            style="color: <?= $currentRoleColor ?>;">
                            <?= htmlspecialchars($_SESSION['role'] ?? '') ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Menu (dito idagdag ang Profile etc.) -->
            <div class="p-1.5">
                <a href="<?= BASE_URL ?>/adminprofile"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs text-gray-600 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="fa-solid fa-user w-4 text-center"></i>
                    My Employee ID
                </a>

                <a href="<?= BASE_URL ?>/logout"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs text-gray-600 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                    Logout
                </a>
            </div>
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
            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-7 w-auto object-contain">
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
<!--  MOBILE DRAWER                                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="mobile-overlay" onclick="closeMobileDrawer()"
    class="md:hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px]"></div>

<div id="mobile-drawer"
    class="md:hidden fixed top-0 left-0 h-screen w-72 bg-white z-[70] flex flex-col shadow-2xl overflow-hidden">
    <?php $sbWmId = 'M'; ?>
    <!-- Watermark logo (design lang) -->
    <div class="sb-glow" aria-hidden="true"></div>
    <div class="sb-watermark" aria-hidden="true">
        <svg viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="sbWmGrad<?= $sbWmId ?? '' ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fb923c" />
                    <stop offset="1" stop-color="#c2410c" />
                </linearGradient>
            </defs>
            <g fill="url(#sbWmGrad<?= $sbWmId ?? '' ?>)">
                <polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
                <polygon
                    points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
            </g>
            <g fill="#1f2937">
                <polygon points="410,722 702,518 706,1088" />
                <polygon
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </g>
        </svg>
    </div>

    <!-- Drawer Header -->
    <div class="sb-layer flex items-center justify-between px-4 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2.5">
            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-8 w-auto object-contain">
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
    <div class="sb-layer px-4 py-3 border-b border-gray-100 bg-gray-50">
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
    <nav class="sb-layer flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

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
                    <span class="flex-1">Requests List</span>
                    <?= sidebarBadge('requestPending', $counts['requestPending']) ?>
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
                    <span class="flex-1">Acknowledge Request</span>
                    <?= sidebarBadge('toReceive', $counts['toReceive']) ?>
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
                    <span class="flex-1">Cash Voucher Request</span>
                    <?= sidebarBadge('toPrepare', $counts['toPrepare']) ?>
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

            <a href="<?= BASE_URL ?>/crmsaleslist" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/crmsaleslist') ?>">
                <i class="fa-solid fa-list-ol w-4 text-center text-sm flex-shrink-0"></i>
                <span class="flex-1">CRM List</span>
                <?= sidebarBadge('crmPending', $counts['crmPending']) ?>
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
            <a href="<?= BASE_URL ?>/accountingcustodian" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingcustodian') ?>">
                <i class="fa-solid fa-circle-dollar-to-slot w-4 text-center text-sm flex-shrink-0"></i>
                <span class="flex-1">Cash Voucher Request</span>
                <?= sidebarBadge('toRelease', $counts['toRelease']) ?>
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
                <span class="flex-1">Approval Cash Voucher</span>
                <?= sidebarBadge('unpreparedVoucher', $counts['unpreparedVoucher']) ?>
            </a>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_HR])): ?>
            <a href="<?= BASE_URL ?>/superad" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/superad') ?>">
                <i class="fa-solid fa-user-plus w-4 text-center text-sm flex-shrink-0"></i>
                Manage Accounts
            </a>
        <?php endif; ?>

        <?php if ($role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF])): ?>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest px-2 mb-2 mt-4">Followups Requests
            </p>
            <a href="<?= BASE_URL ?>/accountingfollowups" onclick="closeMobileDrawer()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= isActive('/accountingfollowups') ?>">
                <i class="fa-solid fa-file-arrow-up w-4 text-center text-sm flex-shrink-0"></i>
                <span class="flex-1">Follow-ups</span>
                <?= sidebarBadge('followupWaiting', $counts['followupWaiting']) ?>
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


<div id="notif-overlay" onclick="toggleSidebarNotif()" class="hidden fixed inset-0 z-40 bg-black/20"></div>

<div id="notif-panel"
    class="fixed top-0 z-50 h-screen bg-white border-r border-gray-100 shadow-xl flex flex-col transition-all duration-300 ease-in-out"
    style="width: 420px; max-width: 100vw; right: auto; left: -420px; border-left: none;">

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

<script>
    var CURRENT_USER_ID = <?= intval($_SESSION['account_id'] ?? 0) ?>;
</script>

<script src="<?= BASE_URL ?>/js/global-notif.js?v=<?= time() ?>"></script>

<?php include ROOT_PATH . '/admin/navigation/sidebar-script.php'; ?>