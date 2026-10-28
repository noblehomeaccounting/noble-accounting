<?php
// sitevisit/js/form-files.php — file boxes (PDF/photo upload, drag & drop, preview) + Other site photos
// Bahagi ng iisang <script> sa sv-script-form.php (iisang scope). Huwag i-include nang mag-isa.
?>
                // Drag & drop sa label
                function svWireDrop(drop, onFiles) {
                    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('sv-drag'); }));
                    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('sv-drag'); }));
                    drop.addEventListener('drop', e => onFiles(Array.from(e.dataTransfer.files || [])));
                }

                // Isang file box (PDF o photos) — may sariling DataTransfer, preview at remove
                const svPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];
                function svFileBox({ input, drop, list, kind }) {
                    const dt = new DataTransfer();
                    const urls = new Map();
                    const isPhoto = kind === 'photo';
                    const isMeas = kind === 'pdf';   // measurement: PDF o photo

                    function removeAt(idx) {
                        const f = dt.files[idx];
                        if (urls.has(f)) { URL.revokeObjectURL(urls.get(f)); urls.delete(f); }
                        dt.items.remove(idx);
                        input.files = dt.files;
                        render();
                    }
                    function render() {
                        list.innerHTML = '';
                        Array.from(dt.files).forEach((file, idx) => {
                            const rm = document.createElement('button');
                            rm.type = 'button';
                            rm.setAttribute('aria-label', isPhoto ? 'Remove photo' : 'Remove file');
                            rm.addEventListener('click', () => removeAt(idx));
                            if (isPhoto) {
                                if (!urls.has(file)) urls.set(file, URL.createObjectURL(file));
                                const wrap = document.createElement('div');
                                wrap.className = 'relative';
                                const img = document.createElement('img');
                                img.src = urls.get(file);
                                img.title = file.name;
                                img.className = 'w-full aspect-square object-cover rounded-lg border border-gray-200';
                                rm.className = 'absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-gray-800 text-white flex items-center justify-center hover:bg-red-600 shadow';
                                rm.innerHTML = '<i class="fa-solid fa-xmark text-[10px] leading-none"></i>';
                                wrap.append(img, rm);
                                list.appendChild(wrap);
                            } else {
                                const li = document.createElement('li');
                                li.className = 'flex items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 min-w-0';
                                if (svPhotoTypes.includes(file.type)) {
                                    if (!urls.has(file)) urls.set(file, URL.createObjectURL(file));
                                    const th = document.createElement('img');
                                    th.src = urls.get(file);
                                    th.alt = '';
                                    th.className = 'w-7 h-7 rounded-md object-cover border border-gray-200 shrink-0';
                                    li.appendChild(th);
                                } else {
                                    li.innerHTML = '<span class="w-7 h-7 rounded-md bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-pdf text-xs"></i></span>';
                                }
                                const info = document.createElement('span');
                                info.className = 'min-w-0 flex-1';
                                const nm = document.createElement('span');
                                nm.className = 'block text-[12px] font-medium text-gray-800 truncate';
                                nm.textContent = file.name;
                                const sz = document.createElement('span');
                                sz.className = 'block text-[10.5px] text-gray-400';
                                sz.textContent = fmtSize(file.size);
                                info.append(nm, sz);
                                rm.className = 'inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 shrink-0';
                                rm.innerHTML = '<i class="fa-solid fa-xmark text-sm leading-none"></i>';
                                li.append(info, rm);
                                list.appendChild(li);
                            }
                        });
                        svUpdateChecklist();
                        svRefreshSpaces();
                    }
                    function add(files) {
                        let bad = 0, tooBig = 0;
                        files.forEach(file => {
                            if (isPhoto) {
                                if (!svPhotoTypes.includes(file.type)) { bad++; return; }
                            } else {
                                const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                                const isImg = isMeas && svPhotoTypes.includes(file.type);
                                if (!isPdf && !isImg) { bad++; return; }
                                if (isPdf && file.size > MAX_PDF) { tooBig++; return; }
                            }
                            const isDup = Array.from(dt.files).some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                            if (!isDup) dt.items.add(file);
                        });
                        input.files = dt.files;
                        render();
                        if (bad) crmShowToast(isPhoto ? `${bad} file(s) skipped — only JPG, PNG or WEBP photos are allowed.` : `${bad} file(s) skipped — measurements must be a PDF or a JPG, PNG or WEBP photo.`, 'warning');
                        if (tooBig) crmShowToast(`${tooBig} PDF(s) skipped — over 15MB.`, 'warning');
                    }
                    input.addEventListener('change', () => add(Array.from(input.files)));
                    svWireDrop(drop, add);
                    return {
                        count: () => dt.files.length,
                        files: () => Array.from(dt.files),
                        urlOf: f => { if (!urls.has(f)) urls.set(f, URL.createObjectURL(f)); return urls.get(f); },
                        hasFiles: () => dt.files.length > 0,
                        destroy: () => urls.forEach(u => URL.revokeObjectURL(u)),
                    };
                }

                // ── Other site photos ──
                const svGeneralPhotos = svFileBox({
                    input: document.getElementById('sv_photos'),
                    drop: document.getElementById('sv_photo_drop'),
                    list: document.getElementById('sv_photo_preview'),
                    kind: 'photo',
                });

