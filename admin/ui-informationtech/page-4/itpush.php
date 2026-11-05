<?php
// itpush.php (route: itpush) — IT: Phone notifications (on/off, devices, test)
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_IT];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phone Notifications</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .ip-switch { position: relative; width: 46px; height: 26px; border-radius: 999px; background: #d1d5db; transition: background .2s; flex: none; cursor: pointer; border: 0; }
        .ip-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.25); transition: transform .2s; }
        .ip-switch[aria-checked="true"] { background: #b45309; }
        .ip-switch[aria-checked="true"]::after { transform: translateX(20px); }
        .ip-switch:disabled { opacity: .5; cursor: wait; }
        .ip-switch:focus-visible { outline: 2px solid #f59e0b; outline-offset: 2px; }
    </style>
</head>

<body class="bg-slate-100">

    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8">
        <div class="max-w-7xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-xl mb-4 overflow-hidden">
                <div class="h-1.5" style="background:repeating-linear-gradient(-45deg,#f59e0b 0 10px,#1f2937 10px 20px)"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-mobile-screen-button text-lg"></i></span>
                        <div>
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">IT · Maintenance</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Phone Notifications</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Turn push alerts on or off and test any device.</p>
                        </div>
                    </div>
                    <div id="ip-server" class="text-[11px] font-semibold px-3 py-1.5 rounded-full bg-gray-100 text-gray-500">Checking server…</div>
                </div>
                <div id="ip-warn" class="hidden mx-5 mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs"></div>
            </section>

            <!-- ═══ ON / OFF ═══ -->
            <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <?php foreach ([
                    ['user', 'fa-solid fa-user', 'Request Portal', 'Employees (nobleaccount): memos, request updates.'],
                    ['admin', 'fa-solid fa-building', 'Departments', 'Admin accounts (noblerole): approvals, notifications.'],
                ] as [$t, $ic, $lbl, $desc]): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 flex items-center gap-4">
                    <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0"><i class="<?= $ic ?>"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900"><?= $lbl ?></p>
                        <p class="text-[11px] text-gray-400 mt-0.5"><?= $desc ?></p>
                        <p class="text-[11px] mt-1.5"><span id="ip-devcount-<?= $t ?>" class="font-semibold text-gray-700">0</span> <span class="text-gray-400">device(s) on</span>
                            · <span id="ip-state-<?= $t ?>" class="font-semibold">…</span></p>
                    </div>
                    <button type="button" class="ip-switch" role="switch" aria-checked="false" data-type="<?= $t ?>" aria-label="<?= $lbl ?> notifications"></button>
                </div>
                <?php endforeach; ?>
            </section>

            <!-- ═══ DEVICES ═══ -->
            <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
                    <div class="flex bg-slate-100 rounded-lg p-1 text-xs font-semibold" id="ip-tabs">
                        <button type="button" data-tab="all" class="px-3 py-1.5 rounded-md bg-white shadow-sm text-gray-900">All</button>
                        <button type="button" data-tab="user" class="px-3 py-1.5 rounded-md text-gray-500">Request Portal</button>
                        <button type="button" data-tab="admin" class="px-3 py-1.5 rounded-md text-gray-500">Departments</button>
                    </div>
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                        <input id="ip-search" type="search" placeholder="Search name, role, device…" class="w-full h-9 pl-8 pr-3 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400">
                    </div>
                    <button type="button" id="ip-refresh" class="h-9 px-3 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50"><i class="fa-solid fa-rotate mr-1"></i>Refresh</button>
                </div>

                <div class="overflow-auto max-h-[60vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-gray-400 sticky top-0 z-10">
                            <tr>
                                <th class="text-left font-semibold px-4 py-2.5">Account</th>
                                <th class="text-left font-semibold px-4 py-2.5">Device</th>
                                <th class="text-left font-semibold px-4 py-2.5">Last seen</th>
                                <th class="text-left font-semibold px-4 py-2.5">Last push</th>
                                <th class="text-right font-semibold px-4 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ip-body">
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
    (function () {
        const API = '<?= BASE_URL ?>/itpushajax';
        const h = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let devices = [], tab = 'all';

        function note(msg, bad) {
            const t = document.createElement('div');
            t.textContent = msg;
            t.style.cssText = `position:fixed;right:16px;bottom:16px;z-index:10060;max-width:360px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.15);${bad ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca' : 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0'}`;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }

        async function call(body) {
            const res = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(body) });
            const d = await res.json().catch(() => null);
            if (!d) throw new Error(`Server error (HTTP ${res.status}). Check the route "itpushajax".`);
            return d;
        }

        function ago(s) {
            if (!s) return '<span class="text-gray-300">—</span>';
            const d = new Date(s.replace(' ', 'T'));
            const sec = Math.floor((Date.now() - d) / 1000);
            const txt = isNaN(sec) ? s : sec < 60 ? 'just now' : sec < 3600 ? Math.floor(sec / 60) + 'm ago' : sec < 86400 ? Math.floor(sec / 3600) + 'h ago' : Math.floor(sec / 86400) + 'd ago';
            return `<span title="${h(s)}">${h(txt)}</span>`;
        }

        function render() {
            const q = document.getElementById('ip-search').value.trim().toLowerCase();
            const list = devices.filter(d => (tab === 'all' || d.type === tab)
                && (!q || `${d.name} ${d.role} ${d.position} ${d.os} ${d.browser}`.toLowerCase().includes(q)));
            const body = document.getElementById('ip-body');
            if (!list.length) {
                body.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">${devices.length ? 'No device matches your search.' : 'No device has notifications on yet.'}</td></tr>`;
                return;
            }
            body.innerHTML = list.map(d => `
                <tr class="border-t border-gray-100 hover:bg-amber-50/40">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-gray-900">${h(d.name)}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            <span class="inline-block px-1.5 py-px rounded ${d.type === 'admin' ? 'bg-slate-800 text-white' : 'bg-amber-100 text-amber-800'} text-[10px] font-semibold uppercase tracking-wide">${d.type === 'admin' ? 'Dept' : 'Portal'}</span>
                            ${h([d.role, d.position].filter(Boolean).join(' · '))}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                        <i class="fa-solid ${d.mobile ? 'fa-mobile-screen' : 'fa-desktop'} text-gray-400 mr-1.5"></i>${h(d.browser)} · ${h(d.os)}
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${ago(d.last_seen_at || d.created_at)}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${ago(d.last_push_at)}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button type="button" data-act="test" data-type="${d.type}" data-aid="${d.account_id}" class="h-8 px-3 rounded-lg bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold"><i class="fa-solid fa-paper-plane mr-1"></i>Test</button>
                        <button type="button" data-act="remove" data-id="${d.id}" data-name="${h(d.name)}" class="h-8 w-8 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 text-xs" title="Remove device" aria-label="Remove device"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`).join('');
        }

        function setSwitch(type, on) {
            const sw = document.querySelector(`.ip-switch[data-type="${type}"]`);
            sw.setAttribute('aria-checked', on ? 'true' : 'false');
            const st = document.getElementById('ip-state-' + type);
            st.textContent = on ? 'On' : 'Off';
            st.className = 'font-semibold ' + (on ? 'text-emerald-600' : 'text-red-600');
        }

        async function load() {
            try {
                const d = await call({ action: 'list' });
                if (!d.success) throw new Error(d.message || 'Could not load.');
                devices = d.devices || [];
                setSwitch('user', d.settings.user);
                setSwitch('admin', d.settings.admin);
                ['user', 'admin'].forEach(t => document.getElementById('ip-devcount-' + t).textContent = devices.filter(x => x.type === t).length);

                const srv = document.getElementById('ip-server');
                srv.textContent = d.server ? '● Server ready' : '● Server not set up';
                srv.className = 'text-[11px] font-semibold px-3 py-1.5 rounded-full ' + (d.server ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');

                const warn = document.getElementById('ip-warn');
                const msgs = [];
                if (!d.server) msgs.push('VAPID keys or vendor/minishlink/web-push are missing on this server.');
                if (!d.settings.ready) msgs.push('Run push-setting.sql in phpMyAdmin so the on/off switches are saved.');
                warn.classList.toggle('hidden', !msgs.length);
                warn.innerHTML = msgs.map(h).join('<br>');
                render();
            } catch (e) {
                document.getElementById('ip-body').innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-red-500 text-sm">${h(e.message)}</td></tr>`;
            }
        }

        document.querySelectorAll('.ip-switch').forEach(sw => sw.addEventListener('click', async () => {
            const type = sw.dataset.type, on = sw.getAttribute('aria-checked') !== 'true';
            sw.disabled = true;
            try {
                const d = await call({ action: 'toggle', type, on });
                note(d.message || (d.success ? 'Saved.' : 'Could not save.'), !d.success);
                if (d.success) setSwitch(type, on);
            } catch (e) { note(e.message, true); }
            sw.disabled = false;
        }));

        document.getElementById('ip-body').addEventListener('click', async e => {
            const b = e.target.closest('[data-act]'); if (!b) return;
            if (b.dataset.act === 'remove' && !confirm(`Remove this device of ${b.dataset.name}? It will stop getting notifications.`)) return;
            b.disabled = true;
            try {
                const d = b.dataset.act === 'test'
                    ? await call({ action: 'test', type: b.dataset.type, account_id: +b.dataset.aid })
                    : await call({ action: 'remove', id: +b.dataset.id });
                note(d.message || (d.success ? 'Done.' : 'Failed.'), !d.success);
                if (d.success) load();
            } catch (err) { note(err.message, true); }
            b.disabled = false;
        });

        document.getElementById('ip-tabs').addEventListener('click', e => {
            const b = e.target.closest('[data-tab]'); if (!b) return;
            tab = b.dataset.tab;
            document.querySelectorAll('#ip-tabs [data-tab]').forEach(x => x.className = 'px-3 py-1.5 rounded-md ' + (x === b ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'));
            render();
        });
        document.getElementById('ip-search').addEventListener('input', render);
        document.getElementById('ip-refresh').addEventListener('click', load);
        load();
    })();
    </script>
</body>

</html>