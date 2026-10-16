<?php
// transmittal.php  —  Transmittal Form (PO). Pwede lang gumawa kapag PAID na ang Purchase Request.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$trAjaxUrl = BASE_URL . '/transmittalajax';

// "Issued by" = naka-login na PO (pangalan + pirma)
$me = ['name' => $_SESSION['username'] ?? '', 'sig' => null];
$uid = intval($_SESSION['account_id'] ?? 0);
if ($uid > 0) {
    $st = $conn->prepare("SELECT name, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $uid);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if ($u) {
        $me['name'] = $u['name'];
        $sq = !empty($u['active_signature_id'])
            ? $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1")
            : $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        if (!empty($u['active_signature_id'])) $sq->bind_param('ii', $u['active_signature_id'], $uid);
        else $sq->bind_param('i', $uid);
        $sq->execute();
        $r = $sq->get_result()->fetch_assoc();
        $sq->close();
        if ($r) $me['sig'] = $r['path'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmittal</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* ── Papel na Transmittal Form ── */
        .tr-sheet { background: #fff; color: #111; font-family: Cambria, Georgia, 'Times New Roman', serif; font-size: 11.5px; }
        .tr-sheet table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .tr-sheet td { padding: 2px 6px; vertical-align: middle; overflow-wrap: anywhere; }
        .tr-sheet .bx td { border: 1px solid #222; }
        .tr-sheet .bx-outer { border: 2px solid #111; }
        .tr-sheet .lbl { font-weight: 700; white-space: nowrap; }
        .tr-sheet .ctr { text-align: center; }
        .tr-sheet .uline { border-bottom: 1px solid #111; }
        .tr-sheet input, .tr-sheet textarea {
            width: 100%; background: #fff8eb; border: 1px dashed #f0b45a; border-radius: 3px;
            padding: 2px 5px; font: inherit; outline: none; text-align: inherit;
        }
        .tr-sheet input:focus, .tr-sheet textarea:focus { border-style: solid; border-color: #b45309; background: #fff; }
        .tr-sig { position: relative; height: 56px; }
        .tr-sig img { position: absolute; left: 50%; bottom: 4px; transform: translateX(-50%); height: 56px; width: auto; max-width: 90%; object-fit: contain; z-index: 2; pointer-events: none; }
        .tr-sig .nm { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; font-weight: 700; font-size: 12.5px; z-index: 1; }

        /* Print: kinokopya lang ang form sa #nhPrintArea */
        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            html, body { background: #fff !important; height: auto !important; overflow: visible !important; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; width: 100%; }
            #nhPrintArea > * { min-width: 0 !important; width: 100% !important; }
            #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #nhPrintArea .no-print { display: none !important; }
        }
    </style>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">
        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Transmittal</h1>
                    <p class="text-gray-400 text-xs mt-0.5">A transmittal can be made once the purchase request is <b class="text-green-600">Paid</b>.</p>
                </div>
                <div class="inline-flex items-center gap-1 bg-white border border-gray-200 rounded-lg p-1">
                    <button type="button" data-tab="ready" class="tr-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Ready
                        <span data-count="ready" class="min-w-[20px] px-1.5 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                    </button>
                    <button type="button" data-tab="done" class="tr-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Transmittals
                        <span data-count="done" class="min-w-[20px] px-1.5 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                    </button>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <section id="trPanelList" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100">
                    <div class="relative w-full sm:w-80">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="trSearch" placeholder="Search ref no., req no. or client..."
                            class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[820px]">
                        <thead id="trHead" class="bg-gray-50 text-[10.5px] font-semibold uppercase tracking-wider text-gray-500"></thead>
                        <tbody id="trList" class="divide-y divide-gray-100">
                            <tr><td class="px-5 py-12 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="trFooter" class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
            </section>

            <!-- ===================== FORM (papel) ===================== -->
            <section id="trPanelForm" class="hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" id="trBack" class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 hover:text-amber-700">
                            <i class="fa-solid fa-chevron-left text-[9px]"></i> Back
                        </button>
                        <p id="trHint" class="text-xs text-gray-500 truncate"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="trAddRow" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-700 bg-white border border-amber-200 rounded-lg hover:bg-amber-50">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                        <button type="button" id="trPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                            <i class="fa-solid fa-print"></i> Print / Save PDF
                        </button>
                        <button type="button" id="trSubmit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                            <i class="fa-solid fa-paper-plane"></i> Save Transmittal
                        </button>
                    </div>
                </div>
                <!-- Para sa Summary of Transmittal (hindi kasama sa print) -->
                <div id="trSumBox" class="bg-white border border-amber-200 rounded-lg mb-3 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-amber-50 border-b border-amber-200">
                        <p class="text-xs font-semibold text-amber-900"><i class="fa-solid fa-list-check mr-1"></i> For the Summary of Transmittal <span class="font-normal text-amber-700">· not printed</span></p>
                        <span id="trSumStatus" class="text-[11px]"></span>
                    </div>
                    <div id="trSumEdit" class="grid grid-cols-1 md:grid-cols-3 gap-3 p-4">
                        <label class="block">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Particulars <span class="text-red-500">*</span></span>
                            <input type="text" id="trParticulars" maxlength="255" placeholder="e.g. Counter top Consumables"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Mode of Delivery</span>
                            <input type="text" id="trMode" maxlength="255" list="trModeList" placeholder="e.g. Pick up by Client / Lalamove"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                            <datalist id="trModeList"><option value="Pick up by Client"><option value="Lalamove"><option value="Deliver"></datalist>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Remarks</span>
                            <input type="text" id="trSumRemarks" maxlength="1000" placeholder="Optional"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                        </label>
                    </div>
                    <div id="trSumView" class="hidden grid grid-cols-1 md:grid-cols-3 gap-3 px-4 py-3 text-xs"></div>
                </div>

                <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4 md:p-8 overflow-x-auto">
                    <div id="trSheet" class="tr-sheet min-w-[760px] max-w-[820px] mx-auto"></div>
                </div>
            </section>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== CONFIRM MODAL ===================== -->
    <div id="trConfirmModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-file-export text-sm"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Save this transmittal?</p>
                        <p id="trConfirmReq" class="font-mono text-[11px] font-semibold text-red-600"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1">
                    <p><span class="text-gray-400">Project:</span> <span id="trConfirmProject" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Items:</span> <span id="trConfirmItems" class="font-semibold text-gray-800"></span></p>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fa-solid fa-signature text-amber-700 mr-1"></i>Your name and saved signature will be placed in <b>Issued by</b>. The Ref No. is generated on save.
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" id="trConfirmCancel" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="trConfirmOk" class="px-3.5 py-2 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const AJAX = <?= json_encode($trAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;
            const ME   = <?= json_encode($me) ?>;
            const SUMMARY_URL = <?= json_encode(BASE_URL . '/transmittalsummary') ?>;
            const SUM_IDS = { particulars: 'trParticulars', delivery_mode: 'trMode', summary_remarks: 'trSumRemarks' };

            let tab = 'ready';
            let data = { ready: [], done: [] };
            let mode = 'list';     // list | edit | view
            let currentPr = null;  // PR na ginagawan ng transmittal
            let currentTr = null;  // naka-save na transmittal (view)

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const todayISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
            const toDate = v => v ? new Date(String(v).replace(' ', 'T')) : null;
            const fmtDate = v => { const d = toDate(v); return d && !isNaN(d) ? d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'; };
            const slashDate = iso => { if (!iso) return ''; const [y, m, d] = String(iso).slice(0, 10).split('-'); return `${m}/${d}/${y}`; };

            function toast(message, type = 'success', duration = 4000) {
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

            // ═════════════ LIST ═════════════
            function setTab(t) {
                tab = t;
                document.querySelectorAll('.tr-tab').forEach(b => {
                    const on = b.dataset.tab === t;
                    b.classList.toggle('bg-amber-50', on);
                    b.classList.toggle('text-amber-700', on);
                    b.classList.toggle('text-gray-500', !on);
                });
                renderList();
            }
            document.querySelectorAll('.tr-tab').forEach(b => b.addEventListener('click', () => { showPanel('list'); setTab(b.dataset.tab); }));
            document.getElementById('trSearch').addEventListener('input', renderList);

            function renderList() {
                document.querySelector('[data-count="ready"]').textContent = data.ready.length;
                document.querySelector('[data-count="done"]').textContent = data.done.length;
                const q = document.getElementById('trSearch').value.trim().toLowerCase();
                const th = 'px-5 py-3 text-left';
                const head = document.getElementById('trHead');
                const body = document.getElementById('trList');

                if (tab === 'ready') {
                    head.innerHTML = `<tr class="border-b border-gray-200"><th class="${th}">Req No.</th><th class="${th}">Client</th><th class="${th}">BOM</th><th class="px-5 py-3 text-center">Items</th><th class="px-5 py-3 text-right">Total</th><th class="${th}">Paid</th><th class="px-5 py-3 text-right">Action</th></tr>`;
                    const list = data.ready.filter(r => !q || `${r.req_no} ${r.client} ${r.bom_number ?? ''}`.toLowerCase().includes(q));
                    body.innerHTML = list.length ? list.map(r => `
                        <tr class="hover:bg-amber-50/30">
                            <td class="px-5 py-3.5 font-mono text-[12px] font-semibold text-red-600">${esc(r.req_no)}</td>
                            <td class="px-5 py-3.5 max-w-[220px]"><p class="font-semibold text-gray-800 truncate">${esc(r.client)}</p><p class="text-[11px] text-gray-400 truncate">${esc(r.project_scope || '')}</p></td>
                            <td class="px-5 py-3.5 font-mono text-[11px] text-amber-700">${esc(r.bom_number || '—')}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums">${r.item_count}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-gray-900">₱ ${money(r.total_amount)}</td>
                            <td class="px-5 py-3.5"><span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Paid</span><p class="text-[11px] text-gray-400">${fmtDate(r.paid_at)}</p></td>
                            <td class="px-5 py-3.5 text-right"><button type="button" data-create="${r.id}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-file-export"></i> Create Transmittal</button></td>
                        </tr>`).join('')
                        : `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${data.ready.length ? 'No requests match your search.' : 'No paid purchase requests waiting for a transmittal.'}</td></tr>`;
                    document.getElementById('trFooter').textContent = `Showing ${list.length} of ${data.ready.length}`;
                } else {
                    head.innerHTML = `<tr class="border-b border-gray-200"><th class="${th}">Ref No.</th><th class="${th}">Date</th><th class="${th}">Project / Client</th><th class="${th}">Req No.</th><th class="px-5 py-3 text-center">Items</th><th class="${th}">Issued / Received</th><th class="px-5 py-3 text-right">Action</th></tr>`;
                    const list = data.done.filter(r => !q || `${r.ref_no} ${r.req_no ?? ''} ${r.project_name} ${r.client_name}`.toLowerCase().includes(q));
                    body.innerHTML = list.length ? list.map(r => `
                        <tr class="hover:bg-amber-50/30">
                            <td class="px-5 py-3.5 font-mono text-[12px] font-semibold text-gray-900">${esc(r.ref_no)}</td>
                            <td class="px-5 py-3.5 text-gray-700">${esc(slashDate(r.transmittal_date))}</td>
                            <td class="px-5 py-3.5 max-w-[220px]"><p class="font-semibold text-gray-800 truncate">${esc(r.project_name)}</p><p class="text-[11px] text-gray-400 truncate">${esc(r.client_name)}</p></td>
                            <td class="px-5 py-3.5 font-mono text-[11px] text-red-600">${esc(r.req_no || '—')}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums">${r.item_count}</td>
                            <td class="px-5 py-3.5"><p class="text-gray-700">${esc(r.issued_by || '—')}</p><p class="text-[11px] text-gray-400">to ${esc(r.received_by || '—')}</p></td>
                            <td class="px-5 py-3.5 text-right"><button type="button" data-view="${r.id}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-eye"></i> View</button></td>
                        </tr>`).join('')
                        : `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${data.done.length ? 'No transmittals match your search.' : 'No transmittals yet.'}</td></tr>`;
                    document.getElementById('trFooter').textContent = `Showing ${list.length} of ${data.done.length}`;
                }
            }

            async function load() {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Failed to load.', 'error'); return; }
                    data = { ready: d.ready, done: d.done };
                    renderList();
                } catch (e) { toast('Connection error while loading.', 'error'); }
            }

            document.getElementById('trList').addEventListener('click', e => {
                const c = e.target.closest('[data-create]');
                const v = e.target.closest('[data-view]');
                if (c) openCreate(+c.dataset.create);
                if (v) openView(+v.dataset.view);
            });

            function showPanel(p) {
                mode = p === 'list' ? 'list' : mode;
                document.getElementById('trPanelList').classList.toggle('hidden', p !== 'list');
                document.getElementById('trPanelForm').classList.toggle('hidden', p === 'list');
            }
            document.getElementById('trBack').addEventListener('click', () => {
                showPanel('list');
                history.replaceState(null, '', location.pathname);
                load();
            });

            // ═════════════ PAPEL NA FORM ═════════════
            function renderSheet(d, edit) {
                const f = (name, val, extra = '') => edit ? `<input name="${name}" value="${esc(val ?? '')}" ${extra}>` : esc(val ?? '');
                const sigImg = p => p ? `<img src="${BASE}/${esc(p)}" alt="">` : '';
                const infoRow = (label, name, val) => `
                    <tr><td class="lbl" style="width:24%">${label}</td><td class="ctr">${f(name, val, 'style="text-align:center"')}</td></tr>`;

                document.getElementById('trSheet').innerHTML = `
                    <table><tr>
                        <td style="width:18%"><img src="${BASE}/icon/logo.webp" alt="NobleHome" style="height:70px"></td>
                        <td class="ctr">
                            <div style="font-weight:700;font-size:19px;letter-spacing:.02em">NOBLEHOME CONSTRUCTION CORPORATION</div>
                            <div style="font-style:italic;font-size:10px">1181MC Premiere Bldg.,EDSA Balintawak Quezon City</div>
                            <div style="font-size:10.5px">noblehomeconst.ph@gmail.com/ Tel. No. 02-88221295/Cell. No. 0968-591-6544</div>
                        </td>
                        <td style="width:18%"></td>
                    </tr></table>

                    <div class="ctr" style="margin-top:8px"><span style="font-weight:700;font-size:15px;border-bottom:1.5px solid #111;padding:0 30px 1px">TRANSMITTAL FORM</span></div>

                    <table style="margin-top:6px">
                        <tr>
                            <td class="lbl" style="width:12%">REF No.</td>
                            <td style="width:30%"></td>
                            <td class="lbl" style="width:12%;text-align:right">DATE :</td>
                            <td style="width:22%" class="ctr uline">${edit ? `<input type="date" name="transmittal_date" value="${esc(d.transmittal_date || todayISO())}" style="text-align:center">` : `<b>${esc(slashDate(d.transmittal_date))}</b>`}</td>
                            <td></td>
                        </tr>
                        <tr><td></td><td style="font-weight:700;font-size:12.5px;padding-left:40px">${edit ? `<span style="color:#b45309">${esc(d.ref_preview || 'Auto on save')}</span>` : esc(d.ref_no)}</td><td colspan="3"></td></tr>
                    </table>

                    <table class="bx bx-outer" style="margin-top:4px">
                        ${infoRow('PROJECT NAME:', 'project_name', d.project_name)}
                        ${infoRow('CLIENT NAME:', 'client_name', d.client_name)}
                        ${infoRow('CLIENT COMPANY:', 'client_company', d.client_company)}
                        ${infoRow('CLIENT ADRESS:', 'client_address', d.client_address)}
                        ${infoRow('SUBJECT / PURPOSE:', 'subject', d.subject)}
                    </table>

                    <table class="bx bx-outer" style="margin-top:14px">
                        <colgroup><col style="width:14%"><col style="width:43%"><col style="width:11%"><col></colgroup>
                        <tr class="ctr" style="font-weight:700"><td>SERIAL NO.</td><td>DESCRIPTION</td><td style="font-size:10.5px">QUANTITY</td><td>REMARKS</td></tr>
                        <tbody id="trItems"></tbody>
                        <tr><td></td><td class="ctr" style="font-style:italic">---------NOTHING FOLLOWS-----------</td><td></td><td></td></tr>
                    </table>

                    <table class="bx-outer" style="margin-top:6px">
                        <tr><td class="lbl" style="padding:4px 6px">NOTES :</td></tr>
                        <tr><td style="background:#d9d9d9;height:34px;padding:4px 6px">${edit ? `<textarea name="notes" rows="2" style="background:#fff8eb">${esc(d.notes || '')}</textarea>` : esc(d.notes || '').replace(/\n/g, '<br>')}</td></tr>
                    </table>

                    <table style="margin-top:12px">
                        <tr><td class="lbl" style="width:50%">ISSUED BY :</td><td class="lbl">RECEIVED BY :</td></tr>
                        <tr>
                            <td style="padding:0 28px">
                                <div class="tr-sig">${sigImg(edit ? ME.sig : d.issued_by_signature_path)}<span class="nm">${esc(edit ? ME.name : d.issued_by)}</span></div>
                                <div class="uline"></div>
                                <div class="ctr" style="font-size:9px;font-weight:700">(Signature over Printed Name/Date )</div>
                            </td>
                            <td style="padding:0 28px">
                                <div class="tr-sig">${edit ? `<input name="received_by" value="${esc(d.received_by || '')}" placeholder="Receiver's name" style="position:absolute;left:0;right:0;bottom:2px;width:100%;text-align:center;font-weight:700">` : `<span class="nm">${esc(d.received_by || '')}</span>`}</div>
                                <div class="uline"></div>
                                <div class="ctr" style="font-size:9px;font-weight:700">(Signature over Printed Name/Date )</div>
                            </td>
                        </tr>
                    </table>`;

                const body = document.getElementById('trItems');
                const items = d.items || [];
                if (edit) {
                    body.innerHTML = '';
                    (items.length ? items : [{}, {}, {}]).forEach(it => addRow(it));
                } else {
                    body.innerHTML = items.map(it => `
                        <tr><td class="ctr">${esc(it.serial_no)}</td><td class="ctr">${esc(it.description)}</td><td class="ctr" style="white-space:nowrap">${esc(it.quantity)}</td><td>${esc(it.remarks)}</td></tr>`).join('');
                }
            }

            function addRow(it = {}) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input name="serial_no[]" value="${esc(it.serial_no || '')}" style="text-align:center"></td>
                    <td><input name="description[]" value="${esc(it.description || '')}" placeholder="Description" style="text-align:center"></td>
                    <td><input name="quantity[]" value="${esc(it.quantity || '')}" placeholder="Qty" style="text-align:center"></td>
                    <td><div style="display:flex;align-items:center;gap:4px"><input name="remarks[]" value="${esc(it.remarks || '')}"><button type="button" class="tr-del no-print" title="Remove item" style="color:#9ca3af;flex:none;padding:0 2px"><i class="fa-solid fa-xmark"></i></button></div></td>`;
                document.getElementById('trItems').appendChild(tr);
            }

            const sheet = document.getElementById('trSheet');
            sheet.addEventListener('click', e => {
                const del = e.target.closest('.tr-del');
                if (!del) return;
                if (document.querySelectorAll('#trItems tr').length <= 1) { toast('At least one item row is needed.', 'error'); return; }
                del.closest('tr').remove();
                queueSave();
            });

            function setFormMode(m) {
                mode = m;
                document.getElementById('trAddRow').classList.toggle('hidden', m !== 'edit');
                document.getElementById('trSubmit').classList.toggle('hidden', m !== 'edit');
                document.getElementById('trPrint').classList.toggle('hidden', m !== 'view');
                document.getElementById('trSumEdit').classList.toggle('hidden', m !== 'edit');
                document.getElementById('trSumView').classList.toggle('hidden', m !== 'view');
                showPanel('form');
            }

            // ── draft (hindi nare-reset ang ininput) ──
            const draftKey = () => 'nh-tr-draft2-' + (currentPr ? currentPr.id : 'none');
            let draftTimer = null;
            function collect() {
                const v = n => (sheet.querySelector(`[name="${n}"]`)?.value || '').trim();
                const head = {};
                ['transmittal_date', 'project_name', 'client_name', 'client_company', 'client_address', 'subject', 'notes', 'received_by'].forEach(n => head[n] = v(n));
                Object.entries(SUM_IDS).forEach(([k, id]) => head[k] = document.getElementById(id).value.trim());
                head.items = [...document.querySelectorAll('#trItems tr')].map(tr => ({
                    serial_no: tr.querySelector('[name="serial_no[]"]').value,
                    description: tr.querySelector('[name="description[]"]').value,
                    quantity: tr.querySelector('[name="quantity[]"]').value,
                    remarks: tr.querySelector('[name="remarks[]"]').value,
                }));
                return head;
            }
            function saveDraft() { if (mode !== 'edit') return; try { localStorage.setItem(draftKey(), JSON.stringify(collect())); } catch (e) { } }
            function readDraft() { try { return JSON.parse(localStorage.getItem(draftKey()) || 'null'); } catch (e) { return null; } }
            function clearDraft() { try { localStorage.removeItem(draftKey()); } catch (e) { } }
            function queueSave() { clearTimeout(draftTimer); draftTimer = setTimeout(saveDraft, 300); }
            sheet.addEventListener('input', queueSave);
            sheet.addEventListener('change', queueSave);
            document.getElementById('trSumEdit').addEventListener('input', queueSave);

            // ── gumawa mula sa paid na PR ──
            async function openCreate(prId) {
                try {
                    const res = await fetch(`${AJAX}?action=prefill&pr_id=${prId}`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) {
                        toast(d.message || 'Cannot create a transmittal for this request.', 'error', 6000);
                        if (d.transmittal_id) openView(d.transmittal_id);
                        return;
                    }
                    currentPr = d.pr;
                    const base = {
                        ref_preview: d.ref_preview,
                        transmittal_date: todayISO(),
                        // client name lang ang may laman; ang iba ay ita-type
                        project_name: '', client_name: d.pr.client, client_company: '',
                        client_address: '', subject: '', notes: '', received_by: '',
                        items: [],
                    };
                    const draft = readDraft();
                    const data0 = draft ? Object.assign(base, draft) : base;
                    renderSheet(data0, true);
                    Object.entries(SUM_IDS).forEach(([k, id]) => document.getElementById(id).value = data0[k] || '');
                    document.getElementById('trSumStatus').innerHTML = '<span class="text-gray-400">Goes to the summary as <b>For delivery</b> once saved</span>';
                    if (draft) toast('Your unsaved inputs were restored.');
                    document.getElementById('trHint').innerHTML = `<i class="fa-solid fa-pen text-amber-700 mr-1"></i>From <b class="font-mono text-red-600">${esc(d.pr.req_no)}</b> (Paid). Fill in the highlighted fields.`;
                    setFormMode('edit');
                    history.replaceState(null, '', `${location.pathname}?pr=${prId}`);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } catch (e) { toast('Connection error.', 'error'); }
            }

            async function openView(id) {
                try {
                    const res = await fetch(`${AJAX}?action=view&id=${id}`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Could not open transmittal.', 'error'); return; }
                    currentTr = d.tr;
                    renderSheet(d.tr, false);
                    const t = d.tr;
                    const box = (lbl, val) => `<div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">${lbl}</p><p class="text-gray-800 font-medium mt-0.5">${val ? esc(val) : '<span class="text-gray-300">—</span>'}</p></div>`;
                    document.getElementById('trSumView').innerHTML = box('Particulars', t.particulars) + box('Mode of Delivery', t.delivery_mode) + box('Remarks', t.summary_remarks);
                    document.getElementById('trSumStatus').innerHTML = (t.delivered_at
                        ? `<span class="inline-flex items-center gap-1 font-semibold text-green-700"><i class="fa-solid fa-truck"></i> Delivered ${esc(slashDate(t.delivered_at))}</span>`
                        : '<span class="inline-flex items-center gap-1 font-semibold text-orange-600"><i class="fa-solid fa-hourglass-half"></i> For delivery</span>')
                        + ` <a href="${SUMMARY_URL}" class="ml-2 font-semibold text-amber-700 hover:underline">Open summary ›</a>`;
                    document.getElementById('trHint').innerHTML = `<i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Transmittal <b class="font-mono">${esc(d.tr.ref_no)}</b>${d.tr.req_no ? ` for <span class="font-mono text-red-600">${esc(d.tr.req_no)}</span>` : ''}. Print it for the receiver to sign.`;
                    setFormMode('view');
                    history.replaceState(null, '', `${location.pathname}?view=${id}`);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } catch (e) { toast('Connection error.', 'error'); }
            }

            document.getElementById('trAddRow').addEventListener('click', () => { addRow(); queueSave(); });

            // ── print (kopya ng form lang) ──
            document.getElementById('trPrint').addEventListener('click', () => {
                let area = document.getElementById('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = '';
                const copy = sheet.cloneNode(true);
                copy.removeAttribute('id');
                area.appendChild(copy);
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => {
                    window.print();
                    setTimeout(() => { area.innerHTML = ''; }, 500);
                });
            });

            // ── save (may confirm modal, walang alert) ──
            const modal = document.getElementById('trConfirmModal');
            const openConfirm = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
            const closeConfirm = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
            document.getElementById('trConfirmCancel').addEventListener('click', closeConfirm);
            modal.addEventListener('click', e => { if (e.target === modal) closeConfirm(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });

            document.getElementById('trSubmit').addEventListener('click', () => {
                if (!currentPr) return;
                const d = collect();
                if (!d.project_name || !d.client_name) { toast('Project name and client name are required.', 'error'); return; }
                if (!d.particulars) { toast('Particulars is required for the Summary of Transmittal.', 'error'); document.getElementById('trParticulars').focus(); return; }
                const count = d.items.filter(i => i.description.trim()).length;
                if (!count) { toast('Add at least one item.', 'error'); return; }
                document.getElementById('trConfirmReq').textContent = currentPr.req_no;
                document.getElementById('trConfirmProject').textContent = d.project_name;
                document.getElementById('trConfirmItems').textContent = count;
                openConfirm();
            });

            document.getElementById('trConfirmOk').addEventListener('click', async function () {
                const btn = this;
                const d = collect();
                const fd = new FormData();
                fd.append('pr_id', currentPr.id);
                Object.entries(d).forEach(([k, v]) => { if (k !== 'items') fd.append(k, v); });
                d.items.forEach(it => {
                    if (!it.description.trim()) return;
                    fd.append('serial_no[]', it.serial_no);
                    fd.append('description[]', it.description);
                    fd.append('quantity[]', it.quantity);
                    fd.append('remarks[]', it.remarks);
                });
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving…';
                try {
                    const res = await fetch(`${AJAX}?action=create`, { method: 'POST', body: fd });
                    const r = await res.json();
                    closeConfirm();
                    if (!r.success) { toast(r.message || 'Could not save.', 'error'); return; }
                    clearTimeout(draftTimer);
                    clearDraft();
                    toast(`Transmittal ${r.ref_no} saved.` + (r.has_signature ? '' : ' No saved signature was found, so only your name was placed.'), r.has_signature ? 'success' : 'error', 6000);
                    load();
                    setTab('done');
                    openView(r.id);
                } catch (e) { toast('Connection error while saving.', 'error'); }
                finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Save'; }
            });

            // ── simula: ?pr=ID (galing sa Purchase Orders) o ?view=ID ──
            const qs = new URLSearchParams(location.search);
            setTab('ready');
            load();
            if (qs.get('pr')) openCreate(+qs.get('pr'));
            else if (qs.get('view')) openView(+qs.get('view'));
        })();
    </script>
</body>

</html>