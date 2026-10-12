<?php
// index-accounting-followups.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_STAFF, POSITION_CUSTOASSISTANT, POSITION_HEAD];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ── Follow-up requests: still waiting (follow_up) + already uploaded ──
// Requires column: followup_uploaded_at DATETIME NULL on noblebudgetrequest
$followups = [];
$pendingCount = 0;
$uploadedCount = 0;

$res = $conn->query("
    SELECT id, control_no, requestor_name, purpose, date_requested, status,
           request_category, request_reference, attachments, attachment_status,
           followup_uploaded_at
    FROM noblebudgetrequest
    WHERE attachment_status = 'follow_up' OR followup_uploaded_at IS NOT NULL
    ORDER BY (attachment_status = 'follow_up') DESC,
             followup_uploaded_at DESC,
             id DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['is_pending'] = $row['attachment_status'] === 'follow_up';
        $row['files'] = json_decode($row['attachments'] ?? '[]', true) ?: [];
        $row['is_pending'] ? $pendingCount++ : $uploadedCount++;
        $followups[] = $row;
    }
}

function accCategoryLabel(?string $cat): string
{
    return match ($cat) {
        'project' => 'Project',
        'client' => 'Client',
        'nhcc' => 'NHCC',
        default => '—',
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <style>
        #fu-table-wrap {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        #fu-table-wrap::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        #fu-table-wrap::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 9999px;
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- Header -->
        <div class="mb-5">
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">Follow-up Attachments</h1>
            <p class="text-sm text-gray-500 mt-1">
                Requests submitted with "Follow up attachment" — see who still needs to upload and who already did.
            </p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 gap-3 mb-5 max-w-xl">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Waiting for upload</p>
                <p class="text-2xl font-bold text-amber-500 mt-1" id="fu-count-pending"><?= (int) $pendingCount ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Already uploaded</p>
                <p class="text-2xl font-bold text-green-600 mt-1" id="fu-count-uploaded"><?= (int) $uploadedCount ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-3">
            <div class="flex items-center gap-1 bg-white border border-gray-200 rounded-lg p-1 w-fit">
                <button type="button" data-filter="all"
                    class="fu-tab px-3 py-1.5 rounded-md text-xs font-semibold transition-all">All</button>
                <button type="button" data-filter="pending"
                    class="fu-tab px-3 py-1.5 rounded-md text-xs font-semibold transition-all">Waiting</button>
                <button type="button" data-filter="uploaded"
                    class="fu-tab px-3 py-1.5 rounded-md text-xs font-semibold transition-all">Uploaded</button>
            </div>

            <!-- Date filter -->
            <div class="flex flex-wrap items-center gap-2">
                <select id="fu-date-field"
                    class="bg-white border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-orange-400 transition-colors">
                    <option value="requested">Date requested</option>
                    <option value="uploaded">Uploaded on</option>
                </select>
                <input type="date" id="fu-date-from" title="From"
                    class="bg-white border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-orange-400 transition-colors">
                <span class="text-xs text-gray-400">to</span>
                <input type="date" id="fu-date-to" title="To"
                    class="bg-white border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-orange-400 transition-colors">
                <button type="button" id="fu-date-clear"
                    class="hidden text-xs font-semibold text-gray-500 hover:text-red-500 px-2 py-2 transition-colors">
                    <i class="fa-solid fa-xmark mr-1"></i>Clear
                </button>
            </div>

            <div class="relative lg:ml-auto lg:w-72">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" id="fu-search" placeholder="Search name, control no., purpose..."
                    class="w-full bg-white border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-orange-400 transition-colors">
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div id="fu-table-wrap" class="overflow-auto max-h-[calc(100vh-22rem)]">
                <table class="w-full text-sm border-collapse min-w-[860px]">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 text-[10px] font-bold uppercase tracking-widest text-gray-500">
                            <th class="px-4 py-3 text-left border-b border-gray-200">Control No.</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Requestor</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Purpose</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Category</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Date requested</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Follow-up</th>
                            <th class="px-4 py-3 text-left border-b border-gray-200">Uploaded on</th>
                            <th class="px-4 py-3 text-center border-b border-gray-200">Files</th>
                        </tr>
                    </thead>
                    <tbody id="fu-tbody">
                        <?php foreach ($followups as $r): ?>
                            <?php
                            $reqDate = !empty($r['date_requested']) ? date('Y-m-d', strtotime($r['date_requested'])) : '';
                            $upDate = !empty($r['followup_uploaded_at']) ? date('Y-m-d', strtotime($r['followup_uploaded_at'])) : '';
                            ?>
                            <tr class="fu-row border-b border-gray-100 hover:bg-orange-50/40 transition-colors"
                                data-state="<?= $r['is_pending'] ? 'pending' : 'uploaded' ?>"
                                data-requested="<?= $reqDate ?>"
                                data-uploaded="<?= $upDate ?>"
                                data-search="<?= htmlspecialchars(strtolower(
                                    $r['control_no'] . ' ' . $r['requestor_name'] . ' ' . $r['purpose'] . ' ' . ($r['request_reference'] ?? '')
                                ), ENT_QUOTES) ?>">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-orange-600 whitespace-nowrap">
                                    <?= htmlspecialchars($r['control_no']) ?>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">
                                    <?= htmlspecialchars($r['requestor_name']) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-[260px] truncate"
                                    title="<?= htmlspecialchars($r['purpose']) ?>">
                                    <?= htmlspecialchars($r['purpose']) ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                    <?= accCategoryLabel($r['request_category']) ?>
                                    <?php if (!empty($r['request_reference'])): ?>
                                        <span class="text-gray-400">· <?= htmlspecialchars($r['request_reference']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    <?= htmlspecialchars($r['date_requested']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($r['is_pending']): ?>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-amber-50 text-amber-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Waiting
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-green-50 text-green-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Uploaded
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    <?= $r['followup_uploaded_at']
                                        ? htmlspecialchars(date('M d, Y h:i A', strtotime($r['followup_uploaded_at'])))
                                        : '—' ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if (!$r['is_pending'] && $r['files']): ?>
                                        <button type="button"
                                            onclick='fuViewFiles(<?= json_encode($r['control_no']) ?>, <?= htmlspecialchars(json_encode($r['files']), ENT_QUOTES) ?>)'
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-600 hover:text-orange-700 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition-all">
                                            <i class="fa-solid fa-paperclip text-[10px]"></i>
                                            <?= count($r['files']) ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div id="fu-empty" class="<?= $followups ? 'hidden' : '' ?> py-14 text-center">
                    <i class="fa-regular fa-folder-open text-3xl text-gray-300 mb-2"></i>
                    <p class="text-sm text-gray-500 font-medium">No follow-up requests found.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Files modal -->
    <div id="fu-files-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-sm text-gray-800">Uploaded attachments</h3>
                    <p id="fu-files-control" class="text-[11px] font-mono text-orange-600 mt-0.5"></p>
                </div>
                <button type="button" onclick="fuCloseFiles()"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="fu-files-grid" class="p-5 grid grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto"></div>
        </div>
    </div>

    <script>
        const FU_BASE = '<?= BASE_URL ?>/';
        let fuFilter = 'all';

        // ── Filter + search + date range ─────────────
        function fuApply() {
            const q = document.getElementById('fu-search').value.trim().toLowerCase();
            const dateField = document.getElementById('fu-date-field').value; // requested | uploaded
            const from = document.getElementById('fu-date-from').value;       // YYYY-MM-DD
            const to = document.getElementById('fu-date-to').value;
            const hasDate = !!(from || to);
            let visible = 0;

            document.querySelectorAll('.fu-row').forEach(row => {
                const okState = fuFilter === 'all' || row.dataset.state === fuFilter;
                const okText = !q || row.dataset.search.includes(q);

                let okDate = true;
                if (hasDate) {
                    const d = row.dataset[dateField]; // data-requested / data-uploaded
                    if (!d) {
                        okDate = false; // walang date (hal. Waiting pa, walang upload date)
                    } else {
                        if (from && d < from) okDate = false;
                        if (to && d > to) okDate = false;
                    }
                }

                const show = okState && okText && okDate;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            document.getElementById('fu-empty').classList.toggle('hidden', visible > 0);
            document.getElementById('fu-date-clear').classList.toggle('hidden', !hasDate);

            document.querySelectorAll('.fu-tab').forEach(tab => {
                const active = tab.dataset.filter === fuFilter;
                tab.classList.toggle('bg-orange-500', active);
                tab.classList.toggle('text-white', active);
                tab.classList.toggle('text-gray-500', !active);
            });
        }

        document.querySelectorAll('.fu-tab').forEach(tab => {
            tab.addEventListener('click', () => { fuFilter = tab.dataset.filter; fuApply(); });
        });
        document.getElementById('fu-search').addEventListener('input', fuApply);
        document.getElementById('fu-date-field').addEventListener('change', fuApply);
        document.getElementById('fu-date-from').addEventListener('change', fuApply);
        document.getElementById('fu-date-to').addEventListener('change', fuApply);
        document.getElementById('fu-date-clear').addEventListener('click', () => {
            document.getElementById('fu-date-from').value = '';
            document.getElementById('fu-date-to').value = '';
            fuApply();
        });
        fuApply();

        // ── Files modal ──────────────────────────────
        function fuViewFiles(controlNo, files) {
            document.getElementById('fu-files-control').textContent = controlNo;
            document.getElementById('fu-files-grid').innerHTML = files.map(path => {
                const url = FU_BASE + path;
                const isPdf = path.toLowerCase().endsWith('.pdf');
                return `
                    <a href="${url}" target="_blank" rel="noopener"
                       class="block aspect-square rounded-lg overflow-hidden border border-gray-200 hover:border-orange-400 transition-all">
                        ${isPdf
                        ? `<div class="w-full h-full bg-red-50 flex flex-col items-center justify-center">
                                   <i class="fa-solid fa-file-pdf text-red-500 text-3xl"></i>
                                   <span class="text-[10px] text-red-400 mt-1">Open PDF</span></div>`
                        : `<img src="${url}" class="w-full h-full object-cover" loading="lazy">`}
                    </a>`;
            }).join('');
            document.getElementById('fu-files-modal').classList.remove('hidden');
        }

        function fuCloseFiles() {
            document.getElementById('fu-files-modal').classList.add('hidden');
        }
        document.getElementById('fu-files-modal').addEventListener('click', e => {
            if (e.target.id === 'fu-files-modal') fuCloseFiles();
        });
    </script>
</body>

</html>