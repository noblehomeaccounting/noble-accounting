<?php
// user/ui/index-main-page-1.php

include ROOT_PATH . '/network/connect.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$control_no = 'PENDING';
$today = date('Y-m-d');
$todayLabel = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Request Form NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <style>
        /* ── Stepper ── */
        .br-step .br-num {
            transition: all .25s ease;
        }

        .br-step.is-active .br-num {
            background: #f7941d;
            border-color: #f7941d;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(247, 148, 29, .25);
        }

        .br-step.is-done .br-num {
            background: #fff;
            border-color: #fff;
            color: #2b2b2b;
        }

        .br-step.is-active .br-sub {
            color: #fdba74;
        }

        .br-step.is-active .br-title {
            color: #fff;
        }

        .br-step.is-done .br-title {
            color: rgba(255, 255, 255, .85);
        }

        .br-step.is-done {
            cursor: pointer;
        }

        /* vertical line sa pagitan ng steps (desktop) */
        .br-line {
            position: absolute;
            left: 17px;
            top: 38px;
            bottom: -18px;
            width: 2px;
            border-radius: 2px;
            background: rgba(255, 255, 255, .25);
            pointer-events: none;
        }

        .br-step:last-child .br-line {
            display: none !important;
        }

        .br-step.is-done .br-line {
            background: #f7941d !important;
        }

        /* panel animation */
        .br-panel {
            animation: brFade .25s ease;
        }

        @keyframes brFade {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* inputs */
        .br-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: .6rem;
            padding: .6rem .8rem;
            font-size: .875rem;
            color: #1f2937;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .br-input:focus {
            border-color: #f7941d;
            box-shadow: 0 0 0 3px rgba(247, 148, 29, .18);
        }

        .br-input[readonly] {
            background: #f8fafc;
            color: #6b7280;
            cursor: not-allowed;
        }

        .br-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #6b7280;
            margin-bottom: .35rem;
        }

        /* roof accent sa side panel */
        .br-roof {
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 3px;
            background: #f7941d;
        }

        /* scroll */
        .br-scroll {
            scrollbar-width: thin;
            scrollbar-color: #fdba74 transparent;
        }

        .br-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .br-scroll::-webkit-scrollbar-thumb {
            background: #fdba74;
            border-radius: 99px;
        }
    </style>
</head>

