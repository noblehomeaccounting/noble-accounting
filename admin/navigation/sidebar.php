<?php
// sidebar.php — admin sidebar (desktop), mobile top bar + drawer, at notification panel
// Tailwind-only. Iisang listahan ng menu ($sbMenu) ang gamit ng desktop at mobile.
// ⚠️ Huwag palitan ang mga ID / state class (#sidebar.collapsed, #user-card.show, #mobile-drawer.open,
//    #mobile-overlay.open, [data-badge].badge-pop) — ginagamit ng sidebar-script.php at global-notif.js.

$currentUrl = $_SERVER['REQUEST_URI'];
$role = $_SESSION['role'] ?? '';
$position = $_SESSION['position'] ?? POSITION_STAFF;
$isHead = $position === POSITION_HEAD;
$isStaff = $position === POSITION_STAFF;
$isCustodian = $position === POSITION_CUSTODIAN;
$isCustooAssistant = $position === POSITION_CUSTOASSISTANT;
$isSuperAdmin = $position === POSITION_SUPERADMIN;

if (!function_exists('sbIsActive')) {
function sbIsActive(string $path): bool
{
    global $currentUrl;
    $currentPath = rtrim((string) parse_url($currentUrl, PHP_URL_PATH), '/');
    $path = rtrim($path, '/');
    return $path !== '' && ($currentPath === $path || str_ends_with($currentPath, $path));
}

// Naiwan para sa ibang file na tumatawag pa nito
function isActive(string $path): string
{
    return sbIsActive($path)
        ? 'bg-amber-50 text-amber-900 font-semibold shadow-[inset_3px_0_0_#b45309]'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 font-medium';
}
}

$user_id = intval($_SESSION['account_id'] ?? 0);
$onlineResult = $conn->query("SELECT last_active FROM noblerole WHERE id = $user_id LIMIT 1");
$onlineRow = $onlineResult ? $onlineResult->fetch_assoc() : null;
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
    ROLE_IT => '#2563EB',
    ROLE_DESIGNER => '#0D9488',
    ROLE_ACCOUNTING => '#16A34A',
    ROLE_CUTTING => '#CA8A04',
    ROLE_OPERATIONS => '#EA580C',
    ROLE_SALES => '#DC2626',
    ROLE_GRAPHIC => '#DB2777',
    ROLE_HR => '#9333EA',
    ROLE_SUPERADMIN => '#1F2937',
];
$currentRoleColor = $roleColors[$role] ?? '#6B7280';

$sbUserName = $_SESSION['username'] ?? 'Admin';
$sbPositionLabel = ucfirst($_SESSION['position'] ?? '');
$sbInitial = mb_strtoupper(mb_substr(trim($sbUserName) ?: 'A', 0, 1));

// ═══════════════════════════════════════════════════════════════
//  MENU — [section label => [items]]  (walang laman = hindi ipapakita)
//  item: [href, icon, label, badgeKey|null, tooltip|null, activePath|null]
// ═══════════════════════════════════════════════════════════════
$sbMenu = [];
$add = function (string $section, array $item) use (&$sbMenu) {
    $sbMenu[$section][] = $item;
};
$I = fn($href, $icon, $label, $badge = null, $tip = null, $active = null) =>
    ['href' => $href, 'icon' => $icon, 'label' => $label, 'badge' => $badge, 'tip' => $tip ?? $label, 'active' => $active ?? $href];

