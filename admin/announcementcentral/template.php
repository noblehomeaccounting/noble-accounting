<!-- template.php -->

<style>
    /* Template picker: selected state */
    .tpl-radio:checked + .tpl-card {
        border-color: #fb923c;
        background: #fffaf5;
        box-shadow: 0 0 0 4px rgba(251, 146, 60, 0.12);
    }
    .tpl-radio:checked + .tpl-card .tpl-check {
        opacity: 1;
        transform: scale(1);
    }
    .tpl-card:hover { border-color: #fdba74; }
    .tpl-check { opacity: 0; transform: scale(.6); transition: all .15s ease; }
    .ann-input { transition: border-color .15s, box-shadow .15s, background .15s; }
    .ann-input:focus {
        border-color: #fb923c;
        box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.15);
        background: #fff;
    }
    #ann-preview { animation: annFade .2s ease; }
    @keyframes annFade { from { opacity: .4; transform: translateY(2px); } to { opacity: 1; transform: none; } }
</style>

<!-- Header -->
<div class="mb-5">
    <h1 class="text-base font-bold text-gray-800">Create Announcement</h1>
    <p class="text-[11px] text-gray-400 mt-0.5">Pumili ng template, isulat ang detalye, at i-set kung kailan mag-e-expire</p>
</div>

<!-- Step 1: Template -->
<div class="mb-2 flex items-center gap-2">
    <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold flex items-center justify-center">1</span>
    <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">Choose template</span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">

    <!-- Template 1 -->
    <label class="cursor-pointer block">
        <input type="radio" name="template" value="1" class="hidden tpl-radio template-radio" checked>
        <div class="tpl-card relative h-full bg-white border border-gray-200 rounded-xl p-4 transition-all">
            <span class="tpl-check absolute top-3 right-3 w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center">
                <i class="fa-solid fa-check text-[9px]"></i>
            </span>
            <div class="w-9 h-9 rounded-lg bg-orange-100 text-orange-500 flex items-center justify-center mb-3">
                <i class="fa-solid fa-bullhorn text-sm"></i>
            </div>
            <p class="text-xs font-semibold text-gray-800">General Announcement</p>
            <p class="text-[11px] text-gray-400 mt-1 leading-relaxed">Para sa regular na company updates.</p>
        </div>
    </label>

    <!-- Template 2 -->
    <label class="cursor-pointer block">
        <input type="radio" name="template" value="2" class="hidden tpl-radio template-radio">
        <div class="tpl-card relative h-full bg-white border border-gray-200 rounded-xl p-4 transition-all">
            <span class="tpl-check absolute top-3 right-3 w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center">
                <i class="fa-solid fa-check text-[9px]"></i>
            </span>
            <div class="w-9 h-9 rounded-lg bg-red-100 text-red-500 flex items-center justify-center mb-3">
                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
            </div>
            <p class="text-xs font-semibold text-gray-800">Urgent Alert</p>
            <p class="text-[11px] text-gray-400 mt-1 leading-relaxed">Para sa deadlines at importanteng notice.</p>
        </div>
    </label>

    <!-- Template 3 -->
    <label class="cursor-pointer block">
        <input type="radio" name="template" value="3" class="hidden tpl-radio template-radio">
        <div class="tpl-card relative h-full bg-white border border-gray-200 rounded-xl p-4 transition-all">
            <span class="tpl-check absolute top-3 right-3 w-5 h-5 rounded-full bg-orange-500 text-white flex items-center justify-center">
                <i class="fa-solid fa-check text-[9px]"></i>
            </span>
            <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center mb-3">
                <i class="fa-regular fa-calendar-days text-sm"></i>
            </div>
            <p class="text-xs font-semibold text-gray-800">Event / Holiday</p>
            <p class="text-[11px] text-gray-400 mt-1 leading-relaxed">May date block, bagay sa events at holidays.</p>
        </div>
    </label>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">

    <!-- Form -->
    <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm">

        <div class="px-6 pt-5 pb-3 flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold flex items-center justify-center">2</span>
            <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">Details</span>
        </div>

        <div class="px-6 pb-6 space-y-5">

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="ann-title" class="text-[11px] font-semibold text-gray-600">Title</label>
                    <span id="title-count" class="text-[10px] text-gray-300">0 / 100</span>
                </div>
                <input type="text" id="ann-title" maxlength="100" placeholder="Hal. Office closed on Monday"
                    class="ann-input w-full bg-gray-50 border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm text-gray-800 placeholder-gray-300 outline-none">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="ann-body" class="text-[11px] font-semibold text-gray-600">Message</label>
                    <span id="body-count" class="text-[10px] text-gray-300">0 / 500</span>
                </div>
                <textarea id="ann-body" rows="5" maxlength="500" placeholder="Isulat dito ang buong announcement..."
                    class="ann-input w-full bg-gray-50 border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm text-gray-800 placeholder-gray-300 outline-none resize-none"></textarea>
            </div>

            <hr class="border-gray-100">

            <!-- Expiration -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold flex items-center justify-center">3</span>
                    <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">Expiration</span>
                    <span class="text-red-400 text-xs">*</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="ann-expire-date" class="block text-[11px] font-semibold text-gray-600 mb-1.5">Expires on</label>
                        <input type="date" id="ann-expire-date"
                            class="ann-input w-full bg-gray-50 border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm text-gray-800 outline-none">
                    </div>
                    <div>
                        <label for="ann-expire-time" class="block text-[11px] font-semibold text-gray-600 mb-1.5">Expires at</label>
                        <input type="time" id="ann-expire-time"
                            class="ann-input w-full bg-gray-50 border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm text-gray-800 outline-none">
                    </div>
                </div>

                <!-- Quick picks -->
                <div class="flex items-center gap-2 mt-3 flex-wrap">
                    <span class="text-[10px] text-gray-400">Quick pick:</span>
                    <button type="button" onclick="setQuickExpiry(1)"
                        class="text-[11px] font-medium text-gray-600 bg-gray-100 hover:bg-orange-50 hover:text-orange-600 px-2.5 py-1 rounded-full transition">Bukas</button>
                    <button type="button" onclick="setQuickExpiry(3)"
                        class="text-[11px] font-medium text-gray-600 bg-gray-100 hover:bg-orange-50 hover:text-orange-600 px-2.5 py-1 rounded-full transition">3 araw</button>
                    <button type="button" onclick="setQuickExpiry(7)"
                        class="text-[11px] font-medium text-gray-600 bg-gray-100 hover:bg-orange-50 hover:text-orange-600 px-2.5 py-1 rounded-full transition">1 linggo</button>
                    <button type="button" onclick="setQuickExpiry(30)"
                        class="text-[11px] font-medium text-gray-600 bg-gray-100 hover:bg-orange-50 hover:text-orange-600 px-2.5 py-1 rounded-full transition">1 buwan</button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/60 rounded-b-2xl flex items-center justify-between gap-3">
            <p class="text-[10px] text-gray-400 hidden sm:block">
                <i class="fa-regular fa-clock mr-1"></i>Awtomatikong mawawala ang announcement pagdating ng expiration.
            </p>
            <button onclick="submitAnnouncement()"
                class="ml-auto flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-5 py-2.5 rounded-lg shadow-sm transition-all disabled:opacity-60">
                <i class="fa-solid fa-bullhorn text-[11px]"></i>
                Post Announcement
            </button>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="lg:col-span-2 lg:sticky lg:top-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-500">Live preview</span>
            <span class="flex items-center gap-1 text-[10px] text-green-500">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>Real-time
            </span>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div id="ann-preview" class="rounded-xl overflow-hidden border border-gray-100"></div>
            <p class="text-[10px] text-gray-300 text-center mt-3">Ganito makikita ng iba ang announcement</p>
        </div>
    </div>

