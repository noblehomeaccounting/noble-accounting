<?php
// index-clientseries.php  —  Client Series Number (Purchasing monitoring)
// Galing sa system ang P.O/approve/payment date, req no, client, amount at transmittal no.
// Ang hindi pa konektado (delivery info, remarks) ay manual input — auto-save bawat cell.
// May tab bawat client; sa loob ng tab, ang mga series no. ng client na iyon.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$csAjaxUrl = BASE_URL . '/clientseriesajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Series Number</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* inline na input sa loob ng cell — mukhang plain text hanggang i-click */
        .cs-in { width: 100%; min-width: 0; background: transparent; border: 1px solid transparent; border-radius: 6px; padding: 5px 6px; font-size: 12px; outline: none; transition: background .15s, border-color .15s; }
        .cs-in:hover { background: #fffbeb; border-color: #fde68a; }
        .cs-in:focus { background: #fff; border-color: #b45309; box-shadow: 0 0 0 2px rgba(180, 83, 9, .12); }
        .cs-in::placeholder { color: #d1d5db; }
        .cs-in.is-empty:not(:focus) { color: #d1d5db; }
        textarea.cs-in { resize: none; line-height: 1.35; display: block; }
        .cs-saved { animation: csSaved 1.1s ease-out; }
        .cs-err { background: #fef2f2 !important; border-color: #fca5a5 !important; }
        @keyframes csSaved { 0% { background: #dcfce7; border-color: #86efac; } 100% { background: transparent; border-color: transparent; } }
        .cs-tabs { scrollbar-width: none; }
        .cs-tabs::-webkit-scrollbar { display: none; }
        /* naka-pin ang Client Series kapag nag-scroll pakanan */
        .cs-lbl { font-size: 9px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #9ca3af; width: 56px; flex: none; }
        .cs-lbl.sm { width: 38px; }
        .cs-in[type="date"] { padding-right: 2px; }
        .cs-in[type="date"]::-webkit-calendar-picker-indicator { margin-left: 2px; opacity: .45; cursor: pointer; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ===================== HEADER ===================== -->
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                <h1 class="text-gray-900 text-xl font-semibold">Client Series Number</h1>
                <p class="text-gray-400 text-xs mt-0.5">Pick a client tab to see its series. Gray cells come from the system; white cells are typed in and save on their own.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-2">
                    <span id="csLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="csLiveText">Connecting…</span>
                </span>
                <button type="button" id="csRefresh" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                    <i class="fa-solid fa-rotate"></i> Refresh
                </button>
                <button type="button" id="csExport" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                    <i class="fa-solid fa-file-csv"></i> Export
                </button>
            </div>
        </div>

        <!-- ===================== CLIENT TABS ===================== -->
        <div class="bg-white border border-gray-200 rounded-xl mb-3">
            <div class="flex items-center gap-2 px-3 pt-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 shrink-0 pl-1">Clients</p>
                <div class="relative flex-1 min-w-0">
                    <div id="csClientTabs" class="cs-tabs flex items-end gap-1 overflow-x-auto"></div>
                </div>
            </div>
            <div class="border-t border-gray-200"></div>

            <!-- buod ng napiling client -->
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span id="csAvatar" class="w-10 h-10 rounded-xl bg-slate-700 text-white flex items-center justify-center text-sm font-bold shrink-0"></span>
                    <div class="min-w-0">
                        <p id="csClientName" class="text-sm font-semibold text-gray-900 truncate">All clients</p>
                        <p id="csClientSub" class="text-[11px] text-gray-400">—</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 ml-auto">
                    <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Series</p><p id="csStatCount" class="text-lg font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
                    <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Total Amount</p><p id="csStatAmount" class="text-lg font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
                    <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Paid · to deliver</p><p id="csStatUndelivered" class="text-lg font-bold text-orange-600 tabular-nums leading-tight">—</p></div>
                    <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Done</p><p id="csStatDone" class="text-lg font-bold text-green-600 tabular-nums leading-tight">—</p></div>
                </div>
            </div>
        </div>

        <!-- ===================== TABLE CARD ===================== -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            <!-- status chips + search + dates -->
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                <div id="csStatusChips" class="inline-flex flex-wrap items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1"></div>
                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="csSearch" placeholder="Search series, transmittal, person…"
                            class="w-56 border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600">
                    </div>
                    <div class="inline-flex items-center gap-1 border border-gray-200 rounded-lg px-2 py-1 bg-white">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">P.O. Date</span>
                        <input type="date" id="csFrom" class="text-xs outline-none bg-transparent px-1 py-1" title="From">
                        <span class="text-gray-300">–</span>
                        <input type="date" id="csTo" class="text-xs outline-none bg-transparent px-1 py-1" title="To">
                    </div>
                    <button type="button" id="csReset" class="px-2 py-2 text-[11px] font-semibold text-gray-400 hover:text-amber-700" title="Clear search, status and dates">
                        <i class="fa-solid fa-filter-circle-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto overflow-x-hidden max-h-[calc(100vh-360px)] min-h-[260px]">
                <table class="w-full table-fixed text-xs border-separate border-spacing-0">
                    <colgroup>
                        <col style="width:14%"><col style="width:13%"><col style="width:8%"><col style="width:10%">
                        <col style="width:18%"><col style="width:18%">
                        <col><col style="width:56px">
                    </colgroup>
                    <thead id="csHead" class="sticky top-0 z-10"></thead>
                    <tbody id="csBody">
                        <tr><td class="px-5 py-12 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400">
                <span id="csFooter"></span>
                <span>
                    <span class="inline-block w-3 h-3 rounded-sm bg-slate-100 border border-slate-300 align-middle mr-1"></span>from the system
                    <span class="inline-block w-3 h-3 rounded-sm bg-white border border-amber-300 align-middle ml-3 mr-1"></span>typed in (auto-saves)
                </span>
            </div>
        </div>

        <datalist id="csModes"></datalist>
        <datalist id="csPeople"></datalist>
        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <script>
        (function () {
            const AJAX = <?= json_encode($csAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;
            const TR_URL = BASE + '/transmittal';
            const PR_URL = BASE + '/purchaserequest';
            const DEFAULT_MODES = ['LALAMOVE', 'PICK UP', 'DELIVER'];
            const ALL = '__all__';

            let rows = [];
            let client = ALL;
            let status = '';

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const ymd = v => v ? String(v).slice(0, 10) : '';
            const mdy = v => { const s = ymd(v); if (!s) return ''; const [y, m, d] = s.split('-'); return `${+m}/${+d}/${y}`; };
            const poDate = r => ymd(r.request_date || r.created_at);
            const initials = n => {
                const w = String(n).match(/[A-Za-z0-9]+/g) || ['?'];
                return (w.length === 1 ? w[0].slice(0, 3) : w.slice(0, 3).map(x => x[0]).join('')).toUpperCase();
            };

            // iisang kulay bawat client (para madaling makilala ang tab)
            const PALETTE = ['bg-amber-600', 'bg-sky-600', 'bg-emerald-600', 'bg-rose-600', 'bg-violet-600', 'bg-teal-600', 'bg-orange-600', 'bg-indigo-600', 'bg-lime-700', 'bg-fuchsia-600'];
            const colorOf = name => { let h = 0; for (const c of String(name)) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };

            function toast(message, type = 'success', duration = 3500) {
                const box = document.getElementById('crmToastContainer');
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                box.appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }

            const memo = {
                get: k => { try { return localStorage.getItem('nh-cs-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-cs-' + k, v); } catch (e) { } },
            };

            // ── yugto ng bawat series ──
            function stage(r) {
                if (r.status === 'Rejected') return 'cancel';
                if (r.status !== 'Approved') return 'approval';
                if (!r.paid_at) return 'unpaid';
                return 'paid';
            }
            const STATUS = [
                { key: '',            label: 'All',               test: () => true },
                { key: 'approval',    label: 'For approval',      test: r => stage(r) === 'approval' },
                { key: 'unpaid',      label: 'Not paid',          test: r => stage(r) === 'unpaid' },
                { key: 'undelivered', label: 'Paid · to deliver', test: r => stage(r) === 'paid' && !r.deliver_client_date && !r.is_done },
                { key: 'done',        label: 'Done',              test: r => r.is_done },
                { key: 'cancel',      label: 'Cancelled',         test: r => stage(r) === 'cancel' },
            ];

            // ── filters ──
            const inClient = r => client === ALL || r.client === client;
            function baseFiltered() {   // client + search + petsa (hindi pa kasama ang status)
                const q = document.getElementById('csSearch').value.trim().toLowerCase();
                const from = document.getElementById('csFrom').value;
                const to = document.getElementById('csTo').value;
                return rows.filter(r => {
                    if (!inClient(r)) return false;
                    if (q && !`${r.req_no} ${r.client} ${r.tr_ref_no ?? ''} ${r.delivery_mode ?? ''} ${r.delivered_by ?? ''} ${r.remarks ?? ''}`.toLowerCase().includes(q)) return false;
                    const d = poDate(r);
                    if (from && d < from) return false;
                    if (to && d > to) return false;
                    return true;
                });
            }
            const filtered = () => { const s = STATUS.find(x => x.key === status) || STATUS[0]; return baseFiltered().filter(s.test); };

            ['csSearch', 'csFrom', 'csTo'].forEach(id => {
                const el = document.getElementById(id);
                el.value = memo.get(id) || '';
                el.addEventListener(id === 'csSearch' ? 'input' : 'change', () => { memo.set(id, el.value); render(); });
            });
            status = memo.get('status') || '';
            document.getElementById('csReset').addEventListener('click', () => {
                ['csSearch', 'csFrom', 'csTo'].forEach(id => { document.getElementById(id).value = ''; memo.set(id, ''); });
                status = ''; memo.set('status', '');
                render();
            });

            // ── client tabs ──
            function clientList() {
                const map = new Map();
                rows.forEach(r => {
                    const c = map.get(r.client) || { name: r.client, count: 0, last: '' };
                    c.count++;
                    const d = poDate(r);
                    if (d > c.last) c.last = d;
                    map.set(r.client, c);
                });
                // pinakabagong may transaksyon ang nauuna
                return [...map.values()].sort((a, b) => b.last.localeCompare(a.last) || a.name.localeCompare(b.name));
            }

            function renderTabs() {
                const list = clientList();
                if (client !== ALL && !list.some(c => c.name === client)) client = ALL;
                const tab = (key, label, count, badge) => {
                    const on = client === key;
                    return `<button type="button" data-client="${esc(key)}" title="${esc(label)}"
                        class="shrink-0 inline-flex items-center gap-2 px-3 py-2 -mb-px border-b-2 text-xs font-semibold whitespace-nowrap transition-colors
                        ${on ? 'border-amber-700 text-amber-800' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'}">
                        ${badge}
                        <span class="max-w-[160px] truncate">${esc(label)}</span>
                        <span class="min-w-[18px] px-1.5 rounded-full text-[10px] font-bold text-center ${on ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'}">${count}</span>
                    </button>`;
                };
                document.getElementById('csClientTabs').innerHTML =
                    tab(ALL, 'All', rows.length, '<i class="fa-solid fa-layer-group text-[11px]"></i>') +
                    list.map(c => tab(c.name, c.name, c.count, `<span class="w-5 h-5 rounded-md ${colorOf(c.name)} text-white text-[9px] font-bold flex items-center justify-center">${esc(initials(c.name))}</span>`)).join('');
                const active = [...document.querySelectorAll('#csClientTabs [data-client]')].find(b => b.dataset.client === client);
                if (active) active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
            document.getElementById('csClientTabs').addEventListener('click', e => {
                const b = e.target.closest('[data-client]');
                if (!b) return;
                client = b.dataset.client;
                memo.set('client', client);
                renderTabs();
                render();
            });

            function renderChips() {
                const base = baseFiltered();
                document.getElementById('csStatusChips').innerHTML = STATUS.map(s => {
                    const on = s.key === status;
                    return `<button type="button" data-status="${s.key}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[11px] font-semibold transition-all ${on ? 'bg-white shadow-sm text-amber-700' : 'text-gray-500 hover:text-gray-700'}">
                        ${s.label}<span class="text-[10px] ${on ? 'text-amber-600' : 'text-gray-400'}">${base.filter(s.test).length}</span></button>`;
                }).join('');
            }
            document.getElementById('csStatusChips').addEventListener('click', e => {
                const b = e.target.closest('[data-status]');
                if (!b) return;
                status = b.dataset.status;
                memo.set('status', status);
                render();
            });

            // ── table (walang horizontal scroll: magkakasama sa isang cell ang magkaugnay na info) ──
            function renderHead() {
                const g = 'px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.12em] text-left';
                const th = 'px-3 py-2.5 text-[11px] font-semibold text-left border-b border-gray-200';
                document.getElementById('csHead').innerHTML = `
                    <tr>
                        <th colspan="4" class="${g} bg-slate-700 text-white">Purchase Request <span class="font-normal normal-case tracking-normal text-slate-300">· from the system</span></th>
                        <th colspan="4" class="${g} bg-amber-700 text-white">Delivery Tracking <span class="font-normal normal-case tracking-normal text-amber-200">· typed in</span></th>
                    </tr>
                    <tr>
                        <th class="${th} bg-slate-100 text-slate-700 w-[15%]">Client Series</th>
                        <th class="${th} bg-slate-100 text-slate-700 w-[14%]">Dates</th>
                        <th class="${th} bg-slate-100 text-slate-700 text-right w-[9%]">Amount</th>
                        <th class="${th} bg-slate-100 text-slate-700 w-[10%]">Transmittal</th>
                        <th class="${th} bg-amber-50 text-amber-900 w-[17%]">Received · Delivered</th>
                        <th class="${th} bg-amber-50 text-amber-900 w-[17%]">Delivery · Person</th>
                        <th class="${th} bg-amber-50 text-amber-900">Remarks</th>
                        <th class="${th} bg-amber-50 text-amber-900 text-center w-[56px]">Done</th>
                    </tr>`;
            }

            const sysTd = 'px-3 py-2 border-b border-gray-100 bg-slate-50/70 text-gray-700 align-middle';
            const manTd = 'px-1.5 py-1 border-b border-gray-100 align-middle';
            const lblRow = (lbl, inner, sm = false) => `<div class="flex items-center gap-1"><span class="cs-lbl ${sm ? 'sm' : ''}">${lbl}</span><div class="flex-1 min-w-0">${inner}</div></div>`;
            const dateIn = (r, f) => `<input type="date" class="cs-in ${r[f] ? '' : 'is-empty'}" data-id="${r.id}" data-field="${f}" value="${esc(ymd(r[f]))}">`;
            const textIn = (r, f, ph, list = '') => `<input type="text" class="cs-in" data-id="${r.id}" data-field="${f}" value="${esc(r[f] || '')}" placeholder="${ph}" ${list ? `list="${list}"` : ''}>`;

            function stageTag(r) {
                const t = (cls, txt) => `<span class="px-1.5 py-px rounded text-[9.5px] font-bold ${cls}">${txt}</span>`;
                if (r.is_done) return t('bg-green-100 text-green-700', 'DONE');
                return {
                    cancel:   t('bg-red-100 text-red-600', 'CANCEL'),
                    approval: t('bg-amber-100 text-amber-700', 'FOR APPROVAL'),
                    unpaid:   t('bg-sky-100 text-sky-700', 'NOT PAID'),
                    paid:     r.deliver_client_date ? t('bg-emerald-50 text-emerald-700', 'DELIVERED') : t('bg-orange-100 text-orange-700', 'TO DELIVER'),
                }[stage(r)];
            }
            const seriesCell = r => `
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="${PR_URL}?view=${r.id}" class="font-mono text-[12px] font-semibold text-red-600 hover:underline">${esc(r.req_no)}</a>${stageTag(r)}
                </div>
                ${client === ALL ? `<p class="text-[11px] font-semibold text-gray-600 truncate mt-0.5" title="${esc(r.client)}">${esc(r.client)}</p>` : ''}`;

            function renderSummary(list) {
                const scope = rows.filter(inClient);
                const avatar = document.getElementById('csAvatar');
                if (client === ALL) {
                    const n = clientList().length;
                    avatar.className = 'w-10 h-10 rounded-xl bg-slate-700 text-white flex items-center justify-center text-sm font-bold shrink-0';
                    avatar.innerHTML = '<i class="fa-solid fa-layer-group"></i>';
                    document.getElementById('csClientName').textContent = 'All clients';
                    document.getElementById('csClientSub').textContent = `${n} client${n === 1 ? '' : 's'} · ${rows.length} series`;
                } else {
                    avatar.className = `w-10 h-10 rounded-xl ${colorOf(client)} text-white flex items-center justify-center text-sm font-bold shrink-0`;
                    avatar.textContent = initials(client);
                    document.getElementById('csClientName').textContent = client;
                    const nums = scope.map(r => r.req_no).sort();
                    document.getElementById('csClientSub').textContent = nums.length ? `Series ${nums[0]} → ${nums[nums.length - 1]}` : '';
                }
                document.getElementById('csStatCount').textContent = list.length;
                document.getElementById('csStatAmount').textContent = '₱ ' + money(list.reduce((a, r) => a + (r.status === 'Rejected' ? 0 : r.total_amount), 0));
                document.getElementById('csStatUndelivered').textContent = list.filter(STATUS[3].test).length;
                document.getElementById('csStatDone').textContent = list.filter(r => r.is_done).length;
                document.getElementById('csFooter').textContent = `Showing ${list.length} of ${scope.length} series${client === ALL ? '' : ' for ' + client}`;
                return scope;
            }

            function render() {
                renderChips();
                renderHead();
                const list = filtered();
                const scope = renderSummary(list);

                const body = document.getElementById('csBody');
                if (!list.length) {
                    body.innerHTML = `<tr><td colspan="8" class="px-5 py-14 text-center text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${scope.length ? 'No series match your filters.' : 'No purchase requests yet.'}</td></tr>`;
                    return;
                }

                body.innerHTML = list.map(r => {
                    const cancel = r.status === 'Rejected';
                    const dash = '<span class="text-gray-300">—</span>';
                    const cx = '<span class="font-semibold text-red-600">CANCEL</span>';
                    const dates = `
                        ${lblRow('P.O.', mdy(poDate(r)))}
                        ${lblRow('Approved', cancel ? cx : (r.approved_at ? mdy(r.approved_at) : dash))}
                        ${lblRow('Paid', cancel ? cx : (r.paid_at ? mdy(r.paid_at) : dash))}`;
                    const tr = r.tr_ref_no
                        ? `<a href="${TR_URL}?view=${r.tr_id}" class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold text-amber-700 hover:underline whitespace-nowrap" title="From the system"><i class="fa-solid fa-link text-[9px]"></i>${esc(r.tr_ref_no)}</a>`
                        : '<span class="text-[11px] text-gray-300">Not yet</span>';   // galing lang sa Transmittal (hindi manual)
                    const rowBg = r.is_done ? 'bg-green-50' : 'bg-white';
                    return `
                    <tr class="${rowBg} ${cancel ? 'opacity-60' : ''}">
                        <td class="px-3 py-2 border-b border-gray-100 align-middle" data-series>${seriesCell(r)}</td>
                        <td class="${sysTd} leading-relaxed">${dates}</td>
                        <td class="${sysTd} text-right tabular-nums font-semibold ${cancel ? 'line-through text-gray-400' : 'text-gray-900'}">${money(r.total_amount)}</td>
                        <td class="${sysTd}">${tr}</td>
                        <td class="${manTd}">${lblRow('Office', dateIn(r, 'receive_office_date'), true)}${lblRow('Client', dateIn(r, 'deliver_client_date'), true)}</td>
                        <td class="${manTd}">${lblRow('Mode', textIn(r, 'delivery_mode', 'e.g. Lalamove', 'csModes'), true)}${lblRow('By', textIn(r, 'delivered_by', 'Name', 'csPeople'), true)}</td>
                        <td class="${manTd}"><textarea rows="2" class="cs-in" data-id="${r.id}" data-field="remarks" placeholder="Remarks">${esc(r.remarks || '')}</textarea></td>
                        <td class="${manTd} text-center"><input type="checkbox" class="w-4 h-4 accent-green-600 cursor-pointer" data-id="${r.id}" data-field="is_done" ${r.is_done ? 'checked' : ''}></td>
                    </tr>`;
                }).join('');
                body.querySelectorAll('textarea').forEach(t => { if (t.value) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; } });
            }

            function fillOptions(modes, people) {
                const uniq = a => [...new Set(a.map(x => String(x).trim()).filter(Boolean))];
                document.getElementById('csModes').innerHTML = uniq([...DEFAULT_MODES, ...modes]).map(m => `<option value="${esc(m)}">`).join('');
                document.getElementById('csPeople').innerHTML = uniq(people).map(m => `<option value="${esc(m)}">`).join('');
            }

            let loading = false, loadAgain = false;
            async function load() {
                if (loading) { loadAgain = true; return; }
                loading = true;
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Failed to load.', 'error'); return; }
                    rows = d.rows;
                    fillOptions(d.modes || [], d.people || []);
                    renderTabs();
                    render();
                } catch (e) { toast('Connection error while loading.', 'error'); }
                finally {
                    loading = false;
                    if (loadAgain) { loadAgain = false; load(); }
                }
            }

            // ── auto-save ng manual na cell ──
            async function save(el) {
                const id = +el.dataset.id, field = el.dataset.field;
                const value = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value.trim();
                const row = rows.find(r => r.id === id);
                const before = row ? (field === 'is_done' ? (row.is_done ? '1' : '0') : (field.endsWith('_date') ? ymd(row[field]) : (row[field] || ''))) : '';
                if (value === before) return;

                el.classList.remove('cs-saved', 'cs-err');
                try {
                    const res = await fetch(`${AJAX}?action=save`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ pr_id: id, field, value })
                    });
                    const d = await res.json();
                    if (!d.success) { el.classList.add('cs-err'); toast(d.message || 'Could not save.', 'error'); return; }
                    if (row) row[field] = field === 'is_done' ? value === '1' : (value || null);
                    if (el.type === 'date') el.classList.toggle('is-empty', !value);
                    void el.offsetWidth; el.classList.add('cs-saved');

                    if (row && (field === 'is_done' || field === 'deliver_client_date')) {
                        // i-update ang tag, kulay at mga bilang nang hindi binubura ang ibang tina-type
                        const tr = el.closest('tr');
                        tr.querySelector('[data-series]').innerHTML = seriesCell(row);
                        tr.classList.remove('bg-green-50', 'bg-white');
                        tr.classList.add(row.is_done ? 'bg-green-50' : 'bg-white');
                        renderChips();
                        renderSummary(filtered());
                    }
                    if (field === 'delivery_mode' || field === 'delivered_by') {
                        const dl = document.getElementById(field === 'delivery_mode' ? 'csModes' : 'csPeople');
                        if (value && ![...dl.options].some(o => o.value === value)) dl.insertAdjacentHTML('beforeend', `<option value="${esc(value)}">`);
                    }
                } catch (e) { el.classList.add('cs-err'); toast('Connection error. Not saved.', 'error'); }
            }

            const body = document.getElementById('csBody');
            body.addEventListener('change', e => { if (e.target.matches('[data-field]')) save(e.target); });
            body.addEventListener('focusout', e => { if (e.target.matches('input[type="text"][data-field], textarea[data-field]')) save(e.target); });
            body.addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey && e.target.matches('input[type="text"][data-field], textarea[data-field]')) {
                    e.preventDefault();
                    e.target.blur();
                }
            });
            body.addEventListener('input', e => {
                if (e.target.tagName === 'TEXTAREA') { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px'; }
            });

            document.getElementById('csRefresh').addEventListener('click', load);

            // ── export (CSV ng nakikita, bubukas sa Excel) ──
            document.getElementById('csExport').addEventListener('click', () => {
                const head = ['P.O. Date', 'P.O Date Approve', 'Payment Date', 'Client Series', 'Company/Client Name', 'Amount', 'Receive Date to Office', 'Date Deliver to Client', 'Transmittal Number', 'Mode of Delivery', 'Person Deliver to Client / Pick Up', 'Remarks', 'Done'];
                const q = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
                const lines = filtered().map(r => {
                    const cancel = r.status === 'Rejected';
                    return [mdy(poDate(r)), cancel ? 'CANCEL' : mdy(r.approved_at), cancel ? 'CANCEL' : mdy(r.paid_at), 'Req No: ' + r.req_no, r.client,
                        r.total_amount.toFixed(2), mdy(r.receive_office_date), mdy(r.deliver_client_date), r.tr_ref_no || '',
                        r.delivery_mode || '', r.delivered_by || '', r.remarks || '', r.is_done ? 'done' : ''].map(q).join(',');
                });
                const csv = '﻿' + [head.map(q).join(','), ...lines].join('\r\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
                const d = new Date();
                const tag = client === ALL ? 'all' : initials(client).toLowerCase();
                a.download = `client-series-${tag}-${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}.csv`;
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            });

            client = memo.get('client') || ALL;
            load();

            // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
            // Bagong / naaprubahan / nabayarang Purchase Request, Transmittal, at sinave ng ibang PO → kunin agad.
            // Hindi nire-render habang may tina-type o naka-focus sa isang cell; hahabol pagka-alis sa cell.
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const ME = typeof CURRENT_USER_ID !== 'undefined' ? Number(CURRENT_USER_ID) : 0;
            const editing = () => !!document.activeElement?.closest?.('#csBody [data-field], #csSearch, #csFrom, #csTo');
            let missed = false, pollTimer = null;

            function sync() {
                if (document.hidden || editing()) { missed = true; return; }
                missed = false;
                load();
            }
            function startPolling() {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = setInterval(() => { if (!document.hidden && !editing()) load(); }, rtLive() ? 60000 : 30000);
            }
            (function hookRealtime() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !/^(crm|po|pr|transmittal)/i.test(type)) return;
                        if (type === 'po_clientseries' && ME && Number(payload?.by) === ME) return;   // sariling save
                        clearTimeout(t);
                        t = setTimeout(sync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        document.getElementById('csLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        document.getElementById('csLiveText').textContent = live ? 'Live' : 'Auto-refresh 30s';
                        if (!document.hidden) startPolling();
                        if (live && rows.length) sync();
                    });
                };
                hook();
                if (!hookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
                if (!window.NH_RT || !window.NH_RT.enabled) document.getElementById('csLiveText').textContent = 'Auto-refresh 30s';
            })();
            // pagka-alis sa cell (naka-save na), habulin ang na-miss
            document.getElementById('csBody').addEventListener('focusout', () => setTimeout(() => { if (missed) sync(); }, 300));
            setInterval(() => { if (missed) sync(); }, 5000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { if (pollTimer) clearInterval(pollTimer); }
                else { sync(); startPolling(); }
            });
            startPolling();
        })();
    </script>
</body>

</html>