if ($role === ROLE_ACCOUNTING) {
    if ($isHead) {
        $add('Main', $I('/generalannouncement', 'fa-solid fa-bullhorn', 'General Announce'));
        $add('Main', $I('/accountinggraph', 'fa-solid fa-chart-line', 'Dashboard'));
        $add('Main', $I('/accounting', 'fa-solid fa-list-check', 'Requests List', 'requestPending'));
        $add('Main', $I('/accountingcustodianpettycash', 'fa-solid fa-coins', 'Petty Cash'));

        $add('Manage', $I('/announcementdashboard', 'fa-solid fa-sign-hanging', 'Announce List'));
        $add('Manage', $I('/announcement', 'fa-solid fa-pen-to-square', 'Announcement'));
        $add('Manage', $I('/accountingmonitoring', 'fa-solid fa-file-circle-check', 'Monitoring Project'));
        $add('Manage', $I('/accountingtracking', 'fa-solid fa-timeline', 'Tracking Req & Vouch', null, 'Accounting Tracking'));
        $add('Manage', $I('/accountinggeneralsheet', 'fa-solid fa-clipboard-list', 'General Sheet'));
        $add('Manage', $I('/cashvoucherdashboard', 'fa-solid fa-ticket-simple', 'Approval & Voucher', 'unpreparedVoucher', 'Approval Cash Voucher'));

        $add('CRM Manage', $I('/crmaccounting', 'fa-solid fa-folder-tree', 'CRM List', 'depositWaiting'));
        $add('PO Manage', $I('/accountingpurchaseorder', 'fa-solid fa-cart-flatbed', 'Purchase Order List', 'poToReview'));
        $add('Billing', $I('/accountingbilling', 'fa-solid fa-file-invoice-dollar', 'Progress Billing', 'billingPending', 'Billing'));
    }
    if ($isStaff) {
        $add('Main', $I('/accountingstaffdashboard', 'fa-solid fa-chart-column', 'Dashboard Records'));
        $add('Main', $I('/accountingstaff', 'fa-solid fa-list', 'Acknowledge Request', 'toReceive'));
        $add('Main', $I('/accountingcustodian', 'fa-solid fa-circle-dollar-to-slot', 'Cash Voucher Request', 'toPrepare'));
        $add('Main', $I('/projectmonitor', 'fa-solid fa-file-circle-check', 'Project Monitor'));
        $add('Main', $I('/cashvoucherdashboard', 'fa-solid fa-money-bills', 'Approval Cash Voucher', 'unpreparedVoucher'));
        $add('Main', $I('/accountingstaffpurchase', 'fa-solid fa-folder-tree', 'Receive Purchase', null, 'Receive Purchase Request'));
    }
    if ($isCustodian) {
        $add('Main', $I('/accountingcustodiandashboard', 'fa-solid fa-chart-line', 'Dashboard Records'));
        $add('Main', $I('/accountingcustodian', 'fa-solid fa-list', 'Cash Vouch Req', 'toPrepare', 'Cash Voucher Request'));
        $add('Main', $I('/accountingcustodianpettycash', 'fa-solid fa-coins', 'Petty Cash'));

        $add('Manage', $I('/projectmonitor', 'fa-solid fa-file-circle-check', 'Project Monitor'));
        $add('Manage', $I('/cashvoucherdashboard', 'fa-solid fa-ticket-simple', 'Approval Cash Voucher', 'unpreparedVoucher'));
        $add('Manage', $I('/accountingcustodianpurchaseorder', 'fa-solid fa-file-signature', 'Purchase Request'));
    }
    if ($isCustooAssistant) {
        $add('Manage', $I('/accountingcustodian', 'fa-solid fa-circle-dollar-to-slot', 'Cash Voucher Request', 'toRelease'));
        $add('Manage', $I('/accountingcustodianassistantpurchaseorder', 'fa-solid fa-file-circle-check', 'Purchase Request'));
    }
}

if ($role === ROLE_HR) {
    $add('Main', $I('/humanresourcerequest', 'fa-solid fa-address-book', 'Budget Request', null, 'Request'));
    $add('Announcement', $I('/hrannouncement', 'fa-solid fa-pen-to-square', 'Insert Announcement'));
    $add('Announcement', $I('/payrollannouncement', 'fa-solid fa-money-check-dollar', 'Payroll Announcement'));
    $add('Profile & Accounts', $I('/hrprofile', 'fa-solid fa-id-card', 'Insert ID Profile', null, 'Profile'));
    $add('Profile & Accounts', $I('/listaccount', 'fa-solid fa-users-gear', 'List of Accounts'));
}

if ($role === ROLE_SALES) {
    if ($isMainBranch) {
        $add('Main', $I('/salesmarket', 'fa-solid fa-wallet', 'Budget Request list', null, 'Sales Market'));
    }
    $add('Main', $I('/crmsales', 'fa-solid fa-users', 'CRM Main'));
    $add('Main', $I('/crmsaleslist', 'fa-solid fa-list-ol', 'CRM List', 'crmPending'));
    $add('Main', $I('/quotation', 'fa-solid fa-file-pen', 'Quotation'));
}

if ($role === ROLE_DESIGNER) {
    if ($isMainBranch && $isHead) {
        $add('Main', $I('/designer', 'fa-solid fa-wallet', 'Budget Request', null, 'Dashboard'));
    }
    $add('Manage', $I('/crmdesigner', 'fa-solid fa-compass-drafting', 'CRM Designer'));
    if ($isHead) {
        $add('Manage', $I('/checkdesignerquotation', 'fa-solid fa-file-pdf', 'Check Designer Quotation', 'designerReview'));
    }
}

