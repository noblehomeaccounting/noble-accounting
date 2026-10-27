<?php
//step4.php
?>
<div class="crm-step-panel hidden" id="crm-step-4">
    <div class="flex items-center gap-3 mb-5">
        <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0"><i class="fa-solid fa-clipboard-check"></i></span>
        <div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">Review &amp; Submit</h2>
            <p class="text-xs text-gray-400 mt-0.5">Please confirm the details before submitting.</p>
        </div>
    </div>

    <div id="crm_review_content" class="space-y-3"></div>
    <!-- laging kasama sa POST kahit naka-disable ang button habang nagse-save -->
    <input type="hidden" name="submit_inquiry" value="1">

    <label class="mt-4 flex items-start gap-2.5 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-3 cursor-pointer has-[:checked]:bg-green-50 has-[:checked]:border-green-300">
        <input type="checkbox" id="crm_review_confirm" class="mt-0.5 w-4 h-4 accent-amber-700"
            onchange="document.getElementById('crm_submit_btn').disabled = !this.checked">
        <span class="text-[12px] text-amber-900 leading-relaxed">I checked the client details and they are correct. <span class="text-amber-700/80">A control number is assigned once submitted.</span></span>
    </label>

    <div class="flex items-center justify-between gap-2 mt-6 pt-5 border-t border-gray-100">
        <button type="button" onclick="crmGoToStep(3)"
            class="inline-flex items-center gap-2 h-11 px-4 rounded-xl text-gray-600 text-sm font-semibold border border-gray-300 bg-white hover:text-gray-800 hover:border-gray-400">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="submit" name="submit_inquiry" id="crm_submit_btn" disabled
            class="inline-flex items-center gap-2 h-11 px-6 rounded-xl bg-amber-700 hover:bg-amber-800 text-white text-sm font-bold shadow-[0_6px_16px_-6px_rgba(180,83,9,.6)] disabled:opacity-45 disabled:cursor-not-allowed disabled:shadow-none">
            <i class="fa-solid fa-paper-plane text-[11px]"></i> Submit Inquiry
        </button>
    </div>
</div>