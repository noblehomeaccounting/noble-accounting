<?php
// sitevisit/sv-done.php — naka-save na record: overview, details, space list, lightbox
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>

<?php
$latestPhotos = array_values(array_filter(explode(',', $latestVisit['photos'] ?? '')));
$latestMeasFiles = array_values(array_filter(explode(',', $latestVisit['measurement_files'] ?? '')));

// Mga photo ng bawat space muna, saka ang "other" photos — iisang lightbox
$svSpacePhotoSet = [];
foreach ($svSpaces as $sp)
    foreach ($svCsv($sp['photos']) as $p)
        $svSpacePhotoSet[$p] = true;
$svGeneralPhotos = $svSpaces ? array_values(array_filter($latestPhotos, fn($p) => !isset($svSpacePhotoSet[$p]))) : $latestPhotos;
$svAllPhotos = array_merge(array_keys($svSpacePhotoSet), $svGeneralPhotos);
// Measurement photos (hindi PDF) → kasama sa lightbox, pero hindi binibilang na "site photos"
$svIsImg = fn($p) => (bool) preg_match('/\.(webp|jpe?g|png)$/i', $p);
$svMeasImgs = [];
foreach ($svSpaces as $sp)
    foreach ($svCsv($sp['measurement_files']) as $p)
        if ($svIsImg($p))
            $svMeasImgs[] = $p;
// Photos at measurement photo ng mga item → kasama rin sa lightbox
$svItemImgs = [];
foreach ($svSpaceItems as $list)
    foreach ($list as $it)
        foreach (array_merge($svCsv($it['photos'] ?? ''), $svCsv($it['measurement_files'] ?? '')) as $p)
            if ($svIsImg($p))
                $svItemImgs[] = $p;
$svLightbox = array_values(array_unique(array_merge($svAllPhotos, $svMeasImgs, $svItemImgs)));
$svPhotoIdx = array_flip($svLightbox);

// Overview: bilang ng spaces, may sukat, photos, PDF
$ovSpaces = count($svSpaces);
$ovMeasured = 0;
$ovWithReq = 0;
$svItemMeasured = function (array $sp) use ($svSpaceItems, $svCsv): bool {
    foreach ($svSpaceItems[(int) $sp['id']] ?? [] as $it) if ($svCsv($it['measurement_files'] ?? '')) return true;
    return false;
};
foreach ($svSpaces as $sp) {
    if ($svCsv($sp['measurement_files']) || $svItemMeasured($sp))
        $ovMeasured++;
    if (trim((string) ($sp['requirements'] ?? '')) !== '')
        $ovWithReq++;
}
$ovPhotos = count($svAllPhotos);
$ovPdfs = count($latestMeasFiles);
$ovPct = $ovSpaces ? (int) round($ovMeasured / $ovSpaces * 100) : 0;
$ovItems = array_sum(array_map('count', $svSpaceItems));
// Listahan ng item ng isang space (HTML, naka-escape)
$svItemsHtml = function (array $sp) use ($svSpaceItems, $svItemSize, $svCsv, $svIsImg, $svPhotoIdx): string {
    $list = $svSpaceItems[(int) $sp['id']] ?? [];
    if (!$list) return '<span class="text-gray-300">—</span>';
    $out = '<ul class="space-y-1">';
    foreach ($list as $it) {
        $extra = array_filter([$svItemSize($it), (int) $it['qty'] > 1 ? '×' . (int) $it['qty'] : '', (string) ($it['material'] ?? '')]);
        $out .= '<li class="leading-snug"><span class="font-semibold text-gray-900">' . htmlspecialchars($it['item_name']) . '</span>'
            . ($extra ? '<span class="block text-[11px] text-gray-500">' . htmlspecialchars(implode(' · ', $extra)) . '</span>' : '')
            . (!empty($it['remark']) ? '<span class="block text-[11px] text-gray-400 italic">' . htmlspecialchars($it['remark']) . '</span>' : '');
        // photos + PDF/photo ng sukat ng item
        $imgs = array_values(array_filter(array_merge($svCsv($it['photos'] ?? ''), $svCsv($it['measurement_files'] ?? '')), $svIsImg));
        $pdfs = array_values(array_filter($svCsv($it['measurement_files'] ?? ''), fn($p) => !$svIsImg($p)));
        if ($imgs || $pdfs) {
            $out .= '<span class="mt-1 flex flex-wrap items-center gap-1">';
            foreach ($imgs as $p) {
                $out .= '<button type="button" data-sv-photo="' . (int) ($svPhotoIdx[$p] ?? 0) . '" class="block w-9 h-9 rounded overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600"><img src="' . htmlspecialchars(BASE_URL . '/' . $p) . '" alt="" loading="lazy" class="w-full h-full object-cover"></button>';
            }
            foreach ($pdfs as $n => $p) {
                $out .= '<a href="' . htmlspecialchars(BASE_URL . '/' . $p) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded border border-gray-200 px-1.5 py-0.5 text-[10.5px] font-semibold text-gray-700 hover:border-amber-600"><i class="fa-solid fa-file-pdf text-red-500"></i>PDF ' . ($n + 1) . '</a>';
            }
            $out .= '</span>';
        }
        $out .= '</li>';
    }
    return $out . '</ul>';
};
?>