if ($role === ROLE_SUPERADMIN) {
    if ($isMainBranch) {
        $add('Main', $I('/crm-main', 'fa-solid fa-file-circle-check', '2D & Quotation', 'finalReview', '2D & Quotation Approval'));
    }
    $add('Main', $I('/crmewoodapproval', 'fa-solid fa-business-time', 'E-Wood Management', 'ewoodPending'));

    $add('Tracking', $I('/monitoring', 'fa-solid fa-chart-simple', 'Monitoring & WorkBench', null, 'Monitoring'));
    $add('Tracking', $I('/purchaseorderapproval', 'fa-solid fa-file-signature', 'Purchase Order Approval', null, 'Purchase Order'));
    $add('Tracking', $I('/superadmin-dr', 'fa-solid fa-truck', 'Delivery Receipt'));
    $add('Tracking', $I('/superadminlabor', 'fa-solid fa-helmet-safety', 'Labor Quotation'));
    $add('Tracking', $I('/superadminbilling', 'fa-solid fa-file-invoice-dollar', 'Billing'));
    $add('Tracking', $I('/superadminturnover', 'fa-solid fa-key', 'Turnover Letter'));
}

if ($role === ROLE_CUTTING) {
    if ($isMainBranch) {
        $add('Main', $I('/crmcuttinglist', 'fa-solid fa-pen-ruler', 'Cutting List', null, '2D & Quotation Approval'));
    }
    $add('Main', $I('/crmewood', 'fa-solid fa-tree', 'E-Woodfile', 'ewoodTodo', 'Wood Working'));
}

if ($role === ROLE_IT) {
    if ($isMainBranch) {
        $add('Main', $I('/it', 'fa-solid fa-chart-bar', 'Dashboard'));
    }
    $add('Main', $I('#', 'fa-solid fa-wrench', 'Maintenance', null, null, ''));
    $add('Manage', $I('/superad', 'fa-solid fa-user-plus', 'Create Account', null, 'Manage Accounts'));
    $add('Manage', $I('/createaccount', 'fa-solid fa-user-gear', 'Account Management'));
}

if ($role === ROLE_PO) {
    if ($isMainBranch) {
        $add('Main', $I('/crmpurchaseorder', 'fa-solid fa-file-signature', 'Purchase Order', null, 'Dashboard'));
    }
    $add('Main', $I('/clientseries', 'fa-solid fa-user-gear', 'Client Series'));
    $add('Main', $I('/transmittalsummary', 'fa-solid fa-truck-pickup', 'Transmittal Summary'));
}

if ($role === ROLE_PC) {
    if ($isMainBranch) {
        $add('Main', $I('/coordinator', 'fa-solid fa-helmet-safety', 'Coordinator'));
    }
    $add('Main', $I('/coordinatorlabor', 'fa-solid fa-user-gear', 'Labor Quotation'));
    $add('Main', $I('/coordinatortimeline', 'fa-solid fa-chart-gantt', 'Timeline'));
    $add('Progress Billing', $I('/coordinatorbilling', 'fa-solid fa-file-invoice-dollar', 'Billing'));
    $add('Punch list & Turnover', $I('/coordinatorturnover', 'fa-solid fa-key', 'Turnover Letter'));
}

// Para sa lahat ng posisyon ng Accounting
$sbAccountingAll = $role === ROLE_ACCOUNTING && in_array($position, [POSITION_HEAD, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT, POSITION_STAFF]);
$sbFollowups = $sbAccountingAll
    ? [$I('/accountingfollowups', 'fa-solid fa-file-arrow-up', 'Follow-ups', 'followupWaiting')]
    : [];
$sbSettings = ($sbAccountingAll || $role === ROLE_SUPERADMIN || $role === ROLE_PO || $role === ROLE_CUTTING)
    ? [$I('/accountingsignatured', 'fa-solid fa-signature', 'Signatured Request')]
    : [];

// ═══════════════════════════════════════════════════════════════
//  RENDER HELPERS
// ═══════════════════════════════════════════════════════════════

// Kapag naka-collapse: icon lang, nakagitna (ang tooltip ay #sb-tip sa ibaba — fixed para hindi maputol ng scroll)
if (!defined('SB_TIP')) define('SB_TIP', 'group-[.collapsed]/sb:justify-center group-[.collapsed]/sb:px-0');

