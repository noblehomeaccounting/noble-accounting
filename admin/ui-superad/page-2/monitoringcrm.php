<?php
// monitoringcrm.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$monAjaxUrl = BASE_URL . '/monitoringcrmajax';
$monViewUrl = BASE_URL . '/monitoringcrmview';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>CRM Monitoring</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .mon-scroll {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        .mon-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .mon-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .mon-scroll::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 9999px;
        }

        .mon-scroll::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .mon-field {
            width: 100%;
            font-size: 12px;
            color: #374151;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 7px 10px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .mon-field:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 3px #fef3c7;
        }

        .mon-label {
            display: block;
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .mon-section-title {
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 2px;
        }

        .mon-hint {
            font-size: 11px;
            color: #9ca3af;
        }

        /* ── Filter sidebar (e-commerce style) ── */
        .mon-side {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 300px;
            max-width: 88vw;
            z-index: 9999;
            background: #fff;
            display: flex;
            flex-direction: column;
            transform: translateX(-105%);
            transition: transform .22s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18);
        }

        .mon-side.open {
            transform: none;
        }

        @media (min-width: 1280px) {
            .mon-side {
                position: sticky;
                top: 16px;
                bottom: auto;
                width: 264px;
                max-width: none;
                flex-shrink: 0;
                z-index: 1;
                transform: none;
                transition: none;
                box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                max-height: calc(100vh - 32px);
            }
        }

        .mon-sec {
            border-top: 1px solid #f3f4f6;
        }

        .mon-sec>summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #374151;
            user-select: none;
        }

        .mon-sec>summary::-webkit-details-marker {
            display: none;
        }

        .mon-sec>summary::after {
            content: '';
            width: 7px;
            height: 7px;
            border-right: 2px solid #9ca3af;
            border-bottom: 2px solid #9ca3af;
            transform: rotate(45deg);
            margin-top: -3px;
            transition: transform .15s;
        }

        .mon-sec[open]>summary::after {
            transform: rotate(-135deg);
            margin-top: 3px;
        }

        .mon-sec>summary .mon-on {
            display: none;
            width: 7px;
            height: 7px;
            border-radius: 9999px;
            background: #f59e0b;
            margin-left: 6px;
            margin-right: auto;
        }

        .mon-sec[data-active="1"]>summary .mon-on {
            display: inline-block;
        }

        .mon-sec-body {
            padding: 0 16px 14px;
        }

        /* Stage list rows */
        .mon-stage-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            text-align: left;
            font-size: 12px;
            font-weight: 500;
            padding: 7px 9px;
            border-radius: 7px;
            color: #374151;
            transition: background .12s, color .12s;
        }

        .mon-stage-tab:hover {
            background: #fffbeb;
            color: #b45309;
        }

        .mon-stage-tab[aria-pressed="true"] {
            background: #1f2937;
            color: #fff;
            font-weight: 600;
        }

        .mon-stage-tab .mon-radio {
            width: 13px;
            height: 13px;
            border-radius: 9999px;
            border: 1.5px solid #d1d5db;
            flex-shrink: 0;
        }

        .mon-stage-tab[aria-pressed="true"] .mon-radio {
            border-color: #f59e0b;
            box-shadow: inset 0 0 0 3px #1f2937;
            background: #f59e0b;
        }

        .mon-stage-tab .mon-lbl {
            flex: 1;
            min-width: 0;
        }

        .mon-stage-tab .mon-n {
            font-size: 10.5px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            padding: 1px 6px;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #374151;
        }

        .mon-stage-tab[aria-pressed="true"] .mon-n {
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        .mon-stage-tab .mon-num {
            font-size: 10px;
            color: #9ca3af;
            font-weight: 700;
            margin-right: 2px;
        }

        .mon-stage-tab[aria-pressed="true"] .mon-num {
            color: #fcd34d;
        }

        .mon-seg {
            display: flex;
            gap: 2px;
            width: 96px;
        }

        .mon-seg span {
            flex: 1;
            height: 5px;
            border-radius: 2px;
            background: #e5e7eb;
        }

        .mon-seg span.on {
            background: #d97706;
        }

        .mon-seg span.cur {
            background: #f59e0b;
            box-shadow: 0 0 0 1px #fde68a;
        }

        .mon-seg.done span.on {
            background: #10b981;
        }

        @media (max-width: 1279px) {
            .mon-stage-tab { padding: 9px 10px; }
            .mon-sec>summary { padding: 14px 16px; }
        }

        .mon-new-dot {
            width: 7px;
            height: 7px;
            border-radius: 9999px;
            background: #f59e0b;
            box-shadow: 0 0 0 3px #fef3c7;
            display: inline-block;
            flex-shrink: 0;
        }

    </style>
</head>

<body>

    <main class="md:pl-[240px] px-3 sm:px-6 pt-20 pb-6 md:py-6">
        <div class="max-w-[1400px] mx-auto">

            <!-- Header -->
            <div class="mb-4 sm:mb-5">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM Management
                </p>
                <h1 class="text-gray-900 text-lg sm:text-xl font-semibold">CRM Monitoring</h1>
            </div>

            <div class="flex items-start gap-5">

                <!-- ═══ Filter sidebar (desktop: nakadikit sa kaliwa · phone/tablet: drawer) ═══ -->
                <aside id="monModal" class="mon-side" aria-labelledby="monModalTitle">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18l-7 8v6l-4 2v-8L3 5z" />
                            </svg>
                            <h2 id="monModalTitle" class="text-gray-900 text-sm font-semibold">Filters</h2>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" id="monPanelClear"
                                class="text-[11px] text-gray-500 hover:text-amber-700 font-medium px-2 py-1">Reset</button>
                            <button type="button" id="monModalClose"
                                class="xl:hidden -mr-2 w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg"
                                aria-label="Close"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto mon-scroll">

                        <!-- Project type -->
                        <details class="mon-sec" data-sec="type" style="border-top:0">
                            <summary><span>Project type</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body">
                                <div id="monType" class="grid grid-cols-3 rounded-lg border border-gray-300 overflow-hidden text-[11px] font-medium">
                                    <button type="button" data-type="" class="px-2 py-1.5">All</button>
                                    <button type="button" data-type="site_visit" class="px-2 py-1.5 border-l border-gray-300">Modular</button>
                                    <button type="button" data-type="ready_for_quotation" class="px-2 py-1.5 border-l border-gray-300">Materials</button>
                                </div>
                                <!-- kasabay ng Project type (para sa MON_FIELDS) -->
                                <select id="fMode" class="hidden" aria-hidden="true" tabindex="-1">
                                    <option value="">Any</option>
                                    <option value="site_visit">Modular</option>
                                    <option value="ready_for_quotation">Materials</option>
                                </select>
                            </div>
                        </details>

                        <!-- Customer stage -->
                        <details class="mon-sec" data-sec="stage">
                            <summary><span>Customer stage</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body">
                                <p id="monFlowHint" class="mon-hint mb-2"></p>
                                <div id="monStageTabs" class="space-y-0.5"></div>
                            </div>
                        </details>

                        <!-- Needs attention -->
                        <details class="mon-sec" data-sec="attention">
                            <summary><span>Needs attention</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body">
                                <div id="monAttention" class="space-y-1.5"></div>
                            </div>
                        </details>

                        <!-- Date -->
                        <details class="mon-sec" data-sec="date">
                            <summary><span>Date</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body space-y-2.5">
                                <div>
                                    <label class="mon-label" for="fBasis">Based on</label>
                                    <select id="fBasis" class="mon-field">
                                        <option value="filed">Date filed</option>
                                        <option value="updated">Last update</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" data-qdate="today" class="mon-qdate text-[11px] font-medium px-2 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-600 hover:border-amber-400 hover:text-amber-700">Today</button>
                                    <button type="button" data-qdate="month" class="mon-qdate text-[11px] font-medium px-2 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-600 hover:border-amber-400 hover:text-amber-700">This month</button>
                                </div>
                                <div>
                                    <label class="mon-label" for="fMonth">Month</label>
                                    <input id="fMonth" type="month" class="mon-field">
                                </div>
                                <div>
                                    <label class="mon-label" for="fDay">Day</label>
                                    <input id="fDay" type="date" class="mon-field">
                                </div>
                                <button type="button" id="monDateClear" class="hidden text-[11px] text-gray-500 hover:text-amber-700 font-medium">Clear date</button>
                                <div class="pt-1">
                                    <p class="mon-label">Range (From – To)</p>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <input id="fFrom" type="date" class="mon-field" title="From" aria-label="From">
                                        <input id="fTo" type="date" class="mon-field" title="To" aria-label="To">
                                    </div>
                                </div>
                            </div>
                        </details>

                        <!-- People & details -->
                        <details class="mon-sec" data-sec="details">
                            <summary><span>Details</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body space-y-2.5">
                                <div><label class="mon-label" for="fProject">Project</label>
                                    <select id="fProject" class="mon-field">
                                        <option value="">All projects</option>
                                    </select>
                                </div>
                                <div><label class="mon-label" for="fSales">Sales</label>
                                    <select id="fSales" class="mon-field">
                                        <option value="">All sales</option>
                                    </select>
                                </div>
                                <div><label class="mon-label" for="fDesigner">Designer</label>
                                    <select id="fDesigner" class="mon-field">
                                        <option value="">All designers</option>
                                        <option value="none">Unassigned</option>
                                    </select>
                                </div>
                                <div><label class="mon-label" for="fClient">Client status</label>
                                    <select id="fClient" class="mon-field">
                                        <option value="">Any</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="tentative">Tentative</option>
                                        <option value="no">Not proceeding</option>
                                        <option value="none">Not yet contacted</option>
                                    </select>
                                </div>
                            </div>
                        </details>

                        <!-- Quick filters -->
                        <details class="mon-sec" data-sec="quick">
                            <summary><span>Quick filters</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body">
                                <div id="monPresets" class="flex flex-wrap gap-1.5"></div>
                            </div>
                        </details>

                        <!-- Current status & timing -->
                        <details class="mon-sec" data-sec="current">
                            <summary><span>Current status</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body space-y-2.5">
                                <div><label class="mon-label" for="fCurrent">Current status</label>
                                    <select id="fCurrent" class="mon-field">
                                        <option value="">Any</option>
                                    </select>
                                </div>
                                <div>
                                    <p class="mon-label">Status date</p>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <input id="fSFrom" type="date" class="mon-field" aria-label="Status date from">
                                        <input id="fSTo" type="date" class="mon-field" aria-label="Status date to">
                                    </div>
                                    <p class="mon-hint mt-1">Kung kailan nangyari ang napiling status.</p>
                                </div>
                            </div>
                        </details>

                        <!-- Status history -->
                        <details class="mon-sec" data-sec="history">
                            <summary><span>Status history</span><span class="mon-on" title="May naka-on na filter dito"></span></summary>
                            <div class="mon-sec-body">
                                <p class="mon-hint mb-2">Projects that passed through the selected statuses</p>
                                <div id="monMatch"
                                    class="grid grid-cols-2 rounded-lg border border-gray-300 overflow-hidden text-[11px] font-medium mb-3">
                                    <button type="button" data-match="any" class="px-3 py-1.5">Any of</button>
                                    <button type="button" data-match="all" class="px-3 py-1.5 border-l border-gray-300">All of</button>
                                </div>
                                <div id="monStatusList" class="space-y-3"></div>
                            </div>
                        </details>
                    </div>

                    <!-- Phone/tablet lang -->
                    <div class="xl:hidden px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] border-t border-gray-100 bg-gray-50/60">
                        <button type="button" id="monModalDone"
                            class="w-full text-xs font-medium bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg transition-colors">Show results</button>
                    </div>
                </aside>
                <div id="monModalBackdrop" class="xl:hidden fixed inset-0 bg-gray-900/40" style="display:none; z-index:9998"></div>

                <!-- ═══ Content ═══ -->
                <div class="flex-1 min-w-0">

                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <button type="button" id="monFilterBtn"
                            class="xl:hidden shrink-0 inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2.5 sm:py-2 rounded-lg border bg-white text-gray-700 border-gray-300 hover:border-amber-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5h18l-7 8v6l-4 2v-8L3 5z" />
                            </svg>
                            Filters
                            <span id="monFilterBadge"
                                class="hidden bg-amber-600 text-white rounded-full text-[10px] leading-none px-1.5 py-1"></span>
                        </button>

                        <div class="relative flex-1 min-w-0 sm:min-w-[180px] sm:max-w-sm">
                            <input id="monSearch" type="text" placeholder="Search control no., client, contact"
                                class="w-full pl-8 pr-8 py-2.5 sm:py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                            </svg>
                            <button type="button" id="monSearchClear"
                                class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 text-lg leading-none w-4 h-4">&times;</button>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto sm:ml-auto">
                            <button type="button" id="monClearAll"
                                class="hidden text-xs text-gray-500 hover:text-amber-700 font-medium px-2 py-1.5">Clear all</button>
                            <label class="mon-hint hidden sm:block" for="fSort">Sort by</label>
                            <select id="fSort" class="mon-field flex-1 sm:flex-none min-w-0 sm:!w-auto" title="Sort by">
                                <option value="updated">Recently updated</option>
                                <option value="newest">Newest filed</option>
                                <option value="oldest">Oldest filed</option>
                                <option value="stage_asc">Stage: New Customer → Execution</option>
                                <option value="stage_desc">Stage: Execution → New Customer</option>
                                <option value="stuck">Longest waiting</option>
                                <option value="client">Client (A–Z)</option>
                                <option value="control">Control no.</option>
                            </select>
                        </div>
                    </div>

                <!-- Active filter pills -->
                <div id="monPills" class="flex-wrap items-center gap-1.5 mb-3" style="display:none"></div>

                <!-- Table: header 40px + 5 rows x 55px, the rest scrolls -->
                <!-- Phone: cards (ang table ay para sa tablet/desktop) -->
                <div id="monCards" class="md:hidden space-y-2"></div>

                <div class="hidden md:block bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="mon-scroll overflow-auto" style="max-height: 316px;">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 z-10">
                                <tr class="h-10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Control No.</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Client</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Project</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Stage</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Current Status</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Waiting On</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Last Update</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Sales</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Designer</th>
                                    <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                        Date Filed</th>
                                    <th
                                        class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200 text-right">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody id="monTbody" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <p id="monCount" class="text-[11px] text-gray-400 mt-2.5"></p>

                <div id="monDetailGrid" class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6 scroll-mt-20">
                    <?php include ROOT_PATH . '/admin/ui-superad/page-2/monitoringcontain1.php'; ?>
                    <?php include ROOT_PATH . '/admin/ui-superad/page-2/monitoringcontain2.php'; ?>
                </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        const MON_AJAX_URL = <?= json_encode($monAjaxUrl) ?>;
        const MON_VIEW_URL = <?= json_encode($monViewUrl) ?>;
        const MON_VISIBLE_ROWS = 5;

        const MON_BADGE = {
            green: 'bg-green-50 text-green-700 ring-green-200',
            blue: 'bg-blue-50 text-blue-700 ring-blue-200',
            amber: 'bg-amber-50 text-amber-700 ring-amber-200',
            red: 'bg-red-50 text-red-700 ring-red-200'
        };
        const MON_DOT = {
            green: 'bg-green-500',
            blue: 'bg-blue-500',
            amber: 'bg-amber-500',
            red: 'bg-red-500'
        };

        // Grouping ng status sa modal
        const MON_GROUPS = [
            ['Contacting & site visit', ['CONTACTING CLIENT', 'WAITING FOR CLIENT', 'WAITING FOR MEASUREMENT', 'REVISIT NEEDED', 'SITE VISIT DONE']],
            ['2D & quotation', ['2D DESIGN', '2D & QUOTATION']],
            ['Initial review', ['INITIAL FOR APPROVAL', 'INITIAL REVISION', 'INITIAL APPROVED', 'WAITING FOR 3D', '3D FOR APPROVAL', '3D REVISION']],
            ['Customer', ['FOR CLIENT REVIEW', 'CUSTOMER REVISION', 'CUSTOMER APPROVED']],
            ['Final', ['FINAL IN PROGRESS', 'FINAL FOR APPROVAL', 'FINAL REVISION', 'FINAL APPROVED']],
            ['Contract', ['CONTRACT PREPARING', 'CONTRACT RETURNED', 'CONTRACT FOR SIGNING', 'CONTRACT SENT TO CLIENT', 'CLIENT SIGNED', 'CONTRACT SIGNED', 'NOTICE TO PROCEED']],
            ['Cutting list & BOM', ['CUTTING LIST UPLOADED', 'CUTTING LIST REJECTED', 'CUTTING LIST APPROVED', 'BOM SUBMITTED', 'BOM APPROVED', 'BOM RECEIVED']],
            ['Purchase request', ['PR SUBMITTED', 'PR RETURNED', 'PR APPROVED', 'PR REVIEWED', 'PR RECEIVED BY ACCOUNTING', 'PR RELEASED', 'PR PAID']],
            ['Delivery', ['TRANSMITTAL ISSUED', 'DELIVERED', 'DR CREATED', 'DR FOR APPROVAL', 'DR RETURNED', 'DR APPROVED']],
            ['Labor & timeline', ['FOREMAN ASSIGNED', 'LABOR QUOTE SUBMITTED', 'LABOR QUOTE RETURNED', 'LABOR QUOTE APPROVED', 'TIMELINE SET']],
            ['Progress billing', ['BILLING SUBMITTED', 'BILLING RETURNED', 'BILLING NOTED', 'BILLING APPROVED', 'FULLY BILLED']],
            ['Punch list & turnover', ['PUNCH LIST OPEN', 'PUNCH LIST CLOSED', 'TURNOVER SUBMITTED', 'TURNOVER RETURNED', 'TURNOVER SIGNED', 'PARTLY TURNED OVER', 'TURNED OVER']],
            ['Closed', ['NOT PROCEEDING']]
        ];

        // Quick filters (history-based, "any of")
        const MON_PRESETS = [
            { name: 'Had a revision', statuses: ['INITIAL REVISION', 'CUSTOMER REVISION', '3D REVISION', 'FINAL REVISION'] },
            { name: 'Reached customer review', statuses: ['FOR CLIENT REVIEW', 'CUSTOMER REVISION', 'CUSTOMER APPROVED'] },
            { name: 'Reached final', statuses: ['FINAL IN PROGRESS', 'FINAL FOR APPROVAL', 'FINAL REVISION', 'FINAL APPROVED'] },
            { name: 'Contract signed', statuses: ['CONTRACT SIGNED'] },
            { name: 'In production', statuses: ['CUTTING LIST UPLOADED', 'CUTTING LIST APPROVED', 'BOM SUBMITTED', 'BOM APPROVED', 'BOM RECEIVED'] },
            { name: 'Delivered', statuses: ['DELIVERED', 'DR CREATED'] },
            { name: 'In operations', statuses: ['DR APPROVED', 'FOREMAN ASSIGNED', 'LABOR QUOTE APPROVED', 'TIMELINE SET'] },
            { name: 'Billing', statuses: ['BILLING SUBMITTED', 'BILLING NOTED', 'BILLING APPROVED'] },
            { name: 'Had a return', statuses: ['DR RETURNED', 'LABOR QUOTE RETURNED', 'BILLING RETURNED', 'TURNOVER RETURNED'] },
            { name: 'Turned over', statuses: ['TURNED OVER'] }
        ];

        // Filter state
        const monF = {
            q: '',
            status: new Set(),
            status_match: 'any',
            current: '',
            status_from: '', status_to: '',
            project: '', sales: '', designer: '', client_status: '', mode: '',
            from: '', to: '',
            cstage: '', date_basis: 'filed', month: '', day: '',
            sort: 'updated'
        };
        // state key -> element id (lahat ng select/date inputs)
        const MON_FIELDS = {
            current: 'fCurrent', status_from: 'fSFrom', status_to: 'fSTo',
            project: 'fProject', sales: 'fSales', designer: 'fDesigner',
            client_status: 'fClient', mode: 'fMode',
            from: 'fFrom', to: 'fTo', sort: 'fSort',
            date_basis: 'fBasis', month: 'fMonth', day: 'fDay'
        };
        const MON_DEFAULTS = { sort: 'updated', date_basis: 'filed' };

        // Customer stages (galing sa backend: filter_options)
        let monStages = {
            new: 'New Customer', site: 'Site Measurement', design: '2D & Design', final: 'Final Design & Quotation',
            quotation: 'Quotation', contract: 'Contract', order: 'Project Execution'
        };
        let monFlows = {
            site_visit: ['new', 'site', 'design', 'final', 'contract', 'order'],
            ready_for_quotation: ['new', 'quotation', 'contract', 'order']
        };
        let monStaleDays = 7;
        let monMatOrder = 'Order Processing';   // huling stage ng Materials
        let monStageCounts = null;

        // "May bagong update" — huling nakita ng user ang bawat project (sa browser lang na ito)
        const MON_SEEN_KEY = 'nh_mon_seen_v1';
        let monSeen = {};
        let monSince = null;   // server time ng unang bisita (itatakda sa unang list kung wala pa)
        try {
            const raw = JSON.parse(localStorage.getItem(MON_SEEN_KEY) || 'null');
            if (raw && typeof raw === 'object') {
                monSeen = raw.ids || {};
                if (raw.since) monSince = raw.since;
            }
        } catch (e) { }
        function monSaveSeen() {
            try { localStorage.setItem(MON_SEEN_KEY, JSON.stringify({ since: monSince, ids: monSeen })); } catch (e) { }
        }
        function monIsNew(r) {
            if (!r.last_ts || monSince == null) return false;
            const seen = monSeen[r.id] ?? monSince;
            return r.last_ts > seen;
        }

        let monStatuses = {};       // label -> color
        let monLastCounts = null;
        let monSearchDebounce = null;
        let monSelectedId = null;
        let monLastRows = [];
        let monReq = 0;

        const $ = id => document.getElementById(id);

        function monEsc(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }
        function monVal(str) {
            return str && String(str).trim() !== '' && str !== '—'
                ? monEsc(str)
                : '<span class="text-gray-300">—</span>';
        }
        function monDate(value) {
            if (!value) return '<span class="text-gray-300">—</span>';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return monEsc(value);
            return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        function monPretty(label) {
            return label.toLowerCase().split(' ')
                .map(w => /^\d/.test(w) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1))
                .join(' ');
        }
        function monShortDate(v) {
            if (!v) return '';
            const dt = new Date(v + 'T00:00:00');
            return isNaN(dt.getTime()) ? v : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function monAgo(age, time) {
            if (age == null) return '<span class="text-gray-300">—</span>';
            const s = age;
            if (s < 60) return 'Just now';
            if (s < 3600) return Math.floor(s / 60) + 'm ago';
            if (s < 86400) return Math.floor(s / 3600) + 'h ago';
            const d = Math.floor(s / 86400);
            if (d === 1) return 'Yesterday';
            if (d < 30) return d + ' days ago';
            return monDate(time);
        }
        function monFullTime(v) {
            if (!v) return '';
            if (String(v).length <= 10) return monShortDate(v);
            const dt = new Date(String(v).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? v : dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }
        function monSegments(r) {
            if (!r.stage_total) return '';
            const step = r.stage_step || 0;
            let html = '';
            for (let i = 1; i <= r.stage_total; i++) {
                html += `<span class="${i < step || (r.done && i === step) ? 'on' : (i === step ? 'cur' : '')}"></span>`;
            }
            return `<div class="mon-seg ${r.done ? 'done' : ''}" title="Step ${step} of ${r.stage_total}">${html}</div>`;
        }
        function monMonthText(m) {
            if (!m) return '';
            const dt = new Date(m + '-01T00:00:00');
            return isNaN(dt.getTime()) ? m : dt.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        }
        function monRange(a, b) {
            if (a && b) return `${monShortDate(a)} – ${monShortDate(b)}`;
            if (a) return `from ${monShortDate(a)}`;
            return `until ${monShortDate(b)}`;
        }

        // Ilan ang active filters (hindi kasama ang search at sort)
        function monPanelFilterCount() {
            let n = monF.status.size ? 1 : 0;
            ['current', 'status_from', 'status_to', 'project', 'sales', 'designer', 'client_status', 'from', 'to']
                .forEach(k => { if (monF[k]) n++; });
            return n;
        }

        // ── Filter sidebar (drawer sa phone/tablet; laging bukas sa desktop) ──
        const monIsDesktop = () => window.matchMedia('(min-width: 1280px)').matches;
        function monOpenModal() {
            $('monModal').classList.add('open');
            $('monModalBackdrop').style.display = 'block';
            document.body.style.overflow = 'hidden';
            $('monModalClose').focus();
        }
        function monCloseModal() {
            if (!$('monModal').classList.contains('open')) return;
            $('monModal').classList.remove('open');
            $('monModalBackdrop').style.display = 'none';
            document.body.style.overflow = '';
            if (!monIsDesktop()) $('monFilterBtn').focus();
        }
        function monModalOpen() {
            return $('monModal').classList.contains('open');
        }
        window.addEventListener('resize', () => { if (monIsDesktop()) monCloseModal(); });

        // ── Phone card (kapareho ng laman ng table row) ──
        function monCardHtml(r) {
            const isNew = monIsNew(r);
            const active = r.id === monSelectedId;
            const daysTxt = r.done ? 'Completed' : (r.stage_days == null ? '' : (r.stage_days === 0 ? 'In this status today' : `${r.stage_days} ${r.stage_days === 1 ? 'day' : 'days'} in this status`));
            return `
            <article data-id="${r.id}" onclick="monRowClick(${r.id})"
                class="bg-white border ${active ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-200'} rounded-lg shadow-sm px-3 py-3 cursor-pointer transition">
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            ${isNew ? '<span class="mon-new-dot" title="Updated since you last checked"></span>' : ''}
                            <span class="notranslate font-mono text-[11px] font-semibold text-amber-700">${monEsc(r.control_no)}</span>
                            <span class="inline-block text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded-full ring-1 ${MON_BADGE[r.stage_color] || 'bg-gray-50 text-gray-600 ring-gray-200'}">${monEsc(r.stage)}</span>
                        </div>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 truncate capitalize">${monEsc(r.client_name)}</p>
                        <p class="text-[11px] text-gray-500 truncate">${monVal(r.contact_number)} <span class="text-gray-300 mx-1">·</span><span class="capitalize">${monVal(r.project_type)}</span> <span class="text-gray-300 mx-1">·</span><span class="${r.material ? 'text-sky-600' : 'text-gray-400'} font-semibold">${r.material ? 'Materials' : 'Modular'}</span></p>
                    </div>
                    <a href="${MON_VIEW_URL}?id=${r.id}" onclick="event.stopPropagation()"
                        class="shrink-0 inline-flex items-center text-[11px] font-medium px-3 py-1.5 rounded-md border border-amber-200 bg-amber-50 text-amber-700">View</a>
                </div>
                <div class="mt-2">
                    <div class="text-[11.5px] font-semibold ${r.stage_key === 'closed' ? 'text-red-600' : 'text-gray-800'}">${monEsc(r.stage_name)}${r.stage_sub ? `<span class="font-normal text-gray-500"> · ${monEsc(r.stage_sub)}</span>` : ''}</div>
                    <div class="mt-1">${monSegments(r)}</div>
                </div>
                <dl class="mt-2 grid grid-cols-3 gap-2 rounded-md bg-gray-50 border border-gray-100 px-2.5 py-2 text-[11px]">
                    <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Waiting on</dt><dd class="text-gray-700 truncate">${r.waiting_on ? monEsc(r.waiting_on) : '<span class="text-gray-300">—</span>'}</dd></div>
                    <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Sales</dt><dd class="text-gray-700 truncate capitalize">${monVal(r.sales_name)}</dd></div>
                    <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Designer</dt><dd class="text-gray-700 truncate capitalize">${monVal(r.designer_name)}</dd></div>
                </dl>
                <p class="mt-1.5 text-[10.5px] ${r.stuck ? 'text-red-600 font-semibold' : 'text-gray-400'}">
                    <span class="${isNew ? 'font-semibold text-gray-700' : ''}">${monAgo(r.last_age, r.last_time)}</span>${daysTxt ? ` · ${r.stuck ? '⚠ ' : ''}${monEsc(daysTxt)}` : ''} · Filed ${monDate(r.created_at)}
                </p>
            </article>`;
        }

        // ── Table ──
        function monRender(rows) {
            const tbody = $('monTbody');
            if (!rows.length) {
                const msg = (monPanelFilterCount() || monF.q || monF.cstage || monF.month || monF.day || monF.mode) ? 'No inquiries match the current filters.' : 'No inquiries yet.';
                tbody.innerHTML = `<tr class="h-11"><td colspan="11" class="px-5 text-center text-gray-400">${msg}</td></tr>`;
                $('monCards').innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-lg px-4 py-10 text-center text-xs text-gray-400">${msg}</div>`;
                return;
            }
            monLastRows = rows;
            $('monCards').innerHTML = rows.map(monCardHtml).join('');
            tbody.innerHTML = rows.map(r => {
                const isNew = monIsNew(r);
                const daysTxt = r.done ? 'Completed' : (r.stage_days == null ? '' : (r.stage_days === 0 ? 'In this status today' : `${r.stage_days} ${r.stage_days === 1 ? 'day' : 'days'} in this status`));
                return `
            <tr class="h-[55px] cursor-pointer transition-colors ${r.id === monSelectedId ? 'bg-amber-50' : 'hover:bg-amber-50/40'}"
                data-id="${r.id}" onclick="monRowClick(${r.id})">
                <td class="notranslate px-5 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        ${isNew ? '<span class="mon-new-dot" title="Updated since you last checked"></span>' : ''}
                        <span class="font-mono text-xs font-semibold text-amber-700">${monEsc(r.control_no)}</span>
                    </div>
                </td>
                <td class="px-5">
                    <div class="max-w-[120px] truncate text-gray-800 font-medium" title="${monEsc(r.client_name)}">${monEsc(r.client_name)}</div>
                    <div class="text-[10.5px] text-gray-400 whitespace-nowrap">${monVal(r.contact_number)}</div>
                </td>
                <td class="px-5 text-gray-600 whitespace-nowrap">
                    <div class="capitalize">${monVal(r.project_type)}</div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide ${r.material ? 'text-sky-600' : 'text-gray-400'}">${r.material ? 'Materials' : 'Modular'}</div>
                </td>
                <td class="px-5 whitespace-nowrap">
                    <div class="text-[11.5px] font-semibold ${r.stage_key === 'closed' ? 'text-red-600' : 'text-gray-800'}">${monEsc(r.stage_name)}${r.stage_sub ? `<span class="font-normal text-gray-500"> · ${monEsc(r.stage_sub)}</span>` : ''}</div>
                    <div class="mt-1">${monSegments(r)}</div>
                </td>
                <td class="px-5 whitespace-nowrap">
                    <span class="inline-block text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded-full ring-1 ${MON_BADGE[r.stage_color] || 'bg-gray-50 text-gray-600 ring-gray-200'}">${monEsc(r.stage)}</span>
                </td>
                <td class="px-5 whitespace-nowrap text-gray-700">${r.waiting_on ? monEsc(r.waiting_on) : '<span class="text-gray-300">—</span>'}</td>
                <td class="px-5 whitespace-nowrap" title="${monEsc((r.last_label ? monPretty(r.last_label) + ' · ' : '') + monFullTime(r.last_time))}">
                    <div class="text-gray-700 ${isNew ? 'font-semibold' : ''}">${monAgo(r.last_age, r.last_time)}</div>
                    <div class="text-[10.5px] ${r.stuck ? 'text-red-600 font-semibold' : 'text-gray-400'}">${r.stuck ? '⚠ ' : ''}${monEsc(daysTxt)}</div>
                </td>
                <td class="px-5 text-gray-600 whitespace-nowrap capitalize">${monVal(r.sales_name)}</td>
                <td class="px-5 text-gray-600 whitespace-nowrap capitalize">${monVal(r.designer_name)}</td>
                <td class="px-5 text-gray-500 whitespace-nowrap">${monDate(r.created_at)}</td>
                <td class="px-5 text-right whitespace-nowrap">
                    <a href="${MON_VIEW_URL}?id=${r.id}" onclick="event.stopPropagation()"
                        class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-md border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white hover:border-amber-600 transition-colors">
                        View
                    </a>
                </td>
            </tr>`;
            }).join('');
        }

        // Tuldok sa section na may naka-on na filter (kasi naka-close ang mga section)
        function monMarkSections() {
            const on = {
                type: !!monF.mode,
                stage: !!monF.cstage && !['today', 'stuck'].includes(monF.cstage),
                attention: ['today', 'stuck'].includes(monF.cstage),
                date: !!(monF.month || monF.day || monF.from || monF.to),
                details: !!(monF.project || monF.sales || monF.designer || monF.client_status),
                quick: MON_PRESETS.some(monPresetActive),
                current: !!(monF.current || monF.status_from || monF.status_to),
                history: monF.status.size > 0
            };
            document.querySelectorAll('details.mon-sec[data-sec]').forEach(d => {
                d.dataset.active = on[d.dataset.sec] ? '1' : '';
            });
        }

        // ── Customer stage tabs ──
        function monRenderStages() {
            const counts = monStageCounts || {};
            const flow = monF.mode ? monFlows[monF.mode] : ['new', 'site', 'design', 'final', 'quotation', 'contract', 'order'];
            const tab = (key, label, n, num) => `
                <button type="button" class="mon-stage-tab" data-stage="${key}" aria-pressed="${monF.cstage === key}">
                    <span class="mon-radio"></span>
                    <span class="mon-lbl">${num ? `<span class="mon-num">${num}.</span>` : ''}${monEsc(label)}</span>
                    <span class="mon-n">${n ?? 0}</span>
                </button>`;
            let html = tab('', 'All', counts.all);
            flow.forEach((k, i) => {
                let label = monStages[k];
                if (!monF.mode && k === 'quotation') label = 'Quotation (Materials)';
                if (k === 'order') label = monF.mode === 'ready_for_quotation' ? monMatOrder
                    : (!monF.mode ? `${monStages.order} / ${monMatOrder}` : monStages.order);
                html += tab(k, label, counts[k], monF.mode ? i + 1 : '');
            });
            if (counts.closed) html += tab('closed', 'Closed', counts.closed);
            $('monStageTabs').innerHTML = html;

            $('monFlowHint').textContent = monF.mode === 'ready_for_quotation'
                ? `Materials: New Customer → Quotation → Contract → ${monMatOrder}`
                : monF.mode === 'site_visit'
                    ? 'Modular: New Customer → Site Measurement → 2D & Design → Final Design & Quotation → Contract → Project Execution'
                    : 'Choose a type to see its step-by-step flow';

            const chip = (key, icon, label, n, tone) => {
                const on = monF.cstage === key;
                const tones = {
                    amber: on ? 'bg-amber-600 border-amber-600 text-white' : 'bg-amber-50 border-amber-200 text-amber-800 hover:border-amber-400',
                    red: on ? 'bg-red-600 border-red-600 text-white' : 'bg-red-50 border-red-200 text-red-700 hover:border-red-400'
                };
                return `<button type="button" data-stage="${key}" aria-pressed="${on}"
                    class="w-full flex items-center gap-2 text-[11.5px] font-semibold px-2.5 py-2 rounded-lg border transition-colors ${tones[tone]}">
                    <i class="fa-solid ${icon} w-3.5 text-center"></i><span class="flex-1 text-left">${monEsc(label)}</span><span class="font-bold">${n ?? 0}</span></button>`;
            };
            $('monAttention').innerHTML =
                chip('today', 'fa-bolt', 'Updated today', counts.today, 'amber') +
                chip('stuck', 'fa-triangle-exclamation', `No movement ${monStaleDays}+ days`, counts.stuck, 'red');

            document.querySelectorAll('#monType button').forEach(b => {
                const on = b.dataset.type === monF.mode;
                b.classList.toggle('bg-gray-800', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('bg-white', !on);
                b.classList.toggle('text-gray-600', !on);
            });
        }

        function monRowClick(id) {
            monSelectedId = id;
            // markahan na nakita na ang update
            const r = monLastRows.find(x => x.id === id);
            if (r && r.last_ts) {
                monSeen[id] = r.last_ts;
                monSaveSeen();
                const dot = document.querySelector(`#monTbody tr[data-id="${id}"] .mon-new-dot`);
                if (dot) dot.remove();
            }
            document.querySelectorAll('#monTbody tr[data-id]').forEach(tr => {
                const active = Number(tr.dataset.id) === id;
                tr.classList.toggle('bg-amber-50', active);
                tr.classList.toggle('hover:bg-amber-50/40', !active);
            });
            document.querySelectorAll('#monCards [data-id]').forEach(c => {
                const active = Number(c.dataset.id) === id;
                c.classList.toggle('border-amber-500', active);
                c.classList.toggle('ring-2', active);
                c.classList.toggle('ring-amber-100', active);
                c.classList.toggle('border-gray-200', !active);
                if (active) c.querySelector('.mon-new-dot')?.remove();
            });
            if (window.monBox1Show) window.monBox1Show(id);
            if (window.monBox2Show) window.monBox2Show(id);
            // Phone: dalhin sa detalye sa ibaba (nasa ilalim ng listahan)
            if (!window.matchMedia('(min-width: 768px)').matches) {
                $('monDetailGrid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // ── Status checklist (grouped) ──
        function monBuildStatusList() {
            const used = new Set();
            const groups = MON_GROUPS.map(([title, labels]) => [title, labels.filter(l => l in monStatuses)]);
            groups.forEach(([, ls]) => ls.forEach(l => used.add(l)));
            const rest = Object.keys(monStatuses).filter(l => !used.has(l));
            if (rest.length) groups.push(['Other', rest]);

            $('monStatusList').innerHTML = groups.filter(([, ls]) => ls.length).map(([title, ls]) => `
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">${monEsc(title)}</p>
                    ${ls.map(l => `
                    <label class="flex items-center gap-2 py-1 px-1.5 -mx-1.5 rounded-md hover:bg-gray-50 cursor-pointer text-xs text-gray-700" data-row="${monEsc(l)}">
                        <input type="checkbox" value="${monEsc(l)}" class="accent-amber-600">
                        <span class="w-1.5 h-1.5 rounded-full ${MON_DOT[monStatuses[l]] || 'bg-gray-400'}"></span>
                        <span class="flex-1">${monEsc(monPretty(l))}</span>
                        <span data-count class="text-[11px] text-gray-400"></span>
                    </label>`).join('')}
                </div>
            `).join('');
            monUpdateCounts(monLastCounts);
        }

        function monUpdateCounts(counts) {
            monLastCounts = counts;
            document.querySelectorAll('#monStatusList label[data-row]').forEach(row => {
                const label = row.dataset.row;
                const n = counts && counts[label] != null ? counts[label] : '';
                row.querySelector('[data-count]').textContent = n;
                const cb = row.querySelector('input');
                row.style.opacity = (counts && n === 0 && !cb.checked) ? '0.45' : '';
            });
        }

        // ── Quick filters ──
        function monPresetActive(p) {
            return monF.status_match === 'any'
                && monF.status.size === p.statuses.length
                && p.statuses.every(s => monF.status.has(s));
        }
        function monRenderPresets() {
            $('monPresets').innerHTML = MON_PRESETS.map((p, i) => {
                const on = monPresetActive(p);
                return `<button type="button" data-preset="${i}"
                    class="text-[11px] font-medium px-2.5 py-1 rounded-full border transition-colors
                    ${on ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-amber-400 hover:text-amber-700'}">
                    ${monEsc(p.name)}</button>`;
            }).join('');
        }

        // ── Active pills ──
        function monRenderPills() {
            const pills = [];
            const selText = id => { const s = $(id); return s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : ''; };

            if (monF.status.size) {
                const lead = monF.status.size > 1 ? `Passed through ${monF.status_match === 'all' ? 'all of' : 'any of'}:` : 'Passed through:';
                pills.push({ lead });
                monF.status.forEach(l => pills.push({ k: 'status', v: l, text: monPretty(l), color: monStatuses[l] }));
            }
            if (monF.current) pills.push({ k: 'current', text: 'Current: ' + monPretty(monF.current) });
            if (monF.status_from || monF.status_to) pills.push({ k: 'status_range', text: 'Status date ' + monRange(monF.status_from, monF.status_to) });
            if (monF.project) pills.push({ k: 'project', text: 'Project: ' + selText('fProject') });
            if (monF.sales) pills.push({ k: 'sales', text: 'Sales: ' + selText('fSales') });
            if (monF.designer) pills.push({ k: 'designer', text: 'Designer: ' + selText('fDesigner') });
            if (monF.client_status) pills.push({ k: 'client_status', text: 'Client: ' + selText('fClient') });
            if (monF.month || monF.day) pills.push({ k: 'date_pick', text: (monF.date_basis === 'updated' ? 'Updated ' : 'Filed ') + (monF.day ? monShortDate(monF.day) : monMonthText(monF.month)) });
            if (monF.from || monF.to) pills.push({ k: 'filed_range', text: (monF.date_basis === 'updated' ? 'Updated ' : 'Filed ') + monRange(monF.from, monF.to) });

            const box = $('monPills');
            if (!pills.length) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }
            box.style.display = 'flex';
            box.innerHTML = pills.map(p => {
                if (p.lead) return `<span class="mon-hint mr-0.5">${monEsc(p.lead)}</span>`;
                const dot = p.color ? `<span class="w-1.5 h-1.5 rounded-full ${MON_DOT[p.color] || 'bg-gray-400'}"></span>` : '';
                return `<span class="inline-flex items-center gap-1.5 text-[11px] bg-amber-50 text-amber-800 border border-amber-200 rounded-full pl-2.5 pr-1 py-0.5">
                    ${dot}${monEsc(p.text)}
                    <button type="button" data-remove="${p.k}" data-val="${monEsc(p.v ?? '')}"
                        class="w-4 h-4 leading-none text-amber-500 hover:text-amber-800 hover:bg-amber-100 rounded-full" aria-label="Remove">&times;</button>
                </span>`;
            }).join('');
        }

        // ── Sync UI galing sa state ──
        function monSyncDateLimits() {
            $('fTo').min = monF.from || '';
            $('fFrom').max = monF.to || '';
            $('fSTo').min = monF.status_from || '';
            $('fSFrom').max = monF.status_to || '';
        }

        function monSyncUI() {
            Object.entries(MON_FIELDS).forEach(([k, id]) => { $(id).value = monF[k]; });
            document.querySelectorAll('#monStatusList input[type=checkbox]').forEach(cb => {
                cb.checked = monF.status.has(cb.value);
            });
            document.querySelectorAll('#monMatch button').forEach(b => {
                const on = b.dataset.match === monF.status_match;
                b.classList.toggle('bg-amber-600', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('bg-white', !on);
                b.classList.toggle('text-gray-600', !on);
            });
            monSyncDateLimits();
            monRenderPills();
            monRenderPresets();
            monRenderStages();
            monMarkSections();
            $('monDateClear').classList.toggle('hidden', !monF.month && !monF.day);

            const n = monPanelFilterCount() + (monF.cstage ? 1 : 0) + (monF.mode ? 1 : 0) + ((monF.month || monF.day) ? 1 : 0);
            const badge = $('monFilterBadge');
            badge.textContent = n;
            badge.classList.toggle('hidden', n === 0);
            $('monClearAll').classList.toggle('hidden', n === 0 && !monF.q && !monF.cstage && !monF.mode && !monF.month && !monF.day);

            const btn = $('monFilterBtn');
            btn.classList.toggle('border-amber-600', n > 0);
            btn.classList.toggle('bg-amber-50', n > 0);
        }

        function monRefresh() {
            monSyncUI();
            monFetchList();
        }

        // ── Fetch ──
        function monQueryString() {
            const p = new URLSearchParams({ action: 'list', q: monF.q, sort: monF.sort });
            if (monF.status.size) {
                p.set('status', [...monF.status].join(','));
                p.set('status_match', monF.status_match);
            }
            ['current', 'status_from', 'status_to', 'project', 'sales', 'designer', 'client_status', 'mode', 'from', 'to', 'cstage', 'month', 'day']
                .forEach(k => { if (monF[k]) p.set(k, monF[k]); });
            if (monF.month || monF.day || monF.from || monF.to) p.set('date_basis', monF.date_basis);
            return p.toString();
        }

        async function monFetchList() {
            const countEl = $('monCount');
            const token = ++monReq;
            try {
                const res = await fetch(`${MON_AJAX_URL}?${monQueryString()}`);
                const data = await res.json();
                if (token !== monReq) return; // may mas bagong request na
                if (!data.success) {
                    countEl.textContent = data.message || 'Failed to load.';
                    return;
                }
                monStageCounts = data.stage_counts || null;
                if (monSince == null && data.now) { monSince = data.now; monSaveSeen(); }
                monRender(data.rows);
                monUpdateCounts(data.status_counts);
                monRenderStages();

                const n = data.count;
                let text = `${n} ${n === 1 ? 'inquiry' : 'inquiries'} found`;
                if (n !== data.total) text += ` (of ${data.total} matching the other filters)`;
                if (n > MON_VISIBLE_ROWS && window.matchMedia('(min-width: 768px)').matches) text += ` · Scroll to see more`;
                countEl.textContent = text;

                // Live count sa modal button
                $('monModalDone').textContent = `Show ${n} ${n === 1 ? 'result' : 'results'}`;
            } catch (e) {
                if (token !== monReq) return;
                console.error('monFetchList:', e);
                countEl.textContent = 'Connection error while fetching inquiries.';
            }
        }

        async function monLoadOptions() {
            try {
                const res = await fetch(`${MON_AJAX_URL}?action=filter_options`);
                const data = await res.json();
                if (!data.success) return;

                monStatuses = data.statuses || {};
                if (data.stages) monStages = data.stages;
                if (data.flows) monFlows = data.flows;
                if (data.stale_days) monStaleDays = data.stale_days;
                if (data.mat_order) monMatOrder = data.mat_order;
                Object.keys(monStatuses).forEach(l => $('fCurrent').add(new Option(monPretty(l), l)));
                data.projects.forEach(p => $('fProject').add(new Option(p.charAt(0).toUpperCase() + p.slice(1), p)));
                data.sales.forEach(s => $('fSales').add(new Option(s.name, s.id)));
                data.designers.forEach(d => $('fDesigner').add(new Option(d.name, d.id)));

                monBuildStatusList();
                monSyncUI();
            } catch (e) {
                console.error('monLoadOptions:', e);
            }
        }

        function monResetAll() {
            monF.q = '';
            monF.status.clear();
            monF.status_match = 'any';
            monF.cstage = '';
            Object.keys(MON_FIELDS).forEach(k => { monF[k] = MON_DEFAULTS[k] ?? ''; });
            $('monSearch').value = '';
            $('monSearchClear').classList.add('hidden');
            monRefresh();
        }

        // ── Events ──
        const monInput = $('monSearch');
        const monClear = $('monSearchClear');

        monInput.addEventListener('input', function () {
            monClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(monSearchDebounce);
            const v = this.value;
            monSearchDebounce = setTimeout(() => { monF.q = v.trim(); monRefresh(); }, 350);
        });

        monClear.addEventListener('click', () => {
            monInput.value = '';
            monClear.classList.add('hidden');
            monF.q = '';
            monRefresh();
            monInput.focus();
        });

        // Modal open / close
        $('monFilterBtn').addEventListener('click', monOpenModal);
        $('monModalClose').addEventListener('click', monCloseModal);
        $('monModalDone').addEventListener('click', monCloseModal);
        $('monModalBackdrop').addEventListener('click', monCloseModal);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && monModalOpen()) monCloseModal();
        });

        $('monPanelClear').addEventListener('click', monResetAll);
        $('monClearAll').addEventListener('click', monResetAll);

        // Selects at date inputs
        Object.entries(MON_FIELDS).forEach(([key, id]) => {
            $(id).addEventListener('change', function () {
                monF[key] = this.value;
                // month o day lang ang isa sa kanila
                if (key === 'month' && this.value) monF.day = '';
                if (key === 'day' && this.value) monF.month = '';
                if (key === 'date_basis' && !monF.month && !monF.day && !monF.from && !monF.to) { monSyncUI(); return; }
                // range o month/day — isa lang ang aktibo
                if ((key === 'from' || key === 'to') && this.value) { monF.month = ''; monF.day = ''; }
                if ((key === 'month' || key === 'day') && this.value) { monF.from = ''; monF.to = ''; }
                monRefresh();
            });
        });

        // Customer stage tabs + attention chips
        ['monStageTabs', 'monAttention'].forEach(id => $(id).addEventListener('click', e => {
            const b = e.target.closest('button[data-stage]');
            if (!b) return;
            const k = b.dataset.stage;
            monF.cstage = (monF.cstage === k && k !== '') ? '' : k;
            if ((k === 'stuck') && monF.sort === 'updated') monF.sort = 'stuck';
            monRefresh();
        }));

        // Modular / Materials
        $('monType').addEventListener('click', e => {
            const b = e.target.closest('button[data-type]');
            if (!b) return;
            monF.mode = b.dataset.type;
            // stage na wala sa flow ng napiling type → balik sa All
            if (monF.mode && monF.cstage && !['stuck', 'today', 'closed'].includes(monF.cstage)
                && !monFlows[monF.mode].includes(monF.cstage)) monF.cstage = '';
            monRefresh();
        });

        // Today / This month
        document.querySelectorAll('.mon-qdate').forEach(b => b.addEventListener('click', () => {
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            if (b.dataset.qdate === 'today') {
                monF.day = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
                monF.month = '';
            } else {
                monF.month = `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
                monF.day = '';
            }
            monF.from = ''; monF.to = '';   // isa lang ang aktibong date filter
            monRefresh();
        }));
        $('monDateClear').addEventListener('click', () => { monF.month = ''; monF.day = ''; monRefresh(); });

        // Status checkboxes
        $('monStatusList').addEventListener('change', e => {
            const cb = e.target.closest('input[type=checkbox]');
            if (!cb) return;
            cb.checked ? monF.status.add(cb.value) : monF.status.delete(cb.value);
            monRefresh();
        });

        // Any / All
        $('monMatch').addEventListener('click', e => {
            const b = e.target.closest('button[data-match]');
            if (!b) return;
            monF.status_match = b.dataset.match;
            monRefresh();
        });

        // Quick filters
        $('monPresets').addEventListener('click', e => {
            const b = e.target.closest('button[data-preset]');
            if (!b) return;
            const p = MON_PRESETS[Number(b.dataset.preset)];
            if (monPresetActive(p)) {
                monF.status.clear();
            } else {
                monF.status = new Set(p.statuses);
                monF.status_match = 'any';
            }
            monRefresh();
        });

        // Remove pill
        $('monPills').addEventListener('click', e => {
            const b = e.target.closest('button[data-remove]');
            if (!b) return;
            const k = b.dataset.remove;
            if (k === 'status') monF.status.delete(b.dataset.val);
            else if (k === 'status_range') { monF.status_from = ''; monF.status_to = ''; }
            else if (k === 'filed_range') { monF.from = ''; monF.to = ''; }
            else if (k === 'date_pick') { monF.month = ''; monF.day = ''; }
            else monF[k] = '';
            monRefresh();
        });

        monSyncUI();
        monLoadOptions();
        monFetchList();

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Anumang galaw ng CRM / production / operations → kunin ulit ang listahan at ang napiling inquiry.
        // Hindi kinukuha habang nakatago ang tab; hahabol pagbalik.
        (function monHookRealtime() {
            const TYPES = /^(crm|contract|bom|pr_|po_|transmittal|pc_|dr_|labor_|billing_|turnover_|ewood|cutting)/i;
            let t = null, missed = false;
            const sync = () => {
                if (document.hidden) { missed = true; return; }
                missed = false;
                monFetchList();
                if (monSelectedId) {
                    if (window.monBox1Show) window.monBox1Show(monSelectedId);
                    if (window.monBox2Show) window.monBox2Show(monSelectedId);
                }
            };
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || monHookRealtime.done) return;
                monHookRealtime.done = true;
                window.NH_RT.on('*', 'changed', payload => {
                    const type = String(payload?.type || '');
                    if (type && !TYPES.test(type)) return;
                    clearTimeout(t);
                    t = setTimeout(sync, 600);
                });
            };
            hook();
            if (!monHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
            document.addEventListener('visibilitychange', () => { if (!document.hidden && missed) sync(); });
        })();
    </script>

</body>

</html>