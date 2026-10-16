(function () {
    let toastContainer = null;
    const SEEN_IDS_KEY = 'notif_seen_ids';
    const LOGIN_USER_KEY = 'notif_login_user';
    const MAX_SEEN = 200;
    const TOAST_MS = 5000;

    let toastQueue = [];
    let isShowingToast = false;

    const currentUser = String(typeof CURRENT_USER_ID !== 'undefined' ? CURRENT_USER_ID : 0);
    if (sessionStorage.getItem(LOGIN_USER_KEY) !== currentUser) {
        sessionStorage.removeItem(SEEN_IDS_KEY);
        sessionStorage.setItem(LOGIN_USER_KEY, currentUser);
    }

    const esc = s => String(s ?? '').replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function getSeenIds() {
        try { return new Set(JSON.parse(sessionStorage.getItem(SEEN_IDS_KEY) || '[]')); }
        catch { return new Set(); }
    }

    function addSeenIds(ids) {
        const seen = getSeenIds();
        ids.forEach(id => seen.add(id));
        sessionStorage.setItem(SEEN_IDS_KEY, JSON.stringify([...seen].slice(-MAX_SEEN)));
    }

    // ---------- styles (isang beses lang ini-inject) ----------
    function injectStyles() {
        if (document.getElementById('nh-toast-style')) return;
        const style = document.createElement('style');
        style.id = 'nh-toast-style';
        style.textContent = `
            #global-toast-container{
                position:fixed; top:20px; right:20px; z-index:99999;
                display:flex; flex-direction:column; align-items:flex-end; gap:12px;
                pointer-events:none;
            }
            .nh-toast{
                --tone:#c2410c; --tone-bg:#fff4e6; --tone-ring:#fed7aa;
                position:relative; overflow:hidden;
                width:390px; max-width:calc(100vw - 40px);
                background:#fff; color:#111827;
                border:1px solid #e7e5e4; border-radius:12px;
                box-shadow:0 18px 40px -16px rgba(15,23,42,.28), 0 2px 6px rgba(15,23,42,.06);
                pointer-events:auto; font-family:inherit;
                animation:nhToastIn .35s cubic-bezier(.16,1,.3,1) both;
            }
            .nh-toast.is-approved{ --tone:#15803d; --tone-bg:#ecfdf3; --tone-ring:#bbf7d0; }
            .nh-toast.is-rejected{ --tone:#b91c1c; --tone-bg:#fef2f2; --tone-ring:#fecaca; }
            .nh-toast.is-leaving{ animation:nhToastOut .22s ease-in forwards; }

            /* header */
            .nh-toast-header{
                display:flex; align-items:center; gap:8px;
                padding:8px 10px 8px 14px;
                background:linear-gradient(90deg,#fff7ed 0%,#fafafa 60%);
                border-bottom:1px solid #f1f1f1;
            }
            .nh-toast-brand{ display:flex; align-items:center; gap:6px;
                font-size:10.5px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#333; }
            .nh-toast-brand svg{ width:16px; height:16px; display:block; }
            .nh-toast-brand span{ color:#F7941D; margin-left:-7px; }
            .nh-toast-time{ margin-left:auto; font-size:11px; color:#9ca3af; font-variant-numeric:tabular-nums; }
            .nh-toast-close{
                width:22px; height:22px; border:0; border-radius:6px; background:transparent; color:#9ca3af;
                cursor:pointer; font-size:11px; display:flex; align-items:center; justify-content:center;
                transition:background .15s, color .15s;
            }
            .nh-toast-close:hover{ background:#ececec; color:#111827; }
            .nh-toast-close:focus-visible{ outline:2px solid #F7941D; outline-offset:1px; }

            /* body */
            .nh-toast-body{ display:flex; gap:12px; padding:14px 16px 14px 14px; }
            .nh-toast-icon{
                flex-shrink:0; width:38px; height:38px; border-radius:10px;
                background:var(--tone-bg); color:var(--tone);
                box-shadow:inset 0 0 0 1px var(--tone-ring);
                display:flex; align-items:center; justify-content:center; font-size:15px;
            }
            .nh-toast-main{ flex:1; min-width:0; }
            .nh-toast-row{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
            .nh-toast-title{ font-size:14px; font-weight:700; color:#111827; font-variant-numeric:tabular-nums; letter-spacing:.2px; }
            .nh-toast-pill{
                display:inline-flex; align-items:center; gap:5px;
                padding:2px 8px; border-radius:999px;
                background:var(--tone-bg); color:var(--tone);
                box-shadow:inset 0 0 0 1px var(--tone-ring);
                font-size:10.5px; font-weight:600; letter-spacing:.3px; text-transform:uppercase;
            }
            .nh-toast-pill::before{ content:''; width:6px; height:6px; border-radius:50%; background:var(--tone); }
            .nh-toast-msg{
                margin-top:6px; font-size:13px; line-height:1.5; color:#4b5563;
                display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;
                word-break:break-word;
            }
            .nh-toast-from{ margin-top:10px; display:flex; align-items:center; gap:7px; font-size:12px; color:#6b7280; }
            .nh-toast-avatar{
                width:20px; height:20px; border-radius:50%;
                background:#333; color:#fff; font-size:10px; font-weight:600; text-transform:uppercase;
                display:flex; align-items:center; justify-content:center;
                box-shadow:0 0 0 2px #fff, 0 0 0 3px #F7941D;
            }
            .nh-toast-from b{ color:#111827; font-weight:600; }

            /* timer line */
            .nh-toast-bar{
                position:absolute; left:0; bottom:0; height:2px; width:100%;
                background:#F7941D; transform-origin:left center;
                animation:nhToastBar linear forwards;
            }
            .nh-toast:hover .nh-toast-bar{ animation-play-state:paused; }

            @keyframes nhToastIn{ from{opacity:0; transform:translateY(-10px) scale(.98);} to{opacity:1; transform:none;} }
            @keyframes nhToastOut{ to{opacity:0; transform:translateY(-8px) scale(.98);} }
            @keyframes nhToastBar{ from{transform:scaleX(1);} to{transform:scaleX(0);} }

            @media (max-width:520px){
                #global-toast-container{ left:12px; right:12px; top:12px; align-items:stretch; }
                .nh-toast{ width:auto; max-width:none; }
            }
            @media (prefers-reduced-motion:reduce){
                .nh-toast, .nh-toast.is-leaving, .nh-toast-bar{ animation:none; }
            }`;
        document.head.appendChild(style);
    }

    function init() {
        injectStyles();
        toastContainer = document.createElement('div');
        toastContainer.id = 'global-toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastContainer);
        // WALA nang polling dito. Ang sidebar na ang nag-fetch.
    }

    // Tinatawag ng sidebar dala ang data na na-fetch na niya
    function processNotifications(data) {
        if (!Array.isArray(data)) return;
        const seenIds = getSeenIds();
        const toShow = data.filter(n => n.is_read == 0 && !seenIds.has(n.id));
        if (toShow.length > 0) {
            toShow.forEach(n => toastQueue.push(n));
            addSeenIds(toShow.map(n => n.id));
            processQueue();
        }
    }

    function processQueue() {
        if (isShowingToast || toastQueue.length === 0) return;
        isShowingToast = true;
        const notif = toastQueue.shift();
        playSound();
        showToast(notif, () => {
            isShowingToast = false;
            setTimeout(processQueue, 400);
        });
    }

    // kulay at icon depende sa laman ng message
    function toastKind(msg) {
        const m = String(msg || '').toLowerCase();
        if (/\breject|declin|disapprov/.test(m)) return { cls: 'is-rejected', icon: 'fa-solid fa-xmark', head: 'Rejected' };
        if (/\bapprov/.test(m)) return { cls: 'is-approved', icon: 'fa-solid fa-check', head: 'Approved' };
        return { cls: '', icon: 'fa-regular fa-bell', head: 'New' };
    }

    function showToast(notif, onDone) {
        const kind = toastKind(notif.message);
        const sender = notif.sender_name ? String(notif.sender_name) : '';

        const toast = document.createElement('div');
        toast.className = 'nh-toast ' + kind.cls;
        toast.setAttribute('role', 'status');
        const logo = `<svg viewBox="0 0 1932 1932" aria-hidden="true">
            <polygon fill="#F7941D" points="72,695 962,72 1472,432 1475,280 1605,280 1605,530 1860,715 1785,818 962,232 145,802"/>
            <polygon fill="#F7941D" points="267,1568 267,827 338,775 785,1330 790,458 920,368 920,1568 793,1568 398,1060 397,1568"/>
            <polygon fill="#333" points="412,720 702,518 706,1088"/>
            <polygon fill="#333" points="1005,348 1135,438 1135,950 1458,950 1458,670 1588,762 1588,1568 1458,1568 1458,1080 1135,1080 1135,1568 1005,1568"/></svg>`;

        toast.innerHTML = `
            <div class="nh-toast-header">
                <div class="nh-toast-brand">${logo}Noble<span>Home</span></div>
                <div class="nh-toast-time">${esc(timeAgoStr(notif.created_at))}</div>
                <button type="button" class="nh-toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="nh-toast-body">
                <div class="nh-toast-icon"><i class="${kind.icon}"></i></div>
                <div class="nh-toast-main">
                    <div class="nh-toast-row">
                        <span class="nh-toast-title">${esc(notif.control_no ?? 'Notification')}</span>
                        <span class="nh-toast-pill">${kind.head}</span>
                    </div>
                    <div class="nh-toast-msg">${esc(notif.message ?? '')}</div>
                    ${sender ? `
                    <div class="nh-toast-from">
                        <span class="nh-toast-avatar">${esc(sender.trim().charAt(0))}</span>
                        <span>by <b>${esc(sender)}</b></span>
                    </div>` : ''}
                </div>
            </div>
            <div class="nh-toast-bar" style="animation-duration:${TOAST_MS}ms"></div>`;

        toastContainer.appendChild(toast);

        // auto-dismiss; humihinto habang naka-hover
        let remaining = TOAST_MS;
        let startedAt = Date.now();
        const start = () => {
            startedAt = Date.now();
            toast._autoTimer = setTimeout(() => dismissToast(toast, onDone), remaining);
        };
        toast.addEventListener('mouseenter', () => {
            clearTimeout(toast._autoTimer);
            remaining -= Date.now() - startedAt;
        });
        toast.addEventListener('mouseleave', start);
        toast.querySelector('.nh-toast-close').addEventListener('click', () => dismissToast(toast, onDone));
        start();
    }

    function dismissToast(toast, onDone) {
        if (toast._leaving) return;
        toast._leaving = true;
        if (!toast.isConnected) { if (onDone) onDone(); return; }
        clearTimeout(toast._autoTimer);
        toast.classList.add('is-leaving');
        setTimeout(() => { toast.remove(); if (onDone) onDone(); }, 280);
    }

    function timeAgoStr(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (isNaN(diff)) return '';
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function playSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start(); osc.stop(ctx.currentTime + 0.3);
        } catch (e) { }
    }

    window.GlobalNotif = { process: processNotifications };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();