if (!function_exists('sbItem')) {
function sbItem(array $it, bool $mobile = false): string
{
    global $counts;
    $on = $it['active'] !== '' && sbIsActive($it['active']);
    $href = $it['href'] === '#' ? '#' : BASE_URL . $it['href'];
    $state = $on
        ? 'bg-amber-50 text-amber-900 font-semibold shadow-[inset_3px_0_0_#b45309]'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 font-medium';
    $iconCls = $on ? 'text-amber-700' : 'text-gray-400 group-hover/it:text-gray-600';
    $badge = $it['badge'] ? sidebarBadge($it['badge'], $counts[$it['badge']] ?? 0) : '';
    $label = htmlspecialchars($it['label']);

    if ($mobile) {
        return '<a href="' . htmlspecialchars($href) . '" onclick="closeMobileDrawer()"'
            . ' class="group/it flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] transition ' . $state . '"'
            . ($on ? ' aria-current="page"' : '') . '>'
            . '<i class="' . $it['icon'] . ' w-4 text-center text-sm shrink-0 ' . $iconCls . '"></i>'
            . '<span class="flex-1 truncate">' . $label . '</span>' . $badge . '</a>';
    }

    return '<a href="' . htmlspecialchars($href) . '" data-tooltip="' . htmlspecialchars($it['tip']) . '"'
        . ' class="group/it relative flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition ' . $state . ' ' . SB_TIP . '"'
        . ($on ? ' aria-current="page"' : '') . '>'
        . '<i class="' . $it['icon'] . ' w-4 text-center text-[13px] shrink-0 ' . $iconCls . '"></i>'
        . '<span class="sidebar-label flex-1 truncate group-[.collapsed]/sb:hidden">' . $label . '</span>'
        . ($badge ? '<span class="shrink-0 group-[.collapsed]/sb:absolute group-[.collapsed]/sb:-top-0.5 group-[.collapsed]/sb:right-0.5 group-[.collapsed]/sb:scale-75">' . $badge . '</span>' : '')
        . '</a>';
}

function sbSectionLabel(string $label, bool $first, bool $mobile = false): string
{
    if ($mobile) {
        return '<p class="px-3 ' . ($first ? 'pt-1' : 'pt-4') . ' pb-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400">' . htmlspecialchars($label) . '</p>';
    }
    return '<p class="sidebar-section-label px-3 ' . ($first ? 'pt-0.5' : 'pt-4') . ' pb-1.5 text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 truncate group-[.collapsed]/sb:hidden">' . htmlspecialchars($label) . '</p>'
        . ($first ? '' : '<div class="hidden group-[.collapsed]/sb:block mx-3 my-2.5 h-px bg-gray-200"></div>');
}

function sbWatermark(string $id): string
{
    return '<div class="sb-glow pointer-events-none absolute -left-[60px] bottom-10 w-[220px] h-[220px] rounded-full z-0 bg-[radial-gradient(circle,rgba(249,115,22,.10)_0%,transparent_70%)] transition-opacity group-[.collapsed]/sb:opacity-0" aria-hidden="true"></div>'
        . '<div class="sb-watermark pointer-events-none select-none absolute -left-[46px] bottom-16 w-[250px] z-0 opacity-[.09] transition-opacity group-[.collapsed]/sb:opacity-0 [-webkit-mask-image:linear-gradient(180deg,transparent_0%,#000_45%,#000_80%,transparent_100%)] [mask-image:linear-gradient(180deg,transparent_0%,#000_45%,#000_80%,transparent_100%)]" aria-hidden="true">'
        . '<svg class="block w-full h-auto" viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sbWmGrad' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fb923c"/><stop offset="1" stop-color="#c2410c"/></linearGradient></defs>'
        . '<g fill="url(#sbWmGrad' . $id . ')"><polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802"/><polygon points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568"/></g>'
        . '<g fill="#1f2937"><polygon points="410,722 702,518 706,1088"/><polygon points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568"/></g></svg></div>';
}
}

// Manipis na scrollbar + "pop" ng badge kapag nagbago ang bilang
$sbScroll = '[scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent] [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300 hover:[&::-webkit-scrollbar-thumb]:bg-gray-400';
$sbBadgePop = '[&_[data-badge].badge-pop]:animate-[bounce_.35s_ease]';
$sbHazard = 'bg-[repeating-linear-gradient(-45deg,#f59e0b_0_8px,#1f2937_8px_16px)]';
?>

