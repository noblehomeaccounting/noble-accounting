<?php
// set-5production.php — kasama sa monitoringcrmview.php
// Deposit / Notice to Proceed → Cutting List → BOM → Purchase Request → Transmittal → Delivery Receipt.
// Ang laman ay galing sa monitoringcrmviewajax.php (?action=timeline → "production").
?>
<section class="bg-white rounded-lg shadow-sm border border-gray-200 mb-5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">After Final Approval</p>
            <h2 class="text-gray-900 text-base font-semibold">Production &amp; Delivery</h2>
        </div>
        <span id="monProdNow" class="text-[11px] text-gray-400"></span>
    </div>

    <!-- 5 yugto -->
    <div id="monProdSteps" class="px-5 pt-4"></div>

    <div id="monProduction" class="px-5 py-4">
        <div class="space-y-2">
            <div class="h-4 w-48 rounded bg-gray-100 animate-pulse"></div>
            <div class="h-4 w-full rounded bg-gray-100 animate-pulse"></div>
            <div class="h-4 w-2/3 rounded bg-gray-100 animate-pulse"></div>
        </div>
    </div>
</section>

<!-- larawan ng cutting list (malaking view) -->
<div id="monProdPhoto" class="hidden fixed inset-0 z-[9999] bg-black/80 items-center justify-center p-6" onclick="this.classList.add('hidden');this.classList.remove('flex')">
    <img id="monProdPhotoImg" src="" alt="" class="max-h-full max-w-full rounded-lg shadow-2xl">
</div>

