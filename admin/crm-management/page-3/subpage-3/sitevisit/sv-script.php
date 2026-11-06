<?php
// sitevisit/sv-script.php — JS: toast, success message, photo lightbox ng naka-save na record
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
        <script>
            function crmShowToast(message, type = 'success', duration = 4000) {
                const container = document.getElementById('crmToastContainer');
                const palette = type === 'success'
                    ? { icon: 'bg-emerald-500', symbol: '✓' }
                    : { icon: 'bg-amber-500', symbol: '!' };

                const toast = document.createElement('div');
                toast.className = `pointer-events-auto flex items-start gap-2.5 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-800
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white ${palette.icon}">${palette.symbol}</span>
            <span class="flex-1 leading-relaxed"></span>
            <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>
        `;
                toast.querySelector('.flex-1').textContent = message;
                container.appendChild(toast);
                requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
                const remove = () => {
                    toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                    setTimeout(() => toast.remove(), 300);
                };
                toast.querySelector('button').addEventListener('click', remove);
                if (duration > 0) setTimeout(remove, duration);
            }

            const SV_INQUIRY_ID = <?= (int) $inquiryId ?>;
            const SV_DRAFT_KEY = 'svDraft_' + SV_INQUIRY_ID;

            <?php if ($svSuccess): ?>
                try { localStorage.removeItem(SV_DRAFT_KEY); sessionStorage.removeItem('svStep_' + SV_INQUIRY_ID); } catch (e) { }
                crmShowToast(<?= json_encode($svSuccess) ?>, 'success');
            <?php endif; ?>

            // Expand / collapse ng mga space (saved record)
            document.querySelectorAll('[data-sv-spaces]').forEach(b => b.addEventListener('click', () => {
                const open = b.dataset.svSpaces === 'open';
                document.querySelectorAll('#svDoneSpaces details').forEach(d => d.open = open);
            }));

            // ═══════════════ PHOTO LIGHTBOX (saved record) ═══════════════
            (function () {
                const lb = document.getElementById('svLightbox');
                if (!lb || !window.SV_PHOTOS) return;
                const img = document.getElementById('svLbImg');
                let idx = 0;
                const show = i => {
                    idx = (i + SV_PHOTOS.length) % SV_PHOTOS.length;
                    img.src = SV_PHOTOS[idx];
                    document.getElementById('svLbOpen').href = SV_PHOTOS[idx];
                    document.getElementById('svLbCount').textContent = `Photo ${idx + 1} of ${SV_PHOTOS.length}`;
                    const multi = SV_PHOTOS.length > 1;
                    document.getElementById('svLbPrev').style.display = multi ? '' : 'none';
                    document.getElementById('svLbNext').style.display = multi ? '' : 'none';
                };
                const open = i => { show(i); lb.classList.remove('hidden'); lb.classList.add('flex'); };
                const close = () => { lb.classList.add('hidden'); lb.classList.remove('flex'); };
                document.querySelectorAll('[data-sv-photo]').forEach(b => b.addEventListener('click', () => open(+b.dataset.svPhoto)));
                document.getElementById('svLbClose').addEventListener('click', close);
                document.getElementById('svLbPrev').addEventListener('click', () => show(idx - 1));
                document.getElementById('svLbNext').addEventListener('click', () => show(idx + 1));
                lb.addEventListener('click', e => { if (e.target === lb) close(); });
                document.addEventListener('keydown', e => {
                    if (lb.classList.contains('hidden')) return;
                    // unahin ang lightbox: huwag nang isara ang Space list modal sa parehong Escape
                    if (e.key === 'Escape') { close(); e.stopImmediatePropagation(); }
                    if (e.key === 'ArrowLeft') show(idx - 1);
                    if (e.key === 'ArrowRight') show(idx + 1);
                });
            })();

            // ═══════════════ SPACE LIST MODAL (saved record) ═══════════════
            (function () {
                const modal = document.getElementById('svListModal');
                if (!modal) return;
                const body = document.getElementById('svListBody');
                const HL = ['bg-amber-50', 'ring-2', 'ring-inset', 'ring-amber-300'];
                const open = n => {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.documentElement.classList.add('overflow-hidden');
                    body.scrollTop = 0;
                    const row = n ? document.getElementById('sv-space-' + n) : null;
                    if (row) {
                        body.scrollTop = Math.max(0, row.getBoundingClientRect().top - body.getBoundingClientRect().top - 44); // 44 = sticky header
                        row.classList.add(...HL);
                        setTimeout(() => row.classList.remove(...HL), 1800);
                    }
                    modal.querySelector('[data-sv-list-close]').focus({ preventScroll: true });
                };
                const close = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.documentElement.classList.remove('overflow-hidden');
                };
                document.querySelectorAll('[data-sv-list-open]').forEach(b =>
                    b.addEventListener('click', () => open(+b.dataset.svListOpen || 0)));
                modal.querySelectorAll('[data-sv-list-close]').forEach(b => b.addEventListener('click', close));
                modal.addEventListener('click', e => { if (e.target === modal) close(); });
                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
                });
            })();

            // ═══════════════ NAWAWALANG FILE (Re-upload / Remove) ═══════════════
            // Kapag hindi ma-load ang photo o PDF ng naka-save na record (hal. nabura sa server),
            // lalabas sa mismong thumbnail ang "Re-upload" at "Remove".
            (function () {
                const MARK = 'svMissing';
                const input = document.createElement('input');
                input.type = 'file';
                input.style.display = 'none';
                document.body.appendChild(input);
                let pending = null;   // { path, isPdf }

                const relPath = url => {
                    const m = String(url || '').match(/uploads\/crm-sitevisit\/[A-Za-z0-9_\-]+\.(webp|jpe?g|png|pdf)/i);
                    return m ? m[0] : '';
                };
                const post = async (fields) => {
                    const fd = new FormData();
                    fd.append('inquiry_id', SV_INQUIRY_ID);
                    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
                    const res = await fetch(location.href, { method: 'POST', body: fd });
                    return res.json();
                };
                const done = (data) => {
                    crmShowToast(data.message || (data.success ? 'Done.' : 'Something went wrong.'), data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 900);
                };

                input.addEventListener('change', async () => {
                    const f = input.files[0];
                    if (!f || !pending) return;
                    if (pending.isPdf && f.type && f.type !== 'application/pdf') { crmShowToast('Please choose a PDF file.', 'error'); input.value = ''; return; }
                    if (f.size > 50 * 1024 * 1024) { crmShowToast('That file is too large.', 'error'); input.value = ''; return; }
                    crmShowToast('Uploading…', 'success', 1500);
                    try { done(await post({ sv_fix: 'replace', path: pending.path, file: f })); }
                    catch (e) { crmShowToast('Connection error. Please try again.', 'error'); }
                    input.value = '';
                });

                const pick = (path, isPdf) => {
                    pending = { path, isPdf };
                    input.accept = isPdf ? 'application/pdf' : 'image/jpeg,image/png,image/webp';
                    input.click();
                };
                const removeFile = async (path) => {
                    if (!confirm('Remove this missing file from the record? This cannot be undone.')) return;
                    try { done(await post({ sv_fix: 'remove', path })); }
                    catch (e) { crmShowToast('Connection error. Please try again.', 'error'); }
                };

                // Thumbnail (img) na sira → takpan ng Re-upload + maliit na × (Remove)
                function markImg(img) {
                    if (img.dataset[MARK] || img.id === 'svLbImg') return;
                    const path = relPath(img.getAttribute('src'));
                    if (!path) return;
                    img.dataset[MARK] = '1';
                    const host = img.closest('button, a') || img.parentElement;
                    // huwag buksan ang lightbox ng sirang larawan
                    host.addEventListener('click', e => { e.preventDefault(); e.stopImmediatePropagation(); }, true);
                    if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
                    img.style.visibility = 'hidden';
                    const cover = document.createElement('span');
                    cover.className = 'absolute inset-0 flex flex-col items-center justify-center gap-0.5 rounded-[inherit] bg-red-50 border border-dashed border-red-300 text-red-600 cursor-pointer hover:bg-red-100';
                    cover.title = 'File is missing on the server — tap to re-upload';
                    cover.innerHTML = '<i class="fa-solid fa-cloud-arrow-up text-[13px]"></i><span class="text-[8.5px] font-bold uppercase leading-none">Re-upload</span>';
                    cover.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); pick(path, false); });
                    const x = document.createElement('span');
                    x.className = 'absolute -top-1.5 -right-1.5 z-10 w-[18px] h-[18px] rounded-full bg-white border border-gray-300 text-gray-500 hover:text-red-600 hover:border-red-300 flex items-center justify-center shadow-sm cursor-pointer';
                    x.title = 'Remove from record';
                    x.innerHTML = '<i class="fa-solid fa-xmark text-[9px]"></i>';
                    x.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); removeFile(path); });
                    host.appendChild(cover);
                    host.appendChild(x);
                }

                // PDF link na wala na → maliit na "Missing · Re-upload · Remove" sa tabi
                function markPdf(a) {
                    if (a.dataset[MARK]) return;
                    const path = relPath(a.getAttribute('href'));
                    if (!path) return;
                    a.dataset[MARK] = '1';
                    a.classList.add('line-through', 'opacity-60');
                    a.addEventListener('click', e => e.preventDefault());
                    const box = document.createElement('span');
                    box.className = 'inline-flex items-center gap-1.5 ml-1.5 text-[10.5px] font-semibold align-middle';
                    box.innerHTML = '<span class="text-red-600">Missing</span>'
                        + '<button type="button" data-a="up" class="px-1.5 py-0.5 rounded bg-amber-700 text-white hover:bg-amber-800">Re-upload</button>'
                        + '<button type="button" data-a="rm" class="px-1.5 py-0.5 rounded border border-gray-300 text-gray-600 hover:text-red-600">Remove</button>';
                    box.querySelector('[data-a="up"]').addEventListener('click', e => { e.preventDefault(); pick(path, true); });
                    box.querySelector('[data-a="rm"]').addEventListener('click', e => { e.preventDefault(); removeFile(path); });
                    a.insertAdjacentElement('afterend', box);
                }

                document.querySelectorAll('img[src*="uploads/crm-sitevisit/"]').forEach(img => {
                    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) markImg(img);
                    img.addEventListener('error', () => markImg(img));
                });
                const seen = new Set();
                document.querySelectorAll('a[href*="uploads/crm-sitevisit/"][href$=".pdf"]').forEach(a => {
                    const href = a.href;
                    if (seen.has(href)) return;   // iisang check lang bawat PDF
                    seen.add(href);
                    fetch(href, { method: 'HEAD', cache: 'no-store' })
                        .then(r => { if (r.status === 404) document.querySelectorAll(`a[href="${CSS.escape(a.getAttribute('href'))}"]`).forEach(markPdf); })
                        .catch(() => { });
                });
            })();

        </script>