<?php include ROOT_PATH . '/admin/navigation/sidebar-loading.php'; ?>

<!-- para mabuo ng Tailwind ang @keyframes bounce na gamit ng badge pop -->
<span class="hidden animate-bounce" aria-hidden="true"></span>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  DESKTOP SIDEBAR (hidden on mobile)                         -->
<!-- ═══════════════════════════════════════════════════════════ -->
<aside id="sidebar"
    class="group/sb hidden md:flex fixed top-0 left-0 h-screen w-56 [&.collapsed]:!w-14 bg-white border-r border-gray-200 flex-col z-50 shadow-sm overflow-hidden transition-[width] duration-[250ms] ease-in-out">
    <?= sbWatermark('D') ?>

    <!-- Logo -->
    <div id="sidebar-logo-area" class="relative z-[1] border-b border-gray-100">
        <div class="<?= $sbHazard ?> h-1"></div>
        <div class="px-3.5 pt-3.5 pb-3 group-[.collapsed]/sb:px-0">
            <div id="sidebar-logo-row" class="flex items-center gap-2.5 group-[.collapsed]/sb:flex-col group-[.collapsed]/sb:gap-2">
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-8 w-auto object-contain shrink-0">
                <span class="sidebar-logo-text min-w-0 leading-tight group-[.collapsed]/sb:hidden">
                    <span class="block text-[13px] font-extrabold tracking-tight text-gray-900 whitespace-nowrap">Noble<span class="text-amber-600">Home</span></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">Department</span>
                </span>
                <button id="sidebar-toggle" type="button" onclick="toggleSidebar()" title="Toggle sidebar" aria-label="Toggle sidebar"
                    class="ml-auto group-[.collapsed]/sb:ml-0 inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200 bg-white text-gray-400 hover:text-amber-700 hover:border-amber-300 transition-transform duration-[250ms] group-[.collapsed]/sb:rotate-180 shrink-0">
                    <i class="fa-solid fa-angles-left text-[9px]"></i>
                </button>
            </div>

            <?php if (!empty($sessionBranch)): ?>
                <div class="sidebar-label mt-3 flex items-center gap-1.5 rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1.5 group-[.collapsed]/sb:hidden" title="Branch">
                    <i class="fa-solid fa-location-dot text-[10px] text-amber-600"></i>
                    <span class="text-[10px] font-bold uppercase tracking-wide text-gray-600 truncate"><?= htmlspecialchars($sessionBranch) ?></span>
                    <?php if ($isMainBranch): ?>
                        <span class="ml-auto shrink-0 px-1.5 py-px rounded bg-amber-100 text-amber-800 text-[9px] font-bold uppercase">Main</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nav Links -->
    <nav class="relative z-[1] flex-1 px-2 py-3 space-y-0.5 overflow-y-auto overflow-x-hidden <?= $sbScroll ?> <?= $sbBadgePop ?>">
        <?php $first = true;
        foreach ($sbMenu as $section => $items):
            echo sbSectionLabel($section, $first);
            $first = false;
            foreach ($items as $it) echo sbItem($it);
        endforeach; ?>

        <?= sbSectionLabel('Notifications', $first); $first = false; ?>
        <button type="button" onclick="toggleSidebarNotif()" data-tooltip="Notifications"
            class="group/it relative w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition <?= SB_TIP ?>">
            <span class="relative w-4 flex items-center justify-center shrink-0">
                <i class="fa-regular fa-bell text-[13px] text-gray-400 group-hover/it:text-gray-600"></i>
                <span id="sidebar-notif-badge"
                    class="hidden absolute -top-1.5 -right-2 min-w-[15px] h-[15px] bg-red-500 rounded-full ring-2 ring-white text-[8px] font-bold text-white flex items-center justify-center px-0.5"></span>
            </span>
            <span id="sidebar-notif-badge-wrap" class="sidebar-label flex-1 text-left flex items-center gap-2 text-xs group-[.collapsed]/sb:hidden">Notifications</span>
            <i id="sidebar-notif-chevron" class="sidebar-label fa-solid fa-chevron-right text-[9px] text-gray-300 transition-transform duration-200 group-[.collapsed]/sb:hidden"></i>
        </button>

        <?php if ($sbFollowups): ?>
            <?= sbSectionLabel('Follow-ups', false) ?>
            <?php foreach ($sbFollowups as $it) echo sbItem($it); ?>
        <?php endif; ?>

        <?= sbSectionLabel('Settings', false) ?>
        <?php $translateVariant = 'desktop';
        include ROOT_PATH . '/admin/navigation/settings/translate.php'; ?>
        <?php foreach ($sbSettings as $it) echo sbItem($it); ?>
    </nav>

    <!-- User Profile Block -->
    <div class="relative z-[1] border-t border-gray-100 bg-white/90 backdrop-blur-sm">
        <?php if (!empty($_SESSION['role'])): ?>
            <div class="h-1" style="background-color: <?= $currentRoleColor ?>;"></div>
        <?php endif; ?>

        <div id="sidebar-user-block"
            class="relative flex items-center gap-2.5 px-3 py-3 hover:bg-gray-50 cursor-pointer transition group-[.collapsed]/sb:justify-center group-[.collapsed]/sb:px-0"
            onmouseenter="showUserCard()" onmouseleave="hideUserCard()" onclick="toggleUserCard()">
            <div class="relative shrink-0">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-[13px] font-bold"
                    style="background-color: <?= $currentRoleColor ?>1A; color: <?= $currentRoleColor ?>; box-shadow: inset 0 0 0 1.5px <?= $currentRoleColor ?>55;">
                    <?= htmlspecialchars($sbInitial) ?>
                </div>
                <span id="presence-dot"
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </div>
            <div class="user-text flex-1 min-w-0 group-[.collapsed]/sb:hidden">
                <p class="text-xs font-semibold text-gray-900 truncate"><?= htmlspecialchars($sbUserName) ?></p>
                <p class="text-[10px] truncate">
                    <span class="font-bold uppercase tracking-wide" style="color: <?= $currentRoleColor ?>;"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></span>
                    <?php if ($sbPositionLabel !== ''): ?><span class="text-gray-400"> · <?= htmlspecialchars($sbPositionLabel) ?></span><?php endif; ?>
                </p>
            </div>
            <i class="user-text fa-solid fa-ellipsis-vertical text-gray-300 text-xs group-[.collapsed]/sb:hidden"></i>
        </div>
    </div>

    <!-- User Hover Card -->
    <div id="user-card" onmouseenter="showUserCard()" onmouseleave="hideUserCard()"
        class="hidden md:block fixed z-[80] w-64 opacity-0 invisible -translate-x-1.5 transition-[opacity,transform,visibility] duration-150 [&.show]:opacity-100 [&.show]:visible [&.show]:translate-x-0 before:content-[''] before:absolute before:-left-3 before:inset-y-0 before:w-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-2xl overflow-hidden">
            <div class="h-1" style="background-color: <?= $currentRoleColor ?>;"></div>
            <div class="px-4 py-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-base font-bold shrink-0"
                        style="background-color: <?= $currentRoleColor ?>1A; color: <?= $currentRoleColor ?>; box-shadow: inset 0 0 0 1.5px <?= $currentRoleColor ?>55;">
                        <?= htmlspecialchars($sbInitial) ?>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate"><?= htmlspecialchars($sbUserName) ?></p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <?= htmlspecialchars($sbPositionLabel) ?>
                            <?php if (!empty($sessionBranch)): ?>&middot; <?= htmlspecialchars($sessionBranch) ?><?php endif; ?>
                        </p>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wide truncate" style="color: <?= $currentRoleColor ?>;"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></span>
                            <span class="inline-flex items-center gap-1">
                                <span id="user-card-dot" class="w-2 h-2 rounded-full bg-gray-300"></span>
                                <span id="user-card-status" class="text-[10px] font-semibold text-gray-400">Away</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-1.5 border-t border-gray-100">
                <a href="<?= BASE_URL ?>/adminprofile"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-amber-50 hover:text-amber-800 transition">
                    <i class="fa-solid fa-id-badge w-4 text-center text-gray-400"></i> My Employee ID
                </a>
                <a href="<?= BASE_URL ?>/logout"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-red-50 hover:text-red-600 transition">
                    <i class="fa-solid fa-right-from-bracket w-4 text-center text-gray-400"></i> Logout
                </a>
            </div>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MOBILE TOP BAR (visible on mobile only)                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<header class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-b border-gray-200 shadow-sm">
    <div class="<?= $sbHazard ?> h-1"></div>
    <div class="flex items-center justify-between px-3 h-14">
        <button type="button" onclick="openMobileDrawer()" aria-label="Open menu"
            class="w-10 h-10 flex items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 transition">
            <i class="fa-solid fa-bars text-base"></i>
        </button>

        <div class="flex items-center gap-2 min-w-0">
            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-7 w-auto object-contain">
            <span class="text-[13px] font-extrabold tracking-tight text-gray-900">Noble<span class="text-amber-600">Home</span></span>
        </div>

        <div class="flex items-center gap-1">
            <button type="button" onclick="toggleSidebarNotif()" aria-label="Notifications"
                class="relative w-10 h-10 flex items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 transition">
                <i class="fa-regular fa-bell text-base"></i>
                <span id="mobile-notif-badge"
                    class="hidden absolute top-1.5 right-1.5 min-w-[15px] h-[15px] bg-red-500 rounded-full ring-2 ring-white text-[8px] font-bold text-white flex items-center justify-center px-0.5"></span>
            </button>
            <button type="button" onclick="openMobileDrawer()" aria-label="Account"
                class="relative w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold"
                style="background-color: <?= $currentRoleColor ?>1A; color: <?= $currentRoleColor ?>; box-shadow: inset 0 0 0 1.5px <?= $currentRoleColor ?>55;">
                <?= htmlspecialchars($sbInitial) ?>
                <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </button>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MOBILE DRAWER                                              -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="mobile-overlay" onclick="closeMobileDrawer()"
    class="md:hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px] opacity-0 pointer-events-none transition-opacity duration-300 [&.open]:opacity-100 [&.open]:pointer-events-auto"></div>

