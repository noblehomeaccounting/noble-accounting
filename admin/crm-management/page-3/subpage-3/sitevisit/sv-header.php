<?php
// sitevisit/sv-header.php — header, stepper ng inquiry at info strip
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                <!-- ═══ HEADER ═══ -->
                <section class="sv-card overflow-hidden mb-4">
                    <div class="sv-hazard h-1.5"></div>
                    <div class="p-5 flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-ruler-combined text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM · Designer · Site Visit</p>
                                <h1 class="text-gray-900 text-lg font-semibold leading-tight truncate capitalize"><?= htmlspecialchars($inquiry['client_name']) ?></h1>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="font-mono text-[11px] font-semibold text-amber-700"><?= htmlspecialchars($inquiry['control_no']) ?></span>
                                    <?php if ($svAlreadyDone): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border <?= $latestVisited ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $latestVisited ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span><?= $latestVisited ? 'Site visit done' : 'Not visited' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>For site visit</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?= htmlspecialchars($crmDesignerListUrl) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                                <i class="fa-solid fa-arrow-left"></i> Assigned list
                            </a>
                            <?php if ($svAlreadyDone && $inquiry['status'] === 'In Progress'): ?>
                                <a href="<?= htmlspecialchars($crm2dQuotationUrl) ?>"
                                    class="inline-flex items-center gap-2 text-xs font-semibold text-white bg-blue-700 rounded-lg px-3.5 py-2 hover:bg-blue-800 shadow-sm">
                                    <i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stepper -->
                    <div class="px-5 pb-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold">
                            <?php
                            $steps = [['fa-user-check', 'Assigned'], ['fa-ruler-combined', 'Site visit'], ['fa-file-pen', '2D & Quotation']];
                            foreach ($steps as $si => [$ic, $lbl]):
                                $done = $si < $stepState || ($si === 1 && $svAlreadyDone && $latestVisited);
                                $cur = $si === $stepState && !$done;
                                ?>
                                <?php if ($si > 0): ?><span class="sv-step-line <?= $si <= $stepState ? 'sv-done' : '' ?>"></span><?php endif; ?>
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap <?= $done ? 'text-amber-800' : ($cur ? 'text-gray-900' : 'text-gray-400') ?>">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] <?= $done ? 'bg-amber-700 text-white' : ($cur ? 'bg-amber-50 text-amber-700 ring-2 ring-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                                        <i class="fa-solid <?= $done ? 'fa-check' : $ic ?>"></i>
                                    </span>
                                    <span class="hidden sm:inline"><?= htmlspecialchars($lbl) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- ═══ INFO STRIP ═══ -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div class="sv-card px-4 py-3 lg:col-span-2 min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-location-dot mr-1"></i>Site address</p>
                        <p class="text-[13px] font-medium text-gray-900 mt-0.5 break-words"><?= htmlspecialchars($inquiry['address'] ?: '—') ?></p>
                        <?php if (!empty($inquiry['address'])): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($inquiry['address']) ?>" target="_blank" rel="noopener"
                                class="text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-map-location-dot mr-1"></i>Open in Maps</a>
                        <?php endif; ?>
                    </div>
                    <div class="sv-card px-4 py-3 min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-phone mr-1"></i>Contact no.</p>
                        <?php if (!empty($inquiry['contact_number'])): ?>
                            <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', $inquiry['contact_number'])) ?>" class="block text-[13px] font-semibold text-amber-700 hover:underline mt-0.5 truncate"><?= htmlspecialchars($inquiry['contact_number']) ?></a>
                        <?php else: ?>
                            <p class="text-[13px] text-gray-400 mt-0.5">—</p>
                        <?php endif; ?>
                        <p class="text-[11px] text-gray-400 mt-0.5 truncate">Designer: <?= htmlspecialchars($currentDesignerName) ?></p>
                    </div>
                    <div class="sv-card px-4 py-3 min-w-0 <?= $dl && $dl[1] === 'red' && $inquiry['status'] !== 'Approved' ? 'border-red-200 bg-red-50' : '' ?>">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-regular fa-flag mr-1"></i>2D &amp; Quotation deadline</p>
                        <?php if (!empty($inquiry['deadline'])): ?>
                            <p class="text-[13px] font-semibold text-gray-900 mt-0.5"><?= htmlspecialchars(date('M d, Y', strtotime($inquiry['deadline']))) ?></p>
                            <?php if ($dl && $inquiry['status'] !== 'Approved'): ?><p class="text-[11px] font-semibold <?= $svToneText[$dl[1]] ?>"><?= htmlspecialchars($dl[0]) ?></p><?php endif; ?>
                        <?php else: ?>
                            <p class="text-[13px] text-gray-400 mt-0.5"><?= $svAlreadyDone ? 'Not set' : 'Set it in the form below' ?></p>
                        <?php endif; ?>
                    </div>
                </section>

