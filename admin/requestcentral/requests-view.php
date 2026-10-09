<?php
// admin/requestcentral/requests-view.php
// Included sa bawat department main page — hindi standalone

?>

<div class="mb-6 flex items-center justify-between flex-wrap gap-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Budget Requests</h1>
        <p class="text-sm text-gray-400 mt-1">Requests sent to you for approval</p>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <div class="relative" id="dates-dropdown-wrap">
            <button onclick="toggleDatesDropdown(event)"
                class="flex items-center gap-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition shadow-sm">
                <i class="fa-solid fa-calendar-days text-orange-400"></i>
                <span class="hidden sm:inline">Dates with Requests</span>
                <span class="sm:hidden">Dates</span>
                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div id="dates-dropdown"
                class="hidden absolute left-0 mt-2 w-72 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Dates with Requests</p>
                    <span id="dates-dropdown-count" class="text-[10px] text-gray-400"></span>
                </div>
                <div class="px-3 py-2 border-b border-gray-100">
                    <input type="text" id="dates-search" placeholder="Search date..." oninput="filterDatesDropdown()"
                        class="w-full text-xs border border-gray-200 rounded-lg px-3 py-1.5 outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-100 transition">
                </div>
                <ul id="dates-list" class="max-h-64 overflow-y-auto py-1"></ul>
            </div>
        </div>
        <button onclick="prevMonth()"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-100 transition text-gray-500">
            <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
        <span id="cal-label" class="text-sm font-semibold text-gray-700 min-w-[130px] text-center"></span>
        <button onclick="nextMonth()"
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-100 transition text-gray-500">
            <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
        <button onclick="goToday()"
            class="text-xs font-semibold text-orange-500 border border-orange-200 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition">
            Today
        </button>
        <div class="flex items-center gap-1.5 ml-2">
            <span id="last-updated" class="text-[10px] text-gray-400 hidden sm:inline"></span>
            <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
    <div class="grid grid-cols-7 border-b border-gray-100">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
            <div
                class="py-2.5 text-center text-[11px] font-bold text-gray-400 uppercase tracking-widest <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : '') ?>">
                <?= $d ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="calendar-grid" class="grid grid-cols-7"></div>
</div>

<!-- Legend -->
<div class="flex items-center gap-4 mb-6 px-1">
    <div class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
        <span class="text-[11px] text-gray-500">Pending</span>
    </div>
    <div class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
        <span class="text-[11px] text-gray-500">Approved</span>
    </div>
    <div class="flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
        <span class="text-[11px] text-gray-500">Rejected</span>
    </div>
</div>

