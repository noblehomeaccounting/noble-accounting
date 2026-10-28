<?php
// sitevisit/step5-review.php — Step 5 · Review & save (inline confirm)
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                            <!-- 5. Review & save -->
                            <section data-step="5" class="sv-card p-5 hidden">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">5</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Review &amp; save</h2>
                                        <p class="text-[11px] text-gray-400">Check everything below. Tap Edit to go back to a step.</p>
                                    </div>
                                </div>
                                <div id="sv_review" class="space-y-2.5"></div>
                                    <div id="sv_save_area" class="mt-4">
                                        <button type="button" id="sv_save_btn"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm">
                                            <i class="fa-solid fa-floppy-disk"></i> Save site visit record
                                        </button>
                                    </div>

                                    <!-- Inline confirm -->
                                    <div id="sv_confirm" class="hidden mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3.5">
                                        <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>Save this record?</p>
                                        <p id="sv_confirm_text" class="text-[11.5px] text-amber-800 mt-1"></p>
                                        <p class="text-[11px] text-amber-700 mt-1">The record can't be edited after saving.</p>
                                        <div class="grid grid-cols-2 gap-2 mt-3">
                                            <button type="button" id="sv_confirm_no" class="px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                                            <button type="button" id="sv_confirm_yes" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg">
                                                <i class="fa-solid fa-check"></i> Yes, save
                                            </button>
                                        </div>
                                    </div>

                            </section>
