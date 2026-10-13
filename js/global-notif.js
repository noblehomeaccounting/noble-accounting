(function () {
    let toastContainer = null;
    const SEEN_IDS_KEY = 'notif_seen_ids';
    const LOGIN_USER_KEY = 'notif_login_user';
    const MAX_SEEN = 200;

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

    function init() {
        toastContainer = document.createElement('div');
        toastContainer.id = 'global-toast-container';
        toastContainer.style.cssText = `
            position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
            z-index: 99999; display: flex; flex-direction: column;
            align-items: center; gap: 8px; pointer-events: none;`;
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

    function showToast(notif, onDone) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            background:#111; border-left:2px solid #ffa200; border-radius:5px;
            padding:12px 16px; min-width:280px; max-width:480px;
            box-shadow:0 4px 24px rgba(0,0,0,.4); pointer-events:none;
            cursor:default; position:relative; animation:slideIn .3s ease;`;

        toast.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <div style="width:32px;height:32px;border-radius:50%;background:rgba(245,158,11,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-regular fa-bell" style="color:#f59e0b;font-size:14px;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:600;color:#fff;">${esc(notif.control_no ?? 'New Request')}</div>
                    ${notif.sender_name ? `<span style="color:#fff;font-size:11px;font-weight:500;">${esc(notif.sender_name)}</span>` : ''}
                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(notif.message ?? '')}</div>
                    <div style="font-size:10px;color:#6b7280;margin-top:4px;">${esc(timeAgoStr(notif.created_at))}</div>
                </div>
            </div>`;

        if (!document.getElementById('toast-anim-style')) {
            const style = document.createElement('style');
            style.id = 'toast-anim-style';
            style.textContent = `
                @keyframes slideIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
                @keyframes fadeOut { from { opacity:1; } to { opacity:0; } }`;
            document.head.appendChild(style);
        }

        toastContainer.appendChild(toast);
        toast._autoTimer = setTimeout(() => dismissToast(toast, onDone), 5000);
    }

    function dismissToast(toast, onDone) {
        if (!toast.isConnected) { if (onDone) onDone(); return; }
        clearTimeout(toast._autoTimer);
        toast.style.animation = 'fadeOut .3s ease forwards';
        setTimeout(() => { toast.remove(); if (onDone) onDone(); }, 300);
    }

    function timeAgoStr(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
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