<!-- Day Detail Panel -->
<div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <span id="day-panel-title" class="text-sm font-semibold text-gray-700"></span>
        <button onclick="closeDayPanel()" class="text-gray-300 hover:text-gray-500 transition">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Desktop table (md+) -->
    <div class="hidden md:block overflow-x-auto max-h-[400px] overflow-y-auto scrollbar-thin">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-widest">
                    <th class="px-5 py-3 text-left">Control No.</th>
                    <th class="px-5 py-3 text-left">Requestor</th>
                    <th class="px-5 py-3 text-left">Purpose</th>
                    <th class="px-5 py-3 text-left">Category</th>
                    <th class="px-5 py-3 text-left">Items</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody id="day-requests-tbody"></tbody>
        </table>
    </div>

    <!-- Mobile cards (below md) -->
    <div class="md:hidden max-h-[400px] overflow-y-auto scrollbar-thin" id="day-requests-cards"></div>
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
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo" class="w-full h-full object-contain">
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
                <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo"
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
    .scrollbar-thin::-webkit-scrollbar { width: 4px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>

<script>
    // ─── State ───────────────────────────────────────────
    let allRequests = [];
    let previousCount = 0;
    let calYear = new Date().getFullYear();
    let calMonth = new Date().getMonth();
    let selectedDate = null;
    let rejectTargetId = null;
    let pingTargetRequestId = null;

    // ─── Calendar ────────────────────────────────────────
    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    function renderCalendar() {
        document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
        const grid = document.getElementById('calendar-grid');
        grid.innerHTML = '';
        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const today = new Date();
        const isMobile = window.innerWidth < 768;

        const byDate = {};
        allRequests.forEach(r => {
            const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
            if (!d) return;
            if (!byDate[d]) byDate[d] = [];
            byDate[d].push(r);
        });

        // Blank leading cells
        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement('div');
            blank.className = 'border-b border-r border-gray-100 bg-gray-50/30';
            blank.style.minHeight = isMobile ? '44px' : '90px';
            grid.appendChild(blank);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const mm = String(calMonth + 1).padStart(2, '0');
            const dd = String(day).padStart(2, '0');
            const dateStr = `${calYear}-${mm}-${dd}`;
            const requests = byDate[dateStr] ?? [];
            const isToday = (today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === day);
            const isSelected = selectedDate === dateStr;
            const dayOfWeek = (firstDay + day - 1) % 7;
            const isSun = dayOfWeek === 0;
            const isSat = dayOfWeek === 6;
            const hasRequests = requests.length > 0;

            const cell = document.createElement('div');
            cell.style.minHeight = isMobile ? '44px' : '90px';
            cell.className = [
                'border-b border-r border-gray-100 cursor-pointer transition-all duration-150 relative',
                isMobile ? 'flex flex-col items-center justify-start pt-1.5 pb-1' : 'p-2',
                hasRequests ? 'hover:bg-orange-50' : 'hover:bg-gray-50',
                isSelected ? 'bg-orange-50 ring-2 ring-inset ring-orange-400' : '',
            ].join(' ');

            const dotColors = {
                pending:  'bg-yellow-400',
                approved: 'bg-green-400',
                rejected: 'bg-red-400',
            };
            const seenStatuses = [...new Set(requests.map(r => r.status ?? 'pending'))];

            if (isMobile) {
                const dayNumClass = isToday
                    ? 'w-6 h-6 bg-orange-500 text-white rounded-full flex items-center justify-center text-xs font-bold'
                    : isSun ? 'text-red-400 text-xs font-semibold'
                    : isSat ? 'text-blue-400 text-xs font-semibold'
                    : 'text-gray-600 text-xs font-semibold';

                const dots = seenStatuses.map(s =>
                    `<span class="inline-block w-1.5 h-1.5 rounded-full ${dotColors[s] ?? 'bg-gray-300'}"></span>`
                ).join('');

                cell.innerHTML = `
                    <span class="${dayNumClass}">${day}</span>
                    ${hasRequests ? `
                    <div class="flex items-center justify-center gap-0.5 mt-0.5 flex-wrap">${dots}</div>
                    <span class="text-[8px] font-bold text-orange-500 leading-tight">${requests.length}</span>
                    ` : ''}`;
            } else {
                const pending  = requests.filter(r => r.status === 'pending').length;
                const approved = requests.filter(r => r.status === 'approved').length;
                const rejected = requests.filter(r => r.status === 'rejected').length;

                const dots = [
                    pending  ? `<span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span>` : '',
                    approved ? `<span class="inline-block w-2 h-2 rounded-full bg-green-400"></span>` : '',
                    rejected ? `<span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>` : '',
                ].join('');

                const countBadge = hasRequests
                    ? `<span class="text-[10px] font-bold text-orange-500">${requests.length} req</span>` : '';

                const dayNumClass = isToday
                    ? 'w-6 h-6 bg-orange-500 text-white rounded-full flex items-center justify-center'
                    : isSun ? 'text-red-400' : isSat ? 'text-blue-400' : 'text-gray-500';

                cell.innerHTML = `
                    <div class="flex items-start justify-between mb-1">
                        <span class="text-xs font-semibold ${dayNumClass}">${day}</span>
                        ${countBadge}
                    </div>
                    ${hasRequests ? `
                    <div class="flex flex-wrap gap-1 mt-1">${dots}</div>
                    <div class="mt-1.5 space-y-0.5">
                        ${requests.slice(0, 2).map(r => `
                            <div class="text-[9px] truncate px-1.5 py-0.5 rounded font-medium
                                ${r.status === 'pending' ? 'bg-yellow-100 text-yellow-700'
                                : r.status === 'approved' ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700'}">
                                ${r.requestor_name}
                            </div>`).join('')}
                        ${requests.length > 2 ? `<div class="text-[9px] text-gray-400 px-1">+${requests.length - 2} more</div>` : ''}
                    </div>` : ''}`;
            }

            cell.onclick = () => openDayPanel(dateStr, requests);
            grid.appendChild(cell);
        }

        const total = firstDay + daysInMonth;
        const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
        for (let i = 0; i < trailing; i++) {
            const blank = document.createElement('div');
            blank.className = 'border-b border-r border-gray-100 bg-gray-50/30';
            blank.style.minHeight = isMobile ? '44px' : '90px';
            grid.appendChild(blank);
        }
    }

    function prevMonth() {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        selectedDate = null; closeDayPanel(); renderCalendar();
    }
    function nextMonth() {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        selectedDate = null; closeDayPanel(); renderCalendar();
    }
    function goToday() {
        const now = new Date();
        calYear = now.getFullYear(); calMonth = now.getMonth();
        selectedDate = null; closeDayPanel(); renderCalendar();
    }

    // ─── Day Panel ───────────────────────────────────────
    function openDayPanel(dateStr, requests) {
        selectedDate = dateStr;
        renderCalendar();

        const panel = document.getElementById('day-panel');
        const title = document.getElementById('day-panel-title');
        const tbody = document.getElementById('day-requests-tbody');
        const mobileCards = document.getElementById('day-requests-cards');

        const d = new Date(dateStr + 'T00:00:00');
        title.textContent = d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        if (!requests.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">No requests on this day.</td></tr>`;
            mobileCards.innerHTML = `<div class="px-4 py-8 text-center text-gray-400 text-sm">No requests on this day.</div>`;
        } else {
            tbody.innerHTML = requests.map(row => {
                const isApproved = row.status === 'approved';
                const isRejected = row.status === 'rejected';
                const rowClass = isApproved ? 'bg-green-50 hover:bg-green-100' : isRejected ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50';
                return `
                <tr data-id="${row.id}" class="border-t border-gray-100 transition-colors ${rowClass}">
                    <td class="px-5 py-3 font-mono text-xs text-blue-900 cursor-pointer"
                        onclick="viewRequest(${JSON.stringify(row).replace(/"/g, '&quot;')})">
                        ${row.control_no}
                    </td>
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-800">${row.requestor_name}</p>
                        <p class="text-[10px] text-gray-400">${row.sender_email ?? ''}</p>
                    </td>
                    <td class="px-5 py-3 text-gray-600">${row.purpose}</td>
                    <td class="px-5 py-3">${categoryBadge(row.request_category, row.request_reference)}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">${row.items?.filter(i => i.description?.trim()).length ?? 0} item(s)</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            ${statusBadge(row.status)}
                            ${(row.attachment_status ?? 'attached') === 'follow_up'
                                ? `<span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-600 text-[9px] font-semibold px-1.5 py-0.5 rounded-full border border-yellow-200 whitespace-nowrap">
                                    <i class="fa-solid fa-clock text-[8px]"></i> Attchmnt Pending
                                   </span>` : ''}
                        </div>
                    </td>
                    <td class="px-5 py-3">${actionButtons(row.id, row.status, row)}</td>
                </tr>`;
            }).join('');

            mobileCards.innerHTML = requests.map(row => {
                const isApproved = row.status === 'approved';
                const isRejected = row.status === 'rejected';
                const barColor = isApproved ? 'bg-green-400' : isRejected ? 'bg-red-400' : 'bg-yellow-400';
                const rowBg   = isApproved ? 'bg-green-50' : isRejected ? 'bg-red-50' : 'bg-white';
                const itemCount = row.items?.filter(i => i.description?.trim()).length ?? 0;

                return `
                <div data-id="${row.id}" class="flex items-start gap-3 px-4 py-3 border-b border-gray-100 ${rowBg} active:bg-gray-50">
                    <div class="w-1 self-stretch rounded-full flex-shrink-0 mt-0.5 ${barColor}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="font-mono text-[10px] font-bold text-blue-900 truncate">${row.control_no}</span>
                            <div class="flex items-center gap-1 flex-shrink-0 flex-wrap justify-end">
                                ${statusBadge(row.status)}
                                ${(row.attachment_status ?? 'attached') === 'follow_up'
                                    ? `<span class="inline-flex items-center gap-0.5 bg-yellow-100 text-yellow-600 text-[8px] font-semibold px-1.5 py-0.5 rounded-full border border-yellow-200 whitespace-nowrap">
                                        <i class="fa-solid fa-clock text-[7px]"></i>Attchmnt Pending
                                       </span>` : ''}
                            </div>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 truncate leading-tight">${row.requestor_name}</p>
                        <p class="text-[11px] text-gray-500 truncate">${row.purpose}</p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            ${categoryBadge(row.request_category, row.request_reference)}
                            <span class="text-[10px] text-gray-400">${itemCount} item${itemCount !== 1 ? 's' : ''}</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                            <button onclick='viewRequest(${JSON.stringify(row).replace(/'/g, "\\'")})'
                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-semibold px-2.5 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-eye mr-1"></i>View
                            </button>
                            ${row.status === 'pending' ? `
                            <button onclick="doAction(${row.id}, 'approved')"
                                class="bg-green-500 hover:bg-green-600 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-check mr-1"></i>Approve
                            </button>
                            <button onclick="openRejectModal(${row.id})"
                                class="bg-red-500 hover:bg-red-600 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-xmark mr-1"></i>Reject
                            </button>` : ''}
                            ${row.status === 'approved' ? `
                            <button onclick="openPingModal(${row.id})"
                                class="bg-blue-500 hover:bg-blue-600 text-white text-[10px] font-semibold px-2.5 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-bell mr-1"></i>Ping
                            </button>` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        panel.classList.remove('hidden');
        setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }

    function closeDayPanel() {
        selectedDate = null;
        document.getElementById('day-panel').classList.add('hidden');
        renderCalendar();
    }

    // ─── Fetch ───────────────────────────────────────────
    let _highlightDone = false;

    function fetchRequests() {
        fetch('<?= BASE_URL ?>/fetchrequests')
            .then(res => res.json())
            .then(data => {
                if (data.length === previousCount && previousCount !== 0) return;
                previousCount = data.length;
                allRequests = data;
                renderCalendar();
                buildDatesDropdown();
                if (selectedDate) {
                    const filtered = allRequests.filter(r => r.date_requested?.startsWith(selectedDate));
                    openDayPanel(selectedDate, filtered);
                }
                document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                if (!_highlightDone) { _highlightDone = true; checkHighlight(); }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    // ─── Shared Helpers ──────────────────────────────────
    function statusBadge(status) {
        const map = {
            pending:  'bg-yellow-100 text-yellow-700',
            approved: 'bg-green-300 text-green-900',
            rejected: 'bg-red-100 text-red-700',
        };
        return `<span class="${map[status] ?? 'bg-gray-100 text-gray-500'} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">${status}</span>`;
    }

    function categoryBadge(category, reference) {
        if (!category) return '<span class="text-gray-300 text-xs">—</span>';
        const map = {
            project: { label: 'Project', icon: 'fa-helmet-safety', color: 'bg-blue-100 text-blue-700 border-blue-200' },
            client:  { label: 'Client',  icon: 'fa-user-tie',      color: 'bg-purple-100 text-purple-700 border-purple-200' },
            nhcc:    { label: 'NHCC',    icon: 'fa-building',       color: 'bg-orange-100 text-orange-700 border-orange-200' },
        };
        const cfg = map[category] ?? { label: category, icon: 'fa-tag', color: 'bg-gray-100 text-gray-600 border-gray-200' };
        if (!reference) {
            return `<span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold ${cfg.color}">
                        <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${cfg.label}
                    </span>`;
        }
        const safeRef = reference.replace(/'/g, "\\'").replace(/`/g, '\\`');
        return `<div class="relative inline-block"
            onmouseenter="showCatTooltip(event, '${cfg.label}', '${safeRef}')"
            onmouseleave="hideCatTooltip()">
            <span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold cursor-default ${cfg.color}">
                <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${cfg.label}
                <i class="fa-solid fa-circle-info text-[8px] opacity-50"></i>
            </span>
        </div>`;
    }

    function actionButtons(id, status, row) {
        const viewBtn = `<button onclick='viewRequest(${JSON.stringify(row).replace(/'/g, "\\'")})'
            class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
            <i class="fa-solid fa-eye mr-1"></i>View
        </button>`;
        const pingBtn = `<button onclick="openPingModal(${id})"
            class="bg-blue-500 hover:bg-blue-600 text-white text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
            <i class="fa-solid fa-bell mr-1"></i>Ping
        </button>`;

        if (status === 'pending') return `
        <div class="flex items-center gap-2">
            ${viewBtn}
            <button onclick="doAction(${id}, 'approved')"
                class="bg-green-500 hover:bg-green-600 text-white text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
                <i class="fa-solid fa-check mr-1"></i>Approve
            </button>
            <button onclick="openRejectModal(${id})"
                class="bg-red-500 hover:bg-red-600 text-white text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
                <i class="fa-solid fa-xmark mr-1"></i>Reject
            </button>
        </div>`;
        if (status === 'approved') return `<div class="flex items-center gap-2">${viewBtn}${pingBtn}</div>`;
        return viewBtn;
    }

    function doAction(id, action, comment = '') {
        fetch('<?= BASE_URL ?>/actionrequest', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action, comment })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(action === 'approved' ? 'Request approved successfully!' : 'Request rejected.', action);
                    previousCount = 0;
                    fetchRequests();
                }
            });
    }

    function showToast(message, type = 'approved') {
        const existing = document.getElementById('toast-notif');
        if (existing) existing.remove();
        const color = type === 'approved' ? 'bg-green-500' : 'bg-red-500';
        const icon = type === 'approved' ? 'fa-circle-check' : 'fa-xmark';
        const toast = document.createElement('div');
        toast.id = 'toast-notif';
        toast.className = `fixed bottom-6 right-6 z-[999] flex items-center gap-3 ${color} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0`;
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;
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
        fetch('<?= BASE_URL ?>/pingrequest')
            .then(res => res.json())
            .then(staff => {
                document.getElementById('ping-staff-list').innerHTML = staff.length
                    ? staff.map(s => `
                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" value="${s.id}" class="ping-staff-check w-4 h-4 accent-blue-500">
                            <div>
                                <p class="text-sm font-medium text-gray-800">${s.name}</p>
                                <p class="text-[10px] text-gray-400">${s.email ?? ''}</p>
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
        fetch('<?= BASE_URL ?>/pingrequest', {
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
    function viewRequest(row) {
        // ── Desktop fields ──
        document.getElementById('view-control-no').textContent = row.control_no;
        document.getElementById('view-date').textContent = row.date_requested;
        document.getElementById('view-requestor').textContent = row.requestor_name;
        document.getElementById('view-purpose').textContent = row.purpose;
        document.getElementById('view-category').innerHTML = categoryBadge(row.request_category, row.request_reference);

        const items = row.items ?? [];
        let total = 0, rowNum = 0;
        document.getElementById('view-items-tbody').innerHTML = items
            .filter(item => item.description && item.description.trim() !== '')
            .map(item => {
                rowNum++;
                const amount = parseFloat(item.amount) || 0;
                total += amount;
                return `
                <tr class="border-t border-gray-200">
                    <td class="px-3 py-2 text-center text-xs text-gray-400 font-mono border-r border-gray-200 w-10">${rowNum}</td>
                    <td class="px-4 py-2 border-r border-gray-200 min-w-[180px]">${item.description || ''}</td>
                    <td class="px-4 py-2 border-r border-gray-200 min-w-[150px]">${item.purpose || ''}</td>
                    <td class="px-4 py-2 border-r border-gray-200 text-center w-24">${item.quantity || 0}</td>
                    <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">${parseFloat(item.unit_price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">₱ ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    <td class="px-4 py-2 min-w-[120px]">${item.notes || ''}</td>
                </tr>`;
            }).join('');
        document.getElementById('view-total').textContent = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('view-status-badge').innerHTML = statusBadge(row.status);

        const rejectDiv = document.getElementById('view-reject-comment');
        rejectDiv.innerHTML = (row.reject_comment && row.status === 'rejected')
            ? `<div class="mt-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
               <p class="text-[10px] font-bold uppercase tracking-widest text-red-500 mb-1">Reason for Rejection:</p>
               <p class="text-sm text-red-700">${row.reject_comment}</p></div>` : '';

        let attachments = [];
        try { const raw = row.attachments; attachments = typeof raw === 'string' ? JSON.parse(raw) : (raw ?? []); } catch (e) { }
        const attachSec = document.getElementById('view-attachments');

        const oldBadge = document.getElementById('view-attachment-status-badge');
        if (oldBadge) oldBadge.remove();

        if ((row.attachment_status ?? 'attached') === 'follow_up') {
            const badge = document.createElement('div');
            badge.id = 'view-attachment-status-badge';
            badge.className = 'flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-2';
            badge.innerHTML = `<i class="fa-solid fa-clock text-yellow-500 text-xs"></i>
                <span class="text-xs font-semibold text-yellow-700">Attachment pending — requestor will follow up</span>`;
            attachSec.parentElement.prepend(badge);
        }

        if (attachments.length) {
            attachSec.classList.remove('hidden');
            document.getElementById('view-attachments-grid').innerHTML = attachments.map(path => {
                const isPdf = path.toLowerCase().endsWith('.pdf');
                const fullUrl = `<?= BASE_URL ?>/${path}`;
                if (isPdf) {
                    return `<a href="${fullUrl}" target="_blank"
                        class="relative flex flex-col items-center justify-center w-20 h-20 rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all bg-red-50 cursor-pointer gap-1">
                        <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
                        <span class="text-[9px] text-red-400 font-semibold uppercase tracking-wide">PDF</span>
                    </a>`;
                }
                return `<div class="relative group/thumb cursor-pointer" onclick="openLightbox('${fullUrl}')">
                    <img src="${fullUrl}" class="w-20 h-20 object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all">
                    <div class="absolute inset-0 bg-black/0 group-hover/thumb:bg-black/20 rounded-lg transition-all flex items-center justify-center">
                        <i class="fa-solid fa-magnifying-glass text-white opacity-0 group-hover/thumb:opacity-100 transition-all text-xs"></i>
                    </div>
                </div>`;
            }).join('');
        } else {
            attachSec.classList.add('hidden');
        }

        const approverName = row.approver_name ?? '';
        const approvedAt = row.approved_at ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + new Date(row.approved_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true }) : '';
        const receiverName = row.receiver_name ?? '';
        const receivedAt = row.received_at ? new Date(row.received_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + new Date(row.received_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true }) : '';

        const approverSig = (row.status === 'approved') ? (row.approver_signature ?? '') : '';
        document.getElementById('view-approved-by').innerHTML = approverName
            ? `<div class="relative inline-block">
        ${approverSig ? `<img src="<?= BASE_URL ?>/${approverSig}" alt="Signature" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:80px;max-width:220px;z-index:10;pointer-events:none;">` : ''}
        <p class="text-sm font-semibold text-gray-800">${approverName}</p>
        <p class="text-[10px] text-gray-400">${approvedAt}</p>
       </div>` : '';

        const receiverSig = row.receiver_signature_path ?? '';
        document.getElementById('view-received-by').innerHTML = receiverName
            ? `<div class="relative inline-block">
        ${receiverSig ? `<img src="<?= BASE_URL ?>/${receiverSig}" alt="Signature" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:80px;max-width:220px;z-index:10;pointer-events:none;">` : ''}
        <p class="text-sm font-semibold text-gray-800">${receiverName}</p>
        <p class="text-[10px] text-gray-400">${receivedAt}</p>
       </div>` : '';

        const downloadBtn = document.getElementById('view-download-btn');
        const pdfBtn = (row.status === 'approved' && row.receiver_name)
            ? `<button onclick="downloadPDF(${row.id})" class="flex items-center gap-1.5 bg-gray-800 hover:bg-gray-900 text-white text-[11px] font-semibold px-3 py-1.5 rounded-md transition-all whitespace-nowrap">
               <i class="fa-solid fa-file-pdf text-[10px]"></i>Download PDF</button>` : '';
        downloadBtn.innerHTML = `${pdfBtn}
            <button onclick="printRequest(${row.id})" class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-semibold px-3 py-1.5 rounded-md transition-all whitespace-nowrap">
                <i class="fa-solid fa-print text-[10px]"></i>Print</button>`;

        // ══════════════════════════════════════════════════
        // ── MOBILE fields sync ──
        // ══════════════════════════════════════════════════
        document.getElementById('m-control-no').textContent = row.control_no;
        document.getElementById('m-date').textContent = row.date_requested;
        document.getElementById('m-requestor').textContent = row.requestor_name;
        document.getElementById('m-purpose').textContent = row.purpose;
        document.getElementById('m-category').innerHTML = categoryBadge(row.request_category, row.request_reference);
        document.getElementById('m-status-badge').innerHTML = statusBadge(row.status);
        document.getElementById('m-total').textContent = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

        // Mobile items list
        const mItemsList = document.getElementById('m-items-list');
        let mRows = '';
        let mFilled = 0;
        (row.items ?? []).filter(i => i.description?.trim()).forEach(item => {
            mFilled++;
            const amt = parseFloat(item.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            mRows += `
            <div class="grid grid-cols-[24px_1fr_50px_72px] px-3 py-2 gap-1 items-start">
                <span class="text-[10px] text-gray-400 text-center mt-0.5">${mFilled}</span>
                <div>
                    <p class="text-xs font-medium text-gray-800 leading-tight">${item.description}</p>
                    ${item.purpose ? `<p class="text-[10px] text-gray-400">${item.purpose}</p>` : ''}
                    ${item.notes   ? `<p class="text-[10px] text-gray-400 italic">${item.notes}</p>`   : ''}
                </div>
                <span class="text-[10px] text-gray-600 text-right mt-0.5">${item.quantity ?? 0}</span>
                <span class="text-[10px] font-mono font-semibold text-gray-800 text-right mt-0.5">₱${amt}</span>
            </div>`;
        });
        if (!mFilled) mRows = `<div class="px-3 py-3 text-xs text-gray-400 text-center">No items.</div>`;
        mItemsList.innerHTML = mRows;

        // Mobile attachment pending badge
        const mAttachStatus = document.getElementById('m-attachment-status');
        if ((row.attachment_status ?? 'attached') === 'follow_up') {
            mAttachStatus.classList.remove('hidden');
        } else {
            mAttachStatus.classList.add('hidden');
        }

        // Mobile attachments
        const mAttach     = document.getElementById('m-attachments');
        const mAttachGrid = document.getElementById('m-attachments-grid');
        if (attachments.length) {
            mAttach.classList.remove('hidden');
            mAttachGrid.innerHTML = attachments.map(path => {
                const isPdf   = path.toLowerCase().endsWith('.pdf');
                const fullUrl = `<?= BASE_URL ?>/${path}`;
                if (isPdf) {
                    return `<a href="${fullUrl}" target="_blank"
                        class="flex flex-col items-center justify-center w-16 h-16 rounded-lg border border-gray-200 bg-red-50 gap-1">
                        <i class="fa-solid fa-file-pdf text-red-500 text-xl"></i>
                        <span class="text-[9px] text-red-400 font-semibold uppercase">PDF</span>
                    </a>`;
                }
                return `<div class="cursor-pointer" onclick="openLightbox('${fullUrl}')">
                    <img src="${fullUrl}" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                </div>`;
            }).join('');
        } else {
            mAttach.classList.add('hidden');
        }

        // Mobile reject comment
        const mRejectDiv = document.getElementById('m-reject-comment');
        mRejectDiv.innerHTML = (row.reject_comment && row.status === 'rejected')
            ? `<div class="mx-4 my-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
               <p class="text-[9px] font-bold uppercase tracking-widest text-red-500 mb-1">Reason for Rejection:</p>
               <p class="text-xs text-red-700">${row.reject_comment}</p></div>` : '';

        // Mobile signatures
        document.getElementById('m-approved-by').innerHTML = approverName
            ? `<p class="text-xs font-semibold text-gray-800">${approverName}</p>
               <p class="text-[10px] text-gray-400">${approvedAt}</p>` : '<p class="text-[10px] text-gray-300">—</p>';
        document.getElementById('m-received-by').innerHTML = receiverName
            ? `<p class="text-xs font-semibold text-gray-800">${receiverName}</p>
               <p class="text-[10px] text-gray-400">${receivedAt}</p>` : '<p class="text-[10px] text-gray-300">—</p>';

        // Mobile action buttons
        const mActionBtns = document.getElementById('m-action-btns');
        const mCloseBtn = `<button onclick="closeViewModal()"
            class="w-full text-sm text-gray-500 font-medium py-2.5 rounded-xl border border-gray-200 bg-white">Close</button>`;

        let mActionHtml = '';

        if (row.status === 'pending') {
            mActionHtml = `
            <button onclick="doAction(${row.id}, 'approved'); closeViewModal();"
                class="w-full flex items-center justify-center gap-2 bg-green-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-check"></i>Approve
            </button>
            <button onclick="closeViewModal(); openRejectModal(${row.id});"
                class="w-full flex items-center justify-center gap-2 bg-red-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-xmark"></i>Reject
            </button>`;
        } else if (row.status === 'approved') {
            mActionHtml = `
            <button onclick="closeViewModal(); openPingModal(${row.id});"
                class="w-full flex items-center justify-center gap-2 bg-blue-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-bell"></i>Ping Staff
            </button>`;
        }

        // Print button — laging nandoon
        mActionHtml += `
        <button onclick="printRequest(${row.id})"
            class="w-full flex items-center justify-center gap-2 bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
            <i class="fa-solid fa-print"></i>Print
        </button>`;

        // Download PDF — kung approved at may receiver
        if (row.status === 'approved' && row.receiver_name) {
            mActionHtml = `
            <button onclick="downloadPDF(${row.id})"
                class="w-full flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-file-pdf"></i>Download PDF
            </button>` + mActionHtml;
        }

        mActionBtns.innerHTML = mActionHtml + mCloseBtn;
        // ══════════════════════════════════════════════════

        document.getElementById('view-modal').classList.remove('hidden');
    }

    function closeViewModal() { document.getElementById('view-modal').classList.add('hidden'); }
    function downloadPDF(id) { window.open('<?= BASE_URL ?>/download-pdfbudgetrequest?id=' + id, '_blank'); }

    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.remove('hidden');
        event.stopPropagation();
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.add('hidden');
        document.getElementById('lightbox-img').src = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    function printRequest(id) {
        fetch('<?= BASE_URL ?>/fetchrequests')
            .then(res => res.json())
            .then(data => {
                const row = data.find(r => r.id == id);
                if (!row) return;
                const items = row.items ?? [];
                let total = 0, itemRows = '', filledCount = 0;
                items.forEach(item => {
                    if (!item.description) return;
                    filledCount++;
                    const amount = parseFloat(item.amount || 0);
                    total += amount;
                    itemRows += `<tr>
                        <td style="text-align:center;border:1px solid #ccc;padding:5px;">${filledCount}</td>
                        <td style="border:1px solid #ccc;padding:5px;">${item.description || ''}</td>
                        <td style="border:1px solid #ccc;padding:5px;">${item.purpose || ''}</td>
                        <td style="text-align:center;border:1px solid #ccc;padding:5px;">${item.quantity || 0}</td>
                        <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">${parseFloat(item.unit_price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                        <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">₱ ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                        <td style="border:1px solid #ccc;padding:5px;">${item.notes || ''}</td></tr>`;
                });
                for (let e = filledCount; e < 5; e++) {
                    itemRows += `<tr>
                        <td style="text-align:center;border:1px solid #ccc;padding:5px;color:#ccc;">${e + 1}</td>
                        <td style="border:1px solid #ccc;padding:5px;height:22px;"></td>
                        <td style="border:1px solid #ccc;padding:5px;"></td>
                        <td style="text-align:center;border:1px solid #ccc;padding:5px;">0</td>
                        <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">0.00</td>
                        <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">₱ 0.00</td>
                        <td style="border:1px solid #ccc;padding:5px;"></td></tr>`;
                }
                const approverName  = row.approver_name ?? '';
                const approvedAt    = row.approved_at  ? new Date(row.approved_at).toLocaleDateString('en-PH',  { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + new Date(row.approved_at).toLocaleTimeString('en-PH',  { hour: 'numeric', minute: '2-digit', hour12: true }) : '';
                const receiverName  = row.receiver_name ?? '';
                const receivedAt    = row.received_at  ? new Date(row.received_at).toLocaleDateString('en-PH',  { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + new Date(row.received_at).toLocaleTimeString('en-PH',  { hour: 'numeric', minute: '2-digit', hour12: true }) : '';
                const approverSigPath = (row.status === 'approved' && row.approver_signature) ? `<?= BASE_URL ?>/${row.approver_signature}` : '';
                const receiverSigPath = row.receiver_signature_path ? `<?= BASE_URL ?>/${row.receiver_signature_path}` : '';
                const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Budget Request - ${row.control_no}</title>
                <style>*{box-sizing:border-box;}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:15px;}table{width:100%;border-collapse:collapse;}th{background:#f97316;color:white;padding:6px 8px;font-size:10px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.label{font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;}.control-box{background:#f97316;color:white;font-weight:bold;text-align:center;padding:4px 8px;font-size:9px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.sig-line{border-top:1px solid #999;margin-top:40px;}@page{size:A4 landscape;margin:1cm;}</style></head><body>
                <div style="border:1px solid #111;">
                <table style="border-bottom:1px solid #111;"><tr>
                <td style="width:60%;border-right:1px solid #111;padding:10px;"><table><tr>
                <td style="width:60px;"><img src="<?= BASE_URL ?>/icon/logo.png" style="width:50px;height:50px;object-fit:contain;"></td>
                <td style="border-left:1px solid #aaa;padding-left:10px;"><strong style="font-size:11px;text-transform:uppercase;">Noblehome Construction Corporation</strong><br>
                <span style="font-size:9px;color:#666;">1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544</span></td></tr></table></td>
                <td style="width:40%;padding:0;vertical-align:top;"><div style="font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:8px;border-bottom:1px solid #111;">Budget Request Form</div>
                <table style="width:100%;"><tr>
                <td style="width:50%;border-right:1px solid #111;padding:0;vertical-align:top;"><div class="control-box">Control No.</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${row.control_no}</div></td>
                <td style="width:50%;padding:0;vertical-align:top;"><div class="control-box">Date</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${row.date_requested}</div></td>
                </tr></table></td></tr></table>
                <table style="border-bottom:1px solid #111;"><tr>
                <td style="width:34%;border-right:1px solid #111;padding:8px;"><span class="label">Requestor Name:</span><span style="font-size:12px;margin-left:5px;">${row.requestor_name}</span></td>
                <td style="width:33%;border-right:1px solid #111;padding:8px;"><span class="label">Purpose of Request:</span><span style="font-size:12px;margin-left:5px;">${row.purpose}</span></td>
                <td style="width:33%;padding:8px;"><span class="label">Category:</span><span style="font-size:12px;margin-left:5px;">${row.request_category ? row.request_category.toUpperCase() : '—'}</span></td>
                </tr></table>
                <table style="border-collapse:collapse;width:100%;border-bottom:1px solid #111;"><thead><tr>
                <th style="width:5%;border:1px solid #ea6c00;">No.</th><th style="border:1px solid #ea6c00;text-align:left;">Items / Description</th>
                <th style="border:1px solid #ea6c00;text-align:left;">Purpose</th><th style="width:8%;border:1px solid #ea6c00;">Qty</th>
                <th style="width:13%;border:1px solid #ea6c00;">Unit Price</th><th style="width:13%;border:1px solid #ea6c00;">Amount</th>
                <th style="border:1px solid #ea6c00;text-align:left;">Notes</th></tr></thead>
                <tbody>${itemRows}</tbody>
                <tfoot><tr><td colspan="5" style="text-align:right;font-weight:bold;padding:6px;border:1px solid #ccc;font-size:10px;text-transform:uppercase;">Total:</td>
                <td style="text-align:right;font-weight:bold;font-family:monospace;border:1px solid #ccc;padding:6px;">₱ ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                <td style="border:1px solid #ccc;"></td></tr></tfoot></table>
                <table style="width:100%;"><tr>
                <td style="width:50%;border-right:1px solid #111;padding:15px 20px;vertical-align:bottom;"><span class="label">Approved By:</span>
                <div style="margin-top:20px;text-align:center;position:relative;">${approverSigPath ? `<img src="${approverSigPath}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}<strong style="font-size:12px;">${approverName}</strong><br><span style="font-size:9px;color:#888;">${approvedAt}</span></div>
                <div class="sig-line"></div><div style="text-align:center;font-size:9px;text-transform:uppercase;color:#888;margin-top:3px;">Head</div></td>
                <td style="width:50%;padding:15px 20px;vertical-align:bottom;"><span class="label">Received By:</span>
                <div style="margin-top:20px;text-align:center;position:relative;">
                ${receiverSigPath ? `<img src="${receiverSigPath}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}
                <strong style="font-size:12px;">${receiverName}</strong><br>
                <span style="font-size:9px;color:#888;">${receivedAt}</span></div>
                <div class="sig-line"></div></td></tr></table>
                </div><script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script></body></html>`;
                const w = window.open('', '_blank');
                w.document.write(html);
                w.document.close();
            });
    }

    // ─── Dates Dropdown ─────────────────────────────────
    let allDatesCache = [];

    function buildDatesDropdown() {
        const byDate = {};
        allRequests.forEach(r => {
            const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
            if (!d) return;
            if (!byDate[d]) byDate[d] = { pending: 0, approved: 0, rejected: 0, total: 0 };
            byDate[d][r.status] = (byDate[d][r.status] || 0) + 1;
            byDate[d].total++;
        });
        allDatesCache = Object.entries(byDate)
            .sort((a, b) => b[0].localeCompare(a[0]))
            .map(([date, counts]) => ({ date, ...counts }));
        document.getElementById('dates-dropdown-count').textContent =
            allDatesCache.length + ' date' + (allDatesCache.length !== 1 ? 's' : '');
        renderDatesList(allDatesCache);
    }

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
                pending  ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-yellow-400" title="${pending} pending"></span>`  : '',
                approved ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-green-400"  title="${approved} approved"></span>` : '',
                rejected ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-red-400"    title="${rejected} rejected"></span>` : '',
            ].join('');
            return `
            <li class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-orange-50 cursor-pointer transition group"
                onclick="jumpToDate('${date}')">
                <div class="flex items-center gap-2">
                    <i class="fa-regular fa-calendar text-gray-300 group-hover:text-orange-400 transition text-xs"></i>
                    <span class="text-xs font-medium text-gray-700">${label}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    ${dots}
                    <span class="text-[10px] font-bold text-orange-500 bg-orange-50 group-hover:bg-white border border-orange-200 rounded-full px-1.5 py-0.5 ml-1">${total}</span>
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

    function jumpToDate(dateStr) {
        document.getElementById('dates-dropdown').classList.add('hidden');
        const d = new Date(dateStr + 'T00:00:00');
        calYear = d.getFullYear(); calMonth = d.getMonth();
        renderCalendar();
        const requests = allRequests.filter(r => r.date_requested?.startsWith(dateStr));
        openDayPanel(dateStr, requests);
        document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.addEventListener('click', () => {
        document.getElementById('dates-dropdown').classList.add('hidden');
    });

    // ─── Highlight ───────────────────────────────────────
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
            const d = new Date(jumpDate + 'T00:00:00');
            calYear = d.getFullYear(); calMonth = d.getMonth();
            renderCalendar();
            const requests = allRequests.filter(r => r.date_requested?.startsWith(jumpDate));
            openDayPanel(jumpDate, requests);
        }
        let tries = 0;
        const interval = setInterval(() => {
            tries++;
            const isMobile = window.innerWidth < 768;
            const row = isMobile
                ? document.querySelector(`#day-requests-cards div[data-id="${highlightId}"]`)
                : document.querySelector(`#day-requests-tbody tr[data-id="${highlightId}"]`);
            if (row) {
                clearInterval(interval);
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (!isMobile) {
                    const firstTd = row.querySelector('td:first-child');
                    if (firstTd) {
                        const badge = document.createElement('span');
                        badge.className = 'highlight-badge';
                        firstTd.prepend(badge);
                        setTimeout(() => badge.remove(), 5000);
                    }
                } else {
                    let on = true;
                    const flash = setInterval(() => { row.style.backgroundColor = on ? '#fecaca' : '#fee2e2'; on = !on; }, 300);
                    setTimeout(() => { clearInterval(flash); row.style.backgroundColor = ''; }, 5000);
                }
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
    fetchRequests();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') { previousCount = 0; fetchRequests(); }
    });
</script>