<div id="mobile-drawer"
    class="md:hidden fixed top-0 left-0 h-screen w-72 max-w-[85vw] bg-white z-[70] flex flex-col shadow-2xl overflow-hidden -translate-x-full transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] [&.open]:translate-x-0">
    <?= sbWatermark('M') ?>

    <!-- Drawer Header -->
    <div class="relative z-[1] border-b border-gray-100">
        <div class="<?= $sbHazard ?> h-1"></div>
        <div class="flex items-center justify-between px-4 py-3.5">
            <div class="flex items-center gap-2.5 min-w-0">
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="NobleHome Logo" class="h-8 w-auto object-contain">
                <span class="leading-tight min-w-0">
                    <span class="block text-[13px] font-extrabold tracking-tight text-gray-900">Noble<span class="text-amber-600">Home</span></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">Department</span>
                </span>
            </div>
            <button type="button" onclick="closeMobileDrawer()" aria-label="Close"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
        </div>
    </div>

    <!-- Drawer User Block -->
    <div class="relative z-[1] px-4 py-3 border-b border-gray-100 bg-gray-50/80">
        <div class="flex items-center gap-3">
            <div class="relative shrink-0">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold"
                    style="background-color: <?= $currentRoleColor ?>1A; color: <?= $currentRoleColor ?>; box-shadow: inset 0 0 0 1.5px <?= $currentRoleColor ?>55;">
                    <?= htmlspecialchars($sbInitial) ?>
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?> border-2 border-white rounded-full"></span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-semibold text-gray-900 truncate"><?= htmlspecialchars($sbUserName) ?></p>
                <p class="text-[10px] truncate">
                    <span class="font-bold uppercase tracking-wide" style="color: <?= $currentRoleColor ?>;"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></span>
                    <span class="text-gray-400">
                        <?php if ($sbPositionLabel !== ''): ?> · <?= htmlspecialchars($sbPositionLabel) ?><?php endif; ?>
                        <?php if (!empty($sessionBranch)): ?> · <?= htmlspecialchars($sessionBranch) ?><?php endif; ?>
                    </span>
                </p>
                <p class="text-[10px] font-semibold mt-0.5 inline-flex items-center gap-1 <?= $isOnline ? 'text-green-600' : 'text-gray-400' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $isOnline ? 'bg-green-400' : 'bg-gray-300' ?>"></span><?= $isOnline ? 'Online' : 'Away' ?>
                </p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-3">
            <a href="<?= BASE_URL ?>/adminprofile" onclick="closeMobileDrawer()"
                class="inline-flex items-center justify-center gap-1.5 h-9 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-gray-700 hover:border-amber-300 hover:text-amber-800">
                <i class="fa-solid fa-id-badge text-gray-400"></i> Employee ID
            </a>
            <a href="<?= BASE_URL ?>/logout"
                class="inline-flex items-center justify-center gap-1.5 h-9 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-gray-700 hover:border-red-200 hover:text-red-600 hover:bg-red-50">
                <i class="fa-solid fa-right-from-bracket text-gray-400"></i> Logout
            </a>
        </div>
    </div>

    <!-- Drawer Nav (parehong menu ng desktop) -->
    <nav class="relative z-[1] flex-1 px-3 py-3 space-y-0.5 overflow-y-auto overscroll-contain <?= $sbScroll ?> <?= $sbBadgePop ?>">
        <?php $first = true;
        foreach ($sbMenu as $section => $items):
            echo sbSectionLabel($section, $first, true);
            $first = false;
            foreach ($items as $it) echo sbItem($it, true);
        endforeach; ?>

        <?php if ($sbFollowups): ?>
            <?= sbSectionLabel('Follow-ups', $first, true); $first = false; ?>
            <?php foreach ($sbFollowups as $it) echo sbItem($it, true); ?>
        <?php endif; ?>

        <?php if ($sbSettings): ?>
            <?= sbSectionLabel('Settings', $first, true); $first = false; ?>
            <?php foreach ($sbSettings as $it) echo sbItem($it, true); ?>
        <?php endif; ?>
    </nav>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  NOTIFICATION PANEL (laman: js/global-notif.js)             -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div id="notif-overlay" onclick="toggleSidebarNotif()" class="hidden fixed inset-0 z-40 bg-black/30 backdrop-blur-[1px]"></div>

