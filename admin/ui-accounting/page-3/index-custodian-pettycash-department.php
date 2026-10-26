<?php
// index-custodian-pettycash-department.php — Petty Cash Department Sheet (read-only view ng Sheet Two)
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petty Cash — Department Sheet</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .pc-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .pc-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
        .pc-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }

        .pc-input { width: 100%; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem;
                    color: #1f2937; background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
        .pc-input:focus { border-color: #d97706; box-shadow: 0 0 0 3px #fef3c7; }
        .pc-input[readonly] { background: #f9fafb; color: #6b7280; }
        .pc-input[aria-invalid="true"] { border-color: #f87171; box-shadow: 0 0 0 3px #fee2e2; }
        .pc-money { padding-left: 1.75rem !important; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .pc-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: .375rem; }

        .pc-tab { color: #6b7280; }
        .pc-tab:hover { background: #f9fafb; color: #374151; }
        .pc-tab[aria-current="page"] { background: #b45309; color: #fff; }

        .pc-choice { border: 1px solid #e5e7eb; background: #fff; color: #4b5563; }
        .pc-choice:hover { border-color: #d1d5db; }
        .pc-choice[data-active="1"] { border-color: #b45309; background: #fffbeb; color: #92400e; box-shadow: 0 0 0 1px #b45309 inset; }
    </style>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-4 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-sitemap text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Petty Cash — Department Sheet</h1>
                    <p class="text-sm text-gray-500">Sheet Two expenses, grouped by department</p>
                </div>
            </div>
        </div>

        <!-- ═══ SHEET TABS ═══ -->
        <nav class="mb-5 flex gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-sm w-full sm:w-fit" aria-label="Petty cash sheets">
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-route="accountingcustodianpettycash"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-hand-holding-dollar text-[11px]"></i>
                    <span class="leading-tight">General Sheet<span class="hidden lg:inline font-normal opacity-75"> · Receipts</span></span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycashtwo" data-route="accountingcustodianpettycashtwo"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-receipt text-[11px]"></i>
                    <span class="leading-tight">Sheet Two<span class="hidden lg:inline font-normal opacity-75"> · Expenses</span></span>
                </a>
                <a href="<?= BASE_URL ?>/pettycashdepartment" data-route="pettycashdepartment" aria-current="page"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-sitemap text-[11px]"></i>
                    <span class="leading-tight">Department Sheet<span class="hidden lg:inline font-normal opacity-75"> · By department</span></span>
                </a>
        </nav>

        <!-- ═══ SUMMARY ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-amber-50 text-amber-700 items-center justify-center shrink-0"><i class="fa-solid fa-building"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Department</p>
                    <p id="card-dept" class="text-base font-bold text-amber-800 leading-tight mt-1 truncate">—</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-red-50 text-red-500 items-center justify-center shrink-0"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total actual</p>
                    <p id="card-actual" class="text-lg sm:text-xl font-bold text-red-600 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-share" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-purple-50 text-purple-600 items-center justify-center shrink-0"><i class="fa-solid fa-percent"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total VATable</p>
                    <p id="card-vatable" class="text-lg sm:text-xl font-bold text-purple-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-blue-50 text-blue-600 items-center justify-center shrink-0"><i class="fa-solid fa-list-ol"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Entries</p>
                    <p id="card-entries" class="text-lg sm:text-xl font-bold text-blue-700 leading-none mt-1 font-mono break-all">0</p>
                </div>
            </div>
        </div>

        <!-- ═══ SHEET CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5 px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="changeMonth(-1)" title="Previous month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <label>
                        <span class="sr-only">Month</span>
                        <input type="month" id="filter-month" value="<?= date('Y-m') ?>"
                            class="h-9 text-sm font-bold text-gray-800 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-3 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </label>
                    <button type="button" onclick="changeMonth(1)" title="Next month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button" onclick="goThisMonth()"
                        class="h-9 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 hover:bg-amber-100 px-3 rounded-lg transition">
                        This month
                    </button>
                </div>
                <div class="flex items-center gap-2 flex-wrap xl:flex-nowrap">
                    <label class="flex items-center gap-2 min-w-0 flex-1 xl:flex-none">
                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide shrink-0">Dept.</span>
                        <select id="filter-department" class="h-9 text-xs font-semibold border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-800 bg-white min-w-0 flex-1 xl:w-52">
                            <option value="">Loading...</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 min-w-0 flex-1 xl:flex-none">
                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide shrink-0">Title</span>
                        <select id="filter-account-title" class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white min-w-0 flex-1 xl:w-48">
                            <option value="">All account titles</option>
                        </select>
                    </label>
                </div>
            </div>
            <!-- Quick switch: departments na may gastos ngayong buwan -->
            <div id="dept-chips" class="hidden px-4 py-2.5 border-b border-gray-100 flex gap-2 overflow-x-auto pc-scroll"></div>
            <div id="pc-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>

            <div id="sheet-tbody" class="pc-scroll max-h-[calc(100vh-26rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
                <div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
            </div>
            <!-- Footer totals -->
            <div class="border-t border-gray-200 bg-amber-50 px-4 sm:px-5 py-3">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-amber-900">Total actual <span id="foot-label" class="font-normal normal-case text-amber-800"></span></span>
                    <span id="foot-actual" class="font-mono font-bold text-base text-red-600">₱ 0.00</span>
                </div>
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-1 text-[11px]">
                    <div class="flex justify-between sm:block"><span class="text-amber-800/70">VATable</span> <span id="foot-vatable" class="font-mono font-semibold text-gray-800 sm:block">₱ 0.00</span></div>
                    <div class="flex justify-between sm:block"><span class="text-amber-800/70">VAT</span> <span id="foot-vat" class="font-mono font-semibold text-gray-800 sm:block">₱ 0.00</span></div>
                    <div class="flex justify-between sm:block"><span class="text-amber-800/70">Total</span> <span id="foot-total" class="font-mono font-semibold text-gray-800 sm:block">₱ 0.00</span></div>
                    <div class="flex justify-between sm:block"><span class="text-amber-800/70">Non-VAT</span> <span id="foot-nonvat" class="font-mono font-semibold text-gray-800 sm:block">₱ 0.00</span></div>
                    <div class="flex justify-between sm:block"><span class="text-amber-800/70">VAT exempt</span> <span id="foot-vatexempt" class="font-mono font-semibold text-gray-800 sm:block">₱ 0.00</span></div>
                </div>
            </div>
        </div>

    </main>



    <script>
        const BASE_URL_JS = '<?= BASE_URL ?>';
        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
        const fmt = v => '₱ ' + num(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const todayPH = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
        function fmtDay(v) {
            if (!v) return '—';
            const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
            return isNaN(d) ? String(v) : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function monthLabel(val) {
            const [y, m] = String(val || '').split('-').map(Number);
            return (y && m) ? MONTH_NAMES[m - 1] + ' ' + y : '';
        }
        const curMonth = () => document.getElementById('filter-month').value || todayPH().slice(0, 7);

        function emptyHtml(title, sub = '', icon = 'fa-table') {
            return `<div class="px-4 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid ${icon}"></i></div>
                <p class="text-sm font-semibold text-gray-600">${title}</p>
                ${sub ? `<p class="text-xs text-gray-400 mt-1">${escHtml(sub)}</p>` : ''}
            </div>`;
        }

        // Basahin ang sagot ng server nang ligtas (para malinaw ang error kapag hindi JSON)
        async function readJson(res) {
            const text = await res.text();
            try {
                return JSON.parse(text.replace(/^﻿/, '').trim());
            } catch (e) {
                console.error('Server response (not JSON):', res.status, res.url, text);
                const route = (() => { try { return new URL(res.url).pathname.split('/').pop(); } catch (x) { return ''; } })();
                let msg;
                if (/<!doctype html|<html[\s>]/i.test(text)) {
                    const title = ((text.match(/<title>([^<]*)<\/title>/i) || [])[1] || 'a web page').trim();
                    msg = `The server returned the page "${title}" instead of data for "${route}". The route may be missing from $adminRoutes in index.php, or your session expired (reload the page).`;
                } else {
                    const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    msg = `Server error (HTTP ${res.status}): ` + (plain ? plain.slice(0, 180) + (plain.length > 180 ? '…' : '') : 'empty response');
                }
                const err = new Error(msg); err.server = true; throw err;
            }
        }
        const getJson = url => fetch(url, { cache: 'no-store' }).then(readJson);
        const postJson = (url, body) => fetch(url, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
        }).then(readJson);

        // ─── Month navigation (kasama ang ?month= sa URL at sa mga tab) ───
        function syncMonthLinks() {
            const m = curMonth();
            document.querySelectorAll('.pc-tab-link').forEach(a => {
                const u = new URL(a.href, location.href);
                u.searchParams.set('month', m);
                a.href = u.toString();
            });
            try {
                const u = new URL(location.href);
                u.searchParams.set('month', m);
                history.replaceState(null, '', u);
            } catch (e) { }
        }
        function onMonthChanged() { syncMonthLinks(); fetchEntries(); }
        function changeMonth(step) {
            let [y, m] = curMonth().split('-').map(Number);
            m += step;
            if (m < 1) { m = 12; y--; } else if (m > 12) { m = 1; y++; }
            document.getElementById('filter-month').value = y + '-' + String(m).padStart(2, '0');
            onMonthChanged();
        }
        function goThisMonth() {
            document.getElementById('filter-month').value = todayPH().slice(0, 7);
            onMonthChanged();
        }
        (function initMonth() {
            const p = new URLSearchParams(location.search).get('month');
            if (p && /^\d{4}-(0[1-9]|1[0-2])$/.test(p)) document.getElementById('filter-month').value = p;
            document.getElementById('filter-month').addEventListener('change', onMonthChanged);
            syncMonthLinks();
        })();

        function showToast(message, type = 'success') {
            const existing = document.getElementById('pc-toast');
            if (existing) existing.remove();
            const color = type === 'delete' || type === 'error' ? 'bg-red-500' : 'bg-green-600';
            const icon = type === 'delete' ? 'fa-trash' : type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
            const toast = document.createElement('div');
            toast.id = 'pc-toast';
            toast.className = `fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[999] flex items-center gap-3 ${color} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0`;
            toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${escHtml(message)}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
            setTimeout(() => { toast.classList.replace('opacity-100', 'opacity-0'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        let allDepartments = [];
        let activeDept = null;
        let monthRows = [];      // lahat ng Sheet Two entries ng buwan
        let fetchSeq = 0;

        // Isang expense row (ginagamit sa Sheet Two at Department Sheet)
        function expenseRowHtml(row, i, editable) {
            const id = Number(row.id);
            const money = v => num(v) ? fmt(v) : '—';
            const supplier = [row.supplier_name_corp, row.supplier_name_indiv].filter(Boolean);
            const tax = [
                ['VATable', row.vatable_amount], ['VAT', row.vat], ['Total', row.total],
                ['Non-VAT', row.non_vat], ['VAT exempt', row.vat_exempt],
            ].filter(([, v]) => num(v));
            return `
            <div data-id="${id}" class="pc-row flex items-stretch gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50 transition-colors">
                <div class="w-7 shrink-0 pt-0.5 text-right text-[11px] font-mono text-gray-400">${i + 1}</div>
                <div class="w-1 rounded-full shrink-0 bg-red-400"></div>
                <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-start gap-2 lg:gap-5">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>${escHtml(fmtDay(row.date))}</span>
                            ${row.reference_no ? `<span class="notranslate font-mono text-[11px] text-gray-600 bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5"><span class="font-sans font-semibold text-[9px] uppercase">Ref</span> ${escHtml(row.reference_no)}</span>` : ''}
                            ${row.account_title ? `<span class="text-[10px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">${escHtml(row.account_title)}</span>` : ''}
                            ${row.project_department ? `<span class="text-[10px] font-semibold text-gray-600 bg-gray-100 rounded-full px-2 py-0.5"><i class="fa-solid fa-sitemap text-[9px] mr-1 text-gray-400"></i>${escHtml(row.project_department)}</span>` : ''}
                        </div>
                        <p class="mt-1 text-sm font-semibold text-gray-800 break-words">${escHtml(row.particulars || '—')}</p>
                        <div class="flex items-center gap-x-3 gap-y-0.5 mt-0.5 flex-wrap text-[11px] text-gray-500">
                            ${row.in_charge ? `<span class="truncate"><span class="text-gray-400">In-charge:</span> ${escHtml(row.in_charge)}</span>` : ''}
                            ${supplier.length ? `<span class="truncate"><span class="text-gray-400">Supplier:</span> ${supplier.map(escHtml).join(' / ')}</span>` : ''}
                            ${row.tin ? `<span class="truncate font-mono"><span class="font-sans text-gray-400">TIN</span> ${escHtml(row.tin)}</span>` : ''}
                            ${row.no_sales_invoice ? `<span class="truncate"><span class="text-gray-400">Invoice:</span> ${escHtml(row.no_sales_invoice)}</span>` : ''}
                            ${row.inserted_by ? `<span class="truncate text-gray-400">Added by ${escHtml(row.inserted_by)}</span>` : ''}
                        </div>
                        ${row.address ? `<p class="text-[11px] text-gray-400 truncate"><i class="fa-solid fa-location-dot text-[9px] mr-1"></i>${escHtml(row.address)}</p>` : ''}
                        ${tax.length ? `<div class="mt-1.5 flex flex-wrap gap-1">${tax.map(([k, v]) =>
                            `<span class="text-[10px] bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5"><span class="text-gray-400">${k}</span> <span class="font-mono text-gray-700">${money(v)}</span></span>`).join('')}</div>` : ''}
                    </div>
                    <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0 lg:pt-1">
                        <div class="lg:text-right">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Actual</p>
                            <p class="font-mono text-sm font-bold text-red-600 lg:min-w-[110px]">${fmt(row.actual)}</p>
                        </div>
                        ${editable ? `<div class="flex items-center gap-1.5">
                            <button type="button" onclick="editEntry(${id})" title="Edit"
                                class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-gray-200 bg-white hover:border-amber-500 hover:text-amber-700 text-gray-600 text-xs font-semibold transition">
                                <i class="fa-solid fa-pen text-[10px]"></i><span class="hidden sm:inline">Edit</span>
                            </button>
                            <button type="button" onclick="openDeleteModal(${id})" title="Delete"
                                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:border-red-300 hover:bg-red-50 text-gray-500 hover:text-red-600 transition">
                                <i class="fa-solid fa-trash text-[10px]"></i>
                            </button>
                        </div>` : ''}
                    </div>
                </div>
            </div>`;
        }

        // Totals ng buwis (para sa footer)
        function taxTotals(rows) {
            const t = { actual: 0, vatable: 0, vat: 0, total: 0, nonvat: 0, vatexempt: 0 };
            rows.forEach(r => {
                t.actual += num(r.actual); t.vatable += num(r.vatable_amount); t.vat += num(r.vat);
                t.total += num(r.total); t.nonvat += num(r.non_vat); t.vatexempt += num(r.vat_exempt);
            });
            return t;
        }
        function renderFooter(rows) {
            const t = taxTotals(rows);
            const set = (id, v) => document.getElementById(id).textContent = fmt(v);
            set('foot-actual', t.actual); set('foot-vatable', t.vatable); set('foot-vat', t.vat);
            set('foot-total', t.total); set('foot-nonvat', t.nonvat); set('foot-vatexempt', t.vatexempt);
            return t;
        }

        const norm = s => String(s ?? '').trim().toLowerCase();

        // ─── Departments ─────────────────────────────────────
        function loadDepartments() {
            return getJson(`${BASE_URL_JS}/fetchpettycashdepartment`).then(data => {
                allDepartments = Array.isArray(data) ? data : [];
                const sel = document.getElementById('filter-department');
                if (!allDepartments.length) {
                    sel.innerHTML = '<option value="">No departments yet</option>';
                    document.getElementById('sheet-tbody').innerHTML = emptyHtml('No departments yet.', 'Add departments from Sheet Two (gear icon beside Department).', 'fa-building');
                    return;
                }
                sel.innerHTML = allDepartments.map(d => `<option value="${escHtml(d.id)}">${escHtml(d.name)}</option>`).join('');
                const want = new URLSearchParams(location.search).get('dept');
                const match = allDepartments.find(d => norm(d.name) === norm(want)) || allDepartments[0];
                sel.value = match.id;
                switchDept(match, true);
            }).catch(err => {
                document.getElementById('sheet-tbody').innerHTML = emptyHtml('Could not load departments.', err?.server ? err.message : 'Check your connection, then try again.');
            });
        }

        function switchDept(dept, refetch) {
            activeDept = dept;
            document.getElementById('card-dept').textContent = dept.name;
            document.getElementById('card-dept').title = dept.name;
            document.getElementById('filter-department').value = dept.id;
            document.getElementById('filter-account-title').value = '';
            try { const u = new URL(location.href); u.searchParams.set('dept', dept.name); history.replaceState(null, '', u); } catch (e) { }
            if (refetch) fetchEntries(); else render();
        }

        // ─── Fetch (isang beses kada buwan; client-side ang filter ng department) ─
        function fetchEntries() {
            if (!activeDept) return;
            const seq = ++fetchSeq;
            document.getElementById('sheet-tbody').innerHTML =
                '<div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>';
            getJson(`${BASE_URL_JS}/fetchcustodiansheetpettycashtwo?month=${encodeURIComponent(curMonth())}`)
                .then(data => {
                    if (seq !== fetchSeq) return;
                    monthRows = Array.isArray(data) ? data : [];
                    render();
                })
                .catch(err => {
                    if (seq !== fetchSeq) return;
                    document.getElementById('sheet-tbody').innerHTML = emptyHtml('Could not load entries.', err?.server ? err.message : 'Check your connection, then try again.');
                });
        }

        function renderChips() {
            const totals = {};
            monthRows.forEach(r => {
                const k = norm(r.project_department);
                if (!k) return;
                totals[k] = (totals[k] || 0) + num(r.actual);
            });
            const box = document.getElementById('dept-chips');
            const list = allDepartments.filter(d => totals[norm(d.name)]);
            box.classList.toggle('hidden', !list.length);
            box.innerHTML = list.map(d => {
                const on = activeDept && d.id == activeDept.id;
                return `<button type="button" data-id="${escHtml(d.id)}"
                    class="dept-chip shrink-0 inline-flex items-center gap-1.5 text-[11px] font-semibold rounded-full border px-3 py-1 transition
                    ${on ? 'bg-amber-700 border-amber-700 text-white' : 'bg-white border-gray-200 text-gray-600 hover:border-amber-500 hover:text-amber-700'}">
                    ${escHtml(d.name)} <span class="font-mono ${on ? 'text-amber-100' : 'text-gray-400'}">${fmt(totals[norm(d.name)])}</span></button>`;
            }).join('');
        }

        function render() {
            if (!activeDept) return;
            const deptRows = monthRows.filter(r => norm(r.project_department) === norm(activeDept.name));

            // Account title filter (mula sa entries ng department na ito)
            const sel = document.getElementById('filter-account-title');
            const cur = sel.value;
            const titles = [...new Set(deptRows.map(r => (r.account_title ?? '').trim()).filter(Boolean))].sort();
            sel.innerHTML = '<option value="">All account titles</option>' +
                titles.map(t => `<option value="${escHtml(t)}" ${t === cur ? 'selected' : ''}>${escHtml(t)}</option>`).join('');
            const title = titles.includes(cur) ? cur : '';
            const shown = title ? deptRows.filter(r => (r.account_title ?? '').trim() === title) : deptRows;

            const m = monthLabel(curMonth());
            document.getElementById('pc-info').innerHTML =
                `Showing <b class="text-gray-700">${shown.length}</b> entr${shown.length === 1 ? 'y' : 'ies'} for <b class="text-gray-700">${escHtml(activeDept.name)}</b> · ${escHtml(m)}`;
            document.getElementById('sheet-tbody').innerHTML = shown.length
                ? shown.map((r, i) => expenseRowHtml(r, i, false)).join('')
                : emptyHtml(`No entries for <b>${escHtml(activeDept.name)}</b> in ${escHtml(m)}.`, 'Pick another department or month.', 'fa-building');

            const t = renderFooter(shown);
            document.getElementById('foot-label').textContent = title ? '(' + title + ')' : '';
            document.getElementById('card-actual').textContent = fmt(t.actual);
            document.getElementById('card-vatable').textContent = fmt(t.vatable);
            document.getElementById('card-entries').textContent = shown.length;
            const monthTotal = monthRows.reduce((s, r) => s + num(r.actual), 0);
            document.getElementById('card-share').textContent = monthTotal
                ? Math.round(t.actual / monthTotal * 100) + '% of all petty cash expenses this month' : '';
            renderChips();
        }

        // ─── Init ────────────────────────────────────────────
        document.getElementById('filter-department').addEventListener('change', function () {
            const d = allDepartments.find(x => x.id == this.value);
            if (d) switchDept(d, false);
        });
        document.getElementById('filter-account-title').addEventListener('change', render);
        document.getElementById('dept-chips').addEventListener('click', e => {
            const b = e.target.closest('.dept-chip');
            const d = b && allDepartments.find(x => x.id == b.dataset.id);
            if (d) switchDept(d, false);
        });
        loadDepartments();

    </script>

</body>

</html>