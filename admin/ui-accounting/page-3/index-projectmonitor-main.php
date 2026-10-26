<?php
// index-projectmonitor-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_HEAD, POSITION_STAFF];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$canAddEntry = ($_SESSION['position'] ?? '') !== POSITION_STAFF;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Monitoring</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .pmm-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .pmm-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
        .pmm-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }

        .pmm-input { width: 100%; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .5rem .75rem; font-size: .875rem;
                     color: #1f2937; background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
        .pmm-input:focus { border-color: #d97706; box-shadow: 0 0 0 3px #fef3c7; }
        .pmm-input[aria-invalid="true"] { border-color: #f87171; box-shadow: 0 0 0 3px #fee2e2; }
        .pmm-money { padding-left: 1.75rem !important; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .pmm-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: .375rem; }

        .pmm-tab { background: #fff; border-color: #e5e7eb; color: #4b5563; }
        .pmm-tab:hover { border-color: #f59e0b; }
        .pmm-tab[data-active="1"] { background: #b45309; border-color: #b45309; color: #fff; }
        .pmm-tab[data-active="1"] .pmm-count { background: rgba(255,255,255,.2); color: #fff; }

        .pmm-mode { border: 1px solid #e5e7eb; background: #fff; color: #4b5563; }
        .pmm-mode:hover { border-color: #d1d5db; background: #f9fafb; }
        .pmm-mode[data-active="1"][data-mode="billing"] { border-color: #16a34a; background: #f0fdf4; color: #166534; box-shadow: 0 0 0 1px #16a34a inset; }
        .pmm-mode[data-active="1"][data-mode="expense"] { border-color: #dc2626; background: #fef2f2; color: #991b1b; box-shadow: 0 0 0 1px #dc2626 inset; }
        .pmm-mode[data-active="1"][data-mode="hybrid"]  { border-color: #b45309; background: #fffbeb; color: #92400e; box-shadow: 0 0 0 1px #b45309 inset; }
    </style>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-helmet-safety text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Project Monitoring</h1>
                    <p class="text-sm text-gray-500">Accounting report — collections and expenses per project</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($canAddEntry): ?>
                    <button type="button" onclick="openEntryModal()"
                        class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-amber-500 hover:text-amber-700 text-gray-700 text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-sm">
                        <i class="fa-solid fa-file-invoice-dollar text-xs"></i> Add Entry
                    </button>
                <?php endif; ?>
                <button type="button" onclick="openAddModal()"
                    class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-sm">
                    <i class="fa-solid fa-plus text-xs"></i> New Project
                </button>
            </div>
        </div>

        <!-- ═══ SUMMARY (sumusunod sa filter) ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <?php foreach ([
                ['s-count', 'Projects', 'fa-helmet-safety', 'bg-amber-50 text-amber-700', ''],
                ['s-contract', 'Contract value', 'fa-file-signature', 'bg-blue-50 text-blue-600', ''],
                ['s-collected', 'Collected', 'fa-hand-holding-dollar', 'bg-green-50 text-green-600', 's-collected-sub'],
                ['s-balance', 'Uncollected', 'fa-hourglass-half', 'bg-orange-50 text-orange-600', 's-balance-sub'],
            ] as $i => [$id, $label, $icon, $tone, $sub]): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                    <div class="hidden 2xl:flex w-10 h-10 rounded-lg <?= $tone ?> items-center justify-center shrink-0"><i class="fa-solid <?= $icon ?>"></i></div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide"><?= $label ?></p>
                        <p id="<?= $id ?>" class="font-bold text-gray-800 leading-none mt-1 whitespace-nowrap <?= $i ? 'font-mono text-[15px] xl:text-base' : 'text-2xl' ?>">—</p>
                        <?php if ($sub): ?><p id="<?= $sub ?>" class="text-[11px] text-gray-400 mt-1 truncate"></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5 px-4 py-3 border-b border-gray-100">
                <div id="pmm-tabs" class="flex items-center gap-1.5 flex-wrap">
                    <?php foreach (['all' => 'All', 'New' => 'New', 'Ongoing' => 'Ongoing', 'Completed' => 'Completed', 'On Hold' => 'On Hold', 'Cancelled' => 'Cancelled'] as $val => $label): ?>
                        <button type="button" data-filter="<?= $val ?>" data-active="<?= $val === 'all' ? 1 : 0 ?>"
                            class="pmm-tab inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border transition">
                            <?= $label ?> <span class="pmm-count text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5" data-count="<?= $val ?>">0</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="flex items-center gap-2 flex-wrap xl:flex-nowrap">
                    <div class="relative flex-1 min-w-0 xl:w-60">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="search-input" placeholder="Search project, client, ref no., sales…"
                            class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <select id="pmm-sort" class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white">
                        <option value="">Sort: newest</option>
                        <option value="name">Project name A–Z</option>
                        <option value="contract">Contract: highest</option>
                        <option value="balance">Uncollected: highest</option>
                        <option value="pct">% collected: lowest</option>
                    </select>
                </div>
            </div>
            <div id="pmm-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-2">
                <span id="pmm-info-text">&nbsp;</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span><span id="pmm-updated">Live</span></span>
            </div>

            <div id="projects-cards" class="pmm-scroll max-h-[calc(100vh-24rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
                <div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
            </div>
        </div>
    </main>

    <!-- ═══ NEW / EDIT PROJECT MODAL ═══ -->
    <div id="add-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4 py-6">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div id="pj-icon" class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-plus"></i></div>
                    <div class="min-w-0">
                        <h3 id="pj-title" class="font-bold text-base text-gray-800">New Project</h3>
                        <p class="text-[11px] text-gray-500">Fields with <span class="text-red-500">*</span> are required</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddModal()" title="Close" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="pj-form" class="pmm-scroll px-5 py-5 overflow-y-auto" novalidate onsubmit="event.preventDefault(); saveProject();">
                <div id="pj-draft-note" class="hidden mb-4 flex items-center justify-between gap-2 text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-3 py-2">
                    <span><i class="fa-solid fa-clock-rotate-left mr-1"></i> Restored your unsaved draft.</span>
                    <button type="button" onclick="discardProjectDraft()" class="font-semibold hover:underline">Start fresh</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="pmm-label" for="f-project-name">Project name <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select id="f-project-name" class="pmm-input"><option value="">— Select —</option></select>
                            <button type="button" onclick="openProjectNameManager()" title="Manage project names"
                                class="shrink-0 w-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-amber-50 hover:border-amber-400 text-gray-500 hover:text-amber-700 transition">
                                <i class="fa-solid fa-gear text-xs"></i>
                            </button>
                        </div>
                        <p id="err-project-name" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>
                    <div><label class="pmm-label" for="f-client-name">Client name</label><input id="f-client-name" type="text" class="pmm-input"></div>
                    <div><label class="pmm-label" for="f-sales-person">Sales person</label><input id="f-sales-person" type="text" class="pmm-input" list="pmm-sales-list"><datalist id="pmm-sales-list"></datalist></div>
                    <div><label class="pmm-label" for="f-project-scope">Project scope</label><input id="f-project-scope" type="text" class="pmm-input"></div>
                    <div><label class="pmm-label" for="f-job-order">Job order</label><input id="f-job-order" type="text" class="pmm-input font-mono"></div>
                    <div><label class="pmm-label" for="f-purchase-order">Purchase order</label><input id="f-purchase-order" type="text" class="pmm-input font-mono"></div>
                    <div><label class="pmm-label" for="f-notice-to-proceed">Notice to proceed</label><input id="f-notice-to-proceed" type="text" placeholder="e.g. 2026-10-01 or NTP no." class="pmm-input"></div>
                    <div>
                        <label class="pmm-label" for="f-contract-amount">Contract amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input id="f-contract-amount" type="number" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pmm-input pmm-money">
                        </div>
                        <p id="err-contract-amount" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>
                    <div>
                        <label class="pmm-label" for="f-status">Status</label>
                        <select id="f-status" class="pmm-input">
                            <option value="">— Select —</option>
                            <option>New</option><option>Ongoing</option><option>Completed</option><option>On Hold</option><option>Cancelled</option>
                        </select>
                    </div>
                    <div><label class="pmm-label" for="f-billing-order-1">(1) Billing order #</label><input id="f-billing-order-1" type="text" class="pmm-input font-mono"></div>
                    <div><label class="pmm-label" for="f-billing-order-2">(2) Billing order #</label><input id="f-billing-order-2" type="text" class="pmm-input font-mono"></div>
                    <div class="sm:col-span-2"><label class="pmm-label" for="f-address">Address</label><input id="f-address" type="text" class="pmm-input"></div>
                </div>
                <div id="pj-error" class="hidden mt-4 text-xs bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2"></div>
                <button type="submit" class="hidden"></button>
            </form>
            <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
                <button type="button" onclick="closeAddModal()" class="text-sm text-gray-600 hover:bg-gray-100 font-medium px-4 py-2 rounded-lg border border-gray-200 bg-white transition">Cancel</button>
                <button type="button" id="pj-save-btn" onclick="saveProject()"
                    class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> <span>Save Project</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ ADD ENTRY MODAL ═══ -->
    <div id="entry-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-3 sm:px-4 py-6">
        <div id="entry-modal-box" class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col transition-all">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-base text-gray-800">Add Entry</h3>
                        <p class="text-[11px] text-gray-500">Pick a project, choose what to record, then fill in the details.</p>
                    </div>
                </div>
                <button type="button" onclick="closeEntryModal()" title="Close" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="entry-form" class="pmm-scroll px-5 py-5 overflow-y-auto space-y-5" novalidate onsubmit="event.preventDefault(); saveEntry();">
                <div id="en-draft-note" class="hidden flex items-center justify-between gap-2 text-xs bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-3 py-2">
                    <span><i class="fa-solid fa-clock-rotate-left mr-1"></i> Restored your unsaved entry.</span>
                    <button type="button" onclick="discardEntryDraft()" class="font-semibold hover:underline">Start fresh</button>
                </div>

                <!-- Step 1 -->
                <div>
                    <label class="pmm-label" for="entry-project-id"><span class="inline-flex items-center justify-center w-5 h-5 bg-amber-600 text-white rounded-full text-[10px] mr-1">1</span> Project <span class="text-red-500">*</span></label>
                    <select id="entry-project-id" class="pmm-input"><option value="">— Choose a project —</option></select>
                    <p id="entry-project-info" class="hidden text-[11px] text-gray-500 mt-1.5"></p>
                    <p id="err-entry-project" class="hidden text-[11px] text-red-600 mt-1"></p>
                </div>

                <!-- Step 2 -->
                <div>
                    <label class="pmm-label"><span class="inline-flex items-center justify-center w-5 h-5 bg-amber-600 text-white rounded-full text-[10px] mr-1">2</span> What are you recording? <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" data-mode="billing" onclick="setMode('billing')" class="pmm-mode rounded-xl px-2 py-3 flex flex-col items-center gap-1 text-center transition">
                            <i class="fa-solid fa-hand-holding-dollar text-lg"></i>
                            <span class="text-xs font-bold">Collection</span>
                            <span class="hidden sm:block text-[10px] opacity-75">Payment from client</span>
                        </button>
                        <button type="button" data-mode="expense" onclick="setMode('expense')" class="pmm-mode rounded-xl px-2 py-3 flex flex-col items-center gap-1 text-center transition">
                            <i class="fa-solid fa-receipt text-lg"></i>
                            <span class="text-xs font-bold">Expense</span>
                            <span class="hidden sm:block text-[10px] opacity-75">Cost paid out</span>
                        </button>
                        <button type="button" data-mode="hybrid" onclick="setMode('hybrid')" class="pmm-mode rounded-xl px-2 py-3 flex flex-col items-center gap-1 text-center transition">
                            <i class="fa-solid fa-layer-group text-lg"></i>
                            <span class="text-xs font-bold">Both</span>
                            <span class="hidden sm:block text-[10px] opacity-75">Collection + expense</span>
                        </button>
                    </div>
                    <p id="err-entry-mode" class="hidden text-[11px] text-red-600 mt-1"></p>
                </div>

                <!-- Step 3 -->
                <div id="entry-fields" class="hidden">
                    <div id="hybrid-grid" class="grid grid-cols-1 gap-5">
                        <!-- Collection -->
                        <section id="billing-fields" class="hidden rounded-xl border border-green-200 bg-green-50/40 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-green-800 mb-3"><i class="fa-solid fa-hand-holding-dollar mr-1"></i> Billed &amp; paid by client</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="pmm-label" for="b-particulars">Particulars <span class="text-red-500">*</span></label>
                                    <input id="b-particulars" type="text" class="pmm-input" placeholder="e.g. Progress billing #1">
                                    <p id="err-b-particulars" class="hidden text-[11px] text-red-600 mt-1"></p>
                                </div>
                                <div>
                                    <label class="pmm-label" for="b-amount">Amount <span class="text-red-500">*</span></label>
                                    <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                                        <input id="b-amount" type="text" inputmode="decimal" class="pmm-input pmm-money" placeholder="0.00" oninput="formatAmountInput(this)"></div>
                                    <p id="err-b-amount" class="hidden text-[11px] text-red-600 mt-1"></p>
                                </div>
                                <div><label class="pmm-label" for="b-payment-date">Payment date</label><input id="b-payment-date" type="date" class="pmm-input"></div>
                                <div><label class="pmm-label" for="b-bank-check">Bank / check</label><input id="b-bank-check" type="text" class="pmm-input" placeholder="e.g. BDO Check #123"></div>
                                <div><label class="pmm-label" for="b-reference">Reference</label><input id="b-reference" type="text" class="pmm-input font-mono" placeholder="Reference no."></div>
                                <div class="sm:col-span-2"><label class="pmm-label" for="b-remarks">Remarks</label><input id="b-remarks" type="text" class="pmm-input" placeholder="Optional notes"></div>
                            </div>
                        </section>

                        <!-- Expense -->
                        <section id="expense-fields" class="hidden rounded-xl border border-red-200 bg-red-50/40 p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-red-800 mb-3"><i class="fa-solid fa-receipt mr-1"></i> Costs / expenses</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><label class="pmm-label" for="e-title">Title</label><input id="e-title" type="text" class="pmm-input" placeholder="e.g. Labor, Materials" list="pmm-title-list">
                                    <datalist id="pmm-title-list"><option value="Materials"><option value="Labor"><option value="Transportation"><option value="Equipment"><option value="Permits"></datalist></div>
                                <div>
                                    <label class="pmm-label" for="e-mode">Mode of payment</label>
                                    <select id="e-mode" class="pmm-input"><option value="">— Select —</option><option>Cash</option><option>Check</option><option>Bank Transfer</option><option>GCash</option><option>Other</option></select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="pmm-label" for="e-particulars">Particulars <span class="text-red-500">*</span></label>
                                    <input id="e-particulars" type="text" class="pmm-input" placeholder="e.g. Purchase of cement bags">
                                    <p id="err-e-particulars" class="hidden text-[11px] text-red-600 mt-1"></p>
                                </div>
                                <div>
                                    <label class="pmm-label" for="e-amount">Amount <span class="text-red-500">*</span></label>
                                    <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                                        <input id="e-amount" type="text" inputmode="decimal" class="pmm-input pmm-money" placeholder="0.00" oninput="formatAmountInput(this)"></div>
                                    <p id="err-e-amount" class="hidden text-[11px] text-red-600 mt-1"></p>
                                </div>
                                <div><label class="pmm-label" for="e-payment-date">Payment date</label><input id="e-payment-date" type="date" class="pmm-input"></div>
                                <div><label class="pmm-label" for="e-reference">Reference</label><input id="e-reference" type="text" class="pmm-input font-mono" placeholder="Reference no."></div>
                                <div><label class="pmm-label" for="e-remarks">Remarks</label><input id="e-remarks" type="text" class="pmm-input" placeholder="Optional notes"></div>
                            </div>
                        </section>
                    </div>
                </div>
                <div id="entry-error" class="hidden text-xs bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2"></div>
                <button type="submit" class="hidden"></button>
            </form>

            <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
                <p id="entry-mode-hint" class="text-[11px] text-gray-500 hidden sm:block">Choose what to record to continue.</p>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" onclick="closeEntryModal()" class="text-sm text-gray-600 hover:bg-gray-100 font-medium px-4 py-2 rounded-lg border border-gray-200 bg-white transition">Cancel</button>
                    <button type="button" id="entry-save-btn" onclick="saveEntry()"
                        class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                        <i class="fa-solid fa-floppy-disk text-xs"></i> <span>Save Entry</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ PROJECT NAME MANAGER ═══ -->
    <div id="projectname-manager-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-gear"></i></div>
                    <h3 class="font-bold text-base text-gray-800">Manage Project Names</h3>
                </div>
                <button type="button" onclick="closeProjectNameManager()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="px-4 py-3 border-b border-gray-100 flex gap-2 shrink-0" onsubmit="event.preventDefault(); addNewProjectName();">
                <input type="text" id="new-projectname-input" placeholder="New project name..." maxlength="150" class="pmm-input uppercase">
                <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 rounded-lg transition">
                    <i class="fa-solid fa-plus text-[10px]"></i> Add
                </button>
            </form>
            <p id="pn-error" class="hidden mx-4 mt-2 text-xs text-red-600"></p>
            <div id="projectname-list" class="pmm-scroll overflow-y-auto flex-1 px-2 py-2 min-h-[120px]"></div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 text-right shrink-0">
                <button type="button" onclick="closeProjectNameManager()" class="text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg transition">Done</button>
            </div>
        </div>
    </div>

    <script>
        const API = '<?= BASE_URL ?>';
        const CAN_ENTRY = <?= $canAddEntry ? 'true' : 'false' ?>;
        let allProjects = [];
        let lastSig = '';
        let activeFilter = 'all';
        let currentMode = null;     // 'billing' | 'expense' | 'hybrid'
        let savingProject = false, savingEntry = false;

        // ─── Utils ───────────────────────────────────────────
        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const num = v => { const n = parseFloat(String(v ?? '').replace(/,/g, '')); return Number.isFinite(n) ? n : 0; };
        const peso = v => '₱ ' + Math.abs(num(v)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const todayPH = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
        function fmtDay(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        const STATUS = {
            'New': ['bg-purple-100 text-purple-800', 'bg-purple-400'],
            'Ongoing': ['bg-blue-100 text-blue-800', 'bg-blue-500'],
            'Completed': ['bg-green-100 text-green-800', 'bg-green-500'],
            'On Hold': ['bg-yellow-100 text-yellow-800', 'bg-yellow-400'],
            'Cancelled': ['bg-red-100 text-red-700', 'bg-red-400'],
        };
        const stBadge = s => (STATUS[s] || ['bg-gray-100 text-gray-600'])[0];
        const stBar = s => (STATUS[s] || [, 'bg-gray-300'])[1];

        async function readJson(res) {
            const text = await res.text();
            try { return JSON.parse(text.replace(/^﻿/, '').trim()); }
            catch (e) {
                console.error('Not JSON:', res.status, res.url, text);
                const isPage = /<!doctype html|<html[\s>]/i.test(text);
                throw Object.assign(new Error(isPage
                    ? 'The server returned a page instead of data (route missing from $adminRoutes, or your session expired — reload the page).'
                    : `Server error (HTTP ${res.status}).`), { server: true });
            }
        }
        const getJson = url => fetch(url, { cache: 'no-store' }).then(readJson);
        const postJson = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(readJson);
        const errMsg = (e, fallback) => e?.server ? e.message : fallback;

        function showToast(msg, type = 'success') {
            const t = document.createElement('div');
            t.className = `fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[999] ${type === 'error' ? 'bg-red-500' : 'bg-green-600'} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 opacity-0 transition-all duration-300`;
            t.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i> ${escHtml(msg)}`;
            document.body.appendChild(t);
            requestAnimationFrame(() => t.classList.remove('opacity-0'));
            setTimeout(() => { t.classList.add('opacity-0'); setTimeout(() => t.remove(), 300); }, 3500);
        }

        // Inline errors
        function setErr(id, msg, inputId) {
            const el = document.getElementById(id);
            if (el) { el.textContent = msg || ''; el.classList.toggle('hidden', !msg); }
            if (inputId) document.getElementById(inputId)?.toggleAttribute('aria-invalid', !!msg);
        }
        function clearErrs(formId, boxId) {
            document.querySelectorAll(`#${formId} [id^="err-"]`).forEach(el => el.classList.add('hidden'));
            document.querySelectorAll(`#${formId} [aria-invalid]`).forEach(el => el.removeAttribute('aria-invalid'));
            boxMsg(boxId, '');
        }
        function boxMsg(id, msg) {
            const el = document.getElementById(id);
            el.innerHTML = msg ? `<i class="fa-solid fa-circle-exclamation mr-1"></i>${escHtml(msg)}` : '';
            el.classList.toggle('hidden', !msg);
        }

        // Drafts (localStorage; kung hindi available, tahimik lang)
        const store = {
            get: k => { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
            set: (k, v) => { try { v ? localStorage.setItem(k, JSON.stringify(v)) : localStorage.removeItem(k); } catch (e) { } },
        };

        // ─── Projects list ───────────────────────────────────
        function metrics(p) {
            const contract = num(p.contract_amount), credited = num(p.total_credited);
            const pct = contract > 0 ? Math.min(credited / contract * 100, 100) : 0;
            return { contract, credited, balance: contract - credited, pct };
        }

        function fetchProjects() {
            return getJson(`${API}/fetchprojects`).then(data => {
                if (!Array.isArray(data)) return;
                const sig = JSON.stringify(data);
                document.getElementById('pmm-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                if (sig === lastSig) return;
                lastSig = sig;
                allProjects = data;
                applyFilters();
                populateProjectDropdown();
                const sales = [...new Set(data.map(p => (p.sales_person || '').trim()).filter(Boolean))].sort();
                document.getElementById('pmm-sales-list').innerHTML = sales.map(s => `<option value="${escHtml(s)}">`).join('');
            }).catch(e => {
                if (!allProjects.length) document.getElementById('projects-cards').innerHTML = emptyHtml('Could not load projects.', errMsg(e, 'Check your connection, then reload.'), true);
                document.getElementById('pmm-updated').textContent = 'Connection problem';
            });
        }

        function emptyHtml(title, sub = '', isError = false) {
            return `<div class="px-4 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full ${isError ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-400'} flex items-center justify-center mb-2">
                    <i class="fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-helmet-safety'}"></i></div>
                <p class="text-sm font-semibold text-gray-600">${escHtml(title)}</p>
                ${sub ? `<p class="text-xs text-gray-400 mt-1">${escHtml(sub)}</p>` : ''}
            </div>`;
        }

        function applyFilters() {
            const q = document.getElementById('search-input').value.trim().toLowerCase();
            const sort = document.getElementById('pmm-sort').value;
            const matchQ = p => !q || [p.project_name, p.client_name, p.sales_person, p.reference_no, p.job_order, p.status]
                .some(v => v && String(v).toLowerCase().includes(q));
            const counts = { all: 0 };
            allProjects.filter(matchQ).forEach(p => { counts.all++; counts[p.status || ''] = (counts[p.status || ''] || 0) + 1; });
            document.querySelectorAll('#pmm-tabs [data-count]').forEach(el => el.textContent = counts[el.dataset.count] || 0);

            let rows = allProjects.filter(p => (activeFilter === 'all' || (p.status || '') === activeFilter) && matchQ(p));
            if (sort) {
                const val = p => { const m = metrics(p); return sort === 'name' ? String(p.project_name || '').toLowerCase() : sort === 'contract' ? -m.contract : sort === 'balance' ? -m.balance : m.pct; };
                rows = [...rows].sort((a, b) => { const x = val(a), y = val(b); return typeof x === 'string' ? x.localeCompare(y) : x - y; });
            }
            renderProjects(rows, !!(q || activeFilter !== 'all'));
            renderSummary(rows);
        }

        function renderSummary(rows) {
            let contract = 0, credited = 0;
            rows.forEach(p => { const m = metrics(p); contract += m.contract; credited += m.credited; });
            document.getElementById('s-count').textContent = rows.length;
            document.getElementById('s-contract').textContent = peso(contract);
            document.getElementById('s-collected').textContent = peso(credited);
            document.getElementById('s-collected-sub').textContent = contract ? Math.round(credited / contract * 100) + '% of contract value' : '';
            document.getElementById('s-balance').textContent = peso(Math.max(0, contract - credited));
            const open = rows.filter(p => metrics(p).balance > 0).length;
            document.getElementById('s-balance-sub').textContent = open + ' project' + (open === 1 ? '' : 's') + ' with balance';
        }

        function renderProjects(rows, filtered) {
            const box = document.getElementById('projects-cards');
            document.getElementById('pmm-info-text').innerHTML = `Showing <b class="text-gray-700">${rows.length}</b> of ${allProjects.length} project${allProjects.length === 1 ? '' : 's'}`;
            if (!rows.length) {
                box.innerHTML = filtered ? emptyHtml('No projects match this filter.') : emptyHtml('No projects yet.', 'Click "New Project" to add one.');
                return;
            }
            box.innerHTML = rows.map(p => {
                const m = metrics(p);
                const id = Number(p.id);
                const pctCls = m.pct >= 100 ? 'bg-green-600' : m.pct >= 75 ? 'bg-green-500' : m.pct >= 40 ? 'bg-amber-500' : 'bg-red-400';
                const pctTxt = m.pct >= 100 ? 'text-green-700' : m.pct >= 75 ? 'text-green-600' : m.pct >= 40 ? 'text-amber-700' : 'text-red-600';
                return `
                <div data-id="${id}" class="pmm-row group flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors cursor-pointer"
                    onclick="openDetail(${id})" tabindex="0" onkeydown="if(event.key==='Enter') openDetail(${id})">
                    <div class="w-1 rounded-full shrink-0 ${stBar(p.status)}"></div>
                    <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                ${p.reference_no ? `<span class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">${escHtml(p.reference_no)}</span>` : ''}
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ${stBadge(p.status)}">${escHtml(p.status || 'No status')}</span>
                                <span class="text-[11px] text-gray-400"><i class="fa-regular fa-calendar mr-1"></i>${escHtml(fmtDay(p.created_at))}</span>
                            </div>
                            <p class="mt-1 text-sm font-bold text-gray-800 truncate">${escHtml(p.project_name || '—')}</p>
                            <p class="text-[12px] text-gray-500 truncate">${escHtml(p.client_name || 'No client')}${p.sales_person ? ' · <span class="text-gray-400">Sales:</span> ' + escHtml(p.sales_person) : ''}</p>
                        </div>
                        <div class="lg:w-[340px] shrink-0">
                            ${m.contract > 0 ? `
                                <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                    <span class="text-gray-500">Contract <b class="font-mono text-gray-800">${peso(m.contract)}</b></span>
                                    <span class="font-bold ${pctTxt}">${m.pct.toFixed(1)}%</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full ${pctCls} transition-all duration-500" style="width:${m.pct}%"></div></div>
                                <div class="mt-1 flex items-center justify-between gap-2 text-[11px]">
                                    <span class="text-green-700"><span class="text-gray-400">Collected</span> <span class="font-mono">${peso(m.credited)}</span></span>
                                    <span class="${m.balance > 0 ? 'text-amber-800' : 'text-gray-500'}"><span class="text-gray-400">Balance</span> <span class="font-mono">${peso(Math.max(0, m.balance))}</span></span>
                                </div>` : '<p class="text-[11px] text-gray-400 italic">No contract amount set</p>'}
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">
                            ${CAN_ENTRY ? `<button type="button" onclick="openEntryModal(${id})" title="Add entry for this project"
                                class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-gray-200 bg-white hover:border-amber-500 hover:text-amber-700 text-gray-600 text-xs font-semibold transition">
                                <i class="fa-solid fa-plus text-[10px]"></i> Entry</button>` : ''}
                            <button type="button" onclick="openAddModal(${id})" title="Edit project"
                                class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-gray-200 bg-white hover:border-amber-500 hover:text-amber-700 text-gray-600 text-xs font-semibold transition">
                                <i class="fa-solid fa-pen text-[10px]"></i> Edit</button>
                            <button type="button" onclick="openDetail(${id})" title="Open details"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i></button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function openDetail(id) { window.location = `${API}/projectdetail?id=${encodeURIComponent(id)}`; }

        // ─── Project name dropdown + manager ─────────────────
        let projectNames = [];
        function loadProjectNames(selected = '') {
            return getJson(`${API}/fetchprojectnames`).then(names => {
                projectNames = Array.isArray(names) ? names : [];
                const sel = document.getElementById('f-project-name');
                const list = projectNames.map(p => p.name);
                if (selected && !list.includes(selected)) list.push(selected); // lumang pangalan na wala na sa listahan
                sel.innerHTML = '<option value="">— Select —</option>' +
                    list.map(n => `<option value="${escHtml(n)}" ${n === selected ? 'selected' : ''}>${escHtml(n)}</option>`).join('');
            }).catch(e => boxMsg('pj-error', 'Could not load project names. ' + errMsg(e, '')));
        }

        const pnState = { editing: null, confirming: null };
        function openProjectNameManager() {
            Object.assign(pnState, { editing: null, confirming: null });
            setErr('pn-error', '');
            document.getElementById('projectname-manager-modal').classList.remove('hidden');
            renderProjectNameList(true);
            setTimeout(() => document.getElementById('new-projectname-input').focus(), 50);
        }
        function closeProjectNameManager() {
            document.getElementById('projectname-manager-modal').classList.add('hidden');
            document.getElementById('new-projectname-input').value = '';
            loadProjectNames(document.getElementById('f-project-name').value);
        }
        function renderProjectNameList(refetch) {
            const box = document.getElementById('projectname-list');
            const draw = () => {
                box.innerHTML = projectNames.length ? projectNames.map(p => {
                    const id = Number(p.id);
                    if (pnState.editing === id) return `
                        <form class="flex items-center gap-2 px-2 py-1.5" onsubmit="event.preventDefault(); saveEditPN(${id});">
                            <input id="pn-edit-${id}" type="text" value="${escHtml(p.name)}" maxlength="150" class="pmm-input uppercase !py-1.5">
                            <button type="submit" title="Save" class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg bg-green-600 hover:bg-green-700 text-white"><i class="fa-solid fa-check text-[10px]"></i></button>
                            <button type="button" title="Cancel" onclick="pnReset()" class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                        </form>`;
                    if (pnState.confirming === id) return `
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-red-50 border border-red-200">
                            <span class="text-xs text-red-700 truncate">Delete <b>${escHtml(p.name)}</b>?</span>
                            <span class="flex gap-1.5 shrink-0">
                                <button type="button" onclick="deletePN(${id})" class="text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg px-2.5 py-1">Delete</button>
                                <button type="button" onclick="pnReset()" class="text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-2.5 py-1">Cancel</button>
                            </span>
                        </div>`;
                    return `
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                            <span class="text-sm text-gray-700 flex-1 min-w-0 truncate">${escHtml(p.name)}</span>
                            <span class="flex gap-1 shrink-0">
                                <button type="button" title="Rename" onclick="pnEdit(${id})" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-700"><i class="fa-solid fa-pen text-[10px]"></i></button>
                                <button type="button" title="Delete" onclick="pnConfirm(${id})" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"><i class="fa-solid fa-trash text-[10px]"></i></button>
                            </span>
                        </div>`;
                }).join('') : '<p class="text-xs text-gray-400 text-center py-6">No project names yet — add one above.</p>';
                if (pnState.editing) setTimeout(() => document.getElementById('pn-edit-' + pnState.editing)?.focus(), 0);
            };
            if (!refetch) return draw();
            box.innerHTML = '<p class="text-xs text-gray-400 text-center py-6"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading...</p>';
            return getJson(`${API}/fetchprojectnames`).then(d => { projectNames = Array.isArray(d) ? d : []; draw(); })
                .catch(e => setErr('pn-error', errMsg(e, 'Could not load the list.')));
        }
        function pnReset() { Object.assign(pnState, { editing: null, confirming: null }); renderProjectNameList(false); }
        function pnEdit(id) { Object.assign(pnState, { editing: id, confirming: null }); setErr('pn-error', ''); renderProjectNameList(false); }
        function pnConfirm(id) { Object.assign(pnState, { editing: null, confirming: id }); setErr('pn-error', ''); renderProjectNameList(false); }
        function pnPost(route, body, okMsg) {
            setErr('pn-error', '');
            return postJson(`${API}/${route}`, body).then(d => {
                if (!d?.success) throw Object.assign(new Error(d?.message || d?.error || 'Could not save.'), { server: true });
                Object.assign(pnState, { editing: null, confirming: null });
                if (okMsg) showToast(okMsg);
                return renderProjectNameList(true).then(() => true);
            }).catch(e => { setErr('pn-error', errMsg(e, 'Could not reach the server.')); return false; });
        }
        function saveEditPN(id) {
            const val = document.getElementById('pn-edit-' + id).value.trim();
            if (!val) return setErr('pn-error', 'Name cannot be empty.');
            pnPost('saveprojectname', { id, name: val });
        }
        function deletePN(id) { pnPost('deleteprojectname', { id }); }
        function addNewProjectName() {
            const inp = document.getElementById('new-projectname-input');
            const val = inp.value.trim();
            if (!val) return setErr('pn-error', 'Type a project name first.');
            if (projectNames.some(p => String(p.name).toLowerCase() === val.toLowerCase())) return setErr('pn-error', 'That project name already exists.');
            pnPost('saveprojectname', { name: val }, 'Project name added!').then(ok => { if (ok) inp.value = ''; });
        }

        // ─── New / Edit project ──────────────────────────────
        const PJ_FIELDS = ['project-name', 'job-order', 'project-scope', 'purchase-order', 'client-name', 'notice-to-proceed',
            'contract-amount', 'billing-order-1', 'sales-person', 'billing-order-2', 'address', 'status'];
        const PJ_DRAFT = 'pmm-project-draft';
        const pjVal = f => document.getElementById('f-' + f).value;

        function openAddModal(id = null) {
            const modal = document.getElementById('add-modal');
            clearErrs('pj-form', 'pj-error');
            PJ_FIELDS.forEach(f => document.getElementById('f-' + f).value = '');
            const row = id ? allProjects.find(p => p.id == id) : null;
            modal.dataset.editId = row ? row.id : '';
            document.getElementById('pj-title').textContent = row ? 'Edit Project' : 'New Project';
            document.getElementById('pj-icon').innerHTML = `<i class="fa-solid ${row ? 'fa-pen' : 'fa-plus'}"></i>`;
            document.getElementById('pj-save-btn').querySelector('span').textContent = row ? 'Save Changes' : 'Save Project';

            let selectedName = '';
            const draft = row ? null : store.get(PJ_DRAFT);
            document.getElementById('pj-draft-note').classList.toggle('hidden', !draft);
            if (row) {
                const map = { 'project-name': 'project_name', 'job-order': 'job_order', 'project-scope': 'project_scope', 'purchase-order': 'purchase_order',
                    'client-name': 'client_name', 'notice-to-proceed': 'notice_to_proceed', 'contract-amount': 'contract_amount',
                    'billing-order-1': 'billing_order_1', 'sales-person': 'sales_person', 'billing-order-2': 'billing_order_2', 'address': 'address', 'status': 'status' };
                Object.entries(map).forEach(([f, k]) => { if (f !== 'project-name') document.getElementById('f-' + f).value = row[k] ?? ''; });
                selectedName = row.project_name ?? '';
            } else if (draft) {
                PJ_FIELDS.forEach(f => { if (f !== 'project-name' && draft[f] !== undefined) document.getElementById('f-' + f).value = draft[f]; });
                selectedName = draft['project-name'] || '';
            } else {
                document.getElementById('f-status').value = 'New';
            }
            loadProjectNames(selectedName);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeAddModal() {
            if (savingProject) return;
            document.getElementById('add-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        function saveProjectDraft() {
            if (document.getElementById('add-modal').dataset.editId || document.getElementById('add-modal').classList.contains('hidden')) return;
            const d = {}; PJ_FIELDS.forEach(f => d[f] = pjVal(f));
            store.set(PJ_DRAFT, PJ_FIELDS.some(f => f !== 'status' && String(d[f]).trim()) ? d : null);
        }
        function discardProjectDraft() {
            store.set(PJ_DRAFT, null);
            PJ_FIELDS.forEach(f => document.getElementById('f-' + f).value = '');
            document.getElementById('f-status').value = 'New';
            document.getElementById('pj-draft-note').classList.add('hidden');
        }
        PJ_FIELDS.forEach(f => ['input', 'change'].forEach(ev => document.getElementById('f-' + f).addEventListener(ev, saveProjectDraft)));

        function saveProject() {
            if (savingProject) return;
            clearErrs('pj-form', 'pj-error');
            const editId = document.getElementById('add-modal').dataset.editId;
            const amt = pjVal('contract-amount').trim();
            let ok = true;
            if (!pjVal('project-name')) { setErr('err-project-name', 'Project name is required.', 'f-project-name'); ok = false; }
            if (amt !== '' && (!Number.isFinite(Number(amt)) || Number(amt) < 0)) { setErr('err-contract-amount', 'Enter a valid amount (0 or more).', 'f-contract-amount'); ok = false; }
            if (!ok) return;

            const payload = { id: editId ? parseInt(editId, 10) : null };
            PJ_FIELDS.forEach(f => payload[f.replace(/-/g, '_')] = pjVal(f).trim());

            savingProject = true;
            const btn = document.getElementById('pj-save-btn');
            btn.disabled = true; btn.querySelector('span').textContent = 'Saving…';
            postJson(`${API}/saveproject`, payload).then(data => {
                if (data?.success) {
                    if (!editId) store.set(PJ_DRAFT, null);
                    savingProject = false; closeAddModal();
                    lastSig = ''; fetchProjects();
                    showToast(editId ? 'Project updated!' : 'Project saved!');
                } else boxMsg('pj-error', data?.error ?? data?.message ?? 'Could not save the project.');
            }).catch(e => boxMsg('pj-error', errMsg(e, 'Could not reach the server. Your project was not saved.')))
              .finally(() => { savingProject = false; btn.disabled = false; btn.querySelector('span').textContent = editId ? 'Save Changes' : 'Save Project'; });
        }

        // ─── Add entry ───────────────────────────────────────
        const B_FIELDS = ['b-particulars', 'b-amount', 'b-bank-check', 'b-payment-date', 'b-reference', 'b-remarks'];
        const E_FIELDS = ['e-title', 'e-particulars', 'e-amount', 'e-mode', 'e-payment-date', 'e-reference', 'e-remarks'];
        const EN_DRAFT = 'pmm-entry-draft';

        function populateProjectDropdown() {
            const sel = document.getElementById('entry-project-id');
            const saved = sel.value;
            sel.innerHTML = '<option value="">— Choose a project —</option>' + allProjects.map(p =>
                `<option value="${escHtml(p.id)}">${escHtml(p.project_name)}${p.reference_no ? '  [' + escHtml(p.reference_no) + ']' : ''}</option>`).join('');
            if (saved) sel.value = saved;
            showProjectInfo();
        }
        function showProjectInfo() {
            const p = allProjects.find(x => x.id == document.getElementById('entry-project-id').value);
            const el = document.getElementById('entry-project-info');
            el.classList.toggle('hidden', !p);
            if (!p) return;
            const m = metrics(p);
            el.innerHTML = `${escHtml(p.client_name || 'No client')} · Contract <b class="font-mono">${peso(m.contract)}</b> · Collected <b class="font-mono text-green-700">${peso(m.credited)}</b> · Balance <b class="font-mono text-amber-800">${peso(Math.max(0, m.balance))}</b>`;
        }
        document.getElementById('entry-project-id').addEventListener('change', () => { showProjectInfo(); saveEntryDraft(); });

        function openEntryModal(projectId = null) {
            if (!CAN_ENTRY) return;
            clearErrs('entry-form', 'entry-error');
            resetMode();
            const draft = projectId ? null : store.get(EN_DRAFT);
            document.getElementById('en-draft-note').classList.toggle('hidden', !draft);
            document.getElementById('entry-project-id').value = projectId ?? draft?.project ?? '';
            if (draft) {
                [...B_FIELDS, ...E_FIELDS].forEach(f => { if (draft[f] !== undefined) document.getElementById(f).value = draft[f]; });
                if (draft.mode) setMode(draft.mode);
            }
            showProjectInfo();
            document.getElementById('entry-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeEntryModal() {
            if (savingEntry) return;
            document.getElementById('entry-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        function resetMode() {
            currentMode = null;
            document.querySelectorAll('.pmm-mode').forEach(b => b.dataset.active = '0');
            ['entry-fields', 'billing-fields', 'expense-fields'].forEach(id => document.getElementById(id).classList.add('hidden'));
            [...B_FIELDS, ...E_FIELDS].forEach(id => document.getElementById(id).value = '');
            ['b-payment-date', 'e-payment-date'].forEach(id => document.getElementById(id).value = todayPH());
            document.getElementById('entry-mode-hint').textContent = 'Choose what to record to continue.';
            document.getElementById('entry-save-btn').querySelector('span').textContent = 'Save Entry';
            const box = document.getElementById('entry-modal-box');
            box.classList.remove('md:max-w-5xl'); box.classList.add('max-w-2xl');
            document.getElementById('hybrid-grid').classList.remove('md:grid-cols-2');
        }
        function setMode(mode) {
            currentMode = mode;
            setErr('err-entry-mode', '');
            document.querySelectorAll('.pmm-mode').forEach(b => b.dataset.active = b.dataset.mode === mode ? '1' : '0');
            document.getElementById('entry-fields').classList.remove('hidden');
            document.getElementById('billing-fields').classList.toggle('hidden', mode === 'expense');
            document.getElementById('expense-fields').classList.toggle('hidden', mode === 'billing');
            document.getElementById('entry-mode-hint').textContent = {
                billing: 'Collection — payment received from the client.',
                expense: 'Expense — a cost or payment made for the project.',
                hybrid: 'Both — saves one collection and one expense together.',
            }[mode];
            document.getElementById('entry-save-btn').querySelector('span').textContent = mode === 'hybrid' ? 'Save Both' : 'Save Entry';
            const box = document.getElementById('entry-modal-box');
            const grid = document.getElementById('hybrid-grid');
            box.classList.toggle('md:max-w-5xl', mode === 'hybrid');
            box.classList.toggle('max-w-2xl', mode !== 'hybrid');
            grid.classList.toggle('md:grid-cols-2', mode === 'hybrid');
            saveEntryDraft();
        }

        function saveEntryDraft() {
            if (document.getElementById('entry-modal').classList.contains('hidden')) return;
            const d = { project: document.getElementById('entry-project-id').value, mode: currentMode };
            [...B_FIELDS, ...E_FIELDS].forEach(f => d[f] = document.getElementById(f).value);
            const typed = [...B_FIELDS, ...E_FIELDS].some(f => !f.endsWith('payment-date') && String(d[f]).trim());
            store.set(EN_DRAFT, typed ? d : null);
        }
        function discardEntryDraft() {
            store.set(EN_DRAFT, null);
            document.getElementById('entry-project-id').value = '';
            resetMode(); showProjectInfo();
            document.getElementById('en-draft-note').classList.add('hidden');
        }
        [...B_FIELDS, ...E_FIELDS].forEach(f => ['input', 'change'].forEach(ev => document.getElementById(f).addEventListener(ev, saveEntryDraft)));

        // Lagyan ng comma ang amount habang nagta-type
        function formatAmountInput(el) {
            let raw = el.value.replace(/[^0-9.]/g, '');
            const parts = raw.split('.');
            if (parts.length > 2) raw = parts[0] + '.' + parts.slice(1).join('');
            const p = raw.split('.');
            const intPart = p[0].replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            el.value = p[1] !== undefined ? intPart + '.' + p[1].slice(0, 2) : intPart;
        }
        function getRawAmount(id) {
            const val = document.getElementById(id).value.replace(/,/g, '');
            return val === '' ? '' : parseFloat(val);
        }

        async function saveEntry() {
            if (savingEntry) return;
            clearErrs('entry-form', 'entry-error');
            const projectId = document.getElementById('entry-project-id').value;
            const doB = currentMode === 'billing' || currentMode === 'hybrid';
            const doE = currentMode === 'expense' || currentMode === 'hybrid';
            let ok = true;
            if (!projectId) { setErr('err-entry-project', 'Choose a project first.', 'entry-project-id'); ok = false; }
            if (!currentMode) { setErr('err-entry-mode', 'Choose what you are recording.'); ok = false; }
            const need = (prefix) => {
                if (!document.getElementById(prefix + '-particulars').value.trim()) { setErr(`err-${prefix}-particulars`, 'Particulars is required.', prefix + '-particulars'); ok = false; }
                const a = getRawAmount(prefix + '-amount');
                if (a === '' || !Number.isFinite(a) || a <= 0) { setErr(`err-${prefix}-amount`, 'Enter an amount greater than 0.', prefix + '-amount'); ok = false; }
            };
            if (doB) need('b');
            if (doE) need('e');
            if (!ok) return;

            const v = id => document.getElementById(id).value.trim();
            const jobs = [];
            if (doB) jobs.push(['Collection', 'saveprojectbilling', {
                id: null, project_id: parseInt(projectId, 10), particulars: v('b-particulars'), amount: getRawAmount('b-amount'),
                bank_check: v('b-bank-check'), payment_date: v('b-payment-date'), reference: v('b-reference'), remarks: v('b-remarks'),
            }]);
            if (doE) jobs.push(['Expense', 'saveprojectexpense', {
                id: null, project_id: parseInt(projectId, 10), title: v('e-title'), particulars: v('e-particulars'), amount: getRawAmount('e-amount'),
                mode_of_payment: v('e-mode'), payment_date: v('e-payment-date'), reference: v('e-reference'), remarks: v('e-remarks'),
            }]);

            savingEntry = true;
            const btn = document.getElementById('entry-save-btn');
            const label = btn.querySelector('span').textContent;
            btn.disabled = true; btn.querySelector('span').textContent = 'Saving…';

            // Isa-isa para malinaw kung alin ang na-save kapag may pumalya
            const saved = [], failed = [];
            for (const [name, route, body] of jobs) {
                try {
                    const r = await postJson(`${API}/${route}`, body);
                    if (r?.success) saved.push(name);
                    else failed.push(`${name}: ${r?.error ?? r?.message ?? 'not saved'}`);
                } catch (e) { failed.push(`${name}: ${errMsg(e, 'could not reach the server')}`); }
            }
            savingEntry = false; btn.disabled = false; btn.querySelector('span').textContent = label;

            if (!failed.length) {
                store.set(EN_DRAFT, null);
                closeEntryModal();
                lastSig = ''; fetchProjects();
                showToast(saved.length > 1 ? 'Collection & expense saved!' : saved[0] + ' saved!');
            } else {
                if (saved.length) {
                    // Huwag ulitin ang na-save na; itago ang section nito
                    if (saved.includes('Collection')) setMode('expense');
                    if (saved.includes('Expense')) setMode('billing');
                    lastSig = ''; fetchProjects();
                }
                boxMsg('entry-error', (saved.length ? saved.join(' & ') + ' was saved. ' : '') + 'Not saved — ' + failed.join(' | '));
            }
        }

        // Alisin ang error ng field kapag inayos na
        ['input', 'change'].forEach(ev => document.addEventListener(ev, e => {
            const el = e.target;
            if (!el.id || !el.hasAttribute('aria-invalid')) return;
            el.removeAttribute('aria-invalid');
            const errId = { 'entry-project-id': 'err-entry-project' }[el.id] || 'err-' + el.id.replace(/^f-/, '');
            document.getElementById(errId)?.classList.add('hidden');
        }));

        // ─── Events ──────────────────────────────────────────
        document.getElementById('search-input').addEventListener('input', applyFilters);
        document.getElementById('pmm-sort').addEventListener('change', applyFilters);
        document.querySelectorAll('#pmm-tabs .pmm-tab').forEach(b => b.addEventListener('click', () => {
            activeFilter = b.dataset.filter;
            document.querySelectorAll('#pmm-tabs .pmm-tab').forEach(x => x.dataset.active = x === b ? '1' : '0');
            applyFilters();
        }));
        document.addEventListener('click', e => {
            if (e.target.id === 'add-modal') closeAddModal();
            if (e.target.id === 'entry-modal') closeEntryModal();
            if (e.target.id === 'projectname-manager-modal') closeProjectNameManager();
        });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const open = id => !document.getElementById(id).classList.contains('hidden');
            if (open('projectname-manager-modal')) closeProjectNameManager();
            else if (open('add-modal')) closeAddModal();
            else if (open('entry-modal')) closeEntryModal();
        });

        // ─── Init ────────────────────────────────────────────
        fetchProjects();
        setInterval(() => { if (document.visibilityState === 'visible') fetchProjects(); }, 15000);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') fetchProjects(); });
    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'pmm-picked';
            const SEL = '#projects-cards .pmm-row';
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