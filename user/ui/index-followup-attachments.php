<?php
// user/ui/index-followup-attachments.php

include ROOT_PATH . '/network/connect.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$user_id = intval($_SESSION['account_id'] ?? 0);

$requests = [];
$res = $conn->query("
    SELECT id, control_no, requestor_name, purpose, date_requested, items,
           status, request_category, request_reference
    FROM noblebudgetrequest
    WHERE user_id = $user_id AND attachment_status = 'follow_up'
    ORDER BY id DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items = json_decode($row['items'] ?? '[]', true) ?: [];
        $total = 0;
        foreach ($items as $it) {
            $total += (float) str_replace(',', '', $it['amount'] ?? 0);
        }
        $row['item_count'] = count($items);
        $row['total'] = $total;
        $requests[] = $row;
    }
}

$fuTotalAmount = array_sum(array_column($requests, 'total'));

function followupCategoryLabel(?string $cat): string
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
    <title>Follow-up Attachments | NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>

    <style>
        /* Thin scrollbar */
        .thin-scroll {
            scrollbar-width: thin;
            scrollbar-color: #fdba74 transparent;
        }

        .thin-scroll::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .thin-scroll::-webkit-scrollbar-thumb {
            background: #fdba74;
            border-radius: 999px;
        }

        /* Scrollable attachment preview inside the modal */
        #fu-preview {
            scrollbar-width: thin;
        }

        /* sticky na table header */
        .fu-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #fafafa;
        }
    </style>
</head>

