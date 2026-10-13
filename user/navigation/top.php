<?php
// top.php (user navigation)

// Bilang ng sariling request na wala pang na-upload na follow-up attachment
$fuNavCount = 0;
$fuNavUserId = intval($_SESSION['account_id'] ?? 0);
if ($fuNavUserId && isset($conn)) {
    $fuNavStmt = $conn->prepare("
        SELECT COUNT(*) FROM noblebudgetrequest
        WHERE user_id = ? AND attachment_status = 'follow_up'
    ");
    $fuNavStmt->bind_param("i", $fuNavUserId);
    $fuNavStmt->execute();
    $fuNavStmt->bind_result($fuNavCount);
    $fuNavStmt->fetch();
    $fuNavStmt->close();
}
?>
<!-- ===== NH FILL LOADER ===== -->
<div id="nh-loader" aria-label="Loading">
    <svg viewBox="0 0 1930 1640" xmlns="http://www.w3.org/2000/svg" role="img">
        <defs>
            <clipPath id="nhShape">
                <polygon
                    points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
                <polygon
                    points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
                <polygon points="410,722 702,518 706,1088" />
                <polygon
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </clipPath>
            <!-- rising liquid with wavy top -->
            <clipPath id="nhLiquid">
                <path
                    d="M-400,0 q100,-45 200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 t200,0 V2000 H-400 Z">
                    <animateTransform attributeName="transform" type="translate" additive="sum" dur="1.1s"
                        repeatCount="indefinite" values="0 0; 400 0" />
                </path>
            </clipPath>
        </defs>

        <!-- empty logo (no solid color) -->
        <g class="empty">
            <polygon points="70,695 962,70 1475,430 1475,280 1605,280 1605,530 1860,713 1787,820 962,232 145,802" />
            <polygon
                points="265,830 338,775 786,1330 790,455 920,367 920,1568 793,1568 397,1060 397,1568 265,1568" />
            <polygon points="410,722 702,518 706,1088" />
            <polygon
                points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
        </g>

        <!-- colored logo, only visible where the liquid is -->
        <g clip-path="url(#nhShape)">
            <g clip-path="url(#nhLiquid)">
                <rect x="0" y="0" width="1930" height="1640" class="o" />
                <polygon class="d" points="410,722 702,518 706,1088" />
                <polygon class="d"
                    points="1005,347 1135,438 1135,950 1457,950 1457,668 1588,762 1588,1568 1457,1568 1457,1080 1135,1080 1135,1568 1005,1568" />
            </g>
        </g>
    </svg>
</div>

<script>
    // Loader fill follows the real page-load progress
    (function () {
        var loader = document.getElementById('nh-loader');
        var wave = document.querySelector('#nhLiquid path');
        if (!loader || !wave) return;

        var EMPTY = 1750, FULL = -80;   // liquid Y position: empty -> full
        var shown = 0, target = 0, done = false, start = Date.now();

        function paint(p) {
            wave.setAttribute('transform', 'translate(0 ' + (EMPTY + (FULL - EMPTY) * p / 100) + ')');
        }
        function bump(p) { if (p > target) target = p; }
        paint(0);

        // 1) HTML still downloading: creep slowly up to 35%
        var creep = setInterval(function () {
            bump(Math.min(35, (Date.now() - start) / 40));
        }, 100);

        // 2) HTML parsed: 60%, then each image loaded adds up to 95%
        function onReady() {
            bump(60);
            var imgs = Array.prototype.slice.call(document.images);
            var total = imgs.length, loaded = 0;
            if (!total) return;
            function tick() { loaded++; bump(60 + 35 * loaded / total); }
            imgs.forEach(function (img) {
                if (img.complete) tick();
                else {
                    img.addEventListener('load', tick);
                    img.addEventListener('error', tick);
                }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
        else onReady();

        // 3) everything loaded: 100% then hide
        function finish() { done = true; bump(100); }
        window.addEventListener('load', finish);
        setTimeout(finish, 15000); // safety: never block the page forever

        // smooth the liquid toward the target
        (function frame() {
            shown += (target - shown) * 0.12;
            if (done && target - shown < 0.5) shown = 100;
            paint(shown);
            if (shown >= 100) {
                clearInterval(creep);
                setTimeout(function () {
                    loader.classList.add('nh-hide');
                    setTimeout(function () { loader.remove(); }, 600);
                }, 250);
                return;
            }
            requestAnimationFrame(frame);
        })();
    })();
</script>
<!-- ===== END NH FILL LOADER ===== -->

<style>
    /* ===== NH FILL LOADER ===== */
    #nh-loader {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(17, 17, 17, .55);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity .5s ease, visibility .5s ease;
    }

    #nh-loader.nh-hide {
        opacity: 0;
        visibility: hidden
    }

    #nh-loader svg {
        width: min(160px, 42vw);
        height: auto;
        overflow: hidden
    }

    #nh-loader .empty {
        fill: rgba(255, 255, 255, .12);
        stroke: rgba(255, 255, 255, .4);
        stroke-width: 6;
        stroke-linejoin: round
    }

    #nh-loader .o {
        fill: #f79421
    }

    #nh-loader .d {
        fill: #333
    }

    /* ===== END NH FILL LOADER ===== */

    /* Mobile sidebar slide-in */
    #mobile-sidebar {
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #mobile-sidebar.open {
        transform: translateX(0);
    }

    /* ── Nav links: icon on top, label below, orange underline when active ── */
    .nh-link {
        position: relative;
        white-space: nowrap;
    }

    .nh-link i.nh-ico {
        font-size: 1.25rem;
        line-height: 1;
        transition: color .2s ease, transform .2s ease;
    }

    .nh-link:hover i.nh-ico {
        transform: translateY(-1px);
    }

    .nh-link::after {
        content: '';
        position: absolute;
        left: 0.75rem;
        right: 0.75rem;
        bottom: -6px;
        height: 3px;
        border-radius: 999px;
        background: #f97316;
        transform: scaleX(0);
        transform-origin: center;
        transition: transform 0.2s ease;
    }

    @media (min-width: 1024px) {
        .nh-link::after {
            left: 1.25rem;
            right: 1.25rem;
        }
    }

    .nh-link:hover::after {
        transform: scaleX(0.4);
    }

    .nh-link.active::after {
        transform: scaleX(1);
    }
