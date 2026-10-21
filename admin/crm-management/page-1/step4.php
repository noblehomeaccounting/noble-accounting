<?php
//step4.php
?>
<div class="crm-step-panel" id="crm-step-4">
    <div class="crm-step-head">
        <span class="crm-step-icon"><i class="fa-solid fa-clipboard-check"></i></span>
        <div>
            <h2 class="crm-step-title">Review &amp; Submit</h2>
            <p class="crm-step-sub">Please confirm the details before submitting.</p>
        </div>
    </div>

    <div id="crm_review_content" class="space-y-3"></div>
    <!-- laging kasama sa POST kahit naka-disable ang button habang nagse-save -->
    <input type="hidden" name="submit_inquiry" value="1">

    <label class="mt-4 flex items-start gap-2.5 rounded-lg bg-amber-50 border border-amber-200 px-3.5 py-3 cursor-pointer">
        <input type="checkbox" id="crm_review_confirm" class="mt-0.5 w-4 h-4 accent-amber-700"
            onchange="document.getElementById('crm_submit_btn').disabled = !this.checked">
        <span class="text-[11px] text-amber-900 leading-relaxed">I checked the client details and they are correct.</span>
    </label>

    <div class="crm-actions justify-between">
        <button type="button" onclick="crmGoToStep(3)" class="crm-btn-ghost">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="submit" name="submit_inquiry" id="crm_submit_btn" disabled class="crm-btn-submit">
            <i class="fa-solid fa-paper-plane text-[11px]"></i> Submit Inquiry
        </button>
    </div>
</div>