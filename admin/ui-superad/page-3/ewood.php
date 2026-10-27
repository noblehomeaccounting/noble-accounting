<?php
// ewood.php  —  Superadmin: QR Approvals (Cutting Department)
// Bawat upload ng cutting ay may QR photo at BOM. Titingnan dito, saka i-Approve o ibalik for Revision.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$approvalAjaxUrl = BASE_URL . '/crmwoodapprovalajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Approvals | Cutting Department</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-superad/css/superad/ewood.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-superad/css/superad/ewood.css') ?: 1 ?>">
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-6 p-4 md:p-6 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
                <div class="ewood-hazard h-1.5"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-qrcode text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Cutting Department</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">QR Approvals</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Check the QR photo and the BOM of each upload, then approve it or send it back for revision.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 w-full sm:w-auto">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 sm:min-w-[110px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">To review</p>
                            <p id="ewoodStatPending" class="text-2xl font-bold text-amber-800 leading-tight tabular-nums">—</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-2 sm:min-w-[110px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Approved today</p>
                            <p id="ewoodStatToday" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-2 sm:min-w-[110px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Approved</p>
                            <p id="ewoodStatApproved" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                        </div>
                    </div>
                </div>
                <div class="px-5 pb-0 flex flex-wrap items-end justify-between gap-3 border-t border-gray-100">
                    <div class="flex gap-1 -mb-px">
                        <button type="button" data-tab="pending" class="ewood-tab inline-flex items-center gap-2 px-3.5 py-3 text-xs font-semibold border-b-2 border-transparent text-gray-400">
                            <i class="fa-solid fa-inbox"></i> To review
                            <span id="ewoodPendingCount" class="min-w-[20px] px-1.5 py-px rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold text-center">—</span>
                        </button>
                        <button type="button" data-tab="approved" class="ewood-tab inline-flex items-center gap-2 px-3.5 py-3 text-xs font-semibold border-b-2 border-transparent text-gray-400">
                            <i class="fa-solid fa-circle-check"></i> Approved
                            <span id="ewoodApprovedCount" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold text-center">—</span>
                        </button>
                    </div>
                    <div class="relative w-full sm:w-64 pb-2.5">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-[calc(50%-5px)] -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="ewoodSearch" placeholder="Search control no., client, BOM no.…"
                            class="w-full h-9 bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:bg-white focus:border-amber-600">
                    </div>
                </div>
            </section>

            <!-- ═══ TO REVIEW ═══ -->
            <div id="ewoodPanelPending">
                <ol class="flex flex-wrap items-center gap-x-5 gap-y-1 mb-3 px-1 text-[11px] text-gray-500">
                    <li class="flex items-center gap-1.5"><span class="w-4 h-4 rounded-full bg-amber-100 text-amber-800 text-[9px] font-bold flex items-center justify-center">1</span> Open the QR photo (click to enlarge or download)</li>
                    <li class="flex items-center gap-1.5"><span class="w-4 h-4 rounded-full bg-amber-100 text-amber-800 text-[9px] font-bold flex items-center justify-center">2</span> Scan it in WeChat (Scan → Album)</li>
                    <li class="flex items-center gap-1.5"><span class="w-4 h-4 rounded-full bg-amber-100 text-amber-800 text-[9px] font-bold flex items-center justify-center">3</span> Check the BOM, then Approve or send back</li>
                </ol>
                <div id="ewoodApprovalList" class="space-y-4">
                    <div class="bg-white border border-gray-200 rounded-xl text-center text-gray-400 text-xs py-14"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
                </div>
                <div id="ewoodApprovalEmpty" class="hidden bg-white border-2 border-dashed border-gray-200 rounded-xl text-center py-12">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-green-50 text-green-600 flex items-center justify-center mb-2"><i class="fa-solid fa-mug-hot"></i></div>
                    <p class="ewood-empty-msg text-sm text-gray-700 font-semibold">All caught up. Nothing to review.</p>
                </div>
            </div>

            <!-- ═══ APPROVED ═══ -->
            <div id="ewoodPanelApproved" class="hidden">
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div id="ewoodApprovedList" class="divide-y divide-gray-100">
                        <div class="text-center text-gray-400 text-xs py-14"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
                    </div>
                    <div id="ewoodApprovedEmpty" class="hidden text-center py-12">
                        <i class="fa-regular fa-folder-open text-2xl text-gray-300"></i>
                        <p class="ewood-empty-msg text-xs text-gray-500 font-medium mt-2">No approvals yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- BOM view modal — buong BOM na kamukha ng printed form -->
    <div id="ewoodBomViewModal" class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 shrink-0">
                <p id="ewoodBomViewModalTitle" class="text-xs font-semibold text-gray-700 font-mono truncate">BOM</p>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="ewoodBomViewDownload" href="#" target="_blank"
                        class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                    <button type="button" onclick="ewoodBomCloseViewModal()" aria-label="Close"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <div id="ewoodBomViewBody" class="p-5 overflow-x-auto"></div>
        </div>
    </div>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed"></span>
                <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-current opacity-50 hover:opacity-100" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>`;
            toast.querySelector('.flex-1').textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_PROG_AJAX_URL = <?= json_encode($approvalAjaxUrl) ?>;
        const EWOOD_BASE = <?= json_encode(BASE_URL) ?>;

        function ewoodEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Basahin ang sagot ng server nang ligtas (ipinapakita ang totoong error)
        async function ewoodJson(res) {
            const text = await res.text();
            try { return JSON.parse(text.replace(/^﻿/, '').trim()); }
            catch (e) {
                console.error('Not JSON:', res.status, res.url, text);
                let route = res.url;
                try { route = new URL(res.url).pathname.split('/').pop(); } catch (x) { }
                if (/<!doctype html|<html[\s>]/i.test(text)) throw new Error(`"${route}" returned a page instead of data. Check the router, or reload if your session expired.`);
                const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(`"${route}" server error (HTTP ${res.status}): ${plain ? plain.slice(0, 220) : 'empty response'}`);
            }
        }
        async function ewoodPost(action, params) {
            const res = await fetch(`${CUT_PROG_AJAX_URL}?action=${action}`, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(params)
            });
            return ewoodJson(res);
        }

        function ewoodFmt(value, withTime = true) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', withTime
                ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }
                : { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function ewoodAgo(value) {
            if (!value) return '';
            const mins = Math.floor((Date.now() - new Date(String(value).replace(' ', 'T'))) / 60000);
            if (isNaN(mins)) return '';
            if (mins < 1) return 'just now';
            if (mins < 60) return `${mins} min ago`;
            const h = Math.floor(mins / 60);
            if (h < 24) return `${h} hr${h === 1 ? '' : 's'} ago`;
            const d = Math.floor(h / 24);
            return `${d} day${d === 1 ? '' : 's'} ago`;
        }
        const ewoodQty = q => { const n = parseFloat(q); return isNaN(n) ? '' : n.toFixed(2).replace(/\.?0+$/, ''); };

        function ewoodStatusBadge(status) {
            const map = {
                'Pending': 'bg-amber-50 text-amber-700 border-amber-200',
                'In Progress': 'bg-blue-50 text-blue-700 border-blue-200',
                'Completed': 'bg-green-50 text-green-700 border-green-200',
            };
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${map[status] || map.Pending}">${ewoodEscapeHtml(status || 'Pending')}</span>`;
        }

        // ═════════════ STATE ═════════════
        let ewoodTab = 'pending';
        let ewoodPending = [], ewoodApproved = [];
        const ewoodLoaded = { pending: false, approved: false };

        function ewoodSwitchTab(tab) {
            ewoodTab = tab;
            try { sessionStorage.setItem('nh-ewood-tab', tab); } catch (e) { }
            document.getElementById('ewoodPanelPending').classList.toggle('hidden', tab !== 'pending');
            document.getElementById('ewoodPanelApproved').classList.toggle('hidden', tab !== 'approved');
            document.querySelectorAll('.ewood-tab').forEach(btn => {
                const on = btn.dataset.tab === tab;
                btn.classList.toggle('border-amber-700', on);
                btn.classList.toggle('text-amber-700', on);
                btn.classList.toggle('border-transparent', !on);
                btn.classList.toggle('text-gray-400', !on);
            });
        }
        document.querySelectorAll('.ewood-tab').forEach(b => b.addEventListener('click', () => ewoodSwitchTab(b.dataset.tab)));

        const ewoodHay = e => `${e.control_no} ${e.client_name} ${e.uploaded_by ?? ''} ${(e.boms || []).map(b => b.bom_number).join(' ')}`.toLowerCase();

        function ewoodStats() {
            const today = new Date().toDateString();
            document.getElementById('ewoodStatPending').textContent = ewoodLoaded.pending ? ewoodPending.length : '—';
            document.getElementById('ewoodPendingCount').textContent = ewoodLoaded.pending ? ewoodPending.length : '—';
            document.getElementById('ewoodStatApproved').textContent = ewoodLoaded.approved ? ewoodApproved.length : '—';
            document.getElementById('ewoodApprovedCount').textContent = ewoodLoaded.approved ? ewoodApproved.length : '—';
            document.getElementById('ewoodStatToday').textContent = ewoodLoaded.approved
                ? ewoodApproved.filter(e => e.approved_at && new Date(String(e.approved_at).replace(' ', 'T')).toDateString() === today).length : '—';
        }

        // ═════════════ TO REVIEW ═════════════
        function ewoodBomBlock(entry) {
            const boms = entry.boms || [];
            if (!boms.length) {
                return `<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-[11.5px] text-red-700">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>No Bill of Materials is attached to this upload. You can still approve it, but consider sending it back so cutting adds the BOM.</div>`;
            }
            return boms.map(b => {
                const items = b.items || [];
                const shown = items.slice(0, 8);
                return `<div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-gray-50 border-b border-gray-200">
                        <div class="min-w-0">
                            <p class="font-mono text-[11.5px] font-semibold text-amber-800">${ewoodEscapeHtml(b.bom_number)}</p>
                            <p class="text-[10.5px] text-gray-500">${ewoodEscapeHtml(b.general_scope || '')}${b.date_needed ? ' · Needed: ' + ewoodEscapeHtml(b.date_needed) : ''}${b.linked_by_job ? ' · <span class="text-amber-700">found by job</span>' : ''}</p>
                        </div>
                        <button type="button" data-bom-view="${b.id}" class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-semibold text-amber-700 bg-white border border-amber-200 rounded-md hover:bg-amber-50"><i class="fa-solid fa-expand"></i> Full BOM</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[11px] min-w-[460px]">
                            <thead class="text-gray-400"><tr>
                                <th class="text-left font-semibold px-3 py-1.5">Supplier</th>
                                <th class="text-left font-semibold px-3 py-1.5">Item</th>
                                <th class="text-right font-semibold px-3 py-1.5">Qty</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                ${shown.map(it => `<tr>
                                    <td class="px-3 py-1.5 align-top ${String(it.supplier).toUpperCase() === 'OUTSOURCE' ? 'text-amber-700 font-semibold' : 'text-gray-600'}">${ewoodEscapeHtml(it.supplier || '—')}</td>
                                    <td class="px-3 py-1.5 align-top text-gray-800">${it.item_code ? `<span class="font-mono text-[10.5px] text-gray-400 mr-1">${ewoodEscapeHtml(it.item_code)}</span>` : ''}${ewoodEscapeHtml(it.item_description)}</td>
                                    <td class="px-3 py-1.5 align-top text-right whitespace-nowrap text-gray-800">${ewoodQty(it.quantity)} <span class="text-gray-400">${ewoodEscapeHtml(it.unit || '')}</span></td>
                                </tr>`).join('') || '<tr><td colspan="3" class="px-3 py-3 text-center text-gray-400">No items.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    ${items.length > shown.length ? `<p class="px-3 py-1.5 text-[10.5px] text-gray-400 border-t border-gray-100">+${items.length - shown.length} more item${items.length - shown.length === 1 ? '' : 's'} — open Full BOM</p>` : ''}
                </div>`;
            }).join('');
        }

        function ewoodPendingCard(entry) {
            const [qr, ...more] = entry.photos || [];
            const boms = entry.boms || [];
            return `
            <article id="ewoodApproval_${entry.id}" data-search="${ewoodEscapeHtml(ewoodHay(entry))}"
                class="ewood-card bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-[220px_minmax(0,1fr)]">
                    <!-- QR + photos -->
                    <div class="p-4 bg-slate-50 md:border-r border-b md:border-b-0 border-gray-100">
                        ${qr ? `<a href="${qr}" target="_blank" rel="noopener" class="group relative block aspect-square rounded-lg overflow-hidden border border-gray-200 bg-white" title="Open full size">
                            <img src="${qr}" alt="QR photo" class="w-full h-full object-contain">
                            <span class="absolute inset-0 bg-black/0 group-hover:bg-black/40 flex items-center justify-center text-white text-xs font-semibold opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-magnifying-glass-plus mr-1"></i>Open</span>
                        </a>` : '<div class="aspect-square rounded-lg border border-dashed border-gray-300 flex items-center justify-center text-gray-300"><i class="fa-solid fa-qrcode text-3xl"></i></div>'}
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            ${more.slice(0, 6).map(p => `<a href="${p}" target="_blank" rel="noopener"><img src="${p}" alt="" class="w-11 h-11 object-cover rounded-md border border-gray-200 hover:border-amber-500"></a>`).join('')}
                        </div>
                        ${qr ? `<a href="${qr}" download class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-600 hover:text-amber-700"><i class="fa-solid fa-download"></i> Download QR photo</a>` : ''}
                        ${entry.archive_url ? `<a href="${entry.archive_url}" target="_blank" class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-gray-600 hover:text-amber-700 min-w-0"><i class="fa-solid fa-file-zipper"></i><span class="truncate">${ewoodEscapeHtml(entry.archive_name || 'Archive')}</span></a>` : ''}
                    </div>

                    <!-- Details + BOM -->
                    <div class="p-4 min-w-0 flex flex-col gap-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="notranslate font-mono text-[11px] font-semibold text-amber-700">${ewoodEscapeHtml(entry.control_no)}</p>
                                <h3 class="text-base font-semibold text-gray-900 capitalize break-words">${ewoodEscapeHtml(entry.client_name)}</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Uploaded ${entry.created_at ? ewoodEscapeHtml(ewoodAgo(entry.created_at)) + ' · ' + ewoodEscapeHtml(ewoodFmt(entry.created_at)) : ''}${entry.uploaded_by ? ' · by <span class="text-gray-600 font-medium">' + ewoodEscapeHtml(entry.uploaded_by) + '</span>' : ''}</p>
                            </div>
                            ${ewoodStatusBadge(entry.status)}
                        </div>
                        ${entry.remarks ? `<p class="text-xs text-gray-600 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 break-words"><i class="fa-solid fa-comment-dots text-gray-300 mr-1"></i>${ewoodEscapeHtml(entry.remarks)}</p>` : ''}
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Bill of Materials${boms.length > 1 ? ` (${boms.length})` : ''}</p>
                            <div class="space-y-2">${ewoodBomBlock(entry)}</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="ewood-actions px-4 py-3 bg-gray-50 border-t border-gray-100" data-id="${entry.id}">
                    ${ewoodActionsIdle()}
                </div>
            </article>`;
        }

        function ewoodActionsIdle() {
            return `<div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-[11px] text-gray-400"><i class="fa-solid fa-qrcode mr-1"></i>Scan the QR first, then decide.</p>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" data-act="revise" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50"><i class="fa-solid fa-rotate-left"></i> Send back</button>
                    <button type="button" data-act="approve" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm"><i class="fa-solid fa-check"></i> Approve</button>
                </div></div>`;
        }

        function ewoodRenderPending() {
            const list = document.getElementById('ewoodApprovalList');
            list.innerHTML = ewoodPending.map(ewoodPendingCard).join('');
            ewoodApplyFilter();
        }

        async function ewoodLoadApprovalList(silent = false) {
            const list = document.getElementById('ewoodApprovalList');
            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=pending_approval`, { cache: 'no-store' });
                const data = await ewoodJson(res);
                if (!data.success) throw new Error(data.message || 'Failed to load the uploads to review.');
                const before = new Set(ewoodPending.map(e => e.id));
                ewoodPending = data.entries || [];
                ewoodLoaded.pending = true;
                const fresh = silent ? ewoodPending.filter(e => !before.has(e.id)).length : 0;
                ewoodRenderPending();
                if (fresh) crmShowToast(`${fresh} new upload${fresh === 1 ? '' : 's'} to review.`);
            } catch (e) {
                console.error(e);
                if (silent) return;
                list.innerHTML = `<div class="bg-white border border-red-200 rounded-xl text-center text-xs text-red-600 py-10 px-4">${ewoodEscapeHtml(e.message)}</div>`;
            }
            ewoodStats();
        }

        // Approve / Send back — inline (walang pop-up)
        document.getElementById('ewoodApprovalList').addEventListener('click', async ev => {
            const viewBtn = ev.target.closest('[data-bom-view]');
            if (viewBtn) return ewoodBomOpenViewModal(viewBtn.dataset.bomView);

            const btn = ev.target.closest('.ewood-actions [data-act]');
            if (!btn) return;
            const box = btn.closest('.ewood-actions');
            const id = +box.dataset.id;
            const entry = ewoodPending.find(e => e.id === id);
            if (!entry) return;
            const act = btn.dataset.act;

            if (act === 'cancel') { box.innerHTML = ewoodActionsIdle(); return; }

            if (act === 'approve') {
                const bomNos = (entry.boms || []).map(b => b.bom_number).join(', ');
                box.innerHTML = `<div class="flex flex-wrap items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2.5">
                    <p class="text-xs text-green-900 flex-1 min-w-[200px]">Approve <b>${ewoodEscapeHtml(entry.control_no)}</b>? ${bomNos ? `Your signature will be placed on <b class="font-mono">${ewoodEscapeHtml(bomNos)}</b>.` : '<span class="text-red-700">No BOM is attached.</span>'}</p>
                    <button type="button" data-act="cancel" class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button>
                    <button type="button" data-act="approve-yes" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700"><i class="fa-solid fa-check"></i> Yes, approve</button></div>`;
                return;
            }

            if (act === 'approve-yes') {
                btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Approving…';
                try {
                    const data = await ewoodPost('approve', { progress_id: id });
                    if (!data.success) throw new Error(data.message || 'Approval failed.');
                    crmShowToast(data.bom_updated ? `${entry.control_no} approved and the BOM was signed.` : `${entry.control_no} approved. No BOM was found to sign.`, data.bom_updated ? 'success' : 'error', 6000);
                    ewoodPending = ewoodPending.filter(e => e.id !== id);
                    ewoodRenderPending();
                    ewoodLoadApprovedList();
                } catch (e) {
                    crmShowToast(ewoodEscapeHtml(e.message), 'error', 6000);
                    box.innerHTML = ewoodActionsIdle();
                    ewoodLoadApprovalList(true);
                }
                ewoodStats();
                return;
            }

            if (act === 'revise') {
                let draft = ''; try { draft = localStorage.getItem('nh-ewood-rev-' + id) || ''; } catch (e) { }
                box.innerHTML = `<div class="rounded-lg border border-red-200 bg-red-50 p-3">
                    <label class="block text-[11px] font-semibold text-red-800 mb-1.5"><i class="fa-solid fa-rotate-left mr-1"></i>What should cutting fix? <span class="text-red-500">*</span></label>
                    <textarea rows="2" maxlength="1000" class="ewood-rev w-full text-xs border border-red-200 bg-white rounded-lg px-3 py-2 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y" placeholder="e.g. The QR is blurry, please retake the photo.">${ewoodEscapeHtml(draft)}</textarea>
                    <p class="ewood-rev-err hidden text-[11px] text-red-600 mt-1"></p>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" data-act="cancel" class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button>
                        <button type="button" data-act="revise-yes" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700"><i class="fa-solid fa-paper-plane"></i> Send back</button>
                    </div></div>`;
                const ta = box.querySelector('.ewood-rev');
                ta.addEventListener('input', () => { try { localStorage.setItem('nh-ewood-rev-' + id, ta.value); } catch (e) { } box.querySelector('.ewood-rev-err').classList.add('hidden'); });
                ta.focus();
                return;
            }

            if (act === 'revise-yes') {
                const ta = box.querySelector('.ewood-rev'), err = box.querySelector('.ewood-rev-err');
                const remarks = ta.value.trim();
                if (remarks.length < 3) { err.textContent = 'Tell cutting what to fix.'; err.classList.remove('hidden'); ta.focus(); return; }
                btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
                try {
                    const data = await ewoodPost('reject', { progress_id: id, remarks });
                    if (!data.success) throw new Error(data.message || 'Could not send it back.');
                    try { localStorage.removeItem('nh-ewood-rev-' + id); } catch (e) { }
                    crmShowToast(`${entry.control_no} was sent back to cutting.`);
                    ewoodPending = ewoodPending.filter(e => e.id !== id);
                    ewoodRenderPending();
                } catch (e) {
                    err.textContent = e.message; err.classList.remove('hidden');
                    btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send back';
                }
                ewoodStats();
            }
        });

        // ═════════════ APPROVED ═════════════
        function ewoodApprovedRow(entry) {
            const qr = (entry.photos || [])[0];
            return `<div id="ewoodApproved_${entry.id}" data-search="${ewoodEscapeHtml(ewoodHay(entry))}" class="ewood-card flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-2 px-4 py-3">
                ${qr ? `<a href="${qr}" target="_blank" rel="noopener" class="shrink-0"><img src="${qr}" alt="" class="w-12 h-12 object-cover rounded-lg border border-gray-200 hover:border-amber-500"></a>`
                    : '<span class="w-12 h-12 shrink-0 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-gray-300"><i class="fa-solid fa-qrcode"></i></span>'}
                <div class="min-w-0 flex-1 basis-[180px]">
                    <p class="notranslate font-mono text-[10.5px] font-semibold text-amber-700">${ewoodEscapeHtml(entry.control_no)}</p>
                    <p class="text-sm font-semibold text-gray-800 truncate capitalize">${ewoodEscapeHtml(entry.client_name)}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 basis-full sm:basis-auto">
                    ${(entry.boms || []).map(b => `<button type="button" data-bom-view="${b.id}" class="inline-flex items-center gap-1 px-2 py-1 text-[10.5px] font-mono font-semibold text-green-800 bg-green-50 border border-green-200 rounded-full hover:bg-green-100"><i class="fa-solid fa-file-invoice"></i> ${ewoodEscapeHtml(b.bom_number)}</button>`).join('') || '<span class="text-[11px] text-gray-400">No BOM linked</span>'}
                </div>
                <div class="text-right shrink-0 ml-auto">
                    <p class="text-[11px] text-green-700 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i>${ewoodEscapeHtml(ewoodFmt(entry.approved_at))}</p>
                    <p class="text-[10.5px] text-gray-400">${entry.approved_by ? 'by ' + ewoodEscapeHtml(entry.approved_by) : ''}</p>
                </div>
            </div>`;
        }

        async function ewoodLoadApprovedList() {
            const list = document.getElementById('ewoodApprovedList');
            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=approved_list`, { cache: 'no-store' });
                const data = await ewoodJson(res);
                if (!data.success) throw new Error(data.message || 'Failed to load approved uploads.');
                ewoodApproved = data.entries || [];
                ewoodLoaded.approved = true;
                list.innerHTML = ewoodApproved.map(ewoodApprovedRow).join('');
                ewoodApplyFilter();
            } catch (e) {
                console.error(e);
                list.innerHTML = `<div class="text-center text-xs text-red-600 py-10 px-4">${ewoodEscapeHtml(e.message)}</div>`;
            }
            ewoodStats();
        }
        document.getElementById('ewoodApprovedList').addEventListener('click', ev => {
            const b = ev.target.closest('[data-bom-view]');
            if (b) ewoodBomOpenViewModal(b.dataset.bomView);
        });

        // ═════════════ SEARCH ═════════════
        function ewoodApplyFilter() {
            const q = document.getElementById('ewoodSearch').value.trim().toLowerCase();
            [
                { key: 'pending', listId: 'ewoodApprovalList', emptyId: 'ewoodApprovalEmpty', emptyMsg: 'All caught up. Nothing to review.' },
                { key: 'approved', listId: 'ewoodApprovedList', emptyId: 'ewoodApprovedEmpty', emptyMsg: 'No approvals yet.' },
            ].forEach(cfg => {
                if (!ewoodLoaded[cfg.key]) return;
                const cards = document.querySelectorAll(`#${cfg.listId} .ewood-card`);
                let visible = 0;
                cards.forEach(card => {
                    const show = !q || card.dataset.search.includes(q);
                    card.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                const empty = document.getElementById(cfg.emptyId);
                empty.classList.toggle('hidden', visible > 0);
                empty.querySelector('.ewood-empty-msg').textContent = cards.length === 0 ? cfg.emptyMsg : 'No results match your search.';
            });
        }
        document.getElementById('ewoodSearch').addEventListener('input', ewoodApplyFilter);

        // ═════════════ BOM VIEW (full form) ═════════════
        // Fetches one BOM's full data (header + items) and renders it inside
        // the modal, mirroring the printed BOM layout.
        async function ewoodBomOpenViewModal(bomId) {
            const modal = document.getElementById('ewoodBomViewModal');
            const body = document.getElementById('ewoodBomViewBody');
            body.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
            document.getElementById('ewoodBomViewDownload').href = `${CUT_PROG_AJAX_URL}?action=bom_pdf&bom_id=${bomId}`;
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=bom_view&bom_id=${bomId}`);
                const data = await ewoodJson(res);
                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(data.message || 'Failed to load BOM.')}</p>`;
                    return;
                }
                ewoodBomRenderViewBody(data.bom);
            } catch (e) {
                console.error(e);
                body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(e.message || 'Connection error while loading.')}</p>`;
            }
        }

        function ewoodBomCloseViewModal() {
            const modal = document.getElementById('ewoodBomViewModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function ewoodBomFmtDate(value) {
            if (!value) return '—';
            const dt = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            // Match the PDF's date('d/m/Y') format exactly (day first).
            const dd = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}/${dt.getFullYear()}`;
        }

        // Strips trailing zeros the same way the PDF's number_format/rtrim does.
        function ewoodBomFmtQty(q) {
            const n = parseFloat(q);
            if (isNaN(n)) return '';
            return n.toFixed(2).replace(/\.?0+$/, '');
        }

        // Renders the BOM inside the modal body, matching the printed form.
        function ewoodBomRenderViewBody(bom) {
            document.getElementById('ewoodBomViewModalTitle').textContent = bom.bom_number || 'BOM';

            const items = bom.items || [];
            const minRows = 6;
            const cellCls = 'border border-gray-800 px-2 py-1.5 text-xs';

            let rows = items.map((it, i) => `
                <tr>
                    <td class="${cellCls} text-center">${i + 1}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_code)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_description)}</td>
                    <td class="${cellCls} text-center">${ewoodBomFmtQty(it.quantity)}</td>
                    <td class="${cellCls} text-center">${ewoodEscapeHtml(it.unit)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.supplier)}</td>
                </tr>`).join('');
            for (let i = items.length; i < minRows; i++) {
                rows += `<tr>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                </tr>`;
            }

            // sigPath is expected to be a full URL (BASE_URL already applied
            // server-side); no BASE_URL prefix added here.
            const sigCell = (name, title, sigPath) => `
    <td class="relative border border-gray-800 text-center align-bottom px-2 pb-1.5 w-1/4 h-24">
        ${sigPath ? `<img src="${sigPath}" alt="" class="pointer-events-none select-none absolute z-20 left-1/2 -translate-x-1/2 bottom-1 h-20 w-auto max-w-[95%] object-contain">` : ''}
        <p class="text-xs font-bold">${name ? ewoodEscapeHtml(name) : '&nbsp;'}</p>
        <p class="text-[9px] italic text-gray-600">${title ? ewoodEscapeHtml(title) : '&nbsp;'}</p>
    </td>`;

            const requestedSigUrl = bom.requested_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.requested_by_signature_path)}` : null;
            const approvedSigUrl  = bom.approved_by_signature_path  ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.approved_by_signature_path)}`  : null;
            const receivedSigUrl = bom.received_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.received_by_signature_path)}` : null;

            document.getElementById('ewoodBomViewBody').innerHTML = `
                <table class="w-full border-collapse border-l-2 border-r-2 border-t-2 border-gray-800">
                    <tr>
                        <td class="w-[8%]"></td>
                        <td class="text-center w-[62%]" style="font-family:'Arial Narrow',Arial,sans-serif;font-weight:900;font-stretch:condensed;letter-spacing:-2px;font-size:34px;line-height:1.1;">BILL OF MATERIALS</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-[8%] align-middle">Page</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Submitted</td>
                    </tr>
                    <tr>
                        <td></td><td></td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${bom.page ?? 1}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${ewoodBomFmtDate(bom.date_submitted)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[20%] align-middle">J.O Number:</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[58%] align-middle" colspan="2">Client</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Needed</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-800 text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.bom_number)}</td>
                        <td class="border border-gray-800 text-center text-sm font-bold px-2 py-1.5" colspan="2">${ewoodEscapeHtml(bom.client_name)}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.date_needed)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[10%] align-middle">Scope:</td>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1">${ewoodEscapeHtml(bom.general_scope)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[5%]">No.</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[15%]">Item Code</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[35%]">Item Description</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Quantity</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Unit</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[25%]">Supplier</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tr><td colspan="6" class="border border-gray-800 text-center text-red-600 font-bold italic text-xs py-2">** NOTHING FOLLOWS **</td></tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-b-2 border-gray-800">
                    <tr class="bg-gray-200">
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Requested By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Noted By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Received By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Approved By:</td>
                    </tr>
                    <tr>
                        ${sigCell(bom.requested_by, bom.requested_by_title, requestedSigUrl)}
                        ${sigCell(bom.noted_by, bom.noted_by_title, null)}
                        ${sigCell(bom.received_by, bom.received_by_title, receivedSigUrl)}
                        ${sigCell(bom.approved_by, bom.approved_by_title, approvedSigUrl)}
                    </tr>
                    <tr><td colspan="4" class="text-center text-[8px] text-gray-500 py-1.5">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
                </table>
            `;
        }

        document.getElementById('ewoodBomViewModal').addEventListener('click', function (ev) {
            if (ev.target === this) ewoodBomCloseViewModal();
        });
        document.addEventListener('keydown', ev => { if (ev.key === 'Escape') ewoodBomCloseViewModal(); });

        // ═════════════ INIT ═════════════
        let ewoodStartTab = 'pending';
        try { ewoodStartTab = sessionStorage.getItem('nh-ewood-tab') || 'pending'; } catch (e) { }
        ewoodSwitchTab(ewoodStartTab === 'approved' ? 'approved' : 'pending');
        ewoodLoadApprovalList();
        ewoodLoadApprovedList();
        // tingnan bawat 60s kung may bagong upload (huwag kapag may binubuksang form)
        setInterval(() => {
            if (document.hidden || document.querySelector('.ewood-actions .ewood-rev, .ewood-actions [data-act="approve-yes"]')) return;
            ewoodLoadApprovalList(true);
        }, 60000);
    </script>
</body>

</html>