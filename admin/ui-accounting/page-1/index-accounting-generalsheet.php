<?php
// index-accounting-generalsheet.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble General Sheet</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-accounting/css/accounting/index-accounting-generalsheet.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-accounting/css/accounting/index-accounting-generalsheet.css') ?: 1 ?>">
</head>

<body class="bg-gray-100">

    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-book text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Noble General Sheet</h1>
                    <p class="text-sm text-gray-500">Collection and payment records</p>
                </div>
            </div>
            <button type="button" onclick="openAddModal()"
                class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Add Entry
            </button>
        </div>

        <!-- ═══ SUMMARY CARDS (buwan na napili) ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-amber-50 text-amber-700 items-center justify-center shrink-0"><i class="fa-solid fa-list-ol"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Entries</p>
                    <p id="card-total-entries" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-green-50 text-green-600 items-center justify-center shrink-0"><i class="fa-solid fa-peso-sign"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total amount</p>
                    <p id="card-total-amount" class="text-lg sm:text-xl font-bold text-green-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-orange-50 text-orange-600 items-center justify-center shrink-0"><i class="fa-solid fa-receipt"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Via collection receipt</p>
                    <p id="card-cr-amount" class="text-base sm:text-lg font-bold text-gray-800 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-cr-count" class="text-[11px] text-gray-400 mt-1">0 entries</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-blue-50 text-blue-600 items-center justify-center shrink-0"><i class="fa-solid fa-building-columns"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Via deposit</p>
                    <p id="card-dep-amount" class="text-base sm:text-lg font-bold text-gray-800 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-dep-count" class="text-[11px] text-gray-400 mt-1">0 entries</p>
                </div>
            </div>
        </div>

        <!-- ═══ SHEET CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Toolbar -->
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5 px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="changeMonth(-1)" title="Previous month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <label class="relative">
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
                        <input type="text" id="gs-search" placeholder="Search payee, payor, particulars, receipt no..."
                            class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <select id="gs-dept-filter"
                        class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white max-w-[180px]">
                        <option value="">All departments</option>
                    </select>
                </div>
            </div>
            <div id="gs-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>

            <!-- Rows -->
            <div id="sheet-tbody" class="gs-scroll max-h-[calc(100vh-22rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
                <div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
            </div>

            <!-- Footer total -->
            <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-t border-gray-200 bg-amber-50">
                <span class="text-xs font-bold uppercase tracking-wide text-amber-900">Total <span id="foot-label" class="font-normal normal-case text-amber-800"></span></span>
                <span id="foot-amount" class="font-mono font-bold text-base text-amber-900">₱ 0.00</span>
            </div>
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
                        <p id="modal-sub" class="text-[11px] text-gray-500">Fields with <span class="text-red-500">*</span> are required</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal()" title="Close"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="entry-form" class="gs-scroll px-5 py-5 grid grid-cols-1 sm:grid-cols-2 gap-4 overflow-y-auto" novalidate onsubmit="event.preventDefault(); saveEntry();">
                <input type="hidden" id="entry-id">

                <div id="gs-draft-note" class="hidden sm:col-span-2 flex items-center justify-between gap-2 text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-3 py-2">
                    <span><i class="fa-solid fa-clock-rotate-left mr-1"></i> Restored your unsaved draft.</span>
                    <button type="button" onclick="discardDraft()" class="font-semibold hover:underline">Start fresh</button>
                </div>

                <div id="date-field-wrap">
                    <label class="gs-label">Payment Date <span class="text-red-500">*</span></label>
                    <div id="entry-payment-date-view" class="w-full border border-gray-100 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 select-none"></div>
                    <input type="hidden" id="entry-payment-date">
                    <p class="text-[10px] text-gray-400 mt-1"><i class="fa-solid fa-lock mr-1"></i>Set automatically</p>
                </div>

                <div>
                    <label class="gs-label" for="entry-department">Department</label>
                    <select id="entry-department" class="gs-input">
                        <option value="">— Select —</option>
                    </select>
                </div>

                <!-- Reference mode -->
                <div class="sm:col-span-2">
                    <label class="gs-label">Reference <span class="normal-case font-normal text-gray-400 tracking-normal">— which number will be used as the reference</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" id="mode-cr" onclick="setRefMode('collection')"
                            class="gs-mode flex items-center justify-center gap-2 text-xs font-semibold py-2.5 px-3 rounded-lg transition">
                            <i class="fa-solid fa-receipt"></i> Collection Receipt
                        </button>
                        <button type="button" id="mode-dep" onclick="setRefMode('deposit')"
                            class="gs-mode flex items-center justify-center gap-2 text-xs font-semibold py-2.5 px-3 rounded-lg transition">
                            <i class="fa-solid fa-building-columns"></i> Deposit Reference
                        </button>
                    </div>
                </div>

                <div class="gs-field-ref" id="wrap-cr">
                    <label class="gs-label" for="entry-collection-receipt-no">
                        Collection Receipt No.
                        <span id="badge-cr" class="normal-case tracking-normal text-[10px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded ml-1">Reference</span>
                    </label>
                    <input type="text" id="entry-collection-receipt-no" placeholder="e.g. CR-0001" class="gs-input font-mono" autocomplete="off">
                </div>

                <div class="gs-field-ref" id="wrap-dep">
                    <label class="gs-label" for="entry-deposit-reference-no">
                        Deposit Reference No.
                        <span id="badge-dep" class="hidden normal-case tracking-normal text-[10px] bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded ml-1">Reference</span>
                    </label>
                    <input type="text" id="entry-deposit-reference-no" placeholder="e.g. DEP-0001" class="gs-input font-mono" autocomplete="off">
                </div>

                <div class="sm:col-span-2">
                    <label class="gs-label" for="entry-particulars">Particulars <span class="text-red-500">*</span></label>
                    <input type="text" id="entry-particulars" placeholder="e.g. Payment for services" class="gs-input">
                    <p id="err-particulars" class="hidden text-[11px] text-red-600 mt-1"></p>
                </div>

                <div>
                    <label class="gs-label" for="entry-payee">Payee</label>
                    <input type="text" id="entry-payee" placeholder="e.g. Noble Corp" class="gs-input">
                </div>

                <div>
                    <label class="gs-label" for="entry-payor">Payor</label>
                    <input type="text" id="entry-payor" placeholder="e.g. Juan Dela Cruz" class="gs-input">
                </div>

                <div>
                    <label class="gs-label" for="entry-type-of-sale">Type of Sale</label>
                    <input type="text" id="entry-type-of-sale" placeholder="e.g. Cash / Credit" class="gs-input" list="gs-sale-types">
                    <datalist id="gs-sale-types"><option value="Cash"><option value="Credit"><option value="Check"><option value="Bank Transfer"></datalist>
                </div>

                <div>
                    <label class="gs-label" for="entry-sales-person">Sales Person</label>
                    <input type="text" id="entry-sales-person" placeholder="e.g. Maria Santos" class="gs-input">
                </div>

                <div class="sm:col-span-2">
                    <label class="gs-label" for="entry-amount">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                        <input type="number" id="entry-amount" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="gs-input font-mono" style="padding-left:1.75rem">
                    </div>
                    <p id="err-amount" class="hidden text-[11px] text-red-600 mt-1"></p>
                </div>

                <div id="form-error" class="hidden sm:col-span-2 text-xs bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2"></div>
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
        let allEntries = [];
        let deleteTargetId = null;
        let editMode = false;
        let currentRefMode = 'collection';
        let isSaving = false;
        let fetchSeq = 0;

        const BASE_URL_JS = '<?= BASE_URL ?>';
        const DRAFT_KEY = 'gs-add-draft';
        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const fmtMoney = v => '₱ ' + (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

        // ─── Month navigation ────────────────────────────────
        function changeMonth(step) {
            const inp = document.getElementById('filter-month');
            let [y, m] = (inp.value || todayPH().slice(0, 7)).split('-').map(Number);
            m += step;
            if (m < 1) { m = 12; y--; } else if (m > 12) { m = 1; y++; }
            inp.value = y + '-' + String(m).padStart(2, '0');
            fetchEntries();
        }
        function goThisMonth() {
            document.getElementById('filter-month').value = todayPH().slice(0, 7);
            fetchEntries();
        }

        // ─── Reference mode ──────────────────────────────────
        function setRefMode(mode) {
            currentRefMode = mode === 'deposit' ? 'deposit' : 'collection';
            const isCr = currentRefMode === 'collection';
            document.getElementById('mode-cr').dataset.active = isCr ? '1' : '0';
            document.getElementById('mode-dep').dataset.active = isCr ? '0' : '1';
            document.getElementById('badge-cr').classList.toggle('hidden', !isCr);
            document.getElementById('badge-dep').classList.toggle('hidden', isCr);
            document.getElementById('wrap-cr').dataset.ref = isCr ? '1' : '0';
            document.getElementById('wrap-dep').dataset.ref = isCr ? '0' : '1';
            saveDraft();
        }

        // ─── Departments ─────────────────────────────────────
        let deptCache = null;
        function loadDepartments(selectedValue = '') {
            const fill = list => {
                const sel = document.getElementById('entry-department');
                const names = list.map(d => d.name);
                if (selectedValue && !names.includes(selectedValue)) names.push(selectedValue); // lumang value
                sel.innerHTML = '<option value="">— Select —</option>' +
                    names.map(n => `<option value="${escHtml(n)}" ${n === selectedValue ? 'selected' : ''}>${escHtml(n)}</option>`).join('');
            };
            if (deptCache) { fill(deptCache); return Promise.resolve(); }
            return fetch(`${BASE_URL_JS}/fetchnoblepettycashdepartment`)
                .then(r => r.json())
                .then(data => { deptCache = Array.isArray(data) ? data : []; fill(deptCache); })
                .catch(() => fill([]));
        }

        // ─── Fetch + render ──────────────────────────────────
        function fetchEntries() {
            const month = document.getElementById('filter-month').value;
            const seq = ++fetchSeq;
            try { history.replaceState(null, '', '?month=' + encodeURIComponent(month)); } catch (e) { }
            document.getElementById('sheet-tbody').innerHTML =
                '<div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>';
            return fetch(`${BASE_URL_JS}/fetchnoblegeneralsheet?month=${encodeURIComponent(month)}`)
                .then(readJson)
                .then(data => {
                    if (seq !== fetchSeq) return; // may mas bagong request na
                    allEntries = Array.isArray(data) ? data : [];
                    buildDeptFilter();
                    applyFilters();
                })
                .catch(err => {
                    console.error(err);
                    if (seq !== fetchSeq) return;
                    document.getElementById('sheet-tbody').innerHTML = emptyHtml('Could not load entries.',
                        err && err.server ? err.message : 'Check your connection, then try again.');
                });
        }

        function buildDeptFilter() {
            const sel = document.getElementById('gs-dept-filter');
            const cur = sel.value;
            const depts = [...new Set(allEntries.map(r => r.department).filter(Boolean))].sort();
            sel.innerHTML = '<option value="">All departments</option>' +
                depts.map(d => `<option value="${escHtml(d)}" ${d === cur ? 'selected' : ''}>${escHtml(d)}</option>`).join('');
        }

        function applyFilters() {
            const q = document.getElementById('gs-search').value.trim().toLowerCase();
            const dept = document.getElementById('gs-dept-filter').value;
            const shown = allEntries.filter(r =>
                (!dept || r.department === dept) &&
                (!q || [r.payee, r.payor, r.particulars, r.collection_receipt_no, r.deposit_reference_no,
                    r.type_of_sale, r.sales_person, r.department, r.inserted_by]
                    .some(v => v && String(v).toLowerCase().includes(q))));
            renderTable(shown, q || dept);
            renderSummary(shown);
        }

        function emptyHtml(title, sub = '') {
            return `<div class="px-4 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid fa-table"></i></div>
                <p class="text-sm font-semibold text-gray-600">${escHtml(title)}</p>
                ${sub ? `<p class="text-xs text-gray-400 mt-1">${escHtml(sub)}</p>` : ''}
            </div>`;
        }

        function renderTable(data, filtered) {
            const box = document.getElementById('sheet-tbody');
            const month = monthLabel(document.getElementById('filter-month').value);
            document.getElementById('gs-info').innerHTML =
                `Showing <b class="text-gray-700">${data.length}</b> of ${allEntries.length} entr${allEntries.length === 1 ? 'y' : 'ies'} · <b class="text-gray-700">${escHtml(month)}</b>`;

            if (!data.length) {
                box.innerHTML = filtered
                    ? emptyHtml('No matching entries.', 'Try another search or department.')
                    : emptyHtml(`No entries for ${month}.`, 'Click "Add Entry" to record a collection or payment.');
                return;
            }

            box.innerHTML = data.map((row, i) => {
                const id = Number(row.id);
                const isDep = row.reference_mode === 'deposit';
                const refChip = (label, val, active) => val ? `
                    <span class="notranslate inline-flex items-center gap-1 font-mono text-[11px] rounded px-1.5 py-0.5 border
                        ${active ? 'font-bold text-amber-800 bg-amber-50 border-amber-200' : 'text-gray-500 bg-gray-50 border-gray-200'}"
                        title="${label}${active ? ' (reference)' : ''}">
                        <span class="font-sans font-semibold text-[9px] uppercase">${label}</span> ${escHtml(val)}</span>` : '';
                const fromTo = [row.payor, row.payee].filter(Boolean);
                return `
                <div data-id="${id}" class="gs-row flex items-stretch gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="w-7 shrink-0 pt-0.5 text-right text-[11px] font-mono text-gray-400">${i + 1}</div>
                    <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[11px] font-semibold text-gray-600 whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>${escHtml(fmtDay(row.payment_date))}</span>
                                ${refChip('CR', row.collection_receipt_no, !isDep)}
                                ${refChip('DEP', row.deposit_reference_no, isDep)}
                                ${row.type_of_sale ? `<span class="text-[10px] font-semibold text-gray-600 bg-gray-100 rounded-full px-2 py-0.5">${escHtml(row.type_of_sale)}</span>` : ''}
                            </div>
                            <p class="mt-1 text-sm font-semibold text-gray-800 break-words">${escHtml(row.particulars || '—')}</p>
                            ${fromTo.length ? `<p class="text-[12px] text-gray-600 truncate">
                                ${row.payor ? `<span class="text-gray-400">From</span> ${escHtml(row.payor)}` : ''}
                                ${row.payor && row.payee ? '<span class="text-gray-300 mx-1">→</span>' : ''}
                                ${row.payee ? `<span class="text-gray-400">To</span> ${escHtml(row.payee)}` : ''}</p>` : ''}
                            <div class="flex items-center gap-x-3 gap-y-0.5 mt-1 flex-wrap text-[11px] text-gray-500">
                                ${row.department ? `<span class="truncate"><i class="fa-solid fa-sitemap text-[9px] text-gray-400 mr-1"></i>${escHtml(row.department)}</span>` : ''}
                                ${row.sales_person ? `<span class="truncate"><i class="fa-solid fa-user-tie text-[9px] text-gray-400 mr-1"></i>${escHtml(row.sales_person)}</span>` : ''}
                                ${row.inserted_by ? `<span class="truncate text-gray-400">Added by ${escHtml(row.inserted_by)}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0">
                            <p class="font-mono text-sm font-bold text-green-700 lg:min-w-[120px] lg:text-right">${row.amount !== null && row.amount !== '' ? fmtMoney(row.amount) : '—'}</p>
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

        function renderSummary(data) {
            let total = 0, cr = 0, dep = 0, crN = 0, depN = 0;
            data.forEach(r => {
                const a = parseFloat(r.amount || 0);
                total += a;
                if (r.reference_mode === 'deposit') { dep += a; depN++; } else { cr += a; crN++; }
            });
            document.getElementById('card-total-entries').textContent = data.length;
            document.getElementById('card-total-amount').textContent = fmtMoney(total);
            document.getElementById('card-cr-amount').textContent = fmtMoney(cr);
            document.getElementById('card-cr-count').textContent = crN + ' entr' + (crN === 1 ? 'y' : 'ies');
            document.getElementById('card-dep-amount').textContent = fmtMoney(dep);
            document.getElementById('card-dep-count').textContent = depN + ' entr' + (depN === 1 ? 'y' : 'ies');
            document.getElementById('foot-amount').textContent = fmtMoney(total);
            document.getElementById('foot-label').textContent = data.length !== allEntries.length ? '(filtered)' : '';
        }

        // ─── Form helpers ────────────────────────────────────
        const FIELDS = ['entry-collection-receipt-no', 'entry-deposit-reference-no', 'entry-type-of-sale', 'entry-payee',
            'entry-payor', 'entry-particulars', 'entry-sales-person', 'entry-amount', 'entry-department'];

        function setPaymentDate(val) {
            document.getElementById('entry-payment-date').value = val || '';
            document.getElementById('entry-payment-date-view').textContent = val ? fmtDay(val) : '—';
        }

        function clearErrors() {
            ['particulars', 'amount'].forEach(k => {
                document.getElementById('err-' + k).classList.add('hidden');
                document.getElementById('entry-' + k).removeAttribute('aria-invalid');
            });
            document.getElementById('form-error').classList.add('hidden');
        }
        function fieldError(key, msg) {
            const el = document.getElementById('err-' + key);
            el.textContent = msg; el.classList.remove('hidden');
            document.getElementById('entry-' + key).setAttribute('aria-invalid', 'true');
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

        // ─── Draft (Add lang) ────────────────────────────────
        function saveDraft() {
            if (editMode || document.getElementById('entry-modal').classList.contains('hidden')) return;
            const d = { mode: currentRefMode };
            FIELDS.forEach(f => d[f] = document.getElementById(f).value);
            const hasText = FIELDS.some(f => f !== 'entry-department' && String(d[f]).trim() !== '');
            try { hasText ? localStorage.setItem(DRAFT_KEY, JSON.stringify(d)) : localStorage.removeItem(DRAFT_KEY); } catch (e) { }
        }
        function readDraft() {
            try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return null; }
        }
        function clearDraft() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) { } }
        function discardDraft() {
            clearDraft();
            FIELDS.forEach(f => { if (f !== 'entry-department') document.getElementById(f).value = ''; });
            document.getElementById('entry-department').value = '';
            setRefMode('collection');
            document.getElementById('gs-draft-note').classList.add('hidden');
        }
        FIELDS.forEach(f => {
            const el = document.getElementById(f);
            el.addEventListener('input', saveDraft);
            el.addEventListener('change', saveDraft);
        });

        // ─── Add / Edit ──────────────────────────────────────
        function openModal() {
            document.getElementById('entry-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('entry-particulars').focus(), 50);
        }

        function openAddModal() {
            editMode = false;
            clearErrors();
            setModalHeader(false);
            document.getElementById('entry-id').value = '';
            setPaymentDate(todayPH());
            FIELDS.forEach(f => { if (f !== 'entry-department') document.getElementById(f).value = ''; });

            const draft = readDraft();
            document.getElementById('gs-draft-note').classList.toggle('hidden', !draft);
            if (draft) FIELDS.forEach(f => { if (f !== 'entry-department' && draft[f] !== undefined) document.getElementById(f).value = draft[f]; });
            openModal();
            setRefMode(draft?.mode || 'collection');
            loadDepartments(draft?.['entry-department'] || '');
        }

        function editEntry(id) {
            const row = allEntries.find(r => r.id == id);
            if (!row) return;
            editMode = true;
            clearErrors();
            setModalHeader(true);
            document.getElementById('gs-draft-note').classList.add('hidden');
            document.getElementById('entry-id').value = row.id;
            setPaymentDate(row.payment_date ?? '');

            document.getElementById('entry-collection-receipt-no').value = row.collection_receipt_no ?? '';
            document.getElementById('entry-deposit-reference-no').value = row.deposit_reference_no ?? '';
            document.getElementById('entry-type-of-sale').value = row.type_of_sale ?? '';
            document.getElementById('entry-payee').value = row.payee ?? '';
            document.getElementById('entry-payor').value = row.payor ?? '';
            document.getElementById('entry-particulars').value = row.particulars ?? '';
            document.getElementById('entry-sales-person').value = row.sales_person ?? '';
            document.getElementById('entry-amount').value = row.amount ?? '';
            setRefMode(row.reference_mode ?? 'collection');
            loadDepartments(row.department ?? '');
            openModal();
        }

        function closeModal() {
            if (isSaving) return;
            document.getElementById('entry-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        function setSaving(on) {
            isSaving = on;
            const btn = document.getElementById('save-btn');
            btn.disabled = on;
            btn.querySelector('i').className = on ? 'fa-solid fa-spinner fa-spin text-xs' : 'fa-solid fa-floppy-disk text-xs';
            btn.querySelector('span').textContent = on ? 'Saving…' : (editMode ? 'Save Changes' : 'Save Entry');
        }

        function saveEntry() {
            if (isSaving) return;
            clearErrors();
            const payment_date = document.getElementById('entry-payment-date').value;
            const particulars = document.getElementById('entry-particulars').value.trim();
            const amountRaw = document.getElementById('entry-amount').value.trim();
            const amount = amountRaw === '' ? 0 : Number(amountRaw);

            let ok = true;
            if (!particulars) { fieldError('particulars', 'Particulars is required.'); ok = false; }
            if (!Number.isFinite(amount) || amount < 0) { fieldError('amount', 'Enter a valid amount (0 or more).'); ok = false; }
            if (!payment_date) { formError('Payment date is missing. Close and open the form again.'); ok = false; }
            if (!ok) {
                (document.querySelector('#entry-form [aria-invalid="true"]') || document.getElementById('form-error')).focus?.();
                return;
            }

            const crNo = document.getElementById('entry-collection-receipt-no').value.trim();
            const depNo = document.getElementById('entry-deposit-reference-no').value.trim();
            const payload = {
                id: document.getElementById('entry-id').value || null,
                payment_date,
                collection_receipt_no: crNo,
                deposit_reference_no: depNo,
                reference_mode: currentRefMode,
                reference: currentRefMode === 'collection' ? crNo : depNo,
                department: document.getElementById('entry-department').value.trim(),
                type_of_sale: document.getElementById('entry-type-of-sale').value.trim(),
                payee: document.getElementById('entry-payee').value.trim(),
                payor: document.getElementById('entry-payor').value.trim(),
                particulars,
                sales_person: document.getElementById('entry-sales-person').value.trim(),
                amount: amountRaw === '' ? 0 : amountRaw,
            };

            setSaving(true);
            fetch(`${BASE_URL_JS}/savenoblegeneralsheet`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(readJson)
                .then(data => {
                    if (data && data.success) {
                        const wasEdit = editMode;
                        if (!wasEdit) clearDraft();
                        setSaving(false);
                        closeModal();
                        fetchEntries();
                        showToast(wasEdit ? 'Entry updated!' : 'Entry added!');
                    } else {
                        setSaving(false);
                        formError(data.message ?? 'Something went wrong. Please try again.');
                    }
                })
                .catch(err => {
                    setSaving(false);
                    if (err && err.server) {
                        formError(err.page ? err.message : err.message + ' — Check the list after closing; the entry may or may not have been saved.');
                        fetchEntries(); // i-refresh para makita kung na-save pa rin
                    } else {
                        formError('Could not reach the server. Your entry was not saved — check your connection and try again.');
                    }
                });
        }

        // Basahin ang sagot ng server nang ligtas: kung hindi JSON (hal. PHP warning/error),
        // ipakita kung ano talaga ang ibinalik para madaling ma-debug.
        async function readJson(res) {
            const text = await res.text();
            try {
                return JSON.parse(text.replace(/^\uFEFF/, '').trim());
            } catch (e) {
                console.error('Server response (not JSON):', res.status, res.url, text);
                // Buong HTML page ang bumalik (hal. dashboard/login) → hindi nakita ng router ang route
                if (/<!doctype html|<html[\s>]/i.test(text)) {
                    const route = (() => { try { return new URL(res.url).pathname.split('/').pop(); } catch (e) { return res.url; } })();
                    const title = (text.match(/<title>([^<]*)<\/title>/i) || [])[1] || 'a web page';
                    const err = new Error(`The server returned the page "${title.trim()}" instead of data for "${route}". ` +
                        `This route is probably missing or misnamed in the router, or your session expired (try reloading the page).`);
                    err.server = true; err.page = true;
                    throw err;
                }
                const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                const err = new Error(`Server error${res.status ? ' (HTTP ' + res.status + ')' : ''}: ` +
                    (plain ? plain.slice(0, 180) + (plain.length > 180 ? '…' : '') : 'empty response'));
                err.server = true;
                throw err;
            }
        }

        // ─── Delete ──────────────────────────────────────────
        function openDeleteModal(id) {
            const row = allEntries.find(r => r.id == id);
            deleteTargetId = id;
            document.getElementById('delete-summary').innerHTML = row
                ? `<b>${escHtml(row.particulars || '—')}</b><br>${escHtml(fmtDay(row.payment_date))} · ${escHtml(fmtMoney(row.amount))}`
                : '';
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
            const showErr = msg => {
                const el = document.getElementById('delete-error');
                el.textContent = msg; el.classList.remove('hidden');
            };
            fetch(`${BASE_URL_JS}/deletenoblegeneralsheet`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteTargetId })
            })
                .then(readJson)
                .then(data => {
                    btn.disabled = false; btn.textContent = 'Delete';
                    if (data && data.success) {
                        closeDeleteModal();
                        fetchEntries();
                        showToast('Entry deleted.', 'delete');
                    } else showErr(data.message ?? 'Could not delete. Please try again.');
                })
                .catch(err => {
                    btn.disabled = false; btn.textContent = 'Delete';
                    showErr(err && err.server ? err.message : 'Could not reach the server. Please try again.');
                    if (err && err.server) fetchEntries();
                });
        }

        function showToast(message, type = 'success') {
            const existing = document.getElementById('ngs-toast');
            if (existing) existing.remove();
            const color = type === 'delete' ? 'bg-red-500' : 'bg-green-600';
            const icon = type === 'delete' ? 'fa-trash' : 'fa-circle-check';
            const toast = document.createElement('div');
            toast.id = 'ngs-toast';
            toast.className = `fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[999] flex items-center gap-3 ${color} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0`;
            toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${escHtml(message)}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
            setTimeout(() => {
                toast.classList.replace('opacity-100', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Isara ang modal: background click o Esc
        ['entry-modal', 'delete-modal'].forEach(mid => {
            document.getElementById(mid).addEventListener('click', e => {
                if (e.target.id === mid) mid === 'entry-modal' ? closeModal() : closeDeleteModal();
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (!document.getElementById('delete-modal').classList.contains('hidden')) closeDeleteModal();
            else if (!document.getElementById('entry-modal').classList.contains('hidden')) closeModal();
        });

        // ─── Init ────────────────────────────────────────────
        const monthParam = new URLSearchParams(window.location.search).get('month');
        if (monthParam && /^\d{4}-\d{2}$/.test(monthParam)) document.getElementById('filter-month').value = monthParam;

        document.getElementById('filter-month').addEventListener('change', fetchEntries);
        document.getElementById('gs-search').addEventListener('input', applyFilters);
        document.getElementById('gs-dept-filter').addEventListener('change', applyFilters);
        fetchEntries();
    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'gs-picked';
            const SEL = '#sheet-tbody .gs-row';
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