<script>
    const BASE_URL = '<?= BASE_URL ?>';

    const esc = s => String(s ?? '').replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ── User Hover Card ───────────────────────────────────────
    var userCardTimer;

    function positionUserCard() {
        var block = document.getElementById('sidebar-user-block');
        var sidebar = document.getElementById('sidebar');
        var card = document.getElementById('user-card');
        if (!block || !sidebar || !card) return;

        var b = block.getBoundingClientRect();
        var s = sidebar.getBoundingClientRect();
        card.style.left = (s.right + 8) + 'px';
        card.style.bottom = Math.max(8, window.innerHeight - b.bottom) + 'px';
    }

    function showUserCard() {
        clearTimeout(userCardTimer);
        var card = document.getElementById('user-card');
        if (!card) return;

        var dot = document.getElementById('presence-dot');
        var online = dot && dot.classList.contains('bg-green-400');

        var cardDot = document.getElementById('user-card-dot');
        var cardStatus = document.getElementById('user-card-status');
        cardDot.className = 'w-2 h-2 rounded-full ' + (online ? 'bg-green-400' : 'bg-gray-300');
        cardStatus.textContent = online ? 'Online' : 'Away';
        cardStatus.className = 'text-[11px] font-semibold ' + (online ? 'text-green-500' : 'text-gray-400');

        positionUserCard();
        card.classList.add('show');
    }

    function hideUserCard() {
        userCardTimer = setTimeout(function () {
            var card = document.getElementById('user-card');
            if (card) card.classList.remove('show');
        }, 150);
    }

    function toggleUserCard() { // click: para sa touch devices lang
        if (!window.matchMedia('(hover: none)').matches) return;
        var card = document.getElementById('user-card');
        if (!card) return;
        card.classList.contains('show') ? card.classList.remove('show') : showUserCard();
    }

    window.addEventListener('resize', positionUserCard);

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
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) closeMobileDrawer();
    });

    // ── Desktop Sidebar Collapse ──────────────────────────────
    const SIDEBAR_KEY = 'sidebar_collapsed';
    let sidebarCollapsed = localStorage.getItem(SIDEBAR_KEY) === 'true';

    function applySidebarState() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebarCollapsed ? sidebar.classList.add('collapsed') : sidebar.classList.remove('collapsed');
        const main = document.getElementById('main-content') || document.querySelector('main') || document.querySelector('.main-content');
        if (main) {
            main.style.marginLeft = window.innerWidth >= 768 ? (sidebarCollapsed ? '56px' : '224px') : '0';
            main.style.transition = 'margin-left 0.25s ease';
        }
    }
    function toggleSidebar() {
        sidebarCollapsed = !sidebarCollapsed;
        localStorage.setItem(SIDEBAR_KEY, sidebarCollapsed);
        applySidebarState();
    }
    applySidebarState();
    window.addEventListener('resize', applySidebarState);

    // ══════════════════════════════════════════════════════════
    //  ISANG POLLING LANG: heartbeat + cleanup + badges + notif
    // ══════════════════════════════════════════════════════════
    const POLL_MS = 20000;
    let notifPanelOpen = false;
    let polling = false;
    let lastNotifSig = null;

    function setDot(online) {
        document.querySelectorAll('#presence-dot').forEach(dot => {
            dot.className = `absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 ${online ? 'bg-green-400' : 'bg-gray-300'} border-2 border-white rounded-full`;
        });
    }

    function applyBadges(counts) {
        document.querySelectorAll('[data-badge]').forEach(el => {
            const n = parseInt(counts[el.dataset.badge] ?? 0, 10) || 0;
            const text = n > 99 ? '99+' : String(n);
            const changed = el.textContent.trim() !== text;
            el.textContent = text;
            el.classList.toggle('hidden', n <= 0);
            el.classList.toggle('flex', n > 0);
            if (changed && n > 0) {
                el.classList.remove('badge-pop');
                void el.offsetWidth;
                el.classList.add('badge-pop');
            }
        });
    }

    function applyNotifBadge(unread) {
        const text = unread > 9 ? '9+' : String(unread);
        ['sidebar-notif-badge', 'mobile-notif-badge', 'notif-panel-badge'].forEach(id => {
            const b = document.getElementById(id);
            if (!b) return;
            b.textContent = text;
            b.classList.toggle('hidden', unread <= 0);
        });
    }

    async function pollSidebar() {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const res = await fetch(BASE_URL + '/sidebarpoll', { method: 'POST', cache: 'no-store' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const d = await res.json();
            setDot(true);
            applyBadges(d.counts || {});
            applyNotifBadge(d.notif.unread);

            const sig = d.notif.unread + ':' + d.notif.latest_id;
            if (sig !== lastNotifSig || notifPanelOpen) sidebarFetchNotifications();
            lastNotifSig = sig;
        } catch (e) {
            console.error('Poll error:', e);
            setDot(false);
        } finally {
            polling = false;
        }
    }

    pollSidebar();
    setInterval(pollSidebar, POLL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollSidebar(); });

    // ── Notifications panel ───────────────────────────────────
    function toggleSidebarNotif() {
        notifPanelOpen = !notifPanelOpen;
        const panel = document.getElementById('notif-panel');
        const overlay = document.getElementById('notif-overlay');
        const chevron = document.getElementById('sidebar-notif-chevron');

        if (notifPanelOpen) {
            panel.style.left = '0';
            overlay.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
            closeMobileDrawer();
            sidebarFetchNotifications(); // fresh list pagbukas
        } else {
            panel.style.left = '-420px';
            overlay.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
        }
    }

    function sidebarFetchNotifications() {
        fetch(BASE_URL + '/fetchnotifications', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                if (window.GlobalNotif) window.GlobalNotif.process(data); // toast + sound

                const list = document.getElementById('sidebar-notif-list');
                if (!data.length) {
                    list.innerHTML = `<div class="px-4 py-8 text-center text-black text-[11px]">
                        <i class="fa-regular fa-bell mb-2 block text-2xl"></i>No notifications</div>`;
                    return;
                }

                list.innerHTML = data.map(n => {
                    // I-adjust kung ano ang tunay na "ping" sa system mo
                    const isPing = n.type === 'ping';
                    const unread = n.is_read == 0;
                    const typeLabel = sidebarNotifTypeLabel(n.type);

                    let message = esc(n.message);
                    if (n.control_no) {
                        message = message.replace(esc(n.control_no),
                            `<span style="background:#fff3e0;color:#c2410c;font-weight:600;padding:1px 5px;border-radius:4px;font-size:10px;">${esc(n.control_no)}</span>`);
                    }

                    return `
<div data-id="${esc(n.id)}" data-link="${esc(n.link ?? '')}" data-request="${esc(n.request_id ?? 0)}" data-date="${esc(n.date_requested ?? '')}"
     class="notif-item flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 ${unread
                            ? (isPing ? 'bg-red-50 border-l-[3px] border-red-400' : 'bg-orange-50 border-l-[3px] border-orange-400')
                            : 'border-l-[3px] border-transparent'}">
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold ${unread ? 'text-gray-900' : 'text-gray-700'}">${esc(n.control_no ?? '')}</p>
            ${typeLabel ? `<span class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded ${typeLabel === 'CRM' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'}">${typeLabel}</span>` : ''}
        </div>
        ${n.sender_name ? `<p class="text-[10px] ${isPing ? 'text-red-400' : 'text-orange-400'} font-medium mt-0.5">${esc(n.sender_name)}</p>` : ''}
        <p class="text-[11px] ${unread ? 'text-gray-600' : 'text-gray-400'} leading-snug mt-0.5">${message}</p>
        <p class="text-[10px] text-gray-400 mt-1">${sidebarTimeAgo(n.created_at)}</p>
    </div>
    ${unread ? `<div class="w-2 h-2 ${isPing ? 'bg-red-500' : 'bg-orange-500'} rounded-full mt-1.5 shrink-0"></div>` : ''}
</div>`;
                }).join('');
            })
            .catch(err => console.error('Notif error:', err));
    }

    // Event delegation: walang inline onclick, kaya walang XSS sa link
    document.getElementById('sidebar-notif-list').addEventListener('click', e => {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        sidebarClickNotif(Number(item.dataset.id), item.dataset.link, Number(item.dataset.request), item.dataset.date);
    });

    function sidebarNotifTypeLabel(type) {
        if (type === 'crm_inquiry' || type === 'crm_2dquotation') return 'CRM';
        if (type === 'budget') return 'Budget';
        return null;
    }

    function sidebarClickNotif(id, link, requestId, requestDate) {
        fetch(BASE_URL + '/marknotificationsread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(() => {
            // tanggapin lang ang internal links (nagsisimula sa "/")
            if (link && link.startsWith('/') && !link.startsWith('//')) {
                const separator = link.includes('?') ? '&' : '?';
                let url = BASE_URL + link + separator + 'highlight=' + encodeURIComponent(requestId);
                if (requestDate) url += '&date=' + encodeURIComponent(requestDate);
                window.location.href = url;
            } else {
                lastNotifSig = null;
                pollSidebar();
            }
        });
    }

    function sidebarMarkAllRead() {
        fetch(BASE_URL + '/marknotificationsread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: 0 })
        }).then(() => { lastNotifSig = null; pollSidebar(); });
    }

    function sidebarTimeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }
</script>