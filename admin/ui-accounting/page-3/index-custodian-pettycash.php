<?php
// index-custodian-pettycash.php — Petty Cash General Sheet (receipts / cash inflows)
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
    <title>Petty Cash General Sheet</title>
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
                    <i class="fa-solid fa-wallet text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Petty Cash General Sheet</h1>
                    <p class="text-sm text-gray-500">Cash inflows and transaction records</p>
                </div>
            </div>
            <button type="button" onclick="openAddModal()"
                class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Add Entry
            </button>
        </div>

        <!-- ═══ SHEET TABS ═══ -->
        <nav class="mb-5 flex gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-sm w-full sm:w-fit" aria-label="Petty cash sheets">
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-route="accountingcustodianpettycash" aria-current="page"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-hand-holding-dollar text-[11px]"></i>
                    <span class="leading-tight">General Sheet<span class="hidden lg:inline font-normal opacity-75"> · Receipts</span></span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycashtwo" data-route="accountingcustodianpettycashtwo"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-receipt text-[11px]"></i>
                    <span class="leading-tight">Sheet Two<span class="hidden lg:inline font-normal opacity-75"> · Expenses</span></span>
                </a>
                <a href="<?= BASE_URL ?>/pettycashdepartment" data-route="pettycashdepartment"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-sitemap text-[11px]"></i>
                    <span class="leading-tight">Department Sheet<span class="hidden lg:inline font-normal opacity-75"> · By department</span></span>
                </a>
        </nav>

        <!-- ═══ SUMMARY (buwan na napili) ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-amber-50 text-amber-700 items-center justify-center shrink-0"><i class="fa-solid fa-flag"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Beginning balance</p>
                    <p id="card-beginning" class="text-lg sm:text-xl font-bold text-gray-800 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-beginning-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-green-50 text-green-600 items-center justify-center shrink-0"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Receipts</p>
                    <p id="card-inflows" class="text-lg sm:text-xl font-bold text-green-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-inflows-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-red-50 text-red-500 items-center justify-center shrink-0"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Expenses (Sheet Two)</p>
                    <p id="card-expenses" class="text-lg sm:text-xl font-bold text-red-600 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-expenses-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-blue-50 text-blue-600 items-center justify-center shrink-0"><i class="fa-solid fa-scale-balanced"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Remaining</p>
                    <p id="card-remaining" class="text-lg sm:text-xl font-bold text-blue-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-remaining-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
        </div>

        <div id="beginning-warning" class="hidden mb-4 flex items-start gap-2 text-xs bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl px-4 py-3"></div>

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
                    <div class="relative flex-1 min-w-0 xl:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="pc-search" placeholder="Search particulars, voucher, source..."
                            class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <select id="pc-type-filter"
                        class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white">
                        <option value="">All types</option>
                        <option value="beginning">Beginning balance</option>
                        <option value="received">Receipts</option>
                    </select>
                </div>
            </div>
            <div id="pc-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>

            <div id="sheet-tbody" class="pc-scroll max-h-[calc(100vh-24rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
                <div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
            </div>

            <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-t border-gray-200 bg-amber-50">
                <span class="text-xs font-bold uppercase tracking-wide text-amber-900">Total cash in <span id="foot-label" class="font-normal normal-case text-amber-800"></span></span>
                <span id="foot-inflows" class="font-mono font-bold text-base text-green-700">₱ 0.00</span>
            </div>
        </div>

        <!-- Carry-over -->
        <div id="carryover-bar" class="hidden mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 sm:px-5 py-3 flex items-start sm:items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-arrow-right-long"></i></div>
            <p class="text-xs text-blue-800">
                Remaining balance of <b id="carryover-month"></b>: <b id="carryover-amount" class="font-mono"></b>
                <span class="text-blue-700">— this becomes the <b>Beginning Balance</b> for next month.</span>
            </p>
        </div>

    </main>

    <!-- ═══ ADD / EDIT MODAL ═══ -->
    <div id="entry-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4 py-6">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div id="modal-icon" class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="modal-title" class="font-bold text-base text-gray-800">Add Entry</h3>
                        <p class="text-[11px] text-gray-500">Fields with <span class="text-red-500">*</span> are required</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal()" title="Close"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="entry-form" class="pc-scroll px-5 py-5 overflow-y-auto" novalidate onsubmit="event.preventDefault(); saveEntry();">
                <input type="hidden" id="entry-id">
                <div id="pc-draft-note" class="hidden mb-4 flex items-center justify-between gap-2 text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-3 py-2">
                    <span><i class="fa-solid fa-clock-rotate-left mr-1"></i> Restored your unsaved draft.</span>
                    <button type="button" onclick="discardDraft()" class="font-semibold hover:underline">Start fresh</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="pc-label">Entry type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" data-type="received" onclick="setEntryType('received')"
                                class="pc-choice pc-type-btn flex items-center justify-center gap-2 text-sm font-semibold py-2.5 px-3 rounded-lg transition">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Receipt
                            </button>
                            <button type="button" data-type="beginning" onclick="setEntryType('beginning')"
                                class="pc-choice pc-type-btn flex items-center justify-center gap-2 text-sm font-semibold py-2.5 px-3 rounded-lg transition">
                                <i class="fa-solid fa-flag"></i> Beginning Balance
                            </button>
                        </div>
                        <input type="hidden" id="entry-type" value="received">
                        <p id="type-hint" class="text-[11px] text-gray-500 mt-1.5"></p>
                    </div>

                    <div>
                        <label class="pc-label" for="entry-date">Date <span class="text-red-500">*</span></label>
                        <input type="date" id="entry-date" class="pc-input">
                        <p id="date-hint" class="text-[10px] text-gray-400 mt-1"></p>
                        <p id="err-date" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>

                    <div>
                        <label class="pc-label" for="entry-cash-inflows">Amount <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-cash-inflows" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                        <p id="err-cash-inflows" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="pc-label" for="entry-particulars">Particulars <span class="text-red-500">*</span></label>
                        <input type="text" id="entry-particulars" placeholder="e.g. Petty cash replenishment" class="pc-input">
                        <p id="err-particulars" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>

                    <div>
                        <label class="pc-label" for="entry-voucher-no">Voucher No.</label>
                        <input type="text" id="entry-voucher-no" placeholder="e.g. 0001" class="pc-input font-mono" autocomplete="off">
                    </div>

                    <div>
                        <label class="pc-label" for="entry-account-title">Account Title</label>
                        <input type="text" id="entry-account-title" placeholder="e.g. Cash on Hand" class="pc-input" list="pc-title-list">
                        <datalist id="pc-title-list"></datalist>
                    </div>

                    <div>
                        <label class="pc-label" for="entry-in-charge">Source</label>
                        <input type="text" id="entry-in-charge" placeholder="e.g. Juan Dela Cruz" class="pc-input" list="pc-source-list">
                        <datalist id="pc-source-list"></datalist>
                    </div>

                    <div>
                        <label class="pc-label" for="entry-reference">Reference</label>
                        <input type="text" id="entry-reference" placeholder="Optional reference" class="pc-input">
                    </div>
                </div>
                <div id="form-error" class="hidden mt-4 text-xs bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2"></div>
                <button type="submit" class="hidden"></button>
            </form>

            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
                <button type="button" onclick="closeModal()"
                    class="text-sm text-gray-600 hover:bg-gray-100 font-medium px-4 py-2 rounded-lg border border-gray-200 bg-white transition">Cancel</button>
                <button type="button" id="save-btn" onclick="saveEntry()"
                    class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> <span>Save Entry</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ DELETE CONFIRM MODAL ═══ -->
    <div id="delete-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="px-6 pt-6 pb-4 text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-trash text-red-500"></i>
                </div>
                <h3 class="font-bold text-base text-gray-800 mb-1">Delete this entry?</h3>
                <p id="delete-summary" class="text-xs text-gray-600 break-words"></p>
                <p class="text-xs text-gray-400 mt-2">This action cannot be undone.</p>
                <p id="delete-error" class="hidden text-xs text-red-600 mt-2"></p>
            </div>
            <div class="flex gap-2 px-5 pb-5">
                <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 py-2.5 text-sm text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg transition font-medium">Cancel</button>
                <button type="button" id="delete-btn" onclick="confirmDelete()"
                    class="flex-1 py-2.5 text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-wait rounded-lg transition font-semibold">Delete</button>
            </div>
        </div>
    </div>

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

        const SAVE_ROUTE = 'savegeneralsheet';
        const DELETE_ROUTE = 'deletegeneralsheet';
        const DRAFT_KEY = 'pc-general-draft';
        const FIELDS = ['entry-cash-inflows', 'entry-voucher-no', 'entry-account-title', 'entry-particulars', 'entry-in-charge', 'entry-reference'];
        const DRAFT_IGNORE = [];
        let allEntries = [];
        let expenseTotal = 0, expenseCount = 0, expenseOk = true;
        let fetchSeq = 0;

        // ─── Form helpers ────────────────────────────────────
        let editMode = false, isSaving = false, deleteTargetId = null;

        function fieldError(id, msg) {
            const el = document.getElementById('err-' + id);
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            document.getElementById('entry-' + id)?.setAttribute('aria-invalid', 'true');
        }
        function clearErrors() {
            document.querySelectorAll('#entry-form [id^="err-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('#entry-form [aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
            formError('');
        }
        function formError(msg) {
            const el = document.getElementById('form-error');
            el.innerHTML = msg ? `<i class="fa-solid fa-circle-exclamation mr-1"></i>${escHtml(msg)}` : '';
            el.classList.toggle('hidden', !msg);
        }
        function setModalHeader(isEdit) {
            document.getElementById('modal-title').textContent = isEdit ? 'Edit Entry' : 'Add Entry';
            document.getElementById('modal-icon').innerHTML = `<i class="fa-solid ${isEdit ? 'fa-pen' : 'fa-plus'}"></i>`;
            document.getElementById('save-btn').querySelector('span').textContent = isEdit ? 'Save Changes' : 'Save Entry';
        }
        function setSaving(on) {
            isSaving = on;
            const btn = document.getElementById('save-btn');
            btn.disabled = on;
            btn.querySelector('i').className = on ? 'fa-solid fa-spinner fa-spin text-xs' : 'fa-solid fa-floppy-disk text-xs';
            btn.querySelector('span').textContent = on ? 'Saving…' : (editMode ? 'Save Changes' : 'Save Entry');
        }
        function openModal() {
            document.getElementById('entry-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('entry-particulars')?.focus(), 50);
        }
        function closeModal() {
            if (isSaving) return;
            document.getElementById('entry-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Draft (Add lang): naiiwan ang na-type kahit maisara ang form
        function saveDraft() {
            if (editMode || document.getElementById('entry-modal').classList.contains('hidden')) return;
            const d = { _type: typeof getEntryType === 'function' ? getEntryType() : null };
            FIELDS.forEach(f => d[f] = document.getElementById(f).value);
            const hasText = FIELDS.some(f => !DRAFT_IGNORE.includes(f) && String(d[f] ?? '').trim() !== '');
            try { hasText ? localStorage.setItem(DRAFT_KEY, JSON.stringify(d)) : localStorage.removeItem(DRAFT_KEY); } catch (e) { }
        }
        function readDraft() { try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return null; } }
        function clearDraft() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) { } }
        function discardDraft() {
            clearDraft();
            FIELDS.forEach(f => { if (!DRAFT_IGNORE.includes(f)) document.getElementById(f).value = ''; });
            if (typeof setEntryType === 'function') setEntryType('received');
            document.getElementById('pc-draft-note').classList.add('hidden');
        }
        function bindDraft() {
            FIELDS.forEach(f => {
                const el = document.getElementById(f);
                el.addEventListener('input', saveDraft);
                el.addEventListener('change', saveDraft);
            });
        }

        // ─── Delete ──────────────────────────────────────────
        function openDeleteModal(id) {
            const row = allEntries.find(r => r.id == id);
            deleteTargetId = id;
            document.getElementById('delete-summary').innerHTML = row ? deleteSummary(row) : '';
            document.getElementById('delete-error').classList.add('hidden');
            document.getElementById('delete-modal').classList.remove('hidden');
        }
        function closeDeleteModal() {
            if (document.getElementById('delete-btn').disabled) return;
            document.getElementById('delete-modal').classList.add('hidden');
            deleteTargetId = null;
        }
        function confirmDelete() {
            const btn = document.getElementById('delete-btn');
            if (btn.disabled || !deleteTargetId) return;
            btn.disabled = true; btn.textContent = 'Deleting…';
            const showErr = msg => { const el = document.getElementById('delete-error'); el.textContent = msg; el.classList.remove('hidden'); };
            postJson(`${BASE_URL_JS}/${DELETE_ROUTE}`, { id: deleteTargetId })
                .then(data => {
                    btn.disabled = false; btn.textContent = 'Delete';
                    if (data && data.success) { closeDeleteModal(); fetchEntries(); showToast('Entry deleted.', 'delete'); }
                    else showErr(data?.message ?? 'Could not delete. Please try again.');
                })
                .catch(err => {
                    btn.disabled = false; btn.textContent = 'Delete';
                    showErr(err?.server ? err.message : 'Could not reach the server. Please try again.');
                });
        }

        function runSave(payload) {
            setSaving(true);
            postJson(`${BASE_URL_JS}/${SAVE_ROUTE}`, payload)
                .then(data => {
                    setSaving(false);
                    if (data && data.success) {
                        const wasEdit = editMode;
                        if (!wasEdit) clearDraft();
                        closeModal();
                        fetchEntries();
                        showToast(wasEdit ? 'Entry updated!' : 'Entry added!');
                    } else formError(data?.message ?? 'Something went wrong. Please try again.');
                })
                .catch(err => {
                    setSaving(false);
                    formError(err?.server ? err.message : 'Could not reach the server. Your entry was not saved — check your connection and try again.');
                });
        }

        // Isara ang modal: background click o Esc
        document.addEventListener('click', e => {
            if (e.target.id === 'entry-modal') closeModal();
            if (e.target.id === 'delete-modal') closeDeleteModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const top = ['delete-modal', 'dept-manager-modal', 'title-manager-modal', 'entry-modal']
                .find(id => document.getElementById(id) && !document.getElementById(id).classList.contains('hidden'));
            if (top === 'delete-modal') closeDeleteModal();
            else if (top === 'dept-manager-modal') closeDeptManager();
            else if (top === 'title-manager-modal') closeTitleManager();
            else if (top === 'entry-modal') closeModal();
        });

        // ─── Entry type ──────────────────────────────────────
        function getEntryType() { return document.getElementById('entry-type').value; }
        function setEntryType(t) {
            t = t === 'beginning' ? 'beginning' : 'received';
            document.getElementById('entry-type').value = t;
            document.querySelectorAll('.pc-type-btn').forEach(b => b.dataset.active = b.dataset.type === t ? '1' : '0');
            document.getElementById('type-hint').textContent = t === 'beginning'
                ? 'The starting cash for this month (usually last month\'s remaining balance). Only one per month.'
                : 'Cash received into petty cash (e.g. replenishment).';
            const p = document.getElementById('entry-particulars');
            p.placeholder = t === 'beginning' ? 'e.g. Petty cash beginning balance' : 'e.g. Petty cash replenishment';
            saveDraft();
        }

        // ─── Fetch ───────────────────────────────────────────
        function fetchEntries() {
            const month = curMonth();
            const seq = ++fetchSeq;
            document.getElementById('sheet-tbody').innerHTML =
                '<div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>';
            const q = encodeURIComponent(month);
            Promise.all([
                getJson(`${BASE_URL_JS}/fetchgeneralsheet?month=${q}`),
                getJson(`${BASE_URL_JS}/fetchcustodiansheetpettycashtwo?month=${q}`).catch(() => null), // para sa Remaining
            ]).then(([data, expenses]) => {
                if (seq !== fetchSeq) return;
                allEntries = Array.isArray(data) ? data : [];
                expenseOk = Array.isArray(expenses);
                expenseTotal = expenseOk ? expenses.reduce((s, r) => s + num(r.actual), 0) : 0;
                expenseCount = expenseOk ? expenses.length : 0;
                fillSuggestions();
                applyFilters();
            }).catch(err => {
                if (seq !== fetchSeq) return;
                console.error(err);
                document.getElementById('sheet-tbody').innerHTML = emptyHtml('Could not load entries.',
                    err?.server ? err.message : 'Check your connection, then try again.');
            });
        }

        function fillSuggestions() {
            const uniq = key => [...new Set(allEntries.map(r => (r[key] ?? '').trim()).filter(Boolean))].sort();
            document.getElementById('pc-title-list').innerHTML = uniq('account_title').map(v => `<option value="${escHtml(v)}">`).join('');
            document.getElementById('pc-source-list').innerHTML = uniq('in_charge').map(v => `<option value="${escHtml(v)}">`).join('');
        }

        function applyFilters() {
            const q = document.getElementById('pc-search').value.trim().toLowerCase();
            const type = document.getElementById('pc-type-filter').value;
            const shown = allEntries.filter(r =>
                (!type || (type === 'beginning' ? r.entry_type === 'beginning' : r.entry_type !== 'beginning')) &&
                (!q || [r.particulars, r.voucher_no, r.account_title, r.in_charge, r.reference, r.inserted_by]
                    .some(v => v && String(v).toLowerCase().includes(q))));
            renderTable(shown, !!(q || type));
            renderSummary(shown);
        }

        // ─── Render ──────────────────────────────────────────
        function renderTable(data, filtered) {
            const box = document.getElementById('sheet-tbody');
            const m = monthLabel(curMonth());
            document.getElementById('pc-info').innerHTML =
                `Showing <b class="text-gray-700">${data.length}</b> of ${allEntries.length} entr${allEntries.length === 1 ? 'y' : 'ies'} · <b class="text-gray-700">${escHtml(m)}</b>`;
            if (!data.length) {
                box.innerHTML = filtered
                    ? emptyHtml('No matching entries.', 'Try another search or type.')
                    : emptyHtml(`No entries for ${escHtml(m)}.`, 'Start with the Beginning Balance, then add receipts.');
                return;
            }
            box.innerHTML = data.map((row, i) => {
                const id = Number(row.id);
                const isBeg = row.entry_type === 'beginning';
                return `
                <div data-id="${id}" class="pc-row flex items-stretch gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50 transition-colors ${isBeg ? 'bg-amber-50/40' : ''}">
                    <div class="w-7 shrink-0 pt-0.5 text-right text-[11px] font-mono text-gray-400">${i + 1}</div>
                    <div class="w-1 rounded-full shrink-0 ${isBeg ? 'bg-amber-500' : 'bg-green-500'}"></div>
                    <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>${escHtml(fmtDay(row.date))}</span>
                                ${isBeg
                                    ? '<span class="text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-800 rounded-full px-2 py-0.5">Beginning</span>'
                                    : '<span class="text-[10px] font-bold uppercase tracking-wide bg-green-100 text-green-800 rounded-full px-2 py-0.5">Receipt</span>'}
                                ${row.voucher_no ? `<span class="notranslate font-mono text-[11px] text-gray-600 bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5"><span class="font-sans font-semibold text-[9px] uppercase">CV</span> ${escHtml(row.voucher_no)}</span>` : ''}
                                ${row.account_title ? `<span class="text-[10px] font-semibold text-gray-600 bg-gray-100 rounded-full px-2 py-0.5">${escHtml(row.account_title)}</span>` : ''}
                            </div>
                            <p class="mt-1 text-sm font-semibold text-gray-800 break-words">${escHtml(row.particulars || '—')}</p>
                            <div class="flex items-center gap-x-3 gap-y-0.5 mt-0.5 flex-wrap text-[11px] text-gray-500">
                                ${row.in_charge ? `<span class="truncate"><span class="text-gray-400">Source:</span> ${escHtml(row.in_charge)}</span>` : ''}
                                ${row.reference ? `<span class="truncate"><span class="text-gray-400">Ref:</span> ${escHtml(row.reference)}</span>` : ''}
                                ${row.inserted_by ? `<span class="truncate text-gray-400">Added by ${escHtml(row.inserted_by)}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0">
                            <p class="font-mono text-sm font-bold ${isBeg ? 'text-amber-800' : 'text-green-700'} lg:min-w-[120px] lg:text-right">${fmt(row.cash_inflows)}</p>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="editEntry(${id})" title="Edit"
                                    class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-gray-200 bg-white hover:border-amber-500 hover:text-amber-700 text-gray-600 text-xs font-semibold transition">
                                    <i class="fa-solid fa-pen text-[10px]"></i><span class="hidden sm:inline">Edit</span>
                                </button>
                                <button type="button" onclick="openDeleteModal(${id})" title="Delete"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:border-red-300 hover:bg-red-50 text-gray-500 hover:text-red-600 transition">
                                    <i class="fa-solid fa-trash text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        // Ang summary ay laging para sa BUONG buwan (hindi apektado ng search)
        function renderSummary(shown) {
            let beginning = 0, inflows = 0, begCount = 0, recCount = 0;
            allEntries.forEach(r => {
                if (r.entry_type === 'beginning') { beginning = num(r.cash_inflows); begCount++; } // gaya ng dati: ang huli ang bilang
                else { inflows += num(r.cash_inflows); recCount++; }
            });
            const available = beginning + inflows;
            const remaining = available - expenseTotal;

            document.getElementById('card-beginning').textContent = fmt(beginning);
            document.getElementById('card-beginning-sub').textContent = begCount ? 'Set for this month' : 'Not set yet';
            document.getElementById('card-inflows').textContent = fmt(inflows);
            document.getElementById('card-inflows-sub').textContent = recCount + ' receipt' + (recCount === 1 ? '' : 's');
            document.getElementById('card-expenses').textContent = expenseOk ? fmt(expenseTotal) : '—';
            document.getElementById('card-expenses-sub').textContent = expenseOk ? expenseCount + ' expense' + (expenseCount === 1 ? '' : 's') : 'Could not load Sheet Two';
            document.getElementById('card-remaining').textContent = expenseOk ? fmt(remaining) : '—';
            document.getElementById('card-remaining').classList.toggle('text-red-600', expenseOk && remaining < 0);
            document.getElementById('card-remaining-sub').textContent = expenseOk ? (remaining < 0 ? 'Overspent!' : 'Available cash') : '';

            const shownTotal = shown.reduce((s, r) => s + num(r.cash_inflows), 0);
            document.getElementById('foot-inflows').textContent = fmt(shownTotal);
            document.getElementById('foot-label').textContent = shown.length !== allEntries.length ? '(filtered)' : '(beginning + receipts)';

            const warn = document.getElementById('beginning-warning');
            warn.classList.toggle('hidden', begCount <= 1);
            if (begCount > 1) warn.innerHTML = `<i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                <span>There are <b>${begCount}</b> Beginning Balance entries this month. Only the last one is counted — please delete the extra ones.</span>`;

            const bar = document.getElementById('carryover-bar');
            bar.classList.toggle('hidden', !allEntries.length || !expenseOk);
            document.getElementById('carryover-month').textContent = monthLabel(curMonth());
            document.getElementById('carryover-amount').textContent = fmt(remaining);
        }

        // ─── Add / Edit ──────────────────────────────────────
        function setDateField(value, editable) {
            const el = document.getElementById('entry-date');
            el.value = value || '';
            el.readOnly = !editable;
            document.getElementById('date-hint').innerHTML = editable ? '' : '<i class="fa-solid fa-lock mr-1"></i>Set to today automatically';
        }

        function openAddModal() {
            editMode = false;
            clearErrors();
            setModalHeader(false);
            document.getElementById('entry-id').value = '';
            setDateField(todayPH(), false);
            FIELDS.forEach(f => document.getElementById(f).value = '');
            const draft = readDraft();
            document.getElementById('pc-draft-note').classList.toggle('hidden', !draft);
            if (draft) FIELDS.forEach(f => { if (draft[f] !== undefined) document.getElementById(f).value = draft[f]; });
            openModal();
            setEntryType(draft?._type || 'received');
        }

        function editEntry(id) {
            const row = allEntries.find(r => r.id == id);
            if (!row) return;
            editMode = true;
            clearErrors();
            setModalHeader(true);
            document.getElementById('pc-draft-note').classList.add('hidden');
            document.getElementById('entry-id').value = row.id;
            setDateField(row.date ?? '', true);
            document.getElementById('entry-cash-inflows').value = row.cash_inflows ?? '';
            document.getElementById('entry-voucher-no').value = row.voucher_no ?? '';
            document.getElementById('entry-account-title').value = row.account_title ?? '';
            document.getElementById('entry-particulars').value = row.particulars ?? '';
            document.getElementById('entry-in-charge').value = row.in_charge ?? '';
            document.getElementById('entry-reference').value = row.reference ?? '';
            setEntryType(row.entry_type);
            openModal();
        }

        function saveEntry() {
            if (isSaving) return;
            clearErrors();
            const date = document.getElementById('entry-date').value;
            const particulars = document.getElementById('entry-particulars').value.trim();
            const amtRaw = document.getElementById('entry-cash-inflows').value.trim();
            const amount = Number(amtRaw);
            const type = getEntryType();
            let ok = true;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { fieldError('date', 'Date is required.'); ok = false; }
            if (!particulars) { fieldError('particulars', 'Particulars is required.'); ok = false; }
            if (amtRaw === '' || !Number.isFinite(amount) || amount < 0) { fieldError('cash-inflows', 'Enter the amount (0 or more).'); ok = false; }
            if (ok && type === 'beginning') {
                const other = allEntries.find(r => r.entry_type === 'beginning' && String(r.id) !== String(document.getElementById('entry-id').value)
                    && String(r.date ?? '').slice(0, 7) === date.slice(0, 7));
                if (other) { formError(`There is already a Beginning Balance for ${monthLabel(date.slice(0, 7))} (${fmt(other.cash_inflows)}). Edit that one instead.`); ok = false; }
            }
            if (!ok) return;

            runSave({
                id: document.getElementById('entry-id').value || null,
                entry_type: type,
                date,
                cash_inflows: amtRaw,
                voucher_no: document.getElementById('entry-voucher-no').value.trim(),
                account_title: document.getElementById('entry-account-title').value.trim(),
                particulars,
                in_charge: document.getElementById('entry-in-charge').value.trim(),
                reference: document.getElementById('entry-reference').value.trim(),
            });
        }

        function deleteSummary(row) {
            return `<b>${escHtml(row.particulars || '—')}</b><br>${escHtml(fmtDay(row.date))} · ${escHtml(fmt(row.cash_inflows))}`;
        }

        // ─── Init ────────────────────────────────────────────
        bindDraft();
        document.getElementById('pc-search').addEventListener('input', applyFilters);
        document.getElementById('pc-type-filter').addEventListener('change', applyFilters);
        fetchEntries();

    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'pcg-picked';
            const SEL = '#sheet-tbody .pc-row';
            let picked = null;
            try { picked = sessionStorage.getItem(KEY); } catch (e) { }

            if (!document.getElementById('nh-picked-style')) {
                const st = document.createElement('style');
                st.id = 'nh-picked-style';
                st.textContent = `
                    [data-picked="1"] { position: relative; background: #fffbeb !important;
                        box-shadow: inset 3px 0 0 #d97706, inset 0 0 0 1px #fcd34d; }
                    [data-picked="1"]:hover { background: #fef3c7 !important; }
                    [data-picked="1"]::after { content: 'Last opened'; position: absolute; top: 3px; right: 10px;
                        font-size: 9px; font-weight: 700; letter-spacing: .02em; color: #92400e;
                        background: #fde68a; border-radius: 9999px; padding: 1px 7px; pointer-events: none; }`;
                document.head.appendChild(st);
            }

            function apply() {
                document.querySelectorAll(SEL).forEach(el => {
                    const on = picked !== null && String(el.dataset.id) === picked;
                    const v = on ? '1' : '0';
                    if (el.dataset.picked !== v) el.dataset.picked = v;
                });
            }

            // capture: mauuna ito bago buksan ang modal
            document.addEventListener('click', e => {
                const row = e.target.closest(SEL);
                if (!row || row.dataset.id === undefined) return;
                picked = String(row.dataset.id);
                try { sessionStorage.setItem(KEY, picked); } catch (err) { }
                apply();
            }, true);

            // I-apply ulit tuwing magre-render ang listahan (auto-refresh, filter, ibang buwan)
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
            apply();
        })();
    </script>

</body>

</html>