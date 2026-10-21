<?php
// index-accounting-tracking.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$sql = "
    SELECT
        br.*,
        u.name    AS requestor_full_name,
        ab.name   AS approved_by_name,
        rb.name   AS received_by_name,
        sb.name   AS sent_to_name,

        v.id               AS voucher_id,
        v.status           AS voucher_status,
        v.certified_by     AS v_certified_by,
        v.certified_at     AS v_certified_at,
        v.prepared_by      AS v_prepared_by,
        v.prepared_at      AS v_prepared_at,
        v.approved_by      AS v_approved_by,
        v.approved_at      AS v_approved_at,
        v.released_by      AS v_released_by,
        v.released_at      AS v_released_at,
        v.received_by      AS v_received_by,
        v.received_at      AS v_received_at,

        vc.name  AS v_certified_name,
        vp.name  AS v_prepared_name,
        va.name  AS v_approved_name,
        vrl.name AS v_released_name,
        vrv.name AS v_received_name

    FROM noblebudgetrequest br
    LEFT JOIN noblerole u   ON br.user_id     = u.id
    LEFT JOIN noblerole ab  ON br.approved_by = ab.id
    LEFT JOIN noblerole rb  ON br.received_by = rb.id
    LEFT JOIN noblerole sb  ON br.sent_to     = sb.id
    LEFT JOIN noblevoucher v    ON v.request_id  = br.id
    LEFT JOIN noblerole vc  ON v.certified_by = vc.id
    LEFT JOIN noblerole vp  ON v.prepared_by  = vp.id
    LEFT JOIN noblerole va  ON v.approved_by  = va.id
    LEFT JOIN noblerole vrl ON v.released_by  = vrl.id
    LEFT JOIN noblerole vrv ON v.received_by  = vrv.id
    ORDER BY br.created_at DESC
