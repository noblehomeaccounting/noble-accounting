<?php
//step3.php
$crmEditMode = $crmEditMode ?? false;
$crmEditRow = $crmEditRow ?? null;
$crmFmtDt = function ($v, $fmt) {
    $t = $v ? strtotime((string) $v) : false;
    return $t ? date($fmt, $t) : '';
};
?>
<div class="crm-step-panel hidden" id="crm-step-3">
    <div class="flex items-center gap-3 mb-5">
        <span
            class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0"><i
                class="fa-solid fa-users-gear"></i></span>
        <div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">Assignment</h2>
            <p class="text-xs text-gray-400 mt-0.5">Who's handling this inquiry, and what happens next.</p>
        </div>
    </div>

    <!-- Sales Staff (auto-assigned) -->
    <div class="flex items-center gap-3 rounded-2xl bg-white border border-gray-200 px-4 py-3.5 mb-4">
        <span
            class="w-11 h-11 rounded-full bg-amber-700 text-white flex items-center justify-center text-sm font-bold shrink-0">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($currentSalesName ?: '?', 0, 1))) ?>
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Sales Staff</p>
            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($currentSalesName) ?></p>
            <p class="text-[11px] text-gray-400">You ·
                <?= htmlspecialchars(($crmEditMode ? ($crmEditRow['branch'] ?? '') : '') ?: ($_SESSION['branch'] ?? '—')) ?>
                branch</p>
        </div>
        <span
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-bold ring-1 ring-green-600/15">
            <i class="fa-solid fa-check text-[9px]"></i> AUTO-ASSIGNED
        </span>
    </div>

    <?php if ($crmEditMode && $crmEditRow):
        $mDt = $crmFmtDt($crmEditRow['measurement_datetime'] ?? '', 'M d, Y · g:i A');
        $tDt = $crmFmtDt($crmEditRow['target_completion_date'] ?? '', 'M d, Y');
        $hasDesigner = (int) ($crmEditRow['designer_id'] ?? 0) > 0; ?>
        <!-- Edit mode: kasalukuyang assignment (read-only — binabago sa Sales & Market List) -->
        <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 mb-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 mb-3">Current assignment</p>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[12.5px]">
                <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5 min-w-0">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Status</dt>
                    <dd class="font-semibold text-gray-900 truncate">
                        <?= htmlspecialchars((string) ($crmEditRow['status'] ?? '') ?: '—') ?></dd>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5 min-w-0">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Designer</dt>
                    <dd class="font-semibold <?= $hasDesigner ? 'text-gray-900' : 'text-gray-400' ?> truncate">
                        <?= $hasDesigner ? 'Assigned' : 'Not yet assigned' ?></dd>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5 min-w-0">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Measurement</dt>
                    <dd class="font-semibold <?= $mDt ? 'text-gray-900' : 'text-gray-400' ?> truncate">
                        <?= htmlspecialchars($mDt ?: 'Not yet scheduled') ?></dd>
                </div>
            </dl>
            <?php if ($tDt): ?>
                <p class="mt-2 text-[11.5px] text-gray-500"><i class="fa-regular fa-flag mr-1 text-amber-700"></i>Target
                    completion: <b class="text-gray-700"><?= htmlspecialchars($tDt) ?></b></p><?php endif; ?>
            <p class="mt-3 text-[11.5px] text-gray-500"><i
                    class="fa-solid fa-lock text-[10px] mr-1 text-gray-400"></i>Designer, schedule and contract are changed
                from the <b>Sales &amp; Market List</b>, not here.</p>
        </section>
    <?php endif; ?>

    <!-- What happens next — timeline -->
    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 mb-3">
            <?= $crmEditMode ? 'How this inquiry moves forward' : 'What happens after you submit' ?></p>
        <ol class="relative border-l-2 border-dashed border-gray-200 ml-4 space-y-4">
            <li class="relative pl-6">
                <span
                    class="absolute -left-[15px] top-0 w-7 h-7 rounded-full bg-white border border-gray-200 text-gray-400 flex items-center justify-center"><i
                        class="fa-solid fa-pen-ruler text-[11px]"></i></span>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[13px] font-semibold text-gray-900">Designer</p>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold">LATER</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Assigned from the <b>Sales &amp; Market List</b>, together with
                    the confirmed measurement schedule.</p>
                <p id="crm_next_mode_note"
                    class="hidden text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mt-1.5">
                </p>
            </li>
            <li class="relative pl-6">
                <span
                    class="absolute -left-[15px] top-0 w-7 h-7 rounded-full bg-white border border-gray-200 text-gray-400 flex items-center justify-center"><i
                        class="fa-solid fa-file-pen text-[11px]"></i></span>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[13px] font-semibold text-gray-900">2D &amp; Quotation</p>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold">LATER</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">The designer uploads the 2D; the Designer Head and the customer
                    review it.</p>
            </li>
            <li class="relative pl-6">
                <span
                    class="absolute -left-[15px] top-0 w-7 h-7 rounded-full bg-white border border-gray-200 text-gray-400 flex items-center justify-center"><i
                        class="fa-solid fa-peso-sign text-[11px]"></i></span>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[13px] font-semibold text-gray-900">Contract Amount</p>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold">LATER</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Set by Sales in the <b>Final</b> 2D &amp; Quotation.</p>
            </li>
        </ol>
    </section>

    <div class="flex items-center justify-between gap-2 mt-6 pt-5 border-t border-gray-100">
        <button type="button" onclick="crmGoToStep(2)"
            class="inline-flex items-center gap-2 h-11 px-4 rounded-xl text-gray-600 text-sm font-semibold border border-gray-300 bg-white hover:text-gray-800 hover:border-gray-400">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="button" onclick="crmGoToStep4()"
            class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-gray-900 hover:bg-black text-white text-sm font-semibold">
            Next: Review <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>