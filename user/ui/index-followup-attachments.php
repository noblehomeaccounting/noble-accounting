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
        /* Scrollable request list */
        #fu-list {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, .5) transparent;
        }

        #fu-list::-webkit-scrollbar {
            width: 6px;
        }

        #fu-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .5);
            border-radius: 9999px;
        }

        /* Scrollable attachment preview inside the modal */
        #fu-preview {
            scrollbar-width: thin;
        }
    </style>
</head>

<body class="min-h-screen relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">

    <div class="absolute inset-0 bg-black/50 z-0"></div>

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="relative z-20 max-w-5xl mx-auto px-4 pt-25 md:pt-28 pb-12">

        <div class="mb-6">
            <h1 class="text-xl md:text-2xl font-bold text-white">Follow-up Attachments</h1>
            <p class="text-sm text-white mt-1">
                Requests you submitted without an attachment. Upload your proof or photos here.
            </p>
        </div>

        <div id="fu-empty"
            class="<?= $requests ? 'hidden' : '' ?> bg-white rounded-xl border border-gray-100 shadow-sm py-14 text-center">
            <i class="fa-regular fa-circle-check text-4xl text-green-400 mb-3"></i>
            <p class="text-sm font-semibold text-gray-700">You have nothing to upload.</p>
            <p class="text-xs text-gray-400 mt-1">All your requests already have attachments.</p>
        </div>

        <div id="fu-list" class="space-y-3 max-h-[calc(100vh-15rem)] overflow-y-auto pr-1">
            <?php foreach ($requests as $r): ?>
                <div class="fu-card bg-white rounded-xl border border-gray-100 shadow-sm p-4 md:p-5 flex flex-col md:flex-row md:items-center gap-4 transition-opacity duration-300"
                    data-id="<?= (int) $r['id'] ?>">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="font-mono text-xs font-bold text-orange-600">
                                <?= htmlspecialchars($r['control_no']) ?>
                            </span>
                            <span
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize">
                                <?= htmlspecialchars($r['status'] ?? 'pending') ?>
                            </span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-orange-50 text-orange-600">
                                <?= followupCategoryLabel($r['request_category']) ?>
                                <?php if (!empty($r['request_reference'])): ?>
                                    · <?= htmlspecialchars($r['request_reference']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 truncate">
                            <?= htmlspecialchars($r['purpose']) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <?= htmlspecialchars($r['requestor_name']) ?> ·
                            <?= htmlspecialchars($r['date_requested']) ?> ·
                            <?= (int) $r['item_count'] ?> item<?= $r['item_count'] == 1 ? '' : 's' ?> ·
                            <span class="font-mono text-gray-600">₱ <?= number_format($r['total'], 2) ?></span>
                        </p>
                    </div>
                    <button type="button"
                        onclick="fuOpen(<?= (int) $r['id'] ?>, '<?= htmlspecialchars($r['control_no'], ENT_QUOTES) ?>')"
                        class="shrink-0 flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 active:scale-95 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all">
                        <i class="fa-solid fa-cloud-arrow-up text-xs"></i> Upload attachment
                    </button>
                </div>
            <?php endforeach; ?>
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
                            if (!remaining) {
                                document.getElementById('fu-empty').classList.remove('hidden');
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