<?php
// sitevisit/js/form-steps.php — step by step (stepper, Back/Next, Review) + pagsisimula ng form
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // ═══ STEP BY STEP ═══
                const SV_STEPS = { 1: 'Visit details', 2: 'Site notes', 3: 'Spaces & measurements', 4: 'Other site photos', 5: 'Review & save' };
                const SV_LAST = 5;
                let svStep = 1;
                let svMaxReached = 1;

                // Validation ng isang step lang (para sa "Next")
                function svValidateStep(n) {
                    let ok = true, first = null;
                    if (n === 1) {
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
                    }
                    if (n === 3) {
                        if (visitedVal() === 'yes' && !svSpaces.length) {
                            ok = false; first = first || document.getElementById('sv_space_name');
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
                    }
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return ok;
                }

                function svGoStep(n, { force = false, scroll = true } = {}) {
                    n = Math.max(1, Math.min(SV_LAST, n));
                    // pasulong: dapat pasado ang bawat step na lalampasan
                    if (!force && n > svStep) {
                        for (let i = svStep; i < n; i++) {
                            if (!svValidateStep(i)) {
                                if (i !== svStep) svGoStep(i, { force: true });
                                crmShowToast('Please complete this step first.', 'warning');
                                return false;
                            }
                        }
                    }
                    svStep = n;
                    svMaxReached = Math.max(svMaxReached, n);
                    document.querySelectorAll('#svForm [data-step]').forEach(sec => sec.classList.toggle('hidden', +sec.dataset.step !== n));
                    // Step 3 (space list): buong lapad para kita lahat ng column
                    const wide = n === 3;
                    document.getElementById('sv_main_col').classList.toggle('lg:col-span-3', wide);
                    document.getElementById('sv_main_col').classList.toggle('lg:col-span-2', !wide);
                    document.getElementById('sv_side_col').classList.toggle('hidden', wide);

                    // stepper lang — may data-goto-step din ang mga "Edit" button sa Review (walang dot)
                    document.querySelectorAll('#sv_stepper [data-goto-step]').forEach(btn => {
                        const k = +btn.dataset.gotoStep;
                        const done = k < n, cur = k === n;
                        btn.className = 'group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold hover:bg-gray-50 '
                            + (cur ? 'text-gray-900' : done ? 'text-amber-800' : 'text-gray-400');
                        btn.setAttribute('aria-current', cur ? 'step' : 'false');
                        const dot = btn.querySelector('[data-step-dot]');
                        if (!dot) return;
                        dot.className = 'w-7 h-7 rounded-full flex items-center justify-center text-[11px] '
                            + (cur ? 'bg-amber-50 text-amber-700 ring-2 ring-amber-600' : done ? 'bg-amber-700 text-white' : 'bg-gray-100 text-gray-400');
                        if (!dot.dataset.icon) dot.dataset.icon = dot.querySelector('i').className;
                        dot.querySelector('i').className = done ? 'fa-solid fa-check' : dot.dataset.icon;
                    });
                    document.querySelectorAll('[data-step-line]').forEach(l => {
                        l.className = 'w-6 sm:flex-1 h-px ' + (+l.dataset.stepLine <= n ? 'bg-amber-600' : 'bg-gray-200');
                    });

                    // sa maliit na screen: ipakita ang kasalukuyang step sa stepper
                    const navBox = document.getElementById('sv_stepper');
                    const curBtn = navBox.querySelector(`[data-goto-step="${n}"]`);
                    if (curBtn) navBox.scrollLeft = curBtn.offsetLeft - (navBox.clientWidth - curBtn.offsetWidth) / 2;

                    document.getElementById('sv_prev').disabled = n === 1;
                    document.getElementById('sv_step_label').textContent = `Step ${n} of ${SV_LAST} · ${SV_STEPS[n]}`;
                    const next = document.getElementById('sv_next');
                    next.classList.toggle('hidden', n === SV_LAST);
                    next.querySelector('span').textContent = n === 3 && !svSpaces.length && visitedVal() !== 'yes' ? 'Skip' : `Next: ${SV_STEPS[n + 1] || ''}`;

                    if (n === SV_LAST) svRenderReview();
                    // ibalik ang Save kung naiwang bukas ang confirm
                    document.getElementById('sv_confirm').classList.add('hidden');
                    document.getElementById('sv_save_area').classList.remove('hidden');
                    try { sessionStorage.setItem('svStep_' + SV_INQUIRY_ID, String(n)); } catch (e) { }
                    if (scroll) document.getElementById('sv_stepper').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return true;
                }

                function svRenderReview() {
                    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                    const card = (step, icon, title, body, warn = false) => `
                        <div class="rounded-xl border ${warn ? 'border-amber-300 bg-amber-50/50' : 'border-gray-200 bg-white'} overflow-hidden">
                            <div class="flex items-center gap-2 px-3.5 py-2 border-b ${warn ? 'border-amber-200' : 'border-gray-100'} bg-gray-50/60">
                                <i class="fa-solid ${icon} text-amber-700 text-[12px] w-4 text-center"></i>
                                <p class="flex-1 text-[11px] font-bold uppercase tracking-wider text-gray-600">${title}</p>
                                <button type="button" data-goto-step="${step}" class="text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                            </div>
                            <div class="px-3.5 py-2.5 text-[12.5px] text-gray-800">${body}</div>
                        </div>`;
                    const row = (k, v) => `<div class="flex justify-between gap-3 py-0.5"><span class="text-gray-400">${k}</span><span class="font-medium text-right min-w-0 break-words">${v}</span></div>`;

                    const when = elDt.value ? new Date(elDt.value).toLocaleString('en-PH', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';
                    const dl = elDeadline.value ? new Date(elDeadline.value + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Not set';
                    const vis = visitedVal() === 'yes' ? '<span class="text-emerald-700">Yes, visited</span>' : visitedVal() === 'no' ? '<span class="text-amber-700">No, not visited</span>' : '—';

                    const notes = [['Site conditions', 'site_conditions'], ['Client requirements', 'client_requirements'], ['Existing structure', 'existing_structure']]
                        .map(([l, n]) => {
                            const v = document.getElementById('sv_' + n).value.trim();
                            return `<div class="py-0.5"><span class="text-gray-400">${l}:</span> ${v ? esc(v.length > 140 ? v.slice(0, 140) + '…' : v) : '<span class="text-gray-300 italic">none</span>'}</div>`;
                        }).join('');

                    const missingPdf = svSpaces.filter(sp => !sp.pdf.hasFiles()).length;
                    const tick = ok => ok ? '<i class="fa-solid fa-check text-emerald-600"></i>' : '<span class="text-gray-300">—</span>';
                    const spacesBody = svSpaces.length
                        ? `<div class="overflow-x-auto -mx-1 px-1"><table class="w-full min-w-[460px] text-[12px]">
                            <thead class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                <tr><th class="py-1 pr-2 text-left">No.</th><th class="py-1 pr-2 text-left">Space name</th><th class="py-1 px-2 text-center">Requirements</th><th class="py-1 px-2 text-center">Site photos</th><th class="py-1 px-2 text-center">Measurement</th><th class="py-1 pl-2 text-center">Remark</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">${svSpaces.map((sp, i) => `
                                <tr>
                                    <td class="py-1.5 pr-2"><span class="w-5 h-5 rounded-md bg-amber-700 text-white text-[10px] font-bold inline-flex items-center justify-center">${i + 1}</span></td>
                                    <td class="py-1.5 pr-2 font-medium max-w-[180px] truncate">${esc(svNorm(sp.nameInput.value))}</td>
                                    <td class="py-1.5 px-2 text-center">${tick(sp.reqInput.value.trim())}</td>
                                    <td class="py-1.5 px-2 text-center">${sp.photo.count() || '<span class="text-gray-300">—</span>'}</td>
                                    <td class="py-1.5 px-2 text-center">${sp.pdf.hasFiles() ? `<span class="text-emerald-700 font-semibold">${sp.pdf.count()} file${sp.pdf.count() === 1 ? '' : 's'}</span>` : '<i class="fa-solid fa-circle-exclamation text-amber-500" title="No measurement"></i>'}</td>
                                    <td class="py-1.5 pl-2 text-center">${tick(sp.notesInput.value.trim())}</td>
                                </tr>`).join('')}</tbody></table></div>
                            ${missingPdf ? `<p class="mt-2 text-[11px] text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-1"></i>${missingPdf} space${missingPdf === 1 ? ' has' : 's have'} no measurement yet. You can still save.</p>` : ''}`
                        : `<p class="${visitedVal() === 'yes' ? 'text-red-600' : 'text-gray-400'}">${visitedVal() === 'yes' ? 'No spaces yet — add at least one.' : 'No spaces (not needed when not visited).'}</p>`;

                    const gp = svGeneralPhotos.count();
                    document.getElementById('sv_review').innerHTML =
                        card(1, 'fa-calendar-check', 'Visit details', row('Date & time', esc(when)) + row('Visited', vis) + row('2D & Quotation deadline', esc(dl))) +
                        card(2, 'fa-note-sticky', 'Site notes', notes) +
                        card(3, 'fa-ruler-combined', `Spaces (${svSpaces.length})`, spacesBody, visitedVal() === 'yes' && !svSpaces.length) +
                        card(4, 'fa-camera', 'Other site photos', gp ? `${gp} photo${gp === 1 ? '' : 's'}` : '<span class="text-gray-400">None</span>');
                }

                document.getElementById('svForm').addEventListener('click', e => {
                    const b = e.target.closest('[data-goto-step]');
                    if (!b) return;
                    const n = +b.dataset.gotoStep;
                    svGoStep(n, { force: n <= svMaxReached && n < svStep });
                });
                document.getElementById('sv_prev').addEventListener('click', () => svGoStep(svStep - 1));
                document.getElementById('sv_next').addEventListener('click', () => svGoStep(svStep + 1));
                document.getElementById('sv_goto_review').addEventListener('click', () => svGoStep(SV_LAST));
                // pinalitan ang "visited" → baka magbago ang label ng Next sa step 3
                form.addEventListener('change', e => { if (e.target.name === 'visited') svGoStep(svStep, { force: true, scroll: false }); });

                svLoadDraft();
                svDeadlineHint();
                svUpdateChecklist();

                // Saan magsisimula: may error galing server → step 1; kung hindi, ang huling step (session na ito)
                let startStep = 1;
                if (!hasServerOld) {
                    try { startStep = parseInt(sessionStorage.getItem('svStep_' + SV_INQUIRY_ID) || '1', 10) || 1; } catch (e) { }
                }
                svGoStep(1, { force: true, scroll: false });
                if (startStep > 1) {
                    // tahimik na tumalon hanggang sa pinakamalayong pasadong step
                    let n = 1;
                    while (n < startStep && n < SV_LAST && svValidateStepQuiet(n)) n++;
                    svGoStep(n, { force: true, scroll: false });
                }
                function svValidateStepQuiet(n) {
                    if (n === 1) return !!elDt.value && !!visitedVal() && !(elDeadline.value && elDeadline.value < elDeadline.min);
                    if (n === 3) return !(visitedVal() === 'yes' && !svSpaces.length);
                    return true;
                }