</style>

<nav class="fixed top-0 left-0 right-0 z-50 px-3 md:px-6 pt-3">
    <div
        class="relative max-w-[1400px] mx-auto bg-white rounded-xl shadow-[0_18px_40px_-14px_rgba(0,0,0,0.28)] flex items-stretch">

        <!-- ── Section 1: Logo + Brand (orange bar on top) ── -->
        <a href="<?= BASE_URL ?>/home1"
            class="relative flex items-center gap-3 shrink-0 px-4 md:px-5 lg:px-8 py-3 md:py-4 md:border-r border-gray-200">

            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Noblehome Logo"
                class="h-9 md:h-10 lg:h-12 w-auto object-contain">
            <span class="md:hidden lg:block leading-none">
                <span class="block font-bold text-sm lg:text-xl uppercase tracking-[0.1em] whitespace-nowrap">
                    <span class="text-orange-500">Noble</span><span class="text-gray-800">Home</span>
                </span>
                <span
                    class="block mt-1.5 text-[8px] lg:text-[10px] font-semibold uppercase tracking-[0.3em] text-gray-400 whitespace-nowrap">Request
                    Portal</span>
            </span>
        </a>

        <!-- ── Section 2: Desktop nav links (icon on top, label below) ── -->
        <div class="hidden md:flex flex-1 items-center justify-center min-w-0 px-1 lg:px-4">
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
            $relativePath = $basePath ? str_replace($basePath, '', $currentPath) : $currentPath;
            // 'badge' = ID ng badge element (o null kung walang badge)
            $navLinks = [
                '/dashboard' => ['label' => 'Announcement', 'icon' => 'fa-solid fa-bullhorn', 'badge' => 'ann-nav-badge'],
                '/requesthistory' => ['label' => 'My Requests', 'icon' => 'fa-regular fa-file-lines', 'badge' => null],
                '/myvouchers' => ['label' => 'My Vouchers', 'icon' => 'fa-solid fa-ticket', 'badge' => null],
                '/rejectedrequests' => ['label' => 'Rejected Requests', 'icon' => 'fa-solid fa-ban', 'badge' => null],
                '/followupattachments' => ['label' => 'Follow-up Files', 'icon' => 'fa-solid fa-paperclip', 'badge' => 'fu-nav-badge'],
            ];
            $i = 0;
            foreach ($navLinks as $path => $item):
                $isActive = str_starts_with($relativePath, $path);
                $textClass = $isActive ? 'text-orange-500 font-semibold active' : 'text-gray-700 hover:text-orange-500 font-medium';
                $icoClass = $isActive ? 'text-orange-500' : 'text-gray-600';
                ?>
                <?php if ($i > 0): ?>
                    <span class="w-px h-9 bg-gray-200 self-center mt-4 shrink-0"></span>
                <?php endif; ?>
                <a href="<?= BASE_URL . $path ?>"
                    class="nh-link <?= $textClass ?> flex flex-col items-center gap-2 text-[12px] lg:text-[14px] px-3 lg:px-6 py-2 transition-colors">
                    <span class="relative">
                        <i class="nh-ico <?= $item['icon'] ?> <?= $icoClass ?>"></i>
                        <?php if ($item['badge']): ?>
                            <?php
                            $isFu = $item['badge'] === 'fu-nav-badge';
                            $badgeHidden = ($isFu && $fuNavCount > 0) ? '' : 'hidden';
                            $badgeText = $isFu ? ($fuNavCount > 9 ? '9+' : (int) $fuNavCount) : 0;
                            $badgeColor = $isFu ? 'bg-red-500' : 'bg-orange-500';
                            ?>
                            <span id="<?= $item['badge'] ?>"
                                class="<?= $badgeHidden ?> absolute -top-2 -right-3 min-w-[16px] h-4 px-1 <?= $badgeColor ?> rounded-full text-[9px] text-white font-bold flex items-center justify-center"><?= $badgeText ?></span>
                        <?php endif; ?>
                    </span>
                    <span><?= $item['label'] ?></span>
                </a>
                <?php $i++; endforeach; ?>
        </div>

        <!-- ── Section 3: Bell + User ── -->
        <div class="flex items-center gap-1 lg:gap-3 ml-auto md:ml-0 px-3 lg:px-6 md:border-l border-gray-200 shrink-0">

            <!-- Notif Bell (desktop only) -->
            <div class="relative hidden md:block" id="notif-wrapper">
                <button onclick="toggleNotif()"
                    class="relative text-gray-700 hover:text-orange-500 transition-colors w-11 h-11 flex items-center justify-center rounded-full hover:bg-orange-50"
                    aria-label="Notifications">
                    <i class="fa-solid fa-bell text-xl"></i>
                    <span id="notif-badge"
                        class="hidden absolute top-0.5 right-0 w-5 h-5 bg-orange-500 rounded-full text-[10px] text-white font-bold flex items-center justify-center ring-2 ring-white">0</span>
                </button>
                <!-- Notif Dropdown -->
                <div id="notif-dropdown"
                    class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <span class="text-xs font-bold text-gray-700 uppercase tracking-widest">Notifications</span>
                        <button onclick="markAllRead()"
                            class="text-[10px] text-orange-500 hover:text-orange-600 font-semibold transition-colors">Mark
                            all as read</button>
                    </div>
                    <div id="notif-list" class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                        <p class="text-xs text-gray-400 text-center py-6">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- Desktop user (avatar + name + View Profile + chevron) -->
            <div class="relative hidden md:block" id="user-dropdown-wrapper">
                <button onclick="toggleDropdown()"
                    class="flex items-center gap-3 pl-1 pr-2 lg:pr-3 py-1.5 rounded-full hover:bg-orange-50 transition-colors text-left">
                    <div
                        class="w-11 h-11 rounded-full bg-orange-100 ring-2 ring-orange-500 ring-offset-2 ring-offset-white flex items-center justify-center shrink-0 overflow-hidden">
                        <?php if (!empty($_SESSION['picture'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['picture']) ?>" alt="avatar"
                                class="w-full h-full object-cover" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-orange-500 text-sm"></i>
                        <?php endif; ?>
                    </div>
                    <div class="hidden lg:block leading-tight min-w-0">
                        <p class="text-[15px] font-semibold text-gray-800 max-w-[150px] truncate">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </p>
                        <p class="text-xs text-gray-400">View Profile</p>
                    </div>
                    <i class="fa-solid fa-chevron-down text-xs text-orange-500 transition-transform duration-200"
                        id="chevron-icon"></i>
                </button>
                <div id="user-dropdown"
                    class="absolute right-0 mt-3 w-52 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden opacity-0 invisible -translate-y-1 transition-all duration-200 z-50">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <p class="text-xs font-bold text-gray-800 truncate">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </p>
                        <p class="text-[10px] text-gray-400 truncate mt-0.5">
                            <?= htmlspecialchars($_SESSION['email'] ?? '') ?>
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/profile"
                        class="flex items-center gap-3 px-4 py-3 text-xs text-gray-700 hover:bg-orange-50 hover:text-orange-600 font-medium transition-colors">
                        <i class="fa-regular fa-circle-user w-4 text-center"></i> Profile
                    </a>
                    <div class="border-t border-gray-100"></div>
                    <a href="<?= BASE_URL ?>/logoutuser"
                        class="flex items-center gap-3 px-4 py-3 text-xs text-red-500 hover:bg-red-50 font-medium transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Mobile hamburger -->
            <button onclick="toggleMobileSidebar()"
                class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg text-gray-800 hover:bg-orange-50 hover:text-orange-500 transition-all"
                id="hamburger-btn" aria-label="Menu">
                <i class="fa-solid fa-bars text-lg" id="hamburger-icon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Sidebar Overlay -->
<div id="mobile-overlay" class="hidden fixed inset-0 bg-black/50 z-[60] md:hidden" onclick="closeMobileSidebar()"></div>

<!-- Mobile Sidebar -->
<div id="mobile-sidebar" class="fixed top-0 right-0 h-full w-72 bg-white z-[70] md:hidden flex flex-col shadow-2xl">

    <!-- Sidebar Header -->
    <div class="bg-orange-500 px-5 py-5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo"
                class="w-8 h-8 object-contain bg-white rounded-full p-1">
            <div>
                <p class="text-white font-bold text-sm leading-tight">NobleHome</p>
                <p class="text-white/70 text-[10px]">Request Portal</p>
            </div>
        </div>
        <button onclick="closeMobileSidebar()"
            class="text-white/70 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Notifications Section (Mobile Sidebar) -->
    <div class="px-3 py-3 border-b border-gray-100">
        <div class="flex items-center justify-between px-2 mb-2">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Notifications</p>
            <button onclick="markAllRead()"
                class="text-[10px] text-orange-500 hover:text-orange-600 font-semibold transition-colors">Mark all as
                read</button>
        </div>
        <div id="mob-notif-list"
            class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white">
            <p class="text-xs text-gray-400 text-center py-4">Loading...</p>
        </div>
    </div>

    <!-- User Info -->
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center shrink-0 overflow-hidden">
            <?php if (!empty($_SESSION['picture'])): ?>
                <img src="<?= htmlspecialchars($_SESSION['picture']) ?>" alt="avatar" class="w-full h-full object-cover"
                    referrerpolicy="no-referrer">
            <?php else: ?>
                <i class="fa-solid fa-user text-orange-500 text-sm"></i>
            <?php endif; ?>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </p>
            <p class="text-[11px] text-gray-400 truncate"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
        </div>
    </div>

    <!-- Nav Links -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2">Menu</p>
        <?php
        $mobileNavLinks = [
            '/dashboard' => ['label' => 'Announcement', 'icon' => 'fa-bullhorn'],
            '/requesthistory' => ['label' => 'My Requests', 'icon' => 'fa-file-lines'],
            '/myvouchers' => ['label' => 'My Vouchers', 'icon' => 'fa-ticket'],
            '/rejectedrequests' => ['label' => 'Rejected Requests', 'icon' => 'fa-ban'],
            '/followupattachments' => ['label' => 'Follow-up Files', 'icon' => 'fa-paperclip'],
        ];
        foreach ($mobileNavLinks as $path => $item):
            $isActive = str_starts_with($relativePath, $path);
            $activeClass = $isActive
                ? 'bg-orange-50 text-orange-600 font-semibold'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800';
            ?>
            <a href="<?= BASE_URL . $path ?>"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all <?= $activeClass ?>">
                <i
                    class="fa-solid <?= $item['icon'] ?> w-4 text-center <?= $isActive ? 'text-orange-500' : 'text-gray-400' ?>"></i>
                <span><?= $item['label'] ?></span>
                <?php if ($path === '/dashboard'): ?>
                    <span id="mob-ann-badge"
                        class="hidden ml-auto bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">0</span>
                <?php endif; ?>
                <?php if ($path === '/followupattachments'): ?>
                    <span id="mob-fu-badge"
                        class="<?= $fuNavCount > 0 ? '' : 'hidden' ?> ml-auto bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= $fuNavCount > 9 ? '9+' : (int) $fuNavCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <div class="pt-3">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2">Account</p>
        </div>

        <a href="<?= BASE_URL ?>/profile"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-800 transition-all">
            <i class="fa-regular fa-circle-user w-4 text-center text-gray-400"></i>
            <span>Profile</span>
        </a>
    </nav>

    <!-- Logout -->
    <div class="px-3 py-4 border-t border-gray-100">
        <a href="<?= BASE_URL ?>/logoutuser"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-red-500 hover:bg-red-50 transition-all font-medium">
            <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    // ── Mobile Sidebar ───────────────────────────────────
    let sidebarOpen = false;

    function toggleMobileSidebar() {
        sidebarOpen ? closeMobileSidebar() : openMobileSidebar();
    }

    function openMobileSidebar() {
        sidebarOpen = true;
        document.getElementById('mobile-sidebar').classList.add('open');
        document.getElementById('mobile-overlay').classList.remove('hidden');
        document.getElementById('hamburger-icon').className = 'fa-solid fa-xmark text-lg';
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebarOpen = false;
        document.getElementById('mobile-sidebar').classList.remove('open');
        document.getElementById('mobile-overlay').classList.add('hidden');
        document.getElementById('hamburger-icon').className = 'fa-solid fa-bars text-lg';
        document.body.style.overflow = '';
    }

    // ── Desktop Dropdown ─────────────────────────────────
    let notifOpen = false;

    function toggleDropdown() {
        const dropdown = document.getElementById('user-dropdown');
        const chevron = document.getElementById('chevron-icon');
        const isOpen = !dropdown.classList.contains('invisible');
        if (isOpen) {
            dropdown.classList.add('opacity-0', 'invisible', '-translate-y-1');
            dropdown.classList.remove('opacity-100', 'translate-y-0');
            chevron.classList.remove('rotate-180');
        } else {
            dropdown.classList.remove('opacity-0', 'invisible', '-translate-y-1');
            dropdown.classList.add('opacity-100', 'translate-y-0');
            chevron.classList.add('rotate-180');
        }
    }

    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('user-dropdown-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            const dropdown = document.getElementById('user-dropdown');
            const chevron = document.getElementById('chevron-icon');
            dropdown.classList.add('opacity-0', 'invisible', '-translate-y-1');
            dropdown.classList.remove('opacity-100', 'translate-y-0');
            chevron?.classList.remove('rotate-180');
        }
    });

    // ── Notifications ────────────────────────────────────
    function toggleNotif() {
        const dropdown = document.getElementById('notif-dropdown');
        notifOpen = !notifOpen;
        if (notifOpen) {
            dropdown.classList.remove('hidden');
            fetchNotifications();
        } else {
            dropdown.classList.add('hidden');
        }
    }

    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('notif-wrapper');
        if (!wrapper.contains(e.target)) {
            document.getElementById('notif-dropdown').classList.add('hidden');
            notifOpen = false;
        }
    });

    function fetchNotifications() {
        fetch('<?= BASE_URL ?>/fetchnotificationsuser')
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('notif-list');
                const mobList = document.getElementById('mob-notif-list');
                const badge = document.getElementById('notif-badge');
                const unread = data.filter(n => !n.is_read).length;

                if (unread > 0) {
                    badge.textContent = unread > 9 ? '9+' : unread;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }

                const empty = '<p class="text-xs text-gray-400 text-center py-4">No notifications yet.</p>';

                if (!data.length) {
                    list.innerHTML = empty;
                    if (mobList) mobList.innerHTML = empty;
                    return;
                }

                const html = data.slice(0, 10).map(n => `
                <div class="flex items-start gap-3 px-3 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer ${!n.is_read ? 'bg-orange-50' : ''}"
                     onclick="readNotif(${n.id})">
                    <div class="mt-0.5 w-6 h-6 rounded-full flex items-center justify-center shrink-0
                        ${!n.is_read ? 'bg-orange-100 text-orange-500' : 'bg-gray-100 text-gray-400'}">
                        <i class="fa-solid fa-bell text-[9px]"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-700 leading-snug ${!n.is_read ? 'font-semibold' : ''}">${n.message}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">${n.created_at}</p>
                    </div>
                    ${!n.is_read ? '<span class="w-1.5 h-1.5 bg-orange-400 rounded-full mt-2 shrink-0"></span>' : ''}
                </div>`).join('');

                list.innerHTML = html;
                if (mobList) mobList.innerHTML = html;
            });
    }

    function readNotif(id) {
        fetch('<?= BASE_URL ?>/readnotificationuser', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(() => fetchNotifications());
    }

    function markAllRead() {
        fetch('<?= BASE_URL ?>/readnotificationuser', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: 'all' })
        }).then(() => fetchNotifications());
    }

    // ── Announcement Badge ───────────────────────────────
    function updateAnnBadge() {
        fetch('<?= BASE_URL ?>/fetchannouncements')
            .then(res => res.json())
            .then(data => {
                if (!data.length) return;
                const lastSeenId = parseInt(localStorage.getItem('last_seen_ann_id') ?? 0);
                const newCount = data.filter(r => r.id > lastSeenId).length;

                // Desktop badge
                const badge = document.getElementById('ann-nav-badge');
                if (badge) {
                    if (newCount > 0) { badge.textContent = newCount > 9 ? '9+' : newCount; badge.classList.remove('hidden'); }
                    else badge.classList.add('hidden');
                }
                // Mobile badge
                const mobBadge = document.getElementById('mob-ann-badge');
                if (mobBadge) {
                    if (newCount > 0) { mobBadge.textContent = newCount > 9 ? '9+' : newCount; mobBadge.classList.remove('hidden'); }
                    else mobBadge.classList.add('hidden');
                }
            })
            .catch(() => { });
    }

    updateAnnBadge();
    setInterval(updateAnnBadge, 5000);

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('a[href$="/dashboard"]')?.addEventListener('click', function () {
            fetch('<?= BASE_URL ?>/fetchannouncements')
                .then(res => res.json())
                .then(data => {
                    if (data.length) {
                        localStorage.setItem('last_seen_ann_id', data[0].id);
                        document.getElementById('ann-nav-badge')?.classList.add('hidden');
                        document.getElementById('mob-ann-badge')?.classList.add('hidden');
                    }
                });
        });
    });

    // ── Follow-up Badge ──────────────────────────────────
    // Tinatawag ng follow-up page pagkatapos mag-upload para bumaba agad ang bilang
    window.setFuBadge = function (n) {
        ['fu-nav-badge', 'mob-fu-badge'].forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (n > 0) { el.textContent = n > 9 ? '9+' : n; el.classList.remove('hidden'); }
            else el.classList.add('hidden');
        });
    };

    fetchNotifications();
    setInterval(fetchNotifications, 30000);
</script>