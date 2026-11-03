<?php
// sitevisit/js/form-whole-view.php — "Whole view" modal (read-only list ng lahat ng space)
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // ═══ WHOLE VIEW ═══
                const svWhole = document.getElementById('svWhole');
                const svEsc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                const svDash = '<span class="text-gray-300">—</span>';
                const svText = v => v.trim() ? `<span class="whitespace-pre-line break-words">${svEsc(v.trim())}</span>` : svDash;

                function svWholePhotos(sp, size) {
                    const files = sp.photo.files();
                    if (!files.length) return svDash;
                    return `<div class="flex flex-wrap gap-1">${files.map(f =>
                        `<img src="${sp.photo.urlOf(f)}" alt="" title="${svEsc(f.name)}" class="${size} rounded-md object-cover border border-gray-200">`).join('')}</div>`;
                }
                function svWholePdfs(sp) {
                    const files = sp.pdf.files();
                    if (!files.length && svSpaceMeasured(sp)) return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10.5px] font-semibold"><i class="fa-solid fa-cubes text-[9px]"></i>In the items</span>';
                    if (!files.length) return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10.5px] font-semibold"><i class="fa-solid fa-ruler text-[9px]"></i>No measurement</span>';
                    const imgs = files.filter(f => svPhotoTypes.includes(f.type));
                    const pdfs = files.filter(f => !svPhotoTypes.includes(f.type));
                    return `<div class="flex flex-col gap-1">${pdfs.map(f =>
                        `<span class="inline-flex items-center gap-1.5 text-[11.5px] text-gray-700 min-w-0"><i class="fa-solid fa-file-pdf text-red-500 shrink-0"></i><span class="truncate max-w-[160px]" title="${svEsc(f.name)}">${svEsc(f.name)}</span></span>`).join('')}
                        ${imgs.length ? `<div class="flex flex-wrap gap-1">${imgs.map(f => `<img src="${sp.pdf.urlOf(f)}" alt="" title="${svEsc(f.name)}" class="w-11 h-11 rounded-md object-cover border border-gray-200">`).join('')}</div>` : ''}</div>`;
                }

                // Photos + PDF ng isang item (preview lang, galing sa napiling files)
                function svWholeItemFiles(it) {
                    const ph = it.photo.files(), fl = it.pdf.files();
                    if (!ph.length && !fl.length) return '';
                    const imgs = [...ph, ...fl.filter(f => svPhotoTypes.includes(f.type))];
                    const pdfs = fl.filter(f => !svPhotoTypes.includes(f.type));
                    return `<span class="mt-1 flex flex-wrap items-center gap-1">${imgs.map(f => `<img src="${(ph.includes(f) ? it.photo : it.pdf).urlOf(f)}" alt="" title="${svEsc(f.name)}" class="w-9 h-9 rounded object-cover border border-gray-200">`).join('')}${pdfs.map(f => `<span class="inline-flex items-center gap-1 text-[10.5px] text-gray-600 max-w-[140px]"><i class="fa-solid fa-file-pdf text-red-500"></i><span class="truncate">${svEsc(f.name)}</span></span>`).join('')}</span>`;
                }
                // Listahan ng item ng isang space (read-only)
                function svWholeItems(sp) {
                    const list = svFilledItems(sp);
                    if (!list.length) return svDash;
                    return `<ul class="space-y-1">${list.map(it => {
                        const size = svItemSize(it), q = it.qty.value.trim();
                        const extra = [size, q && q !== '1' ? '×' + q : '', it.material.value.trim()].filter(Boolean).map(svEsc).join(' · ');
                        return `<li class="leading-snug"><span class="font-semibold text-gray-900">${svEsc(svNorm(it.name.value))}</span>${extra ? `<span class="block text-[11px] text-gray-500">${extra}</span>` : ''}${it.remark.value.trim() ? `<span class="block text-[11px] text-gray-400 italic">${svEsc(it.remark.value.trim())}</span>` : ''}${svWholeItemFiles(it)}</li>`;
                    }).join('')}</ul>`;
                }

                function svRenderWhole() {
                    const n = svSpaces.length;
                    const measured = svSpaces.filter(svSpaceMeasured).length;
                    const photos = svSpaces.reduce((t, sp) => t + svSpacePhotoN(sp), 0);
                    const pdfs = svSpaces.reduce((t, sp) => t + svSpacePdfN(sp), 0);
                    const itemN = svSpaces.reduce((t, sp) => t + svFilledItems(sp).length, 0);
                    document.getElementById('svWholeSub').textContent = `${n} space${n === 1 ? '' : 's'} · read-only view. Tap Edit to change a space.`;
                    document.getElementById('svWholeFoot').innerHTML =
                        `<b>${measured}</b> of ${n} measured · ${itemN} item${itemN === 1 ? '' : 's'} · ${pdfs} measurement file${pdfs === 1 ? '' : 's'} · ${photos} site photo${photos === 1 ? '' : 's'}`
                        + (measured < n ? ` · <span class="text-amber-700 font-semibold">${n - measured} without measurement</span>` : '');

                    const editBtn = k => `<button type="button" data-whole-edit="${k}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-pen"></i>Edit</button>`;
                    document.getElementById('svWholeRows').innerHTML = n ? svSpaces.map((sp, i) => `
                        <tr class="${svSpaceMeasured(sp) ? '' : 'bg-amber-50/30'}">
                            <td class="px-3 py-3"><span class="inline-flex w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold items-center justify-center">${i + 1}</span></td>
                            <td class="px-3 py-3 font-semibold text-gray-900 break-words">${svEsc(svNorm(sp.nameInput.value)) || svDash}</td>
                            <td class="px-3 py-3 text-gray-800">${svWholeItems(sp)}</td>
                            <td class="px-3 py-3 text-gray-800">${svText(sp.reqInput.value)}</td>
                            <td class="px-3 py-3">${svWholePhotos(sp, 'w-11 h-11')}</td>
                            <td class="px-3 py-3">${svWholePdfs(sp)}</td>
                            <td class="px-3 py-3 text-gray-800">${svText(sp.notesInput.value)}</td>
                            <td class="px-3 py-3 text-right">${editBtn(sp.key)}</td>
                        </tr>`).join('')
                        : '<tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">No spaces yet.</td></tr>';

                    document.getElementById('svWholeCards').innerHTML = n ? svSpaces.map((sp, i) => `
                        <article class="rounded-xl border ${svSpaceMeasured(sp) ? 'border-gray-200' : 'border-amber-200 bg-amber-50/30'} p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                                <p class="flex-1 min-w-0 text-[13px] font-semibold text-gray-900 truncate">${svEsc(svNorm(sp.nameInput.value))}</p>
                                ${editBtn(sp.key)}
                            </div>
                            <dl class="space-y-1.5 text-[12.5px]">
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Items</dt><dd class="text-gray-800 mt-0.5">${svWholeItems(sp)}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Requirements</dt><dd class="text-gray-800">${svText(sp.reqInput.value)}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Site photos</dt><dd class="mt-0.5">${svWholePhotos(sp, 'w-12 h-12')}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Measurement</dt><dd class="mt-0.5">${svWholePdfs(sp)}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Remark</dt><dd class="text-gray-800">${svText(sp.notesInput.value)}</dd></div>
                            </dl>
                        </article>`).join('')
                        : '<p class="py-10 text-center text-gray-400 text-[12.5px]">No spaces yet.</p>';
                }
                function svOpenWhole() {
                    svRenderWhole();
                    svWhole.classList.remove('hidden'); svWhole.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                    document.getElementById('svWholeClose').focus();
                }
                function svCloseWhole() {
                    svWhole.classList.add('hidden'); svWhole.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                }
                document.getElementById('sv_whole_btn').addEventListener('click', svOpenWhole);
                document.getElementById('svWholeClose').addEventListener('click', svCloseWhole);
                document.getElementById('svWholeDone').addEventListener('click', svCloseWhole);
                svWhole.addEventListener('click', e => {
                    if (e.target === svWhole) return svCloseWhole();
                    const b = e.target.closest('[data-whole-edit]');
                    if (!b) return;
                    const sp = svSpaces.find(x => String(x.key) === b.dataset.wholeEdit);
                    svCloseWhole();
                    if (sp) {
                        sp.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        sp.el.classList.add('ring-2', 'ring-amber-400');
                        setTimeout(() => sp.el.classList.remove('ring-2', 'ring-amber-400'), 1600);
                        sp.reqInput.focus({ preventScroll: true });
                    }
                });
                document.addEventListener('keydown', e => { if (e.key === 'Escape' && !svWhole.classList.contains('hidden')) svCloseWhole(); });

                document.getElementById('sv_space_add').addEventListener('click', () => {
                    if (svAddSpace(spaceNameInput.value)) { spaceNameInput.value = ''; svSaveDraft(); }
                    spaceNameInput.focus();
                });
                spaceNameInput.addEventListener('keydown', e => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    document.getElementById('sv_space_add').click();
                });
                spaceNameInput.addEventListener('input', () => svSpaceError(''));
                // Chip mula sa scope area → item sa napiling space (o bagong space)
                const chipTarget = document.getElementById('sv_chip_target');
                if (chipTarget) chipTarget.addEventListener('change', () => { svLastChipNew = chipTarget.value === 'new'; });
                document.querySelectorAll('[data-space-chip]').forEach(b => b.addEventListener('click', () => {
                    const v = chipTarget ? chipTarget.value : 'new';
                    const sp = v !== 'new' ? svSpaces.find(x => String(x.key) === v) : null;
                    if (!sp) { if (svAddSpace(b.dataset.spaceChip)) svSaveDraft(); return; }
                    if (svAddItem(sp, { name: b.dataset.spaceChip })) {
                        sp.el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        crmShowToast(`"${b.dataset.spaceChip}" added to ${svNorm(sp.nameInput.value) || 'the space'}.`, 'success', 2200);
                        svSaveDraft();
                    }
                }));