<!-- Next step -->
<div
    class="rounded-xl border px-4 py-3.5 mb-4 flex flex-wrap items-center gap-3 <?= $latestVisited ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
    <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i
            class="fa-solid <?= $latestVisited ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold"><?= $latestVisited ? 'Ready to proceed' : 'Site not yet visited' ?></p>
        <p class="text-xs opacity-80"><?= $latestVisited
            ? 'The site visit is recorded. You can now prepare the 2D layout and quotation.'
            : 'Schedule a revisit with the client so this inquiry can proceed.' ?></p>
    </div>
    <?php if ($latestVisited && $inquiry['status'] === 'In Progress'): ?>
        <a href="<?= htmlspecialchars($crm2dQuotationUrl) ?>"
            class="w-full sm:w-auto justify-center inline-flex items-center gap-2 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg px-3.5 py-2.5 sm:py-2 shrink-0">
            Go to 2D &amp; Quotation <i class="fa-solid fa-arrow-right"></i>
        </a>
    <?php endif; ?>
</div>

<!-- ═══ OVERVIEW ═══ -->
<?php if ($latestVisited || $svSpaces): ?>
    <section class="sv-card overflow-hidden mb-4">
        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500"><i
                    class="fa-solid fa-gauge-high mr-1.5 text-amber-700"></i>Site visit overview</p>
            <span
                class="sm:ml-auto text-[11px] text-gray-400"><?= htmlspecialchars(date('M d, Y · g:i A', strtotime($latestVisit['visit_datetime']))) ?></span>
            <?php if ($svSpaces): ?>
                <button type="button" data-sv-list-open
                    class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-3 py-2 sm:py-1.5 shrink-0">
                    <i class="fa-solid fa-table-list"></i> View space list
                </button>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-gray-100">
            <div class="bg-white px-3 sm:px-4 py-3 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i
                        class="fa-solid fa-vector-square mr-1"></i>Spaces</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums"><?= $ovSpaces ?></p>
                <p class="text-[11px] text-gray-400"><?= $ovItems ?> item<?= $ovItems === 1 ? '' : 's' ?> · <?= $ovWithReq ?> with requirements</p>
            </div>
            <div class="bg-white px-3 sm:px-4 py-3 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i
                        class="fa-solid fa-ruler-combined mr-1"></i>Measured</p>
                <p
                    class="text-xl sm:text-2xl font-bold leading-tight tabular-nums <?= $ovSpaces && $ovMeasured === $ovSpaces ? 'text-emerald-700' : 'text-amber-700' ?>">
                    <?= $ovMeasured ?><span class="text-sm text-gray-400 font-semibold"> / <?= $ovSpaces ?></span></p>
                <div class="mt-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full <?= $ovPct === 100 ? 'bg-emerald-500' : 'bg-amber-500' ?>"
                        style="width: <?= $ovPct ?>%"></div>
                </div>
            </div>
            <div class="bg-white px-3 sm:px-4 py-3 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i
                        class="fa-regular fa-image mr-1"></i>Site photos</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums"><?= $ovPhotos ?></p>
                <p class="text-[11px] text-gray-400"><?= count($svGeneralPhotos) ?> other ·
                    <?= $ovPhotos - count($svGeneralPhotos) ?> in spaces</p>
            </div>
            <div class="bg-white px-3 sm:px-4 py-3 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i
                        class="fa-solid fa-ruler-combined mr-1"></i>Measurement files</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums"><?= $ovPdfs ?></p>
                <p class="text-[11px] text-gray-400"><?= $ovSpaces - $ovMeasured ?>
                    space<?= $ovSpaces - $ovMeasured === 1 ? '' : 's' ?> without measurement</p>
            </div>
        </div>
        <?php if ($svSpaces): ?>
            <div class="px-4 sm:px-5 py-3 border-t border-gray-100">
                <p class="text-[10.5px] font-semibold uppercase tracking-wider text-gray-400 mb-2">Spaces · tap to view in the
                    list</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($svSpaces as $si => $sp):
                        $has = (bool) $svCsv($sp['measurement_files']) || $svItemMeasured($sp); ?>
                        <button type="button" data-sv-list-open="<?= $si + 1 ?>"
                            class="inline-flex items-center gap-1.5 pl-1 pr-2.5 py-1 rounded-full border text-[11.5px] font-semibold <?= $has ? 'bg-emerald-50 border-emerald-200 text-emerald-800 hover:bg-emerald-100' : 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100' ?>">
                            <span
                                class="w-5 h-5 rounded-full bg-white/80 text-[10px] font-bold flex items-center justify-center"><?= $si + 1 ?></span>
                            <?= htmlspecialchars($sp['space_name']) ?>
                            <?php if (!empty($svSpaceItems[(int) $sp['id']])): ?><span class="text-[10px] font-bold opacity-70">· <?= count($svSpaceItems[(int) $sp['id']]) ?></span><?php endif; ?>
                            <i class="fa-solid <?= $has ? 'fa-check' : 'fa-ruler' ?> text-[9px]"></i>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if ($ovMeasured < $ovSpaces): ?>
                    <p class="mt-2 text-[11px] text-amber-800"><i
                            class="fa-solid fa-triangle-exclamation mr-1"></i><?= $ovSpaces - $ovMeasured ?>
                        space<?= $ovSpaces - $ovMeasured === 1 ? ' has' : 's have' ?> no measurement yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">

    <!-- Left column -->
    <div class="lg:col-span-2 space-y-4 min-w-0">

        <!-- Visit details -->
        <section class="sv-card overflow-hidden">
            <p class="px-4 sm:px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <i class="fa-solid fa-clipboard-check mr-1.5 text-amber-700"></i>Visit details</p>
            <dl class="grid sm:grid-cols-2 gap-px bg-gray-100">
                <div class="bg-white px-4 sm:px-5 py-3">
                    <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Date &amp; time of
                        visit</dt>
                    <dd class="text-[13px] font-semibold text-gray-900 mt-0.5">
                        <?= htmlspecialchars(date('F d, Y · g:i A', strtotime($latestVisit['visit_datetime']))) ?></dd>
                </div>
                <div class="bg-white px-4 sm:px-5 py-3">
                    <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Visited</dt>
                    <dd class="mt-1">
                        <span
                            class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $latestVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                            <i
                                class="fa-solid <?= $latestVisited ? 'fa-check' : 'fa-xmark' ?>"></i><?= $latestVisited ? 'Yes — proceed to 2D & Quotation' : 'No — not visited' ?>
                        </span>
                    </dd>
                </div>
                <div class="bg-white px-4 sm:px-5 py-3 sm:col-span-2">
                    <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Address visited</dt>
                    <dd class="text-[13px] text-gray-900 mt-0.5 break-words">
                        <?= htmlspecialchars($latestVisit['address']) ?></dd>
                </div>
                <div class="bg-white px-4 sm:px-5 py-3 sm:col-span-2">
                    <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-400">Logged on</dt>
                    <dd class="text-[12.5px] text-gray-500 mt-0.5">
                        <?= htmlspecialchars(date('F d, Y · g:i A', strtotime($latestVisit['created_at']))) ?></dd>
                </div>
            </dl>
        </section>

        <!-- Site notes -->
        <section class="sv-card overflow-hidden">
            <p class="px-4 sm:px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <i class="fa-solid fa-note-sticky mr-1.5 text-amber-700"></i>Site notes</p>
            <div class="p-4 sm:p-5 grid sm:grid-cols-3 gap-3 sm:gap-4">
                <?php foreach ([
                    ['fa-mountain-sun', 'Site conditions', $latestVisit['site_conditions']],
                    ['fa-user-pen', 'Client requirements', $latestVisit['client_requirements']],
                    ['fa-house-chimney-crack', 'Existing structure', $latestVisit['existing_structure']],
                ] as [$ic, $lbl, $val]): ?>
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-3.5 min-w-0">
                        <p class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5"><i
                                class="fa-solid <?= $ic ?> mr-1 text-gray-400"></i><?= $lbl ?></p>
                        <div class="break-words"><?= svNotes($val) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!$svSpaces): ?>
            <!-- Measurements -->
            <section class="sv-card overflow-hidden">
                <p class="px-4 sm:px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                    <i class="fa-solid fa-ruler mr-1.5 text-amber-700"></i>Measurements</p>
                <div class="p-4 sm:p-5">
                    <?php if (!empty($latestMeasFiles)): ?>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <?php foreach ($latestMeasFiles as $mi => $mPath): ?>
                                <a href="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 rounded-xl border border-gray-200 px-3.5 py-2.5 hover:border-amber-600 hover:bg-amber-50/40 transition-colors min-w-0">
                                    <span
                                        class="w-9 h-9 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i
                                            class="fa-solid fa-file-pdf"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-semibold text-gray-900">Measurement PDF
                                            <?= $mi + 1 ?></span>
                                        <span
                                            class="block text-[11px] text-gray-400 truncate"><?= htmlspecialchars(basename($mPath)) ?></span>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-400"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!empty($latestVisit['measurements'])): ?>
                        <?= svNotes($latestVisit['measurements']) ?>
                    <?php else: ?>
                        <p class="text-[13px] text-gray-400 italic">No measurement files were uploaded.</p>
                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>

        <!-- Prior entries -->
        <?php if (count($svHistory) > 1): ?>
            <section class="sv-card overflow-hidden">
                <p class="px-4 sm:px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                    <i class="fa-solid fa-clock-rotate-left mr-1.5 text-amber-700"></i>Prior entries</p>
                <ul class="divide-y divide-gray-100">
                    <?php foreach (array_slice($svHistory, 1) as $entry): ?>
                        <?php
                        $entryVisited = $entry['visited'] === 'yes';
                        $entryPhotos = array_filter(explode(',', $entry['photos'] ?? ''));
                        ?>
                        <li class="px-4 sm:px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px]">
                            <span
                                class="font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($entry['visit_datetime']))) ?></span>
                            <span
                                class="text-gray-500 min-w-0 basis-full sm:basis-auto order-last sm:order-none flex-1 truncate"><?= htmlspecialchars($entry['address']) ?></span>
                            <span class="text-gray-400 whitespace-nowrap"><i
                                    class="fa-regular fa-image mr-1"></i><?= count($entryPhotos) ?></span>
                            <span
                                class="inline-flex items-center text-[11px] font-semibold px-2.5 py-0.5 rounded-full <?= $entryVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                <?= $entryVisited ? 'Visited' : 'Not visited' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

    </div>

    <!-- Right column -->
    <div class="space-y-4 min-w-0">
        <section class="sv-card overflow-hidden">
            <p class="px-4 sm:px-5 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <i
                    class="fa-solid fa-camera mr-1.5 text-amber-700"></i><?= $svSpaces ? 'Other site photos' : 'Photographs' ?>
                <?php if (!empty($svGeneralPhotos)): ?><span
                        class="text-gray-400 font-semibold">(<?= count($svGeneralPhotos) ?>)</span><?php endif; ?>
            </p>
            <div class="p-4">
                <?php if (empty($svGeneralPhotos)): ?>
                    <div class="rounded-xl border-2 border-dashed border-gray-200 px-4 py-8 text-center">
                        <i class="fa-regular fa-image text-xl text-gray-300"></i>
                        <p class="text-[12.5px] text-gray-400 mt-1">
                            <?= $svSpaces ? 'No other photos. Space photos are shown under each space.' : 'No photographs were submitted for this visit.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ($svGeneralPhotos as $photoPath):
                            $pi = (int) $svPhotoIdx[$photoPath]; ?>
                            <button type="button" data-sv-photo="<?= $pi ?>"
                                class="block aspect-square rounded-lg overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600 transition">
                                <img src="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>" alt="Site photo <?= $pi + 1 ?>"
                                    loading="lazy" class="w-full h-full object-cover">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