<div id="notif-panel"
    class="fixed top-0 z-50 h-screen bg-white border-r border-gray-200 shadow-2xl flex flex-col transition-all duration-300 ease-in-out"
    style="width: 420px; max-width: 100vw; right: auto; left: -420px; border-left: none;">
    <div class="<?= $sbHazard ?> h-1 shrink-0"></div>

    <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0">
                <i class="fa-regular fa-bell"></i>
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-gray-900">Notifications</span>
                    <span id="notif-panel-badge"
                        class="hidden bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none"></span>
                </div>
                <p class="text-[11px] text-gray-500">Updates on your requests and approvals</p>
            </div>
        </div>
        <button type="button" onclick="toggleSidebarNotif()" aria-label="Close"
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
    </div>

    <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-gray-100 bg-gray-50 shrink-0">
        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Recent</span>
        <button type="button" onclick="sidebarMarkAllRead()"
            class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 hover:text-amber-800 bg-white border border-amber-200 hover:bg-amber-50 rounded-lg px-2.5 py-1 transition">
            <i class="fa-solid fa-check-double text-[10px]"></i> Mark all read
        </button>
    </div>

    <div id="sidebar-notif-list" class="flex-1 min-h-0 overflow-y-auto overscroll-contain divide-y divide-gray-100 <?= $sbScroll ?>">
        <div class="px-6 py-16 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                <i class="fa-regular fa-bell-slash text-xl"></i>
            </div>
            <p class="text-sm font-semibold text-gray-600">No notifications</p>
            <p class="text-[11px] text-gray-400 mt-1">You're all caught up.</p>
        </div>
    </div>
