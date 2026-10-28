<?php
// sitevisit/step1-visit.php — Step 1 · Visit details
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                         
                            <section data-step="1" class="sv-card p-5">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">1</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Visit details</h2>
                                        <p class="text-[11px] text-gray-400">When did you go, and did the visit happen?</p>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="sv-label" for="sv_datetime">Date &amp; time of visit <span class="text-red-500">*</span></label>
                                        <input id="sv_datetime" type="datetime-local" name="visit_datetime" required
                                            value="<?= $svOldVal('visit_datetime') ?>" class="sv-field h-[40px]">
                                        <p class="sv-err hidden text-[11px] text-red-600 mt-1">Enter the date and time of the visit.</p>
                                    </div>
                                    <div>
                                        <label class="sv-label" for="sv_deadline">Initial deadline for 2D &amp; Quotation</label>
                                        <input id="sv_deadline" type="date" name="deadline" value="<?= $svOldVal('deadline') ?>"
                                            class="sv-field h-[40px]" min="<?= htmlspecialchars(date('Y-m-d')) ?>">
                                        <p id="sv_deadline_hint" class="text-[11px] text-gray-400 mt-1">Optional — when you expect to finish the 2D &amp; quotation.</p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <p class="sv-label">Did the site visit happen? <span class="text-red-500">*</span></p>
                                    <div id="sv_visited_group" class="grid sm:grid-cols-2 gap-2">
                                        <label class="sv-choice cursor-pointer">
                                            <input type="radio" name="visited" value="yes" class="sr-only" <?= ($svOld['visited'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                            <span class="sv-choice-box"><i class="fa-solid fa-circle-check text-emerald-600"></i><span><b class="font-semibold">Yes, visited</b><span class="block text-[11px] opacity-75 font-normal">Proceed to 2D &amp; Quotation</span></span></span>
                                        </label>
                                        <label class="sv-choice cursor-pointer">
                                            <input type="radio" name="visited" value="no" class="sr-only" <?= ($svOld['visited'] ?? '') === 'no' ? 'checked' : '' ?>>
                                            <span class="sv-choice-box"><i class="fa-solid fa-circle-xmark text-amber-600"></i><span><b class="font-semibold">No, not visited</b><span class="block text-[11px] opacity-75 font-normal">Client unavailable, rescheduled, etc.</span></span></span>
                                        </label>
                                    </div>
                                    <p class="sv-err hidden text-[11px] text-red-600 mt-1">Choose Yes or No.</p>
                                </div>

                                <div class="mt-4">
                                    <label class="sv-label">Address</label>
                                    <input type="text" name="visit_address" readonly value="<?= htmlspecialchars($inquiry['address']) ?>" class="sv-field h-[40px]">
                                    <p class="text-[11px] text-gray-400 mt-1"><i class="fa-solid fa-lock mr-1"></i>Taken from the inquiry.</p>
                                </div>
                            </section>
