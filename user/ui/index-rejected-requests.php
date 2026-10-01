<?php
include ROOT_PATH . '/network/connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Requests</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="min-h-screen px-4 py-12 relative pt-16"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-attachment: fixed;">
    <div class="fixed inset-0 bg-black/50 z-0 pointer-events-none"></div>

    <div class="max-w-5xl mx-auto relative z-10">
        <h1 class="text-xl font-bold text-white">Rejected Requests</h1>
        <p class="text-sm text-gray-300 mb-4">Edit and resubmit</p>
        <div id="rej-list" class="space-y-3"></div>
    </div>

    <!-- Resubmit Modal -->
    <div id="rs-modal"
        class="hidden fixed inset-0 z-50 bg-black/50 flex items-start justify-center overflow-y-auto p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl my-6 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <div>
                    <h3 class="font-bold text-sm uppercase tracking-widest">Resubmit Request</h3>
                    <p id="rs-control" class="font-mono text-xs text-orange-500"></p>
                </div>
                <button onclick="rsClose()" class="text-gray-400 hover:text-gray-600"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div id="rs-reason"
                class="mx-5 mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700"></div>

            <div class="p-5 space-y-4">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-400">Requestor Name</label>
                        <input id="rs-name"
                            class="w-full border-b-2 border-gray-300 focus:border-orange-500 outline-none py-1 text-sm">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-400">Purpose</label>
                        <input id="rs-purpose"
                            class="w-full border-b-2 border-gray-300 focus:border-orange-500 outline-none py-1 text-sm">
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-400">Category</label>
                        <select id="rs-cat" onchange="rsCatChange()"
                            class="w-full border-b-2 border-gray-300 py-1 text-sm bg-transparent">
                            <option value="project">Project</option>
                            <option value="client">Client</option>
                            <option value="nhcc">NHCC</option>
                        </select>
                    </div>
                    <div id="rs-ref-wrap">
                        <label class="text-[10px] font-bold uppercase text-gray-400">Project / Client name</label>
                        <input id="rs-ref"
                            class="w-full border-b-2 border-gray-300 focus:border-orange-500 outline-none py-1 text-sm">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-orange-500 text-white text-[11px] uppercase">
                                <th class="px-2 py-2">No.</th>
                                <th class="px-2 text-left">Description</th>
                                <th class="px-2 text-left">Purpose</th>
                                <th class="px-2">Qty</th>
                                <th class="px-2">Unit Price</th>
                                <th class="px-2">Amount</th>
                                <th class="px-2 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody id="rs-rows"></tbody>
                        <tfoot>
                            <tr class="border-t-2 bg-gray-50">
                                <td colspan="5" class="px-2 py-2 text-right font-bold text-xs uppercase">Total:</td>
                                <td id="rs-total" class="px-2 py-2 text-right font-mono font-bold">₱ 0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button onclick="rsAddRow()" class="text-xs font-semibold bg-orange-500 text-white px-3 py-1.5 rounded">
                    <i class="fa-solid fa-plus text-[10px]"></i> Add Item
                </button>

                <div>
                    <p class="text-[10px] font-bold uppercase text-gray-400 mb-2"><i class="fa-solid fa-paperclip"></i>
                        Attachments</p>
                    <div id="rs-attach" class="flex flex-wrap gap-2 mb-2"></div>
                    <button onclick="document.getElementById('rs-file').click()"
                        class="text-xs border border-dashed border-orange-300 text-orange-500 px-3 py-1.5 rounded">
                        + Add file
                    </button>
                    <input type="file" id="rs-file" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="hidden">
                    <label class="flex items-center gap-2 mt-3 text-xs text-gray-600">
                        <input type="checkbox" id="rs-followup"> Follow up attachment
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-5 py-3 border-t bg-gray-50">
                <button onclick="rsClose()" class="text-sm text-gray-500 px-4 py-2">Cancel</button>
                <button id="rs-submit" onclick="rsSubmit()"
                    class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-5 py-2 rounded">
                    <i class="fa-solid fa-paper-plane text-xs"></i> Resubmit
                </button>
            </div>
        </div>
    </div>

    <script>
        let rejected = [], rsId = null, rsKeep = [], rsNew = [];

        function money(n) { return Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }); }
        function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

        function load() {
            fetch('<?= BASE_URL ?>/fetchrejectedrequests').then(r => r.json()).then(data => {
                rejected = data;
                const el = document.getElementById('rej-list');
                if (!data.length) { el.innerHTML = '<div class="bg-white rounded-xl p-8 text-center text-gray-400 text-sm">No rejected requests.</div>'; return; }
                el.innerHTML = data.map(r => `
            <div class="bg-white rounded-xl border border-red-100 shadow-sm p-4 flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="font-mono text-xs font-bold text-blue-500">${esc(r.control_no)}
                        ${r.resubmit_count > 0 ? `<span class="ml-2 text-[10px] text-gray-400">resubmitted ${r.resubmit_count}x</span>` : ''}</p>
                    <p class="text-sm font-semibold text-gray-800">${esc(r.purpose)}</p>
                    <p class="text-xs text-red-600 mt-1"><i class="fa-solid fa-circle-exclamation"></i> ${esc(r.reject_comment)}</p>
                </div>
                <button onclick="rsOpen(${r.id})" class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-4 py-2 rounded-lg">
                    <i class="fa-solid fa-pen"></i> Edit & Resubmit
                </button>
            </div>`).join('');
            });
        }

        function rsOpen(id) {
            const r = rejected.find(x => x.id == id); if (!r) return;
            rsId = id; rsKeep = [...(r.attachments || [])]; rsNew = [];
            document.getElementById('rs-control').textContent = r.control_no;
            document.getElementById('rs-reason').innerHTML = '<b>Reason for rejection:</b> ' + esc(r.reject_comment);
            document.getElementById('rs-name').value = r.requestor_name;
            document.getElementById('rs-purpose').value = r.purpose;
            document.getElementById('rs-cat').value = r.request_category || 'nhcc';
            document.getElementById('rs-ref').value = r.request_reference || '';
            document.getElementById('rs-followup').checked = r.attachment_status === 'follow_up';
            rsCatChange();
            document.getElementById('rs-rows').innerHTML = '';
            (r.items.length ? r.items : [{}]).forEach(it => rsAddRow(it));
            rsRenderAttach();
            document.getElementById('rs-modal').classList.remove('hidden');
        }
        function rsClose() { document.getElementById('rs-modal').classList.add('hidden'); }
        function rsCatChange() {
            document.getElementById('rs-ref-wrap').classList.toggle('hidden', document.getElementById('rs-cat').value === 'nhcc');
        }

        function rsAddRow(it = {}) {
            const tr = document.createElement('tr');
            tr.className = 'border-t';
            const inp = (v, ph, type = 'text', cls = '') => `<input type="${type}" value="${esc(v ?? '')}" placeholder="${ph}" ${type === 'number' ? 'min="0" step="0.01" oninput="rsCalc()"' : ''} class="w-full outline-none text-sm py-1 px-1 border-b border-gray-100 ${cls}">`;
            tr.innerHTML = `
        <td class="px-2 text-center text-xs text-gray-400 rs-no"></td>
        <td class="px-1">${inp(it.description, 'Description')}</td>
        <td class="px-1">${inp(it.purpose, 'Purpose')}</td>
        <td class="px-1 w-20">${inp(it.quantity, '0', 'number', 'text-center')}</td>
        <td class="px-1 w-28">${inp(it.unit_price, '0.00', 'number', 'text-right')}</td>
        <td class="px-2 text-right font-mono text-sm rs-amt">₱ 0.00</td>
        <td class="px-1"><div class="flex">${inp(it.notes, 'Notes')}
            <button onclick="this.closest('tr').remove();rsCalc()" class="text-red-400 ml-1"><i class="fa-solid fa-xmark text-xs"></i></button></div></td>`;
            document.getElementById('rs-rows').appendChild(tr);
            rsCalc();
        }

        function rsCalc() {
            let total = 0;
            document.querySelectorAll('#rs-rows tr').forEach((tr, i) => {
                const ins = tr.querySelectorAll('input');
                const amt = (parseFloat(ins[2].value) || 0) * (parseFloat(ins[3].value) || 0);
                tr.querySelector('.rs-no').textContent = i + 1;
                tr.querySelector('.rs-amt').textContent = '₱ ' + money(amt);
                total += amt;
            });
            document.getElementById('rs-total').textContent = '₱ ' + money(total);
        }

        function rsRenderAttach() {
            const old = rsKeep.map((p, i) => `
        <div class="relative w-16 h-16 border rounded overflow-hidden bg-gray-50 flex items-center justify-center">
            ${p.toLowerCase().endsWith('.pdf')
                    ? '<i class="fa-solid fa-file-pdf text-red-500 text-xl"></i>'
                    : `<img src="<?= BASE_URL ?>/${esc(p)}" class="w-full h-full object-cover">`}
            <button onclick="rsKeep.splice(${i},1);rsRenderAttach()" class="absolute top-0 right-0 bg-red-500 text-white w-4 h-4 text-[9px] rounded-bl">×</button>
        </div>`).join('');
            const fresh = rsNew.map((f, i) => `
        <div class="relative w-16 h-16 border border-orange-300 rounded overflow-hidden bg-gray-50 flex items-center justify-center">
            ${f.isPdf ? '<i class="fa-solid fa-file-pdf text-red-500 text-xl"></i>' : `<img src="${f.data}" class="w-full h-full object-cover">`}
            <button onclick="rsNew.splice(${i},1);rsRenderAttach()" class="absolute top-0 right-0 bg-red-500 text-white w-4 h-4 text-[9px] rounded-bl">×</button>
        </div>`).join('');
            document.getElementById('rs-attach').innerHTML = old + fresh;
        }

        document.getElementById('rs-file').addEventListener('change', e => {
            [...e.target.files].forEach(file => {
                const reader = new FileReader();
                reader.onload = ev => {
                    if (file.type === 'application/pdf') {
                        rsNew.push({ name: file.name, data: ev.target.result, isPdf: true }); rsRenderAttach(); return;
                    }
                    const img = new Image();
                    img.onload = () => {
                        const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
                        c.getContext('2d').drawImage(img, 0, 0);
                        rsNew.push({ name: file.name.replace(/\.[^.]+$/, '') + '.webp', data: c.toDataURL('image/webp', 0.85) });
                        rsRenderAttach();
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });
            e.target.value = '';
        });

        function rsSubmit() {
            const cat = document.getElementById('rs-cat').value;
            const ref = document.getElementById('rs-ref').value.trim();
            const name = document.getElementById('rs-name').value.trim();
            const purpose = document.getElementById('rs-purpose').value.trim();
            const followUp = document.getElementById('rs-followup').checked;

            if (!name || !purpose) return alert('Fill in Requestor Name and Purpose.');
            if (cat !== 'nhcc' && !ref) return alert('Enter the project or client name.');
            if (!followUp && !rsKeep.length && !rsNew.length) return alert('Attach at least one file, or toggle "Follow up attachment".');

            const items = [...document.querySelectorAll('#rs-rows tr')].map(tr => {
                const i = tr.querySelectorAll('input');
                const amt = (parseFloat(i[2].value) || 0) * (parseFloat(i[3].value) || 0);
                return {
                    description: i[0].value, purpose: i[1].value, quantity: i[2].value || 0,
                    unit_price: i[3].value || 0, amount: amt.toFixed(2), notes: i[4].value
                };
            }).filter(x => x.description.trim());

            if (!items.length) return alert('Add at least one item.');

            const btn = document.getElementById('rs-submit');
            btn.disabled = true;
            fetch('<?= BASE_URL ?>/resubmitrequest', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: rsId, requestor_name: name, purpose, items,
                    keep_attachments: rsKeep,
                    attachments: rsNew.map(f => ({ name: f.name, data: f.data })),
                    attachment_status: followUp ? 'follow_up' : 'attached',
                    request_category: cat,
                    request_reference: cat !== 'nhcc' ? ref : null
                })
            }).then(r => r.json()).then(d => {
                btn.disabled = false;
                if (d.success) { rsClose(); load(); }
                else alert('Failed: ' + (d.error || 'Unknown error'));
            }).catch(() => { btn.disabled = false; alert('Network error.'); });
        }

        load();
    </script>
</body>

</html>