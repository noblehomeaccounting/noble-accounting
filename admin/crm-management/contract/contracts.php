<?php
// contracts.php — Contract Agreement (route: crmcontracts)
//   Accounting Head → gagawa + pipirma · Sales/Designer → pipirma · Sales → ibibigay ang link sa client
//   Client → pipirma sa link (e-sign + ID) · Superadmin (GM) → huling pirma

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_SALES, ROLE_DESIGNER, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$nhcRole = $_SESSION['role'] ?? '';
$nhcMe = intval($_SESSION['account_id'] ?? 0);
$nhcViewer = '';
if ($nhcRole === ROLE_SUPERADMIN) {
    $nhcViewer = 'superadmin';
} elseif ($nhcRole === ROLE_SALES) {
    $nhcViewer = 'sales';
} elseif ($nhcRole === ROLE_DESIGNER) {
    $nhcViewer = 'designer';
} elseif ($nhcRole === ROLE_ACCOUNTING) {
    $st = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
    $r = ROLE_ACCOUNTING;
    $p = POSITION_HEAD;
    $st->bind_param("iss", $nhcMe, $r, $p);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) $nhcViewer = 'acct_head';
    $st->close();
}
if ($nhcViewer === '') {
    http_response_code(403);
    exit('Access denied. Contracts are for the Accounting Head, Sales, Designer and Superadmin only.');
}

