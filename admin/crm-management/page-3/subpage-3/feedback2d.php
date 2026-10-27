<?php
// feedback2d.php
// Feedback ng Cutting sa 2D + REVISION HISTORY.
// Hindi na pinapalitan ang lumang 2D: bawat upload ay bagong revision (Original → Rev 1 → Rev 2 ...)
// na may petsa/oras, "What changed", at kung aling feedback ang sinagot. Nasa crm2drevisionajax.php ang backend.
?>
<script>
    const Q2D_REV_URL = <?= json_encode(BASE_URL . '/crm2drevisionajax') ?>;
    const Q2D_REV_MAX_FILES = 10;
    const Q2D_REV_MAX_BYTES = 15 * 1024 * 1024;

    let q2dFbLast = [];            // huling feedback list galing sa state
    let q2dFbSig = '';
    let q2dRevList = [];           // revisions (pinakabago muna)
    let q2dRevNextNo = 1;
    let q2dRevFiles = [];          // napiling PDF para sa bagong revision
    let q2dRevChecked = null;      // Set ng feedback id na sinasagot (null = default lahat ng bukas)
    let q2dRevSubmitting = false;

    function q2dFbEsc(s) { return (typeof q2dEscapeHtml === 'function') ? q2dEscapeHtml(s) : String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function q2dFbSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

    // Alin ang revision na sumagot sa isang feedback
    function q2dFbResolvedIn(fid) {
        return q2dRevList.find(r => (r.answers || []).some(a => a.id === fid)) || null;
    }

    // ── Isang feedback card ──
    function q2dFeedbackItemsHtml(feedback, opts = {}) {
        return feedback.map(f => {
            const rev = f.is_resolved ? q2dFbResolvedIn(f.id) : null;
            const checkable = opts.checkable && !f.is_resolved;
            const checked = checkable && (q2dRevChecked ? q2dRevChecked.has(f.id) : true);
            return `
            <div class="rounded-xl border ${f.is_resolved ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50'} px-3 py-2.5">
                <div class="flex items-start gap-2.5">
                    ${checkable ? `<input type="checkbox" class="mt-0.5 w-4 h-4 accent-amber-700 shrink-0 cursor-pointer" ${checked ? 'checked' : ''}
                        onchange="q2dRevToggleFb(${f.id}, this.checked)" title="This revision answers this feedback">` : `<i class="fa-solid ${f.is_resolved ? 'fa-circle-check text-emerald-500' : 'fa-scissors text-red-500'} text-xs mt-1 shrink-0"></i>`}
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${q2dFbEsc(f.message)}</p>
                        <p class="text-[10.5px] text-gray-400 mt-1"><i class="fa-regular fa-clock mr-0.5"></i>${q2dFbEsc(f.created_at)} · Cutting · ${q2dFbEsc(f.created_by_name)}</p>
                        ${f.is_resolved
                    ? `<p class="text-[10.5px] font-semibold text-emerald-700 mt-0.5"><i class="fa-solid fa-check mr-0.5"></i>Resolved${rev ? ` in ${q2dFbEsc(rev.label)} · ${q2dFbEsc(rev.created_at)}` : ''}</p>`
                    : `<p class="text-[10.5px] font-semibold text-red-700 mt-0.5">Needs adjustment</p>`}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    // ── Timeline ng revisions (pinakabago sa itaas, luma sa ibaba) ──
    function q2dRevTimelineHtml() {
        if (!q2dRevList.length) {
            return '<p class="text-[12px] text-gray-400 italic">No 2D file yet.</p>';
        }
        return `<ol class="relative border-l-2 border-gray-200 ml-2 space-y-3">${q2dRevList.map(r => `
            <li class="relative ml-4">
                <span class="absolute -left-[25px] top-3 w-4 h-4 rounded-full border-2 border-white ${r.is_current ? 'bg-amber-600' : 'bg-gray-300'}"></span>
                <div class="rounded-xl border ${r.is_current ? 'border-amber-300 bg-amber-50/50' : 'border-gray-200 bg-white'} px-3 py-2.5">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10.5px] font-bold ${r.revision_no === 0 ? 'bg-gray-100 text-gray-700' : 'bg-amber-700 text-white'}">${q2dFbEsc(r.label)}</span>
                        ${r.is_current ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-emerald-100 text-emerald-700">Current</span>' : '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-gray-100 text-gray-500">Previous</span>'}
                        <span class="ml-auto text-[10.5px] text-gray-500 whitespace-nowrap"><i class="fa-regular fa-clock mr-0.5"></i>${q2dFbEsc(r.created_at || '—')}</span>
                    </div>
                    <p class="text-[10.5px] text-gray-400 mt-1"><i class="fa-solid fa-user-pen mr-0.5"></i>${q2dFbEsc(r.created_by_name)} (${q2dFbEsc(r.created_role)})</p>
                    ${r.change_notes ? `
                        <div class="mt-2 rounded-lg bg-white border border-gray-200 px-2.5 py-2">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">What changed</p>
                            <p class="text-[12px] text-gray-800 whitespace-pre-line break-words">${q2dFbEsc(r.change_notes)}</p>
                        </div>` : (r.revision_no === 0 ? '<p class="text-[11px] text-gray-400 italic mt-1.5">First 2D file submitted.</p>' : '')}
                    ${(r.answers || []).length ? `
                        <div class="mt-2">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Answers feedback</p>
                            ${r.answers.map(a => `<p class="text-[11.5px] text-gray-600 border-l-2 border-red-200 pl-2 mb-1 whitespace-pre-line break-words">"${q2dFbEsc(a.message)}" <span class="text-gray-400">· ${q2dFbEsc(a.created_at)}</span></p>`).join('')}
                        </div>` : ''}
                    <div class="mt-2 space-y-1">${(r.files || []).map(f => `
                        <a href="${q2dFbEsc(f.url)}" target="_blank" rel="noopener" title="${q2dFbEsc(f.name)}"
                            class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] text-gray-800 hover:border-amber-600 hover:text-amber-800 min-w-0">
                            <i class="fa-solid fa-file-pdf text-red-500 shrink-0"></i><span class="truncate flex-1">${q2dFbEsc(f.name)}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-gray-400 shrink-0"></i>
                        </a>`).join('')}</div>
                </div>
            </li>`).join('')}</ol>`;
    }

    // ── Form para sa bagong revision (designer lang, kapag may bukas na feedback) ──
    function q2dFeedbackUploadHtml() {
        return `
            <div class="rounded-xl border border-amber-200 bg-white overflow-hidden">
                <div class="px-3.5 py-2.5 bg-amber-50 border-b border-amber-200 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10.5px] font-bold bg-amber-700 text-white" id="q2dRevNextLabel">Rev ${q2dRevNextNo}</span>
                    <p class="text-[12.5px] font-semibold text-amber-900">Upload a revised 2D</p>
                </div>
                <div class="p-3.5 space-y-3">
                    <p class="text-[11px] text-gray-500"><i class="fa-solid fa-circle-info mr-1 text-amber-600"></i>The old file is kept in the history below. Tick the feedback this revision answers above.</p>
                    <div>
                        <label for="q2dFeedbackFileInput" id="q2dRevDrop"
                            class="flex flex-col items-center justify-center gap-1 border-[1.5px] border-dashed border-gray-300 rounded-xl py-4 px-3 cursor-pointer bg-gray-50 hover:border-amber-600 hover:bg-amber-50 transition-colors text-center">
                            <i class="fa-solid fa-cloud-arrow-up text-amber-700"></i>
                            <span id="q2dFeedbackFileLabel" class="text-[12px] font-semibold text-gray-700">Attach revised 2D PDF(s)</span>
                            <span class="text-[10px] text-gray-400">PDF only · max 15MB each · or drag here</span>
                        </label>
                        <input id="q2dFeedbackFileInput" type="file" accept="application/pdf" multiple class="hidden" onchange="q2dSendRevisedPdf(this)">
                        <ul id="q2dRevFileList" class="mt-2 space-y-1 max-h-36 overflow-y-auto"></ul>
                    </div>
                    <div>
                        <label for="q2dRevNotes" class="block text-[11.5px] font-semibold text-gray-700 mb-1">What changed? <span class="text-red-500">*</span></label>
                        <textarea id="q2dRevNotes" rows="3" maxlength="2000" oninput="q2dRevUpdateState()"
                            placeholder="e.g. Moved the sink 300mm to the left, added pantry cabinet"
                            class="w-full rounded-lg border border-gray-300 text-[12.5px] px-3 py-2 resize-y outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100"></textarea>
                    </div>
                    <p id="q2dRevHint" class="text-[11px] text-gray-400"></p>
                    <div id="q2dRevIdle">
                        <button type="button" id="q2dRevSubmitBtn" onclick="q2dRevAsk(true)" disabled
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                            <i class="fa-solid fa-paper-plane"></i> Send revision to Cutting
                        </button>
                    </div>
                    <div id="q2dRevConfirm" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>Send <span id="q2dRevConfirmLabel">Rev ${q2dRevNextNo}</span> to Cutting?</p>
                        <p id="q2dRevConfirmText" class="text-[11.5px] text-gray-700 mt-1"></p>
                        <div class="grid grid-cols-2 gap-2 mt-2.5">
                            <button type="button" onclick="q2dRevAsk(false)" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Go back</button>
                            <button type="button" id="q2dRevYes" onclick="q2dRevSubmit()" class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg"><i class="fa-solid fa-check"></i> Yes, send</button>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    function q2dFbSectionTitle(icon, text, extra = '') {
        return `<p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mb-2 flex items-center gap-1.5"><i class="fa-solid ${icon} text-amber-700"></i>${text}${extra}</p>`;
    }

    // ── Gumawa ng structure ng sidebar isang beses; ang mga bahagi ay ina-update na lang ──
    function q2dFbEnsureLayout(body) {
        if (body.querySelector('#q2dFbOpen')) return;
        body.innerHTML = `
            <div class="space-y-5">
                <section id="q2dFbOpenWrap"><div id="q2dFbOpen"></div></section>
                <section id="q2dFbFormWrap" class="hidden"></section>
                <section>
                    ${q2dFbSectionTitle('fa-clock-rotate-left', '2D revision history', ' <span id="q2dRevCount" class="text-gray-400 font-semibold normal-case tracking-normal"></span>')}
                    <div id="q2dRevTimeline"><div class="space-y-2"><div class="h-16 rounded-xl bg-gray-100 animate-pulse"></div><div class="h-16 rounded-xl bg-gray-100 animate-pulse"></div></div></div>
                </section>
                <section id="q2dFbResolvedWrap" class="hidden"></section>
            </div>`;
        // mas malapad na sidebar para sa timeline
        const sb = document.getElementById('q2dFeedbackSidebar');
        if (sb) { sb.classList.remove('max-w-sm'); sb.classList.add('max-w-md'); }
    }

    function q2dFbRenderParts() {
        const feedback = q2dFbLast || [];
        const open = feedback.filter(f => !f.is_resolved);
        const resolved = feedback.filter(f => f.is_resolved);
        const canUpload = open.length > 0 && !Q2D_IS_SALES;

        const openEl = document.getElementById('q2dFbOpen');
        if (!openEl) return;
        openEl.innerHTML = open.length
            ? q2dFbSectionTitle('fa-scissors', `Open feedback from Cutting (${open.length})`) + `<div class="space-y-2">${q2dFeedbackItemsHtml(open, { checkable: canUpload })}</div>`
            : (feedback.length
                ? `<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 flex items-center gap-2.5"><i class="fa-solid fa-circle-check text-emerald-600"></i><p class="text-[12.5px] font-semibold text-emerald-800">All Cutting feedback is resolved.</p></div>`
                : `<div class="rounded-xl border border-gray-200 bg-white px-3.5 py-3 flex items-center gap-2.5"><i class="fa-regular fa-comment text-gray-400"></i><p class="text-[12.5px] text-gray-500">No feedback from Cutting yet.</p></div>`);

        // Form — huwag i-reset kapag may laman na (pinipili o sinusulat)
        const formWrap = document.getElementById('q2dFbFormWrap');
        if (canUpload) {
            if (!formWrap.querySelector('#q2dRevNotes')) {
                formWrap.innerHTML = q2dFeedbackUploadHtml();
                q2dRevWireDrop();
            }
            formWrap.classList.remove('hidden');
            const lbl = document.getElementById('q2dRevNextLabel');
            if (lbl) lbl.textContent = `Rev ${q2dRevNextNo}`;
            const lbl2 = document.getElementById('q2dRevConfirmLabel');
            if (lbl2) lbl2.textContent = `Rev ${q2dRevNextNo}`;
            q2dRevRenderFiles();
        } else {
            formWrap.innerHTML = '';
            formWrap.classList.add('hidden');
            q2dRevFiles = [];
        }

        const resWrap = document.getElementById('q2dFbResolvedWrap');
        if (resolved.length) {
            resWrap.classList.remove('hidden');
            resWrap.innerHTML = `
                <details class="group" ${open.length ? '' : 'open'}>
                    <summary class="list-none cursor-pointer text-[10.5px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5 mb-2">
                        <i class="fa-solid fa-check-double text-emerald-600"></i>Resolved feedback (${resolved.length})
                        <i class="fa-solid fa-chevron-down text-[9px] ml-auto transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">${q2dFeedbackItemsHtml(resolved)}</div>
                </details>`;
        } else {
            resWrap.classList.add('hidden');
            resWrap.innerHTML = '';
        }

        const tl = document.getElementById('q2dRevTimeline');
        if (tl && q2dRevLoaded) tl.innerHTML = `<div class="max-h-[60vh] overflow-y-auto pr-1">${q2dRevTimelineHtml()}</div>`;
        const cnt = document.getElementById('q2dRevCount');
        if (cnt) cnt.textContent = q2dRevList.length ? `· ${q2dRevList.length} version${q2dRevList.length === 1 ? '' : 's'}` : '';

        q2dRevUpdateState();
    }

    let q2dRevLoaded = false;
    async function q2dRevLoad() {
        try {
            const res = await fetch(`${Q2D_REV_URL}?action=list&inquiry_id=${Q2D_INQUIRY_ID}`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) {
                const tl = document.getElementById('q2dRevTimeline');
                if (tl) tl.innerHTML = `<p class="text-[12px] text-red-600">${q2dFbEsc(data.message || 'Could not load the revision history.')}</p>`;
                return;
            }
            q2dRevList = data.revisions || [];
            q2dRevNextNo = data.next_revision_no || 1;
            q2dRevLoaded = true;
            q2dFbRenderParts();
        } catch (e) {
            console.error('q2dRevLoad:', e);
        }
    }

    // Tinatawag ng 2D page tuwing nagre-render (galing sa state.cutting_feedback)
    function q2dRenderCuttingFeedback(feedback) {
        const badge = document.getElementById('q2dFeedbackBadge');
        const body = document.getElementById('q2dFeedbackSidebarBody');
        if (!badge || !body) return '';

        feedback = feedback || [];
        const unresolvedCount = feedback.filter(f => !f.is_resolved).length;

        if (unresolvedCount > 0) {
            badge.textContent = unresolvedCount;
            badge.classList.remove('hidden');
            badge.classList.add('flex');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('flex');
        }

        q2dFbLast = feedback;
        q2dFbEnsureLayout(body);

        // bagong feedback na hindi pa pinili → default naka-check
        if (q2dRevChecked) feedback.forEach(f => { if (!f.is_resolved && !q2dFbSig.includes(`|${f.id}:`)) q2dRevChecked.add(f.id); });

        const sig = feedback.map(f => `|${f.id}:${f.is_resolved ? 1 : 0}`).join('');
        const changed = sig !== q2dFbSig;
        q2dFbSig = sig;

        q2dFbRenderParts();
        if (changed || !q2dRevLoaded) q2dRevLoad();

        return '';
    }

    // ── Pagpili ng feedback na sinasagot ──
    function q2dRevToggleFb(id, on) {
        if (!q2dRevChecked) q2dRevChecked = new Set(q2dFbLast.filter(f => !f.is_resolved).map(f => f.id));
        if (on) q2dRevChecked.add(id); else q2dRevChecked.delete(id);
        q2dRevUpdateState();
    }
    function q2dRevSelectedIds() {
        const open = q2dFbLast.filter(f => !f.is_resolved).map(f => f.id);
        return q2dRevChecked ? open.filter(id => q2dRevChecked.has(id)) : open;
    }

    // ── Files ──
    function q2dRevAddFiles(list) {
        let err = '';
        for (const f of list) {
            const isPdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
            if (!isPdf) { err = `"${f.name}" is not a PDF.`; continue; }
            if (f.size > Q2D_REV_MAX_BYTES) { err = `"${f.name}" exceeds the 15MB limit.`; continue; }
            if (q2dRevFiles.some(x => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified)) continue;
            if (q2dRevFiles.length >= Q2D_REV_MAX_FILES) { err = `Maximum of ${Q2D_REV_MAX_FILES} files per revision.`; break; }
            q2dRevFiles.push(f);
        }
        if (err && typeof crmShowToast === 'function') crmShowToast(q2dFbEsc(err), 'error');
        q2dRevRenderFiles();
        q2dRevUpdateState();
    }

    // (pangalan ay galing sa lumang flow — ngayon ay nagdadagdag lang ng file sa listahan, hindi nag-a-upload agad)
    function q2dSendRevisedPdf(inputEl) {
        const picked = Array.from(inputEl.files || []);
        inputEl.value = '';
        q2dRevAddFiles(picked);
    }

    function q2dRevRemoveFile(i) {
        q2dRevFiles.splice(i, 1);
        q2dRevRenderFiles();
        q2dRevUpdateState();
    }

    function q2dRevRenderFiles() {
        const ul = document.getElementById('q2dRevFileList');
        const label = document.getElementById('q2dFeedbackFileLabel');
        if (!ul) return;
        ul.innerHTML = q2dRevFiles.map((f, i) => `
            <li class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11.5px] min-w-0">
                <i class="fa-solid fa-file-pdf text-red-500 shrink-0"></i>
                <span class="truncate flex-1 text-gray-800" title="${q2dFbEsc(f.name)}">${q2dFbEsc(f.name)}</span>
                <span class="text-[10px] text-gray-400 shrink-0">${q2dFbSize(f.size)}</span>
                <button type="button" onclick="q2dRevRemoveFile(${i})" aria-label="Remove"
                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-red-600 hover:bg-white shrink-0"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
            </li>`).join('');
        if (label) label.textContent = q2dRevFiles.length ? `${q2dRevFiles.length} file${q2dRevFiles.length === 1 ? '' : 's'} selected — add more` : 'Attach revised 2D PDF(s)';
    }

    function q2dRevWireDrop() {
        const drop = document.getElementById('q2dRevDrop');
        if (!drop) return;
        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('border-amber-600', 'bg-amber-50'); }));
        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('border-amber-600', 'bg-amber-50'); }));
        drop.addEventListener('drop', e => q2dRevAddFiles(Array.from(e.dataTransfer.files || [])));
    }

    // ── Validation + submit ──
    function q2dRevUpdateState() {
        const btn = document.getElementById('q2dRevSubmitBtn');
        const hint = document.getElementById('q2dRevHint');
        if (!btn) return;
        const notes = (document.getElementById('q2dRevNotes')?.value || '').trim();
        let msg = '';
        if (!q2dRevFiles.length) msg = 'Attach the revised 2D PDF.';
        else if (notes.length < 5) msg = 'Describe what changed in this revision.';
        else if (!q2dRevSelectedIds().length) msg = 'Tick at least one feedback that this revision answers.';
        btn.disabled = !!msg || q2dRevSubmitting;
        if (hint) {
            hint.innerHTML = msg ? `<i class="fa-solid fa-circle-info mr-1"></i>${msg}` : '<i class="fa-solid fa-circle-check mr-1"></i>Ready to send.';
            hint.className = 'text-[11px] ' + (msg ? 'text-gray-400' : 'text-emerald-700');
        }
        q2dRevAsk(false);
    }

    function q2dRevAsk(show) {
        const idle = document.getElementById('q2dRevIdle');
        const conf = document.getElementById('q2dRevConfirm');
        if (!idle || !conf) return;
        if (show) {
            const n = q2dRevSelectedIds().length;
            document.getElementById('q2dRevConfirmText').innerHTML =
                `${q2dRevFiles.length} PDF${q2dRevFiles.length === 1 ? '' : 's'} · answers <b>${n}</b> feedback. The previous 2D stays in the history; Cutting will need to verify the new file again.`;
        }
        idle.classList.toggle('hidden', !!show);
        conf.classList.toggle('hidden', !show);
    }

    async function q2dRevSubmit() {
        if (q2dRevSubmitting) return;
        const notes = (document.getElementById('q2dRevNotes')?.value || '').trim();
        const ids = q2dRevSelectedIds();
        if (!q2dRevFiles.length || notes.length < 5 || !ids.length) { q2dRevUpdateState(); return; }

        q2dRevSubmitting = true;
        const yes = document.getElementById('q2dRevYes');
        if (yes) { yes.disabled = true; yes.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…'; }
        const reset = () => { q2dRevSubmitting = false; if (yes) { yes.disabled = false; yes.innerHTML = '<i class="fa-solid fa-check"></i> Yes, send'; } };

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('inquiry_id', Q2D_INQUIRY_ID);
        fd.append('change_notes', notes);
        fd.append('feedback_ids', JSON.stringify(ids));
        q2dRevFiles.forEach(f => fd.append('files[]', f));

        try {
            const res = await fetch(Q2D_REV_URL, { method: 'POST', body: fd });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch (e) { throw new Error(text.slice(0, 200) || 'Invalid server response'); }

            if (!data.success) {
                crmShowToast(q2dFbEsc(data.message || 'Something went wrong.'), 'error');
                reset();
                q2dRevAsk(false);
                return;
            }

            crmShowToast(q2dFbEsc(data.message || 'Revision sent to Cutting.'));
            if (data.warning) crmShowToast(q2dFbEsc(data.warning), 'error', 6000);

            // linisin ang form
            q2dRevFiles = [];
            q2dRevChecked = null;
            q2dRevSubmitting = false;
            const wrap = document.getElementById('q2dFbFormWrap');
            if (wrap) wrap.innerHTML = '';

            if (typeof q2dLastSignature !== 'undefined') q2dLastSignature = '';
            q2dFbSig = '';
            if (typeof q2dFetchState === 'function') await q2dFetchState();
            await q2dRevLoad();
        } catch (e) {
            console.error('q2dRevSubmit:', e);
            crmShowToast('Connection error: ' + q2dFbEsc(e.message || 'please try again.'), 'error');
            reset();
            q2dRevAsk(false);
        }
    }

    // ── Sidebar open/close ──
    function q2dOpenFeedbackSidebar() {
        document.getElementById('q2dFeedbackOverlay').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('q2dFeedbackSidebar').classList.remove('translate-x-full');
        q2dRevLoad();
    }

    function q2dCloseFeedbackSidebar() {
        document.getElementById('q2dFeedbackOverlay').classList.add('opacity-0', 'pointer-events-none');
        document.getElementById('q2dFeedbackSidebar').classList.add('translate-x-full');
    }

    function q2dToggleFeedbackSidebar() {
        const sidebar = document.getElementById('q2dFeedbackSidebar');
        if (sidebar.classList.contains('translate-x-full')) {
            q2dOpenFeedbackSidebar();
        } else {
            q2dCloseFeedbackSidebar();
        }
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') q2dCloseFeedbackSidebar();
    });
</script>