";
$result = $conn->query($sql);
$requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ─── Helpers ─────────────────────────────────────────
function trkH($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function trkHasDate($v) { return !empty($v) && $v !== '0000-00-00 00:00:00' && $v !== '0000-00-00'; }

// Badge na ipapakita (parehong logic sa luma)
function trkBadge(array $r): array {
    $hasVoucher = !empty($r['voucher_id']);
    $vStatus = $r['voucher_status'] ?? '';
    if ($hasVoucher && $vStatus === 'released')      return ['badge-received', 'Released'];
    if ($hasVoucher && trkHasDate($r['v_received_at'] ?? null)) return ['badge-received', 'Received'];
    if ($hasVoucher) return ['badge-voucher', 'Voucher: ' . ucfirst(str_replace('_', ' ', $vStatus))];
    $st = $r['status'] ?? 'pending';
    $cls = match ($st) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' };
    return [$cls, ucfirst($st)];
}

// Ilang hakbang na ang tapos (8 lahat), para sa progress bar
function trkProgress(array $r): int {
    $n = 1; // submitted
    if (in_array($r['status'] ?? '', ['approved', 'rejected'], true)) $n++;
    foreach (['received_at', 'v_certified_at', 'v_prepared_at', 'v_approved_at', 'v_released_at', 'v_received_at'] as $k) {
        if (trkHasDate($r[$k] ?? null)) $n++;
    }
    return $n;
}

$cnt = ['all' => count($requests), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'voucher' => 0];
foreach ($requests as $r) {
    if (isset($cnt[$r['status'] ?? ''])) $cnt[$r['status']]++;
    if (!empty($r['voucher_id'])) $cnt['voucher']++;
}

// Data para sa modal (isang beses lang i-output, hindi sa bawat button)
$reqMap = [];
foreach ($requests as $r) $reqMap[(int)$r['id']] = $r;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Request Tracking</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .badge-pending  { background:#fef9c3; color:#854d0e; }
        .badge-approved { background:#dcfce7; color:#166534; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .badge-received { background:#dbeafe; color:#1e40af; }
        .badge-voucher  { background:#f3e8ff; color:#6b21a8; }

        .bar-pending  { background:#facc15; }
        .bar-approved { background:#22c55e; }
        .bar-rejected { background:#f87171; }
        .bar-received { background:#3b82f6; }
        .bar-voucher  { background:#a855f7; }

        .trk-tab { background:#fff; border-color:#e5e7eb; }
        .trk-tab:hover { border-color:#f59e0b; }
        .trk-tab[data-active="1"] { border-color:#b45309; box-shadow:0 0 0 1px #b45309 inset; background:#fffbeb; }

        .trk-scroll::-webkit-scrollbar { width: 4px; }
        .trk-scroll::-webkit-scrollbar-track { background: transparent; }
        .trk-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

        .step-dot { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center;
                    font-size:13px; flex-shrink:0; position:relative; z-index:1; }
        .pulse-dot::after { content:''; position:absolute; inset:0; border-radius:50%; background:inherit; opacity:.4;
                            animation: trkPulse 1.8s ease-in-out infinite; }
        @keyframes trkPulse { 0% { transform:scale(1); opacity:.4; } 70%,100% { transform:scale(1.6); opacity:0; } }
    </style>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fas fa-route text-lg"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-800 leading-tight">Budget Request Tracking</h1>
                <p class="text-sm text-gray-500">Real-time status of all budget requests</p>
            </div>
        </div>

        <!-- ═══ STAT TABS (pindutin para i-filter) ═══ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5" id="trkTabs">
            <?php
            $tabs = [
                ['all',      'All requests', 'fa-layer-group',  'bg-amber-50 text-amber-700'],
                ['pending',  'Pending',      'fa-clock',        'bg-yellow-50 text-yellow-600'],
                ['approved', 'Approved',     'fa-check-circle', 'bg-green-50 text-green-600'],
                ['rejected', 'Rejected',     'fa-times-circle', 'bg-red-50 text-red-500'],
                ['voucher',  'With voucher', 'fa-receipt',      'bg-purple-50 text-purple-600'],
            ];
            foreach ($tabs as $i => [$key, $label, $icon, $tone]): ?>
                <button type="button" data-tab="<?= $key ?>" data-active="<?= $key === 'all' ? 1 : 0 ?>"
                    class="trk-tab border rounded-xl p-3.5 flex items-center gap-3 text-left shadow-sm transition min-w-0 <?= $i === 4 ? 'col-span-2 sm:col-span-1' : '' ?>">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 <?= $tone ?>"><i class="fas <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-800 leading-none mt-1"><?= (int)$cnt[$key] ?></span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Toolbar -->
            <div class="flex flex-col sm:flex-row gap-2.5 p-3.5 border-b border-gray-100">
                <div class="relative flex-1 min-w-0">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="searchInput" placeholder="Search control no, requestor, purpose..."
                        class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition" />
                </div>
                <select id="categoryFilter"
                    class="sm:w-44 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    <option value="">All categories</option>
                    <option value="project">Project</option>
                    <option value="client">Client</option>
                    <option value="nhcc">NHCC</option>
                </select>
            </div>
            <div class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">
                Showing <span id="trkShown" class="font-semibold text-gray-700"><?= count($requests) ?></span> of <?= count($requests) ?>
            </div>

            <!-- Rows -->
            <div id="trkList" class="trk-scroll max-h-[65vh] overflow-y-auto divide-y divide-gray-100">
                <?php foreach ($requests as $req):
                    [$badgeClass, $displayStatus] = trkBadge($req);
                    $barClass = str_replace('badge-', 'bar-', $badgeClass);
                    $done = trkProgress($req);
                    $pct = (int)round($done / 8 * 100);
                    $id = (int)$req['id'];
                    $search = strtolower(($req['control_no'] ?? '') . ' ' . ($req['requestor_name'] ?? '') . ' ' . ($req['purpose'] ?? '') . ' ' . ($req['sent_to_name'] ?? ''));
                ?>
                    <div class="request-row flex items-stretch gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors cursor-pointer"
                        data-id="<?= $id ?>"
                        data-status="<?= trkH($req['status']) ?>"
                        data-voucher="<?= !empty($req['voucher_id']) ? 1 : 0 ?>"
                        data-category="<?= trkH(strtolower($req['request_category'] ?? '')) ?>"
                        data-search="<?= trkH($search) ?>"
                        onclick="openTracker(<?= $id ?>)">
                        <div class="w-1 rounded-full shrink-0 <?= $barClass ?>"></div>

                        <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-5">
                            <!-- Main -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5"><?= trkH($req['control_no'] ?: '—') ?></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide <?= $badgeClass ?>"><?= trkH($displayStatus) ?></span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 uppercase"><?= trkH($req['request_category'] ?: '—') ?></span>
                                </div>
                                <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate"><?= trkH($req['purpose'] ?: '—') ?></p>
                                <div class="flex items-center gap-x-3 gap-y-0.5 flex-wrap text-[11px] text-gray-500 mt-0.5">
                                    <span class="truncate"><i class="fas fa-user text-gray-300 mr-1"></i><?= trkH($req['requestor_name'] ?: '—') ?></span>
                                    <span class="truncate"><i class="fas fa-paper-plane text-gray-300 mr-1"></i><?= trkH($req['sent_to_name'] ?: '—') ?></span>
                                    <span class="whitespace-nowrap"><i class="far fa-calendar text-gray-300 mr-1"></i><?= trkHasDate($req['date_requested'] ?? null) ? date('M d, Y', strtotime($req['date_requested'])) : '—' ?><?= trkHasDate($req['created_at'] ?? null) ? ' · ' . date('h:i A', strtotime($req['created_at'])) : '' ?></span>
                                </div>
                            </div>

                            <!-- Progress + action -->
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="flex-1 lg:flex-none lg:w-40 min-w-0">
                                    <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
                                        <span>Progress</span><span class="font-semibold text-gray-700"><?= $done ?>/8</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full rounded-full <?= ($req['status'] ?? '') === 'rejected' ? 'bg-red-400' : 'bg-amber-500' ?>" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="event.stopPropagation(); openTracker(<?= $id ?>)"
                                    class="shrink-0 inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:border-amber-500 hover:text-amber-700 text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-route text-[11px]"></i> Track
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Empty state -->
                <div id="trkEmpty" class="<?= empty($requests) ? '' : 'hidden' ?> py-14 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                        <i class="fas fa-inbox text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-600" id="trkEmptyTitle"><?= empty($requests) ? 'No budget requests yet.' : 'No matching requests.' ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= empty($requests) ? '' : 'Try another search, tab, or category.' ?></p>
                </div>
            </div>
        </div>

    </main>

    <!-- ═══ TRACKER MODAL ═══ -->
    <div id="trackerModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="trk-scroll bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-x-hidden overflow-y-auto" style="max-height:90vh;">

            <!-- Header (light) -->
            <div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-5 pt-5 pb-4 rounded-t-2xl">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Budget Request</p>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="notranslate font-mono font-bold text-gray-800 text-base break-all" id="modal-control-no">—</h2>
                            <span id="modal-status" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"></span>
                        </div>
                    </div>
                    <button type="button" onclick="closeTracker()" title="Close"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-700 font-medium mt-3 break-words line-clamp-2" id="modal-purpose">—</p>
                <div class="flex flex-wrap gap-1.5 mt-2.5 text-[11px]">
                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 rounded-full px-2.5 py-1"><i class="fas fa-user text-gray-400"></i><span id="modal-requestor">—</span></span>
                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 rounded-full px-2.5 py-1"><i class="far fa-calendar text-gray-400"></i><span id="modal-date">—</span></span>
                    <span id="modal-category-badge" class="inline-flex items-center bg-amber-50 text-amber-800 border border-amber-200 rounded-full px-2.5 py-1 font-semibold uppercase">—</span>
                </div>

                <!-- Progress -->
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="font-semibold text-gray-600" id="modal-progress-label">—</span>
                        <span class="text-gray-500" id="modal-progress-count">0/8</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div id="modal-progress-bar" class="h-full rounded-full bg-amber-500 transition-all duration-500" style="width:0%"></div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="px-5 pt-5 pb-1">
                <p class="text-[11px] text-gray-500 uppercase tracking-widest mb-4 font-bold">
                    <i class="fas fa-stream mr-1 text-gray-400"></i> Request Timeline
                </p>
                <div class="flex flex-col" id="tracker-steps"></div>
            </div>

            <!-- Reject Comment -->
            <div id="reject-block" class="hidden mx-5 mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3">
                <p class="text-xs font-bold text-red-600 mb-1"><i class="fas fa-ban mr-1"></i> Rejection Reason</p>
                <p class="text-sm text-red-700 break-words" id="modal-reject-comment">—</p>
            </div>

            <!-- Footer -->
            <div class="px-5 pb-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="bg-gray-50 border border-gray-100 rounded-xl px-3.5 py-2.5 min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Reference</p>
                        <p id="modal-reference" class="text-sm text-gray-700 font-medium truncate">—</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-100 rounded-xl px-3.5 py-2.5 min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Sent to</p>
                        <p id="modal-sent-to" class="text-sm text-gray-700 font-medium truncate">—</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const TRK_DATA = <?= json_encode($reqMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}' ?>;
        const TRK_BADGE = <?= json_encode(array_map(fn($r) => trkBadge($r), $reqMap) ?: new stdClass()) ?>;

        const trkEsc = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function fdt(val) {
            if (!val || val === '0000-00-00 00:00:00' || val === '0000-00-00') return null;
            const d = new Date(String(val).replace(' ', 'T'));
            if (isNaN(d)) return null;
            return d.toLocaleString('en-PH', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: true
            });
        }
        function fdate(val) {
            if (!val || String(val).startsWith('0000')) return '—';
            const d = new Date(String(val).substring(0, 10) + 'T00:00:00');
            return isNaN(d) ? String(val) : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        // Tumatanggap ng id (bago) o buong object (luma)
        function openTracker(arg) {
            const req = (typeof arg === 'object' && arg) ? arg : TRK_DATA[arg];
            if (!req) return;

            document.getElementById('modal-control-no').textContent = req.control_no || '—';
            document.getElementById('modal-purpose').textContent = req.purpose || '—';
            document.getElementById('modal-requestor').textContent = req.requestor_name || '—';
            document.getElementById('modal-date').textContent = fdate(req.date_requested);
            document.getElementById('modal-category-badge').textContent = req.request_category || '—';
            document.getElementById('modal-reference').textContent = req.request_reference || '—';
            document.getElementById('modal-sent-to').textContent = req.sent_to_name || '—';

            const badge = TRK_BADGE[req.id] || ['badge-pending', req.status || 'Pending'];
            const st = document.getElementById('modal-status');
            st.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide ' + badge[0];
            st.textContent = badge[1];

            const rejectBlock = document.getElementById('reject-block');
            if (req.status === 'rejected' && req.reject_comment) {
                rejectBlock.classList.remove('hidden');
                document.getElementById('modal-reject-comment').textContent = req.reject_comment;
            } else {
                rejectBlock.classList.add('hidden');
            }

            const steps = buildSteps(req);
            renderSteps(steps);

            // Progress
            const done = steps.filter(s => s.done).length;
            const current = steps.find(s => s.active && !s.done);
            const isRejected = req.status === 'rejected';
            document.getElementById('modal-progress-count').textContent = done + '/' + steps.length;
            document.getElementById('modal-progress-label').textContent =
                isRejected ? 'Stopped: request rejected'
                : done === steps.length ? 'Completed'
                : current ? 'Now: ' + current.label : 'In progress';
            const bar = document.getElementById('modal-progress-bar');
            bar.className = 'h-full rounded-full transition-all duration-500 ' + (isRejected ? 'bg-red-400' : done === steps.length ? 'bg-green-500' : 'bg-amber-500');
            bar.style.width = '0%';
            requestAnimationFrame(() => { bar.style.width = Math.round(done / steps.length * 100) + '%'; });

            const modal = document.getElementById('trackerModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function buildSteps(r) {
            const isApproved = r.status === 'approved';
            const isRejected = r.status === 'rejected';
            const has = v => !!fdt(v);

            return [
                { label: 'Request Submitted', sub: r.requestor_name || '—', time: fdt(r.created_at),
                  done: true, active: false, icon: 'fa-file-alt' },
                { label: isRejected ? 'Request Rejected' : 'Approved by Accounting',
                  sub: isRejected ? (r.reject_comment ? 'See rejection reason below' : 'No comment') : (r.approved_by_name || 'Awaiting approval'),
                  time: (isApproved || isRejected) ? fdt(r.approved_at) : null,
                  done: isApproved || isRejected, active: !isApproved && !isRejected, rejected: isRejected,
                  icon: isRejected ? 'fa-times' : 'fa-check-double' },
                { label: 'Received by Staff', sub: r.received_by_name || 'Awaiting receipt', time: fdt(r.received_at),
                  done: has(r.received_at), active: isApproved && !has(r.received_at), icon: 'fa-hand-holding' },
                { label: 'Voucher — Certified', sub: r.v_certified_name || 'Awaiting certification', time: fdt(r.v_certified_at),
                  done: has(r.v_certified_at), active: has(r.received_at) && !has(r.v_certified_at), icon: 'fa-stamp' },
                { label: 'Voucher — Prepared', sub: r.v_prepared_name || 'Awaiting preparation', time: fdt(r.v_prepared_at),
                  done: has(r.v_prepared_at), active: has(r.v_certified_at) && !has(r.v_prepared_at), icon: 'fa-pen-nib' },
                { label: 'Voucher — Approved', sub: r.v_approved_name || 'Awaiting approval', time: fdt(r.v_approved_at),
                  done: has(r.v_approved_at), active: has(r.v_prepared_at) && !has(r.v_approved_at), icon: 'fa-clipboard-check' },
                { label: 'Voucher — Released', sub: r.v_released_name || 'Awaiting release', time: fdt(r.v_released_at),
                  done: has(r.v_released_at), active: has(r.v_approved_at) && !has(r.v_released_at), icon: 'fa-paper-plane' },
                { label: 'Voucher — Received', sub: r.v_received_name || 'Awaiting receipt', time: fdt(r.v_received_at),
                  done: has(r.v_received_at), active: has(r.v_released_at) && !has(r.v_received_at), icon: 'fa-box-open' }
            ];
        }

        function renderSteps(steps) {
            const container = document.getElementById('tracker-steps');
            container.innerHTML = steps.map((step, i) => {
                const isLast = i === steps.length - 1;
                let dot, line, icon = step.icon;
                if (step.rejected)      { dot = 'bg-red-100 text-red-600';      line = '#fecaca'; }
                else if (step.done)     { dot = 'bg-green-500 text-white';      line = '#22c55e'; icon = 'fa-check'; }
                else if (step.active)   { dot = 'bg-amber-100 text-amber-700 ring-2 ring-amber-400 pulse-dot'; line = '#e5e7eb'; }
                else                    { dot = 'bg-gray-100 text-gray-400';    line = '#e5e7eb'; }

                const labelColor = step.rejected ? 'text-red-600' : step.done ? 'text-gray-800' : step.active ? 'text-amber-800' : 'text-gray-400';
                const subColor = step.done || step.rejected ? 'text-gray-500' : step.active ? 'text-amber-700' : 'text-gray-400';
                const nowTag = step.active && !step.done
                    ? '<span class="ml-1.5 text-[9px] font-bold uppercase bg-amber-500 text-white rounded px-1.5 py-0.5 align-middle">Now</span>' : '';

                return `
                <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="step-dot ${dot}"><i class="fas ${icon}"></i></div>
                        ${!isLast ? `<div class="w-0.5 flex-1 my-1 min-h-[18px] rounded" style="background:${line}"></div>` : ''}
                    </div>
                    <div class="pb-4 flex-1 min-w-0 pt-1.5">
                        <div class="flex items-start justify-between gap-x-2 gap-y-0.5 flex-wrap">
                            <p class="font-semibold text-sm ${labelColor} leading-tight">${trkEsc(step.label)}${nowTag}</p>
                            ${step.time ? `<span class="text-[10px] text-gray-400 whitespace-nowrap">${trkEsc(step.time)}</span>` : ''}
                        </div>
                        <p class="text-xs ${subColor} mt-0.5 truncate">${trkEsc(step.sub)}</p>
                    </div>
                </div>`;
            }).join('');
        }

        function closeTracker() {
            const modal = document.getElementById('trackerModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.getElementById('trackerModal').addEventListener('click', function (e) {
            if (e.target === this) closeTracker();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTracker(); });

        // ─── Filter: tab + search + category ───
        let trkTab = 'all';

        function filterTable() {
            const search = document.getElementById('searchInput').value.trim().toLowerCase();
            const category = document.getElementById('categoryFilter').value;
            let shown = 0;
            document.querySelectorAll('.request-row').forEach(row => {
                const tabOk = trkTab === 'all'
                    || (trkTab === 'voucher' ? row.dataset.voucher === '1' : row.dataset.status === trkTab);
                const ok = tabOk
                    && (!search || row.dataset.search.includes(search))
                    && (!category || row.dataset.category === category);
                row.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            document.getElementById('trkShown').textContent = shown;
            const total = document.querySelectorAll('.request-row').length;
            document.getElementById('trkEmpty').classList.toggle('hidden', !(total === 0 || shown === 0));
        }

        document.querySelectorAll('#trkTabs .trk-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                trkTab = btn.dataset.tab;
                document.querySelectorAll('#trkTabs .trk-tab').forEach(b => b.dataset.active = b === btn ? '1' : '0');
                filterTable();
            });
        });
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('categoryFilter').addEventListener('change', filterTable);
    </script>

</body>
</html>