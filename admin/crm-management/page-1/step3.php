<?php
//step3.php
?>
<div class="crm-step-panel" id="crm-step-3">
    <div class="crm-step-head">
        <span class="crm-step-icon"><i class="fa-solid fa-users-gear"></i></span>
        <div>
            <h2 class="crm-step-title">Assignment</h2>
            <p class="crm-step-sub">Who's handling this inquiry.</p>
        </div>
    </div>

    <div class="space-y-3">
        <!-- Sales Staff (auto-assigned) -->
        <div class="flex items-center gap-3 rounded-xl bg-white border border-[#E4DFD1] px-4 py-3.5">
            <span class="w-10 h-10 rounded-full bg-amber-700 text-white flex items-center justify-center text-sm font-bold shrink-0">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($currentSalesName ?: '?', 0, 1))) ?>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Sales Staff</p>
                <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($currentSalesName) ?></p>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-bold ring-1 ring-green-600/15">
                <i class="fa-solid fa-check text-[9px]"></i> AUTO-ASSIGNED
            </span>
        </div>

        <!-- Designer Assign: itinatakda mamaya sa Sales & Market List kasabay ng measurement schedule (crmlistajax.php "assign") -->
        <div class="flex items-center gap-3 rounded-xl bg-[#F6F2E8] border border-dashed border-[#DCD5C3] px-4 py-3.5">
            <span class="w-10 h-10 rounded-full bg-white border border-[#E4DFD1] text-gray-400 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-pen-ruler text-sm"></i>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Designer</p>
                <p class="text-xs text-gray-500">Assigned later from the <b>Sales &amp; Market List</b>, together with the confirmed measurement schedule.</p>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white text-gray-500 text-[10px] font-bold ring-1 ring-gray-300 shrink-0">LATER</span>
        </div>

        <!-- Contract Amount: itinatakda mamaya sa 2D & Quotation (crm2dquotationajax.php save_contract_amount) -->
        <div class="flex items-center gap-3 rounded-xl bg-[#F6F2E8] border border-dashed border-[#DCD5C3] px-4 py-3.5">
            <span class="w-10 h-10 rounded-full bg-white border border-[#E4DFD1] text-gray-400 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-peso-sign text-sm"></i>
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Contract Amount</p>
                <p class="text-xs text-gray-500">Set by Sales once the Quotation file is marked <b>Done</b> in 2D &amp; Quotation.</p>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white text-gray-500 text-[10px] font-bold ring-1 ring-gray-300 shrink-0">LATER</span>
        </div>
    </div>

    <div class="crm-actions justify-between">
        <button type="button" onclick="crmGoToStep(2)" class="crm-btn-ghost">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="button" onclick="crmGoToStep4()" class="crm-btn-primary">
            Next: Review <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>