<body class="min-h-screen relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <div class="fixed inset-0 bg-black/60 z-0"></div>

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="relative z-20 max-w-6xl mx-auto px-3 md:px-6 pt-24 md:pt-32 pb-8">

        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 px-6 pt-6 pb-5 border-b border-gray-200">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 tracking-tight">Follow-up Attachments</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Requests you submitted without an attachment. Upload your proof or receipts here.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Summary -->
                    <div class="flex items-stretch divide-x divide-gray-200 rounded-lg border border-gray-200">
                        <div class="px-4 py-2">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Awaiting Upload</p>
                            <p class="text-sm font-semibold text-gray-900 tabular-nums" id="fu-count"><?= count($requests) ?></p>
                        </div>
                        <div class="px-4 py-2">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Total Amount</p>
                            <p class="text-sm font-semibold text-gray-900 tabular-nums">₱ <?= number_format($fuTotalAmount, 2) ?></p>
                        </div>
                    </div>

                    <?php if ($requests): ?>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[11px] pointer-events-none"></i>
                            <input type="text" id="fu-search" placeholder="Search"
                                class="pl-8 pr-3 h-9 w-52 text-sm border border-gray-200 rounded-md outline-none focus:border-gray-400 transition-colors">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notice -->
            <?php if ($requests): ?>
                <div class="flex items-start gap-3 px-6 py-3 bg-amber-50/70 border-b border-amber-100 text-sm text-amber-800">
                    <i class="fa-solid fa-circle-info mt-0.5 text-amber-500"></i>
                    <p>These requests still need supporting documents. Upload them so Accounting can process your request.</p>
                </div>
            <?php endif; ?>

            <!-- Empty state -->
            <div id="fu-empty" class="<?= $requests ? 'hidden' : '' ?> py-16 text-center">
                <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-check text-green-600"></i>
                </div>
                <p class="text-sm font-semibold text-gray-800">You're all caught up</p>
                <p class="text-sm text-gray-500 mt-0.5">All your requests already have attachments.</p>
            </div>

            <!-- Table -->
            <div id="fu-table-wrap" class="<?= $requests ? '' : 'hidden' ?> thin-scroll overflow-auto max-h-[58vh]">
                <table class="fu-table w-full text-sm min-w-[820px]">
                    <thead>
                        <tr class="text-[11px] font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <th class="px-6 py-3 text-left">Control No.</th>
                            <th class="px-6 py-3 text-left">Purpose</th>
                            <th class="px-6 py-3 text-left">Category</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-left">Date</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="fu-list" class="divide-y divide-gray-100">
                        <?php foreach ($requests as $r):
                            $st = strtolower($r['status'] ?? 'pending');
                            $dot = ['approved' => 'bg-green-600', 'rejected' => 'bg-red-500'][$st] ?? 'bg-amber-500';
                            $dateLbl = !empty($r['date_requested']) ? date('M j, Y', strtotime($r['date_requested'])) : '—';
                            $search = strtolower(($r['control_no'] ?? '') . ' ' . ($r['purpose'] ?? '') . ' ' . ($r['request_reference'] ?? ''));
                            ?>
                            <tr class="fu-card group hover:bg-gray-50 transition-all duration-300"
                                data-id="<?= (int) $r['id'] ?>" data-search="<?= htmlspecialchars($search) ?>">
                                <td class="px-6 py-3.5 font-mono text-[13px] font-medium text-gray-900 whitespace-nowrap">
                                    <?= htmlspecialchars($r['control_no']) ?>
                                </td>
                                <td class="px-6 py-3.5 max-w-[260px]">
                                    <p class="text-gray-900 truncate" title="<?= htmlspecialchars($r['purpose']) ?>"><?= htmlspecialchars($r['purpose']) ?></p>
                                    <p class="text-xs text-gray-400"><?= (int) $r['item_count'] ?> item<?= $r['item_count'] == 1 ? '' : 's' ?></p>
                                </td>
                                <td class="px-6 py-3.5 max-w-[200px]">
                                    <p class="text-gray-700"><?= followupCategoryLabel($r['request_category']) ?></p>
                                    <?php if (!empty($r['request_reference'])): ?>
                                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($r['request_reference']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5 text-right tabular-nums font-medium text-gray-900 whitespace-nowrap">
                                    ₱ <?= number_format($r['total'], 2) ?>
                                </td>
                                <td class="px-6 py-3.5 text-gray-500 whitespace-nowrap"><?= htmlspecialchars($dateLbl) ?></td>
                                <td class="px-6 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-2 text-sm text-gray-700 capitalize">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $dot ?>"></span><?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right whitespace-nowrap">
                                    <button type="button"
                                        onclick="fuOpen(<?= (int) $r['id'] ?>, '<?= htmlspecialchars($r['control_no'], ENT_QUOTES) ?>')"
                                        class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-gray-900 hover:bg-[#e07b06] text-white text-[12px] font-medium transition-colors">
                                        <i class="fa-solid fa-arrow-up-from-bracket text-[10px]"></i> Upload
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="fu-no-match" class="hidden">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No requests match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div id="fu-footer" class="<?= $requests ? '' : 'hidden' ?> flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                <span id="fu-result">Showing <?= count($requests) ?> of <?= count($requests) ?> requests</span>
                <span class="hidden sm:inline">Images are converted to WebP automatically</span>
            </div>
        </div>
    </main>

    <!-- Upload modal -->
    <div id="fu-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-sm text-gray-800">Upload attachment</h3>
                    <p id="fu-modal-control" class="text-[11px] font-mono text-orange-600 mt-0.5"></p>
                </div>
                <button type="button" onclick="fuClose()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="px-5 py-4">
                <div id="fu-drop"
                    class="flex flex-col items-center justify-center w-full py-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-orange-400 hover:bg-orange-50 transition-all">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-orange-300 mb-2"></i>
                    <span class="text-sm text-gray-500 font-medium">Click or drag files here</span>
                    <span class="text-[10px] text-gray-300 mt-1">JPG, PNG, WEBP, PDF — multiple allowed (images are
                        converted to WebP)</span>
                </div>
                <input type="file" id="fu-input" accept="image/jpeg,image/png,image/webp,application/pdf" multiple
                    class="hidden">
                <div id="fu-preview" class="mt-3 flex flex-wrap gap-2 max-h-48 overflow-y-auto"></div>
            </div>

            <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-gray-100 bg-gray-50">
                <button type="button" onclick="fuClose()"
                    class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all">Cancel</button>
                <button type="button" id="fu-submit" onclick="fuSubmit()"
                    class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-2 rounded transition-all">
                    <i class="fa-solid fa-paper-plane text-xs"></i> Submit attachment
                </button>
            </div>
        </div>
    </div>

    <script>
        let fuRequestId = null;
        let fuFiles = [];

        function fuOpen(id, controlNo) {
            fuRequestId = id;
            fuFiles = [];
            fuRender();
            document.getElementById('fu-modal-control').textContent = controlNo;
            document.getElementById('fu-modal').classList.remove('hidden');
        }

        function fuClose() {
            document.getElementById('fu-modal').classList.add('hidden');
            fuRequestId = null;
            fuFiles = [];
        }

        // ── File picking ─────────────────────────────
        const fuDrop = document.getElementById('fu-drop');
        const fuInput = document.getElementById('fu-input');

        fuDrop.addEventListener('click', () => fuInput.click());
        fuInput.addEventListener('change', e => { fuHandle([...e.target.files]); e.target.value = ''; });
        fuDrop.addEventListener('dragover', e => { e.preventDefault(); fuDrop.classList.add('border-orange-500', 'bg-orange-50'); });
        fuDrop.addEventListener('dragleave', () => fuDrop.classList.remove('border-orange-500', 'bg-orange-50'));
        fuDrop.addEventListener('drop', e => {
            e.preventDefault();
            fuDrop.classList.remove('border-orange-500', 'bg-orange-50');
            fuHandle([...e.dataTransfer.files].filter(f => f.type.startsWith('image/') || f.type === 'application/pdf'));
        });

        function fuHandle(files) {
            files.forEach(file => {
                const reader = new FileReader();
                const id = Date.now() + '_' + Math.random().toString(36).slice(2);

                // PDF: base64 only, no conversion
                if (file.type === 'application/pdf') {
                    reader.onload = ev => { fuFiles.push({ id, name: file.name, data: ev.target.result, isPdf: true }); fuRender(); };
                    reader.readAsDataURL(file);
                    return;
                }

                // Image: convert to WebP
                reader.onload = ev => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width; canvas.height = img.height;
                        canvas.getContext('2d').drawImage(img, 0, 0);
                        fuFiles.push({
                            id,
                            name: file.name.replace(/\.[^.]+$/, '') + '.webp',
                            data: canvas.toDataURL('image/webp', 0.85)
                        });
                        fuRender();
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function fuRender() {
            document.getElementById('fu-preview').innerHTML = fuFiles.map(f => `
                <div class="relative w-20 h-20 rounded overflow-hidden border border-gray-200 shadow-sm">
                    ${f.isPdf
                    ? `<div class="w-full h-full bg-red-50 flex flex-col items-center justify-center">
                               <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
                               <span class="text-[8px] text-red-400 mt-1">PDF</span></div>`
                    : `<img src="${f.data}" class="w-full h-full object-cover">`}
                    <button type="button" onclick="fuRemove('${f.id}')"
                        class="absolute top-0.5 right-0.5 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <p class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-[8px] px-1 truncate">${f.name}</p>
                </div>`).join('');
        }

        function fuRemove(id) {
            fuFiles = fuFiles.filter(f => f.id !== id);
            fuRender();
        }

        // ── Search ───────────────────────────────────
        function fuFilter() {
            const input = document.getElementById('fu-search');
            const q = (input ? input.value : '').trim().toLowerCase();
            const rows = [...document.querySelectorAll('.fu-card')];
            let shown = 0;
            rows.forEach(tr => {
                const ok = !q || tr.dataset.search.includes(q);
                tr.classList.toggle('hidden', !ok);
                if (ok) shown++;
            });
            const noMatch = document.getElementById('fu-no-match');
            if (noMatch) noMatch.classList.toggle('hidden', !(rows.length && shown === 0));
            const res = document.getElementById('fu-result');
            if (res) res.textContent = 'Showing ' + shown + ' of ' + rows.length + ' request' + (rows.length !== 1 ? 's' : '');
        }
        document.getElementById('fu-search')?.addEventListener('input', fuFilter);

        // ── Submit ───────────────────────────────────
        function fuToast(msg, type = 'success') {
            const t = document.createElement('div');
            t.className = `fixed bottom-5 right-5 z-[300] ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white text-sm font-semibold px-5 py-3 rounded-lg shadow-lg flex items-center gap-2`;
            t.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-check' : 'fa-triangle-exclamation'}"></i> ${msg}`;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        function fuSubmit() {
            if (!fuRequestId) return;
            if (!fuFiles.length) { fuToast('Please attach at least one file.', 'error'); return; }

            const btn = document.getElementById('fu-submit');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Uploading...';

            const doneId = fuRequestId;

            fetch('<?= BASE_URL ?>/uploadfollowup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: doneId,
                    attachments: fuFiles.map(f => ({ name: f.name, data: f.data }))
                })
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if (!data.success) { fuToast(data.error || 'Upload failed.', 'error'); return; }

                    fuClose();
                    fuToast('Attachment uploaded!');

                    // Remove the card from the list + update nav badge
                    const card = document.querySelector(`.fu-card[data-id="${doneId}"]`);
                    if (card) {
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            const remaining = document.querySelectorAll('.fu-card').length;
                            if (window.setFuBadge) setFuBadge(remaining);
                            document.getElementById('fu-count').textContent = remaining;
                            fuFilter();
                            if (!remaining) {
                                document.getElementById('fu-empty').classList.remove('hidden');
                                document.getElementById('fu-table-wrap').classList.add('hidden');
                                document.getElementById('fu-footer').classList.add('hidden');
                            }
                        }, 300);
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    fuToast('Network error. Please try again.', 'error');
                });
        }
    </script>
</body>

</html>