</div>

<script>
    let selectedTemplate = 1;
    const POST_LABEL = '<i class="fa-solid fa-bullhorn text-[11px]"></i> Post Announcement';

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Template selection
    document.querySelectorAll('.template-radio').forEach(radio => {
        radio.addEventListener('change', function () {
            selectedTemplate = parseInt(this.value);
            updatePreview();
        });
    });

    // Min date = today
    (function () {
        const t = new Date();
        const iso = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
        document.getElementById('ann-expire-date').min = iso;
    })();

    function setQuickExpiry(days) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        const iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        document.getElementById('ann-expire-date').value = iso;
        if (!document.getElementById('ann-expire-time').value) {
            document.getElementById('ann-expire-time').value = '17:00';
        }
        updatePreview();
    }

    ['ann-title', 'ann-body'].forEach(id => document.getElementById(id).addEventListener('input', updatePreview));
    ['ann-expire-date', 'ann-expire-time'].forEach(id => document.getElementById(id).addEventListener('change', updatePreview));

    function updatePreview() {
        const rawTitle = document.getElementById('ann-title').value;
        const rawBody = document.getElementById('ann-body').value;

        document.getElementById('title-count').textContent = rawTitle.length + ' / 100';
        document.getElementById('body-count').textContent = rawBody.length + ' / 500';

        const title = rawTitle ? esc(rawTitle) : '<span class="text-gray-300">Announcement title here</span>';
        const body = rawBody ? esc(rawBody) : '<span class="text-gray-300">Announcement message will appear here.</span>';

        const now = new Date();
        const today = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
        const day = now.getDate();
        const month = now.toLocaleString('en-PH', { month: 'short' }).toUpperCase();

        const expireDate = document.getElementById('ann-expire-date').value;
        const expireTime = document.getElementById('ann-expire-time').value;
        const expireLabel = (expireDate && expireTime)
            ? `<span class="inline-flex items-center gap-1 text-[10px] text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full font-medium">
                <i class="fa-regular fa-clock text-[9px]"></i>
                Expires ${new Date(expireDate + 'T' + expireTime).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
               </span>`
            : '';

        const previews = {
            1: `
                <div class="bg-orange-50 border-b border-orange-100 px-5 py-4">
                    <p class="text-[10px] text-orange-500 font-semibold uppercase tracking-widest">General Announcement</p>
                    <h2 class="text-sm font-bold text-gray-800 mt-1 break-words">${title}</h2>
                </div>
                <div class="px-5 py-4 bg-white">
                    <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line break-words">${body}</p>
                    <div class="flex items-center justify-between gap-2 mt-4 flex-wrap">
                        <p class="text-[10px] text-gray-400">${today}</p>
                        ${expireLabel}
                    </div>
                </div>`,

            2: `
                <div class="flex">
                    <div class="w-1 bg-red-400 flex-shrink-0"></div>
                    <div class="flex-1 bg-white">
                        <div class="bg-red-50 border-b border-red-100 px-5 py-3 flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-red-400 text-xs"></i>
                            <p class="text-[10px] text-red-500 font-semibold uppercase tracking-widest">Urgent Alert</p>
                        </div>
                        <div class="px-5 py-4">
                            <h3 class="text-sm font-bold text-gray-800 mb-1 break-words">${title}</h3>
                            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line break-words">${body}</p>
                            <div class="flex items-center gap-2 mt-4 flex-wrap">
                                <span class="bg-red-100 text-red-600 text-[10px] font-semibold px-2 py-0.5 rounded-full">Urgent</span>
                                <span class="text-[10px] text-gray-400">${today}</span>
                                ${expireLabel}
                            </div>
                        </div>
                    </div>
                </div>`,

            3: `
                <div class="grid" style="grid-template-columns: 72px 1fr;">
                    <div class="bg-slate-100 border-r border-slate-200 flex flex-col items-center justify-center py-4 gap-0.5">
                        <span class="text-2xl font-bold text-orange-500 leading-none">${day}</span>
                        <span class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">${month}</span>
                    </div>
                    <div class="px-5 py-4 bg-white">
                        <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-widest">Event / Holiday</p>
                        <h3 class="text-sm font-bold text-gray-800 mt-1 mb-1 break-words">${title}</h3>
                        <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line break-words">${body}</p>
                        <div class="mt-3">${expireLabel}</div>
                    </div>
                </div>`
        };

        const el = document.getElementById('ann-preview');
        el.style.animation = 'none';
        void el.offsetWidth;
        el.style.animation = '';
        el.innerHTML = previews[selectedTemplate];
    }

    function showToast(message, type = 'success') {
        const colors = { success: 'bg-green-500', error: 'bg-red-500' };
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
        const toast = document.createElement('div');
        toast.className = `fixed bottom-6 right-6 z-[999] flex items-center gap-3 ${colors[type]} text-white text-xs font-medium px-5 py-3 rounded-xl shadow-lg opacity-0 transition-all duration-300`;
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
        setTimeout(() => {
            toast.classList.replace('opacity-100', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    function submitAnnouncement() {
        const title = document.getElementById('ann-title').value.trim();
        const body = document.getElementById('ann-body').value.trim();
        const expireDate = document.getElementById('ann-expire-date').value;
        const expireTime = document.getElementById('ann-expire-time').value;

        if (!title || !body) {
            showToast('Please fill in the title and message.', 'error');
            return;
        }
        if (!expireDate || !expireTime) {
            showToast('Please set an expiration date and time.', 'error');
            return;
        }

        const btn = document.querySelector('button[onclick="submitAnnouncement()"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[11px]"></i> Posting...';

        const formData = new FormData();
        formData.append('template', selectedTemplate);
        formData.append('title', title);
        formData.append('body', body);
        formData.append('expires_at', `${expireDate} ${expireTime}:00`);

        fetch('<?= BASE_URL ?>/saveannouncement', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Announcement posted!');
                    document.getElementById('ann-title').value = '';
                    document.getElementById('ann-body').value = '';
                    document.getElementById('ann-expire-date').value = '';
                    document.getElementById('ann-expire-time').value = '';
                    updatePreview();
                } else {
                    showToast('Failed to post: ' + (data.error ?? 'Unknown error'), 'error');
                }
                btn.disabled = false;
                btn.innerHTML = POST_LABEL;
            })
            .catch(() => {
                showToast('Something went wrong. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = POST_LABEL;
            });
    }

    updatePreview();
</script>