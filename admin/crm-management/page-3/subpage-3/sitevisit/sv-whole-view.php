<?php
// sitevisit/sv-whole-view.php — "Whole view" modal — lahat ng space naka-list (read-only)
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
        <!-- ═══ WHOLE VIEW: lahat ng space, naka-list (read-only) ═══ -->
        <div id="svWhole" class="hidden fixed inset-0 z-[9990] bg-black/40 backdrop-blur-[1px] p-3 sm:p-6 items-start justify-center overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="svWholeTitle">
            <div class="w-full max-w-6xl bg-white rounded-2xl shadow-2xl overflow-hidden my-auto">
                <div class="sv-hazard h-1.5"></div>
                <div class="px-5 py-4 border-b border-gray-100 flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-table-list"></i></span>
                    <div class="min-w-0 flex-1">
                        <h2 id="svWholeTitle" class="text-sm font-semibold text-gray-900">Whole view — all spaces</h2>
                        <p id="svWholeSub" class="text-[11px] text-gray-400"></p>
                    </div>
                    <button type="button" id="svWholeClose" aria-label="Close"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
                <div class="max-h-[75vh] overflow-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent]">
                    <table class="hidden md:table w-full min-w-[900px] text-[12.5px]">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-[10.5px] font-bold uppercase tracking-wider text-gray-500 shadow-[0_1px_0_#e5e7eb]">
                            <tr>
                                <th class="px-3 py-2.5 text-left w-14">No.</th>
                                <th class="px-3 py-2.5 text-left w-44">Space name</th>
                                <th class="px-3 py-2.5 text-left">Requirements</th>
                                <th class="px-3 py-2.5 text-left w-52">Site photos</th>
                                <th class="px-3 py-2.5 text-left w-48">Measurement</th>
                                <th class="px-3 py-2.5 text-left">Remark</th>
                                <th class="px-3 py-2.5 w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="svWholeRows" class="divide-y divide-gray-100 align-top"></tbody>
                    </table>
                    <div id="svWholeCards" class="md:hidden p-3 space-y-2.5"></div>
                </div>
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex flex-wrap items-center gap-2">
                    <span id="svWholeFoot" class="text-[11.5px] text-gray-600"></span>
                    <button type="button" id="svWholeDone" class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg"><i class="fa-solid fa-check"></i> Done</button>
                </div>
            </div>
        </div>

