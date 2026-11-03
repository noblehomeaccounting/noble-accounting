<?php
// sitevisit/js/form-spaces.php — space list: add / remove / rename, chips, status
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // ═══ SPACES ═══
                const SV_MAX_SPACES = 30, SV_MAX_ITEMS = 50;
                const SV_OLD_SPACES = <?= json_encode($svOld['spaces'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
                const svSpaces = [];      // { key, el, nameInput, notesInput, reqInput, pdf, photo, items[] }
                let svNextKey = 1;
                const spaceList = document.getElementById('sv_space_list');
                const spaceNameInput = document.getElementById('sv_space_name');
                const spaceErr = document.getElementById('sv_space_err');
                const svNorm = v => String(v || '').replace(/\s+/g, ' ').trim();

                const svEscOpt = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                let svLastChipNew = false;
                function svSpaceError(msg) {
                    spaceErr.textContent = msg || '';
                    spaceErr.classList.toggle('hidden', !msg);
                }

                // Isang hilera sa space list — lahat ng field ay nakikita agad (walang bukas/sara)
                const SV_ITEM_GRID = 'md:grid-cols-[minmax(120px,1.4fr)_64px_64px_64px_52px_minmax(110px,1.1fr)_minmax(110px,1.1fr)_92px_30px]';
                const SV_IN = 'w-full h-8 text-[12px] text-gray-800 bg-white border border-gray-200 rounded-md px-2 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 placeholder:text-gray-300';
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
                        </div>
                        <div data-items-wrap class="md:col-span-7 md:ml-[56px] md:mt-2 rounded-lg border border-dashed border-gray-300 bg-gray-50/70">
                            <div class="flex flex-wrap items-center gap-2 px-2.5 py-1.5">
                                <i class="fa-solid fa-cubes text-amber-700 text-[11px]"></i>
                                <span class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500">Items</span>
                                <span data-item-count class="text-[10.5px] text-gray-400">optional — e.g. upper cabinet, wardrobe</span>
                                <button type="button" data-item-add class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white border border-amber-300 text-amber-800 text-[11px] font-semibold hover:bg-amber-50"><i class="fa-solid fa-plus text-[9px]"></i> Add item</button>
                            </div>
                            <div data-item-head class="hidden ${SV_ITEM_GRID} gap-1.5 px-2 pb-1 text-[9.5px] font-bold uppercase tracking-wider text-gray-400">
                                <span>Item</span><span>W (cm)</span><span>H (cm)</span><span>D (cm)</span><span>Qty</span><span>Material / finish</span><span>Remark</span><span>Photo · PDF</span><span></span>
                            </div>
                            <div data-items class="divide-y divide-gray-200/70"></div>
                        </div>`;
                    // value sa property (hindi sa HTML) para ligtas sa kahit anong pangalan
                    el.querySelector(`[name="spaces[${key}][name]"]`).value = name;
                    el.querySelector(`[name="spaces[${key}][notes]"]`).value = notes || '';
                    el.querySelector(`[name="spaces[${key}][requirements]"]`).value = requirements || '';
                    el.querySelectorAll('textarea').forEach(t => t.addEventListener('change', () => svRefreshSpaces()));
                    return el;
                }

                function svAddSpace(rawName, notes = '', { silent = false } = {}, requirements = '', items = []) {
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
                        items: [], nextItem: 1,
                    };
                    spaceList.appendChild(el);
                    sp.pdf = svFileBox({ input: el.querySelector(`#sv_sp_pdf_${key}`), drop: el.querySelector('[data-drop-pdf]'), list: el.querySelector('[data-list-pdf]'), kind: 'pdf' });
                    sp.photo = svFileBox({ input: el.querySelector(`#sv_sp_ph_${key}`), drop: el.querySelector('[data-drop-photo]'), list: el.querySelector('[data-list-photo]'), kind: 'photo' });

                    // Remove: may inline confirm kapag may laman na
                    const confirmRow = el.querySelector('[data-space-confirm]');
                    el.querySelector('[data-space-remove]').addEventListener('click', () => {
                        const hasContent = sp.pdf.hasFiles() || sp.photo.hasFiles() || sp.notesInput.value.trim() || sp.reqInput.value.trim() || sp.items.length > 0;
                        if (!hasContent) return svRemoveSpace(sp);
                        el.querySelector('[data-space-confirm-text]').textContent =
                            `Remove "${svNorm(sp.nameInput.value) || 'this space'}" and its files?`;
                        confirmRow.classList.remove('hidden'); confirmRow.classList.add('flex');
                    });
                    el.querySelector('[data-space-cancel]').addEventListener('click', () => { confirmRow.classList.add('hidden'); confirmRow.classList.remove('flex'); });
                    el.querySelector('[data-space-yes]').addEventListener('click', () => svRemoveSpace(sp));
                    sp.nameInput.addEventListener('input', () => { sp.nameInput.classList.remove('!border-red-500'); svRefreshSpaces(); });
                    el.querySelector('[data-item-add]').addEventListener('click', () => { svAddItem(sp, {}, { focus: true }); svSaveDraft(); });
                    (Array.isArray(items) ? items : []).forEach(it => svAddItem(sp, it));

                    svSpaces.push(sp);
                    if (!silent) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    document.getElementById('sv_spaces_req_err').classList.add('hidden');
                    svRefreshSpaces();
                    svUpdateChecklist();
                    return sp;
                }

                // ── Mga item sa loob ng space (hal. Upper cabinet 240 × 70 × 35 cm) ──
                function svAddItem(sp, data = {}, { focus = false } = {}) {
                    if (sp.items.length >= SV_MAX_ITEMS) { crmShowToast(`Up to ${SV_MAX_ITEMS} items per space.`, 'warning'); return null; }
                    const k = sp.nextItem++, base = `spaces[${sp.key}][items][${k}]`, fk = `${sp.key}_${k}`;
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-4 ' + SV_ITEM_GRID + ' gap-1.5 items-center px-2 py-1.5';
                    row.innerHTML = `
                        <input type="text" name="${base}[name]" maxlength="100" autocomplete="off" placeholder="Item, e.g. Upper cabinet" aria-label="Item name" class="${SV_IN} col-span-4 md:col-span-1 font-semibold">
                        <input type="text" name="${base}[w]" inputmode="decimal" maxlength="9" placeholder="W cm" aria-label="Width (cm)" class="${SV_IN} tabular-nums">
                        <input type="text" name="${base}[h]" inputmode="decimal" maxlength="9" placeholder="H cm" aria-label="Height (cm)" class="${SV_IN} tabular-nums">
                        <input type="text" name="${base}[d]" inputmode="decimal" maxlength="9" placeholder="D cm" aria-label="Depth (cm)" class="${SV_IN} tabular-nums">
                        <input type="text" name="${base}[qty]" inputmode="numeric" maxlength="3" placeholder="Qty" aria-label="Quantity" class="${SV_IN} tabular-nums">
                        <input type="text" name="${base}[material]" maxlength="150" placeholder="Material / finish" aria-label="Material / finish" class="${SV_IN} col-span-4 md:col-span-1">
                        <input type="text" name="${base}[remark]" maxlength="500" placeholder="Remark" aria-label="Remark" class="${SV_IN} col-span-4 md:col-span-1">
                        <div class="col-span-3 md:col-span-1 flex gap-1">
                            <label for="sv_it_ph_${fk}" data-idrop-photo title="Item photos" class="sv-drop !flex-row !gap-1 !py-0 !px-1.5 h-8 flex-1 cursor-pointer"><i class="fa-solid fa-camera text-amber-700 text-[11px]"></i><span data-icount-photo class="text-[10.5px] font-bold text-gray-600">0</span></label>
                            <label for="sv_it_pdf_${fk}" data-idrop-pdf title="Measurement: PDF or photo" class="sv-drop !flex-row !gap-1 !py-0 !px-1.5 h-8 flex-1 cursor-pointer"><i class="fa-solid fa-ruler-combined text-red-500 text-[11px]"></i><span data-icount-pdf class="text-[10.5px] font-bold text-gray-600">0</span></label>
                            <input id="sv_it_ph_${fk}" type="file" name="item_photos[${fk}][]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                            <input id="sv_it_pdf_${fk}" type="file" name="item_files[${fk}][]" accept="application/pdf,image/jpeg,image/png,image/webp" multiple class="hidden">
                        </div>
                        <button type="button" data-item-remove aria-label="Remove item" class="inline-flex items-center justify-center h-8 w-full md:w-8 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-xmark text-[13px] leading-none"></i></button>
                        <div data-ifiles class="hidden col-span-4 md:col-span-9 flex-wrap items-start gap-2">
                            <div data-ilist-photo class="flex flex-wrap gap-1 [&>div]:w-12"></div>
                            <ul data-ilist-pdf class="flex flex-wrap gap-1 [&>li]:max-w-[220px]"></ul>
                        </div>`;
                    const f = n => row.querySelector(`[name="${base}[${n}]"]`);
                    const it = { k, row, name: f('name'), w: f('w'), h: f('h'), d: f('d'), qty: f('qty'), material: f('material'), remark: f('remark') };
                    it.photo = svFileBox({ input: row.querySelector(`#sv_it_ph_${fk}`), drop: row.querySelector('[data-idrop-photo]'), list: row.querySelector('[data-ilist-photo]'), kind: 'photo' });
                    it.pdf = svFileBox({ input: row.querySelector(`#sv_it_pdf_${fk}`), drop: row.querySelector('[data-idrop-pdf]'), list: row.querySelector('[data-ilist-pdf]'), kind: 'pdf' });
                    // value sa property (ligtas sa kahit anong teksto)
                    it.name.value = data.name || ''; it.w.value = data.w ?? ''; it.h.value = data.h ?? ''; it.d.value = data.d ?? '';
                    it.qty.value = data.qty ?? ''; it.material.value = data.material || ''; it.remark.value = data.remark || '';
                    row.querySelectorAll('input').forEach(i => i.addEventListener('input', () => { i.classList.remove('!border-red-500'); }));
                    it.name.addEventListener('change', () => svRefreshSpaces());
                    svItemAutocomplete(it.name, sp);
                    row.querySelector('[data-item-remove]').addEventListener('click', () => {
                        it.photo.destroy(); it.pdf.destroy();
                        row.remove(); sp.items.splice(sp.items.indexOf(it), 1);
                        svRefreshSpaces(); svUpdateChecklist(); svSaveDraft();
                    });
                    sp.el.querySelector('[data-items]').appendChild(row);
                    sp.items.push(it);
                    svRefreshSpaces();
                    if (focus) it.name.focus();
                    return it;
                }
                // ── Sariling dropdown ng pangalan ng item (kapalit ng native datalist) ──
                const SV_SUGGEST = [...(document.getElementById('sv_item_suggest')?.content.querySelectorAll('option') || [])]
                    .map(o => ({ v: o.value, src: o.dataset.src || 'common' }));
                let svAcBox = null, svAcInput = null, svAcSpace = null, svAcIdx = -1, svAcList = [];
                function svAcEnsure() {
                    if (svAcBox) return svAcBox;
                    svAcBox = document.createElement('div');
                    svAcBox.className = 'hidden fixed z-[9995] bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden';
                    svAcBox.setAttribute('role', 'listbox');
                    svAcBox.innerHTML = '<div data-ac-list class="max-h-64 overflow-y-auto overscroll-contain py-1 [scrollbar-width:thin]"></div>';
                    document.body.appendChild(svAcBox);
                    // mousedown para hindi mawala ang focus bago mapili
                    svAcBox.addEventListener('mousedown', e => {
                        const o = e.target.closest('[data-ac-i]'); e.preventDefault();
                        if (o) svAcPick(+o.dataset.acI);
                    });
                    window.addEventListener('resize', svAcPlace);
                    document.addEventListener('scroll', svAcPlace, true);
                    return svAcBox;
                }
                function svAcPlace() {
                    if (!svAcBox || svAcBox.classList.contains('hidden') || !svAcInput) return;
                    const r = svAcInput.getBoundingClientRect();
                    if (r.bottom < 0 || r.top > window.innerHeight) return svAcHide();
                    const w = Math.min(Math.max(r.width, 240), window.innerWidth - 16);
                    const below = window.innerHeight - r.bottom > 280 || r.top < 280;
                    svAcBox.style.width = w + 'px';
                    svAcBox.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                    svAcBox.style.top = below ? (r.bottom + 4) + 'px' : '';
                    svAcBox.style.bottom = below ? '' : (window.innerHeight - r.top + 4) + 'px';
                }
                function svAcHide() { if (svAcBox) svAcBox.classList.add('hidden'); svAcIdx = -1; }
                function svAcRender() {
                    const q = svNorm(svAcInput.value).toLowerCase();
                    const used = new Set(svAcSpace.items.filter(x => x.name !== svAcInput).map(x => svNorm(x.name.value).toLowerCase()).filter(Boolean));
                    svAcList = SV_SUGGEST.filter(s => !q || s.v.toLowerCase().includes(q)).slice(0, 40);
                    if (!svAcList.length || (svAcList.length === 1 && svAcList[0].v.toLowerCase() === q)) return svAcHide();
                    const mark = v => {
                        const i = q ? v.toLowerCase().indexOf(q) : -1;
                        return i < 0 ? svEscOpt(v) : svEscOpt(v.slice(0, i)) + '<b class="text-amber-800">' + svEscOpt(v.slice(i, i + q.length)) + '</b>' + svEscOpt(v.slice(i + q.length));
                    };
                    let html = '', lastSrc = '';
                    svAcList.forEach((s, i) => {
                        if (s.src !== lastSrc) {
                            lastSrc = s.src;
                            html += `<p class="px-3 pt-2 pb-1 text-[9.5px] font-bold uppercase tracking-wider ${s.src === 'inquiry' ? 'text-amber-700' : 'text-gray-400'}"><i class="fa-solid ${s.src === 'inquiry' ? 'fa-clipboard-list' : 'fa-cubes'} mr-1"></i>${s.src === 'inquiry' ? 'From the inquiry' : 'Common items'}</p>`;
                        }
                        const isUsed = used.has(s.v.toLowerCase());
                        html += `<button type="button" role="option" data-ac-i="${i}" aria-selected="${i === svAcIdx}"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-[12.5px] ${i === svAcIdx ? 'bg-amber-50 text-gray-900' : 'text-gray-700 hover:bg-gray-50'}">
                            <span class="flex-1 min-w-0 truncate">${mark(s.v)}</span>
                            ${isUsed ? '<span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600"><i class="fa-solid fa-check text-[9px]"></i>added</span>' : ''}
                        </button>`;
                    });
                    svAcEnsure().querySelector('[data-ac-list]').innerHTML = html;
                    svAcBox.classList.remove('hidden');
                    svAcPlace();
                    const on = svAcBox.querySelector('[aria-selected="true"]');
                    if (on) on.scrollIntoView({ block: 'nearest' });
                }
                function svAcPick(i) {
                    const s = svAcList[i]; if (!s || !svAcInput) return;
                    svAcInput.value = s.v;
                    svAcInput.dispatchEvent(new Event('input', { bubbles: true }));
                    svAcInput.dispatchEvent(new Event('change', { bubbles: true }));
                    svAcHide();
                    const next = svAcInput.parentElement.querySelector('[name$="[w]"]');
                    if (next) next.focus();
                }
                function svItemAutocomplete(input, sp) {
                    if (!SV_SUGGEST.length) return;
                    const open = () => { svAcInput = input; svAcSpace = sp; svAcIdx = -1; svAcRender(); };
                    input.addEventListener('focus', open);
                    input.addEventListener('input', () => { if (svAcInput === input) { svAcIdx = -1; svAcRender(); } });
                    input.addEventListener('blur', () => setTimeout(() => { if (svAcInput === input) svAcHide(); }, 120));
                    input.addEventListener('keydown', e => {
                        const shown = svAcBox && !svAcBox.classList.contains('hidden') && svAcInput === input;
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            if (!shown) { open(); e.preventDefault(); return; }
                            e.preventDefault();
                            svAcIdx = (svAcIdx + (e.key === 'ArrowDown' ? 1 : -1) + svAcList.length) % svAcList.length;
                            svAcRender();
                        } else if (e.key === 'Enter' && shown && svAcIdx >= 0) {
                            e.preventDefault(); svAcPick(svAcIdx);
                        } else if (e.key === 'Escape' && shown) {
                            e.preventDefault(); e.stopPropagation(); svAcHide();
                        }
                    });
                }

                const svNum = v => { const t = String(v ?? '').replace(/,/g, '').trim(); return t === '' ? null : Number(t); };
                // "240 × 70 × 35 cm"
                function svItemSize(it) {
                    const p = [it.w, it.h, it.d].map(x => svNum(x && x.value !== undefined ? x.value : x)).filter(v => v !== null && !isNaN(v));
                    return p.length ? p.join(' × ') + ' cm' : '';
                }
                // Tamang laman ng item: may pangalan kapag may ibang laman; numero ang sukat; 1–999 ang dami
                function svItemProblem(it) {
                    const any = [it.name, it.w, it.h, it.d, it.material, it.remark].some(i => i.value.trim() !== '') || it.photo.hasFiles() || it.pdf.hasFiles();
                    if (!any) return null;
                    if (!svNorm(it.name.value)) return it.name;
                    for (const i of [it.w, it.h, it.d]) { const n = svNum(i.value); if (n !== null && (isNaN(n) || n <= 0 || n > 100000)) return i; }
                    const q = it.qty.value.trim();
                    if (q !== '' && (!/^\d+$/.test(q) || +q < 1 || +q > 999)) return it.qty;
                    return null;
                }
                const svItemData = it => ({ name: it.name.value, w: it.w.value, h: it.h.value, d: it.d.value, qty: it.qty.value, material: it.material.value, remark: it.remark.value });
                const svFilledItems = sp => sp.items.filter(it => svNorm(it.name.value) !== '');
                // may sukat ang space kapag may PDF/photo ang space mismo o ang kahit isang item nito
                const svSpaceMeasured = sp => !!(sp.pdf && sp.pdf.hasFiles()) || sp.items.some(it => it.pdf.hasFiles());
                const svSpacePdfN = sp => (sp.pdf ? sp.pdf.count() : 0) + sp.items.reduce((t, it) => t + it.pdf.count(), 0);
                const svSpacePhotoN = sp => (sp.photo ? sp.photo.count() : 0) + sp.items.reduce((t, it) => t + it.photo.count(), 0);

                // (list na ngayon — laging nakikita ang lahat; naiwan para sa mga tumatawag pa)
                function svSetOpen(sp, open) { sp.open = open; }

                function svRemoveSpace(sp) {
                    sp.pdf.destroy(); sp.photo.destroy();
                    sp.items.forEach(it => { it.photo.destroy(); it.pdf.destroy(); });
                    sp.el.remove();
                    svSpaces.splice(svSpaces.indexOf(sp), 1);
                    svRefreshSpaces(); svUpdateChecklist(); svSaveDraft();
                }

                function svRefreshSpaces() {
                    if (typeof svSpaces === 'undefined') return;
                    svSpaces.forEach((sp, i) => {
                        sp.el.querySelector('[data-space-no]').textContent = i + 1;
                        const ni = sp.items.length;
                        sp.el.querySelector('[data-item-count]').textContent = ni ? `${ni} item${ni === 1 ? '' : 's'}` : 'optional — e.g. upper cabinet, wardrobe';
                        sp.el.querySelector('[data-item-head]').classList.toggle('md:grid', ni > 0);
                        sp.items.forEach(it => {
                            if (!it.photo) return;
                            const pc = it.photo.count(), fc = it.pdf.count();
                            it.row.querySelector('[data-icount-photo]').textContent = pc;
                            it.row.querySelector('[data-icount-pdf]').textContent = fc;
                            const strip = it.row.querySelector('[data-ifiles]');
                            strip.classList.toggle('hidden', !(pc + fc)); strip.classList.toggle('flex', !!(pc + fc));
                        });
                        sp.el.classList.toggle('md:bg-amber-50/30', !svSpaceMeasured(sp));  // walang PDF pa → bahagyang highlight
                    });
                    const n = svSpaces.length;
                    document.getElementById('sv_space_empty').classList.toggle('hidden', n > 0);
                    document.getElementById('sv_space_tools').classList.toggle('hidden', n < 1);
                    document.getElementById('sv_space_head').classList.toggle('md:grid', n > 0);
                    const measured = svSpaces.filter(svSpaceMeasured).length;
                    document.getElementById('sv_space_status').innerHTML =
                        `<b class="text-gray-800">${measured}</b> of ${n} spaces have a measurement`;
                    const cnt = document.getElementById('sv_space_count');
                    cnt.textContent = `${n} space${n === 1 ? '' : 's'}`;
                    cnt.className = 'shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold ' + (n ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500');
                    // chips na naidagdag na → naka-check
                    const taken = new Set(svSpaces.flatMap(s => [s.nameInput.value, ...s.items.map(it => it.name.value)]).map(v => svNorm(v).toLowerCase()).filter(Boolean));
                    // "Idagdag sa" — saang space papasok ang item mula sa scope area
                    const tgt = document.getElementById('sv_chip_target');
                    if (tgt) {
                        const keep = tgt.value;
                        tgt.innerHTML = svSpaces.map(sp => `<option value="${sp.key}">${svEscOpt(svNorm(sp.nameInput.value) || 'Space ' + sp.key)}</option>`).join('') + '<option value="new">➕ As a new space</option>';
                        tgt.value = [...tgt.options].some(o => o.value === keep) && keep !== 'new' ? keep : (svSpaces.length ? String(svSpaces[svSpaces.length - 1].key) : 'new');
                        if (keep === 'new' && svSpaces.length && svLastChipNew) tgt.value = 'new';
                    }
                    document.querySelectorAll('[data-space-chip]').forEach(b => {
                        const on = taken.has(svNorm(b.dataset.spaceChip).toLowerCase());
                        b.disabled = on;
                        b.querySelector('i').className = 'fa-solid ' + (on ? 'fa-check' : 'fa-plus') + ' text-[9px]';
                    });
                }