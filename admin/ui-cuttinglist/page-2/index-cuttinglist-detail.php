<?php
// index-cuttinglist-detail.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$cutListAjaxUrl = BASE_URL . '/cuttinglistajax';
$cutListUrl = BASE_URL . '/crmcuttinglist';
$cutEwoodUrl = BASE_URL . '/crmewood';

$recordId = intval($_GET['id'] ?? 0);
if ($recordId <= 0) {
    header('Location: ' . $cutListUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cutting List — Submission Details</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- Back link -->
            <a href="<?= htmlspecialchars($cutListUrl) ?>"
                class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-gray-500 hover:text-amber-700 mb-4 transition-colors">
                <i class="fa-solid fa-arrow-left text-[11px]"></i> Back to Cutting List
            </a>

            <!-- Header card -->
            <section id="cutHeaderCard" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-4">
                <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
                <div class="p-5">
                    <div class="h-3 w-40 rounded bg-gray-100 animate-pulse mb-2"></div>
                    <div class="h-6 w-64 rounded bg-gray-100 animate-pulse"></div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                <!-- ═══ LEFT: files, history, site notes ═══ -->
                <div class="lg:col-span-2 space-y-4 min-w-0">

                    <!-- Files -->
                    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-folder-open text-amber-700 text-sm"></i>
                            <h2 class="text-sm font-semibold text-gray-900">Files</h2>
                        </div>
                        <div id="cutFileRows" class="space-y-2.5">
                            <div class="h-14 rounded-xl bg-gray-100 animate-pulse"></div>
                        </div>
                    </section>

                    <!-- 2D Revision history (pinakabago sa itaas, luma sa ibaba — walang napapalitan) -->
                    <section id="cutRevisionCard" class="hidden bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-code-branch text-amber-700 text-sm"></i>
                                <h2 class="text-sm font-semibold text-gray-900">2D Revision History</h2>
                            </div>
                            <span id="cutRevisionCount" class="text-[11px] text-gray-400"></span>
                        </div>
                        <div id="cutRevisionList" class="max-h-[560px] overflow-y-auto pr-1"></div>
                    </section>

                    <!-- Site Notes -->
                    <section>
                        <div class="flex items-center gap-2 mb-3 px-1">
                            <i class="fa-solid fa-map-location-dot text-amber-700 text-sm"></i>
                            <h2 class="text-sm font-semibold text-gray-900">Site Notes</h2>
                        </div>
                        <div id="cutSiteVisits" class="space-y-4">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                                <div class="h-3 w-full rounded bg-gray-100 animate-pulse mb-2"></div>
                                <div class="h-3 w-3/4 rounded bg-gray-100 animate-pulse"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ═══ RIGHT: next step + feedback (sticky) ═══ -->
                <aside class="space-y-4 lg:sticky lg:top-6 min-w-0 order-first lg:order-none">
                    <section id="cutNextStep" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="h-3 w-24 rounded bg-gray-100 animate-pulse mb-3"></div>
                        <div class="h-9 w-full rounded-lg bg-gray-100 animate-pulse"></div>
                    </section>

                    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-comment-dots text-amber-700 text-sm"></i>
                                <h2 class="text-sm font-semibold text-gray-900">Feedback to Designer</h2>
                            </div>
                            <span id="cutFeedbackBadge"
                                class="hidden min-w-[20px] h-5 px-1.5 rounded-full bg-amber-600 text-white text-[10px] font-bold items-center justify-center leading-none">0</span>
                        </div>
                        <p id="cutFeedbackSummary" class="text-[12px] text-gray-500 mb-3">—</p>
                        <button type="button" id="cutFeedbackBtn" onclick="cutToggleFeedbackSidebar()"
                            class="w-full inline-flex items-center justify-center gap-2 text-[12px] font-semibold text-gray-700 border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                            <i class="fa-regular fa-comments"></i> Open feedback on 2D
                        </button>
                    </section>
                </aside>
            </div>
        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <!-- Feedback sidebar -->
        <div id="cutFeedbackOverlay" onclick="cutCloseFeedbackSidebar()"
            class="fixed inset-0 bg-black/30 z-[9998] opacity-0 pointer-events-none transition-opacity duration-300"></div>

        <aside id="cutFeedbackSidebar"
            class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[9999] translate-x-full transition-transform duration-300 ease-out flex flex-col">
            <div class="h-1.5 shrink-0 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-amber-700">Cutting → Designer</p>
                    <h2 class="text-sm font-semibold text-gray-900">Feedback on 2D File</h2>
                </div>
                <button type="button" onclick="cutCloseFeedbackSidebar()" aria-label="Close"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="cutFeedbackSidebarBody" class="flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-gray-400 italic">Loading…</p>
            </div>
        </aside>

        <!-- ═══ WHOLE VIEW ng mga space (kapareho ng sa Designer) ═══ -->
        <div id="cutWhole" class="fixed inset-0 z-[9990] hidden items-center justify-center bg-black/50 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="cutWholeTitle">
            <section class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-full flex flex-col overflow-hidden">
                <div class="h-1.5 shrink-0 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
                <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3 shrink-0">
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-table-list text-xs"></i></span>
                    <div class="min-w-0 flex-1">
                        <p id="cutWholeTitle" class="text-sm font-semibold text-gray-900">Space list</p>
                        <p id="cutWholeSub" class="text-[11px] text-gray-400 truncate">Tap a photo to enlarge · tap a PDF to open</p>
                    </div>
                    <button type="button" id="cutWholeClose" aria-label="Close"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
                <div id="cutWholeBody" class="min-h-0 flex-1 overflow-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent]"></div>
                <div id="cutWholeFoot" class="px-5 py-2.5 border-t border-gray-100 text-[11.5px] text-gray-500 shrink-0"></div>
            </section>
        </div>

        <!-- Photo lightbox -->
        <div id="cutLb" class="fixed inset-0 z-[9995] hidden bg-black/80 flex-col items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-4xl flex items-center justify-between text-white mb-3 gap-3">
                <span id="cutLbCount" class="text-xs font-semibold opacity-80 truncate"></span>
                <div class="flex items-center gap-1 shrink-0">
                    <a id="cutLbOpen" href="#" target="_blank" rel="noopener" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10" aria-label="Open original"><i class="fa-solid fa-up-right-from-square text-sm"></i></a>
                    <button type="button" id="cutLbClose" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <div class="relative w-full max-w-4xl flex items-center justify-center">
                <button type="button" id="cutLbPrev" class="absolute left-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
                <img id="cutLbImg" src="" alt="" class="max-w-full max-h-[78vh] rounded-lg object-contain">
                <button type="button" id="cutLbNext" class="absolute right-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>

    </main>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed"></span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>`;
            toast.querySelector('.flex-1').textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_EWOOD_URL = <?= json_encode($cutEwoodUrl) ?>;
        const CUT_RECORD_ID = <?= json_encode($recordId) ?>;

        let cutCurrentRecord = null;
        const CUT_REV_URL = <?= json_encode(BASE_URL . '/crm2drevisionajax') ?>;
        let cutRevisions = [];   // pinakabago muna; [0] = current
        let cutVerifyBusy = false;

        const cutIsNtp = r => r.deposit_status === 'Notice to Proceed';

        // Aling revision ang sumagot sa feedback na ito
        function cutResolvedIn(fid) {
            return cutRevisions.find(r => (r.answers || []).some(a => a.id === fid)) || null;
        }

        async function cutLoadRevisions(r) {
            try {
                const res = await fetch(`${CUT_REV_URL}?action=list&quotation_id=${r.id}`, { cache: 'no-store' });
                const data = await res.json();
                cutRevisions = data.success ? (data.revisions || []) : [];
            } catch (e) {
                console.error('cutLoadRevisions:', e);
                cutRevisions = [];
            }
        }

        function cutEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function cutFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }
        function cutFormatDateShort(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function cutCurrency(value) {
            if (value === null || value === undefined || value === '') return '—';
            const num = Number(String(value).replace(/,/g, ''));
            if (isNaN(num)) return cutEscapeHtml(value);
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // value: naka-escape na HTML
        function cutDetailRow(label, value, icon, wide = false) {
            return `
                <div class="${wide ? 'sm:col-span-2' : ''} flex items-start gap-2.5 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><i class="fa-solid ${icon} text-[11px]"></i></span>
                    <div class="min-w-0">
                        <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">${cutEscapeHtml(label)}</p>
                        <p class="text-[13px] text-gray-800 font-medium break-words">${value || '—'}</p>
                    </div>
                </div>`;
        }

        function cutStatusChip(r) {
            if (!cutIsNtp(r)) {
                return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200" title="Waiting for Accounting to log the deposit"><i class="fa-solid fa-hourglass-half text-[9px]"></i>On hold</span>`;
            }
            const ntp = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-green-50 text-green-700 border-green-200" title="Downpayment received ${cutEscapeHtml(cutFormatDateTimeLong(r.deposit_uploaded_at))}"><i class="fa-solid fa-circle-check text-[9px]"></i>Notice to Proceed</span>`;
            const v2d = r.design_2d_verified
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-green-50 text-green-700 border-green-200"><i class="fa-solid fa-file-circle-check text-[9px]"></i>2D verified</span>`
                : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-red-50 text-red-700 border-red-200"><i class="fa-solid fa-file-circle-exclamation text-[9px]"></i>2D to verify</span>`;
            return ntp + v2d;
        }

        function cutLockedNotice(message) {
            return `
                <div class="flex flex-col items-center justify-center text-center py-8 gap-2 rounded-xl border-2 border-dashed border-gray-200">
                    <span class="w-10 h-10 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-lock"></i></span>
                    <p class="text-[12.5px] text-gray-500 max-w-xs">${cutEscapeHtml(message)}</p>
                </div>`;
        }

        function cutRenderHeader(r) {
            const people = [
                r.branch ? `<span><i class="fa-solid fa-building text-[10px] mr-1 text-gray-300"></i>${cutEscapeHtml(r.branch)} Branch</span>` : '',
                `<span><i class="fa-solid fa-user-tie text-[10px] mr-1 text-gray-300"></i>Sales: ${cutEscapeHtml(r.sales_staff_name || '—')}</span>`,
                `<span><i class="fa-solid fa-pen-nib text-[10px] mr-1 text-gray-300"></i>Designer: ${cutEscapeHtml(r.designer_name || '—')}</span>`,
            ].filter(Boolean).join('');

            document.getElementById('cutHeaderCard').innerHTML = `
                <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-pen-ruler text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="notranslate font-mono text-amber-700 text-[11px] font-bold tracking-wide">${cutEscapeHtml(r.control_no)}</p>
                                <h1 class="text-gray-900 text-lg font-semibold leading-tight capitalize break-words">${cutEscapeHtml(r.client_name)}</h1>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">${cutStatusChip(r)}</div>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11.5px] text-gray-500 mt-3">${people}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5 mt-4 pt-4 border-t border-gray-100">
                        ${cutDetailRow('Address', cutEscapeHtml(r.address), 'fa-location-dot', true)}
                        ${cutDetailRow('Contact Number', cutEscapeHtml(r.contact_number), 'fa-phone')}
                        ${cutDetailRow('Contract Amount', cutCurrency(r.contract_amount), 'fa-peso-sign')}
                        ${cutDetailRow('Type of Project', cutEscapeHtml(r.project_type), 'fa-house')}
                        ${cutDetailRow('Scope of Project', cutEscapeHtml(r.project_scope), 'fa-list-check')}
                        ${cutDetailRow('Measuring Space', cutEscapeHtml(r.measuring_space), 'fa-ruler-combined')}
                        ${cutDetailRow('Measurement Date and Time', cutEscapeHtml(cutFormatDateTimeLong(r.measurement_datetime)), 'fa-calendar-day')}
                    </div>
                </div>`;
        }

        // ── Next step card (kanan) ──
        function cutRenderNextStep(r) {
            const box = document.getElementById('cutNextStep');
            if (!box) return;
            cutVerifyBusy = false;

            if (!cutIsNtp(r)) {
                box.innerHTML = `
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-3">Next step</p>
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-hourglass-half"></i></span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Waiting for Accounting</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">Files and site notes open once Accounting gives the Notice to Proceed. This page updates by itself.</p>
                        </div>
                    </div>`;
                return;
            }

            if (r.design_2d_verified) {
                box.innerHTML = `
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-3">Next step</p>
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-circle-check"></i></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">2D verified</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">by ${cutEscapeHtml(r.design_2d_verified_by_name || '—')} · ${cutEscapeHtml(cutFormatDateShort(r.design_2d_verified_at))}</p>
                        </div>
                    </div>
                    <a href="${cutEscapeHtml(CUT_EWOOD_URL)}" class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm transition-colors">
                        <i class="fa-solid fa-tree"></i> Continue in E-Woodfile
                    </a>`;
                return;
            }

            if (!r.design_2d_path) {
                box.innerHTML = `
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-3">Next step</p>
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-circle-question"></i></span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">No 2D file yet</p>
                            <p class="text-[12px] text-gray-500 mt-0.5">Waiting for the designer to upload the 2D file.</p>
                        </div>
                    </div>`;
                return;
            }

            box.innerHTML = `
                <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400 mb-3">Next step</p>
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-circle-exclamation"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Check the 2D file</p>
                        <p class="text-[12px] text-gray-500 mt-0.5">If there are no more errors, verify it. If something needs fixing, send feedback to the designer.</p>
                    </div>
                </div>
                <div id="cutVerifySlot" class="mt-4">
                    <button type="button" onclick="cutVerify2dAsk(${r.id})"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[12.5px] font-semibold text-white bg-green-700 hover:bg-green-800 rounded-lg shadow-sm transition-colors">
                        <i class="fa-solid fa-file-circle-check"></i> Verify 2D
                    </button>
                </div>
                <button type="button" onclick="cutOpenFeedbackSidebar(); cutShowFeedbackForm(${r.id});"
                    class="mt-2 w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-[12px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-medical"></i> Send feedback instead
                </button>`;
        }

        // Inline confirm (2 hakbang) — walang confirm() dialog
        function cutVerify2dAsk(id) {
            const slot = document.getElementById('cutVerifySlot');
            if (!slot) return;
            slot.innerHTML = `
                <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                    <p class="text-[12px] text-green-800 font-medium mb-2.5">Mark this 2D file as verified (no more errors)?</p>
                    <div class="flex gap-2">
                        <button type="button" onclick="cutVerify2dCancel(${id})" class="flex-1 px-3 py-2 text-[12px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="button" id="cutVerifyConfirmBtn" onclick="cutVerify2d(${id})" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[12px] font-semibold text-white bg-green-700 rounded-lg hover:bg-green-800"><i class="fa-solid fa-check"></i> Yes, verify</button>
                    </div>
                </div>`;
        }
        function cutVerify2dCancel(id) {
            if (cutCurrentRecord) cutRenderNextStep(cutCurrentRecord);
        }

        function cutFileIcon(path) {
            return `<div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center ${path ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-300'}"><i class="fa-solid fa-file-pdf"></i></div>`;
        }
        function cutOpenLink(url, text) {
            return `<a href="${cutEscapeHtml(url)}" target="_blank" rel="noopener" title="${cutEscapeHtml(text)}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 whitespace-nowrap">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>${cutEscapeHtml(text)}</a>`;
        }

        function cutFileCard(label, path, uploaderName, uploaderRole, uploadedAt, revised) {
            const metaLine = path
                ? `<p class="text-[11px] text-gray-400 mt-0.5 truncate">${cutEscapeHtml(uploaderName || '—')} · ${cutEscapeHtml(uploaderRole || '—')}${uploadedAt ? ' · ' + cutEscapeHtml(cutFormatDateShort(uploadedAt)) : ''}</p>`
                : `<p class="text-[11px] text-gray-400 mt-0.5">No file uploaded</p>`;
            return `
                <div class="flex flex-wrap items-center gap-3 border border-gray-200 rounded-xl px-4 py-3 bg-white">
                    ${cutFileIcon(path)}
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-gray-800 flex items-center gap-2 flex-wrap">${cutEscapeHtml(label)}
                            ${revised ? '<span class="inline-flex items-center gap-1 px-1.5 py-px rounded text-[9.5px] font-bold uppercase bg-blue-100 text-blue-700"><i class="fa-solid fa-rotate text-[8px]"></i>Revised</span>' : ''}</p>
                        ${metaLine}
                    </div>
                    ${path ? cutOpenLink(path, 'View PDF') : ''}
                </div>`;
        }

        // 2D card — kasama ang revision tag at Verified state
        function cutRender2dFileCard(r) {
            const path = r.design_2d_path;
            const current = cutRevisions[0] || null;
            const revBadge = current && current.revision_no > 0
                ? `<span class="inline-flex items-center gap-1 px-1.5 py-px rounded text-[9.5px] font-bold uppercase bg-blue-100 text-blue-700" title="${cutEscapeHtml(current.label)} uploaded ${cutEscapeHtml(current.created_at || '')}"><i class="fa-solid fa-code-branch text-[8px]"></i>${cutEscapeHtml(current.label)}</span>`
                : '';
            const verifiedTag = r.design_2d_verified
                ? `<span class="inline-flex items-center gap-1 px-1.5 py-px rounded text-[9.5px] font-bold uppercase bg-green-100 text-green-700" title="Verified by ${cutEscapeHtml(r.design_2d_verified_by_name || '—')} on ${cutEscapeHtml(cutFormatDateTimeLong(r.design_2d_verified_at))}"><i class="fa-solid fa-check text-[8px]"></i>Verified</span>`
                : (path ? '<span class="inline-flex items-center gap-1 px-1.5 py-px rounded text-[9.5px] font-bold uppercase bg-red-100 text-red-700">To verify</span>' : '');
            const files = current && current.files && current.files.length ? current.files : (path ? [{ url: path, name: 'View PDF' }] : []);
            const links = files.map((f, i) => cutOpenLink(f.url, files.length > 1 ? `PDF ${i + 1}` : 'View PDF')).join('');

            const metaLine = path
                ? `<p class="text-[11px] text-gray-400 mt-0.5 truncate">${cutEscapeHtml(r.design_2d_uploader_name || '—')} · ${cutEscapeHtml(r.design_2d_uploaded_role || '—')}${r.design_2d_uploaded_at ? ' · ' + cutEscapeHtml(cutFormatDateShort(r.design_2d_uploaded_at)) : ''}</p>`
                : `<p class="text-[11px] text-gray-400 mt-0.5">No file uploaded</p>`;

            return `
                <div class="flex flex-wrap items-center gap-3 border-2 ${r.design_2d_verified ? 'border-green-200 bg-green-50/30' : 'border-amber-200 bg-amber-50/30'} rounded-xl px-4 py-3">
                    ${cutFileIcon(path)}
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-gray-800 flex items-center gap-2 flex-wrap">2D File ${revBadge}${verifiedTag}</p>
                        ${metaLine}
                    </div>
                    ${links ? `<div class="flex flex-wrap gap-1.5">${links}</div>` : ''}
                </div>`;
        }

        function cutRenderRevisionHistory(r) {
            const card = document.getElementById('cutRevisionCard');
            const list = document.getElementById('cutRevisionList');
            if (!card || !list) return;

            if (!cutIsNtp(r) || !cutRevisions.length) {
                card.classList.add('hidden');
                return;
            }
            card.classList.remove('hidden');
            document.getElementById('cutRevisionCount').textContent =
                `${cutRevisions.length} version${cutRevisions.length === 1 ? '' : 's'} · newest on top`;

            list.innerHTML = `<ol class="relative border-l-2 border-gray-200 ml-2 space-y-3">${cutRevisions.map(v => `
                <li class="relative ml-5">
                    <span class="absolute -left-[29px] top-3.5 w-4 h-4 rounded-full border-2 border-white ${v.is_current ? 'bg-amber-600' : 'bg-gray-300'}"></span>
                    <div class="rounded-xl border ${v.is_current ? 'border-amber-300 bg-amber-50/40' : 'border-gray-200 bg-white'} px-4 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold ${v.revision_no === 0 ? 'bg-gray-100 text-gray-700' : 'bg-amber-700 text-white'}">${cutEscapeHtml(v.label)}</span>
                            ${v.is_current
                    ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-green-100 text-green-700">Current</span>'
                    : '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-gray-100 text-gray-500">Previous</span>'}
                            <span class="ml-auto text-[11px] text-gray-500 whitespace-nowrap"><i class="fa-regular fa-clock mr-1"></i>${cutEscapeHtml(v.created_at || '—')}</span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">${cutEscapeHtml(v.created_by_name)} · ${cutEscapeHtml(v.created_role)}</p>
                        ${v.change_notes ? `
                            <div class="mt-2 rounded-lg bg-white border border-gray-200 px-3 py-2">
                                <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">What changed</p>
                                <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${cutEscapeHtml(v.change_notes)}</p>
                            </div>` : (v.revision_no === 0 ? '<p class="text-[11.5px] text-gray-400 italic mt-1.5">First 2D file submitted.</p>' : '')}
                        ${(v.answers || []).length ? `
                            <div class="mt-2">
                                <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Answers your feedback</p>
                                ${v.answers.map(a => `<p class="text-[12px] text-gray-600 border-l-2 border-amber-300 pl-2 mb-1 whitespace-pre-line break-words">"${cutEscapeHtml(a.message)}" <span class="text-gray-400">· ${cutEscapeHtml(a.created_at)}</span></p>`).join('')}
                            </div>` : ''}
                        <div class="mt-2 flex flex-wrap gap-1.5">${(v.files || []).map(f => `
                            <a href="${cutEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${cutEscapeHtml(f.name)}"
                                class="inline-flex items-center gap-1.5 max-w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] font-medium text-gray-700 hover:border-amber-600 hover:text-amber-800">
                                <i class="fa-solid fa-file-pdf text-red-500"></i><span class="truncate max-w-[200px]">${cutEscapeHtml(f.name)}</span>
                            </a>`).join('')}</div>
                    </div>
                </li>`).join('')}</ol>`;
        }

        function cutRenderFeedbackSidebar(r) {
            const badge = document.getElementById('cutFeedbackBadge');
            const body = document.getElementById('cutFeedbackSidebarBody');
            if (!badge || !body) return;

            const feedback = r.cutting_feedback || [];
            const unresolvedCount = feedback.filter(f => !f.is_resolved).length;

            if (unresolvedCount > 0) {
                badge.textContent = unresolvedCount;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('flex');
            }

            const summary = document.getElementById('cutFeedbackSummary');
            if (summary) {
                summary.textContent = !feedback.length
                    ? 'No feedback sent yet.'
                    : unresolvedCount
                        ? `${unresolvedCount} waiting for the designer · ${feedback.length} total`
                        : `All ${feedback.length} resolved by the designer.`;
            }

            // Kung may sinusulat sa form, huwag burahin (realtime busy guard din ito)
            const draftEl = document.getElementById(`cutFeedbackText_${r.id}`);
            const draft = draftEl ? draftEl.value : '';
            const formOpen = !!(document.getElementById(`cutFeedbackForm_${r.id}`) && !document.getElementById(`cutFeedbackForm_${r.id}`).classList.contains('hidden'));

            const historyHtml = feedback.length
                ? `<div class="space-y-2.5 mb-4">
                    ${feedback.map(f => {
                    const rv = f.is_resolved ? cutResolvedIn(f.id) : null;
                    return `
                        <div class="rounded-xl border ${f.is_resolved ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50'} px-3.5 py-3">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                ${f.is_resolved
                            ? `<span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-green-700"><i class="fa-solid fa-check"></i>Resolved${rv ? ` in ${cutEscapeHtml(rv.label)}` : ''}</span>`
                            : '<span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-amber-700"><i class="fa-solid fa-hourglass-half"></i>Waiting for designer</span>'}
                            </div>
                            <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${cutEscapeHtml(f.message)}</p>
                            <p class="text-[10.5px] text-gray-400 mt-1.5"><i class="fa-regular fa-clock mr-0.5"></i>${cutEscapeHtml(cutFormatDateShort(f.created_at))} · ${cutEscapeHtml(f.created_by_name)}${rv && rv.created_at ? ` · fixed ${cutEscapeHtml(rv.created_at)}` : ''}</p>
                        </div>`;
                }).join('')}
                   </div>`
                : `<div class="flex flex-col items-center text-center gap-2 py-8 mb-2"><i class="fa-regular fa-comments text-3xl text-gray-200"></i><p class="text-sm text-gray-400">No feedback sent yet.</p></div>`;

            const canSend = cutIsNtp(r);
            body.innerHTML = `
                ${historyHtml}
                ${canSend ? `
                <button type="button" id="cutFeedbackFormToggle_${r.id}" onclick="cutToggleFeedbackForm(${r.id})"
                    class="${formOpen ? 'hidden' : ''} w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-[12px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                    <i class="fa-solid fa-plus"></i> Send feedback on 2D
                </button>
                <div id="cutFeedbackForm_${r.id}" class="${formOpen ? '' : 'hidden'} rounded-xl border border-gray-200 p-3 bg-slate-50">
                    <label for="cutFeedbackText_${r.id}" class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">New feedback</label>
                    <textarea id="cutFeedbackText_${r.id}" rows="4" placeholder="Ano ang kailangan i-revise sa 2D..."
                        class="w-full text-[13px] bg-white border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100"></textarea>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" onclick="cutToggleFeedbackForm(${r.id})"
                            class="text-[12px] font-semibold text-gray-500 px-3 py-1.5 rounded-lg hover:bg-gray-100">Cancel</button>
                        <button type="button" id="cutFeedbackSendBtn_${r.id}" onclick="cutSendFeedback(${r.id})"
                            class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-white bg-amber-700 hover:bg-amber-800 px-3.5 py-1.5 rounded-lg"><i class="fa-solid fa-paper-plane text-[10px]"></i> Send</button>
                    </div>
                </div>` : '<p class="text-[12px] text-gray-400 text-center">Feedback opens once this is marked Notice to Proceed.</p>'}
            `;
            const ta = document.getElementById(`cutFeedbackText_${r.id}`);
            if (ta && draft) ta.value = draft;
        }

        function cutToggleFeedbackForm(id) {
            const form = document.getElementById(`cutFeedbackForm_${id}`);
            const toggle = document.getElementById(`cutFeedbackFormToggle_${id}`);
            if (!form) return;
            const willOpen = form.classList.contains('hidden');
            form.classList.toggle('hidden', !willOpen);
            if (toggle) toggle.classList.toggle('hidden', willOpen);
            const ta = document.getElementById(`cutFeedbackText_${id}`);
            if (willOpen && ta) setTimeout(() => ta.focus(), 50);
            if (!willOpen && ta) ta.value = '';
        }
        function cutShowFeedbackForm(id) {
            const form = document.getElementById(`cutFeedbackForm_${id}`);
            if (form && form.classList.contains('hidden')) cutToggleFeedbackForm(id);
        }

        async function cutSendFeedback(quotationId) {
            const textarea = document.getElementById(`cutFeedbackText_${quotationId}`);
            const message = textarea ? textarea.value.trim() : '';
            if (!message) {
                crmShowToast('Please write your feedback first.', 'error');
                return;
            }
            const btn = document.getElementById(`cutFeedbackSendBtn_${quotationId}`);
            if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }

            const formData = new FormData();
            formData.append('action', 'send_feedback');
            formData.append('quotation_id', quotationId);
            formData.append('message', message);

            try {
                const res = await fetch(CUT_LIST_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || 'Feedback sent.');
                if (textarea) textarea.value = '';
                const form = document.getElementById(`cutFeedbackForm_${quotationId}`);
                if (form) form.classList.add('hidden');
                cutLoadDetail(); // reload so the new feedback shows in the sidebar
            } catch (e) {
                console.error('cutSendFeedback:', e);
                crmShowToast('Connection error. Please try again.', 'error');
            } finally {
                if (btn && btn.isConnected) { btn.disabled = false; btn.classList.remove('opacity-60'); }
            }
        }

        // Marks the 2D file as verified (walang mali) by the current cutting user.
        async function cutVerify2d(quotationId) {
            if (cutVerifyBusy) return;
            cutVerifyBusy = true;
            const btn = document.getElementById('cutVerifyConfirmBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying…'; }

            const formData = new FormData();
            formData.append('action', 'verify_2d');
            formData.append('quotation_id', quotationId);

            try {
                const res = await fetch(CUT_LIST_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    cutVerify2dCancel(quotationId);
                    return;
                }

                crmShowToast(data.message || '2D file verified.');
                cutLoadDetail(); // reload so the badge shows immediately
            } catch (e) {
                console.error('cutVerify2d:', e);
                crmShowToast('Connection error. Please try again.', 'error');
                cutVerify2dCancel(quotationId);
            } finally {
                cutVerifyBusy = false;
            }
        }

        function cutOpenFeedbackSidebar() {
            document.getElementById('cutFeedbackOverlay').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('cutFeedbackSidebar').classList.remove('translate-x-full');
        }

        function cutCloseFeedbackSidebar() {
            document.getElementById('cutFeedbackOverlay').classList.add('opacity-0', 'pointer-events-none');
            document.getElementById('cutFeedbackSidebar').classList.add('translate-x-full');
        }

        function cutToggleFeedbackSidebar() {
            const sidebar = document.getElementById('cutFeedbackSidebar');
            if (sidebar.classList.contains('translate-x-full')) {
                cutOpenFeedbackSidebar();
            } else {
                cutCloseFeedbackSidebar();
            }
        }

        document.addEventListener('keydown', (e) => {
            if (cutLbIsOpen()) {
                if (e.key === 'Escape') cutLbClose();
                if (e.key === 'ArrowLeft') cutLbShow(cutLbIdx - 1);
                if (e.key === 'ArrowRight') cutLbShow(cutLbIdx + 1);
                return;
            }
            if (e.key !== 'Escape') return;
            if (!document.getElementById('cutWhole').classList.contains('hidden')) { cutCloseWhole(); return; }
            cutCloseFeedbackSidebar();
        });

        function cutVisitedBadge(visited) {
            return visited
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border whitespace-nowrap bg-green-50 text-green-700 border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Visited</span>`
                : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border whitespace-nowrap bg-gray-50 text-gray-500 border-gray-200"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Not Visited</span>`;
        }

        function cutNoteBlock(label, value, icon) {
            return `
                <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3 min-w-0">
                    <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1"><i class="fa-solid ${icon} mr-1"></i>${cutEscapeHtml(label)}</p>
                    <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${value ? cutEscapeHtml(value) : '<span class="text-gray-400 italic">None provided</span>'}</p>
                </div>`;
        }

        function cutRenderSiteVisitCard(visit, label) {
            const photos = visit.photos || [];
            const spaces = visit.spaces || [];
            const pics = cutVisitPhotos(visit);
            const photosHtml = photos.length
                ? `<p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mt-4 mb-2"><i class="fa-solid fa-camera mr-1"></i>${spaces.length ? 'Other site photos' : 'Photos'} (${photos.length})</p>
                   <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    ${photos.map(p => `
                        <button type="button" data-cut-photo="${visit.id}:${pics.indexOf(p)}" class="block aspect-square border border-gray-200 rounded-lg overflow-hidden hover:border-amber-600 hover:opacity-90 transition">
                            <img src="${cutEscapeHtml(p)}" loading="lazy" alt="" class="w-full h-full object-cover">
                        </button>`).join('')}
                   </div>`
                : (spaces.length ? '' : `<p class="text-xs text-gray-400 mt-3"><i class="fa-regular fa-image mr-1"></i>No photographs attached.</p>`);

            // Spaces (Kitchen, Bedroom…) + Whole view
            const measured = spaces.filter(cutSpaceMeasured).length;
            const spacesHtml = spaces.length ? `
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/40 p-3.5">
                    <div class="flex flex-wrap items-center gap-2 mb-2.5">
                        <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-vector-square mr-1 text-amber-700"></i>Spaces (${spaces.length})</p>
                        <span class="text-[11px] ${measured === spaces.length ? 'text-green-700' : 'text-amber-700'} font-semibold">${measured} of ${spaces.length} measured</span>
                        <button type="button" data-cut-whole="${visit.id}"
                            class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-900 hover:bg-black text-white text-[11.5px] font-semibold"><i class="fa-solid fa-table-list"></i> Whole view</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">${spaces.map((sp, i) => {
                        const has = cutSpaceMeasured(sp);
                        return `<button type="button" data-cut-whole="${visit.id}" data-cut-space="${i + 1}"
                            class="inline-flex items-center gap-1.5 pl-1 pr-2.5 py-1 rounded-full border text-[11.5px] font-semibold ${has ? 'bg-green-50 border-green-200 text-green-800 hover:bg-green-100' : 'bg-white border-amber-200 text-amber-800 hover:bg-amber-100'}">
                            <span class="w-5 h-5 rounded-full bg-white/80 border border-current/20 text-[10px] font-bold flex items-center justify-center">${i + 1}</span>${cutEscapeHtml(sp.name)}
                            <i class="fa-solid ${has ? 'fa-check' : 'fa-ruler'} text-[9px]"></i></button>`;
                    }).join('')}</div>
                </div>` : '';

            return `
                <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <p class="text-[13px] font-semibold text-gray-900">${cutEscapeHtml(label)}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Logged ${cutEscapeHtml(cutFormatDateShort(visit.created_at))} · ${cutEscapeHtml(visit.designer_name)}</p>
                        </div>
                        ${cutVisitedBadge(visit.visited)}
                    </div>
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap gap-x-5 gap-y-1 text-[12px] text-gray-600 mb-3">
                            <span class="min-w-0 break-words"><i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>${cutEscapeHtml(visit.address || '—')}</span>
                            <span><i class="fa-regular fa-calendar text-gray-300 mr-1"></i>${cutEscapeHtml(cutFormatDateTimeLong(visit.visit_datetime))}</span>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2.5">
                            ${spaces.length && !visit.measurements ? '' : cutNoteBlock('Measurements', visit.measurements, 'fa-ruler')}
                            ${cutNoteBlock('Site conditions', visit.site_conditions, 'fa-helmet-safety')}
                            ${cutNoteBlock('Client requirements', visit.client_requirements, 'fa-clipboard-list')}
                            ${cutNoteBlock('Existing structure', visit.existing_structure, 'fa-building')}
                        </div>
                        ${spacesHtml}
                        ${photosHtml}
                    </div>
                </article>`;
        }

        // ═══ SPACES: Whole view + lightbox ═══
        let cutVisits = [];
        let cutWholeVisitId = 0;
        let cutLbList = [], cutLbIdx = 0;
        const cutSpaceMeasured = sp => (sp.meas_pdfs || []).length + (sp.meas_imgs || []).length > 0;
        // lahat ng larawan ng isang visit: photos ng bawat space, measurement photos, saka "other" photos
        function cutVisitPhotos(visit) {
            const out = [];
            (visit.spaces || []).forEach(sp => { (sp.photos || []).forEach(p => out.push(p)); (sp.meas_imgs || []).forEach(p => out.push(p)); });
            (visit.photos || []).forEach(p => out.push(p));
            return [...new Set(out)];
        }
        const cutDash = '<span class="text-gray-300">—</span>';
        const cutText = v => String(v || '').trim() ? `<span class="whitespace-pre-line break-words">${cutEscapeHtml(String(v).trim())}</span>` : cutDash;
        function cutThumbs(visit, list, size, badge) {
            const pics = cutVisitPhotos(visit);
            return (list || []).length ? `<div class="flex flex-wrap gap-1">${list.map(p => `
                <button type="button" data-cut-photo="${visit.id}:${pics.indexOf(p)}" class="relative block ${size} rounded-md overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600">
                    <img src="${cutEscapeHtml(p)}" alt="" loading="lazy" class="w-full h-full object-cover">
                    ${badge ? '<span class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[8px] font-bold text-center leading-3"><i class="fa-solid fa-ruler"></i></span>' : ''}
                </button>`).join('')}</div>` : '';
        }
        function cutMeasHtml(visit, sp) {
            if (!cutSpaceMeasured(sp)) return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10.5px] font-semibold"><i class="fa-solid fa-ruler text-[9px]"></i>No measurement</span>';
            return `<div class="flex flex-col gap-1">${(sp.meas_pdfs || []).map((u, i) => `
                <a href="${cutEscapeHtml(u)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2 py-1 text-[11.5px] font-semibold text-gray-700 hover:border-amber-600 hover:text-amber-800 w-max max-w-full">
                    <i class="fa-solid fa-file-pdf text-red-500"></i>PDF ${i + 1}<i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-gray-400"></i></a>`).join('')}
                ${cutThumbs(visit, sp.meas_imgs, 'w-11 h-11', true)}</div>`;
        }
        function cutRenderWhole(keepScroll) {
            const visit = cutVisits.find(v => v.id === cutWholeVisitId);
            const body = document.getElementById('cutWholeBody');
            if (!visit) { cutCloseWhole(); return; }
            const spaces = visit.spaces || [];
            const top = body.scrollTop;
            const n = spaces.length, measured = spaces.filter(cutSpaceMeasured).length;
            const photoN = spaces.reduce((t, sp) => t + (sp.photos || []).length, 0);
            const fileN = spaces.reduce((t, sp) => t + (sp.meas_pdfs || []).length + (sp.meas_imgs || []).length, 0);
            document.getElementById('cutWholeTitle').innerHTML = `Space list <span class="text-gray-400 font-semibold">(${n})</span>`;
            document.getElementById('cutWholeSub').textContent = `${cutFormatDateTimeLong(visit.visit_datetime)} · ${visit.designer_name || '—'} · tap a photo to enlarge, a PDF to open`;
            document.getElementById('cutWholeFoot').innerHTML = `<b>${measured}</b> of ${n} measured · ${fileN} measurement file${fileN === 1 ? '' : 's'} · ${photoN} site photo${photoN === 1 ? '' : 's'}`
                + (measured < n ? ` · <span class="text-amber-700 font-semibold">${n - measured} without measurement</span>` : '');
            body.innerHTML = `
                <table class="hidden md:table w-full text-[12.5px]">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-[10.5px] font-bold uppercase tracking-wider text-gray-500 shadow-[0_1px_0_#e5e7eb]">
                        <tr><th class="px-3 py-2.5 text-left w-14">No.</th><th class="px-3 py-2.5 text-left w-44">Space name</th><th class="px-3 py-2.5 text-left">Requirements</th>
                            <th class="px-3 py-2.5 text-left w-52">Site photos</th><th class="px-3 py-2.5 text-left w-44">Measurement</th><th class="px-3 py-2.5 text-left">Remark</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">${spaces.map((sp, i) => `
                        <tr id="cut-space-${i + 1}" class="${cutSpaceMeasured(sp) ? '' : 'bg-amber-50/30'} transition-colors">
                            <td class="px-3 py-3"><span class="inline-flex w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold items-center justify-center">${i + 1}</span></td>
                            <td class="px-3 py-3 font-semibold text-gray-900 break-words">${cutEscapeHtml(sp.name)}</td>
                            <td class="px-3 py-3 text-gray-800">${cutText(sp.requirements)}</td>
                            <td class="px-3 py-3">${cutThumbs(visit, sp.photos, 'w-11 h-11') || cutDash}</td>
                            <td class="px-3 py-3">${cutMeasHtml(visit, sp)}</td>
                            <td class="px-3 py-3 text-gray-800">${cutText(sp.notes)}</td>
                        </tr>`).join('')}</tbody>
                </table>
                <div class="md:hidden p-3 space-y-2.5">${spaces.map((sp, i) => `
                    <article id="cut-space-m-${i + 1}" class="rounded-xl border ${cutSpaceMeasured(sp) ? 'border-gray-200' : 'border-amber-200 bg-amber-50/30'} p-3 transition-colors">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                            <p class="flex-1 min-w-0 text-[13px] font-semibold text-gray-900 break-words">${cutEscapeHtml(sp.name)}</p>
                        </div>
                        <dl class="space-y-2 text-[12.5px]">
                            <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Requirements</dt><dd class="text-gray-800">${cutText(sp.requirements)}</dd></div>
                            <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Site photos</dt><dd class="mt-0.5">${cutThumbs(visit, sp.photos, 'w-14 h-14') || cutDash}</dd></div>
                            <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Measurement</dt><dd class="mt-0.5">${cutMeasHtml(visit, sp)}</dd></div>
                            <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Remark</dt><dd class="text-gray-800">${cutText(sp.notes)}</dd></div>
                        </dl>
                    </article>`).join('')}</div>`;
            if (keepScroll) body.scrollTop = top;
        }
        function cutOpenWhole(visitId, spaceNo) {
            cutWholeVisitId = visitId;
            cutRenderWhole(false);
            const modal = document.getElementById('cutWhole');
            modal.classList.remove('hidden'); modal.classList.add('flex');
            document.documentElement.classList.add('overflow-hidden');
            const body = document.getElementById('cutWholeBody');
            body.scrollTop = 0;
            const row = spaceNo ? (document.getElementById('cut-space-' + spaceNo)?.offsetParent ? document.getElementById('cut-space-' + spaceNo) : document.getElementById('cut-space-m-' + spaceNo)) : null;
            if (row) {
                body.scrollTop = Math.max(0, row.getBoundingClientRect().top - body.getBoundingClientRect().top - 44);
                const HL = ['bg-amber-50', 'ring-2', 'ring-inset', 'ring-amber-300'];
                row.classList.add(...HL); setTimeout(() => row.classList.remove(...HL), 1800);
            }
            document.getElementById('cutWholeClose').focus({ preventScroll: true });
        }
        function cutCloseWhole() {
            const modal = document.getElementById('cutWhole');
            modal.classList.add('hidden'); modal.classList.remove('flex');
            document.documentElement.classList.remove('overflow-hidden');
            cutWholeVisitId = 0;
        }
        // realtime / reload: i-refresh ang bukas na Whole view nang hindi nawawala ang scroll
        function cutRefreshWhole() {
            if (cutWholeVisitId && !document.getElementById('cutWhole').classList.contains('hidden')) cutRenderWhole(true);
        }
        const cutLbIsOpen = () => !document.getElementById('cutLb').classList.contains('hidden');
        function cutLbShow(i) {
            if (!cutLbList.length) return;
            cutLbIdx = (i + cutLbList.length) % cutLbList.length;
            document.getElementById('cutLbImg').src = cutLbList[cutLbIdx];
            document.getElementById('cutLbOpen').href = cutLbList[cutLbIdx];
            document.getElementById('cutLbCount').textContent = `Photo ${cutLbIdx + 1} of ${cutLbList.length}`;
            const multi = cutLbList.length > 1;
            document.getElementById('cutLbPrev').style.display = multi ? '' : 'none';
            document.getElementById('cutLbNext').style.display = multi ? '' : 'none';
        }
        function cutLbOpenAt(visitId, idx) {
            const visit = cutVisits.find(v => v.id === visitId);
            if (!visit) return;
            cutLbList = cutVisitPhotos(visit);
            cutLbShow(Math.max(0, idx));
            const lb = document.getElementById('cutLb');
            lb.classList.remove('hidden'); lb.classList.add('flex');
        }
        function cutLbClose() {
            const lb = document.getElementById('cutLb');
            lb.classList.add('hidden'); lb.classList.remove('flex');
        }
        document.addEventListener('click', e => {
            const ph = e.target.closest('[data-cut-photo]');
            if (ph) { const [v, i] = ph.dataset.cutPhoto.split(':').map(Number); cutLbOpenAt(v, i); return; }
            const wb = e.target.closest('[data-cut-whole]');
            if (wb) { cutOpenWhole(Number(wb.dataset.cutWhole), Number(wb.dataset.cutSpace || 0)); }
        });
        document.getElementById('cutWholeClose').addEventListener('click', cutCloseWhole);
        document.getElementById('cutWhole').addEventListener('click', e => { if (e.target.id === 'cutWhole') cutCloseWhole(); });
        document.getElementById('cutLbClose').addEventListener('click', cutLbClose);
        document.getElementById('cutLbPrev').addEventListener('click', () => cutLbShow(cutLbIdx - 1));
        document.getElementById('cutLbNext').addEventListener('click', () => cutLbShow(cutLbIdx + 1));
        document.getElementById('cutLb').addEventListener('click', e => { if (e.target.id === 'cutLb') cutLbClose(); });

        function cutRenderFiles(r) {
            if (!cutIsNtp(r)) {
                document.getElementById('cutFileRows').innerHTML =
                    cutLockedNotice('Files are hidden until this is marked Notice to Proceed.');
                return;
            }

            const cards = [
                cutRender2dFileCard(r),
                cutFileCard('Quotation File', r.quotation_path, r.quotation_uploader_name, r.quotation_uploaded_role, r.quotation_uploaded_at, false),
                r.show_3d
                    ? cutFileCard('3D File', r.design_3d_path, r.design_3d_uploader_name, r.design_3d_uploaded_role, r.design_3d_uploaded_at, false)
                    : '',
            ].join('');
            document.getElementById('cutFileRows').innerHTML = cards;
        }

        function cutRenderSiteVisits(siteVisits, r) {
            const container = document.getElementById('cutSiteVisits');

            if (!cutIsNtp(r)) {
                container.innerHTML = `<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">${cutLockedNotice('Site notes are hidden until this is marked Notice to Proceed.')}</div>`;
                return;
            }

            if (!siteVisits || siteVisits.length === 0) {
                container.innerHTML = `
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 text-center">
                        <p class="text-sm text-gray-400">No site visit was recorded for this inquiry.</p>
                    </div>`;
                return;
            }
            cutVisits = siteVisits;
            const ordered = [...siteVisits].reverse();
            const total = siteVisits.length;
            container.innerHTML = ordered
                .map((visit, i) => cutRenderSiteVisitCard(visit, total > 1 ? `Visit ${total - i}` : 'Site Visit'))
                .join('');
        }

        function cutShowError(message) {
            document.getElementById('cutHeaderCard').innerHTML = `
                <div class="p-6 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <p class="text-sm text-red-600">${cutEscapeHtml(message)}</p>
                </div>`;
            document.getElementById('cutFileRows').innerHTML = '';
            document.getElementById('cutSiteVisits').innerHTML = '';
            document.getElementById('cutNextStep').innerHTML = '<p class="text-[12px] text-gray-400">—</p>';
        }

        async function cutLoadDetail() {
            if (!CUT_RECORD_ID || CUT_RECORD_ID <= 0) {
                cutShowError('Invalid record.');
                return;
            }
            try {
                const res = await fetch(`${CUT_LIST_AJAX_URL}?action=detail&id=${CUT_RECORD_ID}`, { cache: 'no-store' });
                const data = await res.json();

                if (!data.success) {
                    cutShowError(data.message || 'Record not found.');
                    return;
                }

                const r = data.record;
                cutCurrentRecord = r;
                await cutLoadRevisions(r);
                cutRenderHeader(r);
                cutRenderNextStep(r);
                cutRenderFiles(r);
                cutRenderRevisionHistory(r);
                cutRenderSiteVisits(r.site_visits, r);
                cutRefreshWhole();
                cutRenderFeedbackSidebar(r);

            } catch (e) {
                console.error('cutLoadDetail:', e);
                crmShowToast('Connection error while loading the record.', 'error');
            }
        }

        cutLoadDetail();

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Signal para sa inquiry na ito (bagong 2D revision ng Designer, NTP, verify/feedback ng ibang Cutting,
        // reset) → kunin agad. Hindi ginagalaw habang may sinusulat na feedback o naka-bukas ang verify confirm.
        (function cutDetailHookRealtime() {
            const busy = () => {
                const ta = cutCurrentRecord ? document.getElementById(`cutFeedbackText_${cutCurrentRecord.id}`) : null;
                return !!(ta && ta.value.trim()) || !!document.getElementById('cutVerifyConfirmBtn');
            };
            let missed = false;
            const refresh = () => {
                if (document.hidden || busy()) { missed = true; return; }
                missed = false;
                cutLoadDetail();
            };
            document.addEventListener('visibilitychange', () => { if (!document.hidden && missed) refresh(); });
            // habol sa na-miss habang may sinusulat (pagkatapos i-clear o ipadala ang feedback)
            setInterval(() => { if (missed) refresh(); }, 5000);

            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || cutDetailHookRealtime.done) return;
                cutDetailHookRealtime.done = true;
                let t = null;
                window.NH_RT.on('*', 'changed', (payload) => {
                    const inq = Number(payload?.inquiry_id || 0);
                    if (!inq || !cutCurrentRecord || inq !== Number(cutCurrentRecord.inquiry_id)) return;
                    clearTimeout(t);
                    t = setTimeout(refresh, 400);
                });
            };
            hook();
            if (!cutDetailHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
        })();
    </script>
</body>

</html>