<?php
// 2d-sitevisit-spaces.php
// Site Visit (view only) para sa 2D & Quotation page: mga space (hal. Kitchen) na may
// requirements, remark, measurement files at photos — galing sa pinakabagong site visit record.
// Kasama ng 2d-and-quotation.php (include, pagkatapos ma-check ang access sa inquiry).
// Kailangan: $conn, $inquiryId. Walang sinusulat sa database.

$svvUrl = fn(string $p) => BASE_URL . '/' . ltrim($p, '/');
$svvCsv = fn(?string $csv) => array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
$svvIsImg = fn(string $p) => (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $p);

$svvData = null;
try {
    $st = $conn->prepare("
        SELECT id, visit_datetime, visited, photos, measurement_files, site_conditions,
               client_requirements, existing_structure, created_at
        FROM noblecrm_sitevisit
        WHERE inquiry_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $st->bind_param('i', $inquiryId);
    $st->execute();
    $svvRow = $st->get_result()->fetch_assoc();
    $st->close();

    if ($svvRow) {
        $spaces = [];
        $spaceFiles = [];
        $sp = @$conn->prepare("
            SELECT space_name, requirements, notes, measurement_files, photos
            FROM noblecrm_sitevisit_space
            WHERE sitevisit_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        if ($sp) { // wala pang spaces table → lumang record lang
            $sid = (int) $svvRow['id'];
            $sp->bind_param('i', $sid);
            $sp->execute();
            foreach ($sp->get_result()->fetch_all(MYSQLI_ASSOC) as $s) {
                $files = $svvCsv($s['measurement_files']);
                $photos = $svvCsv($s['photos']);
                $spaceFiles = array_merge($spaceFiles, $files, $photos);
                $spaces[] = [
                    'name' => (string) $s['space_name'],
                    'requirements' => (string) ($s['requirements'] ?? ''),
                    'notes' => (string) ($s['notes'] ?? ''),
                    'files' => array_map(fn($p) => ['url' => $svvUrl($p), 'name' => basename($p), 'img' => $svvIsImg($p)], $files),
                    'photos' => array_map($svvUrl, $photos),
                ];
            }
            $sp->close();
        }

        // Ibang site photos / measurement files na hindi nakatali sa isang space (pati lumang record)
        $used = array_flip($spaceFiles);
        $otherPhotos = array_values(array_filter($svvCsv($svvRow['photos']), fn($p) => !isset($used[$p])));
        $otherFiles = array_values(array_filter($svvCsv($svvRow['measurement_files']), fn($p) => !isset($used[$p])));

        $svvData = [
            'visit_datetime' => $svvRow['visit_datetime'] ? date('M d, Y g:i A', strtotime($svvRow['visit_datetime'])) : null,
            'visited' => $svvRow['visited'],
            'site_conditions' => (string) ($svvRow['site_conditions'] ?? ''),
            'client_requirements' => (string) ($svvRow['client_requirements'] ?? ''),
            'existing_structure' => (string) ($svvRow['existing_structure'] ?? ''),
            'spaces' => $spaces,
            'other_photos' => array_map($svvUrl, $otherPhotos),
            'other_files' => array_map(fn($p) => ['url' => $svvUrl($p), 'name' => basename($p), 'img' => $svvIsImg($p)], $otherFiles),
        ];
    }
} catch (Throwable $e) {
    error_log('2d-sitevisit-spaces: ' . $e->getMessage());
    $svvData = null;
}
?>

<!-- ═══ SITE VISIT (view only) ═══ -->
<div id="svvModal" class="hidden fixed inset-0 z-[9999] bg-black/40 items-end sm:items-center justify-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="svvTitle">
    <div
        class="bg-white w-full sm:max-w-3xl max-h-[92vh] sm:max-h-[88vh] rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden">
        <div class="q2d-hazard h-1.5 shrink-0"></div>
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100 shrink-0">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-amber-700">Site visit · view only
                </p>
                <h2 id="svvTitle" class="text-sm font-semibold text-gray-900">Spaces &amp; measurements</h2>
                <p id="svvSub" class="text-[11px] text-gray-500 mt-0.5"></p>
            </div>
            <button type="button" data-svv-close aria-label="Close"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i
                    class="fa-solid fa-xmark text-base leading-none"></i></button>
        </div>
        <div id="svvBody"
            class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 py-4 bg-slate-50/60 space-y-3 [scrollbar-width:thin]">
        </div>
    </div>
</div>

<!-- Photo viewer -->
<div id="svvLb" class="hidden fixed inset-0 z-[10000] bg-black/85 items-center justify-center p-4">
    <button type="button" id="svvLbClose" aria-label="Close"
        class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white"><i
            class="fa-solid fa-xmark"></i></button>
    <button type="button" id="svvLbPrev" aria-label="Previous"
        class="absolute left-2 sm:left-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white"><i
            class="fa-solid fa-chevron-left"></i></button>
    <img id="svvLbImg" alt="Site photo" class="max-w-full max-h-[82vh] rounded-lg object-contain">
    <button type="button" id="svvLbNext" aria-label="Next"
        class="absolute right-2 sm:right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white"><i
            class="fa-solid fa-chevron-right"></i></button>
    <p id="svvLbCount" class="absolute bottom-4 left-1/2 -translate-x-1/2 text-[12px] text-white/80"></p>
</div>

<script>
    window.SVV_DATA = <?= json_encode($svvData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    (function () {
        const D = window.SVV_DATA;
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const modal = document.getElementById('svvModal');
        const body = document.getElementById('svvBody');
        let lbList = [], lbIdx = 0;

        // ── Card sa gilid ng 2D page (tinatawag ng q2dRender) ──
        // Ipakita ang "Site visit" button sa header kapag may record
        if (D) {
            const btn = document.getElementById('q2dSiteVisitBtn');
            if (btn) { btn.classList.remove('hidden'); btn.classList.add('inline-flex'); }
        }

        window.q2dSvCardHtml = function () {
            if (!D) return '';
            const n = D.spaces.length;
            const notVisited = D.visited === 'no';
            return `
            <button type="button" onclick="svvOpen()" class="q2d-card w-full text-left px-4 py-3.5 hover:border-amber-300 transition-colors">
                <p class="q2d-eyebrow"><i class="fa-solid fa-ruler-combined mr-1"></i>Site visit</p>
                <p class="text-[13px] font-semibold text-gray-900 mt-0.5">${n ? `${n} space${n === 1 ? '' : 's'}` : 'Notes &amp; photos'}</p>
                <p class="text-[11px] ${notVisited ? 'text-red-600 font-semibold' : 'text-gray-500'}">${notVisited ? 'Site was not visited' : esc(D.visit_datetime || '')}</p>
                <p class="mt-1.5 text-[11px] font-semibold text-amber-700">View measurements <i class="fa-solid fa-arrow-right text-[9px] ml-0.5"></i></p>
            </button>`;
        };

        function fileLinks(files) {
            if (!files.length) return '';
            return `<div class="space-y-1">${files.map(f => `
                <a href="${esc(f.url)}" target="_blank" rel="noopener" title="${esc(f.name)}"
                    class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] text-gray-800 hover:border-amber-600 hover:text-amber-800 min-w-0">
                    <i class="fa-solid ${f.img ? 'fa-file-image text-violet-500' : 'fa-file-pdf text-red-500'} shrink-0"></i>
                    <span class="truncate flex-1">${esc(f.name)}</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-gray-400 shrink-0"></i>
                </a>`).join('')}</div>`;
        }

        // Thumbnails — ang data-svv-photo ay index sa lbAll
        let lbAll = [];
        function thumbs(urls) {
            if (!urls.length) return '';
            return `<div class="grid grid-cols-4 sm:grid-cols-6 gap-1.5">${urls.map(u => {
                const i = lbAll.push(u) - 1;
                return `<button type="button" data-svv-photo="${i}" class="aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-100 hover:ring-2 hover:ring-amber-500">
                    <img src="${esc(u)}" alt="Site photo" loading="lazy" class="w-full h-full object-cover"></button>`;
            }).join('')}</div>`;
        }

        function textBlock(label, text, tone = '') {
            if (!String(text || '').trim()) return '';
            return `<div class="${tone || 'bg-white border-gray-200'} border rounded-lg px-3 py-2">
                <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">${label}</p>
                <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${esc(text)}</p></div>`;
        }

        function render() {
            lbAll = [];
            document.getElementById('svvSub').textContent = D.visit_datetime
                ? `Visited ${D.visit_datetime}${D.visited === 'no' ? ' · site was not visited' : ''}` : '';

            const spaces = D.spaces.map((s, i) => `
                <details class="group bg-white border border-gray-200 rounded-xl overflow-hidden" ${D.spaces.length <= 3 ? 'open' : ''}>
                    <summary class="list-none cursor-pointer px-4 py-3 flex items-center gap-2.5 hover:bg-gray-50">
                        <span class="w-6 h-6 rounded-md bg-amber-50 text-amber-700 text-[11px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                        <span class="text-[13px] font-semibold text-gray-900 truncate flex-1">${esc(s.name)}</span>
                        <span class="text-[10.5px] text-gray-400 whitespace-nowrap">${s.files.length} file${s.files.length === 1 ? '' : 's'} · ${s.photos.length} photo${s.photos.length === 1 ? '' : 's'}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="px-4 pb-4 pt-1 space-y-2.5 border-t border-gray-100">
                        ${textBlock('Requirements', s.requirements)}
                        ${textBlock('Remark', s.notes, 'bg-amber-50/60 border-amber-200')}
                        ${s.files.length ? `<div class="space-y-1.5"><p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1">Measurements</p>${fileLinks(s.files.filter(f => !f.img))}${thumbs(s.files.filter(f => f.img).map(f => f.url))}</div>` : ''}
                        ${s.photos.length ? `<div><p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1">Photos</p>${thumbs(s.photos)}</div>` : ''}
                        ${!s.requirements && !s.notes && !s.files.length && !s.photos.length ? '<p class="text-[12px] text-gray-400 italic">No details recorded.</p>' : ''}
                    </div>
                </details>`).join('');

            const general = [
                textBlock('Site conditions', D.site_conditions),
                textBlock('Client requirements', D.client_requirements),
                textBlock('Existing structure', D.existing_structure),
            ].join('');
            const others = (D.other_files.length || D.other_photos.length) ? `
                <section class="bg-white border border-gray-200 rounded-xl p-4 space-y-2.5">
                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-images mr-1 text-amber-700"></i>Other site files</p>
                    ${fileLinks(D.other_files.filter(f => !f.img))}${thumbs(D.other_files.filter(f => f.img).map(f => f.url).concat(D.other_photos))}
                </section>` : '';

            body.innerHTML = `
                ${D.spaces.length ? `<p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-ruler-combined mr-1 text-amber-700"></i>Spaces (${D.spaces.length})</p>${spaces}` : ''}
                ${general ? `<section class="space-y-2"><p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 pt-1"><i class="fa-solid fa-clipboard mr-1 text-amber-700"></i>Notes</p>${general}</section>` : ''}
                ${others}
                ${!D.spaces.length && !general && !others ? '<p class="text-sm text-gray-400 italic text-center py-8">Nothing was recorded on this site visit.</p>' : ''}`;
        }

        window.svvOpen = function () {
            if (!D) return;
            render();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.documentElement.classList.add('overflow-hidden');
            body.scrollTop = 0;
        };
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.documentElement.classList.remove('overflow-hidden');
        }
        modal.addEventListener('click', e => {
            if (e.target === modal || e.target.closest('[data-svv-close]')) return close();
            const t = e.target.closest('[data-svv-photo]');
            if (t) openLb(+t.dataset.svvPhoto);
        });

        // ── Photo viewer ──
        const lb = document.getElementById('svvLb');
        const lbImg = document.getElementById('svvLbImg');
        function showLb(i) {
            lbIdx = (i + lbAll.length) % lbAll.length;
            lbImg.src = lbAll[lbIdx];
            document.getElementById('svvLbCount').textContent = `${lbIdx + 1} of ${lbAll.length}`;
            const multi = lbAll.length > 1;
            document.getElementById('svvLbPrev').style.display = multi ? '' : 'none';
            document.getElementById('svvLbNext').style.display = multi ? '' : 'none';
        }
        function openLb(i) { showLb(i); lb.classList.remove('hidden'); lb.classList.add('flex'); }
        function closeLb() { lb.classList.add('hidden'); lb.classList.remove('flex'); }
        document.getElementById('svvLbClose').addEventListener('click', closeLb);
        document.getElementById('svvLbPrev').addEventListener('click', () => showLb(lbIdx - 1));
        document.getElementById('svvLbNext').addEventListener('click', () => showLb(lbIdx + 1));
        lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });

        document.addEventListener('keydown', e => {
            if (!lb.classList.contains('hidden')) {
                if (e.key === 'Escape') { closeLb(); e.stopImmediatePropagation(); }
                if (e.key === 'ArrowLeft') showLb(lbIdx - 1);
                if (e.key === 'ArrowRight') showLb(lbIdx + 1);
                return;
            }
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });
    })();
</script>