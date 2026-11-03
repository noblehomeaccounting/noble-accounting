<?php
// monitoringcontain1.php — included by monitoringcrm.php
$box1Url = BASE_URL . '/monitoringcrmajax';
?>
<section id="monBox1" class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col h-[360px]">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-800">Project Status</h2>
        <span id="monBox1Label" class="text-[11px] font-mono text-gray-400"></span>
    </div>
    <div id="monBox1Body" class="mon-scroll flex-1 overflow-y-auto px-5 py-3">
        <div class="h-full flex flex-col items-center justify-center gap-2 text-center">
            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
            </svg>
            <p class="text-xs text-gray-400">Click a row in the table to see its project status.</p>
        </div>
    </div>
</section>

<script>
    (function () {
        const URL_ = <?= json_encode($box1Url) ?>;
        const body = document.getElementById('monBox1Body');
        const label = document.getElementById('monBox1Label');
        let reqToken = 0; // ignore stale responses when clicking rows quickly

        const DOT = {
            green: 'bg-emerald-500',
            amber: 'bg-amber-400',
            blue:  'bg-sky-500',
            red:   'bg-red-500',
        };

        const STAGE_BADGE = {
            green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            amber: 'bg-amber-50 text-amber-700 border-amber-200',
            blue:  'bg-sky-50 text-sky-700 border-sky-200',
            red:   'bg-red-50 text-red-700 border-red-200',
        };

        const NOTE_STYLE = {
            green: 'bg-emerald-50/60 border-emerald-400 text-emerald-800',
            amber: 'bg-amber-50/70 border-amber-400 text-amber-800',
            blue:  'bg-sky-50/70 border-sky-400 text-sky-800',
            red:   'bg-red-50/70 border-red-400 text-red-800',
        };

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str ?? '';
            return d.innerHTML;
        }

        function fmtDateTime(v) {
            if (!v) return '—';
            const dt = new Date(String(v).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return esc(v);
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function row(key, valueHtml) {
            return `
                <dt class="text-gray-400">${key}</dt>
                <dd class="text-gray-800 font-medium min-w-0 break-words">${valueHtml}</dd>`;
        }

        function ago(s) {
            if (s == null) return '';
            if (s < 3600) return Math.max(1, Math.floor(s / 60)) + 'm ago';
            if (s < 86400) return Math.floor(s / 3600) + 'h ago';
            const d = Math.floor(s / 86400);
            return d === 1 ? 'yesterday' : d + ' days ago';
        }

        // New Customer → … → Project Execution (Materials: 4 steps lang)
        function stepper(it) {
            if (!it.flow || !it.flow.length) return '';
            if (it.stage_key === 'closed') {
                return '<div class="mb-3 text-[11px] font-semibold text-red-600">Closed · client is not proceeding</div>';
            }
            const step = it.stage_step || 0;
            const items = it.flow.map((f, i) => {
                const n = i + 1;
                const done = n < step || (it.done && n === step);
                const cur = n === step && !it.done;
                const dot = done
                    ? '<span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px]"><i class="fa-solid fa-check"></i></span>'
                    : cur
                        ? `<span class="w-5 h-5 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] font-bold ring-4 ring-amber-100">${n}</span>`
                        : `<span class="w-5 h-5 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-[10px] font-bold">${n}</span>`;
                const line = n < it.flow.length
                    ? `<span class="flex-1 h-0.5 mt-2.5 mx-1 ${n < step ? 'bg-emerald-400' : 'bg-gray-200'}"></span>` : '';
                return `
                    <div class="flex items-start ${n < it.flow.length ? 'flex-1' : ''} min-w-0">
                        <div class="flex flex-col items-center w-11 sm:w-14 shrink-0">
                            ${dot}
                            <span class="mt-1 text-[9px] sm:text-[9.5px] leading-tight text-center break-words ${cur ? 'font-bold text-amber-700' : (done ? 'text-gray-600' : 'text-gray-400')}">${esc(f.name)}</span>
                        </div>
                        ${line}
                    </div>`;
            }).join('');
            return `<div class="flex items-start mb-3 -mx-1">${items}</div>`;
        }

        function card(it) {
            const designer = it.designer
                ? esc(it.designer)
                : '<span class="text-amber-600">Not yet assigned</span>';

            const daysTxt = it.done ? 'Completed'
                : it.stage_days == null ? '—'
                : it.stage_days === 0 ? 'Today'
                : `${it.stage_days} ${it.stage_days === 1 ? 'day' : 'days'}`;
            const waiting = it.waiting_on
                ? `<span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-hourglass-half text-amber-500 text-[10px]"></i>${esc(it.waiting_on)}</span>`
                : '<span class="text-gray-400">—</span>';

            const rows = [
                row('Client', esc(it.client_name)),
                it.contact ? row('Contact', esc(it.contact)) : '',
                row('Type', it.material ? 'Materials (no site visit)' : 'Modular (with site visit)'),
                it.site_visit ? row('Site Visit', fmtDateTime(it.site_visit)) : '',
                row('Sales', it.sales ? esc(it.sales) : '<span class="text-gray-400">—</span>'),
                row('Designer', designer),
                row('Waiting on', waiting),
                row('In this status', it.stuck ? `<span class="text-red-600 font-semibold">⚠ ${esc(daysTxt)} — no movement</span>` : esc(daysTxt)),
                row('Last update', it.last_time ? `${String(it.last_time).length <= 10 ? esc(it.last_time) : fmtDateTime(it.last_time)} <span class="text-gray-400 font-normal">(${ago(it.last_age)})</span>` : '—'),
                row('Expected Next', esc(it.next)),
            ].join('');

            const note = it.note
                ? `<div class="mt-3 border-l-2 rounded-r-md px-3 py-1.5 text-xs ${NOTE_STYLE[it.color] || 'bg-gray-50 border-gray-300 text-gray-600'}">${esc(it.note)}</div>`
                : '';

            return `
            <div class="text-[13px]">
                ${stepper(it)}
                <span class="inline-flex items-center gap-2 px-3 py-0.5 rounded-full text-[11px] font-semibold tracking-wide border ${STAGE_BADGE[it.color] || 'bg-gray-50 text-gray-600 border-gray-200'}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${DOT[it.color] || 'bg-gray-400'}"></span>
                    ${esc(it.stage)}
                </span>
                ${it.stage_name ? `<span class="ml-1 text-[11px] text-gray-500">${esc(it.stage_name)}${it.stage_sub ? ' · ' + esc(it.stage_sub) : ''}</span>` : ''}
                <dl class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-1.5 mt-3">
                    ${rows}
                </dl>
                ${note}
            </div>`;
        }

        // Called by the table rows in monitoringcrm.php
        window.monBox1Show = async function (id) {
            const token = ++reqToken;
            body.innerHTML = `
                <div class="space-y-3">
                    <div class="h-6 w-28 rounded-full bg-gray-100 animate-pulse"></div>
                    <div class="h-4 rounded bg-gray-100 animate-pulse"></div>
                    <div class="h-4 rounded bg-gray-100 animate-pulse"></div>
                    <div class="h-4 w-2/3 rounded bg-gray-100 animate-pulse"></div>
                </div>`;

            try {
                const res = await fetch(`${URL_}?action=status_detail&id=${encodeURIComponent(id)}`);
                const data = await res.json();
                if (token !== reqToken) return;

                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-8">${esc(data.message || 'Failed to load.')}</p>`;
                    return;
                }
                label.textContent = data.item.control_no;
                body.innerHTML = card(data.item);
            } catch (e) {
                if (token !== reqToken) return;
                console.error('monBox1Show:', e);
                body.innerHTML = '<p class="text-xs text-red-500 text-center py-8">Connection error.</p>';
            }
        };
    })();
</script>