</div>

<!-- Tooltip ng collapsed sidebar -->
<div id="sb-tip" role="tooltip"
    class="hidden fixed z-[999] pointer-events-none -translate-y-1/2 px-2.5 py-1 rounded-md bg-gray-900 text-white text-[11px] font-medium whitespace-nowrap shadow-lg before:content-[''] before:absolute before:-left-2.5 before:top-1/2 before:-translate-y-1/2 before:border-[5px] before:border-transparent before:border-r-gray-900"></div>
<script>
    (() => {
        const sb = document.getElementById('sidebar');
        const tip = document.getElementById('sb-tip');
        if (!sb || !tip) return;
        const hide = () => tip.classList.add('hidden');
        sb.addEventListener('mouseover', e => {
            const el = e.target.closest('[data-tooltip]');
            if (!el || !sb.contains(el) || !sb.classList.contains('collapsed')) return hide();
            const r = el.getBoundingClientRect();
            tip.textContent = el.dataset.tooltip;
            tip.style.left = (r.right + 10) + 'px';
            tip.style.top = (r.top + r.height / 2) + 'px';
            tip.classList.remove('hidden');
        });
        sb.addEventListener('mouseleave', hide);
        sb.addEventListener('click', hide);
        sb.querySelector('nav')?.addEventListener('scroll', hide, { passive: true });
    })();

    var CURRENT_USER_ID = <?= intval($_SESSION['account_id'] ?? 0) ?>;
</script>

<script src="<?= BASE_URL ?>/js/global-notif.js?v=<?= time() ?>"></script>

<?php include ROOT_PATH . '/admin/navigation/sidebar-script.php'; ?>