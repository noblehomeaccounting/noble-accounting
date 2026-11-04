<?php
// salespo-doc.php — iisang itsura ng Purchase Order (ginagamit ng purchaseordermain.php at salespoapproval.php)
//   SalesPO.render(el, po, { edit: true|false, preview: { col, url, name } })
//   SalesPO.collect(el) → laman ng form (edit mode)
//   SalesPO.print(po)
?>
<style>
    .spo { --ink: #111827; --mute: #6b7280; --line: #e5e7eb; --soft: #f8fafc; --navy: #1f2937; --amber: #b45309; --amber2: #f59e0b;
        font-family: inherit; color: var(--ink); font-size: 12px; line-height: 1.4; background: #fff; }
    .spo * { box-sizing: border-box; }
    .spo .hz { height: 6px; background: repeating-linear-gradient(-45deg, var(--amber2) 0 10px, var(--navy) 10px 20px); border-radius: 3px; }
    .spo .top { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; padding: 16px 0 14px; }
    .spo .co { display: flex; gap: 12px; align-items: center; min-width: 0; }
    .spo .co img { height: 54px; width: auto; }
    .spo .co h1 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: .02em; }
    .spo .co p { margin: 1px 0 0; font-size: 10px; color: var(--mute); }
    .spo .ttl { text-align: right; }
    .spo .ttl h2 { margin: 0 0 6px; font-size: 22px; font-weight: 800; letter-spacing: .08em; color: var(--navy); }
    .spo .meta { display: inline-grid; grid-template-columns: auto auto; border: 1px solid var(--navy); border-radius: 6px; overflow: hidden; font-size: 11px; }
    .spo .meta div { padding: 4px 10px; }
    .spo .meta .k { background: var(--navy); color: #fff; font-weight: 700; font-size: 9.5px; letter-spacing: .06em; text-transform: uppercase; }
    .spo .meta .v { font-weight: 700; font-family: ui-monospace, Consolas, monospace; text-align: center; }
    .spo .pill { display: inline-block; margin-top: 6px; padding: 2px 9px; border-radius: 999px; font-size: 10px; font-weight: 700; border: 1px solid; }
    .spo .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 6px; }
    .spo .card { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
    .spo .card h3 { margin: 0; padding: 6px 10px; background: var(--navy); color: #fff; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
    .spo .card h3 i { color: var(--amber2); }
    .spo .kv { display: grid; grid-template-columns: 112px 1fr; font-size: 11.5px; }
    .spo .kv > div { padding: 5px 10px; border-top: 1px solid var(--line); min-height: 27px; display: flex; align-items: center; }
    .spo .kv > div:nth-child(-n+2) { border-top: 0; }
    .spo .kv .k { background: var(--soft); color: var(--mute); font-size: 9.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .spo .items { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 14px; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
    .spo .items th { background: var(--navy); color: #fff; font-size: 10px; letter-spacing: .05em; text-transform: uppercase; font-weight: 700; padding: 7px 8px; text-align: left; }
    .spo .items td { padding: 6px 8px; border-top: 1px solid var(--line); vertical-align: middle; font-size: 11.5px; }
    .spo .items tbody tr:nth-child(even) td { background: #fcfcfd; }
    .spo .items .n { width: 34px; text-align: center; color: var(--mute); }
    .spo .items .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .spo .items .c { text-align: center; }
    .spo .items .spec { display: block; font-size: 10px; color: var(--mute); }
    .spo .items .empty td { height: 28px; }
    .spo .nf { text-align: center; font-size: 10px; letter-spacing: .2em; color: #9ca3af; font-style: italic; padding: 8px 0 2px; }
    .spo .bottom { display: grid; grid-template-columns: 1fr 330px; gap: 14px; margin-top: 12px; align-items: start; }
    .spo .note h4 { margin: 0 0 4px; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: var(--mute); }
    .spo .note .box { border: 1px solid var(--line); border-radius: 8px; min-height: 74px; padding: 8px 10px; white-space: pre-line; font-size: 11.5px; }
    .spo .sum { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; font-size: 11.5px; }
    .spo .sum > div { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 5px 10px; border-top: 1px solid var(--line); min-height: 28px; }
    .spo .sum > div:first-child { border-top: 0; }
    .spo .sum .v { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
    .spo .sum .mute { color: var(--mute); }
    .spo .sum .final { background: var(--navy); color: #fff; font-weight: 800; }
    .spo .sum .final .v { color: #fcd34d; font-size: 13px; }
    .spo .sum .bal { background: #fffbeb; font-weight: 800; color: #92400e; }
    .spo .signs { display: grid; grid-template-columns: repeat(5, 1fr); margin-top: 16px; border: 1px solid var(--navy); border-radius: 8px; overflow: hidden; }
    .spo .signs > div { border-left: 1px solid var(--line); display: flex; flex-direction: column; }
    .spo .signs > div:first-child { border-left: 0; }
    .spo .signs .h { background: var(--navy); color: #fff; font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; text-align: center; padding: 6px 4px; }
    .spo .signs .s { height: 62px; display: flex; align-items: flex-end; justify-content: center; padding: 4px; }
    .spo .signs .s img { max-height: 56px; max-width: 100%; object-fit: contain; }
    .spo .signs .s .wait { font-size: 10px; color: #9ca3af; font-style: italic; margin-bottom: 6px; }
    .spo .signs .s .here { font-size: 10px; font-weight: 700; color: var(--amber); border: 1.5px dashed var(--amber2); background: #fffbeb; border-radius: 6px; padding: 3px 8px; margin-bottom: 6px; }
    .spo .signs .nm { text-align: center; font-weight: 800; font-size: 10.5px; text-transform: uppercase; padding: 0 4px; min-height: 15px; border-top: 1px solid var(--ink); margin: 0 10px; padding-top: 3px; }
    .spo .signs .rl { text-align: center; font-size: 9.5px; color: var(--mute); }
    .spo .signs .dt { text-align: center; font-size: 9px; color: var(--mute); padding-bottom: 6px; min-height: 18px; }
    .spo .foot { text-align: center; font-size: 9.5px; color: #9ca3af; margin-top: 10px; }
    /* edit mode */
    .spo .in { width: 100%; border: 1px solid transparent; background: #fffbeb80; border-radius: 5px; padding: 3px 6px; font: inherit; color: inherit; outline: none; min-width: 0; }
    .spo .in:hover { border-color: #fde68a; }
    .spo .in:focus { border-color: var(--amber); background: #fff; box-shadow: 0 0 0 2px #fef3c7; }
    .spo .in.r { text-align: right; }
    .spo .in.sm { width: 100px; }
    .spo textarea.in { resize: vertical; min-height: 74px; border-color: var(--line); background: #fff; }
    .spo .rm { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; padding: 0; margin: 0 auto;
        border-radius: 6px; border: 1px solid transparent; background: transparent; color: #9ca3af; cursor: pointer; line-height: 1; font-size: 12px; }
    .spo .rm i { display: block; line-height: 1; width: auto; }
    .spo .rm:hover { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
    .spo .rm:focus-visible { outline: none; border-color: #fca5a5; box-shadow: 0 0 0 2px #fee2e2; }
    .spo .add { margin-top: 6px; display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--amber); background: #fffbeb; border: 1px dashed #fcd34d; border-radius: 7px; padding: 5px 10px; cursor: pointer; }
    .spo .add:hover { background: #fef3c7; }
    @media (max-width: 720px) {
        .spo .parties, .spo .bottom { grid-template-columns: 1fr; }
        .spo .signs { grid-template-columns: repeat(2, 1fr); }
        .spo .signs > div { border-top: 1px solid var(--line); }
        .spo .top { flex-direction: column; }
        .spo .ttl { text-align: left; }
    }
    #spoPrintArea { display: none; }
    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        body > *:not(#spoPrintArea) { display: none !important; }
        #spoPrintArea { display: block !important; }
        #spoPrintArea, #spoPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #spoPrintArea .spo { font-size: 11px; }
        #spoPrintArea .spo .parties, #spoPrintArea .spo .bottom { grid-template-columns: 1fr 1fr; }
        #spoPrintArea .spo .bottom { grid-template-columns: 1fr 300px; }
        #spoPrintArea .spo .signs { grid-template-columns: repeat(5, 1fr); }
        #spoPrintArea .spo .top { flex-direction: row; }
        #spoPrintArea .spo .ttl { text-align: right; }
    }
</style>
<script>
    (function () {
        'use strict';
        const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
        const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
        const num = v => Number(String(v ?? '').replace(/,/g, '')) || 0;
        const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const qty = q => { const n = parseFloat(q); return isNaN(n) ? '' : String(+n.toFixed(2)); };
        const mdy = s => { if (!s) return ''; const [y, m, d] = String(s).slice(0, 10).split('-'); return y ? `${m}/${d}/${y}` : ''; };
        const STATUS_TONE = {
            draft: ['Draft', '#6b7280', '#f3f4f6'], pending_ack: ['For Accounting Head', '#b45309', '#fffbeb'],
            pending_approval: ['For Superadmin', '#1d4ed8', '#eff6ff'], pending_receive: ['For Accounting Staff', '#0369a1', '#f0f9ff'],
            pending_release: ['For release', '#6d28d9', '#f5f3ff'], released: ['Released', '#047857', '#ecfdf5'], returned: ['Returned to Sales', '#b91c1c', '#fef2f2'],
        };

        function calc(items, discPct, addl, dp) {
            const total = Math.round(items.reduce((a, i) => a + num(i.quantity) * num(i.unit_price), 0) * 100) / 100;
            const disc = Math.min(100, Math.max(0, num(discPct)));
            const after = Math.round((total - total * disc / 100) * 100) / 100;
            const add = Math.min(after, Math.max(0, num(addl)));
            const final = Math.round((after - add) * 100) / 100;
            const down = Math.min(final, Math.max(0, num(dp)));
            return { total, vat: Math.round(total * 12 / 112 * 100) / 100, after, final, balance: Math.round((final - down) * 100) / 100 };
        }

        // value o input (edit mode)
        function fld(po, k, edit, type = 'text', cls = '') {
            if (!edit) return type === 'date' ? esc(po[k] ? new Date(po[k] + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '') : esc(po[k] ?? '');
            return `<input class="in ${cls}" data-k="${k}" type="${type}" value="${esc(po[k] ?? '')}" ${type === 'number' ? 'min="0" step="0.01"' : ''}>`;
        }

        function itemRow(it, i, edit) {
            if (!edit) {
                return `<tr><td class="n">${i + 1}</td><td>${esc(it.description)}${it.spec ? `<span class="spec">${esc(it.spec)}</span>` : ''}</td>
                    <td class="c">${qty(it.quantity)}</td><td class="c">${esc(it.unit || '')}</td><td class="r">${money(it.unit_price)}</td><td class="r"><b>₱${money(num(it.quantity) * num(it.unit_price))}</b></td></tr>`;
            }
            return `<tr data-item>
                <td class="n">${i + 1}</td>
                <td><input class="in" data-i="description" placeholder="Product / description" value="${esc(it.description || '')}"><input class="in" data-i="spec" placeholder="Size, color, brand (optional)" value="${esc(it.spec || '')}" style="font-size:10.5px;color:#6b7280;margin-top:2px"></td>
                <td style="width:80px"><input class="in r" data-i="quantity" type="number" min="0" step="0.01" value="${esc(it.quantity ?? '')}"></td>
                <td style="width:76px"><input class="in" data-i="unit" placeholder="pcs" value="${esc(it.unit || '')}"></td>
                <td style="width:110px"><input class="in r" data-i="unit_price" type="number" min="0" step="0.01" value="${esc(it.unit_price ?? '')}"></td>
                <td class="r" style="width:110px"><b data-amt>₱${money(num(it.quantity) * num(it.unit_price))}</b></td>
                <td style="width:34px;text-align:center"><button type="button" class="rm" data-rm title="Remove item" aria-label="Remove item"><i class="fa-solid fa-xmark"></i></button></td>
            </tr>`;
        }

        function signCell(head, name, role, url, at, opts) {
            let s;
            if (url) s = `<img src="${esc(url)}" alt="Signature">`;
            else if (opts.preview) s = `<img src="${esc(opts.preview.url)}" alt="" style="opacity:.5">`;
            else if (opts.here) s = '<span class="here">Sign here</span>';
            else s = '<span class="wait">Pending</span>';
            return `<div><div class="h">${head}</div><div class="s">${s}</div>
                <div class="nm">${esc(name || (opts.preview ? opts.preview.name : '') || '')}</div><div class="rl">${role}</div><div class="dt">${at ? mdy(at) : ''}</div></div>`;
        }

        function render(el, po, opts = {}) {
            const edit = !!opts.edit;
            const items = po.items && po.items.length ? po.items : (edit ? [{}] : []);
            const c = calc(items, po.discount_pct, po.addl_discount, po.down_payment);
            const tone = STATUS_TONE[po.status] || STATUS_TONE.draft;
            const pv = opts.preview || null;   // { col, url, name } — kung saan lalabas ang pirma mo
            const P = k => (pv && pv.col === k) ? { preview: pv } : (opts.here === k ? { here: true } : {});
            const emptyRows = edit ? '' : Array.from({ length: Math.max(0, 5 - items.length) }).map((_, i) => `<tr class="empty"><td class="n">${items.length + i + 1}</td><td></td><td></td><td></td><td></td><td></td></tr>`).join('');

            el.innerHTML = `<div class="spo">
                <div class="hz"></div>
                <div class="top">
                    <div class="co"><img src="${LOGO}" alt="NobleHome">
                        <div><h1>NOBLEHOME CONSTRUCTION CORPORATION</h1>
                            <p>1181 MC Premiere Bldg, EDSA Balintawak, Quezon City</p>
                            <p>noblehomeconst.ph@gmail.com · Tel. 02-8822-1295 · Cell 0968-591-6544</p></div></div>
                    <div class="ttl"><h2>PURCHASE ORDER</h2>
                        <div class="meta"><div class="k">P.O. Number</div><div class="k">P.O. Date</div>
                            <div class="v">${esc(po.po_no || 'NEW')}</div><div class="v">${edit ? `<input class="in" data-k="po_date" type="date" value="${esc(po.po_date || '')}" style="width:130px;text-align:center">` : esc(mdy(po.po_date))}</div></div>
                        <div><span class="pill" style="color:${tone[1]};background:${tone[2]};border-color:${tone[1]}33">${tone[0]}</span></div>
                    </div>
                </div>

                <div class="parties">
                    <div class="card"><h3><i class="fa-solid fa-user"></i>Customer</h3><div class="kv">
                        <div class="k">Name</div><div>${fld(po, 'cust_name', edit)}</div>
                        <div class="k">Company</div><div>${fld(po, 'cust_company', edit)}</div>
                        <div class="k">Address</div><div>${fld(po, 'cust_address', edit)}</div>
                        <div class="k">Phone no.</div><div>${fld(po, 'cust_phone', edit)}</div>
                        <div class="k">Email</div><div>${fld(po, 'cust_email', edit, 'email')}</div>
                        <div class="k">Start date</div><div>${fld(po, 'start_date', edit, 'date')}</div>
                    </div></div>
                    <div class="card"><h3><i class="fa-solid fa-store"></i>Vendor / Sales person</h3><div class="kv">
                        <div class="k">Name</div><div>${fld(po, 'vendor_name', edit)}</div>
                        <div class="k">Company</div><div>${fld(po, 'vendor_company', edit)}</div>
                        <div class="k">Address</div><div>${fld(po, 'vendor_address', edit)}</div>
                        <div class="k">Phone no.</div><div>${fld(po, 'vendor_phone', edit)}</div>
                        <div class="k">Email</div><div>${fld(po, 'vendor_email', edit, 'email')}</div>
                        <div class="k">Prepared by</div><div>${esc(po.prepared_by || '')}</div>
                    </div></div>
                </div>

                <table class="items">
                    <thead><tr><th class="n">No.</th><th>Product description</th><th class="c">Qty</th><th class="c">Unit</th><th class="r">Unit price</th><th class="r">Amount</th>${edit ? '<th></th>' : ''}</tr></thead>
                    <tbody data-items>${items.map((it, i) => itemRow(it, i, edit)).join('')}${emptyRows}</tbody>
                </table>
                ${edit ? '<button type="button" class="add" data-add><i class="fa-solid fa-plus"></i> Add item</button>' : '<div class="nf">***** NOTHING FOLLOWS *****</div>'}

                <div class="bottom">
                    <div class="note"><h4>Note</h4>${edit ? `<textarea class="in" data-k="note" rows="3" placeholder="Delivery instructions, payment terms…">${esc(po.note || '')}</textarea>` : `<div class="box">${esc(po.note || '')}</div>`}</div>
                    <div class="sum">
                        <div><span>Total amount</span><span class="v" data-s="total">₱${money(c.total)}</span></div>
                        <div class="mute"><span>12% VAT inclusive</span><span class="v" data-s="vat">₱${money(c.vat)}</span></div>
                        <div><span>Discount (%)</span><span class="v">${edit ? `<input class="in r sm" data-k="discount_pct" type="number" min="0" max="100" step="0.01" value="${esc(po.discount_pct ?? 0)}">` : (+num(po.discount_pct).toFixed(2)) + '%'}</span></div>
                        <div class="mute"><span>Amount after discount</span><span class="v" data-s="after">₱${money(c.after)}</span></div>
                        <div><span>Add'l discount</span><span class="v">${edit ? `<input class="in r sm" data-k="addl_discount" type="number" min="0" step="0.01" value="${esc(po.addl_discount ?? 0)}">` : '₱' + money(po.addl_discount)}</span></div>
                        <div class="final"><span>Final amount</span><span class="v" data-s="final">₱${money(c.final)}</span></div>
                        <div><span>Down payment</span><span class="v">${edit ? `<input class="in r sm" data-k="down_payment" type="number" min="0" step="0.01" value="${esc(po.down_payment ?? 0)}">` : '₱' + money(po.down_payment)}</span></div>
                        <div class="mute"><span>Down payment date</span><span class="v">${edit ? `<input class="in" data-k="dp_date" type="date" value="${esc(po.dp_date || '')}" style="width:140px">` : esc(mdy(po.dp_date)) || '—'}</span></div>
                        <div class="bal"><span>Current balance</span><span class="v" data-s="balance">₱${money(c.balance)}</span></div>
                    </div>
                </div>

                <div class="signs">
                    ${signCell('Prepared by', po.prepared_by, 'Sales', po.prepared_sig_url, po.submitted_at, edit && opts.mySig ? { preview: { url: opts.mySig, name: po.prepared_by } } : {})}
                    ${signCell('Acknowledged by', po.ack_by, 'Accounting Head', po.ack_sig_url, po.ack_at, P('ack'))}
                    ${signCell('Approved by', po.approved_by, 'Superadmin', po.approved_sig_url, po.approved_at, P('approved'))}
                    ${signCell('Received by', po.received_by, 'Accounting Staff', po.received_sig_url, po.received_at, P('received'))}
                    ${signCell('Released by', po.released_by, esc(po.released_title || 'Custodian / Custodian Asst.'), po.released_sig_url, po.released_at, P('released'))}
                </div>
                <p class="foot">This Purchase Order is valid only when all signatures are complete.</p>
            </div>`;

            if (edit) wire(el);
        }

        // ── edit mode: kuwenta habang nagta-type, add/remove ng item ──
        function wire(el) {
            const body = el.querySelector('[data-items]');
            const renumber = () => body.querySelectorAll('[data-item]').forEach((tr, i) => { tr.querySelector('.n').textContent = i + 1; });
            const recalc = () => {
                const f = collect(el);
                const c = calc(f.items, f.discount_pct, f.addl_discount, f.down_payment);
                body.querySelectorAll('[data-item]').forEach(tr => {
                    const v = k => num(tr.querySelector(`[data-i="${k}"]`).value);
                    tr.querySelector('[data-amt]').textContent = '₱' + money(v('quantity') * v('unit_price'));
                });
                ['total', 'vat', 'after', 'final', 'balance'].forEach(k => { el.querySelector(`[data-s="${k}"]`).textContent = '₱' + money(c[k]); });
            };
            el.querySelector('[data-add]').addEventListener('click', () => {
                body.insertAdjacentHTML('beforeend', itemRow({}, body.children.length, true));
                renumber();
                body.lastElementChild.querySelector('[data-i="description"]').focus();
            });
            body.addEventListener('click', e => {
                const b = e.target.closest('[data-rm]');
                if (!b) return;
                if (body.querySelectorAll('[data-item]').length === 1) { b.closest('tr').querySelectorAll('input').forEach(i => i.value = ''); }
                else b.closest('tr').remove();
                renumber(); recalc();
            });
            el.addEventListener('input', recalc);
        }

        function collect(el) {
            const out = {};
            el.querySelectorAll('[data-k]').forEach(i => { out[i.dataset.k] = i.value; });
            out.items = [...el.querySelectorAll('[data-item]')].map(tr => {
                const o = {};
                tr.querySelectorAll('[data-i]').forEach(i => { o[i.dataset.i] = i.value; });
                return o;
            }).filter(o => (o.description || '').trim() !== '');
            return out;
        }

        function print(po) {
            let area = document.getElementById('spoPrintArea');
            if (!area) { area = document.createElement('div'); area.id = 'spoPrintArea'; document.body.appendChild(area); }
            render(area, po, {});
            const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
            Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
        }

        window.SalesPO = { render, collect, print, calc, money, STATUS_TONE };
    })();
</script>