<?php
// sitevisit/step3-spaces.php — Step 3 · Spaces & measurements (space list)
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                            <!-- 3. Spaces: space muna, saka ang sukat at photos nito -->
                            <section id="sv_spaces_section" data-step="3" class="sv-card p-5 hidden">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="sv-num">3</span>
                                        <div>
                                            <h2 class="text-sm font-semibold text-gray-900 leading-tight">Spaces &amp; measurements</h2>
                                            <p class="text-[11px] text-gray-400">Name each space, then fill in its requirements, site photos, measurement and remark.</p>
                                        </div>
                                    </div>
                                    <span id="sv_space_count" class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10.5px] font-bold">0 spaces</span>
                                </div>

                                <!-- Add space -->
                                <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50/40 p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <div class="relative flex-1 min-w-[180px]">
                                            <i class="fa-solid fa-vector-square absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-amber-700/70 pointer-events-none"></i>
                                            <input id="sv_space_name" type="text" list="sv_space_suggest" maxlength="100" autocomplete="off"
                                                placeholder="Space name, e.g. Kitchen" class="sv-field h-[40px] !pl-8">
                                        </div>
                                        <button type="button" id="sv_space_add"
                                            class="inline-flex items-center gap-1.5 h-[40px] px-4 rounded-lg bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold shadow-sm">
                                            <i class="fa-solid fa-plus"></i> Add space
                                        </button>
                                    </div>
                                    <datalist id="sv_space_suggest">
                                        <?php foreach (array_unique(array_merge($svInquirySpaces, $svSpaceSuggest)) as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <?php if ($svInquirySpaces): ?>
                                        <p class="text-[11px] text-gray-500 mt-2.5 mb-1.5"><i class="fa-solid fa-clipboard-list mr-1 text-amber-700"></i>From the inquiry's scope area — tap to add:</p>
                                        <div id="sv_space_chips" class="flex flex-wrap gap-1.5">
                                            <?php foreach ($svInquirySpaces as $chip): ?>
                                                <button type="button" data-space-chip="<?= htmlspecialchars($chip) ?>"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-white border border-amber-200 text-amber-800 hover:bg-amber-100 disabled:bg-emerald-50 disabled:border-emerald-200 disabled:text-emerald-700 disabled:cursor-default">
                                                    <i class="fa-solid fa-plus text-[9px]"></i><?= htmlspecialchars($chip) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <p id="sv_space_err" class="hidden text-[11px] text-red-600 mt-1.5"></p>
                                </div>

                                <!-- Toolbar ng listahan -->
                                <div id="sv_space_tools" class="hidden mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                                    <span id="sv_space_status" class="text-gray-500"></span>
                                    <span class="hidden lg:inline text-gray-400">· Drag files onto <b class="font-semibold">Add photos</b> / <b class="font-semibold">PDF / photo</b> too.</span>
                                    <button type="button" id="sv_whole_btn"
                                        class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-900 hover:bg-black text-white text-[11.5px] font-semibold">
                                        <i class="fa-solid fa-table-list"></i> Whole view
                                    </button>
                                </div>

                                <!-- Space list: lahat nakikita (parang table); sariling scroll kapag marami -->
                                <div class="mt-2 md:rounded-xl md:border md:border-gray-200 md:overflow-hidden">
                                    <div class="max-h-[75vh] overflow-y-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent]">
                                        <div id="sv_space_head" class="hidden md:grid-cols-[44px_minmax(130px,1fr)_minmax(160px,1.4fr)_170px_150px_minmax(140px,1.2fr)_36px] md:gap-3 sticky top-0 z-10 bg-gray-50 border-b border-gray-200 px-2 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                            <span>No.</span><span>Space name</span><span>Requirements</span><span>Site photos</span><span>Measurement</span><span>Remark</span><span></span>
                                        </div>
                                        <div id="sv_space_list" class="space-y-2 md:space-y-0 md:divide-y md:divide-gray-100"></div>
                                    </div>
                                </div>

                                <div id="sv_space_empty" class="mt-3 rounded-xl border-2 border-dashed border-gray-200 px-4 py-8 text-center">
                                    <span class="w-10 h-10 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid fa-vector-square"></i></span>
                                    <p class="text-[13px] font-semibold text-gray-700">No spaces yet</p>
                                    <p class="text-[11.5px] text-gray-400 mt-0.5">Add a space above to record its measurements.</p>
                                </div>
                                <p id="sv_spaces_req_err" class="hidden mt-2 text-[11px] text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>Add at least one space for a visited site.</p>
                            </section>
