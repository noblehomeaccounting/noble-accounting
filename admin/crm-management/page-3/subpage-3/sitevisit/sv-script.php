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

        </script>