$nhcIntro = [
    'acct_head'  => ['Accounting Head', 'Prepare the contract once the Final is approved, then sign it to send it to Sales and Designer.'],
    'sales'      => ['Sales', 'Sign the contracts prepared by the Accounting Head, then send the signing link to your client.'],
    'designer'   => ['Designer', 'Sign the contracts of your projects prepared by the Accounting Head.'],
    'superadmin' => ['General Manager', 'Give the final signature after the client has signed.'],
][$nhcViewer];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>Contracts</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php if ($nhcViewer === 'acct_head'): ?>
        <!-- Editor ng laman ng contract (Accounting Head lang) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
    <?php endif; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .nhc-hazard { background: repeating-linear-gradient(-45deg, #b45309 0 10px, #1f2937 10px 20px); }
        .nhc-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nhc-field { width: 100%; height: 38px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0 10px; font-size: 13px; background: #fff; outline: none; }
        .nhc-field:focus { border-color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
        @media (max-width: 639px) { #nhcEditorWrap .ql-editor { padding: 14px; min-height: 320px; } }
        .nhc-label { display: block; font-size: 11.5px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        /* contract editor */
        #nhcEditorWrap .ql-toolbar.ql-snow { border: 0; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; background: #fff; z-index: 2; flex-wrap: wrap; }
        #nhcEditorWrap .ql-container.ql-snow { border: 0; font-family: Cambria, Georgia, "Times New Roman", serif; font-size: 14px; }
        #nhcEditorWrap .ql-editor { min-height: 420px; line-height: 1.55; padding: 18px 20px; }
        #nhcEditorWrap .ql-editor p { text-align: justify; margin-bottom: 6px; }
        #nhcEditorWrap .ql-editor h2 { font-size: 18px; margin: 4px 0 12px; }
        .nhc-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .nhc-chip:hover { background: #fef3c7; }
        .nhc-tab[data-active="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen px-3 pt-20 pb-6 sm:px-4 md:p-8 overflow-x-hidden">
        <div class="max-w-6xl mx-auto">

            <section class="relative z-20 bg-white border border-gray-200 rounded-xl mb-4">
                <div class="nhc-hazard h-1.5 rounded-t-xl"></div>
                <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-signature text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM Management · <?= htmlspecialchars($nhcIntro[0]) ?></p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Contract Agreement</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5 max-w-xl"><?= htmlspecialchars($nhcIntro[1]) ?></p>
                        </div>
                    </div>
                    <div id="nhcTabs" class="grid grid-cols-4 gap-1.5 sm:gap-2 w-full lg:w-auto">
                        <?php foreach ([['todo', 'Your turn', 'text-amber-700'], ['progress', 'In progress', 'text-blue-700'], ['done', 'Completed', 'text-emerald-700'], ['', 'All', 'text-gray-900']] as [$v, $l, $cls]): ?>
                            <button type="button" data-tab="<?= $v ?>" class="nhc-tab min-w-0 text-left rounded-xl border border-gray-200 bg-white px-2 py-1.5 sm:px-3.5 sm:py-2 lg:min-w-[110px] transition-colors">
                                <span class="block truncate text-[9px] sm:text-[10px] font-semibold uppercase sm:tracking-wide text-gray-500"><?= $l ?></span>
                                <span class="nhc-tab-count block text-lg sm:text-xl font-bold leading-tight tabular-nums <?= $cls ?>">0</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Search · Date · Status · Sort (iisang hilera) -->
                <div class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-xl grid grid-cols-[minmax(0,1fr)_auto] sm:flex sm:flex-wrap items-center gap-2">
                    <div class="relative sm:flex-1 sm:min-w-[200px] sm:max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input id="nhcSearch" type="text" placeholder="Search control no. or client…" class="nhc-field pl-8 text-xs" style="padding-left:2rem">
                    </div>
                    <button type="button" data-fd-open="nhcFd" class="sm:hidden shrink-0 inline-flex items-center gap-1.5 h-[38px] px-3 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-500 "><i class="fa-solid fa-sliders"></i> Filters <span data-fd-badge class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-amber-700 text-white text-[10px] font-bold inline-flex items-center justify-center">0</span></button>

                    <div id="nhcFdBackdrop" class="nh-fd-backdrop"></div>
<div id="nhcFd" class="nh-fd" role="dialog" aria-label="Filters">
    <div class="nh-fd-only flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2"><i class="fa-solid fa-sliders text-amber-600"></i><h2 class="text-gray-900 text-sm font-semibold">Filters</h2></div>
        <button type="button" data-fd-close aria-label="Close" class="-mr-2 w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>
    <div class="nh-fd-body">

                    <!-- Date: isang button, bukas ang panel kapag pinindot (sa phone: laging bukas sa loob ng drawer) -->
                    <div class="relative min-w-0" id="nhcDateWrap">
                        <button type="button" id="nhcDateBtn" aria-haspopup="true" aria-expanded="false"
                            class="w-full sm:w-auto h-[38px] sm:h-9 inline-flex items-center gap-2 px-3 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-gray-300">
                            <i class="fa-regular fa-calendar text-gray-400"></i>
                            <span id="nhcDateBtnText" class="flex-1 sm:flex-none min-w-0 truncate text-left">Any date</span>
                            <span id="nhcDateBtnX" class="hidden w-4 h-4 -mr-1 rounded-full bg-amber-700 text-white text-[9px] leading-4 text-center" title="Clear date">&times;</span>
                            <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
                        </button>
                        <div id="nhcDatePop" class="hidden absolute left-0 top-full mt-1.5 z-30 w-[320px] max-w-[calc(100vw-3.5rem)] bg-white border border-gray-200 rounded-xl shadow-lg p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Last update</p>
                            <div class="grid grid-cols-3 gap-1.5 mb-3">
                                <button type="button" data-nhc-q="today" class="nhc-q h-8 text-[11px] font-semibold bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">Today</button>
                                <button type="button" data-nhc-q="week" class="nhc-q h-8 text-[11px] font-semibold bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">This week</button>
                                <button type="button" data-nhc-q="month" class="nhc-q h-8 text-[11px] font-semibold bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">This month</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <label class="block"><span class="block text-[10px] font-semibold text-gray-500 mb-0.5">Month</span><input id="nhcFMonth" type="month" class="nhc-field text-xs"></label>
                                <label class="block"><span class="block text-[10px] font-semibold text-gray-500 mb-0.5">Day</span><input id="nhcFDay" type="date" class="nhc-field text-xs"></label>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block"><span class="block text-[10px] font-semibold text-gray-500 mb-0.5">From</span><input id="nhcFFrom" type="date" class="nhc-field text-xs"></label>
                                <label class="block"><span class="block text-[10px] font-semibold text-gray-500 mb-0.5">To</span><input id="nhcFTo" type="date" class="nhc-field text-xs"></label>
                            </div>
                            <p id="nhcFHint" class="text-[10.5px] text-gray-400 mt-2.5 leading-snug empty:hidden"></p>
                            <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-gray-100">
                                <button type="button" id="nhcFClear" class="text-[11px] font-semibold text-gray-500 hover:text-amber-700">Clear date</button>
                                <button type="button" id="nhcDateDone" class="px-3 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800">Done</button>
                            </div>
                        </div>
                    </div>

<p class="nh-fd-only nh-fd-label">Status</p>
                    <select id="nhcFStatus" class="w-full sm:w-auto min-w-0 h-[38px] sm:h-9 pl-3 pr-8 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 hover:border-gray-300" title="Status">
                        <option value="">All statuses</option>
                        <option value="Not created">Not created</option>
                        <option value="Draft">Draft</option>
                        <option value="returned">Returned for correction</option>
                        <option value="For Staff Signing">For Staff Signing</option>
                        <option value="For Client Signing">For Client Signing</option>
                        <option value="For GM Signing">For GM Signing</option>
                        <option value="Completed">Completed</option>
                    </select>
<p class="nh-fd-only nh-fd-label">Sort by</p>
                    <select id="nhcFSort" data-fd-ignore class="w-full sm:w-auto min-w-0 h-[38px] sm:h-9 pl-3 pr-8 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 hover:border-gray-300" title="Sort by">
                        <option value="updated">Sort: Recently updated</option>
                        <option value="oldest">Sort: Oldest update</option>
                        <option value="amount_desc">Sort: Amount high → low</option>
                        <option value="amount_asc">Sort: Amount low → high</option>
                        <option value="client">Sort: Client A–Z</option>
                        <option value="control">Sort: Control no.</option>
                    </select>

    </div>
    <div class="nh-fd-only px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] border-t border-gray-100 bg-gray-50/60">
        <button type="button" data-fd-close class="w-full text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg transition-colors">Show results</button>
    </div>
</div>
                    <span id="nhcCount" class="col-span-2 sm:ml-auto text-[11px] text-gray-400 truncate"></span>
                </div>
            </section>

            <div id="nhcList" class="space-y-2.5"></div>
        </div>

        <!-- Panel -->
        <div id="nhcOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="nhcClose()"></div>
        <div id="nhcPanel" class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 flex flex-col translate-x-full transition-transform duration-300 ease-out">
            <div class="nhc-hazard h-1.5 shrink-0"></div>
            <div class="px-4 sm:px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase">Contract Agreement</p>
                    <h3 id="nhcPControl" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
                    <p id="nhcPClient" class="text-[15px] font-semibold text-gray-900 truncate"></p>
                    <div id="nhcPBadges" class="mt-1 flex flex-wrap gap-1.5"></div>
                </div>
                <button type="button" onclick="nhcClose()" aria-label="Close" class="inline-flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="nhcPBody" class="nhc-scroll px-4 sm:px-5 py-4 overflow-y-auto flex-1 bg-slate-50/60"></div>
            <div id="nhcPFoot" class="px-4 sm:px-5 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] sm:pb-3.5 bg-white border-t border-gray-100 shrink-0"></div>
        </div>

        <div id="crmToastContainer" class="fixed left-4 right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] sm:left-auto sm:right-6 sm:bottom-6 z-[9999] flex flex-col gap-2.5 pointer-events-none sm:w-full sm:max-w-sm"></div>
    </main>

    <script>
        const NHC_AJAX = <?= json_encode(BASE_URL . '/crmcontractsajax') ?>;
        const NHC_PDF = <?= json_encode(BASE_URL . '/crmcontractpdf') ?>;
        const NHC_VIEWER = <?= json_encode($nhcViewer) ?>;

        let nhcRows = [];
        let nhcTab = 'todo';
        let nhcSearchTerm = '';
        let nhcCurrent = null;   // inquiry_id ng bukas na panel
        let nhcData = null;      // detail ng bukas na panel
        let nhcTimer = null;
        let nhcSig = '';

        // ═══ Filters: petsa ng huling update · status · sort ═══
        let nhcF = { day: '', month: '', week: '', from: '', to: '' }, nhcFStatus = '', nhcSortBy = 'updated';
        const NHC_WARN_DAYS = 3;   // "Your turn" na 3+ araw na → pula
        const nhcPad = n => String(n).padStart(2, '0');
        const nhcYmd = d => `${d.getFullYear()}-${nhcPad(d.getMonth() + 1)}-${nhcPad(d.getDate())}`;
        const nhcDt = v => { if (!v) return null; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? null : d; };
        const nhcAgeSec = v => { const d = nhcDt(v); return d ? Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000)) : null; };
        function nhcAgo(v) {
            const s = nhcAgeSec(v);
            if (s === null) return '';
            if (s < 60) return 'just now';
            if (s < 3600) return `${Math.floor(s / 60)}m ago`;
            if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
            const d = Math.floor(s / 86400);
            if (d === 1) return 'yesterday';
            if (d < 30) return `${d} days ago`;
            const m = Math.floor(d / 30);
            return m < 12 ? `${m} month${m === 1 ? '' : 's'} ago` : `${Math.floor(d / 365)}y ago`;
        }
        function nhcAgoHtml(r) {
            const s = nhcAgeSec(r.updated_at);
            if (s === null) return r.status === 'Not created' ? '<span class="text-gray-400">Final approved · contract not started</span>' : '';
            const ago = nhcAgo(r.updated_at);
            if (r.my_action !== 'none' && r.status !== 'Completed' && s >= NHC_WARN_DAYS * 86400)
                return `<span class="font-semibold text-red-600"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Updated ${ago} · still waiting</span>`;
            return `<span class="${s < 3600 ? 'font-semibold text-green-600' : 'text-gray-500'}">Updated ${ago}</span>`;
        }
        const nhcDateActive = () => !!(nhcF.day || nhcF.month || nhcF.week || nhcF.from || nhcF.to);
        function nhcDateOk(r) {
            if (!nhcDateActive()) return true;
            const d = String(r.updated_at || '').slice(0, 10);
            if (!d) return false;
            if (nhcF.from || nhcF.to) return (!nhcF.from || d >= nhcF.from) && (!nhcF.to || d <= nhcF.to);
            if (nhcF.day) return d === nhcF.day;
            if (nhcF.month) return d.slice(0, 7) === nhcF.month;
            const end = new Date(nhcF.week + 'T00:00:00'); end.setDate(end.getDate() + 6);
            return d >= nhcF.week && d <= nhcYmd(end);
        }
        const nhcStatusOk = r => !nhcFStatus || (nhcFStatus === 'returned' ? !!r.returned : r.status === nhcFStatus);
        function nhcDateLabel() {
            const f = v => new Date(v + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
            if (nhcF.from || nhcF.to) return nhcF.from && nhcF.to ? `from ${f(nhcF.from)} to ${f(nhcF.to)}` : nhcF.from ? `from ${f(nhcF.from)} onwards` : `up to ${f(nhcF.to)}`;
            if (nhcF.day) return 'on ' + f(nhcF.day);
            if (nhcF.month) return 'in ' + new Date(nhcF.month + '-01T00:00:00').toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
            return nhcF.week ? 'this week' : '';
        }
        function nhcSortRows(rows) {
            const ts = r => { const d = nhcDt(r.updated_at); return d ? d.getTime() : 0; };
            const by = {
                updated: (a, b) => ts(b) - ts(a) || b.inquiry_id - a.inquiry_id,
                oldest: (a, b) => (ts(a) || Infinity) - (ts(b) || Infinity) || a.inquiry_id - b.inquiry_id,
                amount_desc: (a, b) => (b.amount || 0) - (a.amount || 0),
                amount_asc: (a, b) => (a.amount || 0) - (b.amount || 0),
                client: (a, b) => String(a.client_name || '').localeCompare(String(b.client_name || '')),
                control: (a, b) => String(a.control_no || '').localeCompare(String(b.control_no || '')),
            };
            return rows.sort(by[nhcSortBy] || by.updated);
        }
        function nhcSyncFilters() {
            const g = id => document.getElementById(id);
            g('nhcFMonth').value = nhcF.month; g('nhcFDay').value = nhcF.day;
            g('nhcFFrom').value = nhcF.from; g('nhcFTo').value = nhcF.to;
            g('nhcFFrom').max = nhcF.to || ''; g('nhcFTo').min = nhcF.from || '';
            // label ng Date button
            const f = v => new Date(v + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
            const t0 = nhcYmd(new Date());
            g('nhcDateBtnText').textContent = !nhcDateActive() ? 'Any date'
                : nhcF.day ? (nhcF.day === t0 ? 'Today' : f(nhcF.day))
                : nhcF.week ? 'This week'
                : nhcF.month ? new Date(nhcF.month + '-01T00:00:00').toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })
                : nhcF.from && nhcF.to ? `${f(nhcF.from)} – ${f(nhcF.to)}` : nhcF.from ? `From ${f(nhcF.from)}` : `Until ${f(nhcF.to)}`;
            g('nhcDateBtnX').classList.toggle('hidden', !nhcDateActive());
            g('nhcDateBtn').classList.toggle('border-amber-600', nhcDateActive());
            g('nhcDateBtn').classList.toggle('bg-amber-50', nhcDateActive());
            const t = nhcYmd(new Date());
            document.querySelectorAll('.nhc-q').forEach(b => {
                const q = b.dataset.nhcQ;
                const on = (q === 'today' && nhcF.day === t) || (q === 'month' && nhcF.month === t.slice(0, 7)) || (q === 'week' && !!nhcF.week);
                ['bg-amber-700', 'text-white', 'border-amber-700'].forEach(c => b.classList.toggle(c, on));
                ['bg-white', 'text-gray-600', 'border-gray-200'].forEach(c => b.classList.toggle(c, !on));
            });
            g('nhcFHint').textContent = nhcDateActive() ? 'Contracts not yet started have no date, so they are hidden while a date is chosen.' : '';
        }
        function nhcSetDate(next) { nhcF = Object.assign({ day: '', month: '', week: '', from: '', to: '' }, next); nhcSyncFilters(); nhcRender(); }

        function nhcEsc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
        function nhcMoney(v) { const n = Number(v); return isNaN(n) ? '—' : '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function nhcDate(v) { if (!v) return ''; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? v : d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }); }

        function crmShowToast(message, type = 'success', duration = 4000) {
            const c = document.getElementById('crmToastContainer');
            const t = document.createElement('div');
            t.className = 'pointer-events-auto flex items-start gap-2.5 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-800 translate-x-6 opacity-0 transition-all duration-300';
            t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white ${type === 'success' ? 'bg-emerald-500' : 'bg-red-600'}">${type === 'success' ? '✓' : '!'}</span><span class="flex-1 leading-relaxed">${nhcEsc(message)}</span>`;
            c.appendChild(t);
            requestAnimationFrame(() => t.classList.remove('translate-x-6', 'opacity-0'));
            setTimeout(() => { t.classList.add('opacity-0'); setTimeout(() => t.remove(), 300); }, duration);
        }

        const NHC_STATUS = {
            'Not created': ['bg-gray-50 text-gray-600 border-gray-200', 'bg-gray-300'],
            'Draft': ['bg-gray-50 text-gray-700 border-gray-200', 'bg-gray-400'],
            'For Staff Signing': ['bg-amber-50 text-amber-800 border-amber-200', 'bg-amber-500'],
            'For Client Signing': ['bg-blue-50 text-blue-700 border-blue-200', 'bg-blue-500'],
            'For GM Signing': ['bg-violet-50 text-violet-700 border-violet-200', 'bg-violet-500'],
            'Completed': ['bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
        };
        function nhcBadge(status) {
            const [cls, dot] = NHC_STATUS[status] || NHC_STATUS['Draft'];
            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}"><span class="w-1.5 h-1.5 rounded-full ${dot}"></span>${nhcEsc(status)}</span>`;
        }
        const NHC_ACTION_LABEL = { create: 'Create', edit: 'Edit', sign: 'Sign', send_link: 'Send link', gm_sign: 'Final sign' };

        function nhcProgress(r) {
            if (!r.signed) return '';
            const steps = [['acct', 'Accounting'], ['sales', 'Sales'], ['designer', 'Designer'], ['client', 'Client'], ['gm', 'GM']];
            return `<div class="flex items-center gap-1 w-full sm:w-auto" title="Signatures"><span class="sm:hidden mr-1 text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Signatures</span>${steps.map(([k, l]) =>
                `<span title="${l}" class="w-6 h-6 rounded-full text-[9px] font-bold flex items-center justify-center ${r.signed[k] ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-400'}">${r.signed[k] ? '<i class="fa-solid fa-check"></i>' : l[0]}</span>`).join('')}</div>`;
        }

        function nhcFilter(rows) {
            return nhcSortRows(rows.filter(r => nhcDateOk(r) && nhcStatusOk(r)).filter(r => {
                if (nhcTab === 'todo') return r.my_action !== 'none';
                if (nhcTab === 'done') return r.status === 'Completed';
                if (nhcTab === 'progress') return r.status !== 'Completed' && r.my_action === 'none';
                return true;
            }));
        }

        function nhcRender() {
            const shown = nhcRows.filter(r => nhcDateOk(r) && nhcStatusOk(r));
            const counts = { todo: 0, progress: 0, done: 0, '': shown.length };
            shown.forEach(r => {
                if (r.my_action !== 'none') counts.todo++;
                if (r.status === 'Completed') counts.done++;
                else if (r.my_action === 'none') counts.progress++;
            });
            document.querySelectorAll('.nhc-tab').forEach(b => {
                b.dataset.active = b.dataset.tab === nhcTab ? '1' : '0';
                b.querySelector('.nhc-tab-count').textContent = counts[b.dataset.tab] ?? 0;
            });

            const rows = nhcFilter(nhcRows);
            document.getElementById('nhcCount').textContent = `${rows.length} contract${rows.length === 1 ? '' : 's'}` + (nhcDateActive() ? ` · updated ${nhcDateLabel()}` : '');
            const list = document.getElementById('nhcList');
            if (!rows.length) {
                list.innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-regular fa-folder-open"></i></div>
                    <p class="text-sm font-semibold text-gray-700">${(nhcDateActive() || nhcFStatus) ? 'No contracts match the filters.' : nhcTab === 'todo' ? 'Nothing for you right now.' : 'No contracts here.'}</p></div>`;
                return;
            }
            list.innerHTML = rows.map(r => {
                const act = r.my_action !== 'none';
                return `<article onclick="nhcOpen(${r.inquiry_id})" class="relative bg-white border rounded-xl overflow-hidden cursor-pointer transition ${nhcCurrent === r.inquiry_id ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'}">
                    <span class="absolute left-0 top-0 bottom-0 w-1 ${(NHC_STATUS[r.status] || NHC_STATUS.Draft)[1]}"></span>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 pl-4 sm:pl-5 pr-3 sm:pr-4 py-3 sm:py-3.5">
                        <div class="min-w-0 flex-1 basis-full sm:basis-[220px]">
                            <div class="flex flex-wrap items-center gap-1.5">
                                ${act ? '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-700 text-white">Your turn</span>' : ''}
                                <span class="font-mono text-[11px] font-semibold text-amber-700">${nhcEsc(r.control_no)}</span>
                                ${nhcBadge(r.status)}
                                ${r.returned ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-red-50 text-red-700">Returned</span>' : ''}
                            </div>
                            <p class="mt-1 text-sm font-semibold text-gray-900 truncate capitalize">${nhcEsc(r.client_name)}</p>
                            <p class="text-[11px] text-gray-500">${nhcEsc(r.project_type || '')}${r.amount ? ` <span class="text-gray-300 mx-1">·</span><span class="font-semibold text-gray-700">${nhcMoney(r.amount)}</span>` : ''}</p>
                            <p class="text-[11px] mt-0.5" title="${nhcEsc(nhcDate(r.updated_at))}"><i class="fa-regular fa-clock mr-1 text-gray-300"></i>${nhcAgoHtml(r)}</p>
                        </div>
                        ${nhcProgress(r)}
                        <div class="w-full sm:w-auto sm:ml-auto shrink-0">
                            ${act ? `<span class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 px-3.5 py-2.5 sm:py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg">${NHC_ACTION_LABEL[r.my_action]}</span>`
                                : '<span class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 px-3.5 py-2.5 sm:py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg"><i class="fa-regular fa-eye"></i> View</span>'}
                        </div>
                    </div>
                </article>`;
            }).join('');
        }

        async function nhcLoad({ silent = false } = {}) {
            if (!silent && !nhcRows.length) document.getElementById('nhcList').innerHTML = Array.from({ length: 3 }).map(() => '<div class="h-[78px] rounded-xl bg-white border border-gray-200 animate-pulse"></div>').join('');
            try {
                const res = await fetch(`${NHC_AJAX}?action=list&q=${encodeURIComponent(nhcSearchTerm)}`);
                const data = await res.json();
                if (!data.success) { if (!silent) crmShowToast(data.message || 'Failed to load.', 'error'); return; }
                const sig = JSON.stringify(data.rows);
                if (sig !== nhcSig) { nhcSig = sig; nhcRows = data.rows; nhcRender(); }
            } catch (e) {
                if (!silent) document.getElementById('nhcList').innerHTML = '<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">Could not load contracts. <button type="button" onclick="nhcLoad()" class="font-semibold underline">Retry</button></div>';
            }
        }

        // ═══ Panel ═══
        function nhcSection(title, icon, inner) {
            return `<section class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-3">
                <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid ${icon} mr-1 text-amber-700"></i>${title}</p>
                <div class="px-4 py-3">${inner}</div></section>`;
        }
        function nhcRow(k, v) {
            return `<div class="flex flex-col sm:flex-row sm:justify-between gap-0.5 sm:gap-3 py-1.5 border-b border-gray-50 last:border-0 text-[12.5px]"><span class="text-[11.5px] sm:text-[12.5px] text-gray-400 sm:whitespace-nowrap">${k}</span><span class="text-gray-800 font-medium sm:text-right min-w-0 break-words">${v}</span></div>`;
        }

        async function nhcOpen(inquiryId) {
            nhcCurrent = inquiryId;
            nhcRender();
            document.getElementById('nhcOverlay').classList.remove('hidden');
            requestAnimationFrame(() => document.getElementById('nhcPanel').classList.remove('translate-x-full'));
            document.getElementById('nhcPControl').textContent = 'Loading…';
            document.getElementById('nhcPClient').textContent = '';
            document.getElementById('nhcPBadges').innerHTML = '';
            document.getElementById('nhcPBody').innerHTML = '<div class="space-y-3">' + Array.from({ length: 4 }).map(() => '<div class="h-16 rounded-xl bg-gray-100 animate-pulse"></div>').join('') + '</div>';
            document.getElementById('nhcPFoot').innerHTML = '';
            try {
                const res = await fetch(`${NHC_AJAX}?action=detail&inquiry_id=${inquiryId}`);
                const data = await res.json();
                if (nhcCurrent !== inquiryId) return;
                if (!data.success) { document.getElementById('nhcPBody').innerHTML = `<p class="text-sm text-red-600 text-center py-8">${nhcEsc(data.message)}</p>`; return; }
                nhcData = data;
                nhcRenderPanel();
            } catch (e) {
                document.getElementById('nhcPBody').innerHTML = '<p class="text-sm text-red-600 text-center py-8">Connection error. Please try again.</p>';
            }
        }

        function nhcPanelWide(wide) {
            const p = document.getElementById('nhcPanel');
            p.classList.toggle('max-w-xl', !wide);
            p.classList.toggle('max-w-4xl', wide);
        }

        function nhcClose() {
            document.getElementById('nhcPanel').classList.add('translate-x-full');
            setTimeout(() => document.getElementById('nhcOverlay').classList.add('hidden'), 300);
            nhcCurrent = null; nhcData = null;
            nhcRender();
            try { history.replaceState(null, '', location.pathname); } catch (e) { }
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') nhcClose(); });

        function nhcRenderPanel() {
            const d = nhcData;
            const c = d.contract;
            const info = c || d.defaults;
            document.getElementById('nhcPControl').textContent = info.control_no;
            document.getElementById('nhcPClient').textContent = info.client_name || '';
            document.getElementById('nhcPBadges').innerHTML = nhcBadge(c ? c.status : 'Not created');

            nhcPanelWide(d.my_action === 'create' || d.my_action === 'edit');
            if (d.my_action === 'create' || d.my_action === 'edit') return nhcRenderForm(info, c);
            if (!c) {
                document.getElementById('nhcPBody').innerHTML = nhcSection('Status', 'fa-circle-info', '<p class="text-[12.5px] text-gray-600">The Final is approved. Waiting for the Accounting Head to prepare the contract.</p>');
                document.getElementById('nhcPFoot').innerHTML = nhcFootClose();
                return;
            }
            nhcRenderView(c, d);
        }

        function nhcFootClose(extra = '') {
            return `<div class="flex flex-wrap items-center justify-end gap-2">${extra}<button type="button" onclick="nhcClose()" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button></div>`;
        }

        // Naka-set na e-signature (kapareho ng Budget Request). Kapag wala, hindi makakapirma.
        function nhcSigWarning() {
            if (nhcData.has_signature !== false) return '';
            return `<div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 mb-3 text-[12px] text-red-700">
                <p class="font-semibold"><i class="fa-solid fa-signature mr-1"></i>You have no e-signature set yet.</p>
                <p class="mt-0.5">Set your signature first (the same one used in Budget Requests), then come back to sign.</p></div>`;
        }
        // Preview ng pirmang ilalagay
        function nhcMySigPreview() {
            const u = nhcData.my_signature_url;
            return u ? `<div class="mt-2 inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-3 py-1.5"><span class="text-[10.5px] text-gray-500">Your signature:</span><img src="${nhcEsc(u)}" alt="Your signature" class="h-8 max-w-[160px] object-contain"></div>` : '';
        }

        // ── Form (Accounting Head) ──
        function nhcRenderForm(v, c) {
            const pct = ['dp_percent', 'delivery_percent', 'install_percent', 'retention_percent'];
            const val = k => nhcEsc(v[k] ?? '');
            const returned = c && c.remarks
                ? `<div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 mb-3"><p class="text-[11px] font-bold uppercase tracking-wide text-red-700"><i class="fa-solid fa-rotate-left mr-1"></i>Returned for correction</p><p class="text-[12.5px] text-red-800 mt-0.5 whitespace-pre-line">${nhcEsc(c.remarks)}</p></div>` : '';

            document.getElementById('nhcPBody').innerHTML = `
                ${returned}${nhcSigWarning()}
                <form id="nhcForm" onsubmit="return false" autocomplete="off">
                ${nhcSection('Parties &amp; project', 'fa-handshake', `
                    <div class="space-y-3">
                        <div><label class="nhc-label" for="f_owner">Owner (client or company name) *</label><input id="f_owner" name="owner_name" class="nhc-field" maxlength="150" value="${val('owner_name')}"></div>
                        <div><label class="nhc-label" for="f_project">Project known as *</label><input id="f_project" name="project_name" class="nhc-field" maxlength="150" placeholder="e.g. Office Renovation" value="${val('project_name')}"></div>
                        <div><label class="nhc-label" for="f_address">Address *</label><input id="f_address" name="address" class="nhc-field" maxlength="255" value="${val('address')}"></div>
                        <div><label class="nhc-label" for="f_contact">Contact no. *</label><input id="f_contact" name="contact_number" class="nhc-field" maxlength="50" value="${val('contact_number')}"></div>
                    </div>`)}
                ${nhcSection('Schedule', 'fa-calendar-days', `
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="nhc-label" for="f_start">Anticipated start date *</label><input id="f_start" name="start_date" type="date" class="nhc-field" value="${val('start_date')}"></div>
                        <div><label class="nhc-label" for="f_end">Completion date *</label><input id="f_end" name="completion_date" type="date" class="nhc-field" value="${val('completion_date')}"></div>
                    </div>`)}
                ${nhcSection('Amount &amp; payments', 'fa-peso-sign', `
                    <div class="space-y-3">
                        <div><label class="nhc-label" for="f_amount">Total contract amount (Php) *</label><input id="f_amount" name="contract_amount" inputmode="decimal" class="nhc-field font-semibold tabular-nums" placeholder="0.00" value="${val('contract_amount')}" oninput="nhcCalc()"></div>
                        <div>
                            <p class="nhc-label">Progress payments (must total 100%)</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                ${[['dp_percent', 'Down payment'], ['delivery_percent', 'Before delivery'], ['install_percent', 'Installation'], ['retention_percent', 'Retention']].map(([k, l]) => `
                                    <label class="block"><span class="block text-[10.5px] text-gray-500 mb-0.5">${l}</span>
                                    <div class="relative"><input name="${k}" type="number" min="0" max="100" step="1" class="nhc-field pr-6 tabular-nums" value="${val(k)}" oninput="nhcCalc()"><span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span></div></label>`).join('')}
                            </div>
                            <p id="nhcPctNote" class="text-[11px] mt-1.5"></p>
                        </div>
                        <div class="rounded-lg bg-amber-50/60 border border-amber-100 px-3 py-2 text-[12px] space-y-0.5">
                            <div class="flex justify-between"><span class="text-gray-500">Down payment</span><b id="nhcDpAmt" class="tabular-nums">—</b></div>
                            <div class="flex justify-between"><span class="text-gray-500">Balance</span><b id="nhcBalAmt" class="tabular-nums">—</b></div>
                        </div>
                        <label class="flex items-center gap-2 text-[12.5px] text-gray-700 cursor-pointer select-none"><input type="checkbox" name="dp_paid" value="1" ${Number(v.dp_paid) ? 'checked' : ''} onchange="nhcCalc()" class="w-4 h-4 accent-amber-700"> Down payment is already paid <span class="text-gray-400">(shows "PAID")</span></label>
                        <div class="max-w-[180px]"><label class="nhc-label" for="f_valid">Prices valid for (days)</label><input id="f_valid" name="validity_days" type="number" min="1" max="365" class="nhc-field" value="${val('validity_days')}"></div>
                    </div>`)}
                </form>
                <section class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-3">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex flex-wrap items-center gap-2">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mr-auto"><i class="fa-solid fa-file-contract mr-1 text-amber-700"></i>Contract content</p>
                        <span id="nhcResetIdle"><button type="button" onclick="nhcConfirm(true, 'nhcResetIdle', 'nhcResetBox')" class="text-[11.5px] font-semibold text-gray-500 hover:text-gray-800"><i class="fa-solid fa-arrow-rotate-left mr-1"></i>Start from example</button></span>
                        <span id="nhcResetBox" class="hidden text-[11.5px] space-x-1.5">
                            <span class="text-red-700 font-semibold">Replace everything with the example?</span>
                            <button type="button" onclick="nhcConfirm(false, 'nhcResetIdle', 'nhcResetBox')" class="px-2 py-1 font-semibold text-gray-700 bg-white border border-gray-200 rounded-md">No</button>
                            <button type="button" onclick="nhcResetBody()" class="px-2 py-1 font-semibold text-white bg-red-700 rounded-md">Yes, replace</button>
                        </span>
                    </div>
                    <div class="px-4 py-2.5 bg-amber-50/40 border-b border-gray-100">
                        <p class="text-[11px] text-gray-600 mb-1.5">Write the contract for this project. The text below is only an example — replace it. Click a field to insert it where the cursor is — it fills in automatically from the details above.</p>
                        <div class="flex flex-wrap gap-1.5">${Object.entries(nhcData.placeholders || {}).map(([k, l]) =>
                            `<button type="button" class="nhc-chip" title="${nhcEsc(l)}" onmousedown="event.preventDefault()" onclick="nhcInsertField('${k}')">[[${k}]]</button>`).join('')}</div>
                    </div>
                    <div id="nhcEditorWrap"><div id="nhcEditor"></div></div>
                    <p class="px-4 py-2 border-t border-gray-100 text-[11px] text-gray-500"><i class="fa-solid fa-lock mr-1"></i>The client details, amounts and signature lines at the end are added automatically.</p>
                </section>
                <p class="text-[11px] text-gray-500 px-1"><i class="fa-solid fa-circle-info mr-1 text-amber-700"></i>Save the draft first to preview the PDF. Signing sends the contract to the assigned Sales and Designer.</p>`;
            nhcInitEditor(v.body_html || nhcData.default_body || '');

            const canPreview = !!c;
            document.getElementById('nhcPFoot').innerHTML = `
                <div id="nhcIdle" class="flex flex-wrap items-center justify-end gap-2">
                    ${canPreview ? `<a href="${NHC_PDF}?inquiry_id=${v.inquiry_id}" target="_blank" rel="noopener" class="mr-auto inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-regular fa-file-pdf"></i> Preview PDF</a>` : ''}
                    <button type="button" onclick="nhcSave('save', this)" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-regular fa-floppy-disk"></i> Save draft</button>
                    <button type="button" onclick="nhcConfirm(true)" ${nhcData.has_signature === false ? 'disabled title="Set your e-signature first"' : ''} class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i class="fa-solid fa-signature"></i> Sign &amp; send</button>
                </div>
                <div id="nhcConfirm" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>Sign and send this contract?</p>
                    <p class="text-[11.5px] text-gray-700 mt-1">Your e-signature goes on the Accounting Head line, and the contract goes to Sales and Designer for signing. You can still recall it until the client signs.</p>${nhcMySigPreview()}
                    <div class="flex justify-end gap-2 mt-2.5">
                        <button type="button" onclick="nhcConfirm(false)" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg">Go back</button>
                        <button type="button" onclick="nhcSave('issue', this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg"><i class="fa-solid fa-check"></i> Yes, sign &amp; send</button>
                    </div>
                </div>`;
            nhcCalc();
        }

        // ── Editor ng laman (Quill) ──
        let nhcQuill = null;
        function nhcInitEditor(html) {
            const el = document.getElementById('nhcEditor');
            if (!el) return;
            if (typeof Quill === 'undefined') {
                // Fallback kung hindi na-load ang editor (walang internet sa CDN)
                el.outerHTML = `<div id="nhcEditor" contenteditable="true" class="min-h-[420px] p-4 outline-none text-[14px] leading-relaxed" style="font-family:Cambria,Georgia,serif">${html}</div>`;
                nhcQuill = null;
                return;
            }
            nhcQuill = new Quill(el, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ align: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                        ['clean'],
                    ],
                },
            });
            nhcQuill.clipboard.dangerouslyPasteHTML(html, 'silent');
            nhcQuill.history.clear();
            nhcQuill.setSelection(0, 0, 'silent');
        }
        function nhcBodyHtml() {
            if (nhcQuill) return nhcQuill.root.innerHTML;
            return document.getElementById('nhcEditor')?.innerHTML || '';
        }
        function nhcInsertField(key) {
            const token = `[[${key}]]`;
            if (nhcQuill) {
                const r = nhcQuill.getSelection(true) || { index: nhcQuill.getLength() - 1 };
                nhcQuill.insertText(r.index, token, 'user');
                nhcQuill.setSelection(r.index + token.length, 0, 'user');
            } else {
                document.getElementById('nhcEditor')?.focus();
                document.execCommand('insertText', false, token);
            }
        }
        function nhcResetBody() {
            const html = nhcData.default_body || '';
            if (nhcQuill) { nhcQuill.setContents([], 'silent'); nhcQuill.clipboard.dangerouslyPasteHTML(html, 'user'); }
            else if (document.getElementById('nhcEditor')) document.getElementById('nhcEditor').innerHTML = html;
            nhcConfirm(false, 'nhcResetIdle', 'nhcResetBox');
        }

        function nhcConfirm(show, idleId = 'nhcIdle', boxId = 'nhcConfirm') {
            document.getElementById(idleId)?.classList.toggle('hidden', show);
            document.getElementById(boxId)?.classList.toggle('hidden', !show);
        }

        function nhcCalc() {
            const f = document.getElementById('nhcForm');
            if (!f) return;
            const amt = Number(String(f.contract_amount.value).replace(/[^0-9.]/g, '')) || 0;
            const p = ['dp_percent', 'delivery_percent', 'install_percent', 'retention_percent'].map(k => Number(f[k].value) || 0);
            const sum = p.reduce((a, b) => a + b, 0);
            const dp = Math.round(amt * p[0]) / 100;
            const note = document.getElementById('nhcPctNote');
            note.className = 'text-[11px] mt-1.5 ' + (sum === 100 ? 'text-emerald-700' : 'text-red-600');
            note.innerHTML = sum === 100 ? '<i class="fa-solid fa-check mr-1"></i>Total 100%' : `<i class="fa-solid fa-triangle-exclamation mr-1"></i>Total is ${sum}% — it must be 100%`;
            document.getElementById('nhcDpAmt').textContent = amt ? nhcMoney(dp) + (f.dp_paid.checked ? ' (PAID)' : '') : '—';
            document.getElementById('nhcBalAmt').textContent = amt ? nhcMoney(f.dp_paid.checked ? amt - dp : amt) : '—';
        }

        async function nhcSave(action, btn) {
            const f = document.getElementById('nhcForm');
            const fd = new FormData(f);
            fd.append('action', action);
            fd.append('inquiry_id', nhcCurrent);
            fd.append('body_html', nhcBodyHtml());
            if (!f.dp_paid.checked) fd.set('dp_paid', '0');
            await nhcPost(fd, btn, action === 'save');
        }

        async function nhcPost(fd, btn, keepOpen = false) {
            const old = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…'; }
            try {
                const res = await fetch(NHC_AJAX, { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) { crmShowToast(data.message || 'Something went wrong.', 'error'); return false; }
                crmShowToast(data.message || 'Saved.');
                nhcSig = '';
                await nhcLoad({ silent: true });
                if (keepOpen && nhcCurrent) await nhcOpen(nhcCurrent); else if (nhcCurrent) await nhcOpen(nhcCurrent);
                return true;
            } catch (e) {
                crmShowToast('Connection error. Please try again.', 'error');
                return false;
            } finally {
                if (btn && document.body.contains(btn)) { btn.disabled = false; btn.innerHTML = old; }
            }
        }

        // ── View (pirmahan) ──
        function nhcRenderView(c, d) {
            const steps = c.steps.map(s => {
                const done = !!s.at;
                return `<li class="flex items-start gap-3 py-2">
                    <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-[11px] ${done ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-400'}"><i class="fa-solid ${done ? 'fa-check' : 'fa-pen-nib'}"></i></span>
                    <div class="min-w-0"><p class="text-[12.5px] font-semibold text-gray-800">${s.label}</p>
                    <p class="text-[11px] ${done ? 'text-gray-500' : 'text-gray-400'}">${done ? `${nhcEsc(s.name || '')} · ${nhcDate(s.at)}` : 'Not signed yet'}</p></div></li>`;
            }).join('');

            const clientFiles = c.has_client_id ? `
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <a href="${NHC_AJAX}?action=file&kind=client_id&id=${c.id}" target="_blank" rel="noopener" class="block rounded-lg border border-gray-200 overflow-hidden hover:border-amber-500"><img src="${NHC_AJAX}?action=file&kind=client_id&id=${c.id}" alt="Client ID" class="w-full h-28 object-cover bg-gray-50"><span class="block px-2 py-1 text-[11px] font-semibold text-gray-600">Client ID</span></a>
                    <a href="${NHC_AJAX}?action=file&kind=client_sig&id=${c.id}" target="_blank" rel="noopener" class="block rounded-lg border border-gray-200 overflow-hidden hover:border-amber-500"><img src="${NHC_AJAX}?action=file&kind=client_sig&id=${c.id}" alt="Client signature" class="w-full h-28 object-contain bg-white"><span class="block px-2 py-1 text-[11px] font-semibold text-gray-600">Client signature</span></a>
                </div>` : '';

            const link = c.client_link ? nhcSection('Client signing link', 'fa-link', `
                <p class="text-[12px] text-gray-600 mb-2">Send this link to the client. They can open it on their phone, sign with their finger, and take a photo of their valid ID.</p>
                <div class="flex items-stretch gap-2">
                    <input id="nhcLink" readonly value="${nhcEsc(c.client_link)}" class="nhc-field text-[11.5px] font-mono flex-1 min-w-0" onclick="this.select()">
                    <button type="button" onclick="nhcCopyLink(this)" class="shrink-0 px-3 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-regular fa-copy"></i> Copy</button>
                </div>
                <div class="flex flex-wrap gap-2 mt-2">
                    ${navigator.share ? `<button type="button" onclick="nhcShareLink()" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg"><i class="fa-solid fa-share-nodes mr-1"></i>Share</button>` : ''}
                    <a href="${nhcEsc(c.client_link)}" target="_blank" rel="noopener" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Open</a>
                </div>`) : '';

            document.getElementById('nhcPBody').innerHTML = `
                ${['sign', 'gm_sign'].includes(d.my_action) ? nhcSigWarning() : ''}
                ${link}
                ${nhcSection('Contract', 'fa-file-contract', [
                    nhcRow('Owner', nhcEsc(c.owner_name)),
                    nhcRow('Project', nhcEsc(c.project_name)),
                    nhcRow('Address', nhcEsc(c.address)),
                    nhcRow('Start / completion', `${nhcEsc(c.start_date)} → ${nhcEsc(c.completion_date)}`),
                    nhcRow('Total amount', `<b>${nhcMoney(c.contract_amount)}</b>`),
                    nhcRow('Payments', `DP ${c.dp_percent}% · ${c.delivery_percent}% · ${c.install_percent}% · Ret. ${c.retention_percent}%`),
                    nhcRow('Down payment', nhcMoney(c.dp_amount) + (c.dp_paid ? ' <span class="text-emerald-700 font-semibold">(PAID)</span>' : '')),
                    nhcRow('Balance', nhcMoney(c.balance)),
                ].join('') + `<a href="${NHC_PDF}?inquiry_id=${c.inquiry_id}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-regular fa-file-pdf text-red-500"></i> View full contract (PDF)</a>`)}
                ${nhcSection('Signatures', 'fa-signature', `<ol class="divide-y divide-gray-50">${steps}</ol>${clientFiles}`)}`;

            // Footer: mga gagawin
            const canReturn = (NHC_VIEWER === 'superadmin' && c.status === 'For GM Signing')
                || (['sales', 'designer'].includes(NHC_VIEWER) && ['For Staff Signing', 'For Client Signing'].includes(c.status));
            const canRecall = NHC_VIEWER === 'acct_head' && ['For Staff Signing', 'For Client Signing'].includes(c.status);

            let main = '';
            if (d.my_action === 'sign' || d.my_action === 'gm_sign') {
                main = `<button type="button" onclick="nhcConfirm(true)" ${nhcData.has_signature === false ? 'disabled title="Set your e-signature first"' : ''} class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i class="fa-solid fa-signature"></i> ${d.my_action === 'gm_sign' ? 'Sign as General Manager' : 'Sign contract'}</button>`;
            }
            const sec = [
                canReturn ? `<button type="button" onclick="nhcConfirm(true, 'nhcIdle', 'nhcReturnBox')" class="px-3 py-2 text-xs font-semibold text-red-700 bg-white border border-red-200 rounded-lg hover:bg-red-50"><i class="fa-solid fa-rotate-left mr-1"></i>Return for correction</button>` : '',
                canRecall ? `<button type="button" onclick="nhcConfirm(true, 'nhcIdle', 'nhcRecallBox')" class="px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-arrow-rotate-left mr-1"></i>Recall to edit</button>` : '',
            ].join('');

            document.getElementById('nhcPFoot').innerHTML = `
                <div id="nhcIdle" class="flex flex-wrap items-center justify-end gap-2">
                    <span class="mr-auto flex flex-wrap gap-2">${sec}</span>
                    ${main || `<button type="button" onclick="nhcClose()" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>`}
                </div>
                <div id="nhcConfirm" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>${d.my_action === 'gm_sign' ? 'Give the final signature?' : 'Sign this contract?'}</p>
                    <p class="text-[11.5px] text-gray-700 mt-1">${d.my_action === 'gm_sign' ? 'This completes the contract. Make sure the client signature and ID are correct.' : 'Your e-signature will be placed on your line.'}</p>${nhcMySigPreview()}
                    <div class="flex justify-end gap-2 mt-2.5">
                        <button type="button" onclick="nhcConfirm(false)" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg">Go back</button>
                        <button type="button" onclick="nhcAct('${d.my_action === 'gm_sign' ? 'gm_sign' : 'sign'}', this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg"><i class="fa-solid fa-check"></i> Yes, sign</button>
                    </div>
                </div>
                <div id="nhcReturnBox" class="hidden rounded-xl border border-red-200 bg-red-50 p-3">
                    <p class="text-[12.5px] font-semibold text-red-800"><i class="fa-solid fa-rotate-left mr-1"></i>Return to the Accounting Head for correction</p>
                    <p class="text-[11px] text-red-700/80 mt-0.5">All signatures will be cleared${c.status === 'For GM Signing' ? ', and the client will need to sign again' : ''}.</p>
                    <textarea id="nhcReturnRemarks" rows="2" maxlength="1000" placeholder="What needs to be corrected? (required)" class="mt-2 w-full text-xs border border-red-200 rounded-lg px-2.5 py-1.5 bg-white outline-none focus:border-red-500"></textarea>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" onclick="nhcConfirm(false, 'nhcIdle', 'nhcReturnBox')" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg">Go back</button>
                        <button type="button" onclick="nhcAct('return', this)" class="px-3 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg">Return</button>
                    </div>
                </div>
                <div id="nhcRecallBox" class="hidden rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <p class="text-[12.5px] font-semibold text-gray-800">Recall this contract to edit it?</p>
                    <p class="text-[11px] text-gray-600 mt-0.5">The signatures so far and the client link will be cleared.</p>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" onclick="nhcConfirm(false, 'nhcIdle', 'nhcRecallBox')" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg">Go back</button>
                        <button type="button" onclick="nhcAct('recall', this)" class="px-3 py-1.5 text-xs font-semibold text-white bg-gray-800 rounded-lg">Recall</button>
                    </div>
                </div>`;
        }

        async function nhcAct(action, btn) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('id', nhcData.contract.id);
            if (action === 'return') {
                const r = document.getElementById('nhcReturnRemarks').value.trim();
                if (!r) { crmShowToast('Please write what needs to be corrected.', 'error'); return; }
                fd.append('remarks', r);
            }
            await nhcPost(fd, btn);
        }

        async function nhcCopyLink(btn) {
            const inp = document.getElementById('nhcLink');
            try { await navigator.clipboard.writeText(inp.value); }
            catch (e) { inp.select(); try { document.execCommand('copy'); } catch (x) { } }
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
            setTimeout(() => { if (document.body.contains(btn)) btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy'; }, 1800);
        }
        function nhcShareLink() {
            const c = nhcData.contract;
            navigator.share({ title: 'NobleHome Contract', text: `Contract for ${c.project_name} — please review and sign.`, url: c.client_link }).catch(() => { });
        }

        // ═══ tabs, search, polling, realtime ═══
        document.querySelectorAll('.nhc-tab').forEach(b => b.addEventListener('click', () => {
            nhcTab = b.dataset.tab;
            try { sessionStorage.setItem('nhcTab', nhcTab); } catch (e) { }
            nhcRender();
        }));
        try { const t = sessionStorage.getItem('nhcTab'); if (t !== null) nhcTab = t; } catch (e) { }

        document.querySelectorAll('.nhc-q').forEach(b => b.addEventListener('click', () => {
            const now = new Date(), q = b.dataset.nhcQ;
            if (q === 'today') { const t = nhcYmd(now); nhcSetDate(nhcF.day === t ? {} : { day: t }); }
            else if (q === 'month') { const m = nhcYmd(now).slice(0, 7); nhcSetDate(nhcF.month === m ? {} : { month: m }); }
            else { const mon = new Date(now); mon.setDate(now.getDate() - ((now.getDay() + 6) % 7)); nhcSetDate(nhcF.week ? {} : { week: nhcYmd(mon) }); }
        }));
        document.getElementById('nhcFMonth').addEventListener('change', e => nhcSetDate(e.target.value ? { month: e.target.value } : {}));
        document.getElementById('nhcFDay').addEventListener('change', e => nhcSetDate(e.target.value ? { day: e.target.value } : {}));
        document.getElementById('nhcFFrom').addEventListener('change', e => nhcSetDate({ from: e.target.value, to: nhcF.to }));
        document.getElementById('nhcFTo').addEventListener('change', e => nhcSetDate({ from: nhcF.from, to: e.target.value }));
        document.getElementById('nhcFClear').addEventListener('click', () => nhcSetDate({}));
        (function nhcDatePopover() {
            const btn = document.getElementById('nhcDateBtn'), pop = document.getElementById('nhcDatePop');
            const show = on => { pop.classList.toggle('hidden', !on); btn.setAttribute('aria-expanded', String(on)); };
            btn.addEventListener('click', e => {
                if (e.target.closest('#nhcDateBtnX')) { nhcSetDate({}); show(false); return; }
                show(pop.classList.contains('hidden'));
            });
            document.getElementById('nhcDateDone').addEventListener('click', () => show(false));
            document.addEventListener('click', e => { if (!e.target.closest('#nhcDateWrap')) show(false); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') show(false); });
        })();
        document.getElementById('nhcFStatus').addEventListener('change', e => { nhcFStatus = e.target.value; nhcRender(); });
        document.getElementById('nhcFSort').addEventListener('change', e => { nhcSortBy = e.target.value; nhcRender(); });
        // para gumalaw ang "x minutes ago"
        setInterval(() => { if (!document.hidden && nhcRows.length) nhcRender(); }, 60000);
        nhcSyncFilters();

        let nhcDeb = null;
        document.getElementById('nhcSearch').addEventListener('input', function () {
            clearTimeout(nhcDeb);
            const v = this.value;
            nhcDeb = setTimeout(() => { nhcSearchTerm = v.trim(); nhcSig = ''; nhcLoad(); }, 350);
        });

        const nhcLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
        function nhcPoll() {
            clearInterval(nhcTimer);
            nhcTimer = setInterval(() => { if (!document.hidden) nhcLoad({ silent: true }); }, nhcLive() ? 60000 : 10000);
        }
        (function nhcHook() {
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || nhcHook.done) return;
                nhcHook.done = true;
                let t = null;
                window.NH_RT.on('*', 'changed', p => {
                    const type = String(p?.type || '');
                    if (type && !type.startsWith('crm')) return;
                    clearTimeout(t);
                    t = setTimeout(() => { if (!document.hidden) nhcLoad({ silent: true }); }, 400);
                });
                window.NH_RT.onLive(() => nhcPoll());
            };
            hook();
            if (!nhcHook.done) document.addEventListener('DOMContentLoaded', hook);
        })();
        document.addEventListener('visibilitychange', () => { if (!document.hidden) nhcLoad({ silent: true }); });

        nhcLoad().then(() => {
            nhcPoll();
            const q = new URLSearchParams(location.search).get('inquiry');
            if (q && /^\d+$/.test(q)) nhcOpen(Number(q));
        });
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
    @media (max-width: 639px) {
        #nhcFd #nhcDateBtn, #nhcFd #nhcDateDone { display: none !important; }
        #nhcFd #nhcDatePop { display: block !important; position: static; width: auto; max-width: none; margin: 0; padding: 0; border: 0; box-shadow: none; }
    }
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