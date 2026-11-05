// push-client.js - humihingi ng pahintulot at nagse-save ng subscription ng device
//   Kailangan: window.NH_PUSH_CFG (galing sa network/push-include.php)
//   Para sa settings button:  NHPush.enable()  ·  NHPush.disable()  ·  NHPush.status()
//   Pang-test:  NHPush.test()  (sa Console)  o buksan ang kahit anong page na may ?pushtest=1
(function () {
    'use strict';
    const C = window.NH_PUSH_CFG;
    if (!C) return;

    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const why = msg => console.info('[NH push] ' + msg);

    // iPhone sa Safari (hindi pa naka-Add to Home Screen): walang PushManager dito, kaya ipakita ang paalala
    if (!supported) {
        window.NHPush = { panel: () => (isIOS && !standalone) ? iosHint(true)
            : miniToast('This browser does not support notifications. Use Chrome (Android / computer) or Safari from the Home Screen (iPhone).', true) };
        if (isIOS && !standalone) {
            why('iPhone: open NobleHome from the Home Screen to turn on notifications.');
            const go = () => setTimeout(iosHint, 2500);
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
        } else why('this browser does not support push notifications.');
        return;
    }
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
        const text = await res.text();
        let d = null;
        try { d = JSON.parse(text); } catch (e) { }
        if (!d) {
            // hindi JSON ang sagot → kadalasan kulang ang route o naka-logout
            why(`save failed: HTTP ${res.status} from ${C.save} - not JSON. First 200 chars: ` + text.slice(0, 200));
            throw new Error(res.status === 404 || /<html|<!doctype/i.test(text)
                ? `Could not save: route "${C.save.split('/').pop()}" not found. Add it to the routes file.`
                : `Could not save (HTTP ${res.status}).`);
        }
        if (!d.success) { why('save failed: ' + JSON.stringify(d)); throw new Error(d.message || 'Could not save.'); }
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

    async function test() {
        const r = await reg();
        const sub = await r.pushManager.getSubscription();
        if (!sub) { toast('Turn on notifications first.', true); return false; }
        const res = await fetch(C.save, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ action: 'test', subscription: sub.toJSON() }),
        });
        const d = await res.json().catch(() => ({}));
        toast(d.message || (d.success ? 'Test sent.' : 'Could not send.'), !d.success);
        return !!d.success;
    }

    // ── "Phone notifications" panel: status + Turn on / Send test / Turn off ──
    let panelEl = null;
    async function panel() {
        if (panelEl) panelEl.remove();
        hideBanner();
        const st = await status().catch(() => 'error');
        const info = {
            on:      ['On for this device', '#047857', 'You will get alerts here, even when the site is closed or you are logged out.'],
            off:     ['Off for this device', '#b45309', 'Allowed in the browser, but this device is not saved yet.'],
            default: ['Not turned on', '#b45309', 'Turn it on to get alerts on this device.'],
            denied:  ['Blocked by the browser', '#b91c1c', 'Click the icon on the left of the address bar &rarr; Site settings &rarr; Notifications &rarr; Allow, then reload.'],
            error:   ['Something went wrong', '#b91c1c', 'Reload the page and try again.'],
        }[st] || ['Unknown', '#6b7280', ''];
        panelEl = document.createElement('div');
        panelEl.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:10050;max-width:360px;width:calc(100vw - 32px);background:#fff;color:#111827;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 16px 40px rgba(0,0,0,.2);overflow:hidden';
        const btn = (id, label, primary) => `<button type="button" data-p="${id}" style="height:34px;padding:0 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;${primary ? 'background:#b45309;color:#fff;border:0' : 'background:#fff;color:#374151;border:1px solid #e5e7eb'}">${label}</button>`;
        panelEl.innerHTML = `
            <div style="height:4px;background:repeating-linear-gradient(-45deg,#f59e0b 0 8px,#1f2937 8px 16px)"></div>
            <div style="padding:14px 16px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                    <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280">Phone notifications</p>
                    <button type="button" data-p="close" style="width:28px;height:28px;border:0;background:transparent;color:#9ca3af;font-size:18px;cursor:pointer">&times;</button>
                </div>
                <p style="margin:6px 0 0;font-size:14px;font-weight:700;color:${info[1]}">&#9679; ${info[0]}</p>
                <p style="margin:4px 0 0;font-size:12px;color:#6b7280;line-height:1.45">${info[2]}</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
                    ${st === 'on' ? btn('test', 'Send test', true) + btn('off', 'Turn off') : st === 'denied' ? '' : btn('on', 'Turn on', true)}
                </div>
            </div>`;
        panelEl.addEventListener('click', async e => {
            const b = e.target.closest('[data-p]'); if (!b) return;
            const a = b.dataset.p;
            if (a === 'close') { panelEl.remove(); panelEl = null; return; }
            b.disabled = true;
            try {
                if (a === 'on') { await enable(); }
                if (a === 'test') { await test(); }
                if (a === 'off') { await disable(); }
            } catch (err) { toast(err.message || 'Something went wrong.', true); }
            if (a !== 'test') setTimeout(panel, 300); else b.disabled = false;
        });
        document.body.appendChild(panelEl);
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
                        ? 'On iPhone, tap <b>Share</b> &rarr; <b>Add to Home Screen</b>, open NobleHome from there, then turn on notifications.'
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
            why('permission = ' + Notification.permission);
            const wantPanel = new URLSearchParams(location.search).get('pushtest') === '1';
            if (Notification.permission === 'granted') {
                // naka-on na: siguraduhing naka-save sa account na naka-login ngayon
                const r = await reg();
                const sub = await r.pushManager.getSubscription();
                if (!sub || ls.get(SENT_KEY) !== sub.endpoint) await subscribe();
                if (wantPanel) setTimeout(panel, 600);
                return;
            }
            if (wantPanel) { setTimeout(panel, 600); return; }
            if (Notification.permission === 'denied') { why('blocked in browser settings - allow Notifications for this site, then reload.'); return; }
            const dismissed = +(ls.get(DISMISS_KEY) || 0);
            const force = new URLSearchParams(location.search).get('pushtest') === '1';
            if (!force && Date.now() - dismissed < 7 * 86400000) { why('banner hidden ("Not now" was pressed). Run NHPush.enable() or open with ?pushtest=1'); return; }
            setTimeout(() => showBanner(isIOS && !standalone ? 'ios' : 'ask'), 2500);
        } catch (e) { console.warn('NHPush:', e); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();

    window.NHPush = { enable, disable, status, test, panel };

    // (hoisted) paalala sa iPhone na hindi pa naka-Home Screen - kahit walang PushManager
    function miniToast(msg, bad) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `position:fixed;left:16px;bottom:16px;z-index:10051;max-width:360px;padding:12px 14px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);${bad ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca' : 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0'}`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 6000);
    }

    function iosHint(force) {
        try {
            if (!force && localStorage.getItem('nh-push-ioshint') && Date.now() - +localStorage.getItem('nh-push-ioshint') < 7 * 86400000) return;
        } catch (e) { }
        const b = document.createElement('div');
        b.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:10050;background:#111827;color:#fff;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.3);overflow:hidden';
        b.innerHTML = '<div style="height:4px;background:repeating-linear-gradient(-45deg,#f59e0b 0 8px,#1f2937 8px 16px)"></div>'
            + '<div style="padding:14px 16px"><p style="margin:0;font-size:13px;font-weight:700">Get NobleHome notifications on iPhone</p>'
            + '<p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.7);line-height:1.45">Tap <b>Share</b> &rarr; <b>Add to Home Screen</b>, then open NobleHome from your Home Screen and turn on notifications.</p>'
            + '<button type="button" style="margin-top:10px;height:32px;padding:0 14px;border-radius:8px;background:rgba(255,255,255,.12);color:#fff;font-size:12px;font-weight:600;border:0">OK</button></div>';
        b.querySelector('button').onclick = () => { try { localStorage.setItem('nh-push-ioshint', String(Date.now())); } catch (e) { } b.remove(); };
        document.body.appendChild(b);
    }
})();