<script>
    (function () {
        const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
        const dt = v => {
            if (!v) return '';
            const s = String(v);
            const d = new Date(s.length <= 10 ? s + 'T00:00:00' : s.replace(' ', 'T'));
            if (isNaN(d.getTime())) return esc(s);
            return s.length <= 10
                ? d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        };
        const money = n => '₱' + (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const qty = q => { const n = parseFloat(q); return isNaN(n) ? esc(q) : String(+n.toFixed(2)); };

        const PILL = {
            green: 'bg-green-50 text-green-700 border-green-200', amber: 'bg-amber-50 text-amber-700 border-amber-200',
            red: 'bg-red-50 text-red-700 border-red-200', blue: 'bg-sky-50 text-sky-700 border-sky-200', gray: 'bg-gray-50 text-gray-500 border-gray-200',
        };
        const pill = (text, tone) => `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${PILL[tone] || PILL.gray}">${esc(text)}</span>`;

        // isang hilera ng "sino at kailan" (pirma)
        const signRow = (label, who, when, ok = true) => `
            <div class="flex items-start gap-2.5 py-1.5">
                <span class="mt-0.5 w-4 h-4 rounded-full flex items-center justify-center text-[9px] shrink-0 ${when ? (ok ? 'bg-green-500 text-white' : 'bg-red-500 text-white') : 'border border-dashed border-gray-300 text-gray-300'}">${when ? (ok ? '✓' : '!') : ''}</span>
                <div class="min-w-0 text-xs">
                    <span class="text-gray-500">${esc(label)}</span>
                    ${when ? `<span class="text-gray-800 font-medium">${who ? ' · ' + esc(who) : ''}</span><span class="text-gray-400"> · ${dt(when)}</span>` : '<span class="text-gray-300"> · waiting</span>'}
                </div>
            </div>`;

        const kv = (k, v) => `<div class="min-w-0"><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">${k}</p><p class="text-xs text-gray-800 font-medium mt-0.5 break-words">${v || '<span class="text-gray-300">—</span>'}</p></div>`;

        // listahan ng items (naka-tago hanggang buksan)
        function itemsBox(title, head, rows) {
            if (!rows.length) return '';
            return `<details class="mt-3 rounded-lg border border-gray-200 overflow-hidden group">
                <summary class="cursor-pointer select-none px-3 py-2 bg-gray-50 text-[11px] font-semibold text-gray-600 flex items-center justify-between">
                    <span>${title} <span class="text-gray-400 font-normal">(${rows.length})</span></span>
                    <span class="text-gray-400 group-open:rotate-180 transition-transform">▾</span>
                </summary>
                <table class="w-full table-fixed text-[11.5px]">
                    <thead class="text-[10px] uppercase tracking-wider text-gray-400"><tr>${head.map(h => `<th class="px-3 py-1.5 text-left font-semibold" ${h.w ? `style="width:${h.w}"` : ''}>${h.t}</th>`).join('')}</tr></thead>
                    <tbody class="divide-y divide-gray-100">${rows.map(r => `<tr>${r.map((c, i) => `<td class="px-3 py-1.5 align-top break-words ${head[i].r ? 'text-right tabular-nums' : ''}">${c}</td>`).join('')}</tr>`).join('')}</tbody>
                </table>
            </details>`;
        }

        // isang yugto (card)
        function stage(no, title, statusPill, body, done) {
            return `<div class="relative pl-10 pb-6 last:pb-0">
                <span class="absolute left-[15px] top-8 bottom-0 w-px bg-gray-200"></span>
                <span class="absolute left-0 top-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${done ? 'bg-green-600 text-white' : 'bg-white border-2 border-gray-200 text-gray-400'}">${done ? '✓' : no}</span>
                <div class="flex flex-wrap items-center gap-2 mb-2 pt-1">
                    <p class="text-sm font-semibold text-gray-900">${title}</p>${statusPill}
                </div>
                ${body}
            </div>`;
        }
        const empty = text => `<p class="text-xs text-gray-400 italic">${text}</p>`;
        const block = inner => `<div class="rounded-lg border border-gray-200 p-3.5 mb-2 last:mb-0">${inner}</div>`;

        // ── 0. Deposit & Notice to Proceed (Accounting) ──
        function depositHtml(d) {
            if (!d) return empty('No deposit information yet.');
            const ntp = d.status === 'Notice to Proceed';
            if (!ntp) return block(`
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Waiting for Accounting to log the client's deposit.</p>${pill('Hold', 'amber')}
                </div>`);
            return block(`
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500">Deposit received — production can start.</p>${pill('Notice to Proceed', 'green')}
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                    ${kv('Amount deposited', `<span class="text-sm font-bold">${money(d.amount)}</span>`)}
                    ${kv('Payment method', esc(d.payment_method))}
                    ${kv('Deposit slip', d.slip ? `<a href="${esc(d.slip)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View slip</a>` : '')}
                </div>
                <div class="mt-2 border-t border-gray-100 pt-1">${signRow('Logged (Accounting)', d.logged_by, d.logged_at)}</div>`);
        }

        // ── 1. Cutting list ──
        function cuttingHtml(list) {
            if (!list.length) return empty('No cutting list uploaded yet.');
            return list.map(c => {
                const st = c.qr_approved ? pill('Approved', 'green') : (c.qr_rejected && !c.photos.length ? pill('Rejected · re-upload', 'red') : pill(c.status || 'For approval', 'amber'));
                const photos = c.photos.length ? `<div class="flex flex-wrap gap-2 mt-3">${c.photos.map(p => `
                    <button type="button" data-photo="${esc(p)}" class="w-16 h-16 rounded-md overflow-hidden border border-gray-200 hover:ring-2 hover:ring-amber-400">
                        <img src="${esc(p)}" alt="" class="w-full h-full object-cover" loading="lazy"></button>`).join('')}</div>` : '';
                const file = c.archive ? `<a href="${esc(c.archive)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 hover:underline"><i class="fa-solid fa-file-zipper"></i>${esc(c.archive_name || 'Cutting file')}</a>` : '';
                return block(`
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-gray-500">Uploaded ${c.uploaded_at ? dt(c.uploaded_at) : ''}${c.uploaded_by ? ' by <b class="text-gray-800">' + esc(c.uploaded_by) + '</b>' : ''}</p>${st}
                    </div>
                    ${file ? `<div class="mt-2">${file}</div>` : ''}
                    ${c.qr_rejected ? signRow('Rejected', c.rejected_by, c.rejected_at, false) + (c.rejection_remarks ? `<p class="ml-6 -mt-1 text-[11px] text-red-600 italic">“${esc(c.rejection_remarks)}”</p>` : '') : ''}
                    ${signRow('QR approved', c.approved_by, c.approved_at)}
                    ${photos}`);
            }).join('');
        }

        // ── 2. BOM ──
        function bomHtml(list) {
            if (!list.length) return empty('No bill of materials yet.');
            return list.map(b => block(`
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-mono text-xs font-semibold text-amber-700">${esc(b.bom_number)}</p>
                    ${b.received_at ? pill('Received by Purchasing', 'green') : b.approved_at ? pill('Approved', 'green') : pill('For approval', 'amber')}
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                    ${kv('Date submitted', dt(b.date_submitted))}${kv('Date needed', esc(b.date_needed))}${kv('Scope', esc(b.general_scope))}
                </div>
                <div class="mt-2 border-t border-gray-100 pt-1">
                    ${signRow('Requested', b.requested_by, b.created_at)}
                    ${signRow('Approved', b.approved_by, b.approved_at)}
                    ${signRow('Received (Purchasing)', b.received_by, b.received_at)}
                </div>
                ${itemsBox('Materials', [{ t: 'Code', w: '18%' }, { t: 'Description' }, { t: 'Qty', w: '12%', r: 1 }, { t: 'Unit', w: '12%' }, { t: 'Supplier', w: '22%' }],
                    b.items.map(i => [esc(i.item_code), esc(i.item_description), qty(i.quantity), esc(i.unit), esc(i.supplier)]))}`)).join('');
        }

        // ── 3. Purchase request ──
        function prStatus(p) {
            if (p.status === 'Rejected') return pill('Returned', 'red');
            if (p.status !== 'Approved') return pill('For CEO approval', 'amber');
            if (p.paid_at) return pill('Paid', 'green');
            if (p.released_at) return pill('Waiting to be paid', 'blue');
            if (p.staff_received_at) return pill('For release', 'blue');
            if (p.reviewed_at) return pill('Reviewed', 'blue');
            return pill('Approved by CEO', 'green');
        }
        function prHtml(list) {
            if (!list.length) return empty('No purchase request yet.');
            return list.map(p => block(`
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-mono text-xs font-semibold text-red-600">${esc(p.req_no)}</p>${prStatus(p)}
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                    ${kv('Total amount', `<span class="text-sm font-bold">${money(p.total_amount)}</span>`)}${kv('Request date', dt(p.request_date))}${kv('Date needed', esc(p.date_needed))}
                </div>
                <div class="mt-2 border-t border-gray-100 pt-1">
                    ${signRow('Prepared (Purchasing)', p.prepared_by, p.created_at)}
                    ${p.status === 'Rejected'
                        ? signRow('Returned by the CEO', '', p.rejected_at, false) + (p.rejection_remarks ? `<p class="ml-6 -mt-1 text-[11px] text-red-600 italic">“${esc(p.rejection_remarks)}”</p>` : '')
                        : signRow('Approved (CEO)', p.approved_by, p.approved_at)
                          + signRow('Reviewed (Accounting Head)', p.reviewed_by, p.reviewed_at)
                          + signRow('Received (Accounting Staff)', p.staff_received_by, p.staff_received_at)
                          + signRow('Released (Custodian)', p.released_by, p.released_at)
                          + signRow('Paid', '', p.paid_at)}
                </div>
                ${itemsBox('Items', [{ t: 'Item' }, { t: 'Qty', w: '16%', r: 1 }, { t: 'Price', w: '18%', r: 1 }, { t: 'Amount', w: '20%', r: 1 }],
                    p.items.map(i => [esc(i.item_name), qty(i.quantity) + ' ' + esc(i.unit || ''), money(i.price), money(i.amount)]))}`)).join('');
        }

        // ── 4. Transmittal ──
        function trHtml(list) {
            if (!list.length) return empty('No transmittal yet. It is made once the purchase request is paid.');
            return list.map(t => block(`
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-mono text-xs font-semibold text-gray-900">${esc(t.ref_no)}</p>
                    ${t.delivered_at ? pill('Delivered', 'green') : pill('For delivery', 'amber')}
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                    ${kv('Project', esc(t.project_name))}${kv('Particulars', esc(t.particulars))}${kv('Mode of delivery', esc(t.delivery_mode))}
                </div>
                <div class="mt-2 border-t border-gray-100 pt-1">
                    ${signRow('Issued', t.issued_by, t.created_at || t.transmittal_date)}
                    ${signRow('Delivered to client', t.received_by, t.delivered_at)}
                </div>
                ${itemsBox('Items', [{ t: 'Serial', w: '18%' }, { t: 'Description' }, { t: 'Qty', w: '16%' }],
                    t.items.map(i => [esc(i.serial_no), esc(i.description), esc(i.quantity)]))}`)).join('');
        }

        // ── 5. Delivery receipt (Project Coordinator) ──
        function drHtml(list) {
            if (!list.length) return empty('No delivery receipt yet. The Project Coordinator makes it once the transmittal is delivered.');
            return list.map(d => {
                const total = d.items.reduce((a, i) => a + (Number(i.amount) || 0) * (Number(i.qty) || 1), 0);
                return block(`
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-mono text-xs font-semibold text-sky-700">DR# ${esc(d.dr_no)}</p>${pill('Prepared', 'green')}
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                        ${kv('DR date', dt(d.dr_date))}${kv('Delivery schedule', dt(d.scheduled_date))}${kv('Total', total ? money(total) : '')}
                    </div>
                    <div class="mt-2 border-t border-gray-100 pt-1">${signRow('Prepared (Project Coordinator)', d.prepared_by, d.created_at)}</div>
                    ${itemsBox('Items', [{ t: 'Particular' }, { t: 'Size', w: '22%' }, { t: 'Qty', w: '12%', r: 1 }, { t: 'Unit', w: '12%' }],
                        d.items.map(i => [esc(i.particular), esc(i.size), qty(i.qty), esc(i.unit)]))}`);
            }).join('');
        }

        // ── buong section ──
        window.monRenderProduction = function (p) {
            const box = document.getElementById('monProduction');
            if (!p) { box.innerHTML = empty('Could not load production details.'); return; }
            const steps = [
                { t: 'Notice to Proceed', done: !!(p.deposit && p.deposit.status === 'Notice to Proceed') },
                { t: 'Cutting List', done: p.cutting.some(c => c.qr_approved) },
                { t: 'BOM', done: p.boms.some(b => b.received_at) },
                { t: 'Purchase Request', done: p.prs.some(x => x.paid_at) },
                { t: 'Transmittal', done: p.transmittals.some(t => t.delivered_at) },
                { t: 'Delivery Receipt', done: p.drs.length > 0 },
            ];
            const doneN = steps.filter(s => s.done).length;
            const cur = steps.find(s => !s.done);
            document.getElementById('monProdNow').innerHTML = cur
                ? `Now at: <b class="text-amber-700">${cur.t}</b>`
                : '<b class="text-green-700">All steps done</b>';
            document.getElementById('monProdSteps').innerHTML = `
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-green-500" style="width:${doneN / steps.length * 100}%"></div></div>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-1 mt-2">${steps.map(s => `<p class="text-[10px] font-semibold text-center truncate ${s.done ? 'text-green-700' : s === cur ? 'text-amber-700' : 'text-gray-400'}">${s.done ? '✓ ' : ''}${s.t}</p>`).join('')}</div>`;

            const done = i => steps[i].done ? pill('Done', 'green') : '';
            box.innerHTML =
                stage(1, 'Deposit &amp; Notice to Proceed', done(0), depositHtml(p.deposit), steps[0].done) +
                stage(2, 'Cutting List', done(1), cuttingHtml(p.cutting), steps[1].done) +
                stage(3, 'Bill of Materials', done(2), bomHtml(p.boms), steps[2].done) +
                stage(4, 'Purchase Request', done(3), prHtml(p.prs), steps[3].done) +
                stage(5, 'Transmittal', done(4), trHtml(p.transmittals), steps[4].done) +
                stage(6, 'Delivery Receipt', done(5), drHtml(p.drs), steps[5].done);

            box.querySelectorAll('[data-photo]').forEach(b => b.addEventListener('click', () => {
                document.getElementById('monProdPhotoImg').src = b.dataset.photo;
                const m = document.getElementById('monProdPhoto'); m.classList.remove('hidden'); m.classList.add('flex');
            }));
        };
    })();
</script>