<?php
// admin/requestcentral/requests-view.php
// Included sa bawat department main page — hindi standalone

?>

<!-- ═══ HEADER ═══ -->
<div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
        </div>
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-800 leading-tight">Budget Requests</h1>
            <p class="text-sm text-gray-500">Requests sent to you for approval</p>
        </div>
    </div>
    <div class="flex items-center gap-2 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5 shadow-sm">
        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
        <span id="last-updated">Live</span>
    </div>
</div>

<!-- ═══ STAT CARDS (buwan na nakabukas) ═══ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-layer-group"></i></div>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">This month</p>
            <p id="stat-total" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
        </div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
        <div class="w-10 h-10 rounded-lg bg-yellow-50 text-yellow-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">Pending</p>
            <p id="stat-pending" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
        </div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
        <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-check"></i></div>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">Approved</p>
            <p id="stat-approved" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
        </div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
        <div class="w-10 h-10 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">Rejected</p>
            <p id="stat-rejected" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
        </div>
    </div>
</div>

<!-- ═══ CALENDAR CARD ═══ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-5">
    <!-- Toolbar -->
    <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <button onclick="prevMonth()" title="Previous month"
                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </button>
            <span id="cal-label" class="text-base font-bold text-gray-800 min-w-[140px] text-center"></span>
            <button onclick="nextMonth()" title="Next month"
                class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </button>
            <button onclick="goToday()"
                class="ml-1 h-9 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 hover:bg-amber-100 px-3 rounded-lg transition">
                Today
            </button>
        </div>

        <div class="relative" id="dates-dropdown-wrap">
            <button onclick="toggleDatesDropdown(event)"
                class="h-9 flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 px-3 rounded-lg transition">
                <i class="fa-solid fa-calendar-check text-amber-600"></i>
                <span class="hidden sm:inline">Jump to date</span>
                <span class="sm:hidden">Dates</span>
                <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
            </button>
            <div id="dates-dropdown"
                class="hidden absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Dates with requests</p>
                    <span id="dates-dropdown-count" class="text-[10px] text-gray-400"></span>
                </div>
                <div class="px-3 py-2 border-b border-gray-100">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                        <input type="text" id="dates-search" placeholder="Search date..." oninput="filterDatesDropdown()"
                            class="w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                </div>
                <ul id="dates-list" class="max-h-64 overflow-y-auto py-1 scrollbar-thin"></ul>
            </div>
        </div>
    </div>

    <!-- Weekday header -->
    <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-100">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
            <div class="py-2 text-center text-[10px] sm:text-[11px] font-bold uppercase tracking-wide <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : 'text-gray-500') ?>">
                <span class="sm:hidden"><?= substr($d, 0, 1) ?></span><span class="hidden sm:inline"><?= $d ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="calendar-grid" class="grid grid-cols-7"></div>

    <!-- Legend -->
    <div class="flex items-center gap-4 flex-wrap px-4 py-2.5 border-t border-gray-100 text-[11px] text-gray-500">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>Pending</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Approved</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>Rejected</span>
        <span class="ml-auto hidden sm:inline text-gray-400">Click a day to see its requests</span>
    </div>
</div>

<!-- ═══ DAY PANEL ═══ -->
<div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-gray-100">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-regular fa-calendar"></i>
            </div>
            <div class="min-w-0">
                <p id="day-panel-title" class="text-sm font-bold text-gray-800 truncate"></p>
                <p id="day-panel-sub" class="text-[11px] text-gray-500 truncate"></p>
            </div>
        </div>
        <button onclick="closeDayPanel()" title="Close"
            class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Filter chips -->
    <div id="day-filter" class="flex items-center gap-2 flex-wrap px-4 sm:px-5 py-2.5 border-b border-gray-100 bg-gray-50/60"></div>

    <!-- Card rows (desktop + mobile) -->
    <div id="day-requests-cards" class="max-h-[520px] overflow-y-auto scrollbar-thin divide-y divide-gray-100"></div>
</div>

<!-- ═══════════════════════════════════════════════════════
     VIEW REQUEST MODAL
     ═══════════════════════════════════════════════════════ -->
