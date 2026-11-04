<?php
// memo-doc.php — iisang itsura ng Memorandum (HR page, Superadmin page at user side)
//   MemoDoc.render(el, memo, { toName, preview: { col, url, name } })
//   MemoDoc.print(memo, opts)
?>
<style>
    .mmo { --ink: #111827; --mute: #6b7280; --line: #e5e7eb; --navy: #1f2937; --amber: #b45309; --amber2: #f59e0b;
        color: var(--ink); font-size: 12.5px; line-height: 1.55; background: #fff; }
    .mmo * { box-sizing: border-box; }
    .mmo .lh { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; gap: 10px; padding-bottom: 12px; border-bottom: 2px solid var(--navy); }
    .mmo .lh img { height: 70px; width: auto; }
    .mmo .lh .co { text-align: center; font-family: 'Times New Roman', Georgia, serif; }
    .mmo .lh .co h1 { margin: 0; font-size: 17px; font-weight: 700; letter-spacing: .02em; }
    .mmo .lh .co p { margin: 0; font-size: 11px; font-style: italic; }
    .mmo .lh .co p.ct { font-style: normal; font-size: 10.5px; }
    .mmo .lh .co p.ct a { color: #1d4ed8; }
    .mmo .hz { height: 4px; margin-top: 2px; background: repeating-linear-gradient(-45deg, var(--amber2) 0 8px, var(--navy) 8px 16px); }
    .mmo .ttl { text-align: center; margin: 18px 0 4px; }
    .mmo .ttl h2 { margin: 0; font-size: 22px; font-weight: 900; letter-spacing: .06em; font-family: Arial, Helvetica, sans-serif; }
    .mmo .ttl .sub { display: inline-block; margin-top: 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--amber); }
    .mmo .meta { margin: 16px 0 14px; display: grid; grid-template-columns: 110px 1fr; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .mmo .meta > div { padding: 6px 4px; border-top: 1px solid #f3f4f6; }
    .mmo .meta > div:nth-child(-n+2) { border-top: 0; }
    .mmo .meta .k { font-weight: 700; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: var(--mute); }
    .mmo .meta .v { font-weight: 600; }
    .mmo .body { white-space: pre-line; min-height: 160px; padding: 4px 2px; text-align: justify; }
    .mmo .due { margin-top: 14px; border: 1.5px dashed var(--amber2); background: #fffbeb; border-radius: 8px; padding: 8px 12px; font-size: 11.5px; color: #78350f; }
    .mmo .behalf { margin-top: 28px; font-size: 12px; }
    .mmo .signs { display: grid; gap: 18px; margin-top: 18px; }
    .mmo .signs .lbl { font-size: 11.5px; margin-bottom: 4px; }
    .mmo .signs .s { height: 54px; display: flex; align-items: flex-end; }
    .mmo .signs .s img { max-height: 52px; max-width: 170px; object-fit: contain; }
    .mmo .signs .s .wait { font-size: 10px; color: #9ca3af; font-style: italic; margin-bottom: 4px; }
    .mmo .signs .s .here { font-size: 10px; font-weight: 700; color: var(--amber); border: 1.5px dashed var(--amber2); background: #fffbeb; border-radius: 6px; padding: 2px 8px; margin-bottom: 4px; }
    .mmo .signs .nm { font-weight: 700; text-decoration: underline; text-underline-offset: 3px; min-height: 18px; }
    .mmo .signs .rl { font-size: 11px; color: var(--mute); }
    .mmo .signs .dt { font-size: 9.5px; color: #9ca3af; }
    .mmo .foot { text-align: center; font-size: 9.5px; color: #9ca3af; margin-top: 22px; }
    #mmoPrintArea { display: none; }
    @media print {
        @page { size: A4 portrait; margin: 14mm; }
        body > *:not(#mmoPrintArea) { display: none !important; }
        #mmoPrintArea { display: block !important; }
        #mmoPrintArea, #mmoPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
<script>
    (function () {
        'use strict';
        const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
        const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
        const longDate = v => { if (!v) return ''; const d = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); };
        const TYPES = {
            nte: { label: 'Notice to Explain', short: 'NTE', color: '#b45309', bg: '#fffbeb', icon: 'fa-circle-question' },
            nod: { label: 'Notice of Decision', short: 'NOD', color: '#b91c1c', bg: '#fef2f2', icon: 'fa-gavel' },
            coe: { label: 'Certificate of Employment', short: 'COE', color: '#047857', bg: '#ecfdf5', icon: 'fa-certificate' },
        };
        const STATUS = {
            draft: ['Draft', '#6b7280', '#f3f4f6'], pending_verify: ['For HR Head', '#b45309', '#fffbeb'],
            pending_approval: ['For Superadmin', '#1d4ed8', '#eff6ff'], sent: ['Sent', '#047857', '#ecfdf5'],
            returned: ['Returned', '#b91c1c', '#fef2f2'],
        };

        function signCell(label, name, role, url, at, opts) {
            let s;
            if (url) s = `<img src="${esc(url)}" alt="Signature">`;
            else if (opts.preview) s = `<img src="${esc(opts.preview.url)}" alt="" style="opacity:.5">`;
            else if (opts.here) s = '<span class="here">Sign here</span>';
            else if (at) s = '<span class="wait">(signed)</span>';
            else s = '<span class="wait">Pending</span>';
            return `<div><div class="lbl">${label}</div><div class="s">${s}</div>
                <div class="nm">${esc(name || (opts.preview ? opts.preview.name : '') || ' ')}</div>
                <div class="rl">${role}</div><div class="dt">${at ? esc(longDate(at)) : ''}</div></div>`;
        }

        function render(el, m, opts = {}) {
            const t = TYPES[m.type] || TYPES.nte;
            const pv = opts.preview || null;
            const P = k => (pv && pv.col === k) ? { preview: pv } : (opts.here === k ? { here: true } : {});
            const to = opts.toName || m.to_label || (m.audience === 'all' ? 'All Employees' : '');
            const body = esc(m.body || '').replace(/\{name\}/gi, esc(opts.toName || (m.audience === 'all' ? 'Employee' : '[Employee name]')));
            const isCoe = m.type === 'coe';
            const cols = m.need_superadmin == 1 ? 3 : 2;

            el.innerHTML = `<div class="mmo">
                <div class="lh">
                    <img src="${LOGO}" alt="NobleHome">
                    <div class="co"><h1>NOBLEHOME CONSTRUCTION CORPORATION</h1>
                        <p>1181 MC Premiere Bldg., EDSA Balintawak Quezon City</p>
                        <p class="ct"><a>noblehomeconst.ph@gmail.com</a> / Tel. No. 02-88221295 / Cell. No. 0968-591-6544</p></div>
                    <div></div>
                </div>
                <div class="hz"></div>

                <div class="ttl"><h2>${isCoe ? 'CERTIFICATE OF EMPLOYMENT' : 'MEMORANDUM'}</h2>
                    ${isCoe ? '' : `<div class="sub">${t.label}</div>`}</div>

                <div class="meta">
                    <div class="k">Memo No.</div><div class="v">${esc(m.memo_no || '— (on submit)')}</div>
                    ${isCoe ? '' : `<div class="k">To</div><div class="v">${esc(to || '—')}</div>
                    <div class="k">From</div><div class="v">Human Resource Department</div>`}
                    <div class="k">Date</div><div class="v">${esc(longDate(m.memo_date || m.sent_at) || '—')}</div>
                    ${isCoe ? '' : `<div class="k">Subject</div><div class="v">${esc(m.subject || '')}</div>`}
                    ${m.type === 'nod' && m.ref_no ? `<div class="k">Reference</div><div class="v">${esc(m.ref_no)}</div>` : ''}
                </div>

                <div class="body">${body}</div>

                ${m.type === 'nte' && m.response_due ? `<div class="due"><b>Response required.</b> Please submit your written explanation on or before <b>${esc(longDate(m.response_due))}</b>. Failure to respond within the given period will be taken as a waiver of your right to be heard.</div>` : ''}

                <p class="behalf">For and on behalf of Management.</p>
                <div class="signs" style="grid-template-columns:repeat(${cols},1fr)">
                    ${signCell('Prepared By:', m.prepared_name, 'HR Assistant', m.prepared_sig_url, m.prepared_at, P('prepared'))}
                    ${signCell('Verified by:', m.verified_name, 'HR Head', m.verified_sig_url, m.verified_at, P('verified'))}
                    ${m.need_superadmin == 1 ? signCell('Approved By:', m.approved_name, 'CEO/General Manager', m.approved_sig_url, m.approved_at, P('approved')) : ''}
                </div>
                <p class="foot">This memorandum is system-generated by NobleHome HR.</p>
            </div>`;
        }

        function print(m, opts = {}) {
            let area = document.getElementById('mmoPrintArea');
            if (!area) { area = document.createElement('div'); area.id = 'mmoPrintArea'; document.body.appendChild(area); }
            render(area, m, opts);
            const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
            Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
        }

        window.MemoDoc = { render, print, TYPES, STATUS, longDate };
    })();
</script>