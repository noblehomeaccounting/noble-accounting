<?php
// index-transmittalsummary.php  —  Summary of Transmittal (Purchasing)
// Pumapasok dito ang bawat na-save na transmittal (For delivery).
// Nagiging "Delivered" lang kapag kinumpirma gamit ang Mark as Delivered.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

// Transmittal: magagamit na ng LAHAT ng role (staff o head)
$allowedRoles = array_values(array_unique(array_filter(array_map(
    fn($c) => defined($c) ? constant($c) : null,
    ['ROLE_SUPERADMIN', 'ROLE_PO', 'ROLE_PC', 'ROLE_ACCOUNTING', 'ROLE_SALES', 'ROLE_DESIGNER', 'ROLE_CUTTING',
     'ROLE_OPERATIONS', 'ROLE_GRAPHIC', 'ROLE_HR', 'ROLE_IT']
))));
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$tsAjaxUrl = BASE_URL . '/transmittalsummaryajax';

// PO / Superadmin: lahat · iba: sariling transmittal lang
$tsCanAll = in_array($_SESSION['role'] ?? '', array_filter([
    defined('ROLE_PO') ? ROLE_PO : null,
    defined('ROLE_SUPERADMIN') ? ROLE_SUPERADMIN : null,
]), true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>Summary of Transmittal</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen px-3 pt-20 pb-6 sm:px-4 md:p-8 md:pt-5 overflow-x-hidden transition-all duration-300">

        <!-- ===================== HEADER ===================== -->
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="min-w-0">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5"><?= $tsCanAll ? 'Purchasing' : 'My Transmittals' ?></p>
                <h1 class="text-gray-900 text-lg sm:text-xl font-semibold">Summary of Transmittal</h1>
                <p class="text-gray-400 text-xs mt-0.5"><?= $tsCanAll ? 'Every saved transmittal' : 'Each transmittal you made' ?> lands here as <b class="text-orange-600">For delivery</b>. Mark it delivered once the client has it.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <span class="mr-auto sm:mr-0 inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5" title="Updates automatically when someone else makes a change"><span id="tsLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="tsLiveText">Auto-refresh</span></span>
                <a href="<?= BASE_URL ?>/transmittal" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                    <i class="fa-solid fa-file-export"></i> Transmittals
                </a>
                <button type="button" id="tsExport" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                    <i class="fa-solid fa-file-csv"></i> Export
                </button>
            </div>
        </div>

        <!-- ===================== STATS ===================== -->
        <div class="grid grid-cols-3 gap-1.5 sm:gap-3 mb-3 sm:mb-4">
            <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-orange-50 text-orange-600 items-center justify-center"><i class="fa-solid fa-truck-ramp-box"></i></span>
                <div class="min-w-0"><p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">To deliver</span><span class="hidden sm:inline">For Delivery</span></p><p id="tsStatPending" class="text-lg sm:text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
            <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-green-50 text-green-600 items-center justify-center"><i class="fa-solid fa-truck"></i></span>
                <div class="min-w-0"><p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">This month</span><span class="hidden sm:inline">Delivered this month</span></p><p id="tsStatMonth" class="text-lg sm:text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
            <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-slate-100 text-slate-600 items-center justify-center"><i class="fa-solid fa-file-lines"></i></span>
                <div class="min-w-0"><p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">Total</span><span class="hidden sm:inline">Total Transmittals</span></p><p id="tsStatTotal" class="text-lg sm:text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
        </div>

        <!-- ===================== TABLE CARD ===================== -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 px-3 sm:px-4 py-3 border-b border-gray-100">
                <div id="tsTabs" class="grid grid-cols-3 sm:inline-flex w-full sm:w-auto items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1 [&>button]:justify-center [&>button]:whitespace-nowrap [&>button]:min-w-0"></div>
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto sm:ml-auto">
                    <div class="relative flex-1 sm:flex-none min-w-0">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="tsSearch" placeholder="Series no., particulars, mode…"
                            class="w-full sm:w-56 h-[38px] sm:h-auto border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600">
                    </div>
                    <button type="button" data-fd-open="tsFd" class="sm:hidden shrink-0 inline-flex items-center gap-1.5 h-[38px] px-3 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-500 "><i class="fa-solid fa-sliders"></i> Filters <span data-fd-badge class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-amber-700 text-white text-[10px] font-bold inline-flex items-center justify-center">0</span></button>
                    <div id="tsFdBackdrop" class="nh-fd-backdrop"></div>
<div id="tsFd" class="nh-fd" role="dialog" aria-label="Filters">
    <div class="nh-fd-only flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2"><i class="fa-solid fa-sliders text-amber-600"></i><h2 class="text-gray-900 text-sm font-semibold">Filters</h2></div>
        <button type="button" data-fd-close aria-label="Close" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg text-2xl leading-none">&times;</button>
    </div>
    <div class="nh-fd-body">
<p class="nh-fd-only nh-fd-label">Client</p>
                    <select id="tsClient" class="w-full sm:w-auto h-[38px] sm:h-auto border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600 bg-white sm:max-w-[180px]">
                        <option value="">All clients</option>
                    </select>
                    <p class="nh-fd-only nh-fd-label">Transmittal date</p>
                    <div class="flex sm:inline-flex items-center gap-1 border border-gray-200 rounded-lg px-2 py-1 bg-white">
                        <span class="hidden sm:inline text-[10px] font-semibold uppercase tracking-wider text-gray-400">Date</span>
                        <input type="date" id="tsFrom" class="flex-1 sm:flex-none min-w-0 h-9 sm:h-auto text-xs outline-none bg-transparent px-1 py-1" title="From">
                        <span class="text-gray-300">–</span>
                        <input type="date" id="tsTo" class="flex-1 sm:flex-none min-w-0 h-9 sm:h-auto text-xs outline-none bg-transparent px-1 py-1" title="To">
                    </div>
                    <button type="button" id="tsReset" class="sm:px-2 py-2 text-[11px] font-semibold text-gray-500 sm:text-gray-400 hover:text-amber-700 text-left" title="Clear filters"><i class="fa-solid fa-filter-circle-xmark"></i><span class="sm:hidden"> Clear filters</span></button>

    </div>
    <div class="nh-fd-only px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] border-t border-gray-100 bg-gray-50/60">
        <button type="button" data-fd-close class="w-full text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg transition-colors">Show results</button>
    </div>
</div>
                </div>
            </div>

            <!-- Phone: cards (ang table ay para sa tablet/desktop) -->
            <div id="tsCards" class="md:hidden divide-y divide-gray-100"></div>
            <div class="hidden md:block overflow-y-auto overflow-x-hidden max-h-[calc(100vh-380px)] min-h-[260px]">
                <table class="w-full table-fixed text-xs border-separate border-spacing-0">
                    <colgroup>
                        <col style="width:10%"><col style="width:11%"><col style="width:15%"><col style="width:17%">
                        <col style="width:11%"><col style="width:14%"><col><col style="width:140px">
                    </colgroup>
                    <thead class="sticky top-0 z-10">
                        <tr><th colspan="8" class="bg-amber-400 text-gray-900 text-sm font-bold py-2.5 text-center tracking-wide">Summary of Transmittal</th></tr>
                        <tr class="bg-green-600 text-white text-[11px] font-semibold">
                            <th class="px-3 py-2.5 text-left">Date</th>
                            <th class="px-3 py-2.5 text-left">Series Number</th>
                            <th class="px-3 py-2.5 text-left">Company / Client</th>
                            <th class="px-3 py-2.5 text-left">Particulars</th>
                            <th class="px-3 py-2.5 text-left">Date Delivered</th>
                            <th class="px-3 py-2.5 text-left">Mode of Delivery</th>
                            <th class="px-3 py-2.5 text-left">Remarks</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tsBody">
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <p id="tsFooter" class="px-3 sm:px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
        </div>

        <datalist id="tsModes"><option value="Pick up by Client"><option value="Lalamove"><option value="Deliver"></datalist>
        <div id="crmToastContainer" class="fixed left-4 right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] sm:left-auto sm:bottom-4 z-[10000] flex flex-col gap-2.5 pointer-events-none sm:w-[340px]"></div>
    </main>

    <!-- ===================== MARK AS DELIVERED MODAL ===================== -->
    <div id="tsDeliverModal" class="hidden fixed inset-0 z-[9999] items-end sm:items-center justify-center bg-black/50 sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full sm:max-w-md max-h-[92vh] overflow-y-auto pb-[env(safe-area-inset-bottom)]">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-truck text-sm"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Mark as delivered?</p>
                        <p id="tsDlvRef" class="font-mono text-[11px] font-semibold text-gray-700"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1 mb-4">
                    <p><span class="text-gray-400">Client:</span> <span id="tsDlvClient" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Particulars:</span> <span id="tsDlvPart" class="font-semibold text-gray-800"></span></p>
                </div>
                <div class="grid grid-cols-1 min-[380px]:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Date Delivered <span class="text-red-500">*</span></span>
                        <input type="date" id="tsDlvDate" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Mode of Delivery <span class="text-red-500">*</span></span>
                        <input type="text" id="tsDlvMode" list="tsModes" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                    <label class="block min-[380px]:col-span-2">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Remarks</span>
                        <input type="text" id="tsDlvRemarks" maxlength="1000" placeholder="Optional" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                </div>
                <label class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 cursor-pointer">
                    <input type="checkbox" id="tsDlvConfirm" class="mt-0.5 w-5 h-5 sm:w-4 sm:h-4 shrink-0 accent-green-600">
                    <span class="text-[11px] text-amber-900 leading-relaxed">I confirm the items under this transmittal were <b>delivered to / picked up by the client</b>. This can't be undone.</span>
                </label>
                <p id="tsDlvError" class="hidden mt-2 text-[11px] text-red-600"></p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" data-close="tsDeliverModal" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="tsDlvOk" disabled class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-check mr-1"></i> Yes, delivered
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== EDIT MODAL (habang For delivery) ===================== -->
    <div id="tsEditModal" class="hidden fixed inset-0 z-[9999] items-end sm:items-center justify-center bg-black/50 sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full sm:max-w-md max-h-[92vh] overflow-y-auto pb-[env(safe-area-inset-bottom)]">
            <div class="p-5">
                <p class="text-sm font-semibold text-gray-800">Edit summary details</p>
                <p id="tsEditRef" class="font-mono text-[11px] font-semibold text-gray-500 mb-4"></p>
                <div class="space-y-3">
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Particulars <span class="text-red-500">*</span></span>
                        <input type="text" id="tsEditPart" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Mode of Delivery</span>
                        <input type="text" id="tsEditMode" list="tsModes" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Remarks</span>
                        <input type="text" id="tsEditRemarks" maxlength="1000" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" data-close="tsEditModal" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="tsEditOk" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const AJAX = <?= json_encode($tsAjaxUrl) ?>;
            const TR_URL = <?= json_encode(BASE_URL . '/transmittal') ?>;

            let rows = [];
            let tab = 'pending';
            let target = null;

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const ymd = v => v ? String(v).slice(0, 10) : '';
            const mdy = v => { const s = ymd(v); if (!s) return ''; const [y, m, d] = s.split('-'); return `${m}/${d}/${y}`; };
            const todayISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
            const company = r => r.client_company || r.client_name || '';

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
            const openModal = id => { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); };
            const closeModal = id => { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); };
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.dataset.close)));
            ['tsDeliverModal', 'tsEditModal'].forEach(id => document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); }));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('tsDeliverModal'); closeModal('tsEditModal'); } });

            const memo = {
                get: k => { try { return localStorage.getItem('nh-ts-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-ts-' + k, v); } catch (e) { } },
            };

            // ── tabs + filters ──
            const TABS = [
                { key: 'pending', label: 'For Delivery', dot: 'bg-orange-500', test: r => !r.delivered_at },
                { key: 'done',    label: 'Delivered',    dot: 'bg-green-500',  test: r => !!r.delivered_at },
                { key: 'all',     label: 'All',          dot: 'bg-gray-400',   test: () => true },
            ];
            tab = memo.get('tab') || 'pending';
            ['tsSearch', 'tsFrom', 'tsTo'].forEach(id => {
                const el = document.getElementById(id);
                el.value = memo.get(id) || '';
                el.addEventListener(id === 'tsSearch' ? 'input' : 'change', () => { memo.set(id, el.value); render(); });
            });
            document.getElementById('tsClient').addEventListener('change', e => { memo.set('tsClient', e.target.value); render(); });
            document.getElementById('tsReset').addEventListener('click', () => {
                ['tsSearch', 'tsFrom', 'tsTo', 'tsClient'].forEach(id => { document.getElementById(id).value = ''; memo.set(id, ''); });
                render();
            });

            function baseFiltered() {
                const q = document.getElementById('tsSearch').value.trim().toLowerCase();
                const cl = document.getElementById('tsClient').value;
                const from = document.getElementById('tsFrom').value, to = document.getElementById('tsTo').value;
                return rows.filter(r => {
                    if (q && !`${r.ref_no} ${r.req_no ?? ''} ${company(r)} ${r.particulars ?? ''} ${r.delivery_mode ?? ''} ${r.summary_remarks ?? ''}`.toLowerCase().includes(q)) return false;
                    if (cl && company(r) !== cl) return false;
                    const d = ymd(r.transmittal_date);
                    if (from && d < from) return false;
                    if (to && d > to) return false;
                    return true;
                });
            }
            const filtered = () => baseFiltered().filter((TABS.find(t => t.key === tab) || TABS[0]).test);

            function renderTabs() {
                const base = baseFiltered();
                document.getElementById('tsTabs').innerHTML = TABS.map(t => {
                    const on = t.key === tab;
                    return `<button type="button" data-tab="${t.key}" class="inline-flex items-center gap-1.5 sm:gap-2 px-1.5 sm:px-3 py-2 sm:py-1.5 rounded-md text-xs font-semibold transition-all ${on ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'}">
                        <span class="hidden sm:inline-block shrink-0 w-1.5 h-1.5 rounded-full ${t.dot}"></span><span class="truncate">${t.label}</span>
                        <span class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">${base.filter(t.test).length}</span></button>`;
                }).join('');
            }
            document.getElementById('tsTabs').addEventListener('click', e => {
                const b = e.target.closest('[data-tab]');
                if (!b) return;
                tab = b.dataset.tab; memo.set('tab', tab); render();
            });

            // ── table ──
            function render() {
                renderTabs();
                const month = todayISO().slice(0, 7);
                document.getElementById('tsStatPending').textContent = rows.filter(r => !r.delivered_at).length;
                document.getElementById('tsStatMonth').textContent = rows.filter(r => ymd(r.delivered_at).startsWith(month)).length;
                document.getElementById('tsStatTotal').textContent = rows.length;

                const list = filtered();
                const body = document.getElementById('tsBody');
                document.getElementById('tsFooter').textContent = `Showing ${list.length} of ${rows.length} transmittal${rows.length === 1 ? '' : 's'}`;
                if (!list.length) {
                    body.innerHTML = `<tr><td colspan="8" class="px-5 py-14 text-center text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${rows.length ? 'Nothing here for these filters.' : 'No transmittals yet. They appear here once saved.'}</td></tr>`;
                    document.getElementById('tsCards').innerHTML = `<div class="px-4 py-12 text-center text-xs text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${rows.length ? 'Nothing here for these filters.' : 'No transmittals yet. They appear here once saved.'}</div>`;
                    return;
                }
                // Phone: card bawat transmittal
                document.getElementById('tsCards').innerHTML = list.map(r => {
                    const done = !!r.delivered_at;
                    return `
                    <div class="${done ? 'bg-white' : 'bg-orange-50/30'} px-3 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="${TR_URL}?view=${r.id}" class="font-mono text-[12px] font-semibold text-gray-900 hover:underline">${esc(r.ref_no)}</a>${r.req_no ? ` <span class="font-mono text-[10.5px] text-red-500">· ${esc(r.req_no)}</span>` : ''}
                                <p class="font-semibold text-sm text-gray-900 truncate">${esc(company(r))}</p>
                            </div>
                            <span class="shrink-0 text-[11px] text-gray-500">${mdy(r.transmittal_date)}</span>
                        </div>
                        ${r.particulars ? `<p class="mt-1 text-[12px] text-gray-700 line-clamp-2">${esc(r.particulars)}</p>` : ''}
                        <dl class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2 text-[11px]">
                            <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Delivered</dt><dd>${done ? `<span class="font-semibold text-green-700">${mdy(r.delivered_at)}</span>` : '<span class="font-semibold text-orange-600">For delivery</span>'}</dd></div>
                            <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Mode</dt><dd class="text-gray-700 truncate">${esc(r.delivery_mode || '—')}</dd></div>
                        </dl>
                        ${r.summary_remarks ? `<p class="mt-1.5 text-[11px] text-gray-500 line-clamp-2">${esc(r.summary_remarks)}</p>` : ''}
                        <div class="mt-2.5">${done
                            ? '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-green-50 text-green-700 text-[10.5px] font-bold"><i class="fa-solid fa-circle-check"></i> DELIVERED</span>'
                            : `<div class="flex items-center gap-2">
                                   <button type="button" data-edit="${r.id}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg"><i class="fa-solid fa-pen text-[10px]"></i> Edit</button>
                                   <button type="button" data-deliver="${r.id}" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm"><i class="fa-solid fa-truck text-[10px]"></i> Mark as delivered</button>
                               </div>`}</div>
                    </div>`;
                }).join('');
                const td = 'px-3 py-2.5 border-b border-gray-100 align-middle';
                body.innerHTML = list.map(r => {
                    const done = !!r.delivered_at;
                    const action = done
                        ? `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-green-50 text-green-700 text-[10.5px] font-bold"><i class="fa-solid fa-circle-check"></i> DELIVERED</span>`
                        : `<div class="inline-flex items-center gap-1">
                               <button type="button" data-edit="${r.id}" title="Edit details" class="w-7 h-7 inline-flex items-center justify-center text-gray-500 bg-white border border-gray-200 rounded-md hover:bg-gray-50"><i class="fa-solid fa-pen text-[10px]"></i></button>
                               <button type="button" data-deliver="${r.id}" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 shadow-sm"><i class="fa-solid fa-truck text-[10px]"></i> Delivered</button>
                           </div>`;
                    return `
                    <tr class="${done ? 'bg-white' : 'bg-orange-50/30'} hover:bg-amber-50/40">
                        <td class="${td} text-gray-700 whitespace-nowrap">${mdy(r.transmittal_date)}</td>
                        <td class="${td}"><a href="${TR_URL}?view=${r.id}" class="font-mono font-semibold text-gray-900 hover:text-amber-700 hover:underline">${esc(r.ref_no)}</a>
                            ${r.req_no ? `<p class="font-mono text-[10px] text-red-500 truncate">${esc(r.req_no)}</p>` : ''}</td>
                        <td class="${td}"><p class="font-semibold text-gray-800 truncate" title="${esc(company(r))}">${esc(company(r))}</p></td>
                        <td class="${td} text-gray-700"><p class="line-clamp-2">${esc(r.particulars || '—')}</p></td>
                        <td class="${td} whitespace-nowrap">${done ? `<span class="font-semibold text-green-700">${mdy(r.delivered_at)}</span>` : '<span class="inline-flex items-center gap-1 text-[11px] font-semibold text-orange-600"><span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>For delivery</span>'}</td>
                        <td class="${td} text-gray-700"><p class="truncate" title="${esc(r.delivery_mode || '')}">${esc(r.delivery_mode || '—')}</p></td>
                        <td class="${td} text-gray-500"><p class="line-clamp-2" title="${esc(r.summary_remarks || '')}">${esc(r.summary_remarks || '')}</p></td>
                        <td class="${td} text-right whitespace-nowrap">${action}</td>
                    </tr>`;
                }).join('');
            }

            function fillClients(modes) {
                const sel = document.getElementById('tsClient');
                const keep = sel.value || memo.get('tsClient') || '';
                const names = [...new Set(rows.map(company).filter(Boolean))].sort((a, b) => a.localeCompare(b));
                sel.innerHTML = '<option value="">All clients</option>' + names.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
                sel.value = names.includes(keep) ? keep : '';
                const dl = document.getElementById('tsModes');
                modes.forEach(m => { if (![...dl.options].some(o => o.value.toLowerCase() === m.toLowerCase())) dl.insertAdjacentHTML('beforeend', `<option value="${esc(m)}">`); });
            }

            let lastSig = '';
            async function load(silent) {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) { if (!silent) toast(d.message || 'Failed to load.', 'error'); return; }
                    // tahimik na refresh: i-render lang kapag may nagbago
                    const sig = JSON.stringify(d.rows.map(r => [r.id, r.delivered_at, r.particulars, r.delivery_mode, r.summary_remarks]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const known = new Set(rows.map(r => r.id));
                        const fresh = d.rows.filter(r => !known.has(r.id)).length;
                        if (fresh) toast(`${fresh} new transmittal${fresh === 1 ? '' : 's'} for delivery.`);
                    }
                    lastSig = sig;
                    rows = d.rows;
                    fillClients(d.modes || []);
                    render();
                } catch (e) { if (!silent) toast('Connection error while loading.', 'error'); }
            }

            ['tsBody', 'tsCards'].forEach(id => document.getElementById(id).addEventListener('click', e => {
                const dv = e.target.closest('[data-deliver]');
                const ed = e.target.closest('[data-edit]');
                if (dv) openDeliver(+dv.dataset.deliver);
                if (ed) openEdit(+ed.dataset.edit);
            }));

            // ── Mark as Delivered (may validation) ──
            const okBtn = document.getElementById('tsDlvOk');
            const chk = document.getElementById('tsDlvConfirm');
            const dlvErr = msg => { const p = document.getElementById('tsDlvError'); p.textContent = msg || ''; p.classList.toggle('hidden', !msg); };
            function openDeliver(id) {
                target = rows.find(r => r.id === id);
                if (!target) return;
                document.getElementById('tsDlvRef').textContent = target.ref_no;
                document.getElementById('tsDlvClient').textContent = company(target);
                document.getElementById('tsDlvPart').textContent = target.particulars || '—';
                const date = document.getElementById('tsDlvDate');
                date.min = ymd(target.transmittal_date);
                date.max = todayISO();
                date.value = todayISO();
                document.getElementById('tsDlvMode').value = target.delivery_mode || '';
                document.getElementById('tsDlvRemarks').value = target.summary_remarks || '';
                chk.checked = false;
                okBtn.disabled = true;
                dlvErr('');
                openModal('tsDeliverModal');
            }
            chk.addEventListener('change', () => { okBtn.disabled = !chk.checked; });

            okBtn.addEventListener('click', async function () {
                if (!target || !chk.checked) return;
                const date = document.getElementById('tsDlvDate').value;
                const mode = document.getElementById('tsDlvMode').value.trim();
                if (!date) return dlvErr('Enter the date it was delivered.');
                if (date < ymd(target.transmittal_date)) return dlvErr('Date delivered cannot be earlier than the transmittal date.');
                if (date > todayISO()) return dlvErr('Date delivered cannot be in the future.');
                if (!mode) return dlvErr('Enter the mode of delivery.');
                dlvErr('');
                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving…';
                try {
                    const res = await fetch(`${AJAX}?action=deliver`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: target.id, delivered_at: date, delivery_mode: mode, summary_remarks: document.getElementById('tsDlvRemarks').value.trim(), confirm: '1' })
                    });
                    const d = await res.json();
                    if (!d.success) { dlvErr(d.message || 'Could not save.'); return; }
                    closeModal('tsDeliverModal');
                    toast(`${target.ref_no} marked as delivered.`);
                    load();
                } catch (e) { dlvErr('Connection error. Not saved.'); }
                finally { this.disabled = !chk.checked; this.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Yes, delivered'; }
            });

            // ── Edit details (habang For delivery) ──
            function openEdit(id) {
                target = rows.find(r => r.id === id);
                if (!target) return;
                document.getElementById('tsEditRef').textContent = target.ref_no;
                document.getElementById('tsEditPart').value = target.particulars || '';
                document.getElementById('tsEditMode').value = target.delivery_mode || '';
                document.getElementById('tsEditRemarks').value = target.summary_remarks || '';
                openModal('tsEditModal');
            }
            document.getElementById('tsEditOk').addEventListener('click', async function () {
                if (!target) return;
                const part = document.getElementById('tsEditPart').value.trim();
                if (!part) { toast('Particulars is required.', 'error'); return; }
                this.disabled = true;
                try {
                    const res = await fetch(`${AJAX}?action=update`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: target.id, particulars: part, delivery_mode: document.getElementById('tsEditMode').value.trim(), summary_remarks: document.getElementById('tsEditRemarks').value.trim() })
                    });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Could not save.', 'error'); return; }
                    closeModal('tsEditModal');
                    toast('Details updated.');
                    load();
                } catch (e) { toast('Connection error.', 'error'); }
                finally { this.disabled = false; }
            });

            // ── export ──
            document.getElementById('tsExport').addEventListener('click', () => {
                const q = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
                const head = ['Date', 'Series Number', 'Company/ Client', 'Particulars', 'Date Delivered', 'Mode of Delivery', 'Remarks'];
                const lines = filtered().map(r => [mdy(r.transmittal_date), r.ref_no, company(r), r.particulars || '', r.delivered_at ? mdy(r.delivered_at) : 'For delivery', r.delivery_mode || '', r.summary_remarks || ''].map(q).join(','));
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob(['﻿' + [head.map(q).join(','), ...lines].join('\r\n')], { type: 'text/csv;charset=utf-8' }));
                a.download = `summary-of-transmittal-${todayISO().replace(/-/g, '')}.csv`;
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            });

            load();

            // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
            // Bagong paid na PR / transmittal / delivered (ibang PO o tab) → kunin agad ang listahan.
            // Hindi nire-refresh habang bukas ang Mark as Delivered o Edit (hahabol pagkasara).
            (function hookRealtime() {
                const TYPES = /^(pr_|po_|transmittal)/i;
                const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
                const busy = () => ['tsDeliverModal', 'tsEditModal'].some(id => !document.getElementById(id).classList.contains('hidden'));
                let t = null, missed = false, poll = null;
                const sync = () => {
                    if (document.hidden || busy()) { missed = true; return; }
                    missed = false;
                    load(true);
                };
                const startPolling = () => {
                    if (poll) clearInterval(poll);
                    // Live → 60s fallback lang; hindi Live → 30s
                    poll = setInterval(() => { if (!document.hidden && !busy()) load(true); }, rtLive() ? 60000 : 30000);
                };
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !TYPES.test(type)) return;
                        clearTimeout(t);
                        t = setTimeout(sync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        document.getElementById('tsLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        document.getElementById('tsLiveText').textContent = live ? 'Live' : 'Auto-refresh';
                        if (!document.hidden) startPolling();
                        if (live) sync();
                    });
                };
                hook();
                if (!hookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
                setInterval(() => { if (missed && !document.hidden && !busy()) sync(); }, 5000);
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) { if (poll) clearInterval(poll); }
                    else { sync(); startPolling(); }
                });
                startPolling();
            })();
        })();
    </script>

