<?php
// index-project-detail.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$project_id = intval($_GET['id'] ?? 0);
if (!$project_id) {
    header('Location: ' . BASE_URL . '/projectmonitor');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM nobleprojectmonitor WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
if (!$project) {
    header('Location: ' . BASE_URL . '/projectmonitor');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name']) ?> - Project Monitoring</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        @media print {
            body>*:not(#print-root) {
                display: none !important;
            }

            #print-area {
                display: block !important;
                position: fixed;
                inset: 0;
                background: white;
                z-index: 9999;
                padding: 20px;
                overflow: visible;
            }

            .no-print {
                display: none !important;
            }
        }

        .field-line {
            border-bottom: 1px solid #374151;
            min-width: 160px;
            display: inline-block;
            padding-bottom: 1px;
        }
    </style>
</head>

<body class="bg-slate-100">

    <!-- Top Bar -->
    <div class="md:ml-56 no-print">
        <div
            class="flex items-center justify-between px-4 md:px-8 py-3 md:py-4 bg-white border-b border-gray-100 shadow-sm mt-14 md:mt-0">
            <div class="flex items-center gap-2 md:gap-3 min-w-0">
                <a href="<?= BASE_URL ?>/projectmonitor"
                    class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <div class="min-w-0">
                    <h1 class="text-sm font-bold text-gray-800 truncate">
                        <?= htmlspecialchars($project['project_name']) ?></h1>
                    <p class="text-[10px] text-orange-500 font-mono">
                        <?= htmlspecialchars($project['reference_no'] ?? '') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button onclick="window.print()"
                    class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-semibold px-3 py-1.5 rounded-lg transition-all no-print">
                    <i class="fa-solid fa-print text-[10px]"></i>
                    <span class="hidden sm:inline">Print</span>
                </button>
                <a href="<?= BASE_URL ?>/exportprojectexcel?id=<?= $project_id ?>"
                    class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-[10px] font-semibold px-3 py-1.5 rounded-lg transition-all no-print">
                    <i class="fa-solid fa-file-excel text-[10px]"></i>
                    <span class="hidden sm:inline">Export Excel</span>
                </a>
            </div>
        </div>
    </div>

    <div id="print-area" class="md:ml-56 p-3 md:p-8">
        <div class="bg-white border border-gray-200 shadow-sm max-w-7xl mx-auto rounded-xl overflow-hidden">

            <!-- Header -->
            <div style="display:grid; grid-template-columns:1fr auto; background:#d97706;">
                <div style="display:flex; align-items:center; gap:12px; padding:12px 16px;">
                    <img src="<?= BASE_URL ?>/icon/logo.webp"
                        style="width:52px; height:52px; object-fit:contain; background:white; border-radius:4px; padding:2px;">
                    <div>
                        <div
                            style="font-weight:800; font-size:14px; color:white; text-transform:uppercase; letter-spacing:1px;">
                            Noblehome</div>
                        <div
                            style="font-weight:600; font-size:10px; color:rgba(255,255,255,0.85); text-transform:uppercase; letter-spacing:1px;">
                            Construction Corporation</div>
                        <div style="font-size:8px; color:rgba(255,255,255,0.7); margin-top:2px;">1181 MC Premier Bldg.,
                            EDSA Balintawak Quezon City</div>
                    </div>
                </div>
                <div
                    style="background:#1e293b; padding:12px 24px; display:flex; flex-direction:column; justify-content:center; min-width:220px;">
                    <div
                        style="font-weight:800; font-size:16px; color:white; text-transform:uppercase; letter-spacing:2px; text-align:right;">
                        Project Monitoring</div>
                    <div
                        style="font-size:9px; color:#f97316; text-transform:uppercase; letter-spacing:2px; text-align:right; margin-top:2px;">
                        Accounting Report</div>
                    <div style="margin-top:8px; display:flex; align-items:center; justify-content:flex-end; gap:8px;">
                        <span
                            style="font-size:8px; color:rgba(255,255,255,0.6); text-transform:uppercase; letter-spacing:1px;">Date
                            Issue</span>
                        <span
                            style="background:#374151; color:white; font-size:9px; font-family:monospace; padding:2px 8px; border-radius:3px;"><?= date('Y-m-d') ?></span>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="border-b-2 border-gray-200 mt-4">
                <div class="bg-orange-500 px-3 py-1.5 inline-block ml-3 md:ml-4">
                    <span class="text-[9px] font-700 text-white uppercase tracking-widest">Basic Information</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 px-3 md:px-4 pb-4 pt-2 text-xs">
                    <?php
                    $fields = [
                        'Project Name' => $project['project_name'],
                        'Job Order' => $project['job_order'] ?? '',
                        'Project Scope' => $project['project_scope'] ?? '',
                        'Purchase Order' => $project['purchase_order'] ?? '',
                        'Client Name' => $project['client_name'] ?? '',
                        'Notice to Proceed' => $project['notice_to_proceed'] ?? '',
                        'Contract Amount' => $project['contract_amount'] ? '₱ ' . number_format($project['contract_amount'], 2) : '',
                        '(1) Billing Order #' => $project['billing_order_1'] ?? '',
                        'Sales Person' => $project['sales_person'] ?? '',
                        '(2) Billing Order #' => $project['billing_order_2'] ?? '',
                        'Address' => $project['address'] ?? '',
                        'Status' => $project['status'] ?? '',
                    ];
                    foreach ($fields as $label => $value): ?>
                        <div class="flex items-baseline gap-2">
                            <span
                                class="text-[9px] font-bold uppercase tracking-wide text-gray-500 whitespace-nowrap min-w-[110px]"><?= $label ?>
                                :</span>
                            <span
                                class="border-b border-gray-400 flex-1 text-gray-800 pb-0.5 text-[11px]"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>


            <!-- Section 1: Billed and Paid -->
            <div class="mt-4 px-3 md:px-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="bg-orange-500 px-3 py-1 rounded">
                        <span class="text-[9px] font-bold text-white uppercase tracking-widest">1. Billed and Paid by
                            Client / Owner</span>
                    </div>
                    <button onclick="openBillingModal()"
                        class="no-print flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-plus text-[9px]"></i> Add Row
                    </button>
                </div>

                <!-- Desktop table -->
                <div class="hidden md:block overflow-x-auto overflow-y-auto max-h-72">
                    <table style="width:100%; border-collapse:collapse; font-size:10px;">
                        <thead>
                            <tr style="background:#374151; color:white; position:sticky; top:0; z-index:1;">
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:32px;">
                                    NO.</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:left;">PARTICULARS</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:right; width:100px;">
                                    AMOUNT</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:90px;">
                                    BANK / CHECK</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:90px;">
                                    PAYMENT DATE</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:90px;">
                                    REFERENCE</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:left; width:100px;">
                                    REMARKS</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:60px;"
                                    class="no-print">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="billing-tbody"></tbody>
                        <tfoot>
                            <tr style="background:#fef3c7;">
                                <td colspan="2"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-size:9px; text-transform:uppercase;">
                                    Total Amount Credited :</td>
                                <td id="billing-total"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-family:monospace; text-align:right;">
                                    ₱ 0.00</td>
                                <td colspan="2"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-size:9px; text-align:right;">
                                    Total Balance :</td>
                                <td id="billing-balance"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-family:monospace; text-align:right;"
                                    colspan="3">₱ 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="md:hidden overflow-y-auto max-h-72 rounded-lg border border-gray-100" id="billing-cards">
                    <div class="py-6 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>
                        Loading...</div>
                </div>
                <div
                    class="md:hidden flex items-center justify-between px-3 py-2 bg-yellow-50 border border-yellow-100 rounded-lg mt-2 text-xs font-semibold">
                    <span class="text-gray-500">Total Credited</span>
                    <span id="billing-total-m" class="font-mono text-gray-800">₱ 0.00</span>
                </div>
                <div
                    class="md:hidden flex items-center justify-between px-3 py-2 bg-yellow-50 border border-yellow-100 rounded-lg mt-1 text-xs font-semibold">
                    <span class="text-gray-500">Balance</span>
                    <span id="billing-balance-m" class="font-mono text-gray-800">₱ 0.00</span>
                </div>
            </div>

            <!-- Section 2: Costs / Expenses -->
            <div class="mt-6 px-3 md:px-4 pb-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="bg-gray-700 px-3 py-1 rounded">
                        <span class="text-[9px] font-bold text-white uppercase tracking-widest">2. Costs /
                            Expenses</span>
                    </div>
                    <button onclick="openExpenseModal()"
                        class="no-print flex items-center gap-1.5 bg-gray-700 hover:bg-gray-800 text-white text-[10px] font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-plus text-[9px]"></i> Add Row
                    </button>
                </div>

                <!-- Desktop table -->
                <div class="hidden md:block overflow-x-auto overflow-y-auto max-h-72">
                    <table style="width:100%; border-collapse:collapse; font-size:10px;">
                        <thead>
                            <tr style="background:#374151; color:white; position:sticky; top:0; z-index:1;">
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:32px;">
                                    NO.</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:left; width:120px;">
                                    ACCOUNT TITLE</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:left;">PARTICULARS</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:right; width:100px;">
                                    AMOUNT</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:100px;">
                                    MODE OF PAYMENT</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:90px;">
                                    PAYMENT DATE</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:90px;">
                                    REFERENCE</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:left; width:100px;">
                                    REMARKS</th>
                                <th style="padding:5px 8px; border:1px solid #4b5563; text-align:center; width:60px;"
                                    class="no-print">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="expense-tbody"></tbody>
                        <tfoot>
                            <tr style="background:#fef3c7;">
                                <td colspan="3"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-size:9px; text-transform:uppercase;">
                                    Total Amount Paid :</td>
                                <td id="expense-total"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-family:monospace; text-align:right;"
                                    colspan="6">₱ 0.00</td>
                            </tr>
                            <tr style="background:#f0fdf4;">
                                <td colspan="3"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-size:9px; text-transform:uppercase;">
                                    Possible Income / Loss :</td>
                                <td id="income-loss"
                                    style="padding:5px 8px; border:1px solid #e5e7eb; font-weight:700; font-family:monospace; text-align:right;"
                                    colspan="6">₱ 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="md:hidden overflow-y-auto max-h-72 rounded-lg border border-gray-100" id="expense-cards">
                    <div class="py-6 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>
                        Loading...</div>
                </div>
                <div
                    class="md:hidden flex items-center justify-between px-3 py-2 bg-yellow-50 border border-yellow-100 rounded-lg mt-2 text-xs font-semibold">
                    <span class="text-gray-500">Total Paid</span>
                    <span id="expense-total-m" class="font-mono text-gray-800">₱ 0.00</span>
                </div>
                <div
                    class="md:hidden flex items-center justify-between px-3 py-2 bg-green-50 border border-green-100 rounded-lg mt-1 text-xs font-semibold">
                    <span class="text-gray-500">Possible Income / Loss</span>
                    <span id="income-loss-m" class="font-mono text-gray-800">₱ 0.00</span>
                </div>
            </div>
            <div
                style="padding:10px 14px; font-size:8px; color:#9ca3af; text-align:right; border-top:1px solid #e5e7eb; margin-top:12px;">
                Generated: <?= date('F d, Y') ?> | <?= htmlspecialchars($project['reference_no'] ?? '') ?>
            </div>
        </div>
    </div>

    <!-- Billing Modal -->
    <div id="billing-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 no-print">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-sm text-gray-800">Add Billing Entry</h3>
                <button onclick="closeBillingModal()" class="text-gray-400 hover:text-red-500"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-6 py-5 space-y-3">
                <input type="hidden" id="b-edit-id">
                <div>
                    <label
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Particulars</label>
                    <input id="b-particulars" type="text"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Amount</label>
                        <input id="b-amount" type="text" inputmode="decimal" oninput="formatAmountInput(this)"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Bank /
                            Check</label>
                        <input id="b-bank-check" type="text"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Payment
                            Date</label>
                        <input id="b-payment-date" type="date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                    <div>
                        <label
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Reference</label>
                        <input id="b-reference" type="text"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Remarks</label>
                    <input id="b-remarks" type="text"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
                <button onclick="closeBillingModal()"
                    class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-200">Cancel</button>
                <button onclick="saveBilling()"
                    class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-all">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Save
                </button>
            </div>
        </div>
    </div>

    <!-- Expense Modal -->
    <div id="expense-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 no-print">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-sm text-gray-800">Add Expense Entry</h3>
                <button onclick="closeExpenseModal()" class="text-gray-400 hover:text-red-500"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-6 py-5 space-y-3">
                <input type="hidden" id="e-edit-id">
                <div>
                    <label
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Particulars</label>
                    <input id="e-particulars" type="text"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Title</label>
                    <input id="e-title" type="text"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Amount</label>
                        <input id="e-amount" type="number" step="0.01"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Mode of
                            Payment</label>
                        <select id="e-mode"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                            <option value="">— Select —</option>
                            <option>Cash</option>
                            <option>Check</option>
                            <option>Bank Transfer</option>
                            <option>GCash</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Payment
                            Date</label>
                        <input id="e-payment-date" type="date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                    <div>
                        <label
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Reference</label>
                        <input id="e-reference" type="text"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                    </div>
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block">Remarks</label>
                    <input id="e-remarks" type="text"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
                <button onclick="closeExpenseModal()"
                    class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-200">Cancel</button>
                <button onclick="saveExpense()"
                    class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-all">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        const PROJECT_ID = <?= $project_id ?>;
        const CONTRACT_AMOUNT = <?= floatval($project['contract_amount'] ?? 0) ?>;

        function formatAmountInput(input) {
            let raw = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const parts = raw.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
        }

        // ─── BILLING ─────────────────────────────────────────────────────────────────

        function fetchBilling() {
            fetch(`${BASE_URL}/fetchprojectbilling?project_id=${PROJECT_ID}`)
                .then(res => res.json())
                .then(data => renderBilling(data));
        }

        function renderBilling(data) {
            const tbody = document.getElementById('billing-tbody');
            const cards = document.getElementById('billing-cards');

            if (!data.length) {
                tbody.innerHTML = `<tr><td colspan="8" style="padding:16px; text-align:center; color:#9ca3af;">No billing entries yet.</td></tr>`;
                cards.innerHTML = `<div class="py-6 text-center text-gray-400 text-sm">No billing entries yet.</div>`;
                document.getElementById('billing-total').textContent = '₱ 0.00';
                document.getElementById('billing-total-m').textContent = '₱ 0.00';
                document.getElementById('billing-balance').textContent = '₱ 0.00';
                document.getElementById('billing-balance-m').textContent = '₱ 0.00';
                return;
            }

            let total = 0;

            // Desktop rows (unchanged)
            tbody.innerHTML = data.map((row, i) => {
                const amt = parseFloat(row.amount) || 0;
                total += amt;
                return `<tr style="border-top:1px solid #e5e7eb; background:${i % 2 === 1 ? '#f9fafb' : 'white'}"
            onmouseenter="this.style.background='#dcfce7'"
            onmouseleave="this.style.background='${i % 2 === 1 ? '#f9fafb' : 'white'}'">
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center; color:#9ca3af;">${i + 1}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb;">${row.particulars ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:right; font-family:monospace;">₱ ${amt.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.bank_check ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.payment_date ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.reference ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb;">${row.remarks ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;" class="no-print">
                <div style="display:flex; gap:4px; justify-content:center;">
                    <button onclick="editBilling(${JSON.stringify(row).replace(/"/g, '&quot;')})"
                        style="background:#f3f4f6; border:none; border-radius:4px; padding:2px 8px; font-size:10px; cursor:pointer;">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button onclick="deleteBilling(${row.id})"
                        style="background:#fee2e2; color:#dc2626; border:none; border-radius:4px; padding:2px 8px; font-size:10px; cursor:pointer;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
            }).join('');

            // Mobile cards
            let mTotal = 0;
            cards.innerHTML = data.map((row, i) => {
                const amt = parseFloat(row.amount) || 0;
                mTotal += amt;
                return `
<div class="flex items-start gap-3 px-3 py-3 border-b border-gray-100 ${i % 2 === 1 ? 'bg-gray-50' : 'bg-white'}">
    <div class="w-1 self-stretch rounded-full bg-orange-400 flex-shrink-0"></div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-gray-800 truncate">${row.particulars ?? '—'}</span>
            <span class="font-mono text-xs font-bold text-orange-600 flex-shrink-0">₱ ${amt.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
        </div>
        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
            ${row.bank_check ? `<span class="text-[10px] text-gray-400">${row.bank_check}</span>` : ''}
            ${row.payment_date ? `<span class="text-[10px] text-gray-400">${row.payment_date}</span>` : ''}
            ${row.reference ? `<span class="text-[10px] text-gray-400">Ref: ${row.reference}</span>` : ''}
            ${row.remarks ? `<span class="text-[10px] text-gray-400 italic">${row.remarks}</span>` : ''}
        </div>
    </div>
    <div class="flex gap-1 flex-shrink-0 no-print">
        <button onclick="editBilling(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 transition">
            <i class="fa-solid fa-pen text-[9px]"></i>
        </button>
        <button onclick="deleteBilling(${row.id})"
            class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-500 transition">
            <i class="fa-solid fa-trash text-[9px]"></i>
        </button>
    </div>
</div>`;
            }).join('');

            const balance = CONTRACT_AMOUNT - total;
            const balFmt = '₱ ' + balance.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const totalFmt = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

            document.getElementById('billing-total').textContent = totalFmt;
            document.getElementById('billing-total-m').textContent = totalFmt;
            document.getElementById('billing-balance').textContent = balFmt;
            document.getElementById('billing-balance-m').textContent = balFmt;
        }

        function renderExpenses(data) {
            const tbody = document.getElementById('expense-tbody');
            const cards = document.getElementById('expense-cards');

            if (!data.length) {
                tbody.innerHTML = `<tr><td colspan="9" style="padding:16px; text-align:center; color:#9ca3af;">No expense entries yet.</td></tr>`;
                cards.innerHTML = `<div class="py-6 text-center text-gray-400 text-sm">No expense entries yet.</div>`;
                ['expense-total', 'expense-total-m', 'income-loss', 'income-loss-m'].forEach(id => {
                    document.getElementById(id).textContent = '₱ 0.00';
                });
                return;
            }

            let total = 0;

            // Desktop rows
            tbody.innerHTML = data.map((row, i) => {
                const amt = parseFloat(row.amount) || 0;
                total += amt;
                return `<tr style="border-top:1px solid #e5e7eb; background:${i % 2 === 1 ? '#f9fafb' : 'white'}"
            onmouseenter="this.style.background='#dcfce7'"
            onmouseleave="this.style.background='${i % 2 === 1 ? '#f9fafb' : 'white'}'">
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center; color:#9ca3af;">${i + 1}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb;">${row.title ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb;">${row.particulars ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:right; font-family:monospace;">₱ ${amt.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.mode_of_payment ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.payment_date ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;">${row.reference ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb;">${row.remarks ?? ''}</td>
            <td style="padding:5px 8px; border:1px solid #e5e7eb; text-align:center;" class="no-print">
                <div style="display:flex; gap:4px; justify-content:center;">
                    <button onclick="editExpense(${JSON.stringify(row).replace(/"/g, '&quot;')})"
                        style="background:#f3f4f6; border:none; border-radius:4px; padding:2px 8px; font-size:10px; cursor:pointer;">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button onclick="deleteExpense(${row.id})"
                        style="background:#fee2e2; color:#dc2626; border:none; border-radius:4px; padding:2px 8px; font-size:10px; cursor:pointer;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
            }).join('');

            // Mobile cards
            cards.innerHTML = data.map((row, i) => {
                const amt = parseFloat(row.amount) || 0;
                return `
<div class="flex items-start gap-3 px-3 py-3 border-b border-gray-100 ${i % 2 === 1 ? 'bg-gray-50' : 'bg-white'}">
    <div class="w-1 self-stretch rounded-full bg-gray-600 flex-shrink-0"></div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-gray-800 truncate">${row.title ?? '—'}</span>
            <span class="font-mono text-xs font-bold text-gray-700 flex-shrink-0">₱ ${amt.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
        </div>
        <div class="text-[11px] text-gray-500 truncate mt-0.5">${row.particulars ?? ''}</div>
        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1">
            ${row.mode_of_payment ? `<span class="text-[10px] text-gray-400">${row.mode_of_payment}</span>` : ''}
            ${row.payment_date ? `<span class="text-[10px] text-gray-400">${row.payment_date}</span>` : ''}
            ${row.reference ? `<span class="text-[10px] text-gray-400">Ref: ${row.reference}</span>` : ''}
            ${row.remarks ? `<span class="text-[10px] text-gray-400 italic">${row.remarks}</span>` : ''}
        </div>
    </div>
    <div class="flex gap-1 flex-shrink-0 no-print">
        <button onclick="editExpense(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 transition">
            <i class="fa-solid fa-pen text-[9px]"></i>
        </button>
        <button onclick="deleteExpense(${row.id})"
            class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-500 transition">
            <i class="fa-solid fa-trash text-[9px]"></i>
        </button>
    </div>
</div>`;
            }).join('');

            const incomeLoss = CONTRACT_AMOUNT - total;
            const totalFmt = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const ilFmt = (incomeLoss < 0 ? '-' : '') + '₱ ' + Math.abs(incomeLoss).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const ilColor = incomeLoss < 0 ? 'text-red-600' : 'text-green-600';

            document.getElementById('expense-total').textContent = totalFmt;
            document.getElementById('expense-total-m').textContent = totalFmt;

            ['income-loss', 'income-loss-m'].forEach(id => {
                const el = document.getElementById(id);
                el.textContent = ilFmt;
                el.style.color = incomeLoss < 0 ? '#dc2626' : '#16a34a';
            });

            // Update mobile income-loss card color
            const ilMCard = document.getElementById('income-loss-m').closest('div');
            if (ilMCard) {
                ilMCard.className = ilMCard.className.replace(/bg-\w+-50/, incomeLoss < 0 ? 'bg-red-50' : 'bg-green-50');
                ilMCard.className = ilMCard.className.replace(/border-\w+-100/, incomeLoss < 0 ? 'border-red-100' : 'border-green-100');
            }
        }

        function openBillingModal(clear = true) {
            if (clear) {
                ['b-edit-id', 'b-particulars', 'b-amount', 'b-bank-check', 'b-payment-date', 'b-reference', 'b-remarks']
                    .forEach(id => document.getElementById(id).value = '');
            }
            document.getElementById('billing-modal').classList.remove('hidden');
        }

        function closeBillingModal() {
            document.getElementById('billing-modal').classList.add('hidden');
        }

        function editBilling(row) {
            document.getElementById('b-edit-id').value = row.id;
            document.getElementById('b-particulars').value = row.particulars ?? '';
            document.getElementById('b-amount').value = row.amount
                ? parseFloat(row.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })
                : '';
            document.getElementById('b-bank-check').value = row.bank_check ?? '';
            document.getElementById('b-payment-date').value = row.payment_date ?? '';
            document.getElementById('b-reference').value = row.reference ?? '';
            document.getElementById('b-remarks').value = row.remarks ?? '';
            openBillingModal(false);
        }

        function saveBilling() {
            const payload = {
                id: document.getElementById('b-edit-id').value || null,
                project_id: PROJECT_ID,
                particulars: document.getElementById('b-particulars').value,
                amount: document.getElementById('b-amount').value.replace(/,/g, ''),
                bank_check: document.getElementById('b-bank-check').value,
                payment_date: document.getElementById('b-payment-date').value,
                reference: document.getElementById('b-reference').value,
                remarks: document.getElementById('b-remarks').value,
            };
            fetch(`${BASE_URL}/saveprojectbilling`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(d => {
                if (d.success) { closeBillingModal(); fetchBilling(); showToast('Billing saved!'); }
                else alert('Error: ' + (d.error ?? 'Unknown'));
            });
        }

        function deleteBilling(id) {
            fetch(`${BASE_URL}/deleteprojectbilling`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            }).then(r => r.json()).then(d => { if (d.success) fetchBilling(); });
        }

        // ─── EXPENSES ─────────────────────────────────────────────────────────────────

        function fetchExpenses() {
            fetch(`${BASE_URL}/fetchprojectexpense?project_id=${PROJECT_ID}`)
                .then(res => res.json())
                .then(data => renderExpenses(data));
        }



        function openExpenseModal(clear = true) {
            if (clear) {
                ['e-edit-id', 'e-title', 'e-particulars', 'e-amount', 'e-mode', 'e-payment-date', 'e-reference', 'e-remarks']
                    .forEach(id => document.getElementById(id).value = '');
            }
            document.getElementById('expense-modal').classList.remove('hidden');
        }

        function closeExpenseModal() {
            document.getElementById('expense-modal').classList.add('hidden');
        }

        function editExpense(row) {
            document.getElementById('e-edit-id').value = row.id;
            document.getElementById('e-particulars').value = row.particulars ?? '';
            document.getElementById('e-amount').value = row.amount ?? '';
            document.getElementById('e-mode').value = row.mode_of_payment ?? '';
            document.getElementById('e-payment-date').value = row.payment_date ?? '';
            document.getElementById('e-reference').value = row.reference ?? '';
            document.getElementById('e-remarks').value = row.remarks ?? '';
            document.getElementById('e-title').value = row.title ?? '';
            openExpenseModal(false);
        }

        function saveExpense() {
            const payload = {
                id: document.getElementById('e-edit-id').value || null,
                project_id: PROJECT_ID,
                title: document.getElementById('e-title').value,
                particulars: document.getElementById('e-particulars').value,
                amount: document.getElementById('e-amount').value,
                mode_of_payment: document.getElementById('e-mode').value,
                payment_date: document.getElementById('e-payment-date').value,
                reference: document.getElementById('e-reference').value,
                remarks: document.getElementById('e-remarks').value,
            };
            fetch(`${BASE_URL}/saveprojectexpense`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(d => {
                if (d.success) { closeExpenseModal(); fetchExpenses(); showToast('Expense saved!'); }
                else alert('Error: ' + (d.error ?? 'Unknown'));
            });
        }

        function deleteExpense(id) {
            fetch(`${BASE_URL}/deleteprojectexpense`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            }).then(r => r.json()).then(d => { if (d.success) fetchExpenses(); });
        }

        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'fixed bottom-6 right-6 z-[999] bg-green-500 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 opacity-0 transition-all duration-300 no-print';
            t.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${msg}`;
            document.body.appendChild(t);
            requestAnimationFrame(() => t.classList.remove('opacity-0'));
            setTimeout(() => { t.classList.add('opacity-0'); setTimeout(() => t.remove(), 300); }, 3000);
        }

        async function autoSaveIncomeLoss() {
            const expRes = await fetch(`${BASE_URL}/fetchprojectexpense?project_id=${PROJECT_ID}`);
            const expData = await expRes.json();
            const totalExpenses = expData.reduce((sum, r) => sum + parseFloat(r.amount || 0), 0);
            const incomeLoss = CONTRACT_AMOUNT - totalExpenses;

            await fetch(`${BASE_URL}/saveincomeloss`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: PROJECT_ID, income_loss: incomeLoss })
            });
        }

        fetchBilling();
        fetchExpenses();
        autoSaveIncomeLoss();
        setInterval(() => { fetchBilling(); fetchExpenses(); autoSaveIncomeLoss(); }, 15000);
    </script>
</body>

</html>