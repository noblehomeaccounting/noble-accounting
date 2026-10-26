<?php
// index-custodian-pettycashtwo.php — Petty Cash Sheet Two (expenses)
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
    <title>Petty Cash Custodian Sheet</title>
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
                    <i class="fa-solid fa-receipt text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Petty Cash Custodian Sheet</h1>
                    <p class="text-sm text-gray-500">Expense disbursement records</p>
                </div>
            </div>
            <button type="button" onclick="openAddModal()"
                class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition shadow-sm">
                <i class="fa-solid fa-plus text-xs"></i> Add Entry
            </button>
        </div>

        <!-- ═══ SHEET TABS ═══ -->
        <nav class="mb-5 flex gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-sm w-full sm:w-fit" aria-label="Petty cash sheets">
                <a href="<?= BASE_URL ?>/accountingcustodianpettycash" data-route="accountingcustodianpettycash"
                    class="pc-tab pc-tab-link flex-1 sm:flex-none flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition text-center">
                    <i class="fa-solid fa-hand-holding-dollar text-[11px]"></i>
                    <span class="leading-tight">General Sheet<span class="hidden lg:inline font-normal opacity-75"> · Receipts</span></span>
                </a>
                <a href="<?= BASE_URL ?>/accountingcustodianpettycashtwo" data-route="accountingcustodianpettycashtwo" aria-current="page"
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

        <!-- ═══ SUMMARY ═══ -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-green-50 text-green-600 items-center justify-center shrink-0"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Cash available</p>
                    <p id="card-inflows" class="text-lg sm:text-xl font-bold text-green-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-inflows-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-red-50 text-red-500 items-center justify-center shrink-0"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Total actual</p>
                    <p id="card-actual" class="text-lg sm:text-xl font-bold text-red-600 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-actual-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 ">
                <div class="hidden sm:flex w-10 h-10 rounded-lg bg-blue-50 text-blue-600 items-center justify-center shrink-0"><i class="fa-solid fa-scale-balanced"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Remaining</p>
                    <p id="card-balance" class="text-lg sm:text-xl font-bold text-blue-700 leading-none mt-1 font-mono break-all">₱ 0.00</p>
                    <p id="card-balance-sub" class="text-[11px] text-gray-400 mt-1 truncate"></p>
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
                    <div class="relative flex-1 min-w-0 xl:w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="pc-search" placeholder="Search particulars, supplier, ref no..."
                            class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <select id="pc-title-filter" class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white max-w-[160px]">
                        <option value="">All account titles</option>
                    </select>
                    <select id="pc-dept-filter" class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white max-w-[160px]">
                        <option value="">All departments</option>
                    </select>
                </div>
            </div>
            <div id="pc-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>

            <div id="sheet-tbody" class="pc-scroll max-h-[calc(100vh-24rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
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

        <!-- Carry-over -->
        <div id="carryover-bar" class="hidden mt-4 bg-blue-50 border border-blue-200 rounded-xl px-4 sm:px-5 py-3 flex items-start sm:items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-arrow-right-long"></i></div>
            <p class="text-xs text-blue-800">
                Remaining balance of <b id="carryover-month"></b>: <b id="carryover-amount" class="font-mono"></b>
                <span class="text-blue-700">— this becomes the <b>Beginning Balance</b> for next month's General Sheet.</span>
            </p>
        </div>

    </main>

    <!-- ═══ ADD / EDIT MODAL ═══ -->
    <div id="entry-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4 py-6">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
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
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800 mb-3 mt-0 flex items-center gap-2"><i class="fa-solid fa-receipt"></i> Expense<span class="flex-1 h-px bg-amber-100"></span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="pc-label" for="entry-date">Date</label>
                        <input type="date" id="entry-date" readonly class="pc-input">
                        <p class="text-[10px] text-gray-400 mt-1"><i class="fa-solid fa-lock mr-1"></i>Set automatically</p>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-reference-no">Reference No.</label>
                        <input type="text" id="entry-reference-no" placeholder="e.g. 040001" class="pc-input font-mono" autocomplete="off">
                    </div>
                    <div>
                        <label class="pc-label" for="entry-account-title">Account Title</label>
                        <div class="flex gap-2">
                            <select id="entry-account-title" class="pc-input"><option value="">— Select —</option></select>
                            <button type="button" onclick="openTitleManager()" title="Manage list"
                                class="shrink-0 w-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-amber-50 hover:border-amber-400 text-gray-500 hover:text-amber-700 transition">
                                <i class="fa-solid fa-gear text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-2 md:col-span-3">
                        <label class="pc-label" for="entry-particulars">Particulars <span class="text-red-500">*</span></label>
                        <input type="text" id="entry-particulars" placeholder="e.g. Transportation expense of workers" class="pc-input">
                        <p id="err-particulars" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-department">Department</label>
                        <div class="flex gap-2">
                            <select id="entry-department" class="pc-input"><option value="">— Select —</option></select>
                            <button type="button" onclick="openDeptManager()" title="Manage list"
                                class="shrink-0 w-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-amber-50 hover:border-amber-400 text-gray-500 hover:text-amber-700 transition">
                                <i class="fa-solid fa-gear text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-in-charge">In-Charge</label>
                        <input type="text" id="entry-in-charge" placeholder="e.g. ERMA" class="pc-input" list="pc-incharge-list">
                        <datalist id="pc-incharge-list"></datalist>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-actual">Actual amount <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-actual" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                        <p id="err-actual" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>
                </div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800 mb-3 mt-5 first:mt-0 flex items-center gap-2"><i class="fa-solid fa-store"></i> Supplier<span class="flex-1 h-px bg-amber-100"></span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="pc-label" for="entry-supplier-corp">Supplier (Corp)</label>
                        <input type="text" id="entry-supplier-corp" placeholder="Company name" class="pc-input" list="pc-corp-list">
                        <datalist id="pc-corp-list"></datalist>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-supplier-indiv">Supplier (Individual)</label>
                        <input type="text" id="entry-supplier-indiv" placeholder="Individual name" class="pc-input">
                    </div>
                    <div>
                        <label class="pc-label" for="entry-tin">TIN</label>
                        <input type="text" id="entry-tin" placeholder="000-000-000-000" class="pc-input font-mono" inputmode="numeric">
                        <p id="err-tin" class="hidden text-[11px] text-red-600 mt-1"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="pc-label" for="entry-address">Address</label>
                        <input type="text" id="entry-address" placeholder="Address" class="pc-input">
                    </div>
                    <div>
                        <label class="pc-label" for="entry-no-sales-invoice">Sales Invoice No.</label>
                        <input type="text" id="entry-no-sales-invoice" placeholder="Invoice no." class="pc-input font-mono">
                    </div>
                </div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800 mb-3 mt-5 first:mt-0 flex items-center gap-2"><i class="fa-solid fa-percent"></i> Tax breakdown<span class="flex-1 h-px bg-amber-100"></span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="pc-label" for="entry-total">Total (VAT inclusive)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-total" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">Typing here fills VATable and VAT (12%).</p>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-vatable">VATable amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-vatable" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-vat">VAT</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-vat" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-nonvat">Non-VAT</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-nonvat" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                    </div>
                    <div>
                        <label class="pc-label" for="entry-vat-exempt">VAT exempt</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" id="entry-vat-exempt" step="0.01" min="0" placeholder="0.00" inputmode="decimal" class="pc-input pc-money">
                        </div>
                    </div>
                    <div class="flex items-end">
                        <p id="tax-check" class="text-[11px] text-gray-500 leading-snug"></p>
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

    <!-- ═══ MANAGE ACCOUNT TITLES MANAGER ═══ -->
    <div id="title-manager-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-gear"></i></div>
                    <h3 class="font-bold text-base text-gray-800">Manage Account Titles</h3>
                </div>
                <button type="button" onclick="closeTitleManager()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="px-4 py-3 border-b border-gray-100 flex gap-2 shrink-0" onsubmit="event.preventDefault(); addNewTitle();">
                <input type="text" id="new-title-input" placeholder="New account title..." class="pc-input uppercase" maxlength="150">
                <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 rounded-lg transition">
                    <i class="fa-solid fa-plus text-[10px]"></i> Add
                </button>
            </form>
            <p id="title-mgr-error" class="hidden mx-4 mt-2 text-xs text-red-600"></p>
            <div id="title-list" class="pc-scroll overflow-y-auto px-2 py-2 flex-1 min-h-[120px]"></div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 text-right shrink-0">
                <button type="button" onclick="closeTitleManager()" class="text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg transition">Done</button>
            </div>
        </div>
    </div>

    <!-- ═══ MANAGE DEPARTMENTS MANAGER ═══ -->
    <div id="dept-manager-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-gear"></i></div>
                    <h3 class="font-bold text-base text-gray-800">Manage Departments</h3>
                </div>
                <button type="button" onclick="closeDeptManager()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="px-4 py-3 border-b border-gray-100 flex gap-2 shrink-0" onsubmit="event.preventDefault(); addNewDept();">
                <input type="text" id="new-dept-input" placeholder="New department..." class="pc-input uppercase" maxlength="150">
                <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 rounded-lg transition">
                    <i class="fa-solid fa-plus text-[10px]"></i> Add
                </button>
            </form>
            <p id="dept-mgr-error" class="hidden mx-4 mt-2 text-xs text-red-600"></p>
            <div id="dept-list" class="pc-scroll overflow-y-auto px-2 py-2 flex-1 min-h-[120px]"></div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 text-right shrink-0">
                <button type="button" onclick="closeDeptManager()" class="text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg transition">Done</button>
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

        const SAVE_ROUTE = 'savecustodiansheetpettycashtwo';
        const DELETE_ROUTE = 'deletecustodiansheetpettycashtwo';
        const DRAFT_KEY = 'pc-two-draft';
        const FIELDS = ['entry-reference-no', 'entry-account-title', 'entry-particulars', 'entry-department', 'entry-in-charge',
            'entry-actual', 'entry-supplier-corp', 'entry-supplier-indiv', 'entry-address', 'entry-tin', 'entry-vatable',
            'entry-vat', 'entry-total', 'entry-nonvat', 'entry-no-sales-invoice', 'entry-vat-exempt'];
        const DRAFT_IGNORE = ['entry-account-title', 'entry-department'];
        let allEntries = [];
        let generalSheetInflows = 0, generalOk = true;
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

        // ─── Fetch ───────────────────────────────────────────
        function fetchEntries() {
            const month = curMonth();
            const seq = ++fetchSeq;
            document.getElementById('sheet-tbody').innerHTML =
                '<div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>';
            const q = encodeURIComponent(month);
            Promise.all([
                getJson(`${BASE_URL_JS}/fetchcustodiansheetpettycashtwo?month=${q}`),
                getJson(`${BASE_URL_JS}/fetchgeneralsheet?month=${q}`).catch(() => null),
            ]).then(([data, general]) => {
                if (seq !== fetchSeq) return;
                allEntries = Array.isArray(data) ? data : [];
                generalOk = Array.isArray(general);
                let beginning = 0, received = 0;
                (general || []).forEach(r => {
                    if (r.entry_type === 'beginning') beginning = num(r.cash_inflows);
                    else received += num(r.cash_inflows);
                });
                generalSheetInflows = beginning + received;
                buildFilters();
                fillSuggestions();
                applyFilters();
            }).catch(err => {
                if (seq !== fetchSeq) return;
                console.error(err);
                document.getElementById('sheet-tbody').innerHTML = emptyHtml('Could not load entries.',
                    err?.server ? err.message : 'Check your connection, then try again.');
            });
        }

        function uniq(key) { return [...new Set(allEntries.map(r => (r[key] ?? '').trim()).filter(Boolean))].sort(); }
        function buildFilters() {
            [['pc-title-filter', 'account_title', 'All account titles'], ['pc-dept-filter', 'project_department', 'All departments']].forEach(([id, key, all]) => {
                const sel = document.getElementById(id), cur = sel.value;
                sel.innerHTML = `<option value="">${all}</option>` + uniq(key).map(v => `<option value="${escHtml(v)}" ${v === cur ? 'selected' : ''}>${escHtml(v)}</option>`).join('');
            });
        }
        function fillSuggestions() {
            document.getElementById('pc-incharge-list').innerHTML = uniq('in_charge').map(v => `<option value="${escHtml(v)}">`).join('');
            document.getElementById('pc-corp-list').innerHTML = uniq('supplier_name_corp').map(v => `<option value="${escHtml(v)}">`).join('');
        }

        function applyFilters() {
            const q = document.getElementById('pc-search').value.trim().toLowerCase();
            const title = document.getElementById('pc-title-filter').value;
            const dept = document.getElementById('pc-dept-filter').value;
            const shown = allEntries.filter(r =>
                (!title || (r.account_title ?? '').trim() === title) &&
                (!dept || (r.project_department ?? '').trim() === dept) &&
                (!q || [r.particulars, r.reference_no, r.account_title, r.project_department, r.in_charge, r.supplier_name_corp,
                    r.supplier_name_indiv, r.address, r.tin, r.no_sales_invoice, r.inserted_by]
                    .some(v => v && String(v).toLowerCase().includes(q))));
            const filtered = !!(q || title || dept);
            const box = document.getElementById('sheet-tbody');
            const m = monthLabel(curMonth());
            document.getElementById('pc-info').innerHTML =
                `Showing <b class="text-gray-700">${shown.length}</b> of ${allEntries.length} entr${allEntries.length === 1 ? 'y' : 'ies'} · <b class="text-gray-700">${escHtml(m)}</b>`;
            box.innerHTML = shown.length ? shown.map((r, i) => expenseRowHtml(r, i, true)).join('')
                : filtered ? emptyHtml('No matching entries.', 'Try another search or filter.')
                : emptyHtml(`No expenses for ${escHtml(m)}.`, 'Click "Add Entry" to record an expense.');
            renderFooter(shown);
            document.getElementById('foot-label').textContent = filtered ? '(filtered)' : '';
            renderSummary();
        }

        // Summary: laging buong buwan
        function renderSummary() {
            const actual = taxTotals(allEntries).actual;
            const remaining = generalSheetInflows - actual;
            document.getElementById('card-inflows').textContent = generalOk ? fmt(generalSheetInflows) : '—';
            document.getElementById('card-inflows-sub').textContent = generalOk ? 'Beginning + receipts (General Sheet)' : 'Could not load General Sheet';
            document.getElementById('card-actual').textContent = fmt(actual);
            document.getElementById('card-actual-sub').textContent = allEntries.length + ' expense' + (allEntries.length === 1 ? '' : 's');
            const bal = document.getElementById('card-balance');
            bal.textContent = generalOk ? fmt(remaining) : '—';
            bal.classList.toggle('text-red-600', generalOk && remaining < 0);
            document.getElementById('card-balance-sub').textContent = !generalOk ? '' : remaining < 0 ? 'Overspent!' : "→ Next month's beginning";
            document.getElementById('carryover-bar').classList.toggle('hidden', !generalOk);
            document.getElementById('carryover-month').textContent = monthLabel(curMonth());
            document.getElementById('carryover-amount').textContent = fmt(remaining);
        }

        // ─── Account titles & departments (dropdown + manager) ─
        let titlesCache = [], deptsCache = [];
        function fillSelect(id, items, key, selected) {
            const sel = document.getElementById(id);
            const names = items.map(x => x[key]);
            if (selected && !names.includes(selected)) names.push(selected); // lumang value na wala na sa listahan
            sel.innerHTML = '<option value="">— Select —</option>' +
                names.map(n => `<option value="${escHtml(n)}" ${n === selected ? 'selected' : ''}>${escHtml(n)}</option>`).join('');
        }
        function loadAccountTitles(selected = '') {
            return getJson(`${BASE_URL_JS}/fetchpettycashaccounttitles`)
                .then(d => { titlesCache = Array.isArray(d) ? d : []; fillSelect('entry-account-title', titlesCache, 'title', selected); })
                .catch(() => fillSelect('entry-account-title', titlesCache, 'title', selected));
        }
        function loadDepartments(selected = '') {
            return getJson(`${BASE_URL_JS}/fetchpettycashdepartment`)
                .then(d => { deptsCache = Array.isArray(d) ? d : []; fillSelect('entry-department', deptsCache, 'name', selected); })
                .catch(() => fillSelect('entry-department', deptsCache, 'name', selected));
        }

        // Generic list manager (walang confirm(); inline ang pag-confirm ng delete)
        const MANAGERS = {
            title: { list: 'title-list', input: 'new-title-input', key: 'title', fetch: 'fetchpettycashaccounttitles',
                     save: 'savepettycashaccounttitle', del: 'deletepettycashaccounttitle', reload: () => loadAccountTitles(document.getElementById('entry-account-title').value) },
            dept:  { list: 'dept-list', input: 'new-dept-input', key: 'name', fetch: 'fetchpettycashdepartment',
                     save: 'savepettycashdepartment', del: 'deletepettycashdepartment', reload: () => loadDepartments(document.getElementById('entry-department').value) },
        };
        const mgrState = { title: { items: [], editing: null, confirming: null }, dept: { items: [], editing: null, confirming: null } };

        function mgrError(kind, msg) {
            const el = document.getElementById(kind + '-mgr-error');
            el.textContent = msg || ''; el.classList.toggle('hidden', !msg);
        }
        function mgrLoad(kind) {
            const c = MANAGERS[kind];
            document.getElementById(c.list).innerHTML = '<p class="text-xs text-gray-400 text-center py-6"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading...</p>';
            return getJson(`${BASE_URL_JS}/${c.fetch}`).then(d => { mgrState[kind].items = Array.isArray(d) ? d : []; mgrRender(kind); })
                .catch(err => mgrError(kind, err?.server ? err.message : 'Could not load the list.'));
        }
        function mgrRender(kind) {
            const c = MANAGERS[kind], st = mgrState[kind];
            const box = document.getElementById(c.list);
            if (!st.items.length) { box.innerHTML = '<p class="text-xs text-gray-400 text-center py-6">Nothing here yet — add one above.</p>'; return; }
            box.innerHTML = st.items.map(it => {
                const id = Number(it.id), val = it[c.key] ?? '';
                if (st.editing === id) return `
                    <form class="flex items-center gap-2 px-2 py-1.5" onsubmit="event.preventDefault(); mgrSave('${kind}', ${id});">
                        <input id="${kind}-edit-${id}" type="text" value="${escHtml(val)}" maxlength="150" class="pc-input uppercase !py-1.5">
                        <button type="submit" title="Save" class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg bg-green-600 hover:bg-green-700 text-white"><i class="fa-solid fa-check text-[10px]"></i></button>
                        <button type="button" title="Cancel" onclick="mgrCancel('${kind}')" class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                    </form>`;
                if (st.confirming === id) return `
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-red-50 border border-red-200">
                        <span class="text-xs text-red-700 truncate">Delete <b>${escHtml(val)}</b>?</span>
                        <span class="flex gap-1.5 shrink-0">
                            <button type="button" onclick="mgrDelete('${kind}', ${id})" class="text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg px-2.5 py-1">Delete</button>
                            <button type="button" onclick="mgrCancel('${kind}')" class="text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-2.5 py-1">Cancel</button>
                        </span>
                    </div>`;
                return `
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                        <span class="text-sm text-gray-700 flex-1 min-w-0 truncate">${escHtml(val)}</span>
                        <span class="flex gap-1 shrink-0">
                            <button type="button" title="Rename" onclick="mgrEdit('${kind}', ${id})" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-700"><i class="fa-solid fa-pen text-[10px]"></i></button>
                            <button type="button" title="Delete" onclick="mgrAskDelete('${kind}', ${id})" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600"><i class="fa-solid fa-trash text-[10px]"></i></button>
                        </span>
                    </div>`;
            }).join('');
            if (st.editing) setTimeout(() => document.getElementById(`${kind}-edit-${st.editing}`)?.focus(), 0);
        }
        function mgrEdit(kind, id) { Object.assign(mgrState[kind], { editing: id, confirming: null }); mgrError(kind, ''); mgrRender(kind); }
        function mgrAskDelete(kind, id) { Object.assign(mgrState[kind], { editing: null, confirming: id }); mgrError(kind, ''); mgrRender(kind); }
        function mgrCancel(kind) { Object.assign(mgrState[kind], { editing: null, confirming: null }); mgrRender(kind); }
        function mgrPost(kind, route, body) {
            mgrError(kind, '');
            return postJson(`${BASE_URL_JS}/${route}`, body).then(d => {
                if (!d?.success) throw Object.assign(new Error(d?.message || 'Could not save.'), { server: true });
                Object.assign(mgrState[kind], { editing: null, confirming: null });
                return mgrLoad(kind);
            }).catch(err => mgrError(kind, err?.server ? err.message : 'Could not reach the server.'));
        }
        function mgrSave(kind, id) {
            const val = document.getElementById(`${kind}-edit-${id}`).value.trim();
            if (!val) return mgrError(kind, 'Name cannot be empty.');
            mgrPost(kind, MANAGERS[kind].save, { id, [MANAGERS[kind].key]: val });
        }
        function mgrDelete(kind, id) { mgrPost(kind, MANAGERS[kind].del, { id }); }
        function mgrAdd(kind) {
            const inp = document.getElementById(MANAGERS[kind].input);
            const val = inp.value.trim();
            if (!val) return mgrError(kind, 'Type a name first.');
            if (mgrState[kind].items.some(it => String(it[MANAGERS[kind].key]).toLowerCase() === val.toLowerCase())) return mgrError(kind, 'That one already exists.');
            mgrPost(kind, MANAGERS[kind].save, { [MANAGERS[kind].key]: val }).then(() => { if (!document.getElementById(kind + '-mgr-error').textContent) inp.value = ''; });
        }
        function mgrOpen(kind) { mgrError(kind, ''); mgrCancel(kind); document.getElementById(kind + '-manager-modal').classList.remove('hidden'); mgrLoad(kind); setTimeout(() => document.getElementById(MANAGERS[kind].input).focus(), 50); }
        function mgrClose(kind) { document.getElementById(kind + '-manager-modal').classList.add('hidden'); document.getElementById(MANAGERS[kind].input).value = ''; MANAGERS[kind].reload(); }
        const openTitleManager = () => mgrOpen('title'), closeTitleManager = () => mgrClose('title'), addNewTitle = () => mgrAdd('title');
        const openDeptManager = () => mgrOpen('dept'), closeDeptManager = () => mgrClose('dept'), addNewDept = () => mgrAdd('dept');

        // ─── Tax helper ──────────────────────────────────────
        document.getElementById('entry-total').addEventListener('input', function () {
            const total = num(this.value);
            document.getElementById('entry-vatable').value = this.value === '' ? '' : (total / 1.12).toFixed(2);
            document.getElementById('entry-vat').value = this.value === '' ? '' : (total * 12 / 112).toFixed(2);
            taxCheck(); saveDraft();
        });
        ['entry-actual', 'entry-total', 'entry-vatable', 'entry-vat', 'entry-nonvat', 'entry-vat-exempt']
            .forEach(id => document.getElementById(id).addEventListener('input', taxCheck));
        function taxCheck() {
            const v = id => num(document.getElementById(id).value);
            const actual = v('entry-actual');
            const parts = v('entry-total') + v('entry-nonvat') + v('entry-vat-exempt');
            const el = document.getElementById('tax-check');
            if (!actual || !parts) { el.innerHTML = ''; return; }
            const diff = Math.round((actual - parts) * 100) / 100;
            el.innerHTML = diff === 0
                ? '<span class="text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Total + Non-VAT + VAT exempt matches Actual.</span>'
                : `<span class="text-amber-700"><i class="fa-solid fa-circle-info mr-1"></i>Total + Non-VAT + VAT exempt is ${fmt(parts)} — ${fmt(Math.abs(diff))} ${diff > 0 ? 'less' : 'more'} than Actual.</span>`;
        }

        // ─── Add / Edit ──────────────────────────────────────
        function openAddModal() {
            editMode = false;
            clearErrors();
            setModalHeader(false);
            document.getElementById('entry-id').value = '';
            document.getElementById('entry-date').value = todayPH();
            FIELDS.forEach(f => document.getElementById(f).value = '');
            const draft = readDraft();
            document.getElementById('pc-draft-note').classList.toggle('hidden', !draft);
            if (draft) FIELDS.forEach(f => { if (!DRAFT_IGNORE.includes(f) && draft[f] !== undefined) document.getElementById(f).value = draft[f]; });
            loadAccountTitles(draft?.['entry-account-title'] || '');
            loadDepartments(draft?.['entry-department'] || '');
            taxCheck();
            openModal();
        }

        function editEntry(id) {
            const row = allEntries.find(r => r.id == id);
            if (!row) return;
            editMode = true;
            clearErrors();
            setModalHeader(true);
            document.getElementById('pc-draft-note').classList.add('hidden');
            document.getElementById('entry-id').value = row.id;
            document.getElementById('entry-date').value = row.date ?? '';
            const map = {
                'entry-reference-no': 'reference_no', 'entry-particulars': 'particulars', 'entry-in-charge': 'in_charge',
                'entry-actual': 'actual', 'entry-supplier-corp': 'supplier_name_corp', 'entry-supplier-indiv': 'supplier_name_indiv',
                'entry-address': 'address', 'entry-tin': 'tin', 'entry-vatable': 'vatable_amount', 'entry-vat': 'vat',
                'entry-total': 'total', 'entry-nonvat': 'non_vat', 'entry-no-sales-invoice': 'no_sales_invoice', 'entry-vat-exempt': 'vat_exempt',
            };
            Object.entries(map).forEach(([el, key]) => document.getElementById(el).value = row[key] ?? '');
            loadAccountTitles(row.account_title ?? '');
            loadDepartments(row.project_department ?? '');
            taxCheck();
            openModal();
        }

        function saveEntry() {
            if (isSaving) return;
            clearErrors();
            const val = id => document.getElementById(id).value.trim();
            const date = val('entry-date');
            const particulars = val('entry-particulars');
            let ok = true;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { formError('Date is missing. Close and open the form again.'); ok = false; }
            if (!particulars) { fieldError('particulars', 'Particulars is required.'); ok = false; }
            const actualRaw = val('entry-actual');
            if (actualRaw === '' || !Number.isFinite(Number(actualRaw)) || Number(actualRaw) < 0) { fieldError('actual', 'Enter the actual amount (0 or more).'); ok = false; }
            for (const id of ['entry-vatable', 'entry-vat', 'entry-total', 'entry-nonvat', 'entry-vat-exempt']) {
                const v = val(id);
                if (v !== '' && (!Number.isFinite(Number(v)) || Number(v) < 0)) { document.getElementById(id).setAttribute('aria-invalid', 'true'); formError('Amounts must be numbers, 0 or more.'); ok = false; }
            }
            const tin = val('entry-tin');
            if (tin && !/^[0-9\- ]{9,20}$/.test(tin)) { fieldError('tin', 'TIN should contain digits and dashes only.'); ok = false; }
            if (!ok) return;

            const n = id => val(id) || 0;
            runSave({
                id: val('entry-id') || null,
                date,
                reference_no: val('entry-reference-no'),
                account_title: val('entry-account-title'),
                particulars,
                project_department: val('entry-department'),
                in_charge: val('entry-in-charge'),
                actual: n('entry-actual'),
                supplier_name_corp: val('entry-supplier-corp'),
                supplier_name_indiv: val('entry-supplier-indiv'),
                address: val('entry-address'),
                tin,
                vatable_amount: n('entry-vatable'),
                vat: n('entry-vat'),
                total: n('entry-total'),
                non_vat: n('entry-nonvat'),
                no_sales_invoice: val('entry-no-sales-invoice'),
                vat_exempt: n('entry-vat-exempt'),
            });
        }

        function deleteSummary(row) {
            return `<b>${escHtml(row.particulars || '—')}</b><br>${escHtml(fmtDay(row.date))} · ${escHtml(fmt(row.actual))}`;
        }

        // ─── Init ────────────────────────────────────────────
        bindDraft();
        document.getElementById('pc-search').addEventListener('input', applyFilters);
        document.getElementById('pc-title-filter').addEventListener('change', applyFilters);
        document.getElementById('pc-dept-filter').addEventListener('change', applyFilters);
        fetchEntries();

    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'pc2-picked';
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