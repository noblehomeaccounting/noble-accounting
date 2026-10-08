<?php
// monitoringcontain2.php — included by monitoringcrm.php
// Status History: lahat ng status na dinaanan ng napiling project.
$box2Url = BASE_URL . '/monitoringcrmajax';
?>
<section id="monBox2" class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col h-[300px]">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-800">Status History</h2>
        <span id="monBox2Label" class="text-[11px] font-mono text-gray-400"></span>
    </div>

    <div id="monBox2Body" class="mon-scroll flex-1 overflow-y-auto px-5 py-4">
        <div class="h-full flex flex-col items-center justify-center gap-2 text-center">
            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-xs text-gray-400">Click a row in the table to see its status history.</p>
        </div>
    </div>
</section>

<script>
    (function () {
        const URL_ = <?= json_encode($box2Url) ?>;
        const body = document.getElementById('monBox2Body');
        const label = document.getElementById('monBox2Label');
        let reqToken = 0; // ignore stale responses when clicking rows quickly

        const DOT = {
            green: 'bg-emerald-500',
            amber: 'bg-amber-400',
            blue:  'bg-sky-500',
            red:   'bg-red-500',
        };
        const BADGE = {
            green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            amber: 'bg-amber-50 text-amber-700 border-amber-200',
            blue:  'bg-sky-50 text-sky-700 border-sky-200',
            red:   'bg-red-50 text-red-700 border-red-200',
        };

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str ?? '';
            return d.innerHTML;
        }

        function fmtDateTime(v) {
            if (!v) return '';
            const dt = new Date(String(v).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return esc(v);
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function item(e) {
            const time = e.time ? fmtDateTime(e.time) : (e.current ? 'Now' : '');
            return `
            <li class="relative ml-4 pb-5 last:pb-0">
                <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full ring-4 ring-white ${DOT[e.color] || 'bg-gray-400'}"></span>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold tracking-wide border ${BADGE[e.color] || 'bg-gray-50 text-gray-600 border-gray-200'}">${esc(e.label)}</span>
                    ${e.current ? '<span class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Current</span>' : ''}
                </div>
                ${time ? `<p class="text-[11px] text-gray-400 mt-1">${time}</p>` : ''}
                ${e.note ? `<p class="text-xs text-gray-600 mt-0.5">${esc(e.note)}</p>` : ''}
            </li>`;
        }

        // Called by the table rows in monitoringcrm.php
        window.monBox2Show = async function (id) {
            const token = ++reqToken;
            body.innerHTML = `
                <div class="space-y-4">
                    <div class="h-5 w-32 rounded-full bg-gray-100 animate-pulse"></div>
                    <div class="h-5 w-40 rounded-full bg-gray-100 animate-pulse"></div>
                    <div class="h-5 w-28 rounded-full bg-gray-100 animate-pulse"></div>
                </div>`;

            try {
                const res = await fetch(`${URL_}?action=status_history&id=${encodeURIComponent(id)}`);
                const data = await res.json();
                if (token !== reqToken) return;

                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-8">${esc(data.message || 'Failed to load.')}</p>`;
                    return;
                }

                label.textContent = data.control_no;
                body.innerHTML = `<ol class="relative border-l border-gray-200 ml-1.5">${data.events.map(item).join('')}</ol>`;
                body.scrollTop = body.scrollHeight; // dalhin sa kasalukuyang status (nasa ibaba)
            } catch (e) {
                if (token !== reqToken) return;
                console.error('monBox2Show:', e);
                body.innerHTML = '<p class="text-xs text-red-500 text-center py-8">Connection error.</p>';
            }
        };
    })();
</script>