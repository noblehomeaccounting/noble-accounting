<?php
// index-main-dashboard.php
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
    <title>My Vouchers — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="min-h-screen px-4 py-12 relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center;">
    <div class="absolute inset-0 bg-black/50 z-0"></div>

    <div class="max-w-5xl mx-auto relative z-10 pt-16">

        <style>
            .pr-card{border-radius:18px;overflow:hidden;background:#fff;font-family:'Inter',sans-serif;
                box-shadow:0 28px 60px -28px rgba(0,0,0,.65),0 2px 8px rgba(0,0,0,.08);
                animation:prIn .45s cubic-bezier(.16,1,.3,1) both}
            @keyframes prIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

            /* header */
            .pr-head{position:relative;overflow:hidden;background:#1f1f1f;color:#fff;
                display:flex;align-items:center;gap:18px;padding:22px 28px}
            .pr-head::after{content:'';position:absolute;top:0;right:0;bottom:0;width:190px;pointer-events:none;
                background:repeating-linear-gradient(-45deg,#F7941D 0 14px,transparent 14px 28px);
                -webkit-mask:linear-gradient(90deg,transparent,#000 70%);mask:linear-gradient(90deg,transparent,#000 70%);opacity:.9}
            .pr-logo{width:58px;height:58px;border-radius:14px;background:#fff;flex-shrink:0;
                display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 3px rgba(247,148,29,.35);position:relative;z-index:1}
            .pr-logo img{height:42px;width:auto}
            .pr-head-txt{min-width:0;flex:1;position:relative;z-index:1;padding-right:170px}
            .pr-eyebrow{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#F7941D}
            .pr-title{font-size:22px;font-weight:700;line-height:1.25;margin-top:3px;overflow-wrap:anywhere}
            .pr-sub{font-size:12.5px;color:#a3a3a3;margin-top:4px}
            .pr-chip{position:absolute;z-index:2;top:18px;right:24px;display:inline-flex;align-items:center;gap:6px;
                padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;background:#fff;color:var(--c);
                box-shadow:0 4px 14px rgba(0,0,0,.25)}
            .pr-chip::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--c)}

            /* body */
            .pr-body{padding:24px 28px}
            .pr-grid{display:grid;grid-template-columns:1fr 1.3fr;gap:16px}
            .pr-box{border:1px solid #ececec;border-radius:14px;padding:18px 20px;background:#fff}
            .pr-label{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8a8a8a;
                display:flex;align-items:center;gap:7px}
            .pr-label i{color:#F7941D}

            .pr-paydate{display:flex;align-items:center;gap:16px;margin-top:12px}
            .pr-cal{width:66px;flex-shrink:0;border-radius:12px;overflow:hidden;border:1px solid #ececec;text-align:center;
                box-shadow:0 4px 12px -6px rgba(0,0,0,.2)}
            .pr-cal-m{background:#F7941D;color:#fff;font-size:11px;font-weight:700;letter-spacing:.12em;padding:4px 0}
            .pr-cal-d{font-size:28px;font-weight:800;color:#1f1f1f;line-height:1;padding:8px 0 2px}
            .pr-cal-y{font-size:10.5px;color:#9a9a9a;padding-bottom:6px}
            .pr-weekday{font-size:16px;font-weight:700;color:#1f1f1f}
            .pr-full{font-size:13px;color:#6b6b6b;margin-top:2px}

            .pr-range{display:flex;align-items:center;gap:10px;margin-top:14px;font-size:15px;font-weight:700;color:#1f1f1f}
            .pr-range i{color:#c4c4c4;font-size:12px}
            .pr-track{height:8px;border-radius:999px;background:#f1f1f1;margin-top:14px;overflow:hidden}
            .pr-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#fdba74,#F7941D);transition:width .6s}
            .pr-cap{display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:#8a8a8a}
            .pr-cap b{color:#1f1f1f;font-weight:600}

            .pr-msg{margin-top:16px;border-radius:14px;background:#fafaf9;border:1px solid #f0efed;border-left:4px solid #F7941D;padding:16px 20px}
            .pr-msg p{margin-top:8px;font-size:14px;line-height:1.7;color:#3f3f46;white-space:pre-line;overflow-wrap:anywhere}

            /* footer */
            .pr-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
                padding:14px 28px;background:#fafafa;border-top:1px solid #f0f0f0;font-size:12px;color:#8a8a8a}
            .pr-foot span{display:inline-flex;align-items:center;gap:8px}
            .pr-foot b{color:#1f1f1f;font-weight:600}

            @media (max-width:700px){
                .pr-head{padding:20px;flex-wrap:wrap}
                .pr-head::after{width:90px}
                .pr-head-txt{padding-right:0;flex-basis:100%}
                .pr-chip{position:static;margin-left:auto}
                .pr-head-txt{order:2}
                .pr-body{padding:18px 20px}
                .pr-grid{grid-template-columns:1fr}
                .pr-foot{padding:12px 20px}
            }
        </style>

        <!-- Announcements Section -->
        <div id="announcements-section" class="mb-6 space-y-3"></div>

        <script>
            const NH_LOGO_URL = '<?= BASE_URL ?>/icon/logo.webp';

            function nhEsc(s) {
                const d = document.createElement('div');
                d.textContent = s ?? '';
                return d.innerHTML;
            }

            function nhFmt(d) {
                if (!d) return '—';
                return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
            }

            function nhDay(d) { return d ? new Date(d + 'T00:00:00') : null; }

            // Payroll card (template 4)
            function buildPayrollCard(row) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const pay = nhDay(row.pay_date);
                const ps  = nhDay(row.pay_period_start);
                const pe  = nhDay(row.pay_period_end);

                // status ng payout
                let chip = { t: 'Scheduled', c: '#64748b' };
                if (pay) {
                    const d = Math.round((pay - today) / 864e5);
                    if (d > 1)        chip = { t: `Payout in ${d} days`, c: '#ea580c' };
                    else if (d === 1) chip = { t: 'Payout tomorrow',      c: '#ea580c' };
                    else if (d === 0) chip = { t: 'Payout today',         c: '#16a34a' };
                    else              chip = { t: 'Released',             c: '#475569' };
                }

                // progress ng pay period
                let pct = 0, cap = '';
                const total = (ps && pe) ? Math.round((pe - ps) / 864e5) + 1 : 0;
                if (total > 0) {
                    const done = Math.min(Math.max(Math.round((today - ps) / 864e5) + 1, 0), total);
                    pct = Math.round(done / total * 100);
                    cap = today < ps ? 'Not started' : today > pe ? 'Period closed' : `Day ${done} of ${total}`;
                }

                const posted = row.created_at
                    ? new Date(row.created_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '';

                const sameYear = ps && pe && ps.getFullYear() === pe.getFullYear();
                const shortFmt = d => d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });

                return `
<div class="pr-card">
    <div class="pr-head">
        <div class="pr-logo"><img src="${NH_LOGO_URL}" alt="NobleHome"></div>
        <div class="pr-head-txt">
            <div class="pr-eyebrow">Payroll Announcement</div>
            <div class="pr-title">${nhEsc(row.title)}</div>
            <div class="pr-sub">${posted ? 'Posted ' + nhEsc(posted) + ' · ' : ''}HR Department</div>
        </div>
        <span class="pr-chip" style="--c:${chip.c}">${chip.t}</span>
    </div>

    <div class="pr-body">
        <div class="pr-grid">
            <div class="pr-box">
                <div class="pr-label"><i class="fa-regular fa-calendar"></i>Pay Date</div>
                ${pay ? `
                <div class="pr-paydate">
                    <div class="pr-cal">
                        <div class="pr-cal-m">${pay.toLocaleString('en-PH', { month: 'short' }).toUpperCase()}</div>
                        <div class="pr-cal-d">${pay.getDate()}</div>
                        <div class="pr-cal-y">${pay.getFullYear()}</div>
                    </div>
                    <div>
                        <div class="pr-weekday">${pay.toLocaleDateString('en-PH', { weekday: 'long' })}</div>
                        <div class="pr-full">${nhFmt(row.pay_date)}</div>
                    </div>
                </div>` : `<div class="pr-full" style="margin-top:12px">To be announced</div>`}
            </div>

            <div class="pr-box">
                <div class="pr-label"><i class="fa-regular fa-calendar-check"></i>Pay Period</div>
                ${total > 0 ? `
                <div class="pr-range">
                    <span>${sameYear ? shortFmt(ps) : nhFmt(row.pay_period_start)}</span>
                    <i class="fa-solid fa-arrow-right-long"></i>
                    <span>${nhFmt(row.pay_period_end)}</span>
                </div>
                <div class="pr-track"><div class="pr-fill" style="width:${pct}%"></div></div>
                <div class="pr-cap"><span><b>${total}</b> days</span><span>${cap}</span></div>`
                : `<div class="pr-full" style="margin-top:12px">${nhFmt(row.pay_period_start)} – ${nhFmt(row.pay_period_end)}</div>`}
            </div>
        </div>

        ${row.body ? `
        <div class="pr-msg">
            <div class="pr-label"><i class="fa-solid fa-bullhorn"></i>Message from HR</div>
            <p>${nhEsc(row.body)}</p>
        </div>` : ''}
    </div>

    <div class="pr-foot">
        <span><i class="fa-solid fa-user-tie"></i>Issued by <b>HR Department</b></span>
        <span>NobleHome Construction Corporation</span>
    </div>
</div>`;
            }

            let nhLastAnn = null;
            function fetchUserAnnouncements() {
                fetch('<?= BASE_URL ?>/fetchannouncements')
                    .then(res => res.json())
                    .then(data => {
                        const key = JSON.stringify(data);
                        if (key === nhLastAnn) return;      // walang bago, huwag i-redraw
                        nhLastAnn = key;
                        const section = document.getElementById('announcements-section');
                        if (!Array.isArray(data) || !data.length) { section.innerHTML = ''; return; }
                        section.innerHTML = data.map(row => buildUserAnnHTML(row)).join('');
                    })
                    .catch(() => {});
            }

            function buildUserAnnHTML(row) {
                const rawDate = row.created_at.replace(' ', 'T');
                const dateObj = new Date(rawDate);

                const date = dateObj.toLocaleDateString('en-PH', {
                    year: 'numeric', month: 'long', day: 'numeric'
                });
                const day = dateObj.getDate();
                const month = dateObj.toLocaleString('en-PH', { month: 'short' }).toUpperCase();


                const templates = {
                    1: `<div class=" overflow-hidden shadow-sm " style="font-family: 'Inter', sans-serif;">
    <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 bg-orange-500 rounded-full">
        <div class="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-calendar text-orange-400 text-xs"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] text-white uppercase tracking-widest" style="font-family:'Inter',sans-serif;letter-spacing:0.1em;">General Announcement</p>
            <h2 class="text-sm font-bold text-white truncate" style="font-weight:600;">${nhEsc(row.title)}</h2>
        </div>
        <span class="text-[10px] text-white shrink-0">${date}</span>
    </div>
    <div class="px-5 py-4">
        <p class="text-sm text-white leading-relaxed" style="font-weight:400;line-height:1.7;">${nhEsc(row.body)}</p>
        <p class="text-[10px] text-red-500 mt-3" style="font-weight:500;">Posted by ${nhEsc(row.posted_by_name)}</p>
    </div>
</div>`,

                    2: `<div class="rounded-xl overflow-hidden shadow-sm ">
                    <div style="background:#ef4444;" class="px-5 py-3 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-white text-xs"></i>
                        <p class="text-[10px] text-white/80 uppercase tracking-widest">Urgent Alert</p>
                    </div>
                    <div class="px-5 py-4 bg-white border-l-4 border-red-500">
                        <h3 class="text-sm font-bold text-gray-800 mb-1">${nhEsc(row.title)}</h3>
                        <p class="text-sm text-gray-600">${nhEsc(row.body)}</p>
                        <div class="flex items-center gap-2 mt-3">
                            <span class="bg-red-100 text-red-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Urgent</span>
                            <span class="text-[10px] text-gray-400">${date}</span>
                        </div>
                    </div>
                </div>`,

                    3: `<div class="rounded-xl overflow-hidden shadow-sm">
    <div class="grid" style="grid-template-columns:80px 1fr;">
        <div style="background:#1e293b;" class="flex flex-col items-center justify-center py-4 gap-0.5">
            <span class="text-2xl font-bold text-orange-400 leading-none">${day}</span>
            <span class="text-[10px] text-slate-400 uppercase">${month}</span>
            <span class="text-[10px] text-slate-500">${dateObj.getFullYear()}</span>
        </div>
        <div class="px-5 py-4 bg-white">
            <p class="text-[10px] text-gray-400 uppercase tracking-widest">Event / Holiday</p>
            <h3 class="text-sm font-bold text-gray-800 mt-1 mb-1">${nhEsc(row.title)}</h3>
            <p class="text-sm text-gray-600">${nhEsc(row.body)}</p>
        </div>
    </div>
</div>`,

                    4: buildPayrollCard(row)
                };

                return templates[row.template] ?? '';
            }

            document.addEventListener('DOMContentLoaded', function () {
                fetchUserAnnouncements();
                setInterval(fetchUserAnnouncements, 5000);
            });
        </script>
    </div>

</body>

</html>