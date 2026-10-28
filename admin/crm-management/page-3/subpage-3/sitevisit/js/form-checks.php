<?php
// sitevisit/js/form-checks.php — checklist, deadline hint, draft, validation, save + inline confirm
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // ── Checklist ──
                const elDt = document.getElementById('sv_datetime');
                const elDeadline = document.getElementById('sv_deadline');
                const notesEls = ['site_conditions', 'client_requirements', 'existing_structure'].map(n => document.getElementById('sv_' + n));
                const visitedVal = () => (form.querySelector('input[name="visited"]:checked') || {}).value || '';

                function svSetCheck(key, ok) {
                    const li = document.querySelector(`#sv_checklist [data-check="${key}"]`);
                    if (!li) return;
                    const ck = li.querySelector('.sv-ck');
                    li.classList.toggle('text-gray-900', ok);
                    li.classList.toggle('text-gray-500', !ok);
                    ck.className = 'sv-ck w-5 h-5 rounded-full flex items-center justify-center text-[9px] shrink-0 ' +
                        (ok ? 'bg-emerald-500 border border-emerald-500 text-white' : 'border border-gray-300 text-transparent');
                }
                function svUpdateChecklist() {
                    svSetCheck('datetime', !!elDt.value);
                    svSetCheck('visited', !!visitedVal());
                    svSetCheck('deadline', !!elDeadline.value);
                    svSetCheck('notes', notesEls.some(t => t.value.trim() !== ''));
                    const spaces = typeof svSpaces === 'undefined' ? [] : svSpaces;
                    const spaceNeeded = visitedVal() !== 'no';
                    svSetCheck('spaces', spaces.length > 0);
                    const reqTag = document.querySelector('#sv_checklist [data-check="spaces"] [data-req]');
                    if (reqTag) {
                        reqTag.textContent = spaceNeeded ? 'Required' : 'Optional';
                        reqTag.className = 'text-[10px] font-semibold uppercase ' + (spaceNeeded ? 'text-red-500' : 'text-gray-300');
                    }
                    svSetCheck('meas', spaces.length > 0 && spaces.every(sp => sp.pdf && sp.pdf.hasFiles()));
                    const generalPhotos = typeof svGeneralPhotos === 'undefined' ? 0 : svGeneralPhotos.count();
                    svSetCheck('photos', generalPhotos > 0 || spaces.some(sp => sp.photo && sp.photo.hasFiles()));
                }

                // Deadline hint (ilang araw pa)
                function svDeadlineHint() {
                    const hint = document.getElementById('sv_deadline_hint');
                    if (!elDeadline.value) { hint.textContent = 'Optional — when you expect to finish the 2D & quotation.'; hint.className = 'text-[11px] text-gray-400 mt-1'; return; }
                    const a = new Date(); a.setHours(0, 0, 0, 0);
                    const b = new Date(elDeadline.value + 'T00:00:00');
                    const d = Math.round((b - a) / 864e5);
                    hint.textContent = d === 0 ? 'Due today' : d === 1 ? 'Due tomorrow' : d > 1 ? `${d} days from today` : 'This date has passed';
                    hint.className = 'text-[11px] mt-1 font-semibold ' + (d < 0 ? 'text-red-600' : d <= 3 ? 'text-amber-700' : 'text-gray-500');
                }

                // ── Draft (text fields only) ──
                const draftFields = ['visit_datetime', 'deadline', 'site_conditions', 'client_requirements', 'existing_structure'];
                const hasServerOld = <?= !empty($svOld) ? 'true' : 'false' ?>;
                let draftTimer = null;
                function svSaveDraft() {
                    clearTimeout(draftTimer);
                    draftTimer = setTimeout(() => {
                        const data = { visited: visitedVal() };
                        draftFields.forEach(n => data[n] = form.elements[n].value);
                        data.spaces = svSpaces.map(sp => ({ name: sp.nameInput.value, requirements: sp.reqInput.value, notes: sp.notesInput.value }));
                        const empty = Object.entries(data).every(([k, v]) => k === 'spaces' ? !v.length : !v);
                        try {
                            if (empty) localStorage.removeItem(SV_DRAFT_KEY);
                            else localStorage.setItem(SV_DRAFT_KEY, JSON.stringify(data));
                            document.getElementById('sv_draft_note').classList.toggle('hidden', empty);
                        } catch (e) { }
                    }, 400);
                }
                function svLoadDraft() {
                    if (hasServerOld) { // galing sa server (may error) ang laman — mas bago iyon
                        SV_OLD_SPACES.forEach(sp => svAddSpace(sp.name, sp.notes, { silent: true }, sp.requirements));
                        return;
                    }
                    let data = null;
                    try { data = JSON.parse(localStorage.getItem(SV_DRAFT_KEY) || 'null'); } catch (e) { }
                    if (!data) return;
                    draftFields.forEach(n => { if (data[n]) form.elements[n].value = data[n]; });
                    if (data.visited) {
                        const r = form.querySelector(`input[name="visited"][value="${data.visited}"]`);
                        if (r) r.checked = true;
                    }
                    if (elDeadline.value && elDeadline.value < elDeadline.min) elDeadline.value = '';
                    (Array.isArray(data.spaces) ? data.spaces : []).forEach(sp => svAddSpace(sp.name, sp.notes, { silent: true }, sp.requirements));
                    document.getElementById('sv_draft_note').classList.remove('hidden');
                    crmShowToast('Your unsaved draft was restored. Re-attach files if you had any.', 'success', 5000);
                }

                // ── Validation ──
                function svClearErr(el) {
                    const wrap = el.closest('div');
                    el.classList.remove('sv-invalid');
                    const err = wrap && wrap.querySelector('.sv-err'); if (err) err.classList.add('hidden');
                }
                function svValidate() {
                    let ok = true, first = null;
                    if (!elDt.value) {
                        ok = false; first = first || elDt;
                        elDt.classList.add('sv-invalid');
                        elDt.parentElement.querySelector('.sv-err').classList.remove('hidden');
                    }
                    if (!visitedVal()) {
                        ok = false; first = first || document.getElementById('sv_visited_group');
                        document.querySelectorAll('.sv-choice').forEach(c => c.classList.add('sv-invalid'));
                        document.getElementById('sv_visited_group').parentElement.querySelector('.sv-err').classList.remove('hidden');
                    }
                    if (elDeadline.value && elDeadline.value < elDeadline.min) {
                        ok = false; first = first || elDeadline;
                        elDeadline.classList.add('sv-invalid');
                    }
                    // Spaces: kailangan kapag "Yes, visited"; bawat isa may pangalan at walang doble
                    if (visitedVal() === 'yes' && !svSpaces.length) {
                        ok = false; first = first || document.getElementById('sv_spaces_section');
                        document.getElementById('sv_spaces_req_err').classList.remove('hidden');
                    }
                    const seen = new Set();
                    svSpaces.forEach(sp => {
                        const nm = svNorm(sp.nameInput.value).toLowerCase();
                        if (!nm || seen.has(nm)) {
                            ok = false; first = first || sp.nameInput;
                            sp.nameInput.classList.add('!border-red-500');
                            svSetOpen(sp, true);
                        }
                        seen.add(nm);
                    });
                    if (first) {
                        const stepEl = first.closest('[data-step]');
                        if (stepEl) svGoStep(+stepEl.dataset.step, { force: true, scroll: false });
                        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return ok;
                }

                form.addEventListener('input', e => {
                    if (e.target.classList) svClearErr(e.target);
                    if (e.target === elDeadline) svDeadlineHint();
                    svUpdateChecklist(); svSaveDraft();
                });
                form.addEventListener('change', e => {
                    if (e.target.name === 'visited') {
                        document.querySelectorAll('.sv-choice').forEach(c => c.classList.remove('sv-invalid'));
                        document.getElementById('sv_visited_group').parentElement.querySelector('.sv-err').classList.add('hidden');
                    }
                    if (e.target === elDeadline) { elDeadline.classList.remove('sv-invalid'); svDeadlineHint(); }
                    svUpdateChecklist(); svSaveDraft();
                });
                form.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); // walang aksidenteng submit
                });

                // ── Save → inline confirm → submit ──
                const saveArea = document.getElementById('sv_save_area');
                const confirmBox = document.getElementById('sv_confirm');
                document.getElementById('sv_save_btn').addEventListener('click', () => {
                    if (!svValidate()) { crmShowToast('Please complete the required fields.', 'warning'); return; }
                    const when = new Date(elDt.value).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
                    const v = visitedVal() === 'yes' ? 'Visited' : 'Not visited';
                    const pdfs = svSpaces.reduce((n, sp) => n + sp.pdf.count(), 0);
                    const photos = svGeneralPhotos.count() + svSpaces.reduce((n, sp) => n + sp.photo.count(), 0);
                    const files = `${svSpaces.length} space${svSpaces.length === 1 ? '' : 's'} · ${pdfs} measurement file${pdfs === 1 ? '' : 's'} · ${photos} photo${photos === 1 ? '' : 's'}`;
                    document.getElementById('sv_confirm_text').textContent = `${v} on ${when} — ${files}.`;
                    saveArea.classList.add('hidden');
                    confirmBox.classList.remove('hidden');
                });
                document.getElementById('sv_confirm_no').addEventListener('click', () => {
                    confirmBox.classList.add('hidden');
                    saveArea.classList.remove('hidden');
                });
                document.getElementById('sv_confirm_yes').addEventListener('click', function () {
                    this.disabled = true;
                    document.getElementById('sv_confirm_no').disabled = true;
                    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
                    form.submit();
                });