<div id="view-modal"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8 overflow-y-auto">

    <!-- ── DESKTOP layout (md+) ── -->
    <div class="hidden md:block bg-white w-full max-w-5xl rounded-sm shadow-xl border border-gray-300 my-auto">
        <!-- Header -->
        <div class="grid grid-cols-[1fr_auto] border-b-2 border-gray-800">
            <div class="flex items-center gap-4 px-3 py-3 border-r-2 border-gray-800">
                <div class="w-14 h-14 shrink-0">
                    <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div class="w-px h-12 bg-gray-400"></div>
                <div class="flex-1">
                    <h1 class="font-bold text-sm uppercase tracking-wide leading-tight">Noblehome Construction
                        Corporation</h1>
                    <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                        1181 MC Premiere Bldg., EDSA Bldg., EDSA Balintawak Quezon City<br>
                        noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544
                    </p>
                </div>
            </div>
            <div class="flex flex-col">
                <div class="flex items-center justify-between px-4 py-2 border-b-2 border-gray-800 gap-4">
                    <h2 class="font-bold text-sm uppercase tracking-widest whitespace-nowrap">Budget Request Form</h2>
                    <div class="flex items-center gap-2 shrink-0">
                        <div id="view-download-btn" class="flex items-center gap-2"></div>
                        <button onclick="closeViewModal()"
                            class="text-gray-400 hover:text-red-500 transition-colors p-1">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div class="flex flex-row flex-1 text-[10px]">
                    <div class="flex flex-col border-r-2 border-gray-800 flex-1">
                        <span
                            class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Control
                            No.</span>
                        <p id="view-control-no"
                            class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 min-w-[180px]"></p>
                    </div>
                    <div class="flex flex-col flex-1">
                        <span
                            class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Date:</span>
                        <p id="view-date"
                            class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 min-w-[150px]"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requestor + Purpose + Category -->
        <div class="grid grid-cols-3 border-b-2 border-gray-800">
            <div class="flex items-center gap-2 px-6 py-3 border-r-2 border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Requestor
                    Name:</span>
                <p id="view-requestor" class="text-sm text-gray-800"></p>
            </div>
            <div class="flex items-center gap-2 px-6 py-3 border-r-2 border-gray-800">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Purpose of
                    Request:</span>
                <p id="view-purpose" class="text-sm text-gray-800"></p>
            </div>
            <div class="flex items-center gap-2 px-6 py-3">
                <span
                    class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Category:</span>
                <p id="view-category" class="text-sm text-gray-800"></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto max-h-[220px] overflow-y-auto">
            <table class="w-full text-sm border-collapse">
                <thead class="sticky top-0 z-1">
                    <tr class="bg-orange-500 text-white text-[11px] font-bold uppercase tracking-wider">
                        <th class="w-10 px-3 py-2 border-r border-orange-400 text-center">No.</th>
                        <th class="px-4 py-2 border-r border-orange-400 text-left">Items / Description</th>
                        <th class="px-4 py-2 border-r border-orange-400 text-left">Purpose</th>
                        <th class="w-24 px-4 py-2 border-r border-orange-400 text-center">Quantity</th>
                        <th class="w-28 px-4 py-2 border-r border-orange-400 text-center">Unit Price</th>
                        <th class="w-28 px-4 py-2 border-r border-orange-400 text-center">Amount</th>
                        <th class="px-4 py-2 text-left">Notes</th>
                    </tr>
                </thead>
                <tbody id="view-items-tbody"></tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-800 bg-gray-50">
                        <td colspan="5"
                            class="px-4 py-2 font-bold text-xs uppercase tracking-widest text-right border-r border-gray-300">
                            Total:</td>
                        <td id="view-total" class="px-4 py-2 font-bold font-mono text-right"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Attachments + Reject comment -->
        <div class="flex flex-col px-6 py-4 border-t-2 border-gray-800 gap-2">
            <div id="view-attachments" class="hidden">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-600 mb-2">
                    <i class="fa-solid fa-paperclip mr-1"></i> Attachments
                </p>
                <div id="view-attachments-grid" class="flex flex-wrap gap-2"></div>
            </div>
            <div id="view-reject-comment"></div>
        </div>

        <!-- Signatures -->
        <div class="grid grid-cols-2">
            <div class="px-5">
                <div class="flex items-center gap-2 mb-4">
                    <i class="fa-regular fa-circle-user text-gray-400 text-lg"></i>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Approved By:</span>
                    <div id="view-status-badge"></div>
                </div>
                <div id="view-approved-by" class="text-center"></div>
                <div class="border-b-2 border-gray-400 mb-1"></div>
                <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">Head</p>
            </div>
            <div class="px-8 py-1">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fa-regular fa-circle-user text-gray-400 text-lg"></i>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Received By:</span>
                </div>
                <div id="view-received-by" class="text-center"></div>
                <div id="view-receive-btn"></div>
                <div class="border-b-2 border-gray-400 mb-1"></div>
                <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">&nbsp;</p>
            </div>
        </div>
    </div>
    <!-- end desktop -->

    <!-- ── MOBILE layout (below md) ── -->
    <div class="md:hidden bg-white w-full rounded-2xl shadow-xl my-auto overflow-hidden">

        <!-- Mobile Header -->
        <div class="bg-orange-500 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo"
                    class="w-8 h-8 object-contain bg-white rounded-lg p-0.5">
                <div>
                    <p class="text-white font-bold text-xs uppercase leading-tight">Budget Request Form</p>
                    <p id="m-control-no" class="text-orange-100 text-[9px] font-mono tracking-wider"></p>
                </div>
            </div>
            <button onclick="closeViewModal()" class="text-orange-200 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Date + Category -->
        <div class="grid grid-cols-2 border-b border-gray-100">
            <div class="px-4 py-2.5 border-r border-gray-100">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Date</p>
                <p id="m-date" class="font-mono text-xs text-gray-700"></p>
            </div>
            <div class="px-4 py-2.5">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Category</p>
                <div id="m-category"></div>
            </div>
        </div>

        <!-- Requestor + Purpose -->
        <div class="px-4 py-3 space-y-2 border-b border-gray-100">
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Requestor Name</p>
                <p id="m-requestor" class="text-sm font-semibold text-gray-800"></p>
            </div>
            <div>
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Purpose of Request</p>
                <p id="m-purpose" class="text-sm text-gray-700"></p>
            </div>
        </div>

        <!-- Items List -->
        <div class="border-b border-gray-100">
            <div class="bg-orange-500 grid grid-cols-[24px_1fr_50px_72px] px-3 py-1.5 gap-1">
                <span class="text-[9px] font-bold text-white text-center uppercase">#</span>
                <span class="text-[9px] font-bold text-white uppercase tracking-widest">Item / Description</span>
                <span class="text-[9px] font-bold text-white uppercase text-right">Qty</span>
                <span class="text-[9px] font-bold text-white uppercase text-right">Amount</span>
            </div>
            <div id="m-items-list" class="divide-y divide-gray-100 max-h-[200px] overflow-y-auto scrollbar-thin"></div>
            <!-- Total -->
            <div class="flex items-center justify-end gap-3 px-4 py-2 bg-orange-50 border-t-2 border-orange-400">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Total:</span>
                <span id="m-total" class="font-bold font-mono text-sm text-orange-600"></span>
            </div>
        </div>

        <!-- Attachment pending badge -->
        <div id="m-attachment-status" class="hidden px-4 pt-3 border-b border-gray-100 pb-3">
            <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2">
                <i class="fa-solid fa-clock text-yellow-500 text-xs"></i>
                <span class="text-xs font-semibold text-yellow-700">Attachment pending — requestor will follow up</span>
            </div>
        </div>

        <!-- Attachments -->
        <div id="m-attachments" class="hidden px-4 py-3 border-b border-gray-100">
            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-2">
                <i class="fa-solid fa-paperclip mr-1"></i> Attachments
            </p>
            <div id="m-attachments-grid" class="flex flex-wrap gap-2"></div>
        </div>

        <!-- Reject comment -->
        <div id="m-reject-comment"></div>

        <!-- Signatures -->
        <div class="grid grid-cols-2 border-b border-gray-100">
            <div class="bg-orange-500 py-1.5 text-center border-r border-orange-400">
                <span class="text-[9px] font-bold text-white uppercase tracking-wider">Approved By</span>
            </div>
            <div class="bg-orange-500 py-1.5 text-center">
                <span class="text-[9px] font-bold text-white uppercase tracking-wider">Received By</span>
            </div>
            <div class="px-3 py-3 border-r border-gray-100 min-h-[80px] flex flex-col justify-end">
                <div id="m-status-badge" class="mb-1"></div>
                <div id="m-approved-by"></div>
            </div>
            <div class="px-3 py-3 min-h-[80px] flex flex-col justify-end">
                <div id="m-received-by"></div>
            </div>
        </div>

        <!-- Mobile action buttons -->
        <div class="px-4 py-3 bg-gray-50 flex flex-col gap-2" id="m-action-btns"></div>
    </div>
    <!-- end mobile -->

    <!-- Lightbox (shared) -->
    <div id="lightbox" class="hidden fixed inset-0 z-[70] bg-black/90 flex items-center justify-center px-4"
        onclick="closeLightbox()">
        <button class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightbox-img" src="" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
    </div>
</div>

<!-- Reject Modal -->
<div id="reject-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-sm uppercase tracking-widest text-red-600">
                <i class="fa-solid fa-xmark mr-2"></i>Reject Request
            </h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-500">Please provide a reason for rejecting this request.</p>
            <textarea id="reject-comment" rows="4" placeholder="Enter reason for rejection..."
                class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-800 outline-none focus:border-red-400 focus:ring-1 focus:ring-red-200 resize-none transition-all"></textarea>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
            <button onclick="closeRejectModal()"
                class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all">Cancel</button>
            <button onclick="confirmReject()"
                class="flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold px-5 py-2 rounded transition-all">
                <i class="fa-solid fa-xmark text-xs"></i>Confirm Reject
            </button>
        </div>
    </div>
</div>

