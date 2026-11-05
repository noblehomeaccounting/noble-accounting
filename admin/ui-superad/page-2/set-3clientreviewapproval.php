<!-- clientreviewapproval.php -->
<!-- Client Review & Approval (design progress) -->
<div class="mb-3">
    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Client Review &amp;
        Approval</p>
</div>
<div id="monDesignProgress" class="mb-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="h-3 w-full rounded bg-gray-100 animate-pulse mb-2"></div>
        <div class="h-3 w-3/4 rounded bg-gray-100 animate-pulse"></div>
    </div>
</div>

<script>
    function monRenderDesignProgress(dp) {
        const container = document.getElementById('monDesignProgress');
        const pct = Number(dp.progress || 0);

        const confirmedBlock = dp.confirmed
            ? `
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap bg-green-50 text-green-700 border-green-200">
                        <i class="fa-solid fa-circle-check"></i>Client Review &amp; Approval
                    </span>
                    <p class="text-xs text-gray-400">${monFormatDateTimeLong(dp.confirmed_at)} · by ${monEscapeHtml(dp.confirmed_by_name)}</p>
                </div>
            `
            : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap bg-amber-50 text-amber-700 border-amber-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Not Yet Confirmed
               </span>`;

        container.innerHTML = `
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <p class="text-[13px] text-gray-500">Design Progress</p>
                    <p class="text-sm font-semibold text-gray-800">${pct}%</p>
                </div>
                <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
                    <div class="h-full rounded-full bg-amber-600 transition-all" style="width:${pct}%"></div>
                </div>
                ${confirmedBlock}
            </div>
        `;
    }
</script>