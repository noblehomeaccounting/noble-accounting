<?php
// index-staff-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- Header -->
        <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-800">For Receiving</h1>
                <p class="text-sm text-gray-400 mt-1">Approved requests pending for receive</p>
            </div>

            <!-- Month/Year nav + Dates dropdown -->
            <div class="flex items-center gap-3 flex-wrap">

                <!-- Dates with requests dropdown -->
                <div class="relative" id="dates-dropdown-wrap">
                    <button onclick="toggleDatesDropdown(event)"
                        class="flex items-center gap-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition shadow-sm">
                        <i class="fa-solid fa-calendar-days text-orange-400"></i>
                        Dates with Requests
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
                            <input type="text" id="dates-search" placeholder="Search date..."
                                oninput="filterDatesDropdown()"
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
                    <span id="last-updated" class="text-[10px] text-gray-400"></span>
                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                </div>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <!-- Day headers -->
            <div class="grid grid-cols-7 border-b border-gray-100">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
                    <div class="py-2.5 text-center text-[11px] font-bold text-gray-400 uppercase tracking-widest
                    <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : '') ?>">
                        <?= $d ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Cells -->
            <div id="calendar-grid" class="grid grid-cols-7"></div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 mb-6 px-1">
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                <span class="text-[11px] text-gray-500">Approved (For Receive)</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                <span class="text-[11px] text-gray-500">Received</span>
            </div>
        </div>

        <!-- Day Detail Panel (hidden by default) -->
        <div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <span id="day-panel-title" class="text-sm font-semibold text-gray-700"></span>
                <button onclick="closeDayPanel()" class="text-gray-300 hover:text-gray-500 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="overflow-x-auto max-h-[460px] overflow-y-auto scrollbar-thin">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50">
                        <tr class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-widest">
                            <th class="px-5 py-3 text-left">Control No.</th>
                            <th class="px-5 py-3 text-left">Requestor</th>
                            <th class="px-5 py-3 text-left">Purpose</th>
                            <th class="px-5 py-3 text-left">Total</th>
                            <th class="px-5 py-3 text-left">Approved By</th>
                            <th class="px-5 py-3 text-left">Category</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody id="day-requests-tbody"></tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- View Modal (read-only) -->
    <div id="view-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8 overflow-y-auto">
        <div class="bg-white w-full max-w-5xl rounded-sm shadow-xl border border-gray-300 my-auto">

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
                            1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>
                            noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544
                        </p>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center justify-between px-4 py-2 border-b-2 border-gray-800 gap-4">
                        <h2 class="font-bold text-sm uppercase tracking-widest whitespace-nowrap">Budget Request Form
                        </h2>
                        <div class="flex items-center gap-2">

                            <button onclick="printRequest(currentViewRow)"
                                class="flex items-center gap-1.5 text-[10px] font-semibold text-white bg-orange-500 hover:bg-orange-600 px-3 py-1.5 rounded-lg transition">
                                <i class="fa-solid fa-print"></i> Print
                            </button>
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
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Requestor
                        Name:</span>
                    <p id="view-requestor" class="text-sm text-gray-800"></p>
                </div>
                <div class="flex items-center gap-2 px-6 py-3 border-r-2 border-gray-800">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Purpose
                        of Request:</span>
                    <p id="view-purpose" class="text-sm text-gray-800"></p>
                </div>
                <div class="flex items-center gap-2 px-6 py-3">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Category:</span>
                    <p id="view-category" class="text-sm text-gray-800"></p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto max-h-[280px] overflow-y-auto">
                <table class="w-full text-sm border-collapse">
                    <thead class="sticky top-0 z-10">
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

            <!-- Attachments -->
            <div id="view-attachments" class="hidden px-6 py-3 border-t-2 border-gray-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-600 mb-2">
                    <i class="fa-solid fa-paperclip mr-1"></i> Attachments
                </p>
                <div id="view-attachments-grid" class="flex flex-wrap gap-2"></div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-2 border-t-2 border-gray-800">
                <div class="px-5 py-4 border-r-2 border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Approved By:</span>
                        <div id="view-status-badge"></div>
                    </div>
                    <div id="view-approved-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">Head</p>
                </div>
                <div class="px-5 py-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Received By:</span>
                    </div>
                    <div id="view-received-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">&nbsp;</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="hidden fixed inset-0 z-[200] bg-black/90 flex items-center justify-center px-4"
        onclick="closeLightbox()">
        <button class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightbox-img" src="" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
    </div>

    <div id="cat-tooltip" class="fixed z-[9999] pointer-events-none hidden" style="visibility:hidden">
        <div id="cat-tooltip-box"
            class="bg-gray-800 text-white text-xs font-medium px-3 py-2 rounded-lg shadow-lg max-w-[220px] break-words leading-relaxed">
            <span id="cat-tooltip-label" class="text-gray-400 text-[10px] block mb-0.5 uppercase tracking-wider"></span>
            <span id="cat-tooltip-ref"></span>
        </div>
        <div class="w-2 h-2 bg-gray-800 rotate-45 mx-auto -mt-1"></div>
    </div>

    <script>
        // ─── State ───────────────────────────────────────────
        let allRequests = [];
        let previousCount = 0;
        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth();
        let selectedDate = null;
        let highlightDone = false;

        // ─── Calendar ────────────────────────────────────────
        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];

        function renderCalendar() {
            document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;

            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';

            const firstDay = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const today = new Date();

            // Group by date
            const byDate = {};
            allRequests.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (!d) return;
                if (!byDate[d]) byDate[d] = [];
                byDate[d].push(r);
            });

            // Leading blanks
            for (let i = 0; i < firstDay; i++) {
                const blank = document.createElement('div');
                blank.className = 'min-h-[90px] bg-gray-50/50 border-b border-r border-gray-100';
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

                const cell = document.createElement('div');
                cell.className = [
                    'min-h-[90px] p-2 border-b border-r border-gray-100 cursor-pointer transition-all duration-150',
                    requests.length ? 'hover:bg-orange-50' : 'hover:bg-gray-50',
                    isSelected ? 'bg-orange-50 ring-2 ring-inset ring-orange-400' : '',
                ].join(' ');

                // approved = not yet received (status still 'approved'), received = has receiver_name
                const forReceive = requests.filter(r => !r.receiver_name).length;
                const received = requests.filter(r => r.receiver_name).length;

                const dots = [
                    forReceive ? `<span class="inline-block w-2 h-2 rounded-full bg-blue-400"></span>` : '',
                    received ? `<span class="inline-block w-2 h-2 rounded-full bg-green-400"></span>` : '',
                ].join('');

                const countBadge = requests.length
                    ? `<span class="text-[10px] font-bold text-orange-500">${requests.length} req</span>`
                    : '';

                cell.innerHTML = `
                    <div class="flex items-start justify-between mb-1">
                        <span class="text-xs font-semibold ${isToday ? 'w-6 h-6 bg-orange-500 text-white rounded-full flex items-center justify-center' : isSun ? 'text-red-400' : isSat ? 'text-blue-400' : 'text-gray-500'}">${day}</span>
                        ${countBadge}
                    </div>
                    ${requests.length ? `
                    <div class="flex flex-wrap gap-1 mt-1">${dots}</div>
                    <div class="mt-1.5 space-y-0.5">
                        ${requests.slice(0, 2).map(r => `
                            <div class="text-[9px] truncate px-1.5 py-0.5 rounded font-medium
                                ${r.receiver_name ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'}">
                                ${r.requestor_name}
                            </div>`).join('')}
                        ${requests.length > 2 ? `<div class="text-[9px] text-gray-400 px-1">+${requests.length - 2} more</div>` : ''}
                    </div>` : ''}
                `;

                cell.onclick = () => openDayPanel(dateStr, requests);
                grid.appendChild(cell);
            }

            // Trailing blanks
            const total = firstDay + daysInMonth;
            const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
            for (let i = 0; i < trailing; i++) {
                const blank = document.createElement('div');
                blank.className = 'min-h-[90px] bg-gray-50/50 border-b border-r border-gray-100';
                grid.appendChild(blank);
            }
        }

        function prevMonth() {
            calMonth--;
            if (calMonth < 0) { calMonth = 11; calYear--; }
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }

        function nextMonth() {
            calMonth++;
            if (calMonth > 11) { calMonth = 0; calYear++; }
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }

        function goToday() {
            const now = new Date();
            calYear = now.getFullYear();
            calMonth = now.getMonth();
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }

        // ─── Day Panel ───────────────────────────────────────
        function openDayPanel(dateStr, requests) {
            selectedDate = dateStr;
            renderCalendar();

            const panel = document.getElementById('day-panel');
            const title = document.getElementById('day-panel-title');
            const tbody = document.getElementById('day-requests-tbody');

            const d = new Date(dateStr + 'T00:00:00');
            title.textContent = d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            if (!requests.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">No requests on this day.</td></tr>`;
            } else {
                tbody.innerHTML = requests.map(row => {
                    const isReceived = !!row.receiver_name;
                    const rowClass = isReceived ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50';
                    const items = row.items ?? [];
                    const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);

                    // PALITAN NG
                    const receivedAt = row.received_at
                        ? new Date(row.received_at.replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
                        + ' ' + new Date(row.received_at.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                        : '';

                    const statusBadgeHtml = isReceived
                        ? `<div>
        <span class="bg-green-100 text-green-700 text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">Received</span>
        <p class="text-[9px] text-gray-400 mt-0.5">${receivedAt}</p>
       </div>`
                        : `<span class="bg-blue-100 text-blue-700 text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">For Receive</span>`;
                    const actionHtml = isReceived
                        ? `<button onclick='viewRequest(${JSON.stringify(row).replace(/"/g, '&quot;')})'
                            class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
                            <i class="fa-solid fa-eye mr-1"></i>View
                           </button>`
                        : `<div class="flex items-center gap-2">
                            <button onclick='viewRequest(${JSON.stringify(row).replace(/"/g, '&quot;')})'
                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-eye mr-1"></i>View
                            </button>
                            <button onclick="markReceived(${row.id})"
                                class="bg-blue-500 hover:bg-blue-600 text-white text-[10px] font-semibold px-3 py-1 rounded-full transition-all">
                                <i class="fa-solid fa-check mr-1"></i>Mark Received
                            </button>
                           </div>`;

                    return `
                    <tr data-id="${row.id}" class="border-t border-gray-100 transition-colors ${rowClass}">
                        <td class="px-5 py-3 font-mono text-xs text-blue-900 cursor-pointer"
                            onclick='viewRequest(${JSON.stringify(row).replace(/"/g, '&quot;')})'>
                            ${row.control_no}
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-800">${row.requestor_name}</p>
                            <p class="text-[10px] text-gray-400">${row.sender_email ?? ''}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600">${row.purpose}</td>
                        <td class="px-5 py-3 font-mono text-xs font-semibold text-gray-700">
                            ₱ ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-sm text-gray-700">${row.approver_name ?? ''}</p>
                            <p class="text-[10px] text-gray-400">${row.approved_at
                            ? new Date(row.approved_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                            + ' ' + new Date(row.approved_at.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                            : ''}</p>
                        </td>
                        <td class="px-5 py-3">${categoryBadge(row.request_category, row.request_reference)}</td>
                        <td class="px-5 py-3">${statusBadgeHtml}</td>
                        <td class="px-5 py-3">${actionHtml}</td>
                    </tr>`;
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

        // Dagdagan sa itaas ng fetchApproved function
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

        function showCatTooltip(e, label, reference) {
            const tip = document.getElementById('cat-tooltip');
            document.getElementById('cat-tooltip-label').textContent = label;
            document.getElementById('cat-tooltip-ref').textContent = reference;
            tip.style.visibility = 'hidden';
            tip.classList.remove('hidden');
            const rect = e.currentTarget.getBoundingClientRect();
            const tipW = tip.offsetWidth;
            const tipH = tip.offsetHeight;
            let left = rect.left + (rect.width / 2) - (tipW / 2);
            left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));
            let top = rect.top - tipH - 8;
            if (top < 8) top = rect.bottom + 8;
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
            tip.style.visibility = 'visible';
        }

        function hideCatTooltip() {
            document.getElementById('cat-tooltip').classList.add('hidden');
        }
        // ─── Fetch ───────────────────────────────────────────
        function fetchApproved() {
            fetch('<?= BASE_URL ?>/fetchapproved')
                .then(res => res.json())
                .then(data => {
                    const countChanged = data.length !== previousCount;

                    if (countChanged || !highlightDone) {
                        previousCount = data.length;
                        allRequests = data;

                        renderCalendar();
                        buildDatesDropdown();

                        if (selectedDate) {
                            const filtered = allRequests.filter(r => r.date_requested?.startsWith(selectedDate));
                            openDayPanel(selectedDate, filtered);
                        }

                        document.getElementById('last-updated').textContent =
                            'Updated ' + new Date().toLocaleTimeString('en-PH');
                    }

                    if (!highlightDone) {
                        highlightDone = true;
                        checkHighlight();
                    }
                })
                .catch(err => console.error('Fetch error:', err));
        }

        // ─── Mark Received ───────────────────────────────────
        function markReceived(id) {
            fetch('<?= BASE_URL ?>/markreceived', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Request marked as received!', 'received');
                        previousCount = 0;
                        highlightDone = false; // ← dagdag ito
                        fetchApproved();
                    }
                });
        }

        let currentViewRow = null;

        // ─── View Modal ──────────────────────────────────────
        function viewRequest(row) {
            currentViewRow = row;
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

            const statusMap = {
                approved: 'bg-green-300 text-green-900',
                received: 'bg-blue-100 text-blue-700',
            };
            const statusLabel = row.receiver_name ? 'received' : 'approved';
            const statusClass = row.receiver_name ? statusMap.received : statusMap.approved;
            document.getElementById('view-status-badge').innerHTML =
                `<span class="${statusClass} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">${statusLabel}</span>`;

            const approverName = row.approver_name ?? '';
            const approvedAt = row.approved_at
                ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                + ' ' + new Date(row.approved_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                : '';
            const approverSig = row.approver_signature ?? '';
            document.getElementById('view-approved-by').innerHTML = approverName
                ? `<div class="relative inline-block">
        ${approverSig
                    ? `<img src="<?= BASE_URL ?>/${approverSig}" 
                   alt="Signature" 
                   style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); height:80px; max-width:220px; z-index:10; pointer-events:none;">`
                    : ''}
        <p class="text-sm font-semibold text-gray-800">${approverName}</p>
        <p class="text-[10px] text-gray-400">${approvedAt}</p>
       </div>`
                : '';

            const receiverName = row.receiver_name ?? '';
            const receivedAt = row.received_at
                ? new Date(row.received_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                + ' ' + new Date(row.received_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                : '';
            const receiverSig = row.receiver_signature ?? '';
            document.getElementById('view-received-by').innerHTML = receiverName
                ? `<div class="relative inline-block">
        ${receiverSig
                    ? `<img src="<?= BASE_URL ?>/${receiverSig}" 
                   alt="Signature" 
                   style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); height:80px; max-width:220px; z-index:10; pointer-events:none;">`
                    : ''}
        <p class="text-sm font-semibold text-gray-800">${receiverName}</p>
        <p class="text-[10px] text-gray-400">${receivedAt}</p>
       </div>`
                : '';
            // Attachments
            let attachments = [];
            try {
                const raw = row.attachments;
                attachments = Array.isArray(raw) ? raw : (typeof raw === 'string' && raw.trim() ? JSON.parse(raw) : []);
            } catch (e) { attachments = []; }

            const attachSection = document.getElementById('view-attachments');
            // ── attachment_status badge ──
            const oldBadge = document.getElementById('view-attachment-status-badge');
            if (oldBadge) oldBadge.remove();

            if ((row.attachment_status ?? 'attached') === 'follow_up') {
                const badge = document.createElement('div');
                badge.id = 'view-attachment-status-badge';
                badge.className = 'flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-2 mx-6 mt-3';
                badge.innerHTML = `
        <i class="fa-solid fa-clock text-yellow-500 text-xs"></i>
        <span class="text-xs font-semibold text-yellow-700">Attachment pending — requestor will follow up</span>
    `;
                document.getElementById('view-attachments').before(badge);
            }
            if (attachments.length) {
                attachSection.classList.remove('hidden');
                document.getElementById('view-attachments-grid').innerHTML = attachments.map(path => {
                    const isPdf = path.toLowerCase().endsWith('.pdf');
                    const fullUrl = `<?= BASE_URL ?>/${path}`;

                    if (isPdf) {
                        return `
        <a href="${fullUrl}" target="_blank"
            class="relative flex flex-col items-center justify-center w-20 h-20 rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all bg-red-50 cursor-pointer gap-1">
            <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
            <span class="text-[9px] text-red-400 font-semibold uppercase tracking-wide">PDF</span>
        </a>`;
                    }

                    return `
    <div class="relative group/thumb cursor-pointer" onclick="openLightbox('${fullUrl}', event)">
        <img src="${fullUrl}"
            class="w-20 h-20 object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all">
        <div class="absolute inset-0 bg-black/0 group-hover/thumb:bg-black/20 rounded-lg transition-all flex items-center justify-center">
            <i class="fa-solid fa-magnifying-glass text-white opacity-0 group-hover/thumb:opacity-100 transition-all text-xs"></i>
        </div>
    </div>`;
                }).join('');
            } else {
                attachSection.classList.add('hidden');
            }

            document.getElementById('view-modal').classList.remove('hidden');
        }

        function closeViewModal() { document.getElementById('view-modal').classList.add('hidden'); }

        // ─── Print ───────────────────────────────────────────
        function printRequest(row) {
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
            const approverName = row.approver_name ?? '';
            const approvedAt = row.approved_at
                ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                + ' ' + new Date(row.approved_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                : '';
            const receiverName = row.receiver_name ?? '';
            const receivedAt = row.received_at
                ? new Date(row.received_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                + ' ' + new Date(row.received_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                : '';
            const approverSigPath = row.approver_signature ? `<?= BASE_URL ?>/${row.approver_signature}` : '';
            const receiverSigPath = row.receiver_signature ? `<?= BASE_URL ?>/${row.receiver_signature}` : '';

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
    <div style="margin-top:20px;text-align:center;position:relative;">${receiverSigPath ? `<img src="${receiverSigPath}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}<strong style="font-size:12px;">${receiverName}</strong><br><span style="font-size:9px;color:#888;">${receivedAt}</span></div>
    <div class="sig-line"></div><div style="text-align:center;font-size:9px;color:#888;margin-top:3px;">&nbsp;</div></td></tr></table>
    </div><script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script></body></html>`;

            const w = window.open('', '_blank');
            w.document.write(html);
            w.document.close();
        }

        // ─── Lightbox ────────────────────────────────────────
        function openLightbox(src, e) {
            if (e) e.stopPropagation();
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.remove('hidden');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.getElementById('lightbox-img').src = '';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeLightbox(); closeViewModal(); } });

        // ─── Toast ───────────────────────────────────────────
        function showToast(message, type = 'received') {
            const existing = document.getElementById('toast-notif');
            if (existing) existing.remove();
            const color = type === 'received' ? 'bg-blue-500' : 'bg-green-500';
            const icon = type === 'received' ? 'fa-circle-check' : 'fa-check';
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

        // ─── Dates Dropdown ──────────────────────────────────
        let allDatesCache = [];

        function buildDatesDropdown() {
            const byDate = {};
            allRequests.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (!d) return;
                if (!byDate[d]) byDate[d] = { forReceive: 0, received: 0, total: 0 };
                if (r.receiver_name) byDate[d].received++;
                else byDate[d].forReceive++;
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
            ul.innerHTML = dates.map(({ date, forReceive, received, total }) => {
                const d = new Date(date + 'T00:00:00');
                const label = d.toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                const dots = [
                    forReceive ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-400"  title="${forReceive} for receive"></span>` : '',
                    received ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-green-400" title="${received} received"></span>` : '',
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
            calYear = d.getFullYear();
            calMonth = d.getMonth();
            renderCalendar();
            const requests = allRequests.filter(r => r.date_requested?.startsWith(dateStr));
            openDayPanel(dateStr, requests);
            document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Close dropdown on outside click
        document.addEventListener('click', () => {
            document.getElementById('dates-dropdown').classList.add('hidden');
        });

        // ─── Highlight (from ping/redirect) ──────────────────
        // PALITAN ANG BUONG checkHighlight() ng:
        function checkHighlight() {
            const params = new URLSearchParams(window.location.search);
            const highlightId = params.get('highlight');
            const jumpDate = params.get('date'); // format: YYYY-MM-DD

            if (!highlightId) return;

            // Kung may date, jump ang calendar doon
            if (jumpDate) {
                const d = new Date(jumpDate + 'T00:00:00');
                calYear = d.getFullYear();
                calMonth = d.getMonth();
                renderCalendar();

                // Open ang day panel para sa date na iyon
                const requests = allRequests.filter(r => r.date_requested?.startsWith(jumpDate));
                openDayPanel(jumpDate, requests);
            }

            // Highlight ang row sa day panel
            const interval = setInterval(() => {
                const row = document.querySelector(`tr[data-id="${highlightId}"]`);
                if (row) {
                    clearInterval(interval);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    const firstTd = row.querySelector('td:first-child');
                    const badge = document.createElement('span');
                    badge.className = 'highlight-badge';
                    firstTd.prepend(badge);
                    setTimeout(() => badge.remove(), 5000);
                }
            }, 200);
            setTimeout(() => clearInterval(interval), 5000);
        }

        const style = document.createElement('style');
        style.textContent = `
            @keyframes badgePulse {
                0%   { transform: scale(1);   opacity: 1; }
                50%  { transform: scale(1.4); opacity: 0.5; }
                100% { transform: scale(1);   opacity: 1; }
            }
            .highlight-badge {
                display: inline-block;
                width: 10px; height: 10px;
                background-color: #ef4444;
                border-radius: 50%;
                animation: badgePulse 0.8s ease-in-out 6;
                margin-right: 6px;
                vertical-align: middle;
                flex-shrink: 0;
            }
        `;
        document.head.appendChild(style);

        // ─── Init ────────────────────────────────────────────
        fetchApproved();
        setInterval(fetchApproved, 5000);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') { previousCount = 0; fetchApproved(); }
        });
    </script>
</body>

</html>