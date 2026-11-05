// push-client.js — humihingi ng pahintulot at nagse-save ng subscription ng device
//   Kailangan: window.NH_PUSH_CFG (galing sa network/push-include.php)
//   Para sa settings button:  NHPush.enable()  ·  NHPush.disable()  ·  NHPush.status()
(function () {
    'use strict';
    const C = window.NH_PUSH_CFG;
    if (!C || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;

    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const DISMISS_KEY = 'nh-push-dismiss-' + C.type;
    const SENT_KEY = 'nh-push-sent-' + C.type + '-' + C.uid;

    const b64 = s => {
        const pad = '='.repeat((4 - s.length % 4) % 4);
        const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    };
    const ls = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { } } };

    let regPromise = null;
    const reg = () => regPromise || (regPromise = navigator.serviceWorker.register(C.sw, { scope: C.base + '/' }));

    async function save(sub, action = 'subscribe') {
        const res = await fetch(C.save, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ action, subscription: sub.toJSON ? sub.toJSON() : sub }),
        });
        const d = await res.json().catch(() => ({}));
        if (!d.success) throw new Error(d.message || 'Could not save.');
    }

    async function subscribe() {
        const r = await reg();
        await navigator.serviceWorker.ready;
        let sub = await r.pushManager.getSubscription();
        if (!sub) sub = await r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(C.key) });
        await save(sub);
        ls.set(SENT_KEY, sub.endpoint);
        return sub;
    }

    async function enable() {
        if (isIOS && !standalone) { showBanner('ios'); return false; }
        const p = await Notification.requestPermission();   // dapat galing sa pindot ng user
        if (p !== 'granted') { hideBanner(); ls.set(DISMISS_KEY, String(Date.now())); return false; }
        await subscribe();
        hideBanner();
        toast('Notifications are on for this device.');
        return true;
    }

    async function disable() {
        const r = await reg();
        const sub = await r.pushManager.getSubscription();
        if (sub) { try { await save(sub, 'unsubscribe'); } catch (e) { } await sub.unsubscribe(); }
        ls.set(SENT_KEY, '');
        toast('Notifications are off for this device.');
    }

    async function status() {
        if (Notification.permission !== 'granted') return Notification.permission;
        const r = await reg();
        return (await r.pushManager.getSubscription()) ? 'on' : 'off';
    }

    // ── maliit na banner (bottom-left) ──
    let banner = null;
    function hideBanner() { if (banner) { banner.remove(); banner = null; } }
    function showBanner(kind) {
        hideBanner();
        banner = document.createElement('div');
        banner.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:10050;max-width:340px;width:calc(100vw - 32px);background:#111827;color:#fff;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.3);overflow:hidden;font-family:inherit';
        const ios = kind === 'ios';
        banner.innerHTML = `
            <div style="height:4px;background:repeating-linear-gradient(-45deg,#f59e0b 0 8px,#1f2937 8px 16px)"></div>
            <div style="padding:14px 16px;display:flex;gap:12px;align-items:flex-start">
                <div style="width:36px;height:36px;border-radius:10px;background:#f59e0b;color:#111827;display:flex;align-items:center;justify-content:center;flex:none"><i class="fa-solid fa-bell"></i></div>
                <div style="flex:1;min-width:0">
                    <p style="margin:0;font-size:13px;font-weight:700">${ios ? 'Add NobleHome to your Home Screen' : 'Get notified on this device?'}</p>
                    <p style="margin:3px 0 0;font-size:11.5px;color:rgba(255,255,255,.65);line-height:1.45">${ios
                        ? 'On iPhone, tap <b>Share</b> → <b>Add to Home Screen</b>, open NobleHome from there, then turn on notifications.'
                        : 'We will alert you about memos, approvals and updates, even when the site is closed.'}</p>
                    <div style="display:flex;gap:8px;margin-top:10px">
                        ${ios ? '' : '<button type="button" data-push="on" style="height:32px;padding:0 14px;border-radius:8px;background:#f59e0b;color:#111827;font-size:12px;font-weight:700;border:0;cursor:pointer">Turn on</button>'}
                        <button type="button" data-push="later" style="height:32px;padding:0 12px;border-radius:8px;background:rgba(255,255,255,.1);color:#fff;font-size:12px;font-weight:600;border:0;cursor:pointer">${ios ? 'OK' : 'Not now'}</button>
                    </div>
                </div>
            </div>`;
        banner.addEventListener('click', e => {
            const b = e.target.closest('[data-push]'); if (!b) return;
            if (b.dataset.push === 'on') enable().catch(err => toast(err.message || 'Could not turn on notifications.', true));
            else { ls.set(DISMISS_KEY, String(Date.now())); hideBanner(); }
        });
        document.body.appendChild(banner);
    }

    function toast(msg, bad) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `position:fixed;left:16px;bottom:16px;z-index:10051;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);${bad ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca' : 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0'}`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ── simula ──
    async function init() {
        try {
            if (Notification.permission === 'granted') {
                // naka-on na: siguraduhing naka-save sa account na naka-login ngayon
                const r = await reg();
                const sub = await r.pushManager.getSubscription();
                if (!sub || ls.get(SENT_KEY) !== sub.endpoint) await subscribe();
                return;
            }
            if (Notification.permission === 'denied') return;      // pinatay sa browser settings
            const dismissed = +(ls.get(DISMISS_KEY) || 0);
            if (Date.now() - dismissed < 7 * 86400000) return;      // "Not now" → tanungin ulit pagkalipas ng 7 araw
            setTimeout(() => showBanner(isIOS && !standalone ? 'ios' : 'ask'), 2500);
        } catch (e) { console.warn('NHPush:', e); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

    window.NHPush = { enable, disable, status };
})();