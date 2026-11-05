<?php
//createaccount-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_IT];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">

    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8">
        <div class="max-w-7xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-xl mb-4 overflow-hidden">
                <div class="h-1.5" style="background:repeating-linear-gradient(-45deg,#f59e0b 0 10px,#1f2937 10px 20px)"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-users-gear text-lg"></i></span>
                        <div>
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">IT · Accounts</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Manage Accounts <span id="acc-count" class="ml-1 text-[12px] font-medium text-gray-400"></span></h1>
                            <p class="text-gray-400 text-[11px] mt-0.5 flex items-center gap-1.5">
                                <span id="pulse" class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                                <span id="last-updated">All registered department accounts</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="openSettingsModal('department')" class="inline-flex items-center gap-2 h-10 px-3.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-300 hover:text-amber-700"><i class="fa-regular fa-building"></i> Departments</button>
                        <button type="button" onclick="openSettingsModal('branch')" class="inline-flex items-center gap-2 h-10 px-3.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-300 hover:text-amber-700"><i class="fa-solid fa-location-dot"></i> Branches</button>
                        <a href="<?= BASE_URL ?>/superad" class="inline-flex items-center gap-2 h-10 px-4 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-user-plus"></i> New Account</a>
                    </div>
                </div>

                <!-- stat cards -->
                <div class="px-5 pb-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <?php foreach ([['stat-total', 'Total accounts', 'fa-users', 'text-gray-900'], ['stat-head', 'Heads', 'fa-user-tie', 'text-amber-700'], ['stat-staff', 'Staff', 'fa-user', 'text-blue-700'], ['stat-nobranch', 'No branch', 'fa-circle-exclamation', 'text-red-600']] as [$sid, $slbl, $sico, $scls]): ?>
                        <div class="rounded-xl border border-gray-200 bg-white px-3.5 py-2.5">
                            <span class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"><i class="fa-solid <?= $sico ?> text-[10px]"></i><?= $slbl ?></span>
                            <span id="<?= $sid ?>" class="block text-xl font-bold leading-tight tabular-nums <?= $scls ?>">0</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- filters -->
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 space-y-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mr-1"><i class="fa-solid fa-location-dot mr-1 text-amber-600"></i>Branch</span>
                        <div id="branch-chips" class="flex items-center gap-1.5 flex-wrap"></div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="relative flex-1 min-w-[220px] max-w-sm">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input id="acc-search" type="text" placeholder="Search name or email…"
                                class="w-full h-9 border border-gray-200 rounded-lg pl-8 pr-3 text-xs text-gray-800 bg-white outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition">
                        </div>
                        <select id="role-filter" class="h-9 border border-gray-200 rounded-lg px-3 text-xs text-gray-700 bg-white outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 cursor-pointer">
                            <option value="">All departments</option>
                        </select>
                        <select id="position-filter" class="h-9 border border-gray-200 rounded-lg px-3 text-xs text-gray-700 bg-white outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 cursor-pointer">
                            <option value="">All positions</option>
                            <option value="staff">Staff</option>
                            <option value="head">Head</option>
                            <option value="custodian">Custodian</option>
                            <option value="custoassistant">Custodian Assistant</option>
                        </select>
                        <button type="button" id="clear-filters" onclick="clearFilters()" class="hidden text-[11px] font-semibold text-gray-500 hover:text-amber-700 px-2 h-9">
                            <i class="fa-solid fa-xmark mr-1"></i>Clear filters
                        </button>
                    </div>
                </div>
            </section>

            <!-- ═══ TABLE ═══ -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <!-- tabs: All / Heads / Staff (hindi kasama sa scroll) -->
                <div id="acc-group-tabs" class="px-5 py-2.5 border-b border-gray-100 bg-gray-50/70"></div>
                <!-- scrollable: header ng table nakadikit sa taas habang nag-i-scroll -->
                <div id="acc-scroll" class="overflow-auto [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent]">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 text-[10px] font-semibold text-gray-500 uppercase tracking-wider shadow-[inset_0_-1px_0_#f3f4f6]">
                                <th class="px-5 py-3 text-left w-14">ID</th>
                                <th class="px-3 py-3 text-left">Account</th>
                                <th class="px-3 py-3 text-left">Department</th>
                                <th class="px-3 py-3 text-left">Position</th>
                                <th class="px-3 py-3 text-left">Branch</th>
                                <th class="px-3 py-3 text-left">Created</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="accounts-tbody">
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- ═══ EDIT MODAL ═══ -->
    <div id="edit-modal" class="fixed inset-0 z-[9998] hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="h-1" style="background:repeating-linear-gradient(-45deg,#f59e0b 0 8px,#1f2937 8px 16px)"></div>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-user-pen"></i></span>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Edit Account</h2>
                        <p id="edit-sub" class="text-[11px] text-gray-400"></p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form class="px-5 py-4 space-y-4" onsubmit="return false;">
                <input type="hidden" id="edit-id">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Name</label>
                    <input id="edit-name" type="text" placeholder="Full name"
                        class="w-full h-10 border border-gray-200 rounded-lg px-3 text-sm text-gray-800 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">New password</label>
                    <div class="relative">
                        <input id="edit-password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current"
                            class="w-full h-10 border border-gray-200 rounded-lg px-3 pr-10 text-sm text-gray-800 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md text-gray-400 hover:text-gray-700">
                            <i id="toggle-eye" class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                    <p class="text-[10.5px] text-gray-400 mt-1">Leave blank if you don't want to change the password.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Confirm password</label>
                    <input id="edit-confirm-password" type="password" autocomplete="new-password" placeholder="Repeat new password"
                        class="w-full h-10 border border-gray-200 rounded-lg px-3 text-sm text-gray-800 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition">
                </div>
                <p id="edit-error" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 hidden"></p>
            </form>

            <div class="flex gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50/70">
                <button onclick="closeEditModal()" class="flex-1 h-10 border border-gray-200 bg-white text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50">Cancel</button>
                <button onclick="saveEdit()" class="flex-1 h-10 bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold rounded-lg shadow-sm"><i class="fa-solid fa-floppy-disk mr-1"></i> Save changes</button>
            </div>
        </div>
    </div>

    <!-- ═══ SETTINGS MODAL (Department / Branch) ═══ -->
    <div id="settings-modal" class="fixed inset-0 z-[9998] hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="h-1" style="background:repeating-linear-gradient(-45deg,#f59e0b 0 8px,#1f2937 8px 16px)"></div>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <span id="settings-icon" class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-regular fa-building"></i></span>
                    <h2 id="settings-title" class="text-sm font-semibold text-gray-900">Manage Departments</h2>
                </div>
                <button onclick="closeSettingsModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4">
                <div class="flex gap-2 mb-3">
                    <input id="settings-new-name" type="text" placeholder="New name"
                        class="flex-1 h-10 border border-gray-200 rounded-lg px-3 text-sm text-gray-800 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition">
                    <button onclick="addSettingsItem()" class="inline-flex items-center gap-1.5 h-10 px-4 bg-amber-700 hover:bg-amber-800 text-white text-xs font-semibold rounded-lg"><i class="fa-solid fa-plus"></i> Add</button>
                </div>
                <p id="settings-error" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 hidden mb-3"></p>
                <div id="settings-list" class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                    <p class="text-xs text-gray-400 text-center py-4">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    <div id="acc-toast" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none"></div>

    <script>
        let previousCount = 0;
        let branchList = []; // cached list of available branches for the per-row select
        let allRows = [];    // lahat ng account galing hrfetch (dito kinukuha ang filter)
        let lastHash = '';
        let branchFilter = '';   // '' = lahat · '__none__' = walang branch · o pangalan ng branch

        const accEsc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

        function accToast(msg, ok = true) {
            const t = document.createElement('div');
            t.className = `pointer-events-auto px-4 py-2.5 rounded-lg shadow-lg text-xs font-medium border ${ok ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'}`;
            t.textContent = msg;
            document.getElementById('acc-toast').appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        // ─── Filters ──────────────────────────────────────────────────

        function filteredRows() {
            const q = document.getElementById('acc-search').value.trim().toLowerCase();
            const role = document.getElementById('role-filter').value;
            const pos = document.getElementById('position-filter').value;
            return allRows.filter(r =>
                (branchFilter === '' || (branchFilter === '__none__' ? !r.branch : r.branch === branchFilter)) &&
                (!role || r.role === role) &&
                (!pos || r.position === pos) &&
                (!q || `${r.name} ${r.email}`.toLowerCase().includes(q))
            );
        }

        function renderBranchChips() {
            const count = b => b === '' ? allRows.length : b === '__none__' ? allRows.filter(r => !r.branch).length : allRows.filter(r => r.branch === b).length;
            const chips = [['', 'All'], ...branchList.map(b => [b.name, b.name])];
            if (allRows.some(r => !r.branch)) chips.push(['__none__', 'No branch']);
            // kung napili ang branch na binura na, balik sa All
            if (branchFilter && !chips.some(([v]) => v === branchFilter)) branchFilter = '';
            document.getElementById('branch-chips').innerHTML = chips.map(([v, label]) => {
                const on = v === branchFilter;
                return `<button type="button" data-branch="${accEsc(v).replace(/"/g, '&quot;')}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold border transition
                    ${on ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-white border-gray-200 text-gray-600 hover:border-amber-300 hover:text-amber-700'}">
                    ${accEsc(label)}<span class="px-1.5 rounded-full text-[10px] ${on ? 'bg-white/25' : 'bg-gray-100 text-gray-500'}">${count(v)}</span></button>`;
            }).join('');
        }

        function renderRoleOptions() {
            const sel = document.getElementById('role-filter');
            const cur = sel.value;
            const roles = [...new Set(allRows.map(r => r.role).filter(Boolean))].sort();
            sel.innerHTML = '<option value="">All departments</option>' + roles.map(r => `<option value="${accEsc(r)}">${accEsc(r)}</option>`).join('');
            sel.value = roles.includes(cur) ? cur : '';
        }

        // ─── Hatiin: Head · Staff (kasama ang Custodian at Custodian Assistant) ───
        const isHeadRow = r => r.position === 'head';
        let groupView = 'all';   // 'all' | 'head' | 'staff'
        try { groupView = sessionStorage.getItem('nh-acc-group') || 'all'; } catch (e) { }

        // ─── 6 na account lang ang kita, scroll na ang iba ───
        const VISIBLE_ROWS = 6;
        function fitRows() {
            const box = document.getElementById('acc-scroll');
            const rows = [...document.querySelectorAll('#accounts-tbody > tr')];
            let seen = 0, h = box.querySelector('thead').offsetHeight;
            for (const tr of rows) {
                if (seen >= VISIBLE_ROWS) break;
                h += tr.offsetHeight;
                if (tr.hasAttribute('data-acc')) seen++;
            }
            const total = rows.filter(r => r.hasAttribute('data-acc')).length;
            box.style.maxHeight = total > VISIBLE_ROWS ? h + 'px' : '';
        }
        window.addEventListener('resize', fitRows);

        function setGroupView(v) {
            groupView = v;
            try { sessionStorage.setItem('nh-acc-group', v); } catch (e) { }
            refresh();
        }

        function groupHtml(data, rowFn) {
            const heads = data.filter(isHeadRow), staff = data.filter(r => !isHeadRow(r));
            const tabs = [
                ['all', 'All', 'fa-users-line', data.length],
                ['head', 'Heads', 'fa-user-tie', heads.length],
                ['staff', 'Staff', 'fa-user', staff.length],
            ];
            // tabs: Head · Staff
            document.getElementById('acc-group-tabs').innerHTML = `
                <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-white border border-gray-200">
                    ${tabs.map(([v, label, icon, n]) => {
                        const on = groupView === v;
                        return `<button type="button" data-group="${v}"
                            class="inline-flex items-center gap-2 h-8 px-3.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition
                            ${on ? (v === 'head' ? 'bg-gray-900 text-amber-400 shadow-sm' : 'bg-amber-600 text-white shadow-sm') : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800'}">
                            <i class="fa-solid ${icon}"></i>${label}
                            <span class="px-1.5 py-px rounded-full text-[10px] ${on ? 'bg-white/20' : 'bg-gray-100 text-gray-500'}">${n}</span></button>`;
                    }).join('')}
                </div>`;
            const bar = '';

            const empty = label => `<tr><td colspan="7" class="px-5 py-6 text-center text-xs text-gray-400 italic">No ${label} in this filter.</td></tr>`;
            const section = (label, icon, cls, rows) => `
                <tr><td colspan="7" class="px-5 pt-4 pb-1.5 ${label === 'Staff' ? 'border-t-4 border-gray-100' : ''}">
                    <span class="inline-flex items-center gap-1.5 text-[10.5px] font-bold uppercase tracking-wider ${cls}"><i class="fa-solid ${icon}"></i>${label} · ${rows.length}</span>
                </td></tr>
                ${rows.length ? rows.map(rowFn).join('') : empty(label.toLowerCase())}`;

            if (!data.length) return bar + `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">No accounts match the filters. <button type="button" onclick="clearFilters()" class="ml-1 font-semibold text-amber-700 hover:underline">Clear filters</button></td></tr>`;
            if (groupView === 'head') return bar + (heads.length ? heads.map(rowFn).join('') : empty('heads'));
            if (groupView === 'staff') return bar + (staff.length ? staff.map(rowFn).join('') : empty('staff'));
            return bar + section('Heads', 'fa-user-tie', 'text-amber-700', heads) + section('Staff', 'fa-users', 'text-gray-600', staff);
        }

        document.getElementById('acc-group-tabs').addEventListener('click', e => {
            const g = e.target.closest('[data-group]');
            if (g) setGroupView(g.dataset.group);
        });

        function setBranchFilter(v) { branchFilter = v; refresh(); }
        document.getElementById('branch-chips').addEventListener('click', e => {
            const b = e.target.closest('[data-branch]');
            if (b) setBranchFilter(b.dataset.branch);
        });

        function clearFilters() {
            branchFilter = '';
            groupView = 'all';
            try { sessionStorage.setItem('nh-acc-group', 'all'); } catch (e) { }
            document.getElementById('acc-search').value = '';
            document.getElementById('role-filter').value = '';
            document.getElementById('position-filter').value = '';
            refresh();
        }

        function renderStats() {
            const set = (id, n) => { const el = document.getElementById(id); if (el) el.textContent = n; };
            set('stat-total', allRows.length);
            set('stat-head', allRows.filter(r => r.position === 'head').length);
            set('stat-staff', allRows.filter(r => r.position !== 'head').length);
            set('stat-nobranch', allRows.filter(r => !r.branch).length);
        }

        function refresh() {
            renderStats();
            renderBranchChips();
            const list = filteredRows();
            const on = branchFilter || groupView !== 'all' || document.getElementById('acc-search').value.trim() || document.getElementById('role-filter').value || document.getElementById('position-filter').value;
            document.getElementById('clear-filters').classList.toggle('hidden', !on);
            document.getElementById('acc-count').textContent = on ? `· ${list.length} of ${allRows.length}` : `· ${allRows.length}`;
            if (!allRows.length) {
                document.getElementById('acc-group-tabs').innerHTML = '';
                document.getElementById('accounts-tbody').innerHTML =
                    `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">No accounts found.</td></tr>`;
                return;
            }
            renderAccounts(list);   // laging may tabs (All / Heads / Staff), kahit walang tugma sa filter
        }

        // ─── Accounts Table ───────────────────────────────────────────

        function renderAccounts(data) {
            const tbody = document.getElementById('accounts-tbody');

            const POS_CLS = { head: 'bg-amber-100 text-amber-800', staff: 'bg-gray-100 text-gray-600', custodian: 'bg-violet-50 text-violet-700', custoassistant: 'bg-violet-50 text-violet-700' };
            const fmtCreated = v => { const d = new Date(String(v || '').replace(' ', 'T')); return isNaN(d) ? accEsc(v || '') : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }); };
            tbody.innerHTML = groupHtml(data, (row) => `
        <tr data-acc class="border-t border-gray-100 hover:bg-amber-50/40 transition-colors">
            <td class="px-5 py-3 text-gray-400 font-mono text-[11px]">#${row.id}</td>
            <td class="px-3 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-9 h-9 rounded-lg ${row.position === 'head' ? 'bg-gray-900 text-amber-400' : 'bg-amber-50 text-amber-700'} text-[13px] font-bold flex items-center justify-center shrink-0">${accEsc((row.name || '?').trim().charAt(0).toUpperCase())}</span>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 text-[13px] truncate">${accEsc(row.name)}</p>
                        <p class="text-[11px] text-gray-400 truncate">${accEsc(row.email)}</p>
                    </div>
                </div>
            </td>
            <td class="px-3 py-3">
                <span class="inline-block max-w-[220px] truncate align-middle bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-semibold px-2 py-1 rounded-md uppercase tracking-wide" title="${accEsc(row.role)}">${accEsc(row.role)}</span>
            </td>
            <td class="px-3 py-3">
                <select onchange="changePosition(${row.id}, this.value)"
                    class="${POS_CLS[row.position] || POS_CLS.staff} h-7 text-[10.5px] font-semibold pl-2.5 pr-7 rounded-md uppercase tracking-wide border-0 outline-none cursor-pointer focus:ring-2 focus:ring-amber-200">
                    <option value="staff" ${row.position === 'staff' ? 'selected' : ''}>Staff</option>
                    <option value="head" ${row.position === 'head' ? 'selected' : ''}>Head</option>
                    <option value="custodian" ${row.position === 'custodian' ? 'selected' : ''}>Custodian</option>
                    <option value="custoassistant" ${row.position === 'custoassistant' ? 'selected' : ''}>Custodian Asst.</option>
                </select>
            </td>
            <td class="px-3 py-3">
                <select onchange="changeBranch(${row.id}, this)"
                    class="${row.branch ? 'bg-sky-50 text-sky-700' : 'bg-red-50 text-red-600'} h-7 text-[10.5px] font-semibold pl-2.5 pr-7 rounded-md uppercase tracking-wide border-0 outline-none cursor-pointer focus:ring-2 focus:ring-amber-200">
                    <option value="">— None —</option>
                    ${branchList.map(b => `<option value="${accEsc(b.name)}" ${row.branch === b.name ? 'selected' : ''}>${accEsc(b.name)}</option>`).join('')}
                    ${row.branch && !branchList.some(b => b.name === row.branch) ? `<option value="${accEsc(row.branch)}" selected>${accEsc(row.branch)} (removed)</option>` : ''}
                </select>
            </td>
            <td class="px-3 py-3 text-gray-500 text-[11.5px] whitespace-nowrap" title="${accEsc(row.created_at)}">${fmtCreated(row.created_at)}</td>
            <td class="px-5 py-3 text-right">
                <button onclick="openEditModal(${row.id})"
                    class="inline-flex items-center gap-1.5 h-8 px-3 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-300 hover:text-amber-700">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </button>
            </td>
        </tr>
    `);

            fitRows();

            const now = new Date();
            document.getElementById('last-updated').textContent =
                'Live · updated ' + now.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        }

        function fetchAccounts(forceRender = false) {
            fetch('<?= BASE_URL ?>/hrfetch')
                .then(res => res.json())
                .then(data => {
                    const rows = Array.isArray(data) ? data : [];
                    const hash = JSON.stringify(rows.map(r => [r.id, r.name, r.role, r.position, r.branch]));
                    if (!forceRender && hash === lastHash) return;
                    lastHash = hash;
                    allRows = rows;
                    previousCount = rows.length;
                    renderRoleOptions();
                    refresh();
                })
                .catch(() => {
                    document.getElementById('accounts-tbody').innerHTML =
                        `<tr><td colspan="7" class="px-5 py-8 text-center text-red-400">Failed to load data.</td></tr>`;
                });
        }

        function changePosition(id, newPosition) {
            fetch('<?= BASE_URL ?>/hrposition', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, position: newPosition })
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Could not update position.');
                    // ilipat sa tamang grupo (Heads / Staff)
                    const row = allRows.find(r => +r.id === +id);
                    if (row) row.position = newPosition;
                    lastHash = '';
                    accToast(`${row ? row.name : 'Account'} → ${newPosition === 'custoassistant' ? 'custodian assistant' : newPosition}`);
                    refresh();
                })
                .catch(e => { accToast(e.message || 'Network error. Try again.', false); fetchAccounts(true); });
        }

        function changeBranch(id, sel) {
            const row = allRows.find(r => +r.id === +id);
            const old = row ? (row.branch || '') : '';
            const newBranch = sel.value;
            sel.disabled = true;
            fetch('<?= BASE_URL ?>/updateitbranch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, branch: newBranch })
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Could not update branch.');
                    if (row) row.branch = data.branch || '';
                    lastHash = '';
                    accToast(`${row ? row.name : 'Account'} → ${data.branch || 'no branch'}`);
                    refresh();
                })
                .catch(e => {
                    sel.value = old;
                    sel.disabled = false;
                    accToast(e.message || 'Network error. Try again.', false);
                });
        }

        function loadBranchesForSelect() {
            return fetch('<?= BASE_URL ?>/crudbranches')
                .then(res => res.json())
                .then(data => { branchList = data; });
        }

        // ─── Edit Modal ───────────────────────────────────────────────

        function openEditModal(id) {
            const row = allRows.find(r => +r.id === +id);
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = row ? row.name : '';
            document.getElementById('edit-sub').textContent = row ? `${row.email} · ${row.role}` : '';
            document.getElementById('edit-password').value = '';
            document.getElementById('edit-confirm-password').value = '';
            document.getElementById('edit-error').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('hidden');
            document.getElementById('edit-modal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('edit-password');
            const icon = document.getElementById('toggle-eye');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function saveEdit() {
            const id = document.getElementById('edit-id').value;
            const name = document.getElementById('edit-name').value.trim();
            const password = document.getElementById('edit-password').value;
            const confirmPassword = document.getElementById('edit-confirm-password').value;
            const errorEl = document.getElementById('edit-error');

            if (!name) {
                errorEl.textContent = 'Name is required.';
                errorEl.classList.remove('hidden');
                return;
            }

            if (password && password !== confirmPassword) {
                errorEl.textContent = 'Passwords do not match.';
                errorEl.classList.remove('hidden');
                return;
            }

            errorEl.classList.add('hidden');

            const payload = { id, name };
            if (password) payload.password = password;

            fetch('<?= BASE_URL ?>/hrupdate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeEditModal();
                        fetchAccounts(true);
                    } else {
                        errorEl.textContent = data.message || 'Update failed. Try again.';
                        errorEl.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    errorEl.textContent = 'Network error. Try again.';
                    errorEl.classList.remove('hidden');
                });
        }

        document.getElementById('edit-modal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        // ─── Settings Modal (Department / Branch CRUD) ─────────────────

        let currentSettingsType = 'department'; // 'department' | 'branch'

        const settingsConfig = {
            department: { endpoint: '<?= BASE_URL ?>/cruddepartments', title: 'Manage Departments', placeholder: 'e.g. Human Resources' },
            branch: { endpoint: '<?= BASE_URL ?>/crudbranches', title: 'Manage Branches', placeholder: 'e.g. Main Branch' }
        };

        function openSettingsModal(type) {
            currentSettingsType = type;
            const cfg = settingsConfig[type];
            document.getElementById('settings-title').textContent = cfg.title;
            document.getElementById('settings-icon').innerHTML = `<i class="${type === 'branch' ? 'fa-solid fa-location-dot' : 'fa-regular fa-building'}"></i>`;
            document.getElementById('settings-new-name').value = '';
            document.getElementById('settings-new-name').placeholder = cfg.placeholder;
            document.getElementById('settings-error').classList.add('hidden');
            document.getElementById('settings-modal').classList.remove('hidden');
            document.getElementById('settings-modal').classList.add('flex');
            fetchSettingsList();
        }

        function closeSettingsModal() {
            document.getElementById('settings-modal').classList.add('hidden');
            document.getElementById('settings-modal').classList.remove('flex');
        }

        document.getElementById('settings-modal').addEventListener('click', function (e) {
            if (e.target === this) closeSettingsModal();
        });

        function fetchSettingsList() {
            const cfg = settingsConfig[currentSettingsType];
            const listEl = document.getElementById('settings-list');
            listEl.innerHTML = `<p class="text-xs text-gray-400 text-center py-4">Loading...</p>`;

            fetch(cfg.endpoint)
                .then(res => res.json())
                .then(data => renderSettingsList(data))
                .catch(() => {
                    listEl.innerHTML = `<p class="text-xs text-red-400 text-center py-4">Failed to load list.</p>`;
                });
        }

        function renderSettingsList(data) {
            const listEl = document.getElementById('settings-list');

            if (!data.length) {
                listEl.innerHTML = `<p class="text-xs text-gray-400 text-center py-4">No entries yet.</p>`;
                return;
            }

            listEl.innerHTML = data.map(item => `
        <div class="flex items-center gap-1.5 border ${Number(item.is_main) === 1 ? 'border-amber-200 bg-amber-50/50' : 'border-gray-200'} rounded-lg pl-3 pr-1.5 py-1.5" data-id="${item.id}">
            <span class="settings-item-name flex-1 min-w-0 truncate text-[13px] text-gray-800">${accEsc(item.name)}</span>
            ${Number(item.is_main) === 1 ? '<span class="text-[9.5px] font-bold uppercase tracking-wide bg-amber-600 text-white px-1.5 py-0.5 rounded">Main</span>' : ''}
            <button onclick="startEditSettingsItem(${item.id}, this)" title="Edit"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-gray-800 hover:bg-gray-100"><i class="fa-solid fa-pen text-xs"></i></button>
            <button onclick="deleteSettingsItem(${item.id})" title="Delete"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-regular fa-trash-can text-xs"></i></button>
        </div>
    `).join('');
        }

        function addSettingsItem() {
            const cfg = settingsConfig[currentSettingsType];
            const nameInput = document.getElementById('settings-new-name');
            const name = nameInput.value.trim();
            const errorEl = document.getElementById('settings-error');

            if (!name) {
                errorEl.textContent = 'Name is required.';
                errorEl.classList.remove('hidden');
                return;
            }

            fetch(cfg.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', name })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        nameInput.value = '';
                        errorEl.classList.add('hidden');
                        fetchSettingsList();
                        if (currentSettingsType === 'branch') loadBranchesForSelect().then(() => fetchAccounts(true));
                    } else {
                        errorEl.textContent = data.message || 'Failed to add.';
                        errorEl.classList.remove('hidden');
                    }
                });
        }

        function startEditSettingsItem(id, btn) {
            const row = btn.closest('[data-id]');
            const nameSpan = row.querySelector('.settings-item-name');
            const currentName = nameSpan.textContent;

            row.className = 'flex items-center gap-1.5 border border-amber-200 rounded-lg pl-1.5 pr-1.5 py-1.5';
            row.innerHTML = `
        <input type="text" value="${accEsc(currentName).replace(/"/g, '&quot;')}"
            class="flex-1 min-w-0 h-8 border border-amber-300 rounded-md px-2 text-[13px] text-gray-800 outline-none focus:ring-2 focus:ring-amber-100"
            id="settings-edit-input-${id}">
        <button onclick="confirmEditSettingsItem(${id})" title="Save" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-emerald-600 hover:bg-emerald-50"><i class="fa-solid fa-check text-xs"></i></button>
        <button onclick="fetchSettingsList()" title="Cancel" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xs"></i></button>
    `;
        }

        function confirmEditSettingsItem(id) {
            const cfg = settingsConfig[currentSettingsType];
            const input = document.getElementById(`settings-edit-input-${id}`);
            const name = input.value.trim();

            if (!name) return;

            fetch(cfg.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'edit', id, name })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetchSettingsList();
                        if (currentSettingsType === 'branch') loadBranchesForSelect().then(() => fetchAccounts(true));
                    }
                });
        }

        function deleteSettingsItem(id) {
            if (!confirm('Delete this entry?')) return;

            const cfg = settingsConfig[currentSettingsType];

            fetch(cfg.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetchSettingsList();
                        if (currentSettingsType === 'branch') loadBranchesForSelect().then(() => fetchAccounts(true));
                    }
                });
        }

        // ─── Initial Load ───────────────────────────────────────────

        document.getElementById('acc-search').addEventListener('input', refresh);
        document.getElementById('role-filter').addEventListener('change', refresh);
        document.getElementById('position-filter').addEventListener('change', refresh);

        document.getElementById('settings-new-name').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addSettingsItem(); } });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeEditModal(); closeSettingsModal(); } });

        loadBranchesForSelect().then(() => fetchAccounts(true));

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                fetchAccounts();
            }
        });
    </script>

</body>

</html>