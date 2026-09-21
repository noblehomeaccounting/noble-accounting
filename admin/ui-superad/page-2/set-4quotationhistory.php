<!-- quotationhistory.php -->
<!-- Timeline: APPROVED Initial + Final submissions only -->
<div class="mb-3">
    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">2D &amp; Quotation
        History</p>
</div>
<div id="monTimeline" class="space-y-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="h-3 w-full rounded bg-gray-100 animate-pulse mb-2"></div>
        <div class="h-3 w-3/4 rounded bg-gray-100 animate-pulse"></div>
    </div>
</div>

<script>
    function monStageBadge(stageGroup, label) {
        const map = {
            'in_progress': 'bg-amber-50 text-amber-700 border-amber-200',
            'for_revision': 'bg-red-50 text-red-700 border-red-200',
            'completed': 'bg-green-50 text-green-700 border-green-200',
            'draft': 'bg-gray-50 text-gray-500 border-gray-200',
        };
        const dotMap = {
            'in_progress': 'bg-amber-500',
            'for_revision': 'bg-red-500',
            'completed': 'bg-green-500',
            'draft': 'bg-gray-400',
        };
        const cls = map[stageGroup] || map['draft'];
        const dot = dotMap[stageGroup] || dotMap['draft'];
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap ${cls}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${monEscapeHtml(label)}
                </span>`;
    }

    // Initial / Final tag — same colors as the Initial and Final pages.
    function monStageTag(stage) {
        const isFinal = stage === 'Final';
        const cls = isFinal
            ? 'bg-green-50 text-green-700 border-green-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';
        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border whitespace-nowrap ${cls}">${isFinal ? 'Final' : 'Initial'}</span>`;
    }

    function monReviewBadge(status) {
        const map = {
            'Approved': 'bg-green-50 text-green-700 border-green-200',
            'For Revision': 'bg-red-50 text-red-700 border-red-200',
            'Pending': 'bg-gray-50 text-gray-500 border-gray-200',
        };
        const labelMap = {
            'Pending': 'Draft', // display-only relabel; change to 'Not Final' / 'Awaiting Confirmation' if preferred
        };
        const key = status || 'Pending';
        const cls = map[key] || map['Pending'];
        const label = labelMap[key] || key;
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${monEscapeHtml(label)}</span>`;
    }

    function monFileLine(label, path, uploaderName, uploaderRole, uploadedAt, reviewStatus, remarks) {
        const link = path
            ? `<a href="${monEscapeHtml(path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline font-medium">View File</a>`
            : `<span class="text-gray-300">Not uploaded</span>`;
        const meta = path
            ? `<span class="text-gray-400"> · ${monEscapeHtml(uploaderName)} (${monEscapeHtml(uploaderRole)}) · ${monFormatDateTimeLong(uploadedAt)}</span>`
            : '';

        const status = reviewStatus || 'Pending';
        let remarksHtml = '';
        if (remarks) {
            remarksHtml = `<p class="text-xs text-red-700 mt-1">${monEscapeHtml(remarks)}</p>`;
        } else if (path && status === 'Pending') {
            remarksHtml = `<p class="text-xs text-red-700 mt-1">Attached — not yet finalized.</p>`;
        }

        return `
            <div class="py-2 border-b border-gray-100 last:border-b-0">
                <div class="flex items-center justify-between gap-3 text-[13px]">
                    <span class="text-gray-500">${monEscapeHtml(label)}</span>
                    ${monReviewBadge(reviewStatus)}
                </div>
                <p class="text-xs mt-0.5">${link}${meta}</p>
                ${remarksHtml}
            </div>
        `;
    }

    // Every cycle that reaches this function is already an Approved submission
    // (the server filters the rest out). The 3D file only shows once the 3D
    // itself is Approved — a 3D still "Waiting" / in revision stays hidden.
    function monRenderCycle(cycle) {
        const showsThreeD = !!cycle.design_3d_path && cycle.design_3d_review_status === 'Approved';

        const multi = cycle.stage_total > 1;
        const cycleLabel = multi
            ? `Approved Submission ${cycle.stage_no} of ${cycle.stage_total}`
            : 'Approved Submission';

        // The 3D file can be approved later than the main review (standalone 3D),
        // so "last reviewed" is whichever of the two is newer.
        let lastReviewed = cycle.reviewed_at;
        if (showsThreeD && cycle.design_3d_reviewed_at && (!lastReviewed || cycle.design_3d_reviewed_at > lastReviewed)) {
            lastReviewed = cycle.design_3d_reviewed_at;
        }

        return `
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        ${monStageTag(cycle.stage)}
                        <p class="text-[10px] text-gray-400 font-semibold tracking-[0.1em] uppercase">${cycleLabel}</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Submitted ${monFormatDateTimeLong(cycle.submitted_at)}</p>
                </div>
                <div class="px-5 py-1">
                    ${monFileLine('2D File', cycle.design_2d_path, cycle.design_2d_uploader_name, cycle.design_2d_uploaded_role, cycle.design_2d_uploaded_at, cycle.design_2d_review_status, cycle.design_2d_remarks)}
                    ${monFileLine('Quotation File', cycle.quotation_path, cycle.quotation_uploader_name, cycle.quotation_uploaded_role, cycle.quotation_uploaded_at, cycle.quotation_review_status, cycle.quotation_remarks)}
                    ${showsThreeD ? monFileLine('3D File', cycle.design_3d_path, cycle.design_3d_uploader_name, cycle.design_3d_uploaded_role, cycle.design_3d_uploaded_at, cycle.design_3d_review_status, cycle.design_3d_remarks) : ''}
                </div>
                ${lastReviewed ? `<div class="px-5 py-2.5 bg-gray-50 border-t border-gray-100"><p class="text-[11px] text-gray-400">Last reviewed ${monFormatDateTimeLong(lastReviewed)}</p></div>` : ''}
            </div>
        `;
    }

    function monRenderTimeline(cycles) {
        const timelineEl = document.getElementById('monTimeline');
        if (!cycles || cycles.length === 0) {
            timelineEl.innerHTML = `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                    <p class="text-sm text-gray-400">No approved 2D and Quotation yet for this inquiry.</p>
                </div>
            `;
            return;
        }

        // Server sends Initial (oldest→newest) then Final (oldest→newest).
        // Reversed, the newest — the Final — greets you at the top.
        timelineEl.innerHTML = [...cycles].reverse().map(monRenderCycle).join('');
    }
</script>