<body class="min-h-screen relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <div class="fixed inset-0 bg-black/55 z-0"></div>

    <main class="relative z-10 px-3 md:px-6 pt-24 md:pt-32 pb-4 md:pb-6">
        <div
            class="max-w-6xl mx-auto bg-white rounded-2xl overflow-hidden grid grid-cols-1 grid-rows-[auto_minmax(0,1fr)] lg:grid-rows-1 lg:grid-cols-[280px_1fr] h-[calc(100dvh-7.5rem)] md:h-[calc(100dvh-10rem)] border-t-[3px] border-[#f7941d] ring-1 ring-white/30 shadow-[0_30px_60px_-20px_rgba(0,0,0,.75)]">

            <!-- ═════════ LEFT: Brand + Stepper (charcoal) ═════════ -->
            <aside class="relative text-white px-5 py-5 lg:px-6 lg:py-7 flex flex-col lg:border-r lg:border-black/20 lg:overflow-y-auto br-scroll"
                style="background:#2b2b2b;">

                <!-- Brand -->
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl bg-white flex items-center justify-center shrink-0">
                        <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Noblehome" class="w-9 h-9 object-contain">
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#f7941d]">Budget Request</p>
                        <p class="text-sm font-bold leading-tight truncate">Noblehome Construction</p>
                    </div>
                </div>

                <!-- Control / Date -->
                <div class="grid grid-cols-2 gap-2 mt-5">
                    <div class="rounded-lg bg-[#363636] border border-white/20 px-3 py-2">
                        <p class="text-[9px] uppercase tracking-widest text-white/65">Control No.</p>
                        <p class="text-xs font-mono font-bold mt-0.5"><?= htmlspecialchars($control_no) ?></p>
                    </div>
                    <div class="rounded-lg bg-[#363636] border border-white/20 px-3 py-2">
                        <p class="text-[9px] uppercase tracking-widest text-white/65">Date</p>
                        <p class="text-xs font-semibold mt-0.5"><?= htmlspecialchars(date('M j, Y')) ?></p>
                    </div>
                </div>

                <!-- Stepper: horizontal sa mobile, vertical sa desktop -->
                <ol id="br-stepper"
                    class="mt-6 grid grid-cols-4 gap-2 lg:flex lg:flex-col lg:gap-0 lg:space-y-5">
                    <?php
                    $steps = [
                        1 => ['Request Information', 'Name & purpose', 'fa-user-pen'],
                        2 => ['Items', 'What you need', 'fa-list-check'],
                        3 => ['Attachments', 'Proof & receipts', 'fa-paperclip'],
                        4 => ['Review & Submit', 'Category & head', 'fa-paper-plane'],
                    ];
                    foreach ($steps as $n => [$t, $sub, $ico]): ?>
                        <li class="br-step relative flex flex-col lg:flex-row items-center lg:items-start gap-1.5 lg:gap-3 text-center lg:text-left"
                            data-step="<?= $n ?>">
                            <span class="br-line hidden lg:block"></span>
                            <span
                                class="br-num relative z-10 w-9 h-9 rounded-full bg-[#3a3a3a] border border-white/25 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                <span class="br-n"><?= $n ?></span>
                            </span>
                            <span class="min-w-0 lg:pt-1">
                                <span class="br-title block text-[10px] lg:text-sm font-semibold text-white/80 leading-tight"><?= $t ?></span>
                                <span class="br-sub hidden lg:block text-[11px] text-white/55 mt-0.5"><?= $sub ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <!-- Live total (desktop) -->
                <div class="hidden lg:block mt-auto pt-6">
                    <div class="rounded-xl p-4" style="background:linear-gradient(135deg,#f7941d,#e07b06);">
                        <p class="text-[10px] uppercase tracking-widest text-white/80 font-bold">Total Amount</p>
                        <p class="text-2xl font-extrabold font-mono mt-1" id="br-side-total">₱ 0.00</p>
                        <p class="text-[11px] text-white/80 mt-1" id="br-side-items">0 items</p>
                    </div>
                    <p class="text-[10px] text-white/55 mt-3 leading-relaxed">
                        1181 MC Premiere Bldg., EDSA Balintawak, Quezon City<br>
                        Tel. 02-88221295 · 0968-591-6544
                    </p>
                </div>
            </aside>

            <!-- ═════════ RIGHT: Step content ═════════ -->
            <section class="flex flex-col min-w-0 min-h-0 overflow-hidden">

                <!-- Step header -->
                <header class="shrink-0 px-5 md:px-8 pt-6 pb-4 border-b border-gray-100">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#f7941d]" id="m-step-label">Step 1
                        of 4</p>
                    <h2 class="text-xl md:text-2xl font-extrabold text-gray-900 leading-tight mt-1" id="m-step-title">
                        Request Information</h2>
                    <p class="text-sm text-gray-500 mt-0.5" id="m-step-sub">Fill in your name and purpose</p>
                    <!-- progress bar -->
                    <div class="mt-4 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                        <div id="br-progress" class="h-full rounded-full transition-all duration-300"
                            style="width:25%; background:linear-gradient(90deg,#f7941d,#e07b06);"></div>
                    </div>
                </header>

                <!-- Panels -->
                <div id="br-scroll-area" class="br-scroll flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 md:px-8 py-6 bg-slate-50/60">

                    <!-- ── Step 1: Info ── -->
                    <div id="m-step-1" class="br-panel space-y-5">
                        <div>
                            <label class="br-label" for="m-requestor-name">Requestor Name <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <i
                                    class="fa-regular fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" id="m-requestor-name" placeholder="Enter your full name"
                                    class="br-input" style="padding-left:2.3rem"
                                    value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>">
                            </div>
                        </div>
                        <div>
                            <label class="br-label" for="m-purpose">Purpose of Request <span
                                    class="text-red-500">*</span></label>
                            <textarea id="m-purpose" rows="3" placeholder="What is this request for?"
                                class="br-input resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="br-label">Control No.</label>
                                <input type="text" value="<?= htmlspecialchars($control_no) ?>" readonly
                                    class="br-input font-mono">
                            </div>
                            <div>
                                <label class="br-label">Date</label>
                                <input type="text" value="<?= htmlspecialchars($todayLabel) ?>" readonly
                                    class="br-input">
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl border border-orange-100 bg-orange-50/60 px-4 py-3">
                            <i class="fa-solid fa-circle-info text-[#f7941d] mt-0.5"></i>
                            <p class="text-xs text-gray-600">A control number will be assigned automatically once your
                                request is submitted.</p>
                        </div>
                    </div>

                    <!-- ── Step 2: Items ── -->
                    <div id="m-step-2" class="br-panel hidden space-y-3">
                        <div id="m-item-cards" class="space-y-3"></div>

                        <button type="button" onclick="mAddCard()"
                            class="w-full flex items-center justify-center gap-2 border-2 border-dashed border-orange-300 text-[#e07b06] font-semibold text-sm rounded-xl py-3 hover:bg-orange-50 transition-all">
                            <i class="fa-solid fa-plus text-xs"></i> Add Item
                        </button>

                        <!-- Total (mobile + desktop) -->
                        <div class="lg:hidden rounded-xl px-4 py-3 flex items-center justify-between"
                            style="background:linear-gradient(135deg,#f7941d,#e07b06);">
                            <span class="text-white/85 text-xs font-bold uppercase tracking-wider">Total Amount</span>
                            <span class="text-white font-bold font-mono text-lg" id="m-grand-total">₱ 0.00</span>
                        </div>
                    </div>

                    <!-- ── Step 3: Attachments ── -->
                    <div id="m-step-3" class="br-panel hidden space-y-4">
                        <!-- Follow up toggle -->
                        <label
                            class="flex items-center gap-3 cursor-pointer select-none p-4 bg-white border border-gray-200 rounded-xl hover:border-orange-300 transition-colors">
                            <div class="relative shrink-0">
                                <input type="checkbox" id="m-follow-up-toggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-[#f7941d] transition-colors">
                                </div>
                                <div
                                    class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-all peer-checked:translate-x-5">
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Follow up attachment</p>
                                <p class="text-xs text-gray-400">I don't have the proof yet. I'll upload it later.</p>
                            </div>
                        </label>

                        <div id="m-drop-zone"
                            class="flex flex-col items-center justify-center w-full py-12 bg-white border-2 border-dashed border-gray-200 rounded-2xl cursor-pointer hover:border-[#f7941d] hover:bg-orange-50/50 transition-all group">
                            <span
                                class="w-14 h-14 rounded-2xl bg-orange-50 group-hover:bg-white flex items-center justify-center mb-3 transition-colors">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl text-[#f7941d]"></i>
                            </span>
                            <span class="text-sm text-gray-700 font-semibold">Click or drag files here</span>
                            <span class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP or PDF · multiple allowed</span>
                        </div>
                        <input type="file" id="m-attachment-input"
                            accept="image/jpeg,image/png,image/webp,application/pdf" multiple class="hidden">

                        <div id="m-attachment-preview" class="flex flex-wrap gap-3"></div>
                        <p class="text-[11px] text-gray-400"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Images
                            are converted to WebP automatically.</p>
                    </div>

                    <!-- ── Step 4: Review & Submit ── -->
                    <div id="m-step-4" class="br-panel hidden">
                        <div class="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-5">

                            <div class="space-y-5 min-w-0">
                                <!-- Category -->
                                <div class="bg-white rounded-xl p-5 border border-gray-200">
                                    <p class="br-label mb-3">1. Request Category <span class="text-red-500">*</span></p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div onclick="mSelectCategory('project', this)"
                                            class="m-cat-card flex flex-col items-center gap-1 px-2 py-4 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-orange-300 hover:bg-orange-50 transition-all text-center">
                                            <i class="fa-solid fa-helmet-safety text-gray-300 text-xl m-cat-icon"></i>
                                            <span class="text-xs font-semibold text-gray-700">Project</span>
                                            <span class="text-[10px] text-gray-400">Tied to a project</span>
                                        </div>
                                        <div onclick="mSelectCategory('client', this)"
                                            class="m-cat-card flex flex-col items-center gap-1 px-2 py-4 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-orange-300 hover:bg-orange-50 transition-all text-center">
                                            <i class="fa-solid fa-user-tie text-gray-300 text-xl m-cat-icon"></i>
                                            <span class="text-xs font-semibold text-gray-700">Client</span>
                                            <span class="text-[10px] text-gray-400">Tied to a client</span>
                                        </div>
                                        <div onclick="mSelectCategory('nhcc', this)"
                                            class="m-cat-card flex flex-col items-center gap-1 px-2 py-4 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-orange-300 hover:bg-orange-50 transition-all text-center">
                                            <i class="fa-solid fa-building text-gray-300 text-xl m-cat-icon"></i>
                                            <span class="text-xs font-semibold text-gray-700">NHCC</span>
                                            <span class="text-[10px] text-gray-400">Internal use</span>
                                        </div>
                                    </div>
                                    <div id="m-category-ref-section" class="hidden mt-4">
                                        <label id="m-category-ref-label" class="br-label">Project name</label>
                                        <input type="text" id="m-category-ref-input"
                                            placeholder="Enter project name..." class="br-input">
                                    </div>
                                </div>

                                <!-- Choose head -->
                                <div class="bg-white rounded-xl p-5 border border-gray-200">
                                    <p class="br-label mb-3">2. Send To (Head) <span class="text-red-500">*</span></p>
                                    <div id="m-heads-list" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div class="text-center text-gray-400 text-sm py-4 sm:col-span-2">
                                            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden h-fit">
                                <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2"
                                    style="background:linear-gradient(135deg,#333,#262626);">
                                    <i class="fa-solid fa-receipt text-[#f7941d]"></i>
                                    <p class="text-xs font-bold uppercase tracking-widest text-white">Summary</p>
                                </div>
                                <div class="p-5 space-y-3 text-sm">
                                    <div class="flex justify-between gap-3"><span
                                            class="text-gray-500">Requestor</span><span
                                            class="font-semibold text-gray-800 text-right" id="m-summary-name">—</span>
                                    </div>
                                    <div class="flex justify-between gap-3"><span class="text-gray-500">Purpose</span><span
                                            class="font-semibold text-gray-800 text-right max-w-[60%] break-words"
                                            id="m-summary-purpose">—</span></div>
                                    <div class="flex justify-between gap-3"><span class="text-gray-500">Items</span><span
                                            class="font-semibold text-gray-800" id="m-summary-items">0 items</span></div>
                                    <div class="flex justify-between gap-3"><span
                                            class="text-gray-500">Attachments</span><span
                                            class="font-semibold text-gray-800" id="m-summary-attachments">0
                                            files</span></div>
                                    <ul id="m-summary-list"
                                        class="border-t border-dashed border-gray-200 pt-3 space-y-1.5 text-xs text-gray-600 max-h-40 overflow-y-auto br-scroll">
                                    </ul>
                                    <div class="flex justify-between items-center border-t border-gray-100 pt-3">
                                        <span class="text-gray-500 font-semibold">Total</span>
                                        <span class="font-extrabold text-[#e07b06] font-mono text-lg"
                                            id="m-summary-total">₱ 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer nav -->
                <footer
                    class="shrink-0 bg-white border-t border-gray-100 px-5 md:px-8 py-4 flex items-center gap-3">
                    <button id="m-btn-back" type="button" onclick="mPrevStep()"
                        class="hidden h-11 px-4 flex items-center gap-2 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition-all">
                        <i class="fa-solid fa-chevron-left text-xs"></i> <span class="hidden sm:inline">Back</span>
                    </button>
                    <span class="hidden md:block text-[10px] text-gray-300 font-mono">20260425-BRF-v1</span>
                    <button id="m-btn-next" type="button" onclick="mNextStep()"
                        class="ml-auto h-11 px-6 flex-1 sm:flex-none flex items-center justify-center gap-2 bg-[#f7941d] hover:bg-[#e07b06] active:scale-95 text-white font-bold text-sm rounded-xl shadow-sm transition-all disabled:opacity-60">
                        Next <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </footer>
            </section>
        </div>
    </main>

    <script>
        (function () {
            // ═══════════════════════════════════════════
            // STATE
            // ═══════════════════════════════════════════
            let attachmentFiles = [];
            let mCardCount = 0;
            let mSelectedHeadId = null;
            let mSelectedCategory = null;
            let mCurrentStep = 1;
            let mMaxVisited = 1;
            const mTotalSteps = 4;
            const controlNo = <?= json_encode($control_no) ?>;
            const dateRequested = <?= json_encode($today) ?>;

            const peso = n => '₱ ' + (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

            const mStepTitles = ['', 'Request Information', 'Add Items', 'Attachments', 'Review & Submit'];
            const mStepSubs = ['', 'Fill in your name and purpose', 'Add the items you need', 'Attach proof or receipts', 'Choose a category and who will approve'];

            // ═══════════════════════════════════════════
            // UI
            // ═══════════════════════════════════════════
            function mUpdateUI() {
                for (let i = 1; i <= mTotalSteps; i++) {
                    document.getElementById('m-step-' + i).classList.toggle('hidden', i !== mCurrentStep);
                }

                // Stepper
                document.querySelectorAll('.br-step').forEach(li => {
                    const n = +li.dataset.step;
                    li.classList.toggle('is-active', n === mCurrentStep);
                    li.classList.toggle('is-done', n < mCurrentStep || (n <= mMaxVisited && n !== mCurrentStep));
                    li.querySelector('.br-n').innerHTML = (n < mCurrentStep)
                        ? '<i class="fa-solid fa-check"></i>' : n;
                });

                document.getElementById('m-step-label').textContent = 'Step ' + mCurrentStep + ' of ' + mTotalSteps;
                document.getElementById('m-step-title').textContent = mStepTitles[mCurrentStep];
                document.getElementById('m-step-sub').textContent = mStepSubs[mCurrentStep];
                document.getElementById('br-progress').style.width = (mCurrentStep / mTotalSteps * 100) + '%';

                document.getElementById('m-btn-back').classList.toggle('hidden', mCurrentStep === 1);

                const nextBtn = document.getElementById('m-btn-next');
                nextBtn.innerHTML = mCurrentStep === mTotalSteps
                    ? '<i class="fa-solid fa-paper-plane text-xs"></i> Submit Request'
                    : 'Next <i class="fa-solid fa-chevron-right text-xs"></i>';

                document.getElementById('br-scroll-area').scrollTop = 0;

                if (mCurrentStep === 4) {
                    mFillSummary();
                    mLoadHeads();
                }
            }

            function validateStep(step) {
                if (step === 1) {
                    const name = document.getElementById('m-requestor-name').value.trim();
                    const purpose = document.getElementById('m-purpose').value.trim();
                    if (!name || !purpose) { mShowToast('Please fill in your name and purpose.', 'error'); return false; }
                }
                if (step === 2) {
                    const filled = [...document.querySelectorAll('#m-item-cards [data-card]')]
                        .filter(c => c.querySelector('[data-f="desc"]').value.trim() !== '');
                    if (!filled.length) { mShowToast('Please add at least one item with a description.', 'error'); return false; }
                }
                if (step === 3) {
                    if (attachmentFiles.length === 0 && !document.getElementById('m-follow-up-toggle').checked) {
                        mShowToast('Please attach at least one file, or turn on "Follow up attachment".', 'error');
                        return false;
                    }
                }
                return true;
            }

            function mNextStep() {
                if (!validateStep(mCurrentStep)) return;
                if (mCurrentStep === mTotalSteps) { mSubmit(); return; }
                mCurrentStep++;
                mMaxVisited = Math.max(mMaxVisited, mCurrentStep);
                mUpdateUI();

            }

            function mPrevStep() {
                if (mCurrentStep > 1) { mCurrentStep--; mUpdateUI(); }
            }

            // Click sa stepper (para bumalik o pumunta sa nabisitang step)
            document.querySelectorAll('.br-step').forEach(li => {
                li.addEventListener('click', () => {
                    const n = +li.dataset.step;
                    if (n === mCurrentStep || n > mMaxVisited) return;
                    // kapag pasulong, i-validate ang mga step sa pagitan
                    for (let s = mCurrentStep; s < n; s++) { if (!validateStep(s)) return; }
                    mCurrentStep = n;
                    mUpdateUI();
                });
            });

            // ═══════════════════════════════════════════
            // CATEGORY
            // ═══════════════════════════════════════════
            function mSelectCategory(cat, el) {
                mSelectedCategory = cat;
                document.querySelectorAll('.m-cat-card').forEach(c => {
                    c.classList.remove('border-[#f7941d]', 'bg-orange-50');
                    c.classList.add('border-gray-100');
                    c.querySelector('.m-cat-icon').classList.replace('text-[#f7941d]', 'text-gray-300');
                });
                el.classList.add('border-[#f7941d]', 'bg-orange-50');
                el.classList.remove('border-gray-100');
                el.querySelector('.m-cat-icon').classList.replace('text-gray-300', 'text-[#f7941d]');

                const section = document.getElementById('m-category-ref-section');
                const label = document.getElementById('m-category-ref-label');
                const input = document.getElementById('m-category-ref-input');
                if (cat === 'nhcc') {
                    section.classList.add('hidden');
                    input.value = '';
                } else {
                    section.classList.remove('hidden');
                    label.textContent = cat === 'project' ? 'Project name' : 'Client name';
                    input.placeholder = cat === 'project' ? 'Enter project name...' : 'Enter client name...';
                    setTimeout(() => input.focus(), 50);
                }
            }

            // ═══════════════════════════════════════════
            // ITEMS (Step 2)
            // ═══════════════════════════════════════════
            function renumberCards() {
                document.querySelectorAll('#m-item-cards [data-card]').forEach((c, i) => {
                    c.querySelector('.br-item-no').textContent = i + 1;
                });
            }

            function mAddCard() {
                mCardCount++;
                const card = document.createElement('div');
                card.className = 'bg-white rounded-xl border border-gray-200 p-4 hover:border-orange-200 transition-colors';
                card.dataset.card = mCardCount;
                card.innerHTML = `
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-widest">
                            <span class="br-item-no w-6 h-6 rounded-md bg-orange-50 text-[#e07b06] flex items-center justify-center text-[11px]"></span>
                            Item
                        </span>
                        <button type="button" data-remove class="text-gray-300 hover:text-red-500 text-xs font-semibold transition-colors">
                            <i class="fa-solid fa-trash-can"></i> <span class="hidden sm:inline">Remove</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                        <div class="col-span-2 md:col-span-4">
                            <label class="br-label">Description</label>
                            <input type="text" data-f="desc" placeholder="Item description..." class="br-input">
                        </div>
                        <div class="col-span-2 md:col-span-4">
                            <label class="br-label">Purpose</label>
                            <input type="text" data-f="purpose" placeholder="Purpose of this item..." class="br-input">
                        </div>
                        <div class="md:col-span-2">
                            <label class="br-label">Quantity</label>
                            <input type="number" min="0" placeholder="0" data-type="qty" class="br-input text-center">
                        </div>
                        <div class="md:col-span-2">
                            <label class="br-label">Unit Price</label>
                            <input type="number" min="0" step="0.01" placeholder="0.00" data-type="price" class="br-input text-right">
                        </div>
                        <div class="col-span-2 md:col-span-9">
                            <label class="br-label">Notes</label>
                            <input type="text" data-f="notes" placeholder="Optional notes..." class="br-input">
                        </div>
                        <div class="col-span-2 md:col-span-3 flex md:flex-col items-center md:items-end justify-between md:justify-end">
                            <span class="br-label md:mb-1">Amount</span>
                            <span class="m-card-amount font-extrabold font-mono text-gray-900 text-base">₱ 0.00</span>
                        </div>
                    </div>`;
                document.getElementById('m-item-cards').appendChild(card);
                card.querySelectorAll('[data-type]').forEach(i => i.addEventListener('input', () => mCalcCard(card)));
                card.querySelector('[data-remove]').addEventListener('click', () => mRemoveCard(card));
                renumberCards();
                mCalcTotal();
                return card;
            }

            function mRemoveCard(card) {
                if (document.querySelectorAll('#m-item-cards [data-card]').length <= 1) {
                    mShowToast('At least one item is required.', 'error');
                    return;
                }
                card.style.transition = 'opacity .2s';
                card.style.opacity = '0';
                setTimeout(() => { card.remove(); renumberCards(); mCalcTotal(); }, 200);
            }

            function mCalcCard(card) {
                const qty = parseFloat(card.querySelector('[data-type="qty"]').value) || 0;
                const price = parseFloat(card.querySelector('[data-type="price"]').value) || 0;
                card.querySelector('.m-card-amount').textContent = peso(qty * price);
                mCalcTotal();
            }

            function cardAmount(card) {
                const qty = parseFloat(card.querySelector('[data-type="qty"]').value) || 0;
                const price = parseFloat(card.querySelector('[data-type="price"]').value) || 0;
                return qty * price;
            }

            function mCalcTotal() {
                const cards = [...document.querySelectorAll('#m-item-cards [data-card]')];
                const total = cards.reduce((s, c) => s + cardAmount(c), 0);
                const filled = cards.filter(c => c.querySelector('[data-f="desc"]').value.trim() !== '').length;
                document.getElementById('m-grand-total').textContent = peso(total);
                document.getElementById('br-side-total').textContent = peso(total);
                document.getElementById('br-side-items').textContent = filled + ' item' + (filled !== 1 ? 's' : '');
            }
            document.getElementById('m-item-cards').addEventListener('input', e => {
                if (e.target.matches('[data-f="desc"]')) mCalcTotal();
            });

            // ═══════════════════════════════════════════
            // ATTACHMENTS (Step 3)
            // ═══════════════════════════════════════════
            function handleFiles(files) {
                files.forEach(file => {
                    if (file.type === 'application/pdf') {
                        const reader = new FileReader();
                        reader.onload = ev => {
                            attachmentFiles.push({ id: Date.now() + '_' + Math.random().toString(36).slice(2), name: file.name, webpBase64: ev.target.result, isPdf: true });
                            renderPreviews();
                        };
                        reader.readAsDataURL(file);
                        return;
                    }
                    if (!file.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = ev => {
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            canvas.width = img.width; canvas.height = img.height;
                            canvas.getContext('2d').drawImage(img, 0, 0);
                            attachmentFiles.push({
                                id: Date.now() + '_' + Math.random().toString(36).slice(2),
                                name: file.name.replace(/\.[^.]+$/, '') + '.webp',
                                webpBase64: canvas.toDataURL('image/webp', 0.85)
                            });
                            renderPreviews();
                        };
                        img.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);
                });
            }

            function renderPreviews() {
                const el = document.getElementById('m-attachment-preview');
                el.innerHTML = attachmentFiles.map(f => `
                    <div class="relative group/thumb w-24 h-24 rounded-xl overflow-hidden border border-gray-200 bg-white shadow-sm">
                        ${f.isPdf
                        ? `<div class="w-full h-full bg-red-50 flex flex-col items-center justify-center">
                                <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
                                <span class="text-[9px] text-red-400 mt-1 font-bold">PDF</span>
                           </div>`
                        : `<img src="${f.webpBase64}" class="w-full h-full object-cover">`}
                        <button type="button" data-rm="${f.id}"
                            class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 hover:bg-red-500 text-white flex items-center justify-center text-[10px] transition-colors">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <p class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-[9px] px-1.5 py-0.5 truncate">${esc(f.name)}</p>
                    </div>`).join('');
            }

            document.getElementById('m-attachment-preview').addEventListener('click', e => {
                const b = e.target.closest('[data-rm]');
                if (!b) return;
                attachmentFiles = attachmentFiles.filter(f => f.id !== b.dataset.rm);
                renderPreviews();
            });

            const dz = document.getElementById('m-drop-zone');
            dz.addEventListener('click', () => document.getElementById('m-attachment-input').click());
            document.getElementById('m-attachment-input').addEventListener('change', e => {
                handleFiles([...e.target.files]);
                e.target.value = '';
            });
            dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-[#f7941d]', 'bg-orange-50'); });
            dz.addEventListener('dragleave', () => dz.classList.remove('border-[#f7941d]', 'bg-orange-50'));
            dz.addEventListener('drop', e => {
                e.preventDefault();
                dz.classList.remove('border-[#f7941d]', 'bg-orange-50');
                handleFiles([...e.dataTransfer.files]);
            });

            // ═══════════════════════════════════════════
            // REVIEW (Step 4)
            // ═══════════════════════════════════════════
            function mFillSummary() {
                const cards = [...document.querySelectorAll('#m-item-cards [data-card]')]
                    .filter(c => c.querySelector('[data-f="desc"]').value.trim() !== '');
                const total = cards.reduce((s, c) => s + cardAmount(c), 0);

                document.getElementById('m-summary-name').textContent = document.getElementById('m-requestor-name').value.trim() || '—';
                document.getElementById('m-summary-purpose').textContent = document.getElementById('m-purpose').value.trim() || '—';
                document.getElementById('m-summary-items').textContent = cards.length + ' item' + (cards.length !== 1 ? 's' : '');
                document.getElementById('m-summary-total').textContent = peso(total);
                const follow = document.getElementById('m-follow-up-toggle').checked;
                document.getElementById('m-summary-attachments').textContent =
                    attachmentFiles.length + ' file' + (attachmentFiles.length !== 1 ? 's' : '') + (follow ? ' · follow up' : '');
                document.getElementById('m-summary-list').innerHTML = cards.map(c => `
                    <li class="flex justify-between gap-3">
                        <span class="truncate">${esc(c.querySelector('[data-f="desc"]').value)} <span class="text-gray-400">× ${parseFloat(c.querySelector('[data-type="qty"]').value) || 0}</span></span>
                        <span class="font-mono shrink-0">${peso(cardAmount(c))}</span>
                    </li>`).join('');
            }

            let headsLoaded = false;
            function mLoadHeads() {
                if (headsLoaded) return;
                fetch('<?= BASE_URL ?>/fetchheads')
                    .then(res => res.json())
                    .then(data => {
                        const list = document.getElementById('m-heads-list');
                        if (!data.length) {
                            list.innerHTML = '<p class="text-center text-gray-400 text-sm py-4 sm:col-span-2">No heads available.</p>';
                            return;
                        }
                        headsLoaded = true;
                        list.innerHTML = data.map(h => `
                            <div data-head="${h.id}"
                                class="m-head flex items-center gap-3 px-3 py-3 rounded-xl border-2 border-gray-100 hover:border-orange-300 hover:bg-orange-50 cursor-pointer transition-all">
                                <div class="w-9 h-9 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-user-tie text-[#e07b06] text-xs"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-800 truncate">${esc(h.name)}</p>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide truncate">${esc(h.role)}</p>
                                </div>
                                <i class="m-head-check fa-solid fa-circle-check text-[#f7941d] hidden"></i>
                            </div>`).join('');
                    })
                    .catch(() => {
                        document.getElementById('m-heads-list').innerHTML =
                            '<p class="text-center text-red-400 text-sm py-4 sm:col-span-2">Could not load heads. Please refresh.</p>';
                    });
            }

            document.getElementById('m-heads-list').addEventListener('click', e => {
                const el = e.target.closest('[data-head]');
                if (!el) return;
                mSelectedHeadId = +el.dataset.head;
                document.querySelectorAll('.m-head').forEach(h => {
                    h.classList.remove('border-[#f7941d]', 'bg-orange-50');
                    h.classList.add('border-gray-100');
                    h.querySelector('.m-head-check').classList.add('hidden');
                });
                el.classList.add('border-[#f7941d]', 'bg-orange-50');
                el.classList.remove('border-gray-100');
                el.querySelector('.m-head-check').classList.remove('hidden');
            });

            // ═══════════════════════════════════════════
            // SUBMIT
            // ═══════════════════════════════════════════
            function mSubmit() {
                if (!mSelectedCategory) { mShowToast('Please select a request category.', 'error'); return; }
                if (mSelectedCategory !== 'nhcc' && !document.getElementById('m-category-ref-input').value.trim()) {
                    mShowToast('Please enter the project or client name.', 'error'); return;
                }
                if (!mSelectedHeadId) { mShowToast('Please select a head to send to.', 'error'); return; }

                const items = [];
                document.querySelectorAll('#m-item-cards [data-card]').forEach(card => {
                    const desc = card.querySelector('[data-f="desc"]').value.trim();
                    if (!desc) return; // laktawan ang walang laman
                    items.push({
                        description: desc,
                        purpose: card.querySelector('[data-f="purpose"]').value || '',
                        quantity: card.querySelector('[data-type="qty"]').value || 0,
                        unit_price: card.querySelector('[data-type="price"]').value || 0,
                        amount: cardAmount(card).toFixed(2),
                        notes: card.querySelector('[data-f="notes"]').value || ''
                    });
                });

                const nextBtn = document.getElementById('m-btn-next');
                nextBtn.disabled = true;
                nextBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

                fetch('<?= BASE_URL ?>/submitrequest', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        control_no: controlNo,
                        requestor_name: document.getElementById('m-requestor-name').value.trim(),
                        purpose: document.getElementById('m-purpose').value.trim(),
                        date_requested: dateRequested,
                        sent_to: mSelectedHeadId,
                        items: items,
                        attachments: attachmentFiles.map(f => ({ name: f.name, data: f.webpBase64 })),
                        attachment_status: document.getElementById('m-follow-up-toggle').checked ? 'follow_up' : 'attached',
                        request_category: mSelectedCategory,
                        request_reference: mSelectedCategory !== 'nhcc'
                            ? document.getElementById('m-category-ref-input').value.trim()
                            : null
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            mShowToast('Request submitted successfully!', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            mShowToast('Failed: ' + (data.error || 'Unknown error'), 'error');
                            resetSubmitBtn();
                        }
                    })
                    .catch(() => { mShowToast('Network error. Please try again.', 'error'); resetSubmitBtn(); });
            }

            function resetSubmitBtn() {
                const b = document.getElementById('m-btn-next');
                b.disabled = false;
                b.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> Submit Request';
            }

            function mShowToast(msg, type = 'success') {
                document.getElementById('m-toast')?.remove();
                const color = type === 'success' ? 'bg-green-500' : 'bg-red-500';
                const icon = type === 'success' ? 'fa-check' : 'fa-triangle-exclamation';
                const t = document.createElement('div');
                t.id = 'm-toast';
                t.className = `fixed bottom-24 left-4 right-4 md:left-auto md:right-6 md:bottom-6 md:w-96 z-[999] flex items-center gap-3 ${color} text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg transition-opacity duration-300 opacity-0`;
                t.innerHTML = `<i class="fa-solid ${icon}"></i> <span></span>`;
                t.querySelector('span').textContent = msg;
                document.body.appendChild(t);
                setTimeout(() => t.classList.replace('opacity-0', 'opacity-100'), 10);
                setTimeout(() => { t.classList.replace('opacity-100', 'opacity-0'); setTimeout(() => t.remove(), 300); }, 3000);
            }

            // ── Expose para sa onclick sa HTML ──
            window.mNextStep = mNextStep;
            window.mPrevStep = mPrevStep;
            window.mAddCard = () => { const c = mAddCard(); c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.querySelector('input').focus({ preventScroll: true }); };
            window.mSelectCategory = mSelectCategory;

            // ── Init ──
            for (let i = 0; i < 3; i++) mAddCard();
            mUpdateUI();
        })();
    </script>
</body>

</html>