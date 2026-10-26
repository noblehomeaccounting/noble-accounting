<?php
// set-6operations.php — included by monitoringcrmview.php (pagkatapos ng set-5production.php)
// Operations ng Project Coordinator bawat DR: DR approval → Foreman → Labor quotation → Timeline
// → Progress billing → Punch list → Turnover. Ginagamit ang monEscapeHtml / monFormatMoney ng monitoringcrmview.php.
?>
<section id="monOperations" class="hidden mt-5">
    <div class="flex items-center gap-2 mb-3">
        <span class="w-3 h-3 rounded-sm bg-gray-800"></span>
        <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-gray-500">Operations → Turnover</h2>
    </div>
    <div id="monOperationsBody" class="space-y-5"></div>
</section>

<script>
    (function () {
        const esc = s => monEscapeHtml(s == null ? '' : String(s));
        const money = v => monFormatMoney(v);

        function fDate(v) {
            if (!v) return '—';
            const s = String(v);
            const dt = new Date(s.length <= 10 ? s + 'T00:00:00' : s.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return esc(s);
            return s.length <= 10
                ? dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        const CHIP = {
            green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            amber: 'bg-amber-50 text-amber-700 border-amber-200',
            blue:  'bg-sky-50 text-sky-700 border-sky-200',
            red:   'bg-red-50 text-red-700 border-red-200',
            gray:  'bg-gray-50 text-gray-500 border-gray-200',
        };
        const chip = (text, color) =>
            `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold tracking-wide border whitespace-nowrap ${CHIP[color] || CHIP.gray}">${esc(text)}</span>`;

        // status ng approval (pending / approved / returned) → [label, color]
        function apStatus(s, pendingLabel = 'For approval') {
            if (s === 'approved') return ['Approved', 'green'];
            if (s === 'returned') return ['Returned', 'red'];
            if (s === 'pending') return [pendingLabel, 'blue'];
            return ['—', 'gray'];
        }
        const BILL = {
            pending_head: ['For Accounting Head', 'amber'],
            pending_sa:   ['Noted · for Superadmin', 'blue'],
            approved:     ['Approved', 'green'],
            returned:     ['Returned', 'red'],
        };
        const TURN = {
            pending_sa: ['For Superadmin', 'blue'],
            approved:   ['Signed · waiting client', 'amber'],
            returned:   ['Returned', 'red'],
            closed:     ['Turned over', 'green'],
        };

        // 8 hakbang; state = done | now | warn | todo
        function steps(o) {
            const drOk = !o.dr_status || o.dr_status === 'approved'; // lumang DR (walang status) = tuloy
            const lq = o.labor, b = o.billing, p = o.punch, t = o.turnover;
            const pDone = p && p.open === 0 && p.fixed === 0;
            return [
                ['DR created', 'done'],
                ['DR approved', drOk ? 'done' : (o.dr_status === 'returned' ? 'warn' : 'now')],
                ['Foreman', o.foreman ? 'done' : (drOk ? 'now' : 'todo')],
                ['Labor quote', lq ? (lq.status === 'approved' ? 'done' : lq.status === 'returned' ? 'warn' : 'now') : (o.foreman ? 'now' : 'todo')],
                ['Timeline', o.timeline ? 'done' : (lq && lq.status === 'approved' ? 'now' : 'todo')],
                ['Billing', b ? (b.complete ? 'done' : b.rows.some(r => r.status === 'returned') ? 'warn' : 'now') : (o.timeline ? 'now' : 'todo')],
                ['Punch list', p ? (pDone ? 'done' : 'now') : (b && b.complete ? 'now' : 'todo')],
                ['Turnover', t ? (t.status === 'closed' ? 'done' : t.status === 'returned' ? 'warn' : 'now') : (pDone && b && b.complete ? 'now' : 'todo')],
            ];
        }

        function stepper(list) {
            const DOT = {
                done: 'bg-emerald-500 text-white border-emerald-500',
                now:  'bg-white text-amber-700 border-amber-500 ring-4 ring-amber-50',
                warn: 'bg-red-500 text-white border-red-500',
                todo: 'bg-white text-gray-300 border-gray-200',
            };
            const TXT = { done: 'text-gray-700', now: 'text-amber-700 font-semibold', warn: 'text-red-600 font-semibold', todo: 'text-gray-400' };
            return `<ol class="grid grid-cols-4 sm:grid-cols-8 gap-y-3">
                ${list.map(([label, st], i) => `
                <li class="relative flex flex-col items-center text-center px-1">
                    ${i > 0 ? `<span class="absolute top-3 right-1/2 w-full h-0.5 ${i === 4 ? 'hidden sm:block' : ''} ${st === 'todo' ? 'bg-gray-200' : 'bg-emerald-300'}"></span>` : ''}
                    <span class="relative z-10 w-6 h-6 rounded-full border-2 flex items-center justify-center text-[10px] font-bold ${DOT[st]}">${st === 'done' ? '✓' : st === 'warn' ? '!' : i + 1}</span>
                    <span class="mt-1.5 text-[10.5px] leading-tight ${TXT[st]}">${esc(label)}</span>
                </li>`).join('')}
            </ol>`;
        }

        const row = (label, value) => `
            <div class="flex justify-between gap-3 py-1.5 border-b border-gray-100 last:border-b-0 text-[12.5px]">
                <span class="text-gray-400 whitespace-nowrap">${esc(label)}</span>
                <span class="text-gray-800 font-medium text-right break-words min-w-0">${value || '—'}</span>
            </div>`;

        function block(title, right, inner) {
            return `
            <div class="border border-gray-100 rounded-lg p-3.5 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h4 class="text-[11px] font-bold uppercase tracking-[0.1em] text-gray-500">${esc(title)}</h4>
                    ${right || ''}
                </div>
                ${inner}
            </div>`;
        }
        const empty = text => `<p class="text-xs text-gray-400 py-2">${esc(text)}</p>`;
        const remark = r => r ? `<p class="mt-2 text-[11.5px] text-red-600 bg-red-50 border border-red-100 rounded px-2 py-1.5">Return remarks: ${esc(r)}</p>` : '';

        function drBlock(o) {
            const [l, c] = o.dr_status ? apStatus(o.dr_status) : ['—', 'gray'];
            const permit = o.permit_required === 1 ? 'Required' : o.permit_required === 0 ? 'Not required' : '—';
            return block('Delivery receipt', o.dr_status ? chip(l, c) : '', `
                ${row('Created', fDate(o.created_at))}
                ${row('Prepared by', esc(o.prepared_by))}
                ${row('Permit', permit)}
                ${o.dr_status === 'approved' ? row('Approved by', esc(o.dr_approved_by) + `<span class="block text-[11px] text-gray-400 font-normal">${fDate(o.dr_approved_at)}</span>`) : ''}
                ${o.dr_status === 'returned' ? remark(o.dr_return_remarks) : ''}`);
        }

        function foremanBlock(f) {
            if (!f) return block('Foreman', '', empty('No foreman assigned yet.'));
            return block('Foreman', chip('Assigned', 'green'), `
                <div class="flex gap-3">
                    ${f.id_photo ? `<a href="${esc(f.id_photo)}" target="_blank" rel="noopener" class="shrink-0">
                        <img src="${esc(f.id_photo)}" alt="Foreman ID" class="w-16 h-16 object-cover rounded border border-gray-200 hover:border-amber-500"></a>` : ''}
                    <div class="flex-1 min-w-0">
                        ${row('Name', esc(f.name))}
                        ${row('Contact', esc(f.contact_no))}
                        ${row('ID', esc([f.id_type, f.id_number].filter(Boolean).join(' · ')))}
                    </div>
                </div>`);
        }

        function laborBlock(q) {
            if (!q) return block('Labor quotation', '', empty('No labor quotation yet.'));
            const [l, c] = apStatus(q.status);
            return block('Labor quotation', chip(l, c), `
                ${row('Quote no.', `<span class="font-mono">${esc(q.quote_no)}</span>`)}
                ${row('Subcontractor', esc(q.subcontractor))}
                ${row('Total', money(q.total))}
                ${q.duration_days ? row('Duration', q.duration_days + ' days') : ''}
                ${q.sections.length ? row('Sections', esc(q.sections.join(', '))) : ''}
                ${row(q.status === 'approved' ? 'Approved' : 'Submitted', q.status === 'approved'
                    ? esc(q.approved_by) + `<span class="block text-[11px] text-gray-400 font-normal">${fDate(q.approved_at)}</span>`
                    : fDate(q.submitted_at))}
                ${q.status === 'returned' ? remark(q.return_remarks) : ''}`);
        }

        function timelineBlock(t) {
            if (!t) return block('Timeline', '', empty('No timeline yet.'));
            const pct = t.task_count ? Math.round(t.done / t.task_count * 100) : 0;
            return block('Timeline', chip(pct >= 100 ? 'Done' : pct + '% elapsed', pct >= 100 ? 'green' : 'amber'), `
                ${row('Start', fDate(t.date_started))}
                ${row('Finish', fDate(t.date_finish))}
                ${row('Tasks', `${t.done} / ${t.task_count} past end date`)}
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-amber-500" style="width:${pct}%"></div></div>
                ${t.phases.length ? `<p class="mt-2 text-[11px] text-gray-400 break-words">${esc(t.phases.join(' · '))}</p>` : ''}`);
        }

        function billingBlock(b) {
            if (!b) return block('Progress billing', '', empty('No billing yet.'));
            const pct = b.contract > 0 ? Math.min(100, Math.round(b.billed / b.contract * 100)) : 0;
            const list = b.rows.map(r => {
                const [l, c] = BILL[r.status] || [r.status, 'gray'];
                const who = r.status === 'approved' ? `${esc(r.sa_by)} · ${fDate(r.sa_at)}`
                    : r.status === 'pending_sa' ? `Noted by ${esc(r.head_by)}` : fDate(r.billing_date);
                return `
                <li class="flex items-start justify-between gap-2 py-1.5 border-b border-gray-100 last:border-b-0">
                    <div class="min-w-0">
                        <p class="text-[12.5px] text-gray-800 font-medium">${esc(r.label)} <span class="font-mono text-[10.5px] text-gray-400">${esc(r.ref_no)}</span></p>
                        <p class="text-[11px] text-gray-400 break-words">${who}</p>
                        ${r.status === 'returned' && r.return_remarks ? `<p class="text-[11px] text-red-600 break-words">${esc(r.return_remarks)}</p>` : ''}
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-[12.5px] font-semibold text-gray-800">${money(r.this_period)}</p>
                        ${chip(l, c)}
                    </div>
                </li>`;
            }).join('');
            return block('Progress billing', chip(b.complete ? 'Fully billed' : pct + '% billed', b.complete ? 'green' : 'amber'), `
                <div class="grid grid-cols-3 gap-2 mb-2 text-center">
                    <div class="bg-gray-50 rounded px-1 py-1.5"><p class="text-[10px] text-gray-400 uppercase">Contract</p><p class="text-[12px] font-semibold text-gray-800 break-words">${money(b.contract)}</p></div>
                    <div class="bg-emerald-50 rounded px-1 py-1.5"><p class="text-[10px] text-emerald-600 uppercase">Billed</p><p class="text-[12px] font-semibold text-emerald-700 break-words">${money(b.billed)}</p></div>
                    <div class="bg-amber-50 rounded px-1 py-1.5"><p class="text-[10px] text-amber-700 uppercase">Balance</p><p class="text-[12px] font-semibold text-amber-800 break-words">${money(b.balance)}</p></div>
                </div>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden mb-1"><div class="h-full bg-emerald-500" style="width:${pct}%"></div></div>
                <p class="text-[11px] text-gray-400 mb-1">Retainage ${Number(b.retainage_rate)}% · approved applications only are counted as billed</p>
                <ul>${list}</ul>`);
        }

        function punchBlock(p) {
            if (!p) return block('Punch list', '', empty('No punch list items yet.'));
            const total = p.open + p.fixed + p.verified;
            const closed = p.open === 0 && p.fixed === 0;
            return block('Punch list', chip(closed ? 'Closed' : 'Open', closed ? 'green' : 'amber'), `
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-red-50 rounded py-1.5"><p class="text-[10px] text-red-600 uppercase">Open</p><p class="text-sm font-semibold text-red-700">${p.open}</p></div>
                    <div class="bg-amber-50 rounded py-1.5"><p class="text-[10px] text-amber-700 uppercase">Fixed</p><p class="text-sm font-semibold text-amber-800">${p.fixed}</p></div>
                    <div class="bg-emerald-50 rounded py-1.5"><p class="text-[10px] text-emerald-600 uppercase">Verified</p><p class="text-sm font-semibold text-emerald-700">${p.verified}</p></div>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">${p.verified} of ${total} items verified</p>`);
        }

        function turnoverBlock(t) {
            if (!t) return block('Turnover', '', empty('No turnover letter yet.'));
            const [l, c] = TURN[t.status] || [t.status, 'gray'];
            return block('Turnover', chip(l, c), `
                ${row('Letter date', fDate(t.letter_date))}
                ${row('Client', esc(t.client_name))}
                ${row('Submitted', fDate(t.submitted_at))}
                ${t.sa_at ? row('Signed by', esc(t.sa_by) + `<span class="block text-[11px] text-gray-400 font-normal">${fDate(t.sa_at)}</span>`) : ''}
                ${t.accepted_date ? row('Accepted by client', fDate(t.accepted_date)) : ''}
                ${t.status === 'closed' ? row('Turned over', fDate(t.closed_at) + (t.closed_by ? `<span class="block text-[11px] text-gray-400 font-normal">${esc(t.closed_by)}</span>` : '')) : ''}
                ${t.signed_copy ? row('Signed copy', `<a href="${esc(t.signed_copy)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View file</a>`) : ''}
                ${t.status === 'returned' ? remark(t.return_remarks) : ''}`);
        }

        function overall(o) {
            const t = o.turnover;
            if (t && t.status === 'closed') return chip('Turned over', 'green');
            const st = steps(o);
            const warn = st.find(s => s[1] === 'warn');
            if (warn) return chip(warn[0] + ' returned', 'red');
            const now = st.find(s => s[1] === 'now');
            return now ? chip('At: ' + now[0], 'amber') : chip('In progress', 'gray');
        }

        window.monRenderOperations = function (ops) {
            const wrap = document.getElementById('monOperations');
            const body = document.getElementById('monOperationsBody');
            if (!Array.isArray(ops) || !ops.length) { wrap.classList.add('hidden'); body.innerHTML = ''; return; }
            wrap.classList.remove('hidden');
            body.innerHTML = ops.map(o => `
                <article class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 min-w-0">
                    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Delivery receipt</p>
                            <h3 class="text-gray-900 text-base font-semibold font-mono break-all">${esc(o.dr_no)}</h3>
                        </div>
                        ${overall(o)}
                    </div>
                    ${stepper(steps(o))}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-5">
                        ${drBlock(o)}
                        ${foremanBlock(o.foreman)}
                        ${laborBlock(o.labor)}
                        ${timelineBlock(o.timeline)}
                        <div class="md:col-span-2 min-w-0">${billingBlock(o.billing)}</div>
                        ${punchBlock(o.punch)}
                        ${turnoverBlock(o.turnover)}
                    </div>
                </article>`).join('');
        };
    })();
</script>