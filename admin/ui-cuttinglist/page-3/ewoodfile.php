<?php
//  ewoodfile.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$cutListAjaxUrl = BASE_URL . '/cuttinglistajax';
$cutProgAjaxUrl = BASE_URL . '/crmewoodajax';
$bomAjaxUrl = BASE_URL . '/bomajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cutting Files &amp; Progress</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <style>
        /* Active row in the list */
        .ewood-row.is-active {
            background: #fffbeb;
            box-shadow: inset 3px 0 0 #b45309;
        }

        /* History timeline rail */
        .ewood-timeline {
            position: relative;
        }

        .ewood-timeline::before {
            content: '';
            position: absolute;
            left: 4px;
            top: 8px;
            bottom: 8px;
            width: 1px;
            background: #e5e7eb;
        }

        /* Thin scrollbars */
        #ewoodList,
        #ewoodDetailBody,
        #ewoodBomViewModal {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        /* Mobile: show either the list or the detail, not both */
        @media (max-width: 1023px) {
            #ewoodMain[data-view="detail"] #ewoodSidebar {
                display: none;
            }

            #ewoodMain[data-view="list"] #ewoodDetailPane {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="ewoodMain" data-view="list"
        class="md:ml-56 pt-14 md:pt-0 h-screen flex overflow-hidden transition-all duration-300">

        <!-- ═════════════════════════════════════════════ -->
        <!-- LEFT: submissions list                         -->
        <!-- ═════════════════════════════════════════════ -->
        <aside id="ewoodSidebar" class="w-full lg:w-[380px] shrink-0 h-full bg-white border-r border-gray-200 flex flex-col">

            <div class="px-5 pt-5 pb-4 shrink-0 border-b border-gray-100">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Cutting
                    Department</p>
                <h1 class="text-gray-900 text-lg font-semibold">Cutting Files &amp; Progress</h1>
                <p class="text-gray-400 text-[11px] mt-1 leading-relaxed">
                    Uploads unlock once a job has <strong class="text-gray-500">Notice to Proceed</strong> and a
                    <strong class="text-gray-500">verified 2D file</strong>.
                </p>

                <!-- Search -->
                <div class="relative mt-3">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="ewoodSearch" placeholder="Search control no., client, branch..."
                        class="w-full text-xs bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:bg-white focus:border-amber-600 transition-colors">
                </div>

                <!-- Filter chips -->
                <div class="flex items-center gap-1.5 mt-3">
                    <button type="button" data-filter="all"
                        class="ewood-chip px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors">
                        All <span id="ewoodCount_all" class="opacity-70">0</span>
                    </button>
                    <button type="button" data-filter="ready"
                        class="ewood-chip px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors">
                        Ready <span id="ewoodCount_ready" class="opacity-70">0</span>
                    </button>
                    <button type="button" data-filter="waiting"
                        class="ewood-chip px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors">
                        Waiting <span id="ewoodCount_waiting" class="opacity-70">0</span>
                    </button>
                </div>
            </div>

            <div id="ewoodList" class="flex-1 overflow-y-auto"></div>

            <p id="ewoodCount" class="text-[11px] text-gray-400 px-5 py-2.5 border-t border-gray-100 shrink-0"></p>
        </aside>

        <!-- ═════════════════════════════════════════════ -->
        <!-- RIGHT: detail pane                             -->
        <!-- ═════════════════════════════════════════════ -->
        <section id="ewoodDetailPane" class="flex-1 min-w-0 h-full p-3 lg:p-5 flex">

            <!-- Empty state -->
            <div id="ewoodEmptyState" class="m-auto text-center text-gray-400 max-w-xs">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white border border-gray-200 shadow-sm flex items-center justify-center">
                    <i class="fa-solid fa-scissors text-xl text-amber-700"></i>
                </div>
                <p class="text-sm font-semibold text-gray-600">No submission selected</p>
                <p class="text-xs mt-1">Pick a job from the list to view its details, upload progress, and manage its BOM.</p>
            </div>

            <!-- Detail view -->
            <div id="ewoodDetailView"
                class="hidden flex-col w-full h-full bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                <!-- Header + tabs -->
                <div class="px-5 lg:px-7 pt-4 border-b border-gray-100 shrink-0">
                    <button type="button" onclick="ewoodBackToList()"
                        class="lg:hidden mb-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 hover:text-amber-700">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i> Back to list
                    </button>

                    <p id="ewoodModalControl" class="font-mono text-[11px] font-semibold text-amber-700 tracking-wide"></p>
                    <h2 id="ewoodModalTitle" class="text-lg font-semibold text-gray-900 truncate">Submission</h2>
                    <div id="ewoodHeaderChips" class="mt-2 flex flex-wrap items-center gap-1.5"></div>

                    <div class="mt-4 flex gap-1 -mb-px">
                        <button type="button" data-tab="details" onclick="ewoodSwitchTab('details')"
                            class="ewood-tab-btn inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-amber-700 text-amber-700">
                            <i class="fa-solid fa-circle-info text-[11px]"></i> Details
                        </button>
                        <button type="button" data-tab="upload" onclick="ewoodSwitchTab('upload')"
                            class="ewood-tab-btn inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-cloud-arrow-up text-[11px]"></i> Upload &amp; BOM
                        </button>
                        <button type="button" data-tab="history" onclick="ewoodSwitchTab('history')"
                            class="ewood-tab-btn inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-clock-rotate-left text-[11px]"></i> History
                            <span id="ewoodHistoryCount"
                                class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold items-center justify-center"></span>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div id="ewoodDetailBody" class="flex-1 overflow-y-auto bg-slate-50/60">

                    <!-- Details panel -->
                    <div id="ewoodPanelDetails" class="px-5 lg:px-7 py-5">
                        <div class="max-w-4xl space-y-4">
                            <div id="ewoodDetailNotice"
                                class="hidden text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-white border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">NTP Status</p>
                                    <div id="ewoodDetailNtp"></div>
                                </div>
                                <div class="bg-white border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">2D Verified</p>
                                    <div id="ewoodDetailVerified"></div>
                                </div>
                                <div class="bg-white border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Upload Access</p>
                                    <div id="ewoodDetailAccess"></div>
                                </div>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Latest Progress</p>
                                <p id="ewoodDetailLatest" class="text-sm text-gray-700">—</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upload & BOM panel (two-step: Save Progress, then Create BOM) -->
                    <form id="ewoodUploadForm" class="hidden px-5 lg:px-7 py-5" onsubmit="return false;">
                        <input type="hidden" name="quotation_id" id="ewoodQuotationId">

                        <div class="max-w-3xl space-y-4">

                            <div id="ewoodUploadBlocked"
                                class="hidden text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                            </div>

                            <div id="ewoodUploadFields" class="space-y-4">

                                <!-- STEP 1 -->
                                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
                                        <span class="w-6 h-6 rounded-full bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">Save progress</p>
                                            <p class="text-[11px] text-gray-400">Upload the archive, photos, and remarks for this submission.</p>
                                        </div>
                                    </div>
                                    <div class="p-5 space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">
                                                    <i class="fa-solid fa-file-zipper text-amber-700 mr-1"></i> Archive (.zip / .rar)
                                                </label>
                                                <input type="file" name="archive" accept=".zip,.rar"
                                                    class="w-full text-xs border border-dashed border-gray-300 bg-gray-50 rounded-lg px-2 py-2 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:bg-amber-50 file:text-amber-700 file:text-xs file:font-semibold">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">
                                                    <i class="fa-solid fa-images text-amber-700 mr-1"></i> Photos
                                                    <span class="font-normal text-gray-400">(auto-converted to WebP)</span>
                                                </label>
                                                <input type="file" name="images[]" multiple accept="image/*"
                                                    class="w-full text-xs border border-dashed border-gray-300 bg-gray-50 rounded-lg px-2 py-2 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:bg-amber-50 file:text-amber-700 file:text-xs file:font-semibold">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">Remarks</label>
                                            <textarea name="remarks" rows="3" placeholder="Add notes about this submission..."
                                                class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600"></textarea>
                                        </div>

                                        <!-- Step 1: saves archive/images/remarks via crmewoodajax?action=upload -->
                                        <button type="button" id="ewoodSaveProgressBtn"
                                            class="w-full py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                                            Save Progress
                                        </button>
                                    </div>
                                </div>

                                <!-- BOM already exists for this job and was auto-relinked -->
                                <div id="ewoodBomRelinkedNotice"
                                    class="hidden text-xs text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                                    <i class="fa-solid fa-circle-check mr-1"></i>
                                    This job already has a Bill of Materials on file. It has been linked to this new
                                    submission automatically — no need to create another one.
                                </div>

                                <!-- STEP 2 — hidden until Step 1 succeeds and no BOM could be auto-relinked -->
                                <div id="ewoodBomSection" class="hidden bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
                                        <span class="w-6 h-6 rounded-full bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">Bill of Materials</p>
                                            <p class="text-[11px] text-gray-400">List the materials needed for this job.</p>
                                        </div>
                                    </div>

                                    <div class="p-5 space-y-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">Date Needed</label>
                                                <input type="text" name="date_needed" value="ASAP"
                                                    class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">General Scope</label>
                                                <input type="text" name="general_scope" value="GENERAL SCOPE"
                                                    class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600">
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="block text-[11px] font-semibold text-gray-600">Items</label>
                                                <button type="button" onclick="ewoodBomAddRow()"
                                                    class="text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                                                    <i class="fa-solid fa-plus"></i> Add row
                                                </button>
                                            </div>
                                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                                <table class="w-full text-[11px] min-w-[560px]">
                                                    <thead class="bg-gray-50 text-gray-500">
                                                        <tr>
                                                            <th class="px-2 py-2 text-left font-semibold">Item Code</th>
                                                            <th class="px-2 py-2 text-left font-semibold">Description</th>
                                                            <th class="px-2 py-2 text-left font-semibold w-20">Qty</th>
                                                            <th class="px-2 py-2 text-left font-semibold w-20">Unit</th>
                                                            <th class="px-2 py-2 text-left font-semibold">Supplier</th>
                                                            <th class="w-8"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ewoodBomItemsBody" class="divide-y divide-gray-100"></tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-[11px] text-gray-400 leading-relaxed">
                                            <i class="fa-solid fa-signature mr-1"></i>
                                            Requested By, Noted By, Received By, at Approved By ay awtomatikong napupunan
                                            (pangalan, titulo, at naka-save na pirma) sa bawat departamento habang dumadaan ang
                                            BOM sa kanila. Wala nang manual na ilalagay dito.
                                        </div>

                                        <!-- Step 2: creates the BOM via bomajax?action=create -->
                                        <button type="button" id="ewoodCreateBomBtn"
                                            class="w-full py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                                            Create BOM
                                        </button>
                                    </div>
                                </div>

                                <!-- Reference list of BOMs already submitted for this job -->
                                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100">
                                        <i class="fa-solid fa-file-invoice text-amber-700 text-sm"></i>
                                        <p class="text-sm font-semibold text-gray-800">Previously submitted BOMs</p>
                                    </div>
                                    <div id="ewoodBomList" class="p-4 space-y-2 text-xs"></div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- History panel -->
                    <div id="ewoodPanelHistory" class="hidden px-5 lg:px-7 py-5">
                        <div id="ewoodHistory" class="ewood-timeline max-w-3xl space-y-4 text-xs"></div>
                    </div>

                </div>
            </div>

        </section>
    </main>

    <!-- Toasts -->
    <div id="crmToastContainer"
        class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]">
    </div>

    <!-- BOM view modal — renders the BOM like the printed form, not an iframe -->
    <div id="ewoodBomViewModal"
        class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 shrink-0">
                <p id="ewoodBomViewModalTitle" class="text-xs font-semibold text-gray-700 font-mono truncate">BOM</p>
                <div class="flex items-center gap-3 shrink-0">
                    <a id="ewoodBomViewDownload" href="#" target="_blank"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                    <button type="button" onclick="ewoodBomCloseViewModal()"
                        class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
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
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none">&times;</button>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_PROG_AJAX_URL = <?= json_encode($cutProgAjaxUrl) ?>;
        const BOM_AJAX_URL = <?= json_encode($bomAjaxUrl) ?>;

        let ewoodActiveQuotationId = null;
        let ewoodRowsMap = {};   // quotation_id -> row data, populated on list load
        let ewoodRowsList = [];  // same rows, in list order
        let ewoodListFilter = 'all';
        let ewoodBomRowIndex = 0;

        // Two-step Upload & BOM state — set once Step 1 (Save Progress)
        // succeeds; reset every time a new row is opened.
        let ewoodCurrentProgressionId = null;

        function ewoodEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function ewoodFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        // ── Badges ───────────────────────────────────
        function ewoodNtpBadge(row) {
            const isNtp = row.deposit_status === 'Notice to Proceed';
            const cls = isNtp
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-amber-50 text-amber-700 border-amber-200';
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 ${isNtp ? 'bg-green-600' : 'bg-amber-600'}"></span>
                        ${isNtp ? 'NTP' : 'Hold'}
                    </span>`;
        }

        function ewoodVerifiedBadge(row) {
            const isVerified = !!row.design_2d_verified;
            const cls = isVerified
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-red-50 text-red-600 border-red-200';
            const icon = isVerified ? 'fa-file-circle-check' : 'fa-file-circle-xmark';
            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <i class="fa-solid ${icon} text-[10px]"></i>
                        ${isVerified ? 'Verified' : 'Not verified'}
                    </span>`;
        }

        function ewoodAccessBadge(canUpload) {
            const cls = canUpload
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-gray-100 text-gray-500 border-gray-200';
            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <i class="fa-solid ${canUpload ? 'fa-lock-open' : 'fa-lock'} text-[10px]"></i>
                        ${canUpload ? 'Open' : 'Locked'}
                    </span>`;
        }

        function ewoodApprovalBadge(status) {
            const map = {
                Approved: 'bg-green-50 text-green-700 border-green-200',
                Rejected: 'bg-red-50 text-red-600 border-red-200',
                Pending: 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const cls = map[status] || map.Pending;
            const label = status === 'Pending' ? 'Pending Approval' : status;
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${label}</span>`;
        }

        // ── List ─────────────────────────────────────
        function ewoodIsReady(row) {
            return row.deposit_status === 'Notice to Proceed' && !!row.design_2d_verified;
        }

        function ewoodApplyChipStyles() {
            document.querySelectorAll('.ewood-chip').forEach(chip => {
                const active = chip.dataset.filter === ewoodListFilter;
                chip.classList.toggle('bg-amber-700', active);
                chip.classList.toggle('text-white', active);
                chip.classList.toggle('border-amber-700', active);
                chip.classList.toggle('bg-white', !active);
                chip.classList.toggle('text-gray-500', !active);
                chip.classList.toggle('border-gray-200', !active);
            });
        }

        function ewoodRenderList() {
            const list = document.getElementById('ewoodList');
            const q = document.getElementById('ewoodSearch').value.trim().toLowerCase();

            const readyCount = ewoodRowsList.filter(ewoodIsReady).length;
            document.getElementById('ewoodCount_all').textContent = ewoodRowsList.length;
            document.getElementById('ewoodCount_ready').textContent = readyCount;
            document.getElementById('ewoodCount_waiting').textContent = ewoodRowsList.length - readyCount;
            ewoodApplyChipStyles();

            if (ewoodRowsList.length === 0) {
                list.innerHTML = `<div class="text-center text-gray-400 py-12 text-xs"><i class="fa-regular fa-folder-open text-2xl mb-2 block"></i>No approved submissions found.</div>`;
                document.getElementById('ewoodCount').textContent = '';
                return;
            }

            const filtered = ewoodRowsList.filter(row => {
                const okFilter = ewoodListFilter === 'all'
                    || (ewoodListFilter === 'ready' && ewoodIsReady(row))
                    || (ewoodListFilter === 'waiting' && !ewoodIsReady(row));
                const hay = `${row.control_no} ${row.client_name} ${row.branch} ${row.project_type ?? ''}`.toLowerCase();
                return okFilter && (!q || hay.includes(q));
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div class="text-center text-gray-400 py-12 text-xs">No submissions match your search.</div>`;
            } else {
                list.innerHTML = filtered.map(row => {
                    const ready = ewoodIsReady(row);
                    const statusChip = ready
                        ? `<span class="shrink-0 text-[10px] font-semibold text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">Ready</span>`
                        : `<span class="shrink-0 text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">Waiting</span>`;
                    return `
                    <div id="ewoodRow_${row.id}" onclick="ewoodOpenSidebar(${row.id})"
                        class="ewood-row px-5 py-3.5 border-b border-gray-100 hover:bg-amber-50/40 transition-colors cursor-pointer">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-gray-800 truncate">${ewoodEscapeHtml(row.client_name)}</p>
                                <p class="font-mono text-[10.5px] text-amber-700 truncate mt-0.5">${ewoodEscapeHtml(row.control_no)}</p>
                            </div>
                            ${statusChip}
                        </div>
                        <div class="flex items-center justify-between gap-2 mt-2">
                            <span class="text-[10.5px] text-gray-500 truncate">
                                <i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>${ewoodEscapeHtml(row.branch)}
                                <span class="text-gray-300 mx-1">·</span><span class="capitalize">${ewoodEscapeHtml(row.project_type ?? '—')}</span>
                            </span>
                            <span class="flex items-center gap-1 shrink-0">${ewoodNtpBadge(row)}${ewoodVerifiedBadge(row)}</span>
                        </div>
                    </div>`;
                }).join('');
            }

            document.getElementById('ewoodCount').textContent =
                `Showing ${filtered.length} of ${ewoodRowsList.length} submission${ewoodRowsList.length === 1 ? '' : 's'}`;

            if (ewoodActiveQuotationId !== null) ewoodSetRowHighlight(ewoodActiveQuotationId, true);
        }

        async function ewoodFetchList() {
            try {
                const res = await fetch(`${CUT_LIST_AJAX_URL}?action=list&filter=all`);
                const data = await res.json();
                if (!data.success) { crmShowToast('Failed to load submissions.', 'error'); return; }

                ewoodRowsMap = {};
                ewoodRowsList = data.rows;
                data.rows.forEach(row => { ewoodRowsMap[row.id] = row; });

                ewoodRenderList();
            } catch (e) {
                console.error(e);
                crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        document.getElementById('ewoodSearch').addEventListener('input', ewoodRenderList);
        document.querySelectorAll('.ewood-chip').forEach(chip => {
            chip.addEventListener('click', () => { ewoodListFilter = chip.dataset.filter; ewoodRenderList(); });
        });

        // ── History ──────────────────────────────────
        function ewoodRenderHistory(entries, bomsByProgression = {}) {
            const box = document.getElementById('ewoodHistory');
            if (entries.length === 0) {
                box.innerHTML = `<div class="text-center py-12 text-gray-400"><i class="fa-regular fa-clock text-2xl mb-2 block"></i><p class="text-xs">No updates yet.</p></div>`;
                return;
            }
            box.innerHTML = entries.map(e => {
                const boms = bomsByProgression[e.id] || [];
                const dotCls = e.approval_status === 'Approved' ? 'bg-green-500'
                    : e.approval_status === 'Rejected' ? 'bg-red-500' : 'bg-amber-500';
                const bomsHtml = boms.length
                    ? `<div class="flex flex-wrap gap-1.5 mt-2">
                ${boms.map(b => `
                    <button type="button" data-bom-id="${b.id}"
                        class="ewood-history-bom-btn inline-flex items-center gap-1 text-[10.5px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5 hover:bg-amber-100">
                        <i class="fa-solid fa-file-invoice"></i> ${ewoodEscapeHtml(b.bom_number)}
                    </button>
                `).join('')}
               </div>`
                    : '';
                return `
            <div class="relative pl-6">
                <span class="absolute left-0 top-4 w-[9px] h-[9px] rounded-full ${dotCls}"></span>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-1.5 gap-2">
                        <span class="font-semibold text-gray-800 text-[13px]">${ewoodEscapeHtml(e.status)}</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            ${ewoodApprovalBadge(e.approval_status)}
                        </div>
                    </div>
                    <p class="text-gray-400 text-[10.5px] mb-2">${ewoodFormatDate(e.created_at)} · by ${ewoodEscapeHtml(e.uploaded_by_name)}</p>
                    ${e.remarks ? `<p class="text-gray-600 mb-2">${ewoodEscapeHtml(e.remarks)}</p>` : ''}
                    ${e.approval_status === 'Rejected' && e.rejection_remarks ? `<p class="text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-2">Rejection reason: ${ewoodEscapeHtml(e.rejection_remarks)}</p>` : ''}
                    ${e.archive_url ? `<a href="${e.archive_url}" target="_blank" class="inline-flex items-center gap-1 text-amber-700 hover:underline mb-2"><i class="fa-solid fa-file-zipper"></i> ${ewoodEscapeHtml(e.archive_original_name || 'Download archive')}</a>` : ''}
                    ${e.photos.length ? `<div class="flex flex-wrap gap-1.5 mt-1">${e.photos.map(p => `<a href="${p}" target="_blank"><img src="${p}" class="w-16 h-16 object-cover rounded-lg border border-gray-200"></a>`).join('')}</div>` : ''}
                    ${bomsHtml}
                </div>
            </div>
        `;
            }).join('');
        }

        function ewoodApplyPendingGate(quotationId, entries) {
            // Ignore stale responses if the user already switched rows.
            if (Number(ewoodActiveQuotationId) !== Number(quotationId)) return;

            const latest = entries[0];
            if (!latest) return;

            const isPending = latest.approval_status === 'Pending';
            const isDone = latest.approval_status === 'Approved' && latest.status === 'Completed';

            if (!isPending && !isDone) return; // leave whatever the NTP/verified gate already decided

            if (isPending && ewoodCurrentProgressionId !== null && Number(latest.id) === Number(ewoodCurrentProgressionId)) {
                return;
            }

            document.getElementById('ewoodUploadFields').classList.add('hidden');
            const blocked = document.getElementById('ewoodUploadBlocked');
            blocked.textContent = isDone
                ? 'This job has already been completed and approved. No further archive or photo upload is needed.'
                : 'You still have a submission awaiting the Superadmin\'s QR approval. Please wait for it to be approved or rejected before submitting again.';

            blocked.classList.remove(
                'text-gray-500', 'bg-gray-50', 'border-gray-200',
                'text-green-700', 'bg-green-50', 'border-green-200',
                'text-amber-700', 'bg-amber-50', 'border-amber-200'
            );
            if (isDone) {
                blocked.classList.add('text-green-700', 'bg-green-50', 'border-green-200');
            } else {
                blocked.classList.add('text-amber-700', 'bg-amber-50', 'border-amber-200');
            }

            blocked.classList.remove('hidden');
        }

        async function ewoodLoadHistory(quotationId) {
            try {
                const [historyRes, bomRes] = await Promise.all([
                    fetch(`${CUT_PROG_AJAX_URL}?action=history&quotation_id=${quotationId}`),
                    fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`)
                ]);
                const data = await historyRes.json();
                const bomData = await bomRes.json();
                if (!data.success) return;

                // Index BOMs by their linked progression_id so each history card
                // can show the BOM(s) created alongside that specific submission.
                const bomsByProgression = {};
                (bomData.success ? bomData.entries : []).forEach(b => {
                    if (b.progression_id) {
                        (bomsByProgression[b.progression_id] ||= []).push(b);
                    }
                });

                ewoodRenderHistory(data.entries, bomsByProgression);

                const countEl = document.getElementById('ewoodHistoryCount');
                if (data.entries.length) {
                    countEl.textContent = data.entries.length;
                    countEl.classList.remove('hidden');
                    countEl.classList.add('inline-flex');
                } else {
                    countEl.classList.add('hidden');
                    countEl.classList.remove('inline-flex');
                }

                const latestEl = document.getElementById('ewoodDetailLatest');
                if (latestEl) {
                    latestEl.textContent = data.entries.length
                        ? `${data.entries[0].status} · ${ewoodFormatDate(data.entries[0].created_at)}`
                        : '—';
                }
                ewoodApplyPendingGate(quotationId, data.entries);
            } catch (e) {
                console.error(e);
            }
        }

        // ---------------------------------------------------------------
        // BOM (Step 2 of the Upload & BOM form)
        // ---------------------------------------------------------------

        function ewoodBomRenderList(entries) {
            const box = document.getElementById('ewoodBomList');
            if (!entries.length) {
                box.innerHTML = `<p class="text-gray-400 text-center py-3">No Bill of Materials yet for this job.</p>`;
                return;
            }
            box.innerHTML = entries.map(e => `
                <div class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2.5 hover:bg-gray-50 transition-colors">
                    <div class="min-w-0">
                        <p class="font-mono font-semibold text-amber-700 text-[11px] truncate">${ewoodEscapeHtml(e.bom_number)}</p>
                        <p class="text-gray-400 text-[10px]">${ewoodFormatDate(e.created_at)}</p>
                    </div>
                    <button type="button" data-bom-id="${e.id}" data-bom-number="${ewoodEscapeHtml(e.bom_number)}"
                        class="ewood-bom-pdf-btn shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-eye"></i> View
                    </button>
                </div>
            `).join('');
        }

        document.getElementById('ewoodBomList').addEventListener('click', function (ev) {
            const btn = ev.target.closest('.ewood-bom-pdf-btn');
            if (!btn) return;
            ewoodBomOpenViewModal(btn.dataset.bomId);
        });

        async function ewoodBomLoadList(quotationId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`);
                const data = await res.json();
                if (!data.success) return;
                ewoodBomRenderList(data.entries);
            } catch (e) {
                console.error(e);
            }
        }

        async function ewoodBomHasUnapproved(quotationId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`);
                const data = await res.json();
                if (!data.success) return false;
                return data.entries.some(e => !e.approved_at);
            } catch (e) {
                console.error(e);
                return false;
            }
        }

        async function ewoodBomRelink(quotationId, progressionId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=relink`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ quotation_id: quotationId, progression_id: progressionId })
                });
                const data = await res.json();
                return !!data.success;
            } catch (e) {
                console.error(e);
                return false;
            }
        }

        function ewoodBomAddRow(values = {}) {
            const idx = ewoodBomRowIndex++;
            const tbody = document.getElementById('ewoodBomItemsBody');
            const tr = document.createElement('tr');
            tr.dataset.rowId = idx;
            const inputCls = 'w-full text-[11px] border border-gray-200 rounded px-1.5 py-1 outline-none focus:border-amber-600';
            tr.innerHTML = `
                <td class="px-2 py-1"><input type="text" name="item_code[]" value="${ewoodEscapeHtml(values.item_code || '')}" class="${inputCls}"></td>
                <td class="px-2 py-1"><input type="text" name="item_description[]" value="${ewoodEscapeHtml(values.item_description || '')}" class="${inputCls}"></td>
                <td class="px-2 py-1"><input type="number" step="0.01" min="0" name="quantity[]" value="${values.quantity ?? ''}" class="${inputCls}"></td>
                <td class="px-2 py-1"><input type="text" name="unit[]" value="${ewoodEscapeHtml(values.unit || '')}" class="${inputCls}"></td>
                <td class="px-2 py-1"><input type="text" name="supplier[]" value="${ewoodEscapeHtml(values.supplier || '')}" class="${inputCls}"></td>
                <td class="px-2 py-1 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-gray-300 hover:text-red-500"><i class="fa-solid fa-xmark"></i></button></td>
            `;
            tbody.appendChild(tr);
        }

        // Resets the BOM items table to a handful of blank rows — called
        // whenever a new submission is opened, and after a successful BOM create.
        function ewoodBomResetRows() {
            const tbody = document.getElementById('ewoodBomItemsBody');
            tbody.innerHTML = '';
            ewoodBomRowIndex = 0;
            for (let i = 0; i < 3; i++) ewoodBomAddRow();
        }

        // Fetches one BOM's full data (header + items) and renders it inside
        // the modal, mirroring the printed BOM layout — no PDF/iframe involved.
        async function ewoodBomOpenViewModal(bomId) {
            const modal = document.getElementById('ewoodBomViewModal');
            const body = document.getElementById('ewoodBomViewBody');
            body.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
            document.getElementById('ewoodBomViewDownload').href = `${BOM_AJAX_URL}?action=pdf&bom_id=${bomId}`;
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=view&bom_id=${bomId}`);
                const data = await res.json();
                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(data.message || 'Failed to load BOM.')}</p>`;
                    return;
                }
                ewoodBomRenderViewBody(data.bom);
            } catch (e) {
                console.error(e);
                body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">Connection error while loading.</p>`;
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
            // Match the PDF's date('d/m/Y') format exactly (day first), not
            // locale-dependent — en-PH's short date is month-first.
            const dd = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}/${dt.getFullYear()}`;
        }

        // Strips trailing zeros the same way the PDF's number_format/rtrim does,
        // so "6.00" -> "6" and "6.50" -> "6.5".
        function ewoodBomFmtQty(q) {
            const n = parseFloat(q);
            if (isNaN(n)) return '';
            return n.toFixed(2).replace(/\.?0+$/, '');
        }

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

            const sigCell = (name, title, sigPath) => `
    <td class="relative border border-gray-800 text-center align-bottom px-2 pb-1.5 w-1/4 h-24">
        ${sigPath ? `<img src="${sigPath}" alt="" class="pointer-events-none select-none absolute z-20 left-1/2 -translate-x-1/2 bottom-1 h-20 w-auto max-w-[95%] object-contain">` : ''}
        <p class="text-xs font-bold">${name ? ewoodEscapeHtml(name) : '&nbsp;'}</p>
        <p class="text-[9px] italic text-gray-600">${title ? ewoodEscapeHtml(title) : '&nbsp;'}</p>
    </td>`;

            const requestedSigUrl = bom.requested_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.requested_by_signature_path)}` : null;
            const approvedSigUrl = bom.approved_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.approved_by_signature_path)}` : null;
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

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') ewoodBomCloseViewModal();
        });

        // ── Selection / tabs ─────────────────────────
        function ewoodSetRowHighlight(quotationId, active) {
            const row = document.getElementById(`ewoodRow_${quotationId}`);
            if (!row) return;
            row.classList.toggle('is-active', active);
        }

        // Switches the detail view between the Details / Upload & BOM / History tabs.
        function ewoodSwitchTab(tab) {
            const panels = {
                details: document.getElementById('ewoodPanelDetails'),
                upload: document.getElementById('ewoodUploadForm'),
                history: document.getElementById('ewoodPanelHistory'),
            };
            Object.entries(panels).forEach(([key, el]) => el.classList.toggle('hidden', key !== tab));

            document.querySelectorAll('.ewood-tab-btn').forEach(btn => {
                const active = btn.dataset.tab === tab;
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('text-amber-700', active);
                btn.classList.toggle('border-transparent', !active);
                btn.classList.toggle('text-gray-400', !active);
            });

            document.getElementById('ewoodDetailBody').scrollTop = 0;
        }

        document.getElementById('ewoodHistory').addEventListener('click', function (ev) {
            const btn = ev.target.closest('.ewood-history-bom-btn');
            if (!btn) return;
            ewoodBomOpenViewModal(btn.dataset.bomId);
        });

        // Mobile: go back from the detail view to the list.
        function ewoodBackToList() {
            document.getElementById('ewoodMain').dataset.view = 'list';
        }

        function ewoodResetUploadStepState() {
            ewoodCurrentProgressionId = null;

            document.querySelectorAll('#ewoodUploadFields input[name="archive"], #ewoodUploadFields input[name="images[]"], #ewoodUploadFields textarea[name="remarks"]')
                .forEach(el => { el.disabled = false; el.value = ''; });

            const saveBtn = document.getElementById('ewoodSaveProgressBtn');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Progress';

            document.querySelectorAll('#ewoodBomSection input').forEach(el => { el.disabled = false; });
            const createBomBtn = document.getElementById('ewoodCreateBomBtn');
            if (createBomBtn) {
                createBomBtn.disabled = false;
                createBomBtn.textContent = 'Create BOM';
            }

            document.getElementById('ewoodBomSection').classList.add('hidden');
            document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
        }

        // Opens the detail view for a given row.
        function ewoodOpenSidebar(quotationId) {
            const row = ewoodRowsMap[quotationId];
            if (!row) return;

            if (ewoodActiveQuotationId !== null && ewoodActiveQuotationId !== quotationId) {
                ewoodSetRowHighlight(ewoodActiveQuotationId, false);
            }
            ewoodActiveQuotationId = quotationId;
            ewoodSetRowHighlight(quotationId, true);

            const isNtp = row.deposit_status === 'Notice to Proceed';
            const isVerified = !!row.design_2d_verified;
            const canUpload = isNtp && isVerified;

            // Header
            document.getElementById('ewoodModalControl').textContent = row.control_no;
            document.getElementById('ewoodModalTitle').textContent = row.client_name;
            document.getElementById('ewoodHeaderChips').innerHTML = `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-50 text-gray-600">
                    <i class="fa-solid fa-location-dot text-gray-400 text-[10px]"></i>${ewoodEscapeHtml(row.branch)}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-50 text-gray-600 capitalize">
                    <i class="fa-solid fa-house text-gray-400 text-[10px]"></i>${ewoodEscapeHtml(row.project_type ?? '—')}
                </span>`;

            // Details tab
            document.getElementById('ewoodDetailNtp').innerHTML = ewoodNtpBadge(row);
            document.getElementById('ewoodDetailVerified').innerHTML = ewoodVerifiedBadge(row);
            document.getElementById('ewoodDetailAccess').innerHTML = ewoodAccessBadge(canUpload);
            document.getElementById('ewoodDetailLatest').textContent = '—';

            const countEl = document.getElementById('ewoodHistoryCount');
            countEl.classList.add('hidden');
            countEl.classList.remove('inline-flex');

            const notice = document.getElementById('ewoodDetailNotice');
            if (!canUpload) {
                notice.textContent = !isNtp
                    ? 'Waiting for Notice to Proceed from Accounting.'
                    : 'Waiting for the 2D file to be verified (Cutting List → Verify 2D).';
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }

            // Reset the two-step Upload & BOM state for this row.
            document.getElementById('ewoodUploadForm').reset();
            document.getElementById('ewoodQuotationId').value = quotationId;
            ewoodResetUploadStepState();

            const uploadFields = document.getElementById('ewoodUploadFields');
            const uploadBlocked = document.getElementById('ewoodUploadBlocked');
            uploadBlocked.classList.remove(
                'text-green-700', 'bg-green-50', 'border-green-200',
                'text-amber-700', 'bg-amber-50', 'border-amber-200'
            );
            uploadBlocked.classList.add('text-gray-500', 'bg-gray-50', 'border-gray-200');
            if (canUpload) {
                uploadFields.classList.remove('hidden');
                uploadBlocked.classList.add('hidden');
            } else {
                uploadFields.classList.add('hidden');
                uploadBlocked.textContent = !isNtp
                    ? 'Uploading is disabled until this submission has Notice to Proceed.'
                    : 'Uploading is disabled until the 2D file has been verified.';
                uploadBlocked.classList.remove('hidden');
            }

            // BOM items — reset to blank rows and reload the reference list
            // of previously submitted BOMs for this job.
            ewoodBomResetRows();
            document.getElementById('ewoodBomList').innerHTML = '';
            ewoodBomLoadList(quotationId);

            ewoodSwitchTab('details');

            // Show the detail view; on mobile this also hides the list.
            document.getElementById('ewoodEmptyState').classList.add('hidden');
            document.getElementById('ewoodDetailView').classList.remove('hidden');
            document.getElementById('ewoodDetailView').classList.add('flex');
            document.getElementById('ewoodMain').dataset.view = 'detail';

            ewoodLoadHistory(quotationId);
        }

        // ---------------------------------------------------------------
        // Step 1: Save Progress (archive / images / remarks)
        // ---------------------------------------------------------------
        document.getElementById('ewoodSaveProgressBtn').addEventListener('click', async function () {
            const quotationId = document.getElementById('ewoodQuotationId').value;
            const btn = this;

            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const formData = new FormData(document.getElementById('ewoodUploadForm'));

                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=upload`, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Save Progress';
                    return;
                }

                ewoodCurrentProgressionId = data.id;
                crmShowToast('Progress saved.', 'success');

                // Lock the progress inputs so a second archive/image set can't be
                // attached to the same click-through.
                document.querySelectorAll('#ewoodUploadFields input[name="archive"], #ewoodUploadFields input[name="images[]"], #ewoodUploadFields textarea[name="remarks"]')
                    .forEach(el => el.disabled = true);
                btn.textContent = 'Progress Saved';
                // Keep btn disabled — stays disabled once progress is saved for this row.

                const relinked = await ewoodBomRelink(quotationId, ewoodCurrentProgressionId);

                if (relinked) {
                    crmShowToast('Existing BOM found for this job — linked automatically to this submission.', 'success');
                    document.getElementById('ewoodBomSection').classList.add('hidden');
                    document.getElementById('ewoodBomRelinkedNotice').classList.remove('hidden');
                    ewoodBomLoadList(quotationId);
                } else {
                    document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
                    document.getElementById('ewoodBomSection').classList.remove('hidden');
                    crmShowToast('You can now create a BOM for this submission.', 'success');
                }

                ewoodLoadHistory(quotationId);
            } catch (e) {
                console.error(e);
                crmShowToast('Connection error while saving progress.', 'error');
                btn.disabled = false;
                btn.textContent = 'Save Progress';
            }
        });

        // ---------------------------------------------------------------
        // Step 2: Create BOM (only enabled after Step 1 returns a progression_id,
        // and only shown when no existing unapproved BOM could be auto-relinked)
        // ---------------------------------------------------------------
        document.getElementById('ewoodCreateBomBtn').addEventListener('click', async function () {
            const quotationId = document.getElementById('ewoodQuotationId').value;
            const btn = this;

            if (!ewoodCurrentProgressionId) {
                crmShowToast('Save the progress upload first before creating a BOM.', 'error');
                return;
            }

            const descInputs = document.querySelectorAll('input[name="item_description[]"]');
            const hasItem = Array.from(descInputs).some(i => i.value.trim() !== '');
            if (!hasItem) {
                crmShowToast('Add at least one BOM item before submitting.', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const formData = new FormData(document.getElementById('ewoodUploadForm'));
                formData.append('progression_id', ewoodCurrentProgressionId);

                const res = await fetch(`${BOM_AJAX_URL}?action=create`, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(`BOM failed: ${data.message}`, 'error');

                    btn.disabled = false;
                    btn.textContent = 'Create BOM';
                    return;
                }

                crmShowToast('BOM created successfully.', 'success');

                document.querySelectorAll('#ewoodBomSection input').forEach(el => { el.disabled = true; });
                btn.disabled = true;
                btn.textContent = 'BOM Created';

                ewoodBomLoadList(quotationId);

                ewoodCurrentProgressionId = null;
                document.getElementById('ewoodUploadFields').classList.add('hidden');
                {
                    const blocked = document.getElementById('ewoodUploadBlocked');
                    blocked.textContent = 'You still have a submission awaiting the Superadmin\'s QR approval. Please wait for it to be approved or rejected before submitting again.';
                    blocked.classList.remove(
                        'text-gray-500', 'bg-gray-50', 'border-gray-200',
                        'text-green-700', 'bg-green-50', 'border-green-200'
                    );
                    blocked.classList.add('text-amber-700', 'bg-amber-50', 'border-amber-200');
                    blocked.classList.remove('hidden');
                }

                ewoodLoadHistory(quotationId);

                ewoodSwitchTab('history');
            } catch (e) {
                console.error(e);
                crmShowToast('Connection error while creating BOM.', 'error');
                // Connection failure: safe to let them retry.
                btn.disabled = false;
                btn.textContent = 'Create BOM';
            }
            // NOTE: no blanket `finally` re-enabling the button — the
            // success path intentionally leaves it disabled/relabeled.
        });

        ewoodFetchList();
    </script>
</body>

</html>