<!-- Ping Modal -->
<div id="ping-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-sm uppercase tracking-widest text-blue-600">
                <i class="fa-solid fa-bell mr-2"></i>Ping Staff
            </h3>
            <button onclick="closePingModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-500">Select staff to notify about this request:</p>
            <div id="ping-staff-list"
                class="space-y-1 max-h-[200px] overflow-y-auto border border-gray-100 rounded-lg p-2">
                <p class="text-xs text-gray-400">Loading...</p>
            </div>
            <textarea id="ping-message" rows="3" placeholder="Optional message to staff..."
                class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-800 outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200 resize-none transition-all"></textarea>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
            <button onclick="closePingModal()"
                class="text-sm text-gray-500 font-medium px-4 py-2 rounded">Cancel</button>
            <button onclick="confirmPing()"
                class="flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-5 py-2 rounded transition-all">
                <i class="fa-solid fa-bell text-xs"></i>Send Ping
            </button>
        </div>
    </div>
</div>

<style>
    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .day-chip { background: #fff; border-color: #e5e7eb; color: #4b5563; }
    .day-chip:hover { border-color: #d97706; color: #b45309; }
    .day-chip { cursor: pointer; }
    .day-chip[data-empty="1"] { color: #9ca3af; border-style: dashed; }
    .day-chip[data-active="1"] { background: #b45309; border-color: #b45309; color: #fff; }
    @keyframes dayRowFlash { 0%,100% { background: transparent; } 50% { background: #fef3c7; } }
    .day-row-flash { animation: dayRowFlash .6s ease-in-out 5; }
</style>

<script>
    // ═══════════════════════════════════════════════════════
    //  requests-view.php: JAVASCRIPT (buong kapalit ng lumang <script>)
    //  Mga pagbabago: fetch kada buwan, dates endpoint, escape (XSS),
    //  viewRequestById (walang JSON sa onclick), walang dobleng fetch sa print.
    //  Tandaan: huwag mag-declare ng `esc` o `BASE_URL`; ginagamit na iyon ng sidebar script.
    // ═══════════════════════════════════════════════════════

    const API = '<?= BASE_URL ?>';

    const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const fmtMoney = n => (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });

    function fmtDateTime(str) {
        if (!str) return '';
        const d = new Date(str);
        return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' +
            d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    // ─── State ───────────────────────────────────────────
    let allRequests = [];
    let allDatesCache = [];
    let lastSig = '';
    let calYear = new Date().getFullYear();
    let calMonth = new Date().getMonth();
    let selectedDate = null;
    let rejectTargetId = null;
    let pingTargetRequestId = null;
    let _highlightDone = false;

    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // ─── Calendar ────────────────────────────────────────
    const STATUS_STYLE = {
        pending:  { dot: 'bg-yellow-400', chip: 'bg-yellow-50 text-yellow-800 border-yellow-400', bar: 'bg-yellow-400' },
        approved: { dot: 'bg-green-500',  chip: 'bg-green-50 text-green-800 border-green-500',    bar: 'bg-green-500' },
        rejected: { dot: 'bg-red-400',    chip: 'bg-red-50 text-red-700 border-red-400',          bar: 'bg-red-400' },
    };
    const stStyle = s => STATUS_STYLE[s] ?? STATUS_STYLE.pending;

    function updateStats() {
        const c = { pending: 0, approved: 0, rejected: 0 };
        allRequests.forEach(r => { if (c[r.status] !== undefined) c[r.status]++; });
        document.getElementById('stat-total').textContent = allRequests.length;
        document.getElementById('stat-pending').textContent = c.pending;
        document.getElementById('stat-approved').textContent = c.approved;
        document.getElementById('stat-rejected').textContent = c.rejected;
    }

    function renderCalendar() {
        document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
        updateStats();
        const grid = document.getElementById('calendar-grid');
        grid.innerHTML = '';
        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const today = new Date();
        const isMobile = window.innerWidth < 768;
        const cellH = isMobile ? '56px' : '104px';

        const byDate = {};
        allRequests.forEach(r => {
            const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
            if (!d) return;
            (byDate[d] = byDate[d] || []).push(r);
        });

        const blank = () => {
            const b = document.createElement('div');
            b.className = 'border-b border-r border-gray-100 bg-gray-50/60';
            b.style.minHeight = cellH;
            grid.appendChild(b);
        };

        for (let i = 0; i < firstDay; i++) blank();

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const requests = byDate[dateStr] ?? [];
            const isToday = today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === day;
            const isSelected = selectedDate === dateStr;
            const dow = (firstDay + day - 1) % 7;
            const has = requests.length > 0;
            const pend = requests.filter(r => r.status === 'pending').length;

            const cell = document.createElement('div');
            cell.style.minHeight = cellH;
            cell.className = [
                'border-b border-r border-gray-100 cursor-pointer transition-colors relative min-w-0',
                isMobile ? 'flex flex-col items-center pt-1.5 pb-1 gap-1' : 'p-1.5 flex flex-col gap-1',
                isSelected ? 'bg-amber-50 ring-2 ring-inset ring-amber-500' : (has ? 'hover:bg-amber-50/60' : 'hover:bg-gray-50'),
            ].join(' ');

            const numCls = isToday
                ? 'w-6 h-6 bg-amber-600 text-white rounded-full flex items-center justify-center'
                : 'w-6 h-6 flex items-center justify-center ' + (dow === 0 ? 'text-red-400' : dow === 6 ? 'text-blue-400' : 'text-gray-700');

            if (isMobile) {
                const statuses = [...new Set(requests.map(r => r.status ?? 'pending'))];
                cell.innerHTML = `
                    <span class="text-xs font-semibold ${numCls}">${day}</span>
                    ${has ? `<div class="flex items-center gap-0.5">${statuses.map(s =>
                        `<span class="w-1.5 h-1.5 rounded-full ${stStyle(s).dot}"></span>`).join('')}</div>
                    <span class="text-[9px] font-bold text-amber-700 leading-none">${requests.length}</span>` : ''}`;
            } else {
                cell.innerHTML = `
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-xs font-semibold ${numCls}">${day}</span>
                        ${has ? `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md ${pend ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600'}"
                            title="${pend} pending">${requests.length}</span>` : ''}
                    </div>
                    ${has ? `<div class="flex flex-col gap-0.5 min-w-0">
                        ${requests.slice(0, 2).map(r => `
                            <div class="text-[10px] leading-tight truncate pl-1.5 pr-1 py-0.5 rounded border-l-2 font-medium ${stStyle(r.status).chip}">
                                ${escHtml(r.requestor_name)}
                            </div>`).join('')}
                        ${requests.length > 2 ? `<div class="text-[10px] text-gray-400 pl-1">+${requests.length - 2} more</div>` : ''}
                    </div>` : ''}`;
            }

            cell.onclick = () => { dayFilter = 'all'; openDayPanel(dateStr, requests); };
            grid.appendChild(cell);
        }

        const total = firstDay + daysInMonth;
        const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
        for (let i = 0; i < trailing; i++) blank();
    }

    // Buwan-buwan na navigation: nagfe-fetch ng bagong buwan
    function prevMonth() {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        selectedDate = null; closeDayPanel(); renderCalendar(); fetchRequests(true);
    }
    function nextMonth() {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        selectedDate = null; closeDayPanel(); renderCalendar(); fetchRequests(true);
    }
    function goToday() {
        const now = new Date();
        calYear = now.getFullYear(); calMonth = now.getMonth();
        selectedDate = null; closeDayPanel(); renderCalendar(); fetchRequests(true);
    }

    // ─── Day Panel ───────────────────────────────────────
    let dayFilter = 'all';
    let dayRequests = [];

    function setDayFilter(f) {
        dayFilter = f;
        if (selectedDate) openDayPanel(selectedDate, dayRequests, true);
    }

    function dayRowHtml(row) {
        const id = Number(row.id);
        const itemCount = row.items?.filter(i => i.description?.trim()).length ?? 0;
        const followUp = (row.attachment_status ?? 'attached') === 'follow_up';
        return `
        <div data-id="${id}" class="day-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors">
            <div class="w-1 rounded-full shrink-0 ${stStyle(row.status).bar}"></div>
            <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button onclick="viewRequestById(${id})"
                            class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5 hover:bg-amber-100 transition">
                            ${escHtml(row.control_no)}
                        </button>
                        ${statusBadge(row.status)}
                        ${followUp ? `<span class="inline-flex items-center gap-1 bg-yellow-50 text-yellow-700 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-yellow-200 whitespace-nowrap">
                            <i class="fa-solid fa-paperclip text-[9px]"></i> Attachment pending</span>` : ''}
                    </div>
                    <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate">${escHtml(row.requestor_name)}
                        ${row.sender_email ? `<span class="font-normal text-[11px] text-gray-400 ml-1 hidden sm:inline">${escHtml(row.sender_email)}</span>` : ''}
                    </p>
                    <p class="text-[13px] text-gray-600 break-words line-clamp-2">${escHtml(row.purpose)}</p>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                        ${categoryBadge(row.request_category, row.request_reference)}
                        <span class="inline-flex items-center gap-1 text-[11px] text-gray-500">
                            <i class="fa-solid fa-list-ul text-[9px] text-gray-400"></i>${itemCount} item${itemCount !== 1 ? 's' : ''}
                        </span>
                    </div>
                </div>
                <div class="shrink-0">${actionButtons(id, row.status)}</div>
            </div>
        </div>`;
    }

    function openDayPanel(dateStr, requests, keepScroll = false) {
        selectedDate = dateStr;
        dayRequests = requests;
        renderCalendar();

        const panel = document.getElementById('day-panel');
        const list = document.getElementById('day-requests-cards');
        const d = new Date(dateStr + 'T00:00:00');
        document.getElementById('day-panel-title').textContent =
            d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const cnt = { all: requests.length, pending: 0, approved: 0, rejected: 0 };
        requests.forEach(r => { if (cnt[r.status] !== undefined) cnt[r.status]++; });
        document.getElementById('day-panel-sub').textContent = requests.length
            ? `${requests.length} request${requests.length !== 1 ? 's' : ''} · ${cnt.pending} pending`
            : 'No requests';

        const chip = (key, label) => `
            <button type="button" onclick="setDayFilter('${key}')" data-active="${dayFilter === key ? 1 : 0}" data-empty="${cnt[key] ? 0 : 1}"
                class="day-chip text-[11px] font-semibold px-3 py-1 rounded-full border transition">
                ${label} <span class="ml-0.5 opacity-70">${cnt[key]}</span>
            </button>`;
        const filterBar = document.getElementById('day-filter');
        filterBar.innerHTML = requests.length
            ? chip('all', 'All') + chip('pending', 'Pending') + chip('approved', 'Approved') + chip('rejected', 'Rejected')
            : '';
        filterBar.classList.toggle('hidden', !requests.length);

        const shown = dayFilter === 'all' ? requests : requests.filter(r => r.status === dayFilter);
        list.innerHTML = shown.length ? shown.map(dayRowHtml).join('') : `
            <div class="px-4 py-10 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2">
                    <i class="fa-regular fa-calendar-xmark"></i></div>
                <p class="text-sm text-gray-500">${dayFilter === 'all' ? 'No requests on this day.' : 'No ' + dayFilter + ' requests on this day.'}</p>
                ${dayFilter !== 'all' && requests.length ? `<button type="button" onclick="setDayFilter('all')" class="mt-2 text-xs font-semibold text-amber-700 hover:underline">Show all ${requests.length}</button>` : ''}
            </div>`;

        const wasHidden = panel.classList.contains('hidden');
        panel.classList.remove('hidden');
        if (wasHidden && !keepScroll) setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }

    function closeDayPanel() {
        selectedDate = null;
        document.getElementById('day-panel').classList.add('hidden');
        renderCalendar();
    }

    // ─── Fetch (kada buwan) ──────────────────────────────
    // force = true: laging mag-render kahit pareho ang data
    function fetchRequests(force = false) {
        const month = calYear + '-' + String(calMonth + 1).padStart(2, '0');
        return fetch(API + '/fetchrequests?month=' + month)
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                const sig = month + '|' + data.map(r =>
                    r.id + r.status + (r.received_by ?? '') + (r.attachment_status ?? '')).join('|');
                if (!force && sig === lastSig) return; // walang nagbago
                lastSig = sig;
                allRequests = data;
                renderCalendar();
                if (selectedDate) {
                    openDayPanel(selectedDate, allRequests.filter(r => r.date_requested?.startsWith(selectedDate)));
                }
                document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                if (!_highlightDone) { _highlightDone = true; checkHighlight(); }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    // Lahat ng petsa na may request (maliit na data lang)
    function fetchDates() {
        return fetch(API + '/fetchrequestdates')
            .then(res => res.json())
            .then(dates => {
                if (!Array.isArray(dates)) return;
                allDatesCache = dates;
                document.getElementById('dates-dropdown-count').textContent =
                    dates.length + ' date' + (dates.length !== 1 ? 's' : '');
                renderDatesList(dates);
            })
            .catch(err => console.error('Dates error:', err));
    }

    // ─── Shared Helpers ──────────────────────────────────
    function statusBadge(status) {
        const map = {
            pending: 'bg-yellow-100 text-yellow-700',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-700',
        };
        return `<span class="${map[status] ?? 'bg-gray-100 text-gray-500'} text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">${escHtml(status)}</span>`;
    }

    function categoryBadge(category, reference) {
        if (!category) return '<span class="text-gray-300 text-xs">—</span>';
        const map = {
            project: { label: 'Project', icon: 'fa-helmet-safety', color: 'bg-blue-100 text-blue-700 border-blue-200' },
            client: { label: 'Client', icon: 'fa-user-tie', color: 'bg-purple-100 text-purple-700 border-purple-200' },
            nhcc: { label: 'NHCC', icon: 'fa-building', color: 'bg-orange-100 text-orange-700 border-orange-200' },
        };
        const cfg = map[category] ?? { label: category, icon: 'fa-tag', color: 'bg-gray-100 text-gray-600 border-gray-200' };
        if (!reference) {
            return `<span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold ${cfg.color}">
                        <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${escHtml(cfg.label)}
                    </span>`;
        }
        return `<div class="relative inline-block" data-label="${escHtml(cfg.label)}" data-ref="${escHtml(reference)}"
            onmouseenter="showCatTooltip(event, this.dataset.label, this.dataset.ref)"
            onmouseleave="hideCatTooltip()">
            <span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold cursor-default ${cfg.color}">
                <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${escHtml(cfg.label)}
                <i class="fa-solid fa-circle-info text-[8px] opacity-50"></i>
            </span>
        </div>`;
    }

    function actionButtons(id, status) {
        id = Number(id);
        const viewBtn = `<button onclick="viewRequestById(${id})"
            class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition-all">
            <i class="fa-solid fa-eye mr-1"></i>View
        </button>`;
        const pingBtn = `<button onclick="openPingModal(${id})"
            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-all">
            <i class="fa-solid fa-bell mr-1"></i>Ping
        </button>`;

        if (status === 'pending') return `
        <div class="flex items-center gap-2 flex-wrap">
            ${viewBtn}
            <button onclick="doAction(${id}, 'approved')"
                class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-all">
                <i class="fa-solid fa-check mr-1"></i>Approve
            </button>
            <button onclick="openRejectModal(${id})"
                class="bg-white border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold px-3 py-1.5 rounded-lg transition-all">
                <i class="fa-solid fa-xmark mr-1"></i>Reject
            </button>
        </div>`;
        if (status === 'approved') return `<div class="flex items-center gap-2 flex-wrap">${viewBtn}${pingBtn}</div>`;
        return viewBtn;
    }

    function doAction(id, action, comment = '') {
        fetch(API + '/actionrequest', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action, comment })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(action === 'approved' ? 'Request approved successfully!' : 'Request rejected.', action);
                    fetchRequests(true);
                    fetchDates();
                } else {
                    showToast(data.message || 'Action failed.', 'rejected');
                }
            })
            .catch(() => showToast('Network error. Please try again.', 'rejected'));
    }

    function showToast(message, type = 'approved') {
        const existing = document.getElementById('toast-notif');
        if (existing) existing.remove();
        const color = type === 'approved' ? 'bg-green-500' : 'bg-red-500';
        const icon = type === 'approved' ? 'fa-circle-check' : 'fa-xmark';
        const toast = document.createElement('div');
        toast.id = 'toast-notif';
        toast.className = `fixed bottom-6 right-6 z-[999] flex items-center gap-3 ${color} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0`;
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${escHtml(message)}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
        setTimeout(() => {
            toast.classList.replace('opacity-100', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ─── Reject Modal ────────────────────────────────────
    function openRejectModal(id) {
        rejectTargetId = id;
        document.getElementById('reject-comment').value = '';
        document.getElementById('reject-modal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('reject-modal').classList.add('hidden');
        rejectTargetId = null;
    }
    function confirmReject() {
        const comment = document.getElementById('reject-comment').value.trim();
        if (!comment) {
            document.getElementById('reject-comment').classList.add('border-red-400');
            document.getElementById('reject-comment').placeholder = 'Reason is required!';
            return;
        }
        document.getElementById('reject-comment').classList.remove('border-red-400');
        const idToReject = rejectTargetId;
        closeRejectModal();
        doAction(idToReject, 'rejected', comment);
    }

    // ─── Ping Modal ──────────────────────────────────────
    function openPingModal(id) {
        pingTargetRequestId = id;
        document.getElementById('ping-message').value = '';
        document.getElementById('ping-staff-list').innerHTML =
            '<p class="text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading staff...</p>';
        document.getElementById('ping-modal').classList.remove('hidden');
        fetch(API + '/pingrequest')
            .then(res => res.json())
            .then(staff => {
                document.getElementById('ping-staff-list').innerHTML = staff.length
                    ? staff.map(s => `
                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" value="${Number(s.id)}" class="ping-staff-check w-4 h-4 accent-blue-500">
                            <div>
                                <p class="text-sm font-medium text-gray-800">${escHtml(s.name)}</p>
                                <p class="text-[10px] text-gray-400">${escHtml(s.email ?? '')}</p>
                            </div>
                        </label>`).join('')
                    : '<p class="text-xs text-gray-400">No staff found.</p>';
            });
    }
    function closePingModal() {
        document.getElementById('ping-modal').classList.add('hidden');
        pingTargetRequestId = null;
    }
    function confirmPing() {
        const checked = [...document.querySelectorAll('.ping-staff-check:checked')];
        if (!checked.length) { alert('Please select at least one staff to ping.'); return; }
        const staff_ids = checked.map(c => parseInt(c.value));
        const message = document.getElementById('ping-message').value.trim();
        fetch(API + '/pingrequest', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: pingTargetRequestId, staff_ids, message })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) { closePingModal(); showToast('Staff pinged successfully!', 'approved'); }
            });
    }

    // ─── View Modal ──────────────────────────────────────
    function viewRequestById(id) {
        const row = allRequests.find(r => r.id == id);
        if (row) viewRequest(row);
    }

    function attachmentThumbs(attachments, size) {
        // size: 'lg' (desktop) o 'sm' (mobile)
        const box = size === 'lg' ? 'w-20 h-20' : 'w-16 h-16';
        return attachments.map(path => {
            const isPdf = String(path).toLowerCase().endsWith('.pdf');
            const fullUrl = `${API}/${path}`;
            if (isPdf) {
                return `<a href="${escHtml(fullUrl)}" target="_blank" rel="noopener"
                    class="flex flex-col items-center justify-center ${box} rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all bg-red-50 gap-1">
                    <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
                    <span class="text-[9px] text-red-400 font-semibold uppercase tracking-wide">PDF</span>
                </a>`;
            }
            return `<div class="relative cursor-pointer" data-src="${escHtml(fullUrl)}" onclick="openLightbox(this.dataset.src)">
                <img src="${escHtml(fullUrl)}" class="${box} object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all">
            </div>`;
        }).join('');
    }

    function sigBlock(name, signaturePath, when) {
        if (!name) return '';
        return `<div class="relative inline-block">
            ${signaturePath ? `<img src="${escHtml(API + '/' + signaturePath)}" alt="Signature" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:80px;max-width:220px;z-index:10;pointer-events:none;">` : ''}
            <p class="text-sm font-semibold text-gray-800">${escHtml(name)}</p>
            <p class="text-[10px] text-gray-400">${escHtml(when)}</p>
        </div>`;
    }

    function viewRequest(row) {
        const id = Number(row.id);
        const items = (row.items ?? []).filter(i => i.description && i.description.trim() !== '');
        const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);

        let attachments = [];
        try { const raw = row.attachments; attachments = typeof raw === 'string' ? JSON.parse(raw) : (raw ?? []); } catch (e) { }

        const approverName = row.approver_name ?? '';
        const approvedAt = fmtDateTime(row.approved_at);
        const receiverName = row.receiver_name ?? '';
        const receivedAt = fmtDateTime(row.received_at);
        const approverSig = row.status === 'approved' ? (row.approver_signature ?? '') : '';
        const receiverSig = row.receiver_signature_path ?? '';
        const isFollowUp = (row.attachment_status ?? 'attached') === 'follow_up';

        // ── Desktop ──
        document.getElementById('view-control-no').textContent = row.control_no;
        document.getElementById('view-date').textContent = row.date_requested;
        document.getElementById('view-requestor').textContent = row.requestor_name;
        document.getElementById('view-purpose').textContent = row.purpose;
        document.getElementById('view-category').innerHTML = categoryBadge(row.request_category, row.request_reference);

        document.getElementById('view-items-tbody').innerHTML = items.map((item, idx) => `
            <tr class="border-t border-gray-200">
                <td class="px-3 py-2 text-center text-xs text-gray-400 font-mono border-r border-gray-200 w-10">${idx + 1}</td>
                <td class="px-4 py-2 border-r border-gray-200 min-w-[180px]">${escHtml(item.description)}</td>
                <td class="px-4 py-2 border-r border-gray-200 min-w-[150px]">${escHtml(item.purpose)}</td>
                <td class="px-4 py-2 border-r border-gray-200 text-center w-24">${escHtml(item.quantity || 0)}</td>
                <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">${fmtMoney(item.unit_price)}</td>
                <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">₱ ${fmtMoney(item.amount)}</td>
                <td class="px-4 py-2 min-w-[120px]">${escHtml(item.notes)}</td>
            </tr>`).join('');
        document.getElementById('view-total').textContent = '₱ ' + fmtMoney(total);
        document.getElementById('view-status-badge').innerHTML = statusBadge(row.status);

        document.getElementById('view-reject-comment').innerHTML = (row.reject_comment && row.status === 'rejected')
            ? `<div class="mt-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
               <p class="text-[10px] font-bold uppercase tracking-widest text-red-500 mb-1">Reason for Rejection:</p>
               <p class="text-sm text-red-700">${escHtml(row.reject_comment)}</p></div>` : '';

        const attachSec = document.getElementById('view-attachments');
        const oldBadge = document.getElementById('view-attachment-status-badge');
        if (oldBadge) oldBadge.remove();
        if (isFollowUp) {
            const badge = document.createElement('div');
            badge.id = 'view-attachment-status-badge';
            badge.className = 'flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-2';
            badge.innerHTML = `<i class="fa-solid fa-clock text-yellow-500 text-xs"></i>
                <span class="text-xs font-semibold text-yellow-700">Attachment pending — requestor will follow up</span>`;
            attachSec.parentElement.prepend(badge);
        }
        if (attachments.length) {
            attachSec.classList.remove('hidden');
            document.getElementById('view-attachments-grid').innerHTML = attachmentThumbs(attachments, 'lg');
        } else {
            attachSec.classList.add('hidden');
        }

        document.getElementById('view-approved-by').innerHTML = sigBlock(approverName, approverSig, approvedAt);
        document.getElementById('view-received-by').innerHTML = sigBlock(receiverName, receiverSig, receivedAt);

        const pdfBtn = (row.status === 'approved' && row.receiver_name)
            ? `<button onclick="downloadPDF(${id})" class="flex items-center gap-1.5 bg-gray-800 hover:bg-gray-900 text-white text-[11px] font-semibold px-3 py-1.5 rounded-md transition-all whitespace-nowrap">
               <i class="fa-solid fa-file-pdf text-[10px]"></i>Download PDF</button>` : '';
        document.getElementById('view-download-btn').innerHTML = `${pdfBtn}
            <button onclick="printRequest(${id})" class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-semibold px-3 py-1.5 rounded-md transition-all whitespace-nowrap">
                <i class="fa-solid fa-print text-[10px]"></i>Print</button>`;

        // ── Mobile ──
        document.getElementById('m-control-no').textContent = row.control_no;
        document.getElementById('m-date').textContent = row.date_requested;
        document.getElementById('m-requestor').textContent = row.requestor_name;
        document.getElementById('m-purpose').textContent = row.purpose;
        document.getElementById('m-category').innerHTML = categoryBadge(row.request_category, row.request_reference);
        document.getElementById('m-status-badge').innerHTML = statusBadge(row.status);
        document.getElementById('m-total').textContent = '₱ ' + fmtMoney(total);

        document.getElementById('m-items-list').innerHTML = items.length
            ? items.map((item, idx) => `
            <div class="grid grid-cols-[24px_1fr_50px_72px] px-3 py-2 gap-1 items-start">
                <span class="text-[10px] text-gray-400 text-center mt-0.5">${idx + 1}</span>
                <div>
                    <p class="text-xs font-medium text-gray-800 leading-tight">${escHtml(item.description)}</p>
                    ${item.purpose ? `<p class="text-[10px] text-gray-400">${escHtml(item.purpose)}</p>` : ''}
                    ${item.notes ? `<p class="text-[10px] text-gray-400 italic">${escHtml(item.notes)}</p>` : ''}
                </div>
                <span class="text-[10px] text-gray-600 text-right mt-0.5">${escHtml(item.quantity ?? 0)}</span>
                <span class="text-[10px] font-mono font-semibold text-gray-800 text-right mt-0.5">₱${fmtMoney(item.amount)}</span>
            </div>`).join('')
            : `<div class="px-3 py-3 text-xs text-gray-400 text-center">No items.</div>`;

        document.getElementById('m-attachment-status').classList.toggle('hidden', !isFollowUp);

        const mAttach = document.getElementById('m-attachments');
        if (attachments.length) {
            mAttach.classList.remove('hidden');
            document.getElementById('m-attachments-grid').innerHTML = attachmentThumbs(attachments, 'sm');
        } else {
            mAttach.classList.add('hidden');
        }

        document.getElementById('m-reject-comment').innerHTML = (row.reject_comment && row.status === 'rejected')
            ? `<div class="mx-4 my-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
               <p class="text-[9px] font-bold uppercase tracking-widest text-red-500 mb-1">Reason for Rejection:</p>
               <p class="text-xs text-red-700">${escHtml(row.reject_comment)}</p></div>` : '';

        document.getElementById('m-approved-by').innerHTML = approverName
            ? `<p class="text-xs font-semibold text-gray-800">${escHtml(approverName)}</p>
               <p class="text-[10px] text-gray-400">${escHtml(approvedAt)}</p>` : '<p class="text-[10px] text-gray-300">—</p>';
        document.getElementById('m-received-by').innerHTML = receiverName
            ? `<p class="text-xs font-semibold text-gray-800">${escHtml(receiverName)}</p>
               <p class="text-[10px] text-gray-400">${escHtml(receivedAt)}</p>` : '<p class="text-[10px] text-gray-300">—</p>';

        let mActionHtml = '';
        if (row.status === 'pending') {
            mActionHtml = `
            <button onclick="doAction(${id}, 'approved'); closeViewModal();"
                class="w-full flex items-center justify-center gap-2 bg-green-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-check"></i>Approve
            </button>
            <button onclick="closeViewModal(); openRejectModal(${id});"
                class="w-full flex items-center justify-center gap-2 bg-red-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-xmark"></i>Reject
            </button>`;
        } else if (row.status === 'approved') {
            mActionHtml = `
            <button onclick="closeViewModal(); openPingModal(${id});"
                class="w-full flex items-center justify-center gap-2 bg-blue-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-bell"></i>Ping Staff
            </button>`;
        }
        mActionHtml += `
        <button onclick="printRequest(${id})"
            class="w-full flex items-center justify-center gap-2 bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
            <i class="fa-solid fa-print"></i>Print
        </button>`;
        if (row.status === 'approved' && row.receiver_name) {
            mActionHtml = `
            <button onclick="downloadPDF(${id})"
                class="w-full flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-file-pdf"></i>Download PDF
            </button>` + mActionHtml;
        }
        document.getElementById('m-action-btns').innerHTML = mActionHtml +
            `<button onclick="closeViewModal()"
                class="w-full text-sm text-gray-500 font-medium py-2.5 rounded-xl border border-gray-200 bg-white">Close</button>`;

        document.getElementById('view-modal').classList.remove('hidden');
    }

    function closeViewModal() { document.getElementById('view-modal').classList.add('hidden'); }
    function downloadPDF(id) { window.open(API + '/download-pdfbudgetrequest?id=' + encodeURIComponent(id), '_blank'); }

    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.remove('hidden');
        if (typeof event !== 'undefined' && event) event.stopPropagation();
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.add('hidden');
        document.getElementById('lightbox-img').src = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    // ─── Print (walang bagong fetch; gamit ang naka-load na data) ─────
    function printRequest(id) {
        const row = allRequests.find(r => r.id == id);
        if (!row) return;

        const items = (row.items ?? []).filter(i => i.description);
        const total = items.reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
        const td = 'border:1px solid #ccc;padding:5px;';

        let itemRows = items.map((item, idx) => `<tr>
            <td style="text-align:center;${td}">${idx + 1}</td>
            <td style="${td}">${escHtml(item.description)}</td>
            <td style="${td}">${escHtml(item.purpose)}</td>
            <td style="text-align:center;${td}">${escHtml(item.quantity || 0)}</td>
            <td style="text-align:right;${td}font-family:monospace;">${fmtMoney(item.unit_price)}</td>
            <td style="text-align:right;${td}font-family:monospace;">₱ ${fmtMoney(item.amount)}</td>
            <td style="${td}">${escHtml(item.notes)}</td></tr>`).join('');

        for (let e = items.length; e < 5; e++) {
            itemRows += `<tr>
                <td style="text-align:center;${td}color:#ccc;">${e + 1}</td>
                <td style="${td}height:22px;"></td><td style="${td}"></td>
                <td style="text-align:center;${td}">0</td>
                <td style="text-align:right;${td}font-family:monospace;">0.00</td>
                <td style="text-align:right;${td}font-family:monospace;">₱ 0.00</td>
                <td style="${td}"></td></tr>`;
        }

        const approverName = row.approver_name ?? '';
        const receiverName = row.receiver_name ?? '';
        const approvedAt = fmtDateTime(row.approved_at);
        const receivedAt = fmtDateTime(row.received_at);
        const approverSigPath = (row.status === 'approved' && row.approver_signature) ? `${API}/${row.approver_signature}` : '';
        const receiverSigPath = row.receiver_signature_path ? `${API}/${row.receiver_signature_path}` : '';

        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Budget Request - ${escHtml(row.control_no)}</title>
        <style>*{box-sizing:border-box;}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:15px;}table{width:100%;border-collapse:collapse;}th{background:#f97316;color:white;padding:6px 8px;font-size:10px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.label{font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;}.control-box{background:#f97316;color:white;font-weight:bold;text-align:center;padding:4px 8px;font-size:9px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.sig-line{border-top:1px solid #999;margin-top:40px;}@page{size:A4 landscape;margin:1cm;}</style></head><body>
        <div style="border:1px solid #111;">
        <table style="border-bottom:1px solid #111;"><tr>
        <td style="width:60%;border-right:1px solid #111;padding:10px;"><table><tr>
        <td style="width:60px;"><img src="${API}/icon/logo.webp" style="width:50px;height:50px;object-fit:contain;"></td>
        <td style="border-left:1px solid #aaa;padding-left:10px;"><strong style="font-size:11px;text-transform:uppercase;">Noblehome Construction Corporation</strong><br>
        <span style="font-size:9px;color:#666;">1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544</span></td></tr></table></td>
        <td style="width:40%;padding:0;vertical-align:top;"><div style="font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:8px;border-bottom:1px solid #111;">Budget Request Form</div>
        <table style="width:100%;"><tr>
        <td style="width:50%;border-right:1px solid #111;padding:0;vertical-align:top;"><div class="control-box">Control No.</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${escHtml(row.control_no)}</div></td>
        <td style="width:50%;padding:0;vertical-align:top;"><div class="control-box">Date</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${escHtml(row.date_requested)}</div></td>
        </tr></table></td></tr></table>
        <table style="border-bottom:1px solid #111;"><tr>
        <td style="width:34%;border-right:1px solid #111;padding:8px;"><span class="label">Requestor Name:</span><span style="font-size:12px;margin-left:5px;">${escHtml(row.requestor_name)}</span></td>
        <td style="width:33%;border-right:1px solid #111;padding:8px;"><span class="label">Purpose of Request:</span><span style="font-size:12px;margin-left:5px;">${escHtml(row.purpose)}</span></td>
        <td style="width:33%;padding:8px;"><span class="label">Category:</span><span style="font-size:12px;margin-left:5px;">${row.request_category ? escHtml(row.request_category.toUpperCase()) : '—'}</span></td>
        </tr></table>
        <table style="border-collapse:collapse;width:100%;border-bottom:1px solid #111;"><thead><tr>
        <th style="width:5%;border:1px solid #ea6c00;">No.</th><th style="border:1px solid #ea6c00;text-align:left;">Items / Description</th>
        <th style="border:1px solid #ea6c00;text-align:left;">Purpose</th><th style="width:8%;border:1px solid #ea6c00;">Qty</th>
        <th style="width:13%;border:1px solid #ea6c00;">Unit Price</th><th style="width:13%;border:1px solid #ea6c00;">Amount</th>
        <th style="border:1px solid #ea6c00;text-align:left;">Notes</th></tr></thead>
        <tbody>${itemRows}</tbody>
        <tfoot><tr><td colspan="5" style="text-align:right;font-weight:bold;padding:6px;border:1px solid #ccc;font-size:10px;text-transform:uppercase;">Total:</td>
        <td style="text-align:right;font-weight:bold;font-family:monospace;border:1px solid #ccc;padding:6px;">₱ ${fmtMoney(total)}</td>
        <td style="border:1px solid #ccc;"></td></tr></tfoot></table>
        <table style="width:100%;"><tr>
        <td style="width:50%;border-right:1px solid #111;padding:15px 20px;vertical-align:bottom;"><span class="label">Approved By:</span>
        <div style="margin-top:20px;text-align:center;position:relative;">${approverSigPath ? `<img src="${escHtml(approverSigPath)}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}<strong style="font-size:12px;">${escHtml(approverName)}</strong><br><span style="font-size:9px;color:#888;">${escHtml(approvedAt)}</span></div>
        <div class="sig-line"></div><div style="text-align:center;font-size:9px;text-transform:uppercase;color:#888;margin-top:3px;">Head</div></td>
        <td style="width:50%;padding:15px 20px;vertical-align:bottom;"><span class="label">Received By:</span>
        <div style="margin-top:20px;text-align:center;position:relative;">
        ${receiverSigPath ? `<img src="${escHtml(receiverSigPath)}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}
        <strong style="font-size:12px;">${escHtml(receiverName)}</strong><br>
        <span style="font-size:9px;color:#888;">${escHtml(receivedAt)}</span></div>
        <div class="sig-line"></div></td></tr></table>
        </div><script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script></body></html>`;

        const w = window.open('', '_blank');
        w.document.write(html);
        w.document.close();
    }

    // ─── Dates Dropdown ──────────────────────────────────
    function renderDatesList(dates) {
        const ul = document.getElementById('dates-list');
        if (!dates.length) {
            ul.innerHTML = '<li class="px-4 py-4 text-xs text-gray-400 text-center">No dates found.</li>';
            return;
        }
        ul.innerHTML = dates.map(({ date, pending, approved, rejected, total }) => {
            const d = new Date(date + 'T00:00:00');
            const label = d.toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            const dots = [
                pending ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-yellow-400" title="${pending} pending"></span>` : '',
                approved ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500"  title="${approved} approved"></span>` : '',
                rejected ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-red-400"    title="${rejected} rejected"></span>` : '',
            ].join('');
            return `
            <li class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-amber-50 cursor-pointer transition group"
                data-date="${escHtml(date)}" onclick="jumpToDate(this.dataset.date)">
                <div class="flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-gray-300 group-hover:text-amber-600 transition text-xs"></i>
                    <span class="text-xs font-medium text-gray-700">${escHtml(label)}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    ${dots}
                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 group-hover:bg-white border border-amber-200 rounded-full px-1.5 py-0.5 ml-1">${Number(total)}</span>
                </div>
            </li>`;
        }).join('');
    }

    function filterDatesDropdown() {
        const q = document.getElementById('dates-search').value.toLowerCase();
        if (!q) { renderDatesList(allDatesCache); return; }
        const filtered = allDatesCache.filter(({ date }) => {
            const d = new Date(date + 'T00:00:00');
            return d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
                .toLowerCase().includes(q) || date.includes(q);
        });
        renderDatesList(filtered);
    }

    function toggleDatesDropdown(e) {
        e.stopPropagation();
        document.getElementById('dates-dropdown').classList.toggle('hidden');
        document.getElementById('dates-search').value = '';
        renderDatesList(allDatesCache);
    }

    // Lumipat sa buwan ng petsa, i-load ito, saka buksan ang araw
    function jumpToDate(dateStr) {
        document.getElementById('dates-dropdown').classList.add('hidden');
        const d = new Date(dateStr + 'T00:00:00');
        calYear = d.getFullYear(); calMonth = d.getMonth();
        selectedDate = null; dayFilter = 'all';
        fetchRequests(true).then(() => {
            openDayPanel(dateStr, allRequests.filter(r => r.date_requested?.startsWith(dateStr)));
            document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // Isara lang ang dropdown kapag nag-click sa LABAS nito
    document.addEventListener('click', e => {
        if (!e.target.closest('#dates-dropdown-wrap')) {
            document.getElementById('dates-dropdown').classList.add('hidden');
        }
    });

    // ─── Highlight (galing sa notification link) ─────────
    const _highlightStyle = document.createElement('style');
    _highlightStyle.textContent = `
        @keyframes badgePulse { 0%{transform:scale(1);opacity:1;} 50%{transform:scale(1.4);opacity:0.5;} 100%{transform:scale(1);opacity:1;} }
        .highlight-badge { display:inline-block; width:10px; height:10px; background-color:#ef4444; border-radius:50%; animation:badgePulse 0.8s ease-in-out 6; margin-right:6px; vertical-align:middle; flex-shrink:0; }
    `;
    document.head.appendChild(_highlightStyle);

    function checkHighlight() {
        const params = new URLSearchParams(window.location.search);
        const highlightId = params.get('highlight');
        const jumpDate = params.get('date');
        if (!highlightId) return;
        if (jumpDate) {
            dayFilter = 'all';
            // naka-load na ang buwan na ito (tingnan ang Init sa ibaba)
            openDayPanel(jumpDate, allRequests.filter(r => r.date_requested?.startsWith(jumpDate)));
        }
        let tries = 0;
        const interval = setInterval(() => {
            tries++;
            const row = document.querySelector(`#day-requests-cards .day-row[data-id="${CSS.escape(highlightId)}"]`);
            if (row) {
                clearInterval(interval);
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.classList.add('day-row-flash');
                setTimeout(() => row.classList.remove('day-row-flash'), 3200);
            }
            if (tries >= 25) clearInterval(interval);
        }, 200);
    }

    // ─── Category Tooltip ────────────────────────────────
    (function () {
        const tip = document.createElement('div');
        tip.id = 'cat-tooltip';
        tip.className = 'fixed z-[9999] pointer-events-none hidden';
        tip.innerHTML = `
            <div id="cat-tooltip-box" class="bg-gray-800 text-white text-xs font-medium px-3 py-2 rounded-lg shadow-lg max-w-[280px] break-words">
                <span id="cat-tooltip-label" class="text-gray-400 text-[10px] block mb-0.5 uppercase tracking-wider"></span>
                <span id="cat-tooltip-ref"></span>
            </div>
            <div class="w-2 h-2 bg-gray-800 rotate-45 mx-auto -mt-1"></div>`;
        document.body.appendChild(tip);
    })();

    function showCatTooltip(e, label, reference) {
        const tip = document.getElementById('cat-tooltip');
        document.getElementById('cat-tooltip-label').textContent = label;
        document.getElementById('cat-tooltip-ref').textContent = reference;
        tip.style.visibility = 'hidden';
        tip.classList.remove('hidden');
        const rect = e.currentTarget.getBoundingClientRect();
        const tipW = tip.offsetWidth, tipH = tip.offsetHeight;
        let left = rect.left + (rect.width / 2) - (tipW / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));
        let top = rect.top - tipH - 8;
        if (top < 8) top = rect.bottom + 8;
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
        tip.style.visibility = 'visible';
    }
    function hideCatTooltip() { document.getElementById('cat-tooltip').classList.add('hidden'); }

    // ─── Init ────────────────────────────────────────────
    // Kung may ?date= sa URL (galing sa notification), i-load agad ang buwan na iyon
    (function () {
        const jump = new URLSearchParams(window.location.search).get('date');
        if (jump) {
            const d = new Date(jump + 'T00:00:00');
            if (!isNaN(d)) { calYear = d.getFullYear(); calMonth = d.getMonth(); }
        }
    })();

    fetchRequests(true);
    fetchDates();

    // Pagbalik sa tab: i-refresh lang kapag may nagbago
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') { fetchRequests(); fetchDates(); }
    });

    setInterval(() => {
        if (document.visibilityState === 'visible') fetchRequests();
    }, 10000); // 10 segundo

        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'brv-picked';
            const SEL = '#day-requests-cards .day-row';
            let picked = null;
            try { picked = sessionStorage.getItem(KEY); } catch (e) { }

            if (!document.getElementById('nh-picked-style')) {
                const st = document.createElement('style');
                st.id = 'nh-picked-style';
                st.textContent = `
                    [data-picked="1"] { position: relative; background: #fffbeb !important;
                        box-shadow: inset 3px 0 0 #d97706, inset 0 0 0 1px #fcd34d; }
                    [data-picked="1"]:hover { background: #fef3c7 !important; }
                    [data-picked="1"]::after { content: 'Last opened'; position: absolute; top: 3px; right: 10px;
                        font-size: 9px; font-weight: 700; letter-spacing: .02em; color: #92400e;
                        background: #fde68a; border-radius: 9999px; padding: 1px 7px; pointer-events: none; }`;
                document.head.appendChild(st);
            }

            function apply() {
                document.querySelectorAll(SEL).forEach(el => {
                    const on = picked !== null && String(el.dataset.id) === picked;
                    const v = on ? '1' : '0';
                    if (el.dataset.picked !== v) el.dataset.picked = v;
                });
            }

            // capture: mauuna ito bago buksan ang modal
            document.addEventListener('click', e => {
                const row = e.target.closest(SEL);
                if (!row || row.dataset.id === undefined) return;
                picked = String(row.dataset.id);
                try { sessionStorage.setItem(KEY, picked); } catch (err) { }
                apply();
            }, true);

            // I-apply ulit tuwing magre-render ang listahan (auto-refresh, filter, ibang buwan)
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
            apply();
        })();
    </script>