<style>
    /* ═══ Filter drawer sa phone (kagaya ng CRM Monitoring) · sa sm+ walang epekto (display: contents) ═══ */
    .nh-fd-backdrop { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, .4); z-index: 9998; }
    @media (max-width: 639px) {
        .nh-fd { position: fixed; top: 0; bottom: 0; left: 0; width: 300px; max-width: 88vw; z-index: 9999; background: #fff;
            display: flex; flex-direction: column; transform: translateX(-105%); transition: transform .22s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18); }
        .nh-fd.open { transform: none; }
        .nh-fd-backdrop.open { display: block; }
        .nh-fd-body { flex: 1; overflow-y: auto; padding: 14px 16px 18px; display: flex; flex-direction: column; gap: 14px; }
        .nh-fd-body > * { width: 100%; }
        .nh-fd-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #374151; margin-bottom: -8px; }
    }
    @media (min-width: 640px) {
        .nh-fd, .nh-fd-body { display: contents; }
        .nh-fd-only { display: none !important; }
    }
    @media (max-width: 639px) { #tsFd #tsReset { width: 100%; } }
</style>

<script>
    // Filter drawer sa phone — bubuksan ng [data-fd-open="id"]; badge = ilang filter ang naka-on
    (function () {
        const isPhone = () => !window.matchMedia('(min-width: 640px)').matches;
        function setup(d) {
            const bd = document.getElementById(d.id + 'Backdrop');
            const open = () => { d.classList.add('open'); bd && bd.classList.add('open'); document.body.style.overflow = 'hidden'; };
            const close = () => { if (!d.classList.contains('open')) return; d.classList.remove('open'); bd && bd.classList.remove('open'); document.body.style.overflow = ''; };
            d._fdOpen = open;
            bd && bd.addEventListener('click', close);
            d.querySelectorAll('[data-fd-close]').forEach(b => b.addEventListener('click', close));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (!isPhone()) close(); });
            const badges = document.querySelectorAll(`[data-fd-open="${d.id}"] [data-fd-badge]`);
            const count = () => {
                let n = 0;
                d.querySelectorAll('input, select').forEach(el => {
                    if (el.type === 'hidden' || el.hasAttribute('data-fd-ignore')) return;
                    if (el.tagName === 'SELECT' ? el.selectedIndex > 0 : (el.type === 'checkbox' ? el.checked : !!el.value)) n++;
                });
                badges.forEach(b => { b.textContent = n; b.classList.toggle('hidden', !n); });
            };
            ['change', 'input'].forEach(ev => d.addEventListener(ev, count));
            d.addEventListener('click', () => setTimeout(count, 0));
            setTimeout(count, 0);
        }
        document.querySelectorAll('.nh-fd').forEach(setup);
        document.querySelectorAll('[data-fd-open]').forEach(btn => btn.addEventListener('click', () => {
            const d = document.getElementById(btn.dataset.fdOpen);
            if (d && d._fdOpen) d._fdOpen();
        }));
        window.nhFdOpen = id => { const d = document.getElementById(id); if (d && d._fdOpen) d._fdOpen(); };
    })();
</script>
</body>

</html>