</div>

<?php if ($svSpaces): ?>
    <!-- ═══ SPACE LIST (modal) ═══ -->
    <div id="svListModal" class="fixed inset-0 z-[9990] hidden items-center justify-center bg-black/50 p-0 sm:p-6"
        role="dialog" aria-modal="true" aria-labelledby="svListTitle">
        <section class="bg-white sm:rounded-2xl shadow-2xl w-full max-w-6xl h-full sm:h-auto sm:max-h-full flex flex-col overflow-hidden">
            <div class="h-1.5 shrink-0 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex items-center gap-3 shrink-0">
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i
                        class="fa-solid fa-table-list text-xs"></i></span>
                <div class="min-w-0 flex-1">
                    <p id="svListTitle" class="text-sm font-semibold text-gray-900">Space list <span
                            class="text-gray-400 font-semibold">(<?= count($svSpaces) ?>)</span></p>
                    <p class="text-[11px] text-gray-400 truncate">Tap a photo to enlarge · tap a PDF to open</p>
                </div>
                <button type="button" data-sv-list-close aria-label="Close"
                    class="-mr-2 sm:-mr-1 inline-flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
            </div>
            <div id="svListBody"
                class="min-h-0 flex-1 overflow-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent]">
                <table class="hidden md:table w-full min-w-[1000px] text-[12.5px]">
                    <thead
                        class="sticky top-0 z-10 bg-gray-50 text-[10.5px] font-bold uppercase tracking-wider text-gray-500 shadow-[0_1px_0_#e5e7eb]">
                        <tr>
                            <th class="px-3 py-2.5 text-left w-14">No.</th>
                            <th class="px-3 py-2.5 text-left w-40">Space name</th>
                            <th class="px-3 py-2.5 text-left">Items</th>
                            <th class="px-3 py-2.5 text-left">Requirements</th>
                            <th class="px-3 py-2.5 text-left w-52">Site photos</th>
                            <th class="px-3 py-2.5 text-left w-44">Measurement</th>
                            <th class="px-3 py-2.5 text-left">Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        <?php foreach ($svSpaces as $si => $sp):
                            $spPdfs = $svCsv($sp['measurement_files']);
                            $spPhotos = $svCsv($sp['photos']);
                            $shown = array_slice($spPhotos, 0, 4);
                            $more = count($spPhotos) - count($shown); ?>
                            <tr id="sv-space-<?= $si + 1 ?>" class="hover:bg-amber-50/30 transition-colors">
                                <td class="px-3 py-3"><span
                                        class="inline-flex w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold items-center justify-center"><?= $si + 1 ?></span>
                                </td>
                                <td class="px-3 py-3 font-semibold text-gray-900 break-words">
                                    <?= htmlspecialchars($sp['space_name']) ?></td>
                                <td class="px-3 py-3 text-gray-800"><?= $svItemsHtml($sp) ?></td>
                                <td class="px-3 py-3 text-gray-800 whitespace-pre-line break-words">
                                    <?= !empty($sp['requirements']) ? htmlspecialchars($sp['requirements']) : '<span class="text-gray-300">—</span>' ?>
                                </td>
                                <td class="px-3 py-3">
                                    <?php if ($spPhotos): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($shown as $k => $photoPath): ?>
                                                <button type="button" data-sv-photo="<?= (int) ($svPhotoIdx[$photoPath] ?? 0) ?>"
                                                    class="relative block w-11 h-11 rounded-md overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600">
                                                    <img src="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>"
                                                        alt="<?= htmlspecialchars($sp['space_name']) ?> photo" loading="lazy"
                                                        class="w-full h-full object-cover">
                                                    <?php if ($k === 3 && $more > 0): ?>
                                                        <span
                                                            class="absolute inset-0 bg-black/55 text-white text-[11px] font-bold flex items-center justify-center">+<?= $more ?></span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3">
                                    <?php if ($spPdfs): ?>
                                        <div class="flex flex-col gap-1">
                                            <?php $pdfNo = 0;
                                            $measImgs = array_values(array_filter($spPdfs, $svIsImg)); ?>
                                            <?php foreach ($spPdfs as $mPath):
                                                if ($svIsImg($mPath))
                                                    continue;
                                                $pdfNo++; ?>
                                                <a href="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2 py-1 text-[11.5px] font-semibold text-gray-700 hover:border-amber-600 hover:text-amber-800 w-max max-w-full">
                                                    <i class="fa-solid fa-file-pdf text-red-500"></i>PDF <?= $pdfNo ?>
                                                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-gray-400"></i>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if ($measImgs): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach ($measImgs as $mPath): ?>
                                                        <button type="button" data-sv-photo="<?= (int) ($svPhotoIdx[$mPath] ?? 0) ?>"
                                                            title="Measurement photo"
                                                            class="relative block w-11 h-11 rounded-md overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-600">
                                                            <img src="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>"
                                                                alt="<?= htmlspecialchars($sp['space_name']) ?> measurement" loading="lazy"
                                                                class="w-full h-full object-cover">
                                                            <span
                                                                class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[8px] font-bold text-center leading-3"><i
                                                                    class="fa-solid fa-ruler"></i></span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10.5px] font-semibold"><i
                                                class="fa-solid fa-ruler text-[9px]"></i>No measurement</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-gray-800 whitespace-pre-line break-words">
                                    <?= !empty($sp['notes']) ? htmlspecialchars($sp['notes']) : '<span class="text-gray-300">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Phone: card bawat space (ang table ay para sa md+) -->
                <div class="md:hidden p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] space-y-2.5">
                    <?php foreach ($svSpaces as $si => $sp):
                        $spFiles = $svCsv($sp['measurement_files']);
                        $spPhotos = $svCsv($sp['photos']);
                        $mImgs = array_values(array_filter($spFiles, $svIsImg));
                        $mPdfs = array_values(array_filter($spFiles, fn($p) => !$svIsImg($p))); ?>
                        <article id="sv-space-m-<?= $si + 1 ?>" class="rounded-xl border border-gray-200 bg-white p-3 text-[12.5px]">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex w-7 h-7 rounded-lg bg-amber-700 text-white text-[11px] font-bold items-center justify-center shrink-0"><?= $si + 1 ?></span>
                                <p class="font-semibold text-gray-900 min-w-0 flex-1 break-words"><?= htmlspecialchars($sp['space_name']) ?></p>
                                <?php if (!$spFiles && !$svItemMeasured($sp)): ?>
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10.5px] font-semibold"><i class="fa-solid fa-ruler text-[9px]"></i>No measurement</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($svSpaceItems[(int) $sp['id']])): ?>
                                <p class="mt-2.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Items</p>
                                <div class="mt-1 text-gray-800"><?= $svItemsHtml($sp) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($sp['requirements'])): ?>
                                <p class="mt-2.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Requirements</p>
                                <p class="mt-0.5 text-gray-800 whitespace-pre-line break-words"><?= htmlspecialchars($sp['requirements']) ?></p>
                            <?php endif; ?>
                            <?php if ($spPhotos || $mImgs || $mPdfs): ?>
                                <p class="mt-2.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Photos &amp; measurement</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <?php foreach ($spPhotos as $photoPath): ?>
                                        <button type="button" data-sv-photo="<?= (int) ($svPhotoIdx[$photoPath] ?? 0) ?>"
                                            class="block w-14 h-14 rounded-md overflow-hidden border border-gray-200 active:ring-2 active:ring-amber-600">
                                            <img src="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>" alt="" loading="lazy" class="w-full h-full object-cover">
                                        </button>
                                    <?php endforeach; ?>
                                    <?php foreach ($mImgs as $mPath): ?>
                                        <button type="button" data-sv-photo="<?= (int) ($svPhotoIdx[$mPath] ?? 0) ?>" title="Measurement photo"
                                            class="relative block w-14 h-14 rounded-md overflow-hidden border border-gray-200">
                                            <img src="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" alt="" loading="lazy" class="w-full h-full object-cover">
                                            <span class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[9px] font-bold text-center leading-4"><i class="fa-solid fa-ruler"></i></span>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php foreach ($mPdfs as $n => $mPath): ?>
                                        <a href="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-1.5 text-[11.5px] font-semibold text-gray-700">
                                            <i class="fa-solid fa-file-pdf text-red-500"></i>PDF <?= $n + 1 ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($sp['notes'])): ?>
                                <p class="mt-2.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Remark</p>
                                <p class="mt-0.5 text-gray-800 whitespace-pre-line break-words"><?= htmlspecialchars($sp['notes']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
<?php endif; ?>

<!-- Photo lightbox -->
<?php if (!empty($svLightbox)): ?>
    <div id="svLightbox" class="fixed inset-0 z-[9998] hidden bg-black/80 flex-col items-center justify-center p-3 sm:p-4"
        role="dialog" aria-modal="true">
        <div class="w-full max-w-4xl flex items-center justify-between text-white mb-3">
            <span id="svLbCount" class="text-xs font-semibold opacity-80"></span>
            <div class="flex items-center gap-1">
                <a id="svLbOpen" href="#" target="_blank" rel="noopener"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10"
                    aria-label="Open original"><i class="fa-solid fa-up-right-from-square text-sm"></i></a>
                <button type="button" id="svLbClose"
                    class="inline-flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 rounded-lg text-white/70 hover:text-white hover:bg-white/10 shrink-0"
                    aria-label="Close"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
            </div>
        </div>
        <div class="relative w-full max-w-4xl flex items-center justify-center">
            <button type="button" id="svLbPrev"
                class="absolute left-0 w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
            <img id="svLbImg" src="" alt="" class="max-w-full rounded-lg object-contain">
            <button type="button" id="svLbNext"
                class="absolute right-0 w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
    <script>
        window.SV_PHOTOS = <?= json_encode(array_map(fn($p) => BASE_URL . '/' . $p, $svLightbox)) ?>;
    </script>
<?php endif; ?>