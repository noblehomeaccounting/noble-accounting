<!-- top.php -->
<div id="nh-loader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/10 backdrop-blur-sm">
    <div class="flex items-center flex-wrap justify-center" id="loader-letters"></div>
</div>

<script>
    const words = [
        { letters: 'NOBLE', color: '#f97316', delay: 0 },
        { letters: 'HOME', color: null, delay: 5 },
        { letters: 'DEPOT', color: null, delay: 9 },
    ];
    const wrap = document.getElementById('loader-letters');
    const isMobile = window.innerWidth < 768;
    const fontSize = isMobile ? '1.6rem' : '2.5rem';

    words.forEach(({ letters, color, delay }, wordIndex) => {
        letters.split('').forEach((ch, i) => {
            const span = document.createElement('span');
            span.textContent = ch;
            span.style.cssText = `
                font-size: ${fontSize}; font-weight: 600; letter-spacing: ${isMobile ? '1px' : '2px'};
                color: ${color ?? '#111'}; opacity: 1;
                animation: nhblink 2s infinite;
                animation-delay: ${(delay + i) * 0.15}s;
            `;
            wrap.appendChild(span);
        });

        if (wordIndex < words.length - 1) {
            const space = document.createElement('span');
            space.style.cssText = `display: inline-block; width: ${isMobile ? '6px' : '10px'};`;
            wrap.appendChild(space);
        }
    });

    setTimeout(() => {
        const loader = document.getElementById('nh-loader');
        loader.style.transition = 'opacity 0.4s ease';
        loader.style.opacity = '0';
        setTimeout(() => loader.remove(), 400);
    }, 2000);
</script>

<style>
    @keyframes nhblink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.1;
        }
    }

    /* Mobile sidebar slide-in */
    #mobile-sidebar {
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #mobile-sidebar.open {
        transform: translateX(0);
    }
</style>

<nav class="fixed top-0 left-0 right-0 z-50 bg-black/40 backdrop-blur-sm md:bg-transparent md:backdrop-blur-none">
    <div class="max-w-8xl mx-auto px-6 py-4 flex items-center justify-between">

        <!-- Logo + Brand -->
        <a href="<?= BASE_URL ?>/" class="flex items-center gap-3">
            <div class="w-9 h-9 shrink-0">
                <img src="<?= BASE_URL ?>/icon/logo.png" alt="Noblehome Logo"
                    class="w-full h-full object-contain bg-white rounded-full p-1">
            </div>
            <div class="w-px h-6 bg-white/40"></div>
            <span class="text-white font-bold text-sm uppercase tracking-widest drop-shadow">
                NobleHome <span class="text-orange-400">Request</span>
            </span>
        </a>

        <!-- Desktop nav links -->
        <div class="hidden md:flex items-center gap-1">
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
            $relativePath = $basePath ? str_replace($basePath, '', $currentPath) : $currentPath;
            $navLinks = [
                '/dashboard' => ['label' => 'Announcement', 'badge' => true],
                '/requesthistory' => ['label' => 'My Requests', 'badge' => false],
                '/myvouchers' => ['label' => 'My Vouchers', 'badge' => false],
                '/rejectedrequests' => ['label' => 'Rejected Requests', 'badge' => false],
            ];
            foreach ($navLinks as $path => $item):
                $isActive = str_starts_with($relativePath, $path);
                $activeClass = $isActive ? 'text-white border-b-2 border-white' : 'text-white/70 hover:text-white hover:bg-white/10';
                ?>
                <a href="<?= BASE_URL . $path ?>"
                    class="<?= $activeClass ?> relative text-xs font-semibold uppercase tracking-widest px-4 py-2 rounded-lg transition-all">
                    <?= $item['label'] ?>
                    <?php if ($item['badge']): ?>
                        <span id="ann-nav-badge"
                            class="hidden absolute -top-1 -right-1 w-4 h-4 bg-orange-500 rounded-full text-[9px] text-white font-bold flex items-center justify-center">0</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-2">

            <!-- Notif Bell (desktop only now) -->
            <div class="relative hidden md:block" id="notif-wrapper">
                <button onclick="toggleNotif()"
                    class="relative text-white/70 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10 group">
                    <i class="fa-solid fa-bell text-md"></i>
                    <span id="notif-badge"
                        class="hidden absolute top-1 right-1 w-4 h-4 bg-orange-500 rounded-full text-[9px] text-white font-bold flex items-center justify-center">0</span>
                    <span
                        class="absolute right-full top-1/2 -translate-y-1/2 ml-2 bg-gray-800 text-white text-[10px] font-medium px-2 py-1 rounded-md
                        whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50 hidden md:block">
                        Notifications
                    </span>
                </button>
                <!-- Notif Dropdown -->
                <div id="notif-dropdown"
                    class="hidden absolute right-0 mt-1 w-80 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
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

            <!-- Desktop user dropdown -->
            <div class="relative hidden md:block" id="user-dropdown-wrapper">
                <button onclick="toggleDropdown()"
                    class="flex items-center gap-2 text-white/80 hover:text-white hover:bg-white/10 px-3 py-2 rounded-lg transition-all">
                    <div
                        class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center shrink-0 overflow-hidden">
                        <?php if (!empty($_SESSION['picture'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['picture']) ?>" alt="avatar"
                                class="w-full h-full object-cover" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-white text-[10px]"></i>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-widest hidden sm:inline">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </span>
                    <i class="fa-solid fa-chevron-down text-[9px] opacity-60 transition-transform duration-200"
                        id="chevron-icon"></i>
                </button>
                <div id="user-dropdown"
                    class="absolute right-0 mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden opacity-0 invisible -translate-y-1 transition-all duration-200 z-50">
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
                class="md:hidden flex items-center justify-center w-9 h-9 rounded-lg text-white hover:bg-white/10 transition-all"
                id="hamburger-btn">
                <i class="fa-solid fa-bars text-sm" id="hamburger-icon"></i>
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
            <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo"
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
    <nav class="flex-1 px-3 py-4 space-y-1">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2">Menu</p>
        <?php
        $mobileNavLinks = [
            '/dashboard' => ['label' => 'Announcement', 'icon' => 'fa-bullhorn'],
            '/requesthistory' => ['label' => 'My Requests', 'icon' => 'fa-file-lines'],
            '/myvouchers' => ['label' => 'My Vouchers', 'icon' => 'fa-ticket'],
            '/rejectedrequests' => ['label' => 'Rejected Requests', 'icon' => 'fa-file-lines'],
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
        document.getElementById('hamburger-icon').className = 'fa-solid fa-xmark text-sm';
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebarOpen = false;
        document.getElementById('mobile-sidebar').classList.remove('open');
        document.getElementById('mobile-overlay').classList.add('hidden');
        document.getElementById('hamburger-icon').className = 'fa-solid fa-bars text-sm';
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

    fetchNotifications();
    setInterval(fetchNotifications, 30000);
</script>