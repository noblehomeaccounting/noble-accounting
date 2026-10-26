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
<?php
function fuH($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Ilang araw na naghihintay (mula date_requested)
function fuDaysWaiting(?string $date): ?int {
    if (empty($date) || str_starts_with($date, '0000')) return null;
    $t = strtotime(substr($date, 0, 10));
    if (!$t) return null;
    return max(0, (int) floor((strtotime('today') - $t) / 86400));
}

$fuFiles = [];
foreach ($followups as $r) $fuFiles[(int) $r['id']] = ['control_no' => $r['control_no'], 'files' => array_values($r['files'])];
$totalCount = count($followups);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-accounting/css/accounting/index-accounting-followups.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-accounting/page-1/css/accounting/index-accounting-followups.css') ?: 1 ?>">
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-paperclip text-lg"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-800 leading-tight">Follow-up Attachments</h1>
                <p class="text-sm text-gray-500">Requests submitted with "Follow up attachment" — who still needs to upload, and who already did</p>
            </div>
        </div>

        <!-- ═══ STAT TABS (pindutin para i-filter) ═══ -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-5" id="fu-tabs">
            <?php
            $tabs = [
                ['all',      'All follow-ups',     $totalCount,    'fa-layer-group',   'bg-amber-50 text-amber-700'],
                ['pending',  'Waiting for upload', $pendingCount,  'fa-hourglass-half','bg-yellow-50 text-yellow-600'],
                ['uploaded', 'Already uploaded',   $uploadedCount, 'fa-circle-check',  'bg-green-50 text-green-600'],
            ];
            foreach ($tabs as [$key, $label, $n, $icon, $tone]): ?>
                <button type="button" data-filter="<?= $key ?>" data-active="<?= $key === 'all' ? 1 : 0 ?>"
                    class="fu-tab border rounded-xl p-3 sm:p-4 flex items-center gap-3 text-left shadow-sm transition min-w-0">
                    <span class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center shrink-0 <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span class="block text-[10px] sm:text-[11px] font-semibold text-gray-500 uppercase tracking-wide leading-tight"><?= $label ?></span>
                        <span class="block text-2xl font-bold leading-none mt-1 <?= $key === 'pending' && $n ? 'text-amber-600' : 'text-gray-800' ?>"
                            id="fu-count-<?= $key ?>"><?= (int) $n ?></span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST CARD ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <!-- Toolbar -->
            <div class="flex flex-col xl:flex-row xl:items-center gap-2.5 px-4 py-3 border-b border-gray-100">
                <div class="relative flex-1 min-w-0">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                    <input type="text" id="fu-search" placeholder="Search name, control no., purpose, reference..."
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="fu-date-field"
                        class="h-9 bg-white border border-gray-200 rounded-lg px-2.5 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                        <option value="requested">Date requested</option>
                        <option value="uploaded">Uploaded on</option>
                    </select>
                    <div class="flex items-center gap-2 flex-1 sm:flex-none min-w-0">
                        <input type="date" id="fu-date-from" title="From"
                            class="h-9 flex-1 sm:flex-none min-w-0 bg-white border border-gray-200 rounded-lg px-2.5 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                        <span class="text-xs text-gray-400">to</span>
                        <input type="date" id="fu-date-to" title="To"
                            class="h-9 flex-1 sm:flex-none min-w-0 bg-white border border-gray-200 rounded-lg px-2.5 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <button type="button" id="fu-date-clear"
                        class="hidden h-9 text-xs font-semibold text-gray-600 hover:text-red-600 border border-gray-200 hover:border-red-200 rounded-lg px-3 transition">
                        <i class="fa-solid fa-xmark mr-1"></i>Clear dates
                    </button>
                </div>
            </div>
            <div class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">
                Showing <b id="fu-shown" class="text-gray-700"><?= $totalCount ?></b> of <?= $totalCount ?>
            </div>

            <!-- Rows -->
            <div id="fu-table-wrap" class="fu-scroll overflow-y-auto max-h-[calc(100vh-24rem)] min-h-[200px] divide-y divide-gray-100">
                <?php foreach ($followups as $r):
                    $id = (int) $r['id'];
                    $pending = $r['is_pending'];
                    $reqDate = !empty($r['date_requested']) ? date('Y-m-d', strtotime($r['date_requested'])) : '';
                    $upDate = !empty($r['followup_uploaded_at']) ? date('Y-m-d', strtotime($r['followup_uploaded_at'])) : '';
                    $days = $pending ? fuDaysWaiting($r['date_requested']) : null;
                    $nFiles = count($r['files']);
                    $statusLabel = ucfirst((string) ($r['status'] ?? ''));
                ?>
                    <div data-id="<?= $id ?>" class="fu-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors"
                        data-state="<?= $pending ? 'pending' : 'uploaded' ?>"
                        data-requested="<?= fuH($reqDate) ?>"
                        data-uploaded="<?= fuH($upDate) ?>"
                        data-search="<?= fuH(strtolower($r['control_no'] . ' ' . $r['requestor_name'] . ' ' . $r['purpose'] . ' ' . ($r['request_reference'] ?? ''))) ?>">
                        <div class="w-1 rounded-full shrink-0 <?= $pending ? 'bg-yellow-400' : 'bg-green-500' ?>"></div>

                        <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2.5 lg:gap-5">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5"><?= fuH($r['control_no']) ?></span>
                                    <?php if ($pending): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Waiting
                                        </span>
                                        <?php if ($days !== null): ?>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full <?= $days >= 7 ? 'bg-red-50 text-red-600 border border-red-200' : ($days >= 3 ? 'bg-orange-50 text-orange-700' : 'bg-gray-100 text-gray-600') ?>">
                                                <?= $days === 0 ? 'Today' : $days . ' day' . ($days !== 1 ? 's' : '') . ' waiting' ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-green-100 text-green-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Uploaded
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($statusLabel !== ''): ?>
                                        <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">Request: <?= fuH($statusLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate"><?= fuH($r['requestor_name']) ?></p>
                                <p class="text-[13px] text-gray-600 break-words line-clamp-2" title="<?= fuH($r['purpose']) ?>"><?= fuH($r['purpose']) ?></p>
                                <div class="flex items-center gap-x-3 gap-y-1 mt-1.5 flex-wrap text-[11px] text-gray-500">
                                    <span class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-full px-2 py-0.5 font-semibold text-gray-600">
                                        <?= fuH(accCategoryLabel($r['request_category'])) ?>
                                        <?php if (!empty($r['request_reference'])): ?>
                                            <span class="font-normal text-gray-400 truncate max-w-[180px]">· <?= fuH($r['request_reference']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>Requested <?= $reqDate ? fuH(date('M d, Y', strtotime($reqDate))) : '—' ?></span>
                                    <?php if ($r['followup_uploaded_at']): ?>
                                        <span class="whitespace-nowrap text-green-700"><i class="fa-solid fa-cloud-arrow-up mr-1"></i>Uploaded <?= fuH(date('M d, Y h:i A', strtotime($r['followup_uploaded_at']))) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <?php if (!$pending && $nFiles): ?>
                                    <button type="button" onclick="fuViewFiles(<?= $id ?>)"
                                        class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg transition">
                                        <i class="fa-solid fa-paperclip text-[11px]"></i> View <?= $nFiles ?> file<?= $nFiles !== 1 ? 's' : '' ?>
                                    </button>
                                <?php elseif ($pending): ?>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 bg-gray-50 border border-dashed border-gray-300 px-3 py-1.5 rounded-lg">
                                        <i class="fa-regular fa-clock text-[11px]"></i> No files yet
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No files</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div id="fu-empty" class="<?= $followups ? 'hidden' : '' ?> py-14 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                        <i class="fa-regular fa-folder-open text-xl"></i>
                    </div>
                    <p class="text-sm text-gray-600 font-semibold"><?= $followups ? 'No matching follow-ups.' : 'No follow-up requests found.' ?></p>
                    <?php if ($followups): ?><p class="text-xs text-gray-400 mt-1">Try another search, tab, or date range.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- ═══ FILES MODAL ═══ -->
    <div id="fu-files-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-paperclip"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-sm text-gray-800">Uploaded attachments</h3>
                        <p id="fu-files-control" class="notranslate text-[11px] font-mono text-amber-700 mt-0.5 truncate"></p>
                    </div>
                </div>
                <button type="button" onclick="fuCloseFiles()" title="Close"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="fu-files-grid" class="fu-scroll p-5 grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto"></div>
        </div>
    </div>

    <script>
        const FU_BASE = '<?= BASE_URL ?>/';
        const FU_FILES = <?= json_encode($fuFiles ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
        let fuFilter = 'all';

        const fuEsc = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

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
                    const d = row.dataset[dateField];
                    if (!d) okDate = false; // hal. Waiting pa, walang upload date
                    else {
                        if (from && d < from) okDate = false;
                        if (to && d > to) okDate = false;
                    }
                }
                const show = okState && okText && okDate;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            document.getElementById('fu-shown').textContent = visible;
            document.getElementById('fu-empty').classList.toggle('hidden', visible > 0);
            document.getElementById('fu-date-clear').classList.toggle('hidden', !hasDate);
            document.querySelectorAll('#fu-tabs .fu-tab').forEach(tab => {
                tab.dataset.active = tab.dataset.filter === fuFilter ? '1' : '0';
            });
        }

        document.querySelectorAll('#fu-tabs .fu-tab').forEach(tab => {
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
        function fuViewFiles(id) {
            const rec = FU_FILES[id];
            if (!rec) return;
            document.getElementById('fu-files-control').textContent = rec.control_no || '';
            document.getElementById('fu-files-grid').innerHTML = rec.files.map((path, i) => {
                const url = fuEsc(FU_BASE + path);
                const name = fuEsc(String(path).split('/').pop());
                const isPdf = String(path).toLowerCase().endsWith('.pdf');
                return `
                    <a href="${url}" target="_blank" rel="noopener" title="${name}"
                       class="group block rounded-xl overflow-hidden border border-gray-200 hover:border-amber-500 hover:shadow-md transition">
                        <div class="aspect-square bg-gray-50">
                            ${isPdf
                                ? `<div class="w-full h-full bg-red-50 flex flex-col items-center justify-center">
                                       <i class="fa-solid fa-file-pdf text-red-500 text-3xl"></i>
                                       <span class="text-[10px] font-semibold text-red-500 mt-1">PDF</span></div>`
                                : `<img src="${url}" alt="Attachment ${i + 1}" class="w-full h-full object-cover" loading="lazy">`}
                        </div>
                        <div class="px-2 py-1.5 text-[10px] text-gray-500 truncate border-t border-gray-100 group-hover:text-amber-700">
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] mr-1"></i>${name}
                        </div>
                    </a>`;
            }).join('');
            document.getElementById('fu-files-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function fuCloseFiles() {
            document.getElementById('fu-files-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.getElementById('fu-files-modal').addEventListener('click', e => {
            if (e.target.id === 'fu-files-modal') fuCloseFiles();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') fuCloseFiles(); });
    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'fu-picked';
            const SEL = '#fu-table-wrap .fu-row';
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
</body>

</html>