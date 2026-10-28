<?php
// sitevisit/js/form-spaces.php — space list: add / remove / rename, chips, status
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // ═══ SPACES ═══
                const SV_MAX_SPACES = 30;
                const SV_OLD_SPACES = <?= json_encode($svOld['spaces'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
                const svSpaces = [];      // { key, el, nameInput, notesInput, pdf, photo }
                let svNextKey = 1;
                const spaceList = document.getElementById('sv_space_list');
                const spaceNameInput = document.getElementById('sv_space_name');
                const spaceErr = document.getElementById('sv_space_err');
                const svNorm = v => String(v || '').replace(/\s+/g, ' ').trim();

                function svSpaceError(msg) {
                    spaceErr.textContent = msg || '';
                    spaceErr.classList.toggle('hidden', !msg);
                }

                // Isang hilera sa space list — lahat ng field ay nakikita agad (walang bukas/sara)
                const SV_ROW_GRID = 'md:grid md:grid-cols-[44px_minmax(130px,1fr)_minmax(160px,1.4fr)_170px_150px_minmax(140px,1.2fr)_36px] md:gap-3 md:items-start';
                const SV_TA = 'w-full text-[12.5px] text-gray-800 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 outline-none resize-y min-h-[64px] focus:border-amber-600 focus:ring-2 focus:ring-amber-100 placeholder:text-gray-300';
                function svBuildSpaceCard(key, name, notes, requirements) {
                    const el = document.createElement('article');
                    el.className = 'relative rounded-xl md:rounded-none border md:border-0 border-gray-200 bg-white px-3 py-3 md:px-2 md:py-2.5 space-y-2.5 md:space-y-0 hover:bg-amber-50/20 ' + SV_ROW_GRID;
                    el.dataset.spaceKey = key;
                    el.innerHTML = `
                        <div class="flex md:block items-center gap-2">
                            <span data-space-no class="w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0"></span>
                            <span class="md:hidden text-[10px] font-bold uppercase tracking-wider text-gray-400">Space</span>
                        </div>
                        <div class="min-w-0">
                            <p class="sv-label md:!hidden">Space name</p>
                            <input type="text" name="spaces[${key}][name]" maxlength="100" aria-label="Space name"
                                class="w-full h-9 text-[13px] font-semibold text-gray-900 bg-white border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        </div>
                        <div class="min-w-0">
                            <p class="sv-label md:!hidden">Requirements</p>
                            <textarea name="spaces[${key}][requirements]" rows="2" maxlength="2000" aria-label="Requirements"
                                class="${SV_TA}" placeholder="What the client wants…"></textarea>
                        </div>
                        <div class="min-w-0">
                            <p class="sv-label md:!hidden">Site photos</p>
                            <label for="sv_sp_ph_${key}" data-drop-photo
                                class="sv-drop !flex-row !gap-1.5 !py-2 !px-2 !text-left cursor-pointer">
                                <i class="fa-solid fa-camera text-amber-700 text-[12px]"></i>
                                <span class="text-[11.5px] font-semibold text-gray-700">Add photos</span>
                            </label>
                            <input id="sv_sp_ph_${key}" type="file" name="space_photos[${key}][]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                            <div data-list-photo class="mt-1.5 grid grid-cols-4 gap-1 max-h-32 overflow-y-auto p-1 -m-1 [scrollbar-width:thin]"></div>
                        </div>
                        <div class="min-w-0">
                            <p class="sv-label md:!hidden">Measurement (PDF or photo)</p>
                            <label for="sv_sp_pdf_${key}" data-drop-pdf
                                class="sv-drop !flex-row !gap-1.5 !py-2 !px-2 !text-left cursor-pointer">
                                <i class="fa-solid fa-ruler-combined text-red-500 text-[12px]"></i>
                                <span class="text-[11.5px] font-semibold text-gray-700">PDF / photo</span>
                            </label>
                            <input id="sv_sp_pdf_${key}" type="file" name="space_pdfs[${key}][]" accept="application/pdf,image/jpeg,image/png,image/webp" multiple class="hidden">
                            <ul data-list-pdf class="mt-1.5 space-y-1 max-h-32 overflow-y-auto [scrollbar-width:thin]"></ul>
                        </div>
                        <div class="min-w-0">
                            <p class="sv-label md:!hidden">Remark</p>
                            <textarea name="spaces[${key}][notes]" rows="2" maxlength="2000" aria-label="Remark"
                                class="${SV_TA}" placeholder="Ceiling height, outlets…"></textarea>
                        </div>
                        <div class="absolute top-2.5 right-2 md:static md:pt-0.5">
                            <button type="button" data-space-remove aria-label="Remove space"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-trash-can text-[13px]"></i></button>
                        </div>
                        <div data-space-confirm class="hidden md:col-span-7 rounded-lg px-3 py-2 bg-red-50 border border-red-100 flex-wrap items-center gap-2 md:mt-2">
                            <p class="flex-1 min-w-[160px] text-[12px] text-red-800"><i class="fa-solid fa-triangle-exclamation mr-1"></i><span data-space-confirm-text></span></p>
                            <button type="button" data-space-cancel class="px-3 py-1.5 text-[11.5px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Keep</button>
                            <button type="button" data-space-yes class="px-3 py-1.5 text-[11.5px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">Remove</button>
                        </div>`;
                    // value sa property (hindi sa HTML) para ligtas sa kahit anong pangalan
                    el.querySelector(`[name="spaces[${key}][name]"]`).value = name;
                    el.querySelector(`[name="spaces[${key}][notes]"]`).value = notes || '';
                    el.querySelector(`[name="spaces[${key}][requirements]"]`).value = requirements || '';
                    el.querySelectorAll('textarea').forEach(t => t.addEventListener('change', () => svRefreshSpaces()));
                    return el;
                }

                function svAddSpace(rawName, notes = '', { silent = false } = {}, requirements = '') {
                    const name = svNorm(rawName);
                    if (!name) { if (!silent) svSpaceError('Type a space name first.'); return null; }
                    if (name.length > 100) { if (!silent) svSpaceError('The space name is too long (max 100 characters).'); return null; }
                    if (svSpaces.some(s => svNorm(s.nameInput.value).toLowerCase() === name.toLowerCase())) {
                        if (!silent) svSpaceError(`"${name}" is already added.`);
                        return null;
                    }
                    if (svSpaces.length >= SV_MAX_SPACES) { if (!silent) svSpaceError(`You can add up to ${SV_MAX_SPACES} spaces.`); return null; }
                    svSpaceError('');

                    const key = svNextKey++;
                    const el = svBuildSpaceCard(key, name, notes, requirements);
                    const sp = {
                        key, el,
                        nameInput: el.querySelector(`[name="spaces[${key}][name]"]`),
                        notesInput: el.querySelector(`[name="spaces[${key}][notes]"]`),
                        reqInput: el.querySelector(`[name="spaces[${key}][requirements]"]`),
                    };
                    spaceList.appendChild(el);
                    sp.pdf = svFileBox({ input: el.querySelector(`#sv_sp_pdf_${key}`), drop: el.querySelector('[data-drop-pdf]'), list: el.querySelector('[data-list-pdf]'), kind: 'pdf' });
                    sp.photo = svFileBox({ input: el.querySelector(`#sv_sp_ph_${key}`), drop: el.querySelector('[data-drop-photo]'), list: el.querySelector('[data-list-photo]'), kind: 'photo' });

                    // Remove: may inline confirm kapag may laman na
                    const confirmRow = el.querySelector('[data-space-confirm]');
                    el.querySelector('[data-space-remove]').addEventListener('click', () => {
                        const hasContent = sp.pdf.hasFiles() || sp.photo.hasFiles() || sp.notesInput.value.trim() || sp.reqInput.value.trim();
                        if (!hasContent) return svRemoveSpace(sp);
                        el.querySelector('[data-space-confirm-text]').textContent =
                            `Remove "${svNorm(sp.nameInput.value) || 'this space'}" and its files?`;
                        confirmRow.classList.remove('hidden'); confirmRow.classList.add('flex');
                    });
                    el.querySelector('[data-space-cancel]').addEventListener('click', () => { confirmRow.classList.add('hidden'); confirmRow.classList.remove('flex'); });
                    el.querySelector('[data-space-yes]').addEventListener('click', () => svRemoveSpace(sp));
                    sp.nameInput.addEventListener('input', () => { sp.nameInput.classList.remove('!border-red-500'); svRefreshSpaces(); });

                    svSpaces.push(sp);
                    if (!silent) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    document.getElementById('sv_spaces_req_err').classList.add('hidden');
                    svRefreshSpaces();
                    svUpdateChecklist();
                    return sp;
                }

                // (list na ngayon — laging nakikita ang lahat; naiwan para sa mga tumatawag pa)
                function svSetOpen(sp, open) { sp.open = open; }

                function svRemoveSpace(sp) {
                    sp.pdf.destroy(); sp.photo.destroy();
                    sp.el.remove();
                    svSpaces.splice(svSpaces.indexOf(sp), 1);
                    svRefreshSpaces(); svUpdateChecklist(); svSaveDraft();
                }

                function svRefreshSpaces() {
                    if (typeof svSpaces === 'undefined') return;
                    svSpaces.forEach((sp, i) => {
                        sp.el.querySelector('[data-space-no]').textContent = i + 1;
                        const p = sp.pdf ? sp.pdf.count() : 0, ph = sp.photo ? sp.photo.count() : 0;
                        sp.el.classList.toggle('md:bg-amber-50/30', !p);  // walang PDF pa → bahagyang highlight
                    });
                    const n = svSpaces.length;
                    document.getElementById('sv_space_empty').classList.toggle('hidden', n > 0);
                    document.getElementById('sv_space_tools').classList.toggle('hidden', n < 1);
                    document.getElementById('sv_space_head').classList.toggle('md:grid', n > 0);
                    const measured = svSpaces.filter(sp => sp.pdf && sp.pdf.hasFiles()).length;
                    document.getElementById('sv_space_status').innerHTML =
                        `<b class="text-gray-800">${measured}</b> of ${n} spaces have a measurement`;
                    const cnt = document.getElementById('sv_space_count');
                    cnt.textContent = `${n} space${n === 1 ? '' : 's'}`;
                    cnt.className = 'shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold ' + (n ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500');
                    // chips na naidagdag na → naka-check
                    const taken = new Set(svSpaces.map(s => svNorm(s.nameInput.value).toLowerCase()));
                    document.querySelectorAll('[data-space-chip]').forEach(b => {
                        const on = taken.has(svNorm(b.dataset.spaceChip).toLowerCase());
                        b.disabled = on;
                        b.querySelector('i').className = 'fa-solid ' + (on ? 'fa-check' : 'fa-plus') + ' text-[9px]';
                    });
                }

