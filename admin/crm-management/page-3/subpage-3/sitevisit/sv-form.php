<?php
// sitevisit/sv-form.php — bagong record: stepper, mga step (hiwalay na file), Back/Next, checklist
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                    <!-- ═══ NEW RECORD FORM ═══ -->
                    <form id="svForm" method="POST" action="" enctype="multipart/form-data" novalidate
                        class="grid lg:grid-cols-3 gap-4 items-start">

                        <input type="hidden" name="inquiry_id" value="<?= (int) $inquiry['id'] ?>">
                        <input type="hidden" name="submit_sitevisit" value="1">

                        <div id="sv_main_col" class="lg:col-span-2 space-y-4 min-w-0">

                            <?php if (!empty($svFormError)): ?>
                                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 flex items-start gap-2.5">
                                    <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5"></i>
                                    <p class="text-[13px] text-red-800"><?= htmlspecialchars($svFormError) ?> <span class="text-red-600">Please re-attach your files.</span></p>
                                </div>
                            <?php endif; ?>

                            <!-- ═══ STEPPER ═══ -->
                            <nav id="sv_stepper" aria-label="Form steps" class="relative sv-card px-3 py-2.5 overflow-x-auto [scrollbar-width:none]">
                                <ol class="flex items-center gap-1 min-w-max">
                                    <li>
                                        <button type="button" data-goto-step="1"
                                            class="group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold text-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed">
                                            <span data-step-dot class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] bg-gray-100 text-gray-400"><i class="fa-solid fa-calendar-check"></i></span>
                                            <span class="whitespace-nowrap">Visit</span>
                                        </button>
                                    </li>
                                    <li aria-hidden="true" class="w-6 sm:flex-1 h-px bg-gray-200" data-step-line="2"></li>
                                    <li>
                                        <button type="button" data-goto-step="2"
                                            class="group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold text-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed">
                                            <span data-step-dot class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] bg-gray-100 text-gray-400"><i class="fa-solid fa-note-sticky"></i></span>
                                            <span class="whitespace-nowrap">Notes</span>
                                        </button>
                                    </li>
                                    <li aria-hidden="true" class="w-6 sm:flex-1 h-px bg-gray-200" data-step-line="3"></li>
                                    <li>
                                        <button type="button" data-goto-step="3"
                                            class="group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold text-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed">
                                            <span data-step-dot class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] bg-gray-100 text-gray-400"><i class="fa-solid fa-ruler-combined"></i></span>
                                            <span class="whitespace-nowrap">Spaces</span>
                                        </button>
                                    </li>
                                    <li aria-hidden="true" class="w-6 sm:flex-1 h-px bg-gray-200" data-step-line="4"></li>
                                    <li>
                                        <button type="button" data-goto-step="4"
                                            class="group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold text-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed">
                                            <span data-step-dot class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] bg-gray-100 text-gray-400"><i class="fa-solid fa-camera"></i></span>
                                            <span class="whitespace-nowrap">Photos</span>
                                        </button>
                                    </li>
                                    <li aria-hidden="true" class="w-6 sm:flex-1 h-px bg-gray-200" data-step-line="5"></li>
                                    <li>
                                        <button type="button" data-goto-step="5"
                                            class="group inline-flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] font-semibold text-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed">
                                            <span data-step-dot class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] bg-gray-100 text-gray-400"><i class="fa-solid fa-clipboard-check"></i></span>
                                            <span class="whitespace-nowrap">Review</span>
                                        </button>
                                    </li>
                                </ol>
                            </nav>

                            <?php include __DIR__ . '/step1-visit.php'; ?>

                            <?php include __DIR__ . '/step2-notes.php'; ?>

                            <?php include __DIR__ . '/step3-spaces.php'; ?>

                            <?php include __DIR__ . '/step4-photos.php'; ?>

                            <?php include __DIR__ . '/step5-review.php'; ?>

                            <!-- Back / Next -->
                            <div id="sv_step_nav" class="sv-card px-4 py-3 flex items-center gap-2">
                                <button type="button" id="sv_prev"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <i class="fa-solid fa-arrow-left"></i> Back
                                </button>
                                <span id="sv_step_label" class="flex-1 text-center text-[11px] font-semibold text-gray-400"></span>
                                <button type="button" id="sv_next"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-gray-900 hover:bg-black rounded-lg">
                                    <span>Next</span> <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Sidebar: checklist + save -->
                        <aside id="sv_side_col" class="space-y-4 lg:sticky lg:top-4 min-w-0">
                            <section class="sv-card overflow-hidden">
                                <div class="sv-hazard h-1"></div>
                                <div class="p-5">
                                    <h2 class="text-sm font-semibold text-gray-900">Record checklist</h2>
                                    <p class="text-[11px] text-gray-400 mb-3">Required items must be done before saving.</p>
                                    <ul id="sv_checklist" class="space-y-2 text-[12.5px]">
                                        <?php foreach ([
                                            ['datetime', 'Date & time of visit', true],
                                            ['visited', 'Visited: Yes or No', true],
                                            ['deadline', '2D & Quotation deadline', false],
                                            ['notes', 'Site notes', false],
                                            ['spaces', 'Spaces added', true],
                                            ['meas', 'Measurement per space', false],
                                            ['photos', 'Photos', false],
                                        ] as [$k, $lbl, $req]): ?>
                                            <li data-check="<?= $k ?>" class="flex items-center gap-2 text-gray-500">
                                                <span class="sv-ck w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-[9px] text-transparent shrink-0"><i class="fa-solid fa-check"></i></span>
                                                <span class="flex-1"><?= htmlspecialchars($lbl) ?></span>
                                                <span data-req class="text-[10px] font-semibold uppercase <?= $req ? 'text-red-500' : 'text-gray-300' ?>"><?= $req ? 'Required' : 'Optional' ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <button type="button" id="sv_goto_review"
                                        class="mt-5 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg">
                                        <i class="fa-solid fa-clipboard-check"></i> Review &amp; save
                                    </button>

                                    <p id="sv_draft_note" class="hidden text-[11px] text-gray-400 mt-3"><i class="fa-solid fa-cloud mr-1"></i>Draft saved on this device (spaces included). Files are not saved in drafts.</p>
                                </div>
                            </section>
                        </aside>
                    </form>
