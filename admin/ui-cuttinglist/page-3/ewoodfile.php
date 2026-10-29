<?php
//  ewoodfile.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$cutListAjaxUrl = BASE_URL . '/cuttinglistajax';
$cutProgAjaxUrl = BASE_URL . '/crmewoodajax';
$bomAjaxUrl = BASE_URL . '/bomajax';
$supAjaxUrl = BASE_URL . '/crmsupplierajax';   // listahan ng supplier at produkto (crmsupplier.php)
$supPageUrl = BASE_URL . '/crmsupplier';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cutting Files &amp; Progress</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-cuttinglist/css/cutting/ewoodfile.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-cuttinglist/css/cutting/ewoodfile.css') ?: 1 ?>">
</head>

<body class="bg-slate-100">
    <main id="ewoodMain" data-view="list"
        class="md:ml-56 pt-14 md:pt-0 h-screen flex overflow-hidden transition-all duration-300">

        <!-- ═════════════════════════════════════════════ -->
        <!-- LEFT: submissions list                         -->
        <!-- ═════════════════════════════════════════════ -->
        <aside id="ewoodSidebar" class="w-full lg:w-[360px] shrink-0 h-full bg-white border-r border-gray-200 flex flex-col">

            <div class="ewood-hazard h-1.5 shrink-0"></div>
            <div class="px-5 pt-4 pb-4 shrink-0 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-scissors"></i></span>
                    <div class="min-w-0">
                        <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Cutting Department</p>
                        <h1 class="text-gray-900 text-base font-semibold leading-tight">Cutting Files &amp; Progress <span id="ewoodLiveDot" title="Connecting…" class="inline-block w-2 h-2 rounded-full bg-amber-400 ml-1 align-middle"></span></h1>
                    </div>
                </div>

                <!-- Counters = filter -->
                <div class="grid grid-cols-3 gap-1.5 mt-4">
                    <button type="button" data-filter="all"
                        class="ewood-chip text-left rounded-lg border px-2.5 py-2 transition-colors">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80">All</span>
                        <span id="ewoodCount_all" class="block text-lg font-bold leading-tight tabular-nums">0</span>
                    </button>
                    <button type="button" data-filter="ready"
                        class="ewood-chip text-left rounded-lg border px-2.5 py-2 transition-colors">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80">Ready</span>
                        <span id="ewoodCount_ready" class="block text-lg font-bold leading-tight tabular-nums">0</span>
                    </button>
                    <button type="button" data-filter="waiting"
                        class="ewood-chip text-left rounded-lg border px-2.5 py-2 transition-colors">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80">Waiting</span>
                        <span id="ewoodCount_waiting" class="block text-lg font-bold leading-tight tabular-nums">0</span>
                    </button>
                </div>

                <!-- Search -->
                <div class="relative mt-3">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="ewoodSearch" placeholder="Search control no., client, branch..."
                        class="w-full text-xs bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:bg-white focus:border-amber-600 transition-colors">
                </div>
                <p class="text-gray-400 text-[10.5px] mt-2 leading-relaxed"><i class="fa-solid fa-circle-info mr-1"></i>A job is <b class="text-gray-500">Ready</b> once it has Notice to Proceed and a verified 2D file.</p>
            </div>

            <div id="ewoodList" class="flex-1 overflow-y-auto"></div>

            <p id="ewoodCount" class="text-[11px] text-gray-400 px-5 py-2.5 border-t border-gray-100 shrink-0"></p>
        </aside>

        <!-- ═════════════════════════════════════════════ -->
        <!-- RIGHT: detail pane                             -->
        <!-- ═════════════════════════════════════════════ -->
        <section id="ewoodDetailPane" class="flex-1 min-w-0 h-full p-3 lg:p-4 flex">

            <!-- Empty state -->
            <div id="ewoodEmptyState" class="m-auto text-center text-gray-400 max-w-xs">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white border border-gray-200 shadow-sm flex items-center justify-center">
                    <i class="fa-solid fa-scissors text-xl text-amber-700"></i>
                </div>
                <p class="text-sm font-semibold text-gray-600">No submission selected</p>
                <p class="text-xs mt-1">Pick a job from the list to view its details, upload progress, and manage its BOM.</p>
            </div>

            <!-- Detail view -->
            <div id="ewoodDetailView"
                class="hidden flex-col w-full h-full bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

                <!-- Header + stepper + tabs -->
                <div class="shrink-0 border-b border-gray-100">
                    <div class="ewood-hazard h-1.5"></div>
                    <div class="px-5 lg:px-7 pt-4">
                        <button type="button" onclick="ewoodBackToList()"
                            class="lg:hidden mb-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 hover:text-amber-700">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i> Back to list
                        </button>

                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p id="ewoodModalControl" class="font-mono text-[11px] font-semibold text-amber-700 tracking-wide"></p>
                                <h2 id="ewoodModalTitle" class="text-xl font-semibold text-gray-900 truncate capitalize">Submission</h2>
                                <div id="ewoodHeaderChips" class="mt-1.5 flex flex-wrap items-center gap-1.5"></div>
                            </div>
                            <button type="button" id="ewoodPrimaryBtn" onclick="ewoodSwitchTab('upload')"
                                class="hidden inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload progress
                            </button>
                        </div>

                        <!-- Job stepper -->
                        <ol id="ewoodStepper" class="mt-4 grid grid-cols-5"></ol>

                        <div class="mt-3 flex gap-1 -mb-px overflow-x-auto">
                            <button type="button" data-tab="details" onclick="ewoodSwitchTab('details')"
                                class="ewood-tab-btn shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-amber-700 text-amber-700">
                                <i class="fa-solid fa-gauge text-[11px]"></i> Overview
                            </button>
                            <button type="button" data-tab="upload" onclick="ewoodSwitchTab('upload')"
                                class="ewood-tab-btn shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-cloud-arrow-up text-[11px]"></i> Upload &amp; BOM
                            </button>
                            <button type="button" data-tab="history" onclick="ewoodSwitchTab('history')"
                                class="ewood-tab-btn shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-clock-rotate-left text-[11px]"></i> History
                                <span id="ewoodHistoryCount"
                                    class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-100 text-gray-500 text-[10px] font-bold items-center justify-center"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div id="ewoodDetailBody" class="flex-1 overflow-y-auto bg-slate-50/60">

                    <!-- Overview panel -->
                    <div id="ewoodPanelDetails" class="px-5 lg:px-7 py-5">
                        <div class="max-w-5xl space-y-4">

                            <!-- Next step -->
                            <div id="ewoodDetailNotice" class="rounded-xl border px-4 py-3.5"></div>

                            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-4">
                                <!-- Requirements -->
                                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <p class="px-4 py-3 border-b border-gray-100 text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-list-check mr-1 text-amber-700"></i>Before uploading</p>
                                    <dl class="divide-y divide-gray-100 text-xs">
                                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                                            <dt class="text-gray-500">Notice to Proceed</dt><dd id="ewoodDetailNtp"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                                            <dt class="text-gray-500">2D file</dt><dd id="ewoodDetailVerified"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                                            <dt class="text-gray-500">Upload access</dt><dd id="ewoodDetailAccess"></dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                                            <dt class="text-gray-500">Branch · Type</dt><dd id="ewoodDetailJob" class="text-gray-800 font-medium text-right capitalize"></dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Latest submission -->
                                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden min-w-0">
                                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-box-open mr-1 text-amber-700"></i>Latest submission</p>
                                        <button type="button" onclick="ewoodSwitchTab('history')" class="text-[11px] font-semibold text-amber-700 hover:underline">All history</button>
                                    </div>
                                    <p id="ewoodDetailLatest" class="sr-only">—</p>
                                    <div id="ewoodLatestCard" class="p-4"></div>
                                </div>
                            </div>

                            <!-- Bills of materials -->
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-file-invoice mr-1 text-amber-700"></i>Bills of materials</p>
                                    <span id="ewoodBomCount" class="text-[11px] text-gray-400"></span>
                                </div>
                                <div id="ewoodBomOverview" class="p-4 flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload & BOM panel (two-step: Save Progress, then Create BOM) -->
                    <form id="ewoodUploadForm" class="hidden px-5 lg:px-7 py-5" onsubmit="return false;">
                        <input type="hidden" name="quotation_id" id="ewoodQuotationId">

                        <div class="max-w-3xl space-y-4">

                            <div id="ewoodUploadBlocked"
                                class="hidden text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                            </div>

                            <div id="ewoodUploadFields" class="space-y-4">

                                <!-- STEP 1 -->
                                <div id="ewoodStep1Card" class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
                                        <span class="w-6 h-6 rounded-full bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">Progress files</p>
                                            <p class="text-[11px] text-gray-400">Choose the archive, photos, and remarks. Nothing is sent yet.</p>
                                        </div>
                                        <button type="button" id="ewoodStep1Edit"
                                            class="hidden ml-auto text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                                            <i class="fa-solid fa-pen mr-1"></i>Edit
                                        </button>
                                    </div>
                                    <div id="ewoodStep1Body" class="p-5 space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">
                                                    <i class="fa-solid fa-file-zipper text-amber-700 mr-1"></i> Archive (.zip / .rar)
                                                </label>
                                                <input type="file" name="archive" accept=".zip,.rar"
                                                    class="w-full text-xs border border-dashed border-gray-300 bg-gray-50 rounded-lg px-2 py-2 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:bg-amber-50 file:text-amber-700 file:text-xs file:font-semibold">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">
                                                    <i class="fa-solid fa-images text-amber-700 mr-1"></i> Photos
                                                    <span class="font-normal text-gray-400">(auto-converted to WebP)</span>
                                                </label>
                                                <input type="file" name="images[]" multiple accept="image/*"
                                                    class="w-full text-xs border border-dashed border-gray-300 bg-gray-50 rounded-lg px-2 py-2 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:bg-amber-50 file:text-amber-700 file:text-xs file:font-semibold">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">Remarks</label>
                                            <textarea name="remarks" rows="3" placeholder="Add notes about this submission..."
                                                class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600"></textarea>
                                        </div>

                                        <p id="ewoodStep1Err" class="hidden text-[11px] text-red-600"></p>

                                        <!-- Step 1: lilipat lang sa BOM. WALA pang ipinapadala hanggang i-Submit sa Step 2. -->
                                        <button type="button" id="ewoodSaveProgressBtn"
                                            class="w-full py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                                            Next: Bill of Materials <i class="fa-solid fa-arrow-right ml-1"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- May BOM na ang job na hindi pa approved → ikakabit na lang ito sa submission -->
                                <div id="ewoodBomRelinkedNotice"
                                    class="hidden bg-white border border-green-200 rounded-xl overflow-hidden">
                                    <div class="px-5 py-4 text-xs text-green-800 bg-green-50">
                                        <i class="fa-solid fa-circle-check mr-1"></i>
                                        This job already has a Bill of Materials waiting for approval
                                        (<span id="ewoodBomExistingNo" class="font-mono font-semibold"></span>).
                                        It will be attached to this submission, so there is no need to create another one.
                                    </div>
                                    <div class="p-4">
                                        <button type="button" id="ewoodSubmitRelinkBtn"
                                            class="w-full py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                                            <i class="fa-solid fa-paper-plane mr-1"></i> Submit to Superadmin
                                        </button>
                                    </div>
                                </div>

                                <!-- STEP 2 — hidden until Step 1 succeeds and no BOM could be auto-relinked -->
                                <div id="ewoodBomSection" class="hidden bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100">
                                        <span class="w-6 h-6 rounded-full bg-amber-700 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">Bill of Materials</p>
                                            <p class="text-[11px] text-gray-400">List the materials needed for this job. You can make as many BOMs as you need (e.g. one per area or per supplier).</p>
                                        </div>
                                    </div>

                                    <div class="p-5 space-y-4">
                                        <!-- Revise mode: naibalik ng Superadmin → ayusin ang dating BOM (parehong BOM number) -->
                                        <div id="ewoodBomReviseNote" class="hidden rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-[11.5px] text-red-900 leading-relaxed">
                                            <p class="font-semibold"><i class="fa-solid fa-pen-to-square mr-1"></i>Fix the BOM if needed — <span id="ewoodBomReviseNo" class="font-mono"></span></p>
                                            <p id="ewoodBomReviseReason" class="hidden mt-1 break-words"></p>
                                            <p class="mt-1 text-red-800/80">The items below are from the BOM that was sent back. Change, add or remove items, then submit. Each BOM keeps its BOM number. You can also add another BOM.</p>
                                            <p id="ewoodBomReviseMissing" class="hidden mt-1.5 font-semibold text-red-700"></p>
                                        </div>
                                        <!-- Mga BOM (isa o higit pa) — ginagawa ng ewoodBomAddCard() -->
                                        <div id="ewoodCatalogNote" class="hidden mb-2 text-[11px] rounded-lg px-3 py-2"></div>
                                        <div id="ewoodBomCards" class="space-y-4"></div>

                                        <button type="button" id="ewoodBomAddCardBtn"
                                            class="w-full inline-flex items-center justify-center gap-2 py-2.5 text-xs font-semibold text-amber-800 bg-amber-50/50 border-2 border-dashed border-amber-300 rounded-xl hover:bg-amber-50 hover:border-amber-400 transition-colors">
                                            <i class="fa-solid fa-plus"></i> Add another BOM
                                        </button>

                                        <p class="text-[10.5px] text-gray-400">
                                            Missing a supplier or product? Add it in <a href="<?= htmlspecialchars($supPageUrl) ?>" target="_blank" class="font-semibold text-amber-700 hover:underline">Suppliers &amp; Products</a>, then click
                                            <button type="button" id="ewoodCatalogReload" class="font-semibold text-amber-700 hover:underline">reload list</button>.
                                            Choose <b class="text-gray-500">Outsource</b> when no supplier carries it yet.
                                        </p>

                                        <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-[11px] text-gray-400 leading-relaxed">
                                            <i class="fa-solid fa-signature mr-1"></i>
                                            Requested By, Noted By, Received By, at Approved By ay awtomatikong napupunan
                                            (pangalan, titulo, at naka-save na pirma) sa bawat departamento habang dumadaan ang
                                            BOM sa kanila. Wala nang manual na ilalagay dito.
                                        </div>

                                        <!-- Step 2: dito pa lang ipapadala ang progress (crmewoodajax?action=upload) kasabay ng BOM (bomajax?action=create) -->
                                        <button type="button" id="ewoodCreateBomBtn"
                                            class="w-full py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 disabled:cursor-not-allowed transition-colors shadow-sm">
                                            <i class="fa-solid fa-paper-plane mr-1"></i> Submit progress &amp; BOM
                                        </button>
                                        <p class="text-[10.5px] text-gray-400 text-center">All BOMs are sent together with the progress. The Superadmin only sees this submission once the BOMs are attached.</p>
                                    </div>
                                </div>

                                <!-- Reference list of BOMs already submitted for this job -->
                                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100">
                                        <i class="fa-solid fa-file-invoice text-amber-700 text-sm"></i>
                                        <p class="text-sm font-semibold text-gray-800">Previously submitted BOMs</p>
                                    </div>
                                    <div id="ewoodBomList" class="p-4 space-y-2 text-xs"></div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- History panel -->
                    <div id="ewoodPanelHistory" class="hidden px-5 lg:px-7 py-5">
                        <div id="ewoodHistory" class="ewood-timeline max-w-3xl space-y-4 text-xs"></div>
                    </div>

                </div>
            </div>

        </section>
    </main>

    <!-- Toasts -->
    <div id="crmToastContainer"
        class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]">
    </div>

    <!-- Double confirmation bago ipadala sa Superadmin: 1) Review + checkbox  2) Final confirm -->
    <div id="ewoodConfirmModal" class="hidden fixed inset-0 z-[9999] items-start sm:items-center justify-center bg-black/50 p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl my-auto overflow-hidden flex flex-col max-h-[94vh]">
            <div class="ewood-hazard h-1.5 shrink-0"></div>
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Before sending to the Superadmin</p>
                    <p id="ewoodCfTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                </div>
                <button type="button" id="ewoodCfClose" aria-label="Close"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <!-- steps -->
            <div class="px-5 pt-3 shrink-0">
                <ol class="flex items-center gap-2 text-[11px] font-semibold">
                    <li id="ewoodCfStep1" class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]">1</span> Review the BOM</li>
                    <li class="flex-1 h-px bg-gray-200"></li>
                    <li id="ewoodCfStep2" class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]">2</span> Final confirm</li>
                </ol>
            </div>
            <div id="ewoodCfBody" class="flex-1 overflow-y-auto px-5 py-4"></div>
            <div id="ewoodCfFoot" class="px-5 py-3 bg-gray-50 border-t border-gray-100 shrink-0"></div>
        </div>
    </div>

    <!-- Product picker: mga produkto ng napiling supplier -->
    <div id="ewoodPickModal" class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh]">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Choose a product</p>
                    <p id="ewoodPickTitle" class="text-sm font-semibold text-gray-800 truncate"></p>
                </div>
                <button type="button" id="ewoodPickClose" aria-label="Close"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div class="px-4 py-2.5 border-b border-gray-100 shrink-0">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                    <input type="text" id="ewoodPickSearch" placeholder="Search code or description…"
                        class="w-full h-9 bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:bg-white focus:border-amber-600">
                </div>
            </div>
            <div id="ewoodPickList" class="flex-1 overflow-y-auto divide-y divide-gray-100"></div>
        </div>
    </div>

    <!-- BOM view modal — renders the BOM like the printed form, not an iframe -->
    <div id="ewoodBomViewModal"
        class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 shrink-0">
                <p id="ewoodBomViewModalTitle" class="text-xs font-semibold text-gray-700 font-mono truncate">BOM</p>
                <div class="flex items-center gap-3 shrink-0">
                    <a id="ewoodBomViewDownload" href="#" target="_blank"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                    <button type="button" onclick="ewoodBomCloseViewModal()" aria-label="Close"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <div id="ewoodBomViewBody" class="p-5 overflow-x-auto"></div>
        </div>
    </div>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none">&times;</button>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_PROG_AJAX_URL = <?= json_encode($cutProgAjaxUrl) ?>;
        const BOM_AJAX_URL = <?= json_encode($bomAjaxUrl) ?>;
        const SUP_AJAX_URL = <?= json_encode($supAjaxUrl) ?>;

        let ewoodActiveQuotationId = null;
        let ewoodRowsMap = {};   // quotation_id -> row data, populated on list load
        let ewoodRowsList = [];  // same rows, in list order
        let ewoodListFilter = 'all';
        let ewoodBomRowIndex = 0;

        // Two-step Upload & BOM state — set once Step 1 (Save Progress)
        // succeeds; reset every time a new row is opened.
        let ewoodCurrentProgressionId = null;

        function ewoodEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Basahin ang sagot ng server nang ligtas. Kapag hindi JSON (PHP error, kulang na route,
        // expired session), ipinapakita ang totoong dahilan imbes na tahimik na "Loading…".
        async function ewoodJson(res) {
            const text = await res.text();
            try { return JSON.parse(text.replace(/^﻿/, '').trim()); }
            catch (e) {
                console.error('Not JSON:', res.status, res.url, text);
                let route = res.url;
                try { route = new URL(res.url).pathname.split('/').pop(); } catch (x) { }
                if (/<!doctype html|<html[\s>]/i.test(text)) {
                    throw new Error(`"${route}" returned a page instead of data. Check that it is registered in the router, or reload if your session expired.`);
                }
                const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(`"${route}" server error (HTTP ${res.status}): ${plain ? plain.slice(0, 220) : 'empty response'}`);
            }
        }

        function ewoodFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        // ── Badges ───────────────────────────────────
        function ewoodNtpBadge(row) {
            const isNtp = row.deposit_status === 'Notice to Proceed';
            const cls = isNtp
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-amber-50 text-amber-700 border-amber-200';
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 ${isNtp ? 'bg-green-600' : 'bg-amber-600'}"></span>
                        ${isNtp ? 'NTP' : 'Hold'}
                    </span>`;
        }

        function ewoodVerifiedBadge(row) {
            const isVerified = !!row.design_2d_verified;
            const cls = isVerified
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-red-50 text-red-600 border-red-200';
            const icon = isVerified ? 'fa-file-circle-check' : 'fa-file-circle-xmark';
            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <i class="fa-solid ${icon} text-[10px]"></i>
                        ${isVerified ? 'Verified' : 'Not verified'}
                    </span>`;
        }

        function ewoodAccessBadge(canUpload) {
            const cls = canUpload
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-gray-100 text-gray-500 border-gray-200';
            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <i class="fa-solid ${canUpload ? 'fa-lock-open' : 'fa-lock'} text-[10px]"></i>
                        ${canUpload ? 'Open' : 'Locked'}
                    </span>`;
        }

        function ewoodApprovalBadge(status) {
            const map = {
                Approved: 'bg-green-50 text-green-700 border-green-200',
                Rejected: 'bg-red-50 text-red-600 border-red-200',
                Pending: 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const cls = map[status] || map.Pending;
            const label = status === 'Pending' ? 'Pending Approval' : status;
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${label}</span>`;
        }

        // ── List ─────────────────────────────────────
        function ewoodIsReady(row) {
            return row.deposit_status === 'Notice to Proceed' && !!row.design_2d_verified;
        }

        // ── Kalagayan ng job sa E-Woodfile (para sa listahan) ──
        // Galing sa cuttinglistajax?action=list → row.track
        function ewoodAgo(v) {
            if (!v) return '';
            const t = new Date(String(v).replace(' ', 'T'));
            const m = Math.floor((Date.now() - t) / 60000);
            if (isNaN(m)) return '';
            if (m < 1) return 'just now';
            if (m < 60) return `${m}m ago`;
            const h = Math.floor(m / 60);
            if (h < 24) return `${h}h ago`;
            const d = Math.floor(h / 24);
            return d < 7 ? `${d}d ago` : ewoodFormatDate(v);
        }
        function ewoodTrack(row) {
            const t = row.track || {};
            const T = (key, label, tone, icon, note = '') => ({ key, label, tone, icon, note });
            if (row.deposit_status !== 'Notice to Proceed') return T('ntp', 'Waiting for NTP', 'gray', 'fa-hourglass-half', 'Accounting gives the Notice to Proceed');
            if (!row.design_2d_verified) return T('verify', 'Verify 2D', 'amber', 'fa-file-circle-exclamation', 'Verify it in Cutting List');
            if (t.upload_approval === 'Pending') return T('qr', 'Waiting for QR approval', 'sky', 'fa-qrcode', `Uploaded ${ewoodAgo(t.upload_at)}`);
            if (t.upload_approval === 'Rejected') return T('back', 'Sent back', 'red', 'fa-rotate-left', `Upload again · ${ewoodAgo(t.upload_at)}`);
            // inaprubahan ang upload pero may BOM na ibinalik (piniling ibalik ng Superadmin)
            if (t.returned_boms > 0)
                return T('bomback', `${t.returned_boms} BOM sent back`, 'red', 'fa-rotate-left', `Fix and upload again${t.upload_at ? ' · ' + ewoodAgo(t.upload_at) : ''}`);
            if (!t.upload_approval) return T('upload', 'To upload', 'amber', 'fa-cloud-arrow-up', 'No progress uploaded yet');
            if (t.upload_status === 'Completed') return T('done', 'Completed', 'green', 'fa-circle-check', `Approved ${ewoodAgo(t.upload_at)}${t.approved_boms ? ` · ${t.approved_boms} BOM${t.approved_boms === 1 ? '' : 's'} signed` : ''}`);
            return T('next', 'Next upload', 'amber', 'fa-cloud-arrow-up', `Last approved ${ewoodAgo(t.upload_at)}`);
        }
        const EWOOD_TONE = {
            gray:  { bar: 'bg-gray-300',   pill: 'bg-gray-100 text-gray-600 border-gray-200',   av: 'bg-gray-100 text-gray-500' },
            amber: { bar: 'bg-amber-400',  pill: 'bg-amber-50 text-amber-800 border-amber-200', av: 'bg-amber-50 text-amber-700' },
            sky:   { bar: 'bg-sky-500',    pill: 'bg-sky-50 text-sky-700 border-sky-200',       av: 'bg-sky-50 text-sky-700' },
            red:   { bar: 'bg-red-500',    pill: 'bg-red-50 text-red-700 border-red-200',       av: 'bg-red-50 text-red-600' },
            green: { bar: 'bg-green-500',  pill: 'bg-green-50 text-green-700 border-green-200', av: 'bg-green-50 text-green-700' },
        };

        function ewoodApplyChipStyles() {
            document.querySelectorAll('.ewood-chip').forEach(chip => {
                const active = chip.dataset.filter === ewoodListFilter;
                chip.classList.toggle('bg-amber-50', active);
                chip.classList.toggle('text-amber-800', active);
                chip.classList.toggle('border-amber-600', active);
                chip.classList.toggle('ring-1', active);
                chip.classList.toggle('ring-amber-600', active);
                chip.classList.toggle('bg-white', !active);
                chip.classList.toggle('text-gray-600', !active);
                chip.classList.toggle('border-gray-200', !active);
            });
        }

        function ewoodRenderList() {
            const list = document.getElementById('ewoodList');
            const q = document.getElementById('ewoodSearch').value.trim().toLowerCase();

            const readyCount = ewoodRowsList.filter(ewoodIsReady).length;
            document.getElementById('ewoodCount_all').textContent = ewoodRowsList.length;
            document.getElementById('ewoodCount_ready').textContent = readyCount;
            document.getElementById('ewoodCount_waiting').textContent = ewoodRowsList.length - readyCount;
            ewoodApplyChipStyles();

            if (ewoodRowsList.length === 0) {
                list.innerHTML = `<div class="text-center text-gray-400 py-12 text-xs"><i class="fa-regular fa-folder-open text-2xl mb-2 block"></i>No jobs yet.</div>`;
                document.getElementById('ewoodCount').textContent = '';
                return;
            }

            const filtered = ewoodRowsList.filter(row => {
                const okFilter = ewoodListFilter === 'all'
                    || (ewoodListFilter === 'ready' && ewoodIsReady(row))
                    || (ewoodListFilter === 'waiting' && !ewoodIsReady(row));
                const hay = `${row.control_no} ${row.client_name} ${row.branch} ${row.project_type ?? ''}`.toLowerCase();
                return okFilter && (!q || hay.includes(q));
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div class="text-center text-gray-400 py-12 text-xs">No submissions match your search.</div>`;
            } else {
                const check = (ok, label) => `<span class="inline-flex items-center gap-1 text-[10.5px] font-medium ${ok ? 'text-green-700' : 'text-gray-400'}"><i class="fa-solid ${ok ? 'fa-circle-check' : 'fa-circle-xmark'} text-[10px]"></i>${label}</span>`;
                list.innerHTML = filtered.map(row => {
                    const tr = ewoodTrack(row);
                    const tone = EWOOD_TONE[tr.tone];
                    const early = tr.key === 'ntp' || tr.key === 'verify';
                    return `
                    <div id="ewoodRow_${row.id}" onclick="ewoodOpenSidebar(${row.id})"
                        class="ewood-row relative flex items-stretch gap-3 pl-5 pr-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${tone.bar}"></span>
                        <span class="w-9 h-9 rounded-lg ${tone.av} flex items-center justify-center shrink-0 text-[11px] font-bold uppercase">${ewoodEscapeHtml(String(row.client_name || '?').trim().slice(0, 2))}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="ewood-row-name text-[13px] font-semibold text-gray-800 truncate capitalize">${ewoodEscapeHtml(row.client_name)}</p>
                                <span class="shrink-0 inline-flex items-center gap-1 px-1.5 py-px rounded-full border text-[9.5px] font-bold whitespace-nowrap ${tone.pill}"><i class="fa-solid ${tr.icon} text-[8.5px]"></i>${ewoodEscapeHtml(tr.label)}</span>
                            </div>
                            <p class="font-mono text-[10.5px] text-gray-400 truncate">${ewoodEscapeHtml(row.control_no)} · <span class="font-sans capitalize">${ewoodEscapeHtml(row.branch)}</span></p>
                            ${early
                                ? `<div class="flex items-center gap-3 mt-1">${check(row.deposit_status === 'Notice to Proceed', 'NTP')}${check(!!row.design_2d_verified, '2D verified')}</div>`
                                : `<p class="text-[10.5px] text-gray-500 mt-1 truncate" title="${ewoodEscapeHtml(tr.note)}">${ewoodEscapeHtml(tr.note)}</p>`}
                        </div>
                    </div>`;
                }).join('');
            }

            document.getElementById('ewoodCount').textContent =
                `Showing ${filtered.length} of ${ewoodRowsList.length} submission${ewoodRowsList.length === 1 ? '' : 's'}`;

            if (ewoodActiveQuotationId !== null) ewoodSetRowHighlight(ewoodActiveQuotationId, true);
        }

        async function ewoodFetchList({ silent = false } = {}) {
            try {
                const res = await fetch(`${CUT_LIST_AJAX_URL}?action=list&filter=all`, { cache: 'no-store' });
                const data = await ewoodJson(res);
                if (!data.success) { if (!silent) crmShowToast(ewoodEscapeHtml(data.message || 'Failed to load submissions.'), 'error'); return false; }

                ewoodRowsMap = {};
                ewoodRowsList = data.rows;
                data.rows.forEach(row => { ewoodRowsMap[row.id] = row; });

                ewoodRenderList();
                return true;
            } catch (e) {
                console.error(e);
                if (!silent) crmShowToast(ewoodEscapeHtml(e.message || 'Connection error while fetching submissions.'), 'error');
                return false;
            }
        }

        document.getElementById('ewoodSearch').addEventListener('input', ewoodRenderList);
        document.querySelectorAll('.ewood-chip').forEach(chip => {
            chip.addEventListener('click', () => { ewoodListFilter = chip.dataset.filter; ewoodRenderList(); });
        });

        // ── History ──────────────────────────────────
        function ewoodRenderHistory(entries, bomsByProgression = {}) {
            const box = document.getElementById('ewoodHistory');
            if (entries.length === 0) {
                box.innerHTML = `<div class="text-center py-12 text-gray-400"><i class="fa-regular fa-clock text-2xl mb-2 block"></i><p class="text-xs">No updates yet.</p></div>`;
                return;
            }
            box.innerHTML = entries.map(e => {
                const boms = bomsByProgression[e.id] || [];
                const dotCls = e.approval_status === 'Approved' ? 'bg-green-500'
                    : e.approval_status === 'Rejected' ? 'bg-red-500' : 'bg-amber-500';
                const bomsHtml = boms.length
                    ? `<div class="flex flex-wrap gap-1.5 mt-2">
                ${boms.map(b => `
                    <button type="button" data-bom-id="${b.id}"
                        class="ewood-history-bom-btn inline-flex items-center gap-1 text-[10.5px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5 hover:bg-amber-100">
                        <i class="fa-solid fa-file-invoice"></i> ${ewoodEscapeHtml(b.bom_number)}
                    </button>
                `).join('')}
               </div>`
                    : '';
                return `
            <div class="relative pl-6">
                <span class="absolute left-0 top-4 w-[9px] h-[9px] rounded-full ${dotCls}"></span>
                <div class="bg-white border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-1.5 gap-2">
                        <span class="font-semibold text-gray-800 text-[13px]">${ewoodEscapeHtml(e.status)}</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            ${ewoodApprovalBadge(e.approval_status)}
                        </div>
                    </div>
                    <p class="text-gray-400 text-[10.5px] mb-2">${ewoodFormatDate(e.created_at)} · by ${ewoodEscapeHtml(e.uploaded_by_name)}</p>
                    ${e.remarks ? `<p class="text-gray-600 mb-2">${ewoodEscapeHtml(e.remarks)}</p>` : ''}
                    ${e.approval_status === 'Rejected' && e.rejection_remarks ? `<p class="text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-2">Rejection reason: ${ewoodEscapeHtml(e.rejection_remarks)}</p>` : ''}
                    ${e.archive_url ? `<a href="${e.archive_url}" target="_blank" class="inline-flex items-center gap-1 text-amber-700 hover:underline mb-2"><i class="fa-solid fa-file-zipper"></i> ${ewoodEscapeHtml(e.archive_original_name || 'Download archive')}</a>` : ''}
                    ${e.photos.length ? `<div class="flex flex-wrap gap-1.5 mt-1">${e.photos.map(p => `<a href="${p}" target="_blank"><img src="${p}" class="w-16 h-16 object-cover rounded-lg border border-gray-200"></a>`).join('')}</div>` : ''}
                    ${bomsHtml}
                </div>
            </div>
        `;
            }).join('');
        }

        // ---------------------------------------------------------------
        // OVERVIEW: stepper sa header, "next step", latest submission, BOMs
        // ---------------------------------------------------------------
        const ewoodOv = { row: null, latest: null, loaded: false, boms: [] };

        function ewoodOvState() {
            const row = ewoodOv.row, latest = ewoodOv.latest;
            const isNtp = row.deposit_status === 'Notice to Proceed';
            const isVerified = !!row.design_2d_verified;
            const appr = latest ? latest.approval_status : null;
            const steps = [
                ['Notice to Proceed', isNtp ? 'done' : 'now'],
                ['2D verified', isVerified ? 'done' : (isNtp ? 'now' : 'todo')],
                ['Upload & BOM', appr === 'Pending' || appr === 'Approved' ? 'done' : (isNtp && isVerified ? 'now' : 'todo')],
                ['Superadmin QR', appr === 'Approved' ? 'done' : appr === 'Pending' ? 'now' : appr === 'Rejected' ? 'warn' : 'todo'],
                ['Completed', appr === 'Approved' && latest.status === 'Completed' ? 'done' : 'todo'],
            ];
            return { isNtp, isVerified, appr, steps };
        }

        function ewoodRenderOverview() {
            if (!ewoodOv.row) return;
            const { isNtp, isVerified, appr, steps } = ewoodOvState();
            const latest = ewoodOv.latest;

            // stepper
            const DOT = {
                done: 'bg-emerald-500 border-emerald-500 text-white',
                now: 'bg-white border-amber-500 text-amber-700 ring-4 ring-amber-50',
                warn: 'bg-red-500 border-red-500 text-white',
                todo: 'bg-white border-gray-200 text-gray-300',
            };
            const TXT = { done: 'text-gray-600', now: 'text-amber-700 font-semibold', warn: 'text-red-600 font-semibold', todo: 'text-gray-400' };
            document.getElementById('ewoodStepper').innerHTML = steps.map(([label, st], i) => `
                <li class="relative flex flex-col items-center text-center px-1 min-w-0">
                    ${i ? `<span class="ewood-step-line absolute top-[11px] right-1/2 w-full" data-on="${st === 'todo' ? 0 : 1}"></span>` : ''}
                    <span class="relative z-10 w-6 h-6 rounded-full border-2 flex items-center justify-center text-[10px] font-bold ${DOT[st]}">${st === 'done' ? '<i class="fa-solid fa-check text-[9px]"></i>' : st === 'warn' ? '!' : i + 1}</span>
                    <span class="mt-1 text-[10.5px] leading-tight ${TXT[st]} truncate max-w-full">${label}</span>
                </li>`).join('');

            // next step
            const canUpload = isNtp && isVerified;
            let tone, icon, title, body, action = '';
            if (!isNtp) { tone = 'gray'; icon = 'fa-hourglass-half'; title = 'Waiting for Notice to Proceed'; body = 'Accounting gives the Notice to Proceed once the deposit is in. Uploads stay locked until then.'; }
            else if (!isVerified) { tone = 'gray'; icon = 'fa-hourglass-half'; title = 'Waiting for the 2D file to be verified'; body = 'Verify the 2D file in Cutting List → Verify 2D. Uploads unlock after that.'; }
            else if (!ewoodOv.loaded) { tone = 'gray'; icon = 'fa-spinner fa-spin'; title = 'Checking the latest submission…'; body = ''; }
            else if (appr === 'Rejected') { tone = 'red'; icon = 'fa-rotate-left'; title = 'Sent back for revision'; body = latest.rejection_remarks ? `Superadmin: “${ewoodEscapeHtml(latest.rejection_remarks)}”. Fix it and upload again — you can also correct the BOM.` : 'Fix it and upload again — you can also correct the BOM.'; action = 'Upload again'; }
            else if (appr === 'Pending') { tone = 'blue'; icon = 'fa-qrcode'; title = 'Waiting for the Superadmin’s QR approval'; body = 'Your latest upload and BOM were sent. You can upload again once it is approved or sent back.'; }
            else if (appr === 'Approved' && latest.status !== 'Completed' && (ewoodOv.boms || []).some(b => !b.approved_at && b.editable)) {
                // Inaprubahan ang upload pero may BOM na ibinalik ng Superadmin
                const back = ewoodOv.boms.filter(b => !b.approved_at && b.editable).map(b => b.bom_number);
                tone = 'red'; icon = 'fa-rotate-left'; title = `${back.length} BOM${back.length === 1 ? ' was' : 's were'} sent back`;
                body = `The upload was approved, but <b class="font-mono">${ewoodEscapeHtml(back.join(', '))}</b> needs fixing. Upload again — the BOM${back.length === 1 ? '' : 's'} will open so you can correct ${back.length === 1 ? 'it' : 'them'}.`;
                action = 'Fix BOM & upload';
            }
            else if (appr === 'Approved' && latest.status === 'Completed') { tone = 'green'; icon = 'fa-circle-check'; title = 'Completed and approved'; body = 'Nothing else to upload for this job.'; }
            else { tone = 'amber'; icon = 'fa-cloud-arrow-up'; title = latest ? 'Ready for the next upload' : 'Ready to upload'; body = 'Choose the progress files, then fill in the Bill of Materials. Both are sent to the Superadmin together.'; action = 'Upload progress'; }
            const TONE = {
                gray: 'bg-white border-gray-200 text-gray-700', amber: 'bg-amber-50 border-amber-200 text-amber-900',
                red: 'bg-red-50 border-red-200 text-red-800', blue: 'bg-sky-50 border-sky-200 text-sky-900', green: 'bg-green-50 border-green-200 text-green-800',
            };
            const notice = document.getElementById('ewoodDetailNotice');
            notice.className = `rounded-xl border px-4 py-3.5 flex flex-wrap items-center gap-3 ${TONE[tone]}`;
            notice.innerHTML = `<span class="w-9 h-9 rounded-lg bg-white/70 border border-current/10 flex items-center justify-center shrink-0"><i class="fa-solid ${icon}"></i></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-semibold">${title}</p>${body ? `<p class="text-xs opacity-80 mt-0.5 break-words">${body}</p>` : ''}</div>
                ${action && canUpload ? `<button type="button" onclick="ewoodSwitchTab('upload')" class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-cloud-arrow-up"></i> ${action}</button>` : ''}`;
            document.getElementById('ewoodPrimaryBtn').classList.toggle('hidden', !(action && canUpload));

            // latest submission
            const card = document.getElementById('ewoodLatestCard');
            if (!ewoodOv.loaded) card.innerHTML = '<p class="text-xs text-gray-400 py-4 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>';
            else if (!latest) card.innerHTML = '<div class="py-6 text-center"><i class="fa-regular fa-folder-open text-2xl text-gray-300"></i><p class="text-xs text-gray-400 mt-2">Nothing uploaded yet for this job.</p></div>';
            else {
                const photos = latest.photos || [];
                card.innerHTML = `
                    <div class="flex flex-wrap items-center gap-2">
                        ${ewoodApprovalBadge(latest.approval_status)}
                        <span class="text-[11px] text-gray-500">Job status: <b class="text-gray-700">${ewoodEscapeHtml(latest.status || '—')}</b></span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">${ewoodFormatDate(latest.created_at)} · by ${ewoodEscapeHtml(latest.uploaded_by_name || '—')}</p>
                    ${latest.remarks ? `<p class="text-xs text-gray-600 mt-2 break-words">${ewoodEscapeHtml(latest.remarks)}</p>` : ''}
                    ${latest.approval_status === 'Rejected' && latest.rejection_remarks ? `<p class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-2 break-words"><i class="fa-solid fa-rotate-left mr-1"></i>${ewoodEscapeHtml(latest.rejection_remarks)}</p>` : ''}
                    ${photos.length || latest.archive_url ? `<div class="flex flex-wrap items-center gap-2 mt-3">
                        ${photos.slice(0, 5).map(p => `<a href="${p}" target="_blank" rel="noopener"><img src="${p}" alt="" class="w-14 h-14 object-cover rounded-lg border border-gray-200 hover:border-amber-500"></a>`).join('')}
                        ${photos.length > 5 ? `<span class="text-[11px] text-gray-400">+${photos.length - 5} more</span>` : ''}
                        ${latest.archive_url ? `<a href="${latest.archive_url}" target="_blank" class="inline-flex items-center gap-1.5 h-8 px-2.5 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i class="fa-solid fa-file-zipper"></i> Archive</a>` : ''}
                    </div>` : ''}`;
            }

            // BOMs
            const boms = ewoodOv.boms || [];
            document.getElementById('ewoodBomCount').textContent = boms.length ? `${boms.length} total` : '';
            document.getElementById('ewoodBomOverview').innerHTML = boms.length
                ? boms.map(b => `<button type="button" data-bom-id="${b.id}" class="ewood-history-bom-btn inline-flex items-center gap-2 px-3 py-2 rounded-lg border ${b.approved_at ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800'} hover:shadow-sm text-left">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span><span class="block font-mono text-[11px] font-semibold">${ewoodEscapeHtml(b.bom_number)}</span>
                        <span class="block text-[10px] opacity-75">${b.approved_at ? 'Approved' : 'Waiting for approval'} · ${ewoodFormatDate(b.created_at)}</span></span></button>`).join('')
                : '<p class="text-xs text-gray-400">No Bill of Materials yet. You create it in the Upload &amp; BOM tab.</p>';
        }

        // BOM buttons sa overview → buksan ang BOM view
        document.getElementById('ewoodBomOverview').addEventListener('click', ev => {
            const b = ev.target.closest('[data-bom-id]');
            if (b) ewoodBomOpenViewModal(b.dataset.bomId);
        });

        function ewoodApplyPendingGate(quotationId, entries) {
            // Ignore stale responses if the user already switched rows.
            if (Number(ewoodActiveQuotationId) !== Number(quotationId)) return;

            const latest = entries[0];
            if (!latest) return;

            const isPending = latest.approval_status === 'Pending';
            const isDone = latest.approval_status === 'Approved' && latest.status === 'Completed';

            if (!isPending && !isDone) return; // leave whatever the NTP/verified gate already decided

            if (isPending && ewoodCurrentProgressionId !== null && Number(latest.id) === Number(ewoodCurrentProgressionId)) {
                return;
            }

            document.getElementById('ewoodUploadFields').classList.add('hidden');
            const blocked = document.getElementById('ewoodUploadBlocked');
            blocked.textContent = isDone
                ? 'This job has already been completed and approved. No further archive or photo upload is needed.'
                : 'You still have a submission awaiting the Superadmin\'s QR approval. Please wait for it to be approved or rejected before submitting again.';

            blocked.classList.remove(
                'text-gray-500', 'bg-gray-50', 'border-gray-200',
                'text-green-700', 'bg-green-50', 'border-green-200',
                'text-amber-700', 'bg-amber-50', 'border-amber-200'
            );
            if (isDone) {
                blocked.classList.add('text-green-700', 'bg-green-50', 'border-green-200');
            } else {
                blocked.classList.add('text-amber-700', 'bg-amber-50', 'border-amber-200');
            }

            blocked.classList.remove('hidden');
        }

        async function ewoodLoadHistory(quotationId) {
            try {
                // Hiwalay ang BOM: kapag pumalya ang bomajax, lalabas pa rin ang History
                const bomPromise = fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`)
                    .then(ewoodJson)
                    .catch(e => { console.error('bomajax list:', e); return { success: false, message: e.message }; });
                const historyRes = await fetch(`${CUT_PROG_AJAX_URL}?action=history&quotation_id=${quotationId}`);
                const data = await ewoodJson(historyRes);
                const bomData = await bomPromise;
                if (!data.success) {
                    document.getElementById('ewoodHistory').innerHTML = `<p class="text-red-600 text-center py-8"><i class="fa-solid fa-circle-exclamation mr-1"></i>${ewoodEscapeHtml(data.message || 'Could not load the history.')}</p>`;
                    return;
                }

                // Index BOMs by their linked progression_id so each history card
                // can show the BOM(s) created alongside that specific submission.
                const bomsByProgression = {};
                (bomData.success ? bomData.entries : []).forEach(b => {
                    if (b.progression_id) {
                        (bomsByProgression[b.progression_id] ||= []).push(b);
                    }
                });

                ewoodRenderHistory(data.entries, bomsByProgression);

                const countEl = document.getElementById('ewoodHistoryCount');
                if (data.entries.length) {
                    countEl.textContent = data.entries.length;
                    countEl.classList.remove('hidden');
                    countEl.classList.add('inline-flex');
                } else {
                    countEl.classList.add('hidden');
                    countEl.classList.remove('inline-flex');
                }

                const latestEl = document.getElementById('ewoodDetailLatest');
                if (latestEl) {
                    latestEl.textContent = data.entries.length
                        ? `${data.entries[0].status} · ${ewoodFormatDate(data.entries[0].created_at)}`
                        : '—';
                }
                if (Number(ewoodActiveQuotationId) === Number(quotationId)) {
                    ewoodOv.latest = data.entries[0] || null;
                    ewoodOv.loaded = true;
                    ewoodRenderOverview();
                }
                ewoodApplyPendingGate(quotationId, data.entries);
            } catch (e) {
                console.error(e);
                document.getElementById('ewoodHistory').innerHTML = `<p class="text-red-600 text-center py-8"><i class="fa-solid fa-circle-exclamation mr-1"></i>${ewoodEscapeHtml(e.message || 'Connection error while loading the history.')}</p>`;
            }
        }

        // ---------------------------------------------------------------
        // BOM (Step 2 of the Upload & BOM form)
        // ---------------------------------------------------------------

        function ewoodBomRenderList(entries) {
            ewoodOv.boms = entries;
            ewoodRenderOverview();
            const box = document.getElementById('ewoodBomList');
            if (!entries.length) {
                box.innerHTML = `<p class="text-gray-400 text-center py-3">No Bill of Materials yet for this job.</p>`;
                return;
            }
            box.innerHTML = entries.map(e => `
                <div class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2.5 hover:bg-gray-50 transition-colors">
                    <div class="min-w-0">
                        <p class="font-mono font-semibold text-amber-700 text-[11px] truncate">${ewoodEscapeHtml(e.bom_number)}</p>
                        <p class="text-gray-400 text-[10px]">${ewoodFormatDate(e.created_at)}</p>
                    </div>
                    <button type="button" data-bom-id="${e.id}" data-bom-number="${ewoodEscapeHtml(e.bom_number)}"
                        class="ewood-bom-pdf-btn shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-eye"></i> View
                    </button>
                </div>
            `).join('');
        }

        document.getElementById('ewoodBomList').addEventListener('click', function (ev) {
            const btn = ev.target.closest('.ewood-bom-pdf-btn');
            if (!btn) return;
            ewoodBomOpenViewModal(btn.dataset.bomId);
        });

        function ewoodBomListError(message) {
            document.getElementById('ewoodBomList').innerHTML =
                `<p class="text-red-600 text-center py-3 break-words"><i class="fa-solid fa-circle-exclamation mr-1"></i>${ewoodEscapeHtml(message)}</p>`;
        }

        async function ewoodBomLoadList(quotationId) {
            document.getElementById('ewoodBomList').innerHTML = `<p class="text-gray-400 text-center py-3"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`);
                const data = await ewoodJson(res);
                // huwag i-render kung lumipat na sa ibang row
                if (Number(ewoodActiveQuotationId) !== Number(quotationId)) return;
                if (!data.success) { ewoodBomListError(data.message || 'Could not load the BOM list.'); return; }
                ewoodBomRenderList(data.entries || []);
            } catch (e) {
                console.error(e);
                if (Number(ewoodActiveQuotationId) === Number(quotationId)) ewoodBomListError(e.message || 'Connection error while loading the BOM list.');
            }
        }

        async function ewoodBomHasUnapproved(quotationId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`);
                const data = await ewoodJson(res);
                if (!data.success) return false;
                return data.entries.some(e => !e.approved_at);
            } catch (e) {
                console.error(e);
                return false;
            }
        }

        async function ewoodBomRelink(quotationId, progressionId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=relink`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ quotation_id: quotationId, progression_id: progressionId, confirm_bom: ewoodBomConfirmed ? '1' : '0' })
                });
                const data = await ewoodJson(res);
                if (!data.success) console.warn('relink:', data.message);
                return !!data.success;
            } catch (e) {
                console.error(e);
                return false;
            }
        }

        // ---------------------------------------------------------------
        // Supplier → Product. Pipili ng supplier, saka pipili ng produkto nito.
        // "Outsource" = wala pang supplier, kaya manual ang pag-type ng item.
        // ---------------------------------------------------------------
        const EWOOD_OUTSOURCE = '__out';
        let ewoodCatalog = [];          // [{id, name, items:[{id,item_code,description,unit}]}]
        let ewoodCatalogLoaded = false;
        let ewoodPickRow = null;

        function ewoodCatalogNote(html, tone) {
            const el = document.getElementById('ewoodCatalogNote');
            el.innerHTML = html || '';
            el.className = html ? `mb-2 text-[11px] rounded-lg px-3 py-2 border ${tone === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-amber-50 border-amber-200 text-amber-800'}` : 'hidden';
        }

        async function ewoodLoadCatalog() {
            try {
                const res = await fetch(`${SUP_AJAX_URL}?action=catalog`, { cache: 'no-store' });
                const data = await ewoodJson(res);
                if (!data.success) throw new Error(data.message || 'Could not load the supplier list.');
                ewoodCatalog = data.suppliers || [];
                ewoodCatalogLoaded = true;
                ewoodCatalogNote(ewoodCatalog.length ? '' : '<i class="fa-solid fa-circle-info mr-1"></i>No suppliers yet. Add them in Suppliers &amp; Products, or use Outsource for now.');
            } catch (e) {
                console.error(e);
                ewoodCatalog = [];
                ewoodCatalogLoaded = false;
                ewoodCatalogNote(`<i class="fa-solid fa-circle-exclamation mr-1"></i>${ewoodEscapeHtml(e.message)}`, 'error');
            }
            // i-refresh ang mga dropdown pero panatilihin ang napili
            document.querySelectorAll('#ewoodBomCards tbody tr').forEach(tr => {
                const sel = tr.querySelector('.bom-sup');
                const cur = sel.value;
                sel.innerHTML = ewoodSupplierOptions(cur);
                if (sel.value !== cur) ewoodApplySupplier(tr, false);
            });
        }
        document.getElementById('ewoodCatalogReload').addEventListener('click', () => ewoodLoadCatalog());

        const ewoodSupplierById = id => ewoodCatalog.find(s => String(s.id) === String(id)) || null;

        function ewoodSupplierOptions(selected = '') {
            return `<option value="">— Choose supplier —</option>`
                + ewoodCatalog.map(s => `<option value="${s.id}" ${String(s.id) === String(selected) ? 'selected' : ''}>${ewoodEscapeHtml(s.name)}${s.items.length ? '' : ' (no products)'}</option>`).join('')
                + `<option value="${EWOOD_OUTSOURCE}" ${selected === EWOOD_OUTSOURCE ? 'selected' : ''}>Outsource</option>`;
        }

        // Ayusin ang itsura ng row ayon sa napiling supplier
        function ewoodApplySupplier(tr, clear = true) {
            const val = tr.querySelector('.bom-sup').value;
            const code = tr.querySelector('[name="item_code[]"]');
            const desc = tr.querySelector('[name="item_description[]"]');
            const unit = tr.querySelector('[name="unit[]"]');
            const supName = tr.querySelector('[name="supplier[]"]');
            const pickBtn = tr.querySelector('.bom-pick');
            if (clear) { code.value = ''; desc.value = ''; unit.value = ''; }
            const isOut = val === EWOOD_OUTSOURCE, sup = ewoodSupplierById(val);
            supName.value = isOut ? 'OUTSOURCE' : (sup ? sup.name : '');
            [code, desc, unit].forEach(el => {
                el.readOnly = !isOut;
                el.classList.toggle('bg-gray-50', !isOut);
                el.classList.toggle('cursor-pointer', !!sup);
            });
            code.placeholder = isOut ? 'Code (optional)' : '';
            desc.placeholder = isOut ? 'Type the item to source' : (sup ? 'Click to choose a product' : 'Choose a supplier first');
            unit.placeholder = isOut ? 'Unit' : '';
            pickBtn.classList.toggle('hidden', !sup);
            tr.classList.toggle('bg-amber-50/40', isOut);
            ewoodBomItemsError('', tr);
        }

        // ═════════════ MULTI-BOM ═════════════
        // Isang submission = isang progress upload + isa o higit pang BOM (kahit ilan, max 20).
        // Bawat BOM ay sariling card: Date Needed, General Scope at sariling items.
        const EWOOD_BOM_MAX = 20;
        let ewoodBomCardSeq = 0;
        let ewoodBomRemovedIds = [];   // naibalik na BOM na tinanggal sa submission na ito (buburahin pag-submit)

        const ewoodBomCards = () => [...document.querySelectorAll('#ewoodBomCards .ewood-bom-card')];

        function ewoodBomFilledRows(card) {
            return [...card.querySelectorAll('tbody tr')].filter(tr =>
                tr.querySelector('[name="item_description[]"]').value.trim() || tr.querySelector('[name="item_code[]"]').value.trim());
        }

        function ewoodBomCountItems(card) {
            if (!card) return;
            const n = ewoodBomFilledRows(card).length;
            card.querySelector('.ewood-bom-count').textContent = n ? `· ${n} item${n === 1 ? '' : 's'}` : '';
        }

        const EWOOD_BOM_REMOVE_BTN = `<button type="button" data-bom-act="remove" title="Remove this BOM"
            class="inline-flex items-center gap-1 h-7 px-2 text-[11px] font-semibold text-gray-400 rounded-md hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-trash-can text-[10px]"></i><span class="hidden sm:inline">Remove</span></button>`;

        function ewoodBomRenumber() {
            const cards = ewoodBomCards();
            cards.forEach((card, i) => {
                const old = Number(card.dataset.bomId) > 0;
                card.querySelector('.ewood-bom-badge').textContent = i + 1;
                card.querySelector('.ewood-bom-title').innerHTML = old
                    ? `<span class="font-mono">${ewoodEscapeHtml(card.dataset.bomNumber)}</span> <span class="font-semibold text-red-600">· sent back</span>`
                    : (cards.length > 1 ? `New BOM ${i + 1}` : 'New BOM');
                const slot = card.querySelector('.ewood-bom-remove-slot');
                if (!slot.querySelector('[data-bom-act="remove-yes"]')) slot.innerHTML = cards.length > 1 ? EWOOD_BOM_REMOVE_BTN : '';
                ewoodBomCountItems(card);
            });
            const add = document.getElementById('ewoodBomAddCardBtn');
            if (add) {
                const full = cards.length >= EWOOD_BOM_MAX;
                add.disabled = full;
                add.classList.toggle('opacity-50', full);
                add.innerHTML = full ? `Maximum of ${EWOOD_BOM_MAX} BOMs per submission` : '<i class="fa-solid fa-plus"></i> Add another BOM';
            }
        }

        // Gumawa ng BOM card. bom = naibalik na BOM (para ayusin) o null para sa bago
        function ewoodBomAddCard(bom = null) {
            const card = document.createElement('div');
            card.className = 'ewood-bom-card rounded-xl border border-gray-200 bg-white overflow-hidden transition-colors';
            card.dataset.cardId = String(++ewoodBomCardSeq);
            card.dataset.bomId = bom ? String(Number(bom.id) || 0) : '0';
            card.dataset.bomNumber = bom ? String(bom.bom_number || '') : '';
            const fieldCls = 'w-full text-xs border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-600';
            card.innerHTML = `
                <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-gray-200 min-w-0">
                    <span class="ewood-bom-badge w-5 h-5 rounded-md bg-amber-100 text-amber-800 text-[10px] font-bold flex items-center justify-center shrink-0"></span>
                    <i class="fa-solid fa-file-invoice text-amber-700 text-xs shrink-0"></i>
                    <p class="ewood-bom-title text-xs font-semibold text-gray-800 truncate min-w-0"></p>
                    <span class="ewood-bom-count text-[10.5px] text-gray-400 shrink-0"></span>
                    <div class="ewood-bom-remove-slot ml-auto shrink-0"></div>
                </div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">Date Needed</label>
                            <input type="text" name="date_needed" maxlength="50" class="${fieldCls}">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1.5">General Scope</label>
                            <input type="text" name="general_scope" maxlength="255" class="${fieldCls}">
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label class="block text-[11px] font-semibold text-gray-600">Items <span class="font-normal text-gray-400">— choose the supplier first, then its product</span></label>
                            <button type="button" data-bom-act="add-row" class="shrink-0 text-[11px] font-semibold text-amber-700 hover:text-amber-800"><i class="fa-solid fa-plus"></i> Add row</button>
                        </div>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-[11px] min-w-[540px] table-fixed">
                                <colgroup><col style="width:23%"><col style="width:15%"><col><col style="width:10%"><col style="width:10%"><col style="width:32px"></colgroup>
                                <thead class="bg-gray-50 text-gray-500">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-semibold">Supplier</th>
                                        <th class="px-2 py-2 text-left font-semibold">Item Code</th>
                                        <th class="px-2 py-2 text-left font-semibold">Description</th>
                                        <th class="px-2 py-2 text-left font-semibold">Qty</th>
                                        <th class="px-2 py-2 text-left font-semibold">Unit</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="ewood-bom-items divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                        <p class="ewood-bom-err hidden mt-1.5 text-[11px] text-red-600"></p>
                    </div>
                </div>`;
            // .value para ligtas sa quotes at special characters
            if (bom?.return_remarks) {
                const note = document.createElement('p');
                note.className = 'px-4 py-2 text-[11.5px] text-red-800 bg-red-50 border-b border-red-200 break-words';
                note.innerHTML = '<i class="fa-solid fa-rotate-left mr-1"></i><b>Superadmin:</b> ';
                note.append(document.createTextNode(`“${bom.return_remarks}”`));
                card.firstElementChild.after(note);
            }
            card.querySelector('[name="date_needed"]').value = bom?.date_needed || 'ASAP';
            card.querySelector('[name="general_scope"]').value = bom?.general_scope || 'GENERAL SCOPE';
            document.getElementById('ewoodBomCards').appendChild(card);
            ewoodBomRenumber();
            return card;
        }

        function ewoodBomItemsError(msg, tr = null, card = null) {
            card = card || tr?.closest('.ewood-bom-card') || null;
            (card ? [card] : ewoodBomCards()).forEach(c => {
                const el = c.querySelector('.ewood-bom-err');
                el.textContent = '';
                el.classList.add('hidden');
                c.classList.remove('border-red-300');
                c.querySelectorAll('tbody tr').forEach(r => r.classList.remove('ring-1', 'ring-red-300'));
            });
            if (!msg || !card) return;
            const el = card.querySelector('.ewood-bom-err');
            el.textContent = msg;
            el.classList.remove('hidden');
            card.classList.add('border-red-300');
            if (tr) tr.classList.add('ring-1', 'ring-red-300');
        }

        // Magdagdag ng item row sa isang BOM (default: sa huling BOM)
        function ewoodBomAddRow(values = {}, tbody = null) {
            if (!tbody) {
                const cards = ewoodBomCards();
                tbody = (cards[cards.length - 1] || ewoodBomAddCard()).querySelector('.ewood-bom-items');
            }
            const idx = ewoodBomRowIndex++;
            // bagong row: kunin ang supplier ng huling row ng parehong BOM para mabilis magdagdag
            const chosen = [...tbody.querySelectorAll('.bom-sup')].filter(s => s.value);
            const supVal = values.supplier_value ?? (chosen.length ? chosen[chosen.length - 1].value : '');
            const tr = document.createElement('tr');
            tr.dataset.rowId = idx;
            const inputCls = 'w-full text-[11px] border border-gray-200 rounded px-1.5 py-1 outline-none focus:border-amber-600';
            tr.innerHTML = `
                <td class="px-2 py-1"><select class="bom-sup ${inputCls} bg-white">${ewoodSupplierOptions(supVal)}</select>
                    <input type="hidden" name="supplier[]" value=""></td>
                <td class="px-2 py-1"><input type="text" name="item_code[]" maxlength="50" class="${inputCls} font-mono"></td>
                <td class="px-2 py-1"><div class="relative"><input type="text" name="item_description[]" maxlength="255" class="${inputCls} pr-7">
                    <button type="button" class="bom-pick hidden absolute right-1 top-1/2 -translate-y-1/2 w-5 h-5 inline-flex items-center justify-center rounded text-amber-700 hover:bg-amber-100" title="Choose product"><i class="fa-solid fa-list text-[10px]"></i></button></div></td>
                <td class="px-2 py-1"><input type="number" step="0.01" min="0" name="quantity[]" class="${inputCls}"></td>
                <td class="px-2 py-1"><input type="text" name="unit[]" maxlength="30" class="${inputCls}"></td>
                <td class="px-2 py-1 text-center"><button type="button" class="bom-del inline-flex items-center justify-center w-6 h-6 rounded text-gray-300 hover:text-red-500 hover:bg-red-50" title="Remove row"><i class="fa-solid fa-xmark"></i></button></td>
            `;
            tr.querySelector('[name="quantity[]"]').value = values.quantity ?? '';
            tbody.appendChild(tr);
            ewoodApplySupplier(tr, true);
            return tr;
        }

        function ewoodBomNewCard({ scroll = false } = {}) {
            if (ewoodBomCards().length >= EWOOD_BOM_MAX) return null;
            const card = ewoodBomAddCard();
            const tbody = card.querySelector('.ewood-bom-items');
            for (let i = 0; i < 3; i++) ewoodBomAddRow({}, tbody);
            if (scroll) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(() => card.querySelector('[name="general_scope"]').focus({ preventScroll: true }), 300);
            }
            return card;
        }
        document.getElementById('ewoodBomAddCardBtn').addEventListener('click', () => ewoodBomNewCard({ scroll: true }));

        // Naka-lock pagkatapos maipadala (hindi na mababago)
        function ewoodBomLock(on) {
            const box = document.getElementById('ewoodBomCards');
            box.classList.toggle('pointer-events-none', on);
            box.classList.toggle('opacity-70', on);
            box.querySelectorAll('input, select').forEach(el => { el.disabled = on; });
            document.getElementById('ewoodBomAddCardBtn').classList.toggle('hidden', on);
        }

        // Mga event ng lahat ng BOM card (isang listener lang)
        (function () {
            const box = document.getElementById('ewoodBomCards');
            box.addEventListener('change', e => {
                const sel = e.target.closest('.bom-sup'); if (!sel) return;
                const tr = sel.closest('tr');
                ewoodApplySupplier(tr, true);
                ewoodBomCountItems(tr.closest('.ewood-bom-card'));
                if (ewoodSupplierById(sel.value)) ewoodOpenPicker(tr);
                else if (sel.value === EWOOD_OUTSOURCE) tr.querySelector('[name="item_description[]"]').focus();
            });
            box.addEventListener('click', e => {
                const card = e.target.closest('.ewood-bom-card'); if (!card) return;
                const act = e.target.closest('[data-bom-act]')?.dataset.bomAct;
                const slot = card.querySelector('.ewood-bom-remove-slot');

                if (act === 'add-row') {
                    const tr = ewoodBomAddRow({}, card.querySelector('.ewood-bom-items'));
                    tr.querySelector('.bom-sup').focus();
                    return;
                }
                if (act === 'remove') {
                    const old = Number(card.dataset.bomId) > 0;
                    if (!old && !ewoodBomFilledRows(card).length) { card.remove(); ewoodBomRenumber(); return; }
                    // inline na kumpirmasyon (walang pop-up)
                    slot.innerHTML = `<span class="inline-flex items-center gap-1.5">
                        <span class="text-[11px] font-semibold text-red-700">${old ? 'Delete this BOM when you submit?' : 'Remove this BOM?'}</span>
                        <button type="button" data-bom-act="remove-no" class="h-7 px-2.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md hover:bg-gray-50">Keep</button>
                        <button type="button" data-bom-act="remove-yes" class="h-7 px-2.5 text-[11px] font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Remove</button></span>`;
                    return;
                }
                if (act === 'remove-no') { slot.innerHTML = ''; ewoodBomRenumber(); return; }
                if (act === 'remove-yes') {
                    const bid = Number(card.dataset.bomId) || 0;
                    if (bid > 0 && !ewoodBomRemovedIds.includes(bid)) ewoodBomRemovedIds.push(bid);
                    card.remove();
                    ewoodBomRenumber();
                    return;
                }

                const tr = e.target.closest('tbody tr'); if (!tr) return;
                const tbody = tr.parentElement;
                if (e.target.closest('.bom-del')) {
                    if (tbody.rows.length > 1) tr.remove();
                    else { tr.querySelector('.bom-sup').value = ''; tr.querySelector('[name="quantity[]"]').value = ''; ewoodApplySupplier(tr, true); }
                    ewoodBomItemsError('', null, card);
                    ewoodBomCountItems(card);
                    return;
                }
                const onPickField = e.target.closest('.bom-pick') || (e.target.matches('[name="item_code[]"], [name="item_description[]"], [name="unit[]"]') && e.target.readOnly);
                if (!onPickField) return;
                if (ewoodSupplierById(tr.querySelector('.bom-sup').value)) ewoodOpenPicker(tr);
                else { ewoodBomItemsError('Choose a supplier first (or Outsource).', tr); tr.querySelector('.bom-sup').focus(); }
            });
            box.addEventListener('input', e => {
                const card = e.target.closest('.ewood-bom-card'); if (!card) return;
                ewoodBomItemsError('', null, card);
                ewoodBomCountItems(card);
            });
        })();

        // ── Product picker ──
        function ewoodOpenPicker(tr) {
            const sup = ewoodSupplierById(tr.querySelector('.bom-sup').value);
            if (!sup) return;
            ewoodPickRow = tr;
            document.getElementById('ewoodPickTitle').textContent = sup.name;
            document.getElementById('ewoodPickSearch').value = '';
            ewoodRenderPicker();
            const m = document.getElementById('ewoodPickModal');
            m.classList.remove('hidden'); m.classList.add('flex');
            setTimeout(() => document.getElementById('ewoodPickSearch').focus(), 50);
        }
        function ewoodClosePicker() {
            const m = document.getElementById('ewoodPickModal');
            m.classList.add('hidden'); m.classList.remove('flex');
            ewoodPickRow = null;
        }
        function ewoodRenderPicker() {
            const box = document.getElementById('ewoodPickList');
            const sup = ewoodPickRow ? ewoodSupplierById(ewoodPickRow.querySelector('.bom-sup').value) : null;
            if (!sup) { box.innerHTML = ''; return; }
            const q = document.getElementById('ewoodPickSearch').value.trim().toLowerCase();
            const cur = ewoodPickRow.querySelector('[name="item_code[]"]').value;
            const list = sup.items.filter(i => !q || `${i.item_code} ${i.description} ${i.unit}`.toLowerCase().includes(q));
            if (!sup.items.length) {
                box.innerHTML = `<div class="px-4 py-10 text-center text-xs text-gray-500">This supplier has no products yet.<br>
                    <a href="<?= htmlspecialchars($supPageUrl) ?>" target="_blank" class="inline-block mt-2 font-semibold text-amber-700 hover:underline">Add products in Suppliers &amp; Products</a></div>`;
                return;
            }
            box.innerHTML = list.length ? list.map(i => `
                <button type="button" data-pick="${i.id}" class="w-full text-left flex items-center gap-3 px-4 py-2.5 hover:bg-amber-50 ${i.item_code === cur ? 'bg-amber-50' : ''}">
                    <span class="font-mono text-[11px] font-semibold text-amber-800 w-28 shrink-0 break-all">${ewoodEscapeHtml(i.item_code)}</span>
                    <span class="flex-1 min-w-0 text-xs text-gray-800 break-words">${ewoodEscapeHtml(i.description)}</span>
                    <span class="text-[10.5px] text-gray-400 shrink-0">${ewoodEscapeHtml(i.unit || '')}</span>
                    ${i.item_code === cur ? '<i class="fa-solid fa-check text-amber-700 text-xs"></i>' : ''}
                </button>`).join('')
                : '<p class="px-4 py-10 text-center text-xs text-gray-400">No product matches.</p>';
        }
        document.getElementById('ewoodPickSearch').addEventListener('input', ewoodRenderPicker);
        document.getElementById('ewoodPickSearch').addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); document.querySelector('#ewoodPickList [data-pick]')?.click(); }
        });
        document.getElementById('ewoodPickClose').addEventListener('click', ewoodClosePicker);
        document.getElementById('ewoodPickModal').addEventListener('click', e => { if (e.target.id === 'ewoodPickModal') ewoodClosePicker(); });
        document.getElementById('ewoodPickList').addEventListener('click', e => {
            const b = e.target.closest('[data-pick]'); if (!b || !ewoodPickRow) return;
            const sup = ewoodSupplierById(ewoodPickRow.querySelector('.bom-sup').value);
            const it = sup && sup.items.find(x => String(x.id) === b.dataset.pick);
            if (!it) return;
            const tr = ewoodPickRow;
            tr.querySelector('[name="item_code[]"]').value = it.item_code;
            tr.querySelector('[name="item_description[]"]').value = it.description;
            tr.querySelector('[name="unit[]"]').value = it.unit || '';
            ewoodClosePicker();
            ewoodBomItemsError('', tr);
            ewoodBomCountItems(tr.closest('.ewood-bom-card'));
            tr.querySelector('[name="quantity[]"]').focus();
        });

        // Suriin ang lahat ng BOM bago i-submit → [error, row, card] ('' kapag ayos)
        function ewoodBomValidateItems() {
            const cards = ewoodBomCards();
            if (!cards.length) return ['Add at least one Bill of Materials.', null, null];
            for (let c = 0; c < cards.length; c++) {
                const card = cards[c];
                const label = cards.length > 1 ? `BOM ${c + 1}, row` : 'Row';
                const rows = [...card.querySelectorAll('tbody tr')];
                let filled = 0;
                for (let i = 0; i < rows.length; i++) {
                    const tr = rows[i], n = i + 1;
                    const sup = tr.querySelector('.bom-sup').value;
                    const desc = tr.querySelector('[name="item_description[]"]').value.trim();
                    const qty = tr.querySelector('[name="quantity[]"]').value.trim();
                    if (!sup && !desc && !qty) continue;   // blankong row, laktawan
                    if (!sup) return [`${label} ${n}: choose a supplier (or Outsource).`, tr, card];
                    if (!desc) return [sup === EWOOD_OUTSOURCE ? `${label} ${n}: type the item to source.` : `${label} ${n}: choose a product from ${ewoodSupplierById(sup)?.name || 'the supplier'}.`, tr, card];
                    if (!(Number(qty) > 0)) return [`${label} ${n}: enter a quantity greater than 0.`, tr, card];
                    filled++;
                }
                if (!filled) return [cards.length > 1 ? `BOM ${c + 1} has no items. Add an item, or remove this BOM.` : 'Add at least one BOM item before submitting.', rows[0] || null, card];
            }
            return ['', null, null];
        }

        // Lahat ng BOM na ipapadala (para sa bomajax?action=create_multi)
        function ewoodBomCollect() {
            return ewoodBomCards().map(card => ({
                bom_id: Number(card.dataset.bomId) || 0,
                date_needed: card.querySelector('[name="date_needed"]').value.trim(),
                general_scope: card.querySelector('[name="general_scope"]').value.trim(),
                items: ewoodCfRowsFromForm(card),
            }));
        }

        // Isang bagong BOM na may ilang blankong row — tuwing magbubukas ng job, at pagkatapos mag-submit.
        function ewoodBomResetRows() {
            document.getElementById('ewoodBomCards').innerHTML = '';
            ewoodBomRowIndex = 0;
            ewoodBomRemovedIds = [];
            ewoodBomNewCard();
        }

        // Fetches one BOM's full data (header + items) and renders it inside
        // the modal, mirroring the printed BOM layout — no PDF/iframe involved.
        async function ewoodBomOpenViewModal(bomId) {
            const modal = document.getElementById('ewoodBomViewModal');
            const body = document.getElementById('ewoodBomViewBody');
            body.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
            document.getElementById('ewoodBomViewDownload').href = `${BOM_AJAX_URL}?action=pdf&bom_id=${bomId}`;
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=view&bom_id=${bomId}`);
                const data = await ewoodJson(res);
                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(data.message || 'Failed to load BOM.')}</p>`;
                    return;
                }
                ewoodBomRenderViewBody(data.bom);
            } catch (e) {
                console.error(e);
                body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(e.message || 'Connection error while loading.')}</p>`;
            }
        }

        function ewoodBomCloseViewModal() {
            const modal = document.getElementById('ewoodBomViewModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function ewoodBomFmtDate(value) {
            if (!value) return '—';
            const dt = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            // Match the PDF's date('d/m/Y') format exactly (day first), not
            // locale-dependent — en-PH's short date is month-first.
            const dd = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}/${dt.getFullYear()}`;
        }

        // Strips trailing zeros the same way the PDF's number_format/rtrim does,
        // so "6.00" -> "6" and "6.50" -> "6.5".
        function ewoodBomFmtQty(q) {
            const n = parseFloat(q);
            if (isNaN(n)) return '';
            return n.toFixed(2).replace(/\.?0+$/, '');
        }

        function ewoodBomRenderViewBody(bom) {
            document.getElementById('ewoodBomViewModalTitle').textContent = bom.bom_number || 'BOM';

            const items = bom.items || [];
            const minRows = 6;
            const cellCls = 'border border-gray-800 px-2 py-1.5 text-xs';

            let rows = items.map((it, i) => `
                <tr>
                    <td class="${cellCls} text-center">${i + 1}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_code)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_description)}</td>
                    <td class="${cellCls} text-center">${ewoodBomFmtQty(it.quantity)}</td>
                    <td class="${cellCls} text-center">${ewoodEscapeHtml(it.unit)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.supplier)}</td>
                </tr>`).join('');
            for (let i = items.length; i < minRows; i++) {
                rows += `<tr>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                </tr>`;
            }

            const sigCell = (name, title, sigPath) => `
    <td class="relative border border-gray-800 text-center align-bottom px-2 pb-1.5 w-1/4 h-24">
        ${sigPath ? `<img src="${sigPath}" alt="" class="pointer-events-none select-none absolute z-20 left-1/2 -translate-x-1/2 bottom-1 h-20 w-auto max-w-[95%] object-contain">` : ''}
        <p class="text-xs font-bold">${name ? ewoodEscapeHtml(name) : '&nbsp;'}</p>
        <p class="text-[9px] italic text-gray-600">${title ? ewoodEscapeHtml(title) : '&nbsp;'}</p>
    </td>`;

            const requestedSigUrl = bom.requested_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.requested_by_signature_path)}` : null;
            const approvedSigUrl = bom.approved_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.approved_by_signature_path)}` : null;
            const receivedSigUrl = bom.received_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.received_by_signature_path)}` : null;

            document.getElementById('ewoodBomViewBody').innerHTML = `
                <table class="w-full border-collapse border-l-2 border-r-2 border-t-2 border-gray-800">
                    <tr>
                        <td class="w-[8%]"></td>
                        <td class="text-center w-[62%]" style="font-family:'Arial Narrow',Arial,sans-serif;font-weight:900;font-stretch:condensed;letter-spacing:-2px;font-size:34px;line-height:1.1;">BILL OF MATERIALS</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-[8%] align-middle">Page</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Submitted</td>
                    </tr>
                    <tr>
                        <td></td><td></td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${bom.page ?? 1}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${ewoodBomFmtDate(bom.date_submitted)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[20%] align-middle">J.O Number:</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[58%] align-middle" colspan="2">Client</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Needed</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-800 text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.bom_number)}</td>
                        <td class="border border-gray-800 text-center text-sm font-bold px-2 py-1.5" colspan="2">${ewoodEscapeHtml(bom.client_name)}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.date_needed)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[10%] align-middle">Scope:</td>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1">${ewoodEscapeHtml(bom.general_scope)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[5%]">No.</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[15%]">Item Code</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[35%]">Item Description</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Quantity</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Unit</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[25%]">Supplier</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tr><td colspan="6" class="border border-gray-800 text-center text-red-600 font-bold italic text-xs py-2">** NOTHING FOLLOWS **</td></tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-b-2 border-gray-800">
                    <tr class="bg-gray-200">
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Requested By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Noted By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Received By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Approved By:</td>
                    </tr>
                    <tr>
                        ${sigCell(bom.requested_by, bom.requested_by_title, requestedSigUrl)}
                        ${sigCell(bom.noted_by, bom.noted_by_title, null)}
                        ${sigCell(bom.received_by, bom.received_by_title, receivedSigUrl)}
                        ${sigCell(bom.approved_by, bom.approved_by_title, approvedSigUrl)}
                    </tr>
                    <tr><td colspan="4" class="text-center text-[8px] text-gray-500 py-1.5">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
                </table>
            `;
        }

        document.getElementById('ewoodBomViewModal').addEventListener('click', function (ev) {
            if (ev.target === this) ewoodBomCloseViewModal();
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') return;
            if (!document.getElementById('ewoodConfirmModal').classList.contains('hidden')) ewoodCloseConfirm();
            else if (!document.getElementById('ewoodPickModal').classList.contains('hidden')) ewoodClosePicker();
            else ewoodBomCloseViewModal();
        });

        // ── Selection / tabs ─────────────────────────
        function ewoodSetRowHighlight(quotationId, active) {
            const row = document.getElementById(`ewoodRow_${quotationId}`);
            if (!row) return;
            row.classList.toggle('is-active', active);
        }

        // Switches the detail view between the Details / Upload & BOM / History tabs.
        function ewoodSwitchTab(tab) {
            const panels = {
                details: document.getElementById('ewoodPanelDetails'),
                upload: document.getElementById('ewoodUploadForm'),
                history: document.getElementById('ewoodPanelHistory'),
            };
            Object.entries(panels).forEach(([key, el]) => el.classList.toggle('hidden', key !== tab));

            document.querySelectorAll('.ewood-tab-btn').forEach(btn => {
                const active = btn.dataset.tab === tab;
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('text-amber-700', active);
                btn.classList.toggle('border-transparent', !active);
                btn.classList.toggle('text-gray-400', !active);
            });

            document.getElementById('ewoodDetailBody').scrollTop = 0;
        }

        document.getElementById('ewoodHistory').addEventListener('click', function (ev) {
            const btn = ev.target.closest('.ewood-history-bom-btn');
            if (!btn) return;
            ewoodBomOpenViewModal(btn.dataset.bomId);
        });

        // Mobile: go back from the detail view to the list.
        function ewoodBackToList() {
            document.getElementById('ewoodMain').dataset.view = 'list';
        }

        const EWOOD_SUBMIT_HTML = '<i class="fa-solid fa-paper-plane mr-1"></i> Submit progress &amp; BOM';
        const EWOOD_RELINK_HTML = '<i class="fa-solid fa-paper-plane mr-1"></i> Submit to Superadmin';
        let ewoodExistingBoms = [];   // mga hindi pa approved na BOM ng job (kung meron)
        let ewoodReviseBoms = [];     // [{id, bom_number}] — naibalik na BOM na inaayos (parehong BOM number)
        const EWOOD_REVISE_HTML = '<i class="fa-solid fa-paper-plane mr-1"></i> Submit progress &amp; updated BOM';
        let ewoodSubmitting = false;
        let ewoodBomConfirmed = false;      // true kapag dumaan na sa double confirmation
        let ewoodUploadQuotationId = null;   // job ng naipadalang progress (para sa discard)

        // Naipadala ang progress pero hindi natapos ang BOM → burahin sa server bago lumipat
        function ewoodDiscardOrphan() {
            if (ewoodCurrentProgressionId === null) return;
            const body = new URLSearchParams({ quotation_id: ewoodUploadQuotationId, progression_id: ewoodCurrentProgressionId });
            fetch(`${BOM_AJAX_URL}?action=discard_progress`, { method: 'POST', body, keepalive: true }).catch(() => { });
        }
        window.addEventListener('pagehide', ewoodDiscardOrphan);

        function ewoodResetUploadStepState() {
            ewoodDiscardOrphan();
            ewoodBomConfirmed = false;
            ewoodCurrentProgressionId = null;
            ewoodExistingBoms = [];
            ewoodReviseBoms = [];
            ewoodBomRemovedIds = [];
            ewoodSubmitting = false;
            ewoodBomReviseUi(null);
            ewoodBomLock(false);

            document.querySelectorAll('#ewoodUploadFields input[name="archive"], #ewoodUploadFields input[name="images[]"], #ewoodUploadFields textarea[name="remarks"]')
                .forEach(el => { el.disabled = false; el.value = ''; });

            ewoodStep1Lock(false);
            const saveBtn = document.getElementById('ewoodSaveProgressBtn');
            saveBtn.disabled = false;
            document.getElementById('ewoodStep1Err').classList.add('hidden');

            document.querySelectorAll('#ewoodBomSection input').forEach(el => { el.disabled = false; });
            const createBomBtn = document.getElementById('ewoodCreateBomBtn');
            createBomBtn.disabled = false;
            createBomBtn.innerHTML = EWOOD_SUBMIT_HTML;
            const relinkBtn = document.getElementById('ewoodSubmitRelinkBtn');
            relinkBtn.disabled = false;
            relinkBtn.innerHTML = EWOOD_RELINK_HTML;

            document.getElementById('ewoodBomSection').classList.add('hidden');
            document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
        }

        // I-lock ang Step 1 habang nasa Step 2 (hindi naka-disabled para kasama pa rin sa FormData)
        function ewoodStep1Lock(on) {
            const body = document.getElementById('ewoodStep1Body');
            body.classList.toggle('pointer-events-none', on);
            body.classList.toggle('opacity-60', on);
            document.getElementById('ewoodSaveProgressBtn').classList.toggle('hidden', on);
            document.getElementById('ewoodStep1Edit').classList.toggle('hidden', !on || ewoodCurrentProgressionId !== null);
        }

        // Opens the detail view for a given row.
        function ewoodOpenSidebar(quotationId) {
            const row = ewoodRowsMap[quotationId];
            if (!row) return;

            if (ewoodActiveQuotationId !== null && ewoodActiveQuotationId !== quotationId) {
                ewoodSetRowHighlight(ewoodActiveQuotationId, false);
            }
            ewoodActiveQuotationId = quotationId;
            ewoodSetRowHighlight(quotationId, true);

            const isNtp = row.deposit_status === 'Notice to Proceed';
            const isVerified = !!row.design_2d_verified;
            const canUpload = isNtp && isVerified;

            // Header
            document.getElementById('ewoodModalControl').textContent = row.control_no;
            document.getElementById('ewoodModalTitle').textContent = row.client_name;
            document.getElementById('ewoodHeaderChips').innerHTML = `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-50 text-gray-600 capitalize">
                    <i class="fa-solid fa-location-dot text-gray-400 text-[10px]"></i>${ewoodEscapeHtml(row.branch)}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-50 text-gray-600 capitalize">
                    <i class="fa-solid fa-house text-gray-400 text-[10px]"></i>${ewoodEscapeHtml(row.project_type ?? '—')}
                </span>
                ${canUpload ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-green-200 bg-green-50 text-green-700"><i class="fa-solid fa-lock-open text-[9px]"></i>Uploads open</span>'
                    : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-100 text-gray-500"><i class="fa-solid fa-lock text-[9px]"></i>Uploads locked</span>'}`;
            document.getElementById('ewoodDetailJob').textContent = `${row.branch || '—'} · ${row.project_type || '—'}`;
            ewoodOv.row = row; ewoodOv.latest = null; ewoodOv.loaded = false; ewoodOv.boms = [];

            // Details tab
            document.getElementById('ewoodDetailNtp').innerHTML = ewoodNtpBadge(row);
            document.getElementById('ewoodDetailVerified').innerHTML = ewoodVerifiedBadge(row);
            document.getElementById('ewoodDetailAccess').innerHTML = ewoodAccessBadge(canUpload);
            document.getElementById('ewoodDetailLatest').textContent = '—';

            const countEl = document.getElementById('ewoodHistoryCount');
            countEl.classList.add('hidden');
            countEl.classList.remove('inline-flex');

            ewoodRenderOverview();

            // Reset the two-step Upload & BOM state for this row.
            document.getElementById('ewoodUploadForm').reset();
            document.getElementById('ewoodQuotationId').value = quotationId;
            ewoodResetUploadStepState();

            const uploadFields = document.getElementById('ewoodUploadFields');
            const uploadBlocked = document.getElementById('ewoodUploadBlocked');
            uploadBlocked.classList.remove(
                'text-green-700', 'bg-green-50', 'border-green-200',
                'text-amber-700', 'bg-amber-50', 'border-amber-200'
            );
            uploadBlocked.classList.add('text-gray-500', 'bg-gray-50', 'border-gray-200');
            if (canUpload) {
                uploadFields.classList.remove('hidden');
                uploadBlocked.classList.add('hidden');
            } else {
                uploadFields.classList.add('hidden');
                uploadBlocked.textContent = !isNtp
                    ? 'Uploading is disabled until this submission has Notice to Proceed.'
                    : 'Uploading is disabled until the 2D file has been verified.';
                uploadBlocked.classList.remove('hidden');
            }

            // BOM items — reset to blank rows and reload the reference list
            // of previously submitted BOMs for this job.
            ewoodBomResetRows();
            document.getElementById('ewoodBomList').innerHTML = '';
            ewoodBomLoadList(quotationId);

            ewoodSwitchTab('details');

            // Show the detail view; on mobile this also hides the list.
            document.getElementById('ewoodEmptyState').classList.add('hidden');
            document.getElementById('ewoodDetailView').classList.remove('hidden');
            document.getElementById('ewoodDetailView').classList.add('flex');
            document.getElementById('ewoodMain').dataset.view = 'detail';

            ewoodLoadHistory(quotationId);
        }

        // ---------------------------------------------------------------
        // Daloy: Step 1 (pumili ng files) → Step 2 (BOM) → isang Submit.
        // Dati, ang "Save Progress" ay agad nagpapadala sa Superadmin kahit wala pang BOM.
        // Ngayon, sabay na ipinapadala ang progress at ang BOM sa dulo.
        // ---------------------------------------------------------------
        function ewoodStep1Error(msg) {
            const el = document.getElementById('ewoodStep1Err');
            el.textContent = msg || '';
            el.classList.toggle('hidden', !msg);
        }

        // ── Revise mode UI ── (boms = [{id, bom_number}] na naibalik)
        function ewoodBomReviseUi(boms, reason = '', missing = 0) {
            const note = document.getElementById('ewoodBomReviseNote');
            const btn = document.getElementById('ewoodCreateBomBtn');
            if (!boms || !boms.length) {
                note.classList.add('hidden');
                if (btn && !ewoodSubmitting) btn.innerHTML = EWOOD_SUBMIT_HTML;
                return;
            }
            document.getElementById('ewoodBomReviseNo').textContent = boms.map(b => b.bom_number).join(', ');
            const r = document.getElementById('ewoodBomReviseReason');
            r.textContent = reason ? `Superadmin: “${reason}”` : '';
            r.classList.toggle('hidden', !reason);
            const m = document.getElementById('ewoodBomReviseMissing');
            m.textContent = missing ? `${missing} item${missing === 1 ? '' : 's'} could not be matched to the current supplier list (highlighted). Choose the supplier and product again.` : '';
            m.classList.toggle('hidden', !missing);
            note.classList.remove('hidden');
            btn.innerHTML = EWOOD_REVISE_HTML;
        }

        // Ilagay sa isang BOM card ang items ng dating BOM; ibinabalik kung ilan ang hindi tumugma sa supplier list
        function ewoodBomFillRows(items, tbody) {
            tbody.innerHTML = '';
            const missingRows = [];
            items.forEach(it => {
                const supName = String(it.supplier || '').trim();
                const isOut = supName.toUpperCase() === 'OUTSOURCE';
                const sup = isOut ? null : ewoodCatalog.find(x => x.name.trim().toLowerCase() === supName.toLowerCase());
                const inCatalog = isOut || (sup && sup.items.some(x => x.item_code === it.item_code));
                const tr = ewoodBomAddRow({ supplier_value: isOut ? EWOOD_OUTSOURCE : (inCatalog ? String(sup.id) : ''), quantity: ewoodQtyFmt(it.quantity) }, tbody);
                if (inCatalog) {
                    tr.querySelector('[name="item_code[]"]').value = it.item_code || '';
                    tr.querySelector('[name="item_description[]"]').value = it.item_description || '';
                    tr.querySelector('[name="unit[]"]').value = it.unit || '';
                } else {
                    missingRows.push(tr);
                    tr.querySelector('[name="item_description[]"]').placeholder = `Was: ${supName || '—'} · ${it.item_description || it.item_code || ''}`;
                }
            });
            if (!items.length) for (let i = 0; i < 3; i++) ewoodBomAddRow({}, tbody);
            // pagkatapos idagdag lahat (nabubura ng ewoodApplySupplier ang highlight habang nagdadagdag)
            missingRows.forEach(tr => tr.classList.add('ring-1', 'ring-red-300'));
            ewoodBomCountItems(tbody.closest('.ewood-bom-card'));
            return missingRows.length;
        }

        // Kunin LAHAT ng naibalik na BOM ng job at ilagay bawat isa sa sariling card (true kapag tagumpay)
        async function ewoodBomLoadForRevise(boms) {
            try {
                if (!ewoodCatalogLoaded) await ewoodLoadCatalog();
                const full = await Promise.all(boms.map(async b => {
                    const res = await fetch(`${BOM_AJAX_URL}?action=view&bom_id=${encodeURIComponent(b.id)}`, { cache: 'no-store' });
                    const data = await ewoodJson(res);
                    if (!data.success || !data.bom) throw new Error(data.message || 'Could not load the BOM.');
                    return data.bom;
                }));
                document.getElementById('ewoodBomCards').innerHTML = '';
                ewoodBomRowIndex = 0;
                ewoodBomRemovedIds = [];
                let missing = 0;
                full.forEach(bom => {
                    const card = ewoodBomAddCard(bom);
                    missing += ewoodBomFillRows(bom.items || [], card.querySelector('.ewood-bom-items'));
                });
                ewoodBomRenumber();
                ewoodReviseBoms = full.map(b => ({ id: Number(b.id), bom_number: b.bom_number }));
                ewoodBomConfirmed = false;
                ewoodBomReviseUi(ewoodReviseBoms, ewoodOv?.latest?.approval_status === 'Rejected' ? (ewoodOv.latest.rejection_remarks || '') : '', missing);
                return ewoodReviseBoms.length > 0;
            } catch (e) {
                console.error('ewoodBomLoadForRevise:', e);
                ewoodReviseBoms = [];
                ewoodBomReviseUi(null);
                return false;
            }
        }

        // Mga hindi pa approved na BOM ng job (para ayusin / ikabit imbes na gumawa ng bago)
        async function ewoodBomFindUnapproved(quotationId) {
            try {
                const res = await fetch(`${BOM_AJAX_URL}?action=list&quotation_id=${quotationId}`, { cache: 'no-store' });
                const data = await ewoodJson(res);
                if (!data.success) return [];
                return (data.entries || []).filter(e => !e.approved_at && e.editable !== false).reverse();   // pinakaluma muna
            } catch (e) {
                console.error(e);
                return [];
            }
        }

        document.getElementById('ewoodSaveProgressBtn').addEventListener('click', async function () {
            const form = document.getElementById('ewoodUploadForm');
            const photos = form.querySelector('input[name="images[]"]').files;
            const archive = form.querySelector('input[name="archive"]').files;
            ewoodStep1Error('');
            if (!photos.length) {
                ewoodStep1Error('Add at least one photo (the QR photo is what the Superadmin checks).');
                return;
            }
            if (archive.length && !/\.(zip|rar)$/i.test(archive[0].name)) {
                ewoodStep1Error('The archive must be a .zip or .rar file.');
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking…';
            const quotationId = document.getElementById('ewoodQuotationId').value;
            ewoodExistingBoms = await ewoodBomFindUnapproved(quotationId);
            btn.disabled = false;
            btn.innerHTML = 'Next: Bill of Materials <i class="fa-solid fa-arrow-right ml-1"></i>';

            ewoodStep1Lock(true);
            // Naibalik ang BOM → buksan ang form na naka-fill para maayos (kapag nabasa nang maayos)
            // naka-load na ang parehong mga BOM (hal. bumalik sa Step 1) → huwag nang i-reload
            const sameSet = ewoodExistingBoms.length > 0 && ewoodReviseBoms.length > 0
                && ewoodExistingBoms.every(b => ewoodReviseBoms.some(r => r.id === Number(b.id)));
            if (ewoodExistingBoms.length && (sameSet || await ewoodBomLoadForRevise(ewoodExistingBoms))) {
                document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
                document.getElementById('ewoodBomSection').classList.remove('hidden');
                document.getElementById('ewoodBomSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else if (ewoodExistingBoms.length) {
                document.getElementById('ewoodBomExistingNo').textContent = ewoodExistingBoms.map(b => b.bom_number).join(', ');
                document.getElementById('ewoodBomSection').classList.add('hidden');
                document.getElementById('ewoodBomRelinkedNotice').classList.remove('hidden');
                document.getElementById('ewoodBomRelinkedNotice').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
                document.getElementById('ewoodBomSection').classList.remove('hidden');
                document.getElementById('ewoodBomSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Bumalik sa Step 1 para palitan ang files (habang wala pang naipapadala)
        document.getElementById('ewoodStep1Edit').addEventListener('click', () => {
            if (ewoodCurrentProgressionId !== null || ewoodSubmitting) return;
            ewoodStep1Lock(false);
            document.getElementById('ewoodBomSection').classList.add('hidden');
            document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
        });

        // Ipapadala ang progress (isang beses lang; kapag pumalya ang BOM, hindi na uulitin ang upload)
        async function ewoodUploadProgressOnce() {
            if (ewoodCurrentProgressionId !== null) return ewoodCurrentProgressionId;
            const res = await fetch(`${CUT_PROG_AJAX_URL}?action=upload`, { method: 'POST', body: new FormData(document.getElementById('ewoodUploadForm')) });
            const data = await ewoodJson(res);
            if (!data.success || !data.id) throw new Error(data.message || 'Could not save the progress upload.');
            ewoodCurrentProgressionId = data.id;
            ewoodUploadQuotationId = document.getElementById('ewoodQuotationId').value;
            document.getElementById('ewoodStep1Edit').classList.add('hidden');
            return data.id;
        }

        // Pagkatapos maipadala: i-lock ang form at ipakita sa History
        function ewoodAfterSubmit(quotationId) {
            ewoodCurrentProgressionId = null;
            document.getElementById('ewoodUploadFields').classList.add('hidden');
            const blocked = document.getElementById('ewoodUploadBlocked');
            blocked.textContent = 'Submitted. It is now waiting for the Superadmin\'s QR approval. You can submit again once it is approved or sent back for revision.';
            blocked.classList.remove('text-gray-500', 'bg-gray-50', 'border-gray-200', 'text-green-700', 'bg-green-50', 'border-green-200');
            blocked.classList.add('text-amber-700', 'bg-amber-50', 'border-amber-200');
            blocked.classList.remove('hidden');
            ewoodBomLoadList(quotationId);
            ewoodLoadHistory(quotationId);
            ewoodSwitchTab('history');
        }

        // Step 2a: gumawa ng BOM + ipadala ang progress
        document.getElementById('ewoodCreateBomBtn').addEventListener('click', function () {
            if (ewoodSubmitting) return;
            const [itemErr, badRow, badCard] = ewoodBomValidateItems();
            if (itemErr) {
                ewoodBomItemsError(itemErr, badRow, badCard);
                (badRow || badCard)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            // "Retry BOM": na-confirm na ito dati, kaya ituloy na
            if (ewoodBomConfirmed && ewoodCurrentProgressionId !== null) return ewoodDoCreate();
            ewoodOpenConfirm('create');
        });

        async function ewoodDoCreate() {
            if (ewoodSubmitting) return;
            const quotationId = document.getElementById('ewoodQuotationId').value;
            const btn = document.getElementById('ewoodCreateBomBtn');

            ewoodSubmitting = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Submitting…';
            let step = 'progress';
            try {
                const progressionId = await ewoodUploadProgressOnce();
                step = 'bom';
                const formData = new FormData();
                formData.append('quotation_id', quotationId);
                formData.append('progression_id', progressionId);
                formData.append('confirm_bom', ewoodBomConfirmed ? '1' : '0');
                formData.append('boms', JSON.stringify(ewoodBomCollect()));
                formData.append('remove_bom_ids', JSON.stringify(ewoodBomRemovedIds));
                const res = await fetch(`${BOM_AJAX_URL}?action=create_multi`, { method: 'POST', body: formData });
                const data = await ewoodJson(res);
                if (!data.success) throw new Error(data.message || 'Could not save the Bills of Materials.');

                const saved = data.boms || [];
                const nums = saved.map(b => b.bom_number).filter(Boolean).join(', ');
                crmShowToast(saved.length > 1
                    ? `Progress and ${saved.length} BOMs (${nums}) were submitted to the Superadmin.`
                    : (saved[0]?.revised ? `Progress and the updated ${nums || 'BOM'} were submitted to the Superadmin.` : 'Progress and BOM submitted to the Superadmin.'), 'success', 6000);
                ewoodReviseBoms = [];
                ewoodBomRemovedIds = [];
                document.getElementById('ewoodBomReviseNote').classList.add('hidden');
                ewoodBomLock(true);
                btn.innerHTML = 'Submitted';
                ewoodSubmitting = false;
                ewoodAfterSubmit(quotationId);
            } catch (e) {
                console.error(e);
                ewoodSubmitting = false;
                btn.disabled = false;
                if (step === 'bom') {
                    // naipadala na ang progress; BOM na lang ang uulitin
                    btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-1"></i> Retry BOM';
                    crmShowToast(`Progress was saved, but the BOM failed: ${e.message}. Fix it if needed, then click "Retry BOM".`, 'error', 8000);
                } else {
                    btn.innerHTML = EWOOD_SUBMIT_HTML;
                    crmShowToast(ewoodEscapeHtml(e.message || 'Could not save the progress upload.'), 'error', 6000);
                }
            }
        }

        // Step 2b: may BOM na → ipadala ang progress at ikabit ang BOM
        document.getElementById('ewoodSubmitRelinkBtn').addEventListener('click', function () {
            if (ewoodSubmitting) return;
            ewoodOpenConfirm('relink');
        });

        async function ewoodDoRelink() {
            if (ewoodSubmitting) return;
            const quotationId = document.getElementById('ewoodQuotationId').value;
            const btn = document.getElementById('ewoodSubmitRelinkBtn');
            ewoodSubmitting = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Submitting…';
            try {
                const progressionId = await ewoodUploadProgressOnce();
                const linked = await ewoodBomRelink(quotationId, progressionId);
                ewoodSubmitting = false;
                if (linked) {
                    crmShowToast(`Submitted with ${ewoodExistingBoms.map(b => b.bom_number).join(', ') || 'the existing BOM'}.`, 'success');
                    btn.innerHTML = 'Submitted';
                    ewoodAfterSubmit(quotationId);
                } else {
                    // hindi naikabit → gumawa na lang ng bagong BOM para sa submission na ito
                    crmShowToast('The existing BOM could not be attached. Please fill in a new BOM below.', 'error', 6000);
                    ewoodBomConfirmed = false;   // bagong BOM → kailangang i-review ulit
                    document.getElementById('ewoodBomRelinkedNotice').classList.add('hidden');
                    document.getElementById('ewoodBomSection').classList.remove('hidden');
                    ewoodBomResetRows();
                    document.getElementById('ewoodCreateBomBtn').innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i> Submit BOM';
                }
            } catch (e) {
                console.error(e);
                ewoodSubmitting = false;
                btn.disabled = false;
                btn.innerHTML = EWOOD_RELINK_HTML;
                crmShowToast(ewoodEscapeHtml(e.message || 'Could not submit.'), 'error', 6000);
            }
        }

        // ═════════════ DOUBLE CONFIRMATION ═════════════
        // Hakbang 1: makikita ang buong BOM at upload; kailangang i-check ang "I checked…".
        // Hakbang 2: huling tanong bago ipadala. Sa server, tinatanggihan ang BOM na walang confirm_bom=1.
        let ewoodCf = null;   // { mode, rows, bom }
        const ewoodCfEl = id => document.getElementById(id);

        function ewoodCfRowsFromForm(card) {
            return [...card.querySelectorAll('tbody tr')].map(tr => ({
                supplier: tr.querySelector('[name="supplier[]"]').value.trim(),
                item_code: tr.querySelector('[name="item_code[]"]').value.trim(),
                item_description: tr.querySelector('[name="item_description[]"]').value.trim(),
                quantity: tr.querySelector('[name="quantity[]"]').value.trim(),
                unit: tr.querySelector('[name="unit[]"]').value.trim(),
            })).filter(r => r.item_description || r.item_code);
        }

        function ewoodCfSteps(n) {
            [['ewoodCfStep1', 1], ['ewoodCfStep2', 2]].forEach(([id, k]) => {
                const li = ewoodCfEl(id), dot = li.querySelector('span');
                li.className = `flex items-center gap-1.5 ${k === n ? 'text-amber-800' : k < n ? 'text-green-700' : 'text-gray-400'}`;
                dot.className = `w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${k === n ? 'bg-amber-700 text-white' : k < n ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-500'}`;
                dot.innerHTML = k < n ? '<i class="fa-solid fa-check text-[9px]"></i>' : k;
            });
        }

        async function ewoodOpenConfirm(mode) {
            const row = ewoodRowsMap[ewoodActiveQuotationId] || {};
            ewoodCf = { mode, boms: [], removed: [] };
            ewoodCfEl('ewoodCfTitle').textContent = `${row.control_no || ''} · ${row.client_name || ''}`;
            const m = ewoodCfEl('ewoodConfirmModal');
            m.classList.remove('hidden'); m.classList.add('flex');
            document.body.style.overflow = 'hidden';
            if (mode === 'create') {
                const cards = ewoodBomCards();
                ewoodCf.boms = cards.map((card, i) => {
                    const bid = Number(card.dataset.bomId) || 0;
                    return {
                        bom_id: bid,
                        label: bid > 0 ? `${card.dataset.bomNumber} (updated)` : (cards.length > 1 ? `New BOM ${i + 1}` : 'New BOM'),
                        date_needed: card.querySelector('[name="date_needed"]').value,
                        general_scope: card.querySelector('[name="general_scope"]').value,
                        rows: ewoodCfRowsFromForm(card),
                    };
                });
                ewoodCf.removed = ewoodReviseBoms.filter(b => ewoodBomRemovedIds.includes(b.id)).map(b => b.bom_number);
                ewoodCfReview();
            } else {
                ewoodCfSteps(1);
                ewoodCfEl('ewoodCfBody').innerHTML = '<p class="py-12 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading the BOM…</p>';
                ewoodCfEl('ewoodCfFoot').innerHTML = '';
                try {
                    const list = await Promise.all(ewoodExistingBoms.map(async b => {
                        const res = await fetch(`${BOM_AJAX_URL}?action=view&bom_id=${encodeURIComponent(b.id)}`);
                        const data = await ewoodJson(res);
                        if (!data.success) throw new Error(data.message || 'Could not load the BOM.');
                        return data.bom;
                    }));
                    if (!ewoodCf) return;
                    ewoodCf.boms = list.map(bom => ({ bom_id: Number(bom.id), label: bom.bom_number, date_needed: bom.date_needed, general_scope: bom.general_scope, rows: bom.items || [] }));
                    ewoodCfReview();
                } catch (e) {
                    ewoodCfEl('ewoodCfBody').innerHTML = `<p class="py-12 text-center text-xs text-red-600">${ewoodEscapeHtml(e.message)}</p>`;
                }
            }
        }

        function ewoodCloseConfirm() {
            const m = ewoodCfEl('ewoodConfirmModal');
            m.classList.add('hidden'); m.classList.remove('flex');
            document.body.style.overflow = '';
            ewoodCf = null;
        }
        ewoodCfEl('ewoodCfClose').addEventListener('click', ewoodCloseConfirm);
        ewoodCfEl('ewoodConfirmModal').addEventListener('click', e => { if (e.target.id === 'ewoodConfirmModal') ewoodCloseConfirm(); });

        // Isang BOM sa review: grupo ayon sa supplier, at markahan ang dobleng item
        function ewoodCfBomTable(bom) {
            const rows = bom.rows;
            const groups = {};
            rows.forEach(r => { const k = r.supplier || '—'; (groups[k] ||= []).push(r); });
            const keyOf = r => `${(r.supplier || '').toUpperCase()}|${(r.item_code || r.item_description || '').toUpperCase()}`;
            const seen = {}, dupes = new Set();
            rows.forEach(r => { const k = keyOf(r); if (seen[k]) dupes.add(k); seen[k] = true; });
            return `
                <div class="mt-4 rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-800"><i class="fa-solid fa-file-invoice text-amber-700 mr-1"></i>${ewoodEscapeHtml(bom.label || 'Bill of Materials')} <span class="font-normal text-gray-400">· ${rows.length} item${rows.length === 1 ? '' : 's'}</span></p>
                        <p class="text-[11px] text-gray-500">Scope: <b class="text-gray-700">${ewoodEscapeHtml(bom.general_scope || '—')}</b> · Needed: <b class="text-gray-700">${ewoodEscapeHtml(bom.date_needed || '—')}</b></p>
                    </div>
                    ${dupes.size ? `<p class="px-4 py-2 text-[11.5px] text-amber-900 bg-amber-50 border-b border-amber-200"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Some items appear more than once in this BOM. Check if that is intended (highlighted).</p>` : ''}
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px] min-w-[480px]">
                            <thead class="text-[10px] uppercase tracking-wide text-gray-400"><tr>
                                <th class="text-left font-semibold px-4 py-2 w-8">#</th>
                                <th class="text-left font-semibold px-2 py-2">Item</th>
                                <th class="text-right font-semibold px-4 py-2">Qty</th></tr></thead>
                            <tbody>
                            ${Object.entries(groups).map(([sup, list]) => `
                                <tr class="${sup === 'OUTSOURCE' ? 'bg-amber-50' : 'bg-slate-50'}"><td colspan="3" class="px-4 py-1.5 text-[11px] font-bold ${sup === 'OUTSOURCE' ? 'text-amber-800' : 'text-gray-700'}">
                                    <i class="fa-solid ${sup === 'OUTSOURCE' ? 'fa-magnifying-glass' : 'fa-truck-field'} mr-1"></i>${sup === 'OUTSOURCE' ? 'Outsource — still needs a supplier' : ewoodEscapeHtml(sup)} <span class="font-normal text-gray-400">(${list.length})</span></td></tr>
                                ${list.map(r => {
                                    const k = keyOf(r);
                                    return `<tr class="border-t border-gray-100 ${dupes.has(k) ? 'bg-amber-50/70' : ''}">
                                        <td class="px-4 py-2 text-gray-400 align-top">${rows.indexOf(r) + 1}</td>
                                        <td class="px-2 py-2 align-top text-gray-800 break-words">${r.item_code ? `<span class="font-mono text-[11px] text-gray-400 mr-1">${ewoodEscapeHtml(r.item_code)}</span>` : ''}${ewoodEscapeHtml(r.item_description)}${dupes.has(k) ? ' <span class="text-[10px] font-semibold text-amber-700">· repeated</span>' : ''}</td>
                                        <td class="px-4 py-2 align-top text-right whitespace-nowrap font-semibold text-gray-900">${ewoodQtyFmt(r.quantity)} <span class="font-normal text-gray-400">${ewoodEscapeHtml(r.unit || '')}</span></td></tr>`;
                                }).join('')}`).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
        }

        // Hakbang 1 — buong review (lahat ng BOM)
        function ewoodCfReview() {
            if (!ewoodCf) return;
            ewoodCfSteps(1);
            const boms = ewoodCf.boms;
            const all = boms.flatMap(b => b.rows);
            const form = document.getElementById('ewoodUploadForm');
            const photos = [...form.querySelector('input[name="images[]"]').files];
            const archive = form.querySelector('input[name="archive"]').files[0];
            const remarks = form.querySelector('textarea[name="remarks"]').value.trim();

            const sups = new Set(all.map(r => r.supplier || '—').filter(k => k !== 'OUTSOURCE'));
            const outN = all.filter(r => r.supplier === 'OUTSOURCE').length;
            const tile = (label, val, cls = 'text-gray-900') => `<div class="rounded-xl border border-gray-200 px-3 py-2"><p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">${label}</p><p class="text-lg font-bold ${cls} leading-tight">${val}</p></div>`;

            ewoodCfEl('ewoodCfBody').innerHTML = `
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    ${tile('BOMs', boms.length, boms.length > 1 ? 'text-amber-700' : 'text-gray-900')}${tile('Items', all.length)}${tile('Suppliers', sups.size)}${tile('Outsource', outN, outN ? 'text-amber-700' : 'text-gray-900')}${tile('Photos', photos.length || '—')}
                </div>
                ${ewoodCf.removed?.length ? `<p class="mt-3 text-[11.5px] text-red-800 bg-red-50 border border-red-200 rounded-lg px-3 py-2"><i class="fa-solid fa-trash-can mr-1"></i>Will be deleted: <b class="font-mono">${ewoodEscapeHtml(ewoodCf.removed.join(', '))}</b></p>` : ''}

                ${boms.map(ewoodCfBomTable).join('')}

                <div class="mt-4 rounded-xl border border-gray-200 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Progress upload</p>
                    <div class="flex flex-wrap items-center gap-2">
                        ${photos.slice(0, 6).map(f => `<img src="${URL.createObjectURL(f)}" alt="" class="w-14 h-14 object-cover rounded-lg border border-gray-200">`).join('')}
                        ${photos.length > 6 ? `<span class="text-[11px] text-gray-400">+${photos.length - 6} more</span>` : ''}
                        ${archive ? `<span class="inline-flex items-center gap-1.5 h-8 px-2.5 text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg max-w-full"><i class="fa-solid fa-file-zipper"></i><span class="truncate">${ewoodEscapeHtml(archive.name)}</span></span>` : '<span class="text-[11px] text-gray-400">No archive</span>'}
                    </div>
                    ${remarks ? `<p class="mt-2 text-xs text-gray-600 break-words"><i class="fa-solid fa-comment-dots text-gray-300 mr-1"></i>${ewoodEscapeHtml(remarks)}</p>` : ''}
                </div>

                <label class="mt-4 flex items-start gap-2.5 rounded-xl border-2 border-amber-200 bg-amber-50/60 px-4 py-3 cursor-pointer select-none">
                    <input type="checkbox" id="ewoodCfCheck" class="mt-0.5 w-4 h-4 accent-amber-700 shrink-0">
                    <span class="text-xs text-amber-900"><b>I checked every item, quantity and supplier${boms.length > 1 ? ` in all ${boms.length} BOMs` : ''}.</b> ${boms.length > 1 ? 'These Bills of Materials are' : 'This Bill of Materials is'} complete and correct.</span>
                </label>`;
            ewoodCfEl('ewoodCfFoot').innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="button" data-cf="edit" class="px-3.5 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-100"><i class="fa-solid fa-pen mr-1"></i>${ewoodCf.mode === 'create' ? 'Edit the BOM' : 'Cancel'}</button>
                    <button type="button" data-cf="next" disabled class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed">Continue <i class="fa-solid fa-arrow-right"></i></button>
                </div>`;
            ewoodCfEl('ewoodCfCheck').addEventListener('change', e => { ewoodCfEl('ewoodCfFoot').querySelector('[data-cf="next"]').disabled = !e.target.checked; });
            ewoodCfEl('ewoodCfBody').scrollTop = 0;
        }

        // Hakbang 2 — huling tanong
        function ewoodCfFinal() {
            if (!ewoodCf) return;
            ewoodCfSteps(2);
            const bomN = ewoodCf.boms.length;
            const n = ewoodCf.boms.reduce((t, b) => t + b.rows.length, 0);
            const bomWord = bomN > 1 ? `<b>${bomN} BOMs</b>` : (ewoodReviseBoms.length && ewoodCf.boms[0]?.bom_id ? `updated <b>${ewoodEscapeHtml(ewoodCf.boms[0].label)}</b>` : 'BOM');
            ewoodCfEl('ewoodCfBody').innerHTML = `
                <div class="py-6 text-center max-w-md mx-auto">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-paper-plane text-xl"></i></div>
                    <p class="text-base font-semibold text-gray-900">Send this to the Superadmin?</p>
                    <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">${ewoodCf.mode === 'create' ? `The progress upload and the ${bomWord} with <b>${n} item${n === 1 ? '' : 's'}</b> in total` : `The progress upload with <b>${ewoodEscapeHtml(ewoodCf.boms.map(b => b.label).join(', ') || 'the existing BOM')}</b>`} will be sent for QR approval.</p>
                    <div class="mt-4 text-left rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[11.5px] text-red-800 space-y-1">
                        <p><i class="fa-solid fa-lock mr-1"></i>You <b>cannot edit</b> the BOM${bomN > 1 ? 's' : ''} after sending.</p>
                        <p><i class="fa-solid fa-rotate-left mr-1"></i>If something is wrong, the Superadmin has to send it back first.</p>
                    </div>
                </div>`;
            ewoodCfEl('ewoodCfFoot').innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="button" data-cf="back" class="px-3.5 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-100"><i class="fa-solid fa-arrow-left mr-1"></i>Back to review</button>
                    <button type="button" data-cf="send" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-paper-plane"></i> Yes, send to Superadmin</button>
                </div>`;
        }

        ewoodCfEl('ewoodCfFoot').addEventListener('click', e => {
            const b = e.target.closest('[data-cf]'); if (!b || !ewoodCf) return;
            const act = b.dataset.cf;
            if (act === 'edit') { ewoodCloseConfirm(); document.getElementById('ewoodBomSection').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
            if (act === 'next') { if (ewoodCfEl('ewoodCfCheck')?.checked) ewoodCfFinal(); return; }
            if (act === 'back') { ewoodCfReview(); return; }
            if (act === 'send') {
                const mode = ewoodCf.mode;
                ewoodBomConfirmed = true;
                ewoodCloseConfirm();
                if (mode === 'create') ewoodDoCreate(); else ewoodDoRelink();
            }
        });

        function ewoodQtyFmt(q) { const n = parseFloat(q); return isNaN(n) ? '' : n.toFixed(2).replace(/\.?0+$/, ''); }

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // NTP ng Accounting, Verify 2D, bagong upload/BOM, QR approve/reject ng Superadmin, reset → kunin agad.
        // Hindi ginagalaw ang bukas na job habang may ginagawa sa Upload & BOM (files, BOM, confirm, picker).
        const ewoodRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
        let ewoodRtMissed = false;
        let ewoodPollTimer = null;

        function ewoodRtBusy() {
            if (ewoodSubmitting || ewoodCurrentProgressionId !== null || ewoodCf) return true;
            if (document.getElementById('ewoodStep1Body')?.classList.contains('pointer-events-none')) return true; // nasa Step 2 (BOM)
            if (!document.getElementById('ewoodPickModal')?.classList.contains('hidden')) return true;
            const form = document.getElementById('ewoodUploadForm');
            if (form) {
                if ([...form.querySelectorAll('input[type="file"]')].some(el => el.files && el.files.length)) return true;
                const remarks = form.querySelector('textarea[name="remarks"]');
                if (remarks && remarks.value.trim()) return true;
            }
            return false;
        }

        function ewoodCurrentTab() {
            if (!document.getElementById('ewoodUploadForm').classList.contains('hidden')) return 'upload';
            if (!document.getElementById('ewoodPanelHistory').classList.contains('hidden')) return 'history';
            return 'details';
        }

        // I-reload ang bukas na job nang hindi nawawala ang tab, scroll at list/detail view (mobile)
        function ewoodRtRefreshDetail() {
            const id = ewoodActiveQuotationId;
            if (id === null || !ewoodRowsMap[id]) return;
            if (document.hidden || ewoodRtBusy()) { ewoodRtMissed = true; return; }
            ewoodRtMissed = false;
            const main = document.getElementById('ewoodMain');
            const body = document.getElementById('ewoodDetailBody');
            const view = main.dataset.view, tab = ewoodCurrentTab(), top = body.scrollTop;
            ewoodOpenSidebar(id);
            if (tab !== 'details') ewoodSwitchTab(tab);
            body.scrollTop = top;
            main.dataset.view = view;
        }

        const ewoodGateSig = row => row ? `${row.deposit_status}|${row.design_2d_verified ? 1 : 0}` : '';

        async function ewoodRtSync(payload = null) {
            const activeId = ewoodActiveQuotationId;
            const before = activeId !== null ? ewoodGateSig(ewoodRowsMap[activeId]) : '';
            const activeInq = activeId !== null ? Number(ewoodRowsMap[activeId]?.inquiry_id || 0) : 0;
            const ok = await ewoodFetchList({ silent: true });
            if (!ok || activeId === null || Number(ewoodActiveQuotationId) !== Number(activeId)) return;

            const inq = Number(payload?.inquiry_id || 0);
            const forThisJob = payload && (!inq || inq === activeInq);
            const gateChanged = before !== ewoodGateSig(ewoodRowsMap[activeId]);
            if (forThisJob || gateChanged) ewoodRtRefreshDetail();
        }

        function ewoodStartPolling() {
            if (ewoodPollTimer) clearInterval(ewoodPollTimer);
            // Live → 60s fallback lang; hindi Live → 20s
            ewoodPollTimer = setInterval(() => { if (!document.hidden) ewoodRtSync(); }, ewoodRtLive() ? 60000 : 20000);
        }

        (function ewoodHookRealtime() {
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || ewoodHookRealtime.done) return;
                ewoodHookRealtime.done = true;
                let t = null, last = null;
                window.NH_RT.on('*', 'changed', (payload) => {
                    const type = String(payload?.type || '');
                    if (type && !type.startsWith('crm')) return; // budget/voucher — hindi para dito
                    // pagsamahin ang sunod-sunod na signal; kapag magkaibang job, i-check lahat
                    last = (last && Number(last.inquiry_id || 0) !== Number(payload?.inquiry_id || 0)) ? {} : (payload || {});
                    clearTimeout(t);
                    t = setTimeout(() => { const p = last; last = null; if (document.hidden) { ewoodRtMissed = true; return; } ewoodRtSync(p); }, 400);
                });
                window.NH_RT.onLive(live => {
                    const dot = document.getElementById('ewoodLiveDot');
                    if (dot) {
                        dot.className = 'inline-block w-2 h-2 rounded-full ml-1 align-middle ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        dot.title = live ? 'Live' : 'Reconnecting…';
                    }
                    if (!document.hidden) ewoodStartPolling();
                    if (live && ewoodRowsList.length) ewoodRtSync();
                });
            };
            hook();
            if (!ewoodHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
        })();

        // habol sa na-miss habang may ginagawa (hal. na-cancel ang upload) o naka-tago ang tab
        setInterval(() => { if (ewoodRtMissed && !document.hidden) ewoodRtSync({}); }, 5000);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (ewoodPollTimer) clearInterval(ewoodPollTimer);
            } else {
                ewoodRtSync(ewoodRtMissed ? {} : null);
                ewoodStartPolling();
            }
        });

        ewoodLoadCatalog().then(() => { if (!ewoodBomCards().length) ewoodBomResetRows(); });
        ewoodFetchList().then(ewoodStartPolling);
    </script>
</body>

</html>