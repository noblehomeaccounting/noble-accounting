<?php
// payroll.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ⚠️ PALITAN: gamitin mo kung anong session key yung ginagamit ng existing announcement insert mo para sa posted_by
$postedBy = $_SESSION['user_id'] ?? 0;

/* ---------- AJAX handlers (same page) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'list') {
        $res = $conn->query("
            SELECT id, title, body, pay_period_start, pay_period_end, pay_date, is_active, created_at
            FROM nobleannouncement
            WHERE template = 4
            ORDER BY created_at DESC
            LIMIT 50
        ");
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        $start = $_POST['pay_period_start'] ?? '';
        $end   = $_POST['pay_period_end'] ?? '';
        $pay   = $_POST['pay_date'] ?? '';

        if ($title === '' || $body === '' || !$start || !$end || !$pay) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }
        if ($end < $start) {
            echo json_encode(['success' => false, 'message' => 'Pay period end cannot be before start.']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO nobleannouncement
                (title, body, template, pay_period_start, pay_period_end, pay_date, is_active, posted_by, created_at)
            VALUES (?, ?, 4, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->bind_param('sssssi', $title, $body, $start, $end, $pay, $postedBy);
        $ok = $stmt->execute();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Payroll announcement posted.' : 'Failed to save.']);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE nobleannouncement SET is_active = 1 - is_active WHERE id = ? AND template = 4");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM nobleannouncement WHERE id = ? AND template = 4");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Announcement</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Page header -->
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-orange-500 flex items-center justify-center shadow-sm">
                    <i class="fa-solid fa-money-bill-wave text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-800 leading-tight">Payroll Announcement</h1>
                    <p class="text-xs text-slate-500">Post payroll notices that appear on every employee's dashboard.</p>
                </div>
            </div>

            <!-- 1. DETAILS FORM -->
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-pen-to-square text-orange-500 text-lg"></i>
                    </div>
                    <div class="w-px h-12 bg-slate-200 shrink-0"></div>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900 leading-tight">New Announcement</h2>
                        <p class="text-sm text-slate-500">Fill in the payroll details below.</p>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Title</label>
                        <input id="pa-title" type="text" maxlength="120" placeholder="e.g. October Payroll Schedule" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Period Start</label>
                            <input id="pa-start" type="date" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Period End</label>
                            <input id="pa-end" type="date" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Pay Date</label>
                            <input id="pa-paydate" type="date" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Message</label>
                        <textarea id="pa-body" rows="4" placeholder="Details or reminders for employees..." class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 resize-none"></textarea>
                    </div>

                    <div id="pa-msg" class="hidden text-xs px-3 py-2 rounded-lg"></div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                        <button type="button" onclick="resetForm()"
                            class="px-4 py-2.5 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Clear</button>
                        <button type="button" id="pa-submit" onclick="submitPayroll()"
                            class="px-5 py-2.5 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-lg shadow-sm transition flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane text-xs"></i> Post Announcement
                        </button>
                    </div>
                </div>
            </section>

            <!-- 2. LIVE PREVIEW -->
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-eye text-orange-500 text-lg"></i>
                    </div>
                    <div class="w-px h-12 bg-slate-200 shrink-0"></div>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900 leading-tight">Live Preview</h2>
                        <p class="text-sm text-slate-500">This is how your announcement will appear on the employee dashboard.</p>
                    </div>
                </div>
                <div class="p-6 bg-slate-50 rounded-b-2xl">
                    <div id="pa-preview"></div>
                </div>
            </section>

            <!-- 3. POSTED LIST -->
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-list text-orange-500 text-lg"></i>
                    </div>
                    <div class="w-px h-12 bg-slate-200 shrink-0"></div>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900 leading-tight">Posted Announcements</h2>
                        <p class="text-sm text-slate-500">Hide or delete payroll announcements you have posted.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50">
                                <th class="px-6 py-3 font-semibold">Title</th>
                                <th class="px-6 py-3 font-semibold">Pay Period</th>
                                <th class="px-6 py-3 font-semibold">Pay Date</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                                <th class="px-6 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pa-list" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
        (function () {
            const LOGO_URL = '<?= BASE_URL ?>/icon/logo.webp';

            /* ---------- helpers ---------- */
            function esc(s) {
                const d = document.createElement('div');
                d.textContent = s ?? '';
                return d.innerHTML;
            }
            function fmt(d) {
                if (!d) return '—';
                return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
            }

            /* ---------- SAME card used in the dashboard (template 4) ---------- */
            function buildPayrollHTML(r) {
                return `
<div class="rounded-2xl overflow-hidden bg-white border border-slate-200 shadow-sm" style="font-family:'Inter',sans-serif;">

    <!-- Banner -->
    <div class="relative overflow-hidden border-b border-slate-200" style="background:linear-gradient(100deg,#f8fafc 0%,#ffffff 100%);">
        <div class="absolute inset-y-0 right-0 w-56 pointer-events-none hidden md:block">
            <div class="absolute inset-0" style="background:rgba(251,146,60,.12);clip-path:polygon(10% 100%,55% 0,100% 0,100% 100%);"></div>
            <div class="absolute inset-0" style="background:linear-gradient(135deg,#fdba74,#f97316);clip-path:polygon(38% 100%,72% 0,100% 0,100% 16%,68% 100%);"></div>
        </div>
        <div class="relative flex items-center gap-6 px-8 py-6">
            <img src="${LOGO_URL}" alt="NobleHome" class="h-16 w-auto shrink-0">
            <div class="w-px h-14 bg-slate-200 shrink-0"></div>
            <div class="min-w-0 md:pr-44">
                <p class="text-[11px] text-slate-500 uppercase tracking-[0.14em] font-semibold">Payroll Announcement</p>
                <h3 class="text-xl font-bold text-slate-900 mt-1 leading-snug break-words">${esc(r.title)|| 'Payroll Announcement Title'}</h3>
                <p class="text-sm text-slate-500 mt-1">Stay informed. Stay on track.</p>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex items-center gap-4 rounded-xl border border-slate-200 px-5 py-4">
                <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center shrink-0">
                    <i class="fa-regular fa-calendar-check text-orange-500 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Pay Period</p>
                    <p class="text-sm font-bold text-slate-900 mt-1">${fmt(r.pay_period_start)} – ${fmt(r.pay_period_end)}</p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-xl border border-slate-200 px-5 py-4">
                <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center shrink-0">
                    <i class="fa-regular fa-calendar text-orange-500 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Pay Date</p>
                    <p class="text-sm font-bold text-slate-900 mt-1">${fmt(r.pay_date)}</p>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-4 rounded-xl border border-slate-200 px-5 py-4">
            <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bullhorn text-orange-500 text-lg"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-slate-500 uppercase tracking-wider font-semibold">Message</p>
                <p class="text-sm text-slate-700 leading-relaxed mt-1 whitespace-pre-line break-words">${esc(r.body)|| 'Your message will appear here.'}</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center gap-2.5 px-6 py-3.5 bg-slate-50 border-t border-slate-100">
        <i class="fa-regular fa-user text-slate-400 text-sm"></i>
        <p class="text-xs text-slate-500">Issued by HR Department</p>
    </div>
</div>`;
            }

            /* ---------- live preview ---------- */
            const f = {
                title: document.getElementById('pa-title'),
                start: document.getElementById('pa-start'),
                end: document.getElementById('pa-end'),
                pay: document.getElementById('pa-paydate'),
                body: document.getElementById('pa-body')
            };
            function renderPreview() {
                document.getElementById('pa-preview').innerHTML = buildPayrollHTML({
                    title: f.title.value, body: f.body.value,
                    pay_period_start: f.start.value, pay_period_end: f.end.value, pay_date: f.pay.value
                });
            }
            Object.values(f).forEach(el => el.addEventListener('input', renderPreview));

            function showMsg(text, ok) {
                const m = document.getElementById('pa-msg');
                m.textContent = text;
                m.className = 'text-xs px-3 py-2 rounded-lg ' + (ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700');
            }
            function resetForm() {
                Object.values(f).forEach(el => el.value = '');
                document.getElementById('pa-msg').className = 'hidden';
                renderPreview();
            }

            /* ---------- requests (post to this same page) ---------- */
            async function api(data) {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                });
                return res.json();
            }

            async function submitPayroll() {
                const btn = document.getElementById('pa-submit');
                btn.disabled = true;
                const r = await api({
                    action: 'create',
                    title: f.title.value, body: f.body.value,
                    pay_period_start: f.start.value, pay_period_end: f.end.value, pay_date: f.pay.value
                });
                btn.disabled = false;
                if (r.success) { resetForm(); loadList(); }
                showMsg(r.message, r.success);
            }

            async function loadList() {
                const rows = await api({ action: 'list' });
                const tb = document.getElementById('pa-list');
                if (!rows.length) {
                    tb.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">No payroll announcements yet.</td></tr>`;
                    return;
                }
                tb.innerHTML = rows.map(r => `
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-700">${esc(r.title)}</td>
                    <td class="px-6 py-3 text-slate-500">${fmt(r.pay_period_start)} – ${fmt(r.pay_period_end)}</td>
                    <td class="px-6 py-3 text-slate-500">${fmt(r.pay_date)}</td>
                    <td class="px-6 py-3">
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full ${r.is_active == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-500'}">
                            ${r.is_active == 1 ? 'Active' : 'Hidden'}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <button onclick="toggleRow(${r.id})" class="text-xs px-2.5 py-1.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600">
                            ${r.is_active == 1 ? 'Hide' : 'Show'}
                        </button>
                        <button onclick="deleteRow(${r.id})" class="text-xs px-2.5 py-1.5 rounded-md bg-red-50 hover:bg-red-100 text-red-600 ml-1">Delete</button>
                    </td>
                </tr>`).join('');
            }

            async function toggleRow(id) { await api({ action: 'toggle', id }); loadList(); }
            async function deleteRow(id) {
                if (!confirm('Delete this payroll announcement?')) return;
                await api({ action: 'delete', id });
                loadList();
            }

            window.submitPayroll = submitPayroll;
            window.resetForm = resetForm;
            window.toggleRow = toggleRow;
            window.deleteRow = deleteRow;

            renderPreview();
            loadList();
        })();
    </script>
</body>

</html>