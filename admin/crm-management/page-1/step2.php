<?php
//step2.php
// Tailwind-only. Ang $crmInputCls / $crmChipCls ay galing sa crm-main.php (may fallback dito).
// Galing sa crm-main.php (POST kapag failed submit, o naka-save na record kapag edit mode)
$crmForm = $crmForm ?? $_POST;
$postedMode = $postedMode ?? ($crmForm['inquiry_mode'] ?? '');
$postedNature = $postedNature ?? ($crmForm['project_nature'] ?? '');
$crmNatureLocked = $crmNatureLocked ?? '';
$crmInputCls = $crmInputCls ?? 'w-full h-11 px-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-xl outline-none transition placeholder:text-gray-400 focus:border-amber-600 focus:ring-2 focus:ring-amber-100';
$crmChipCls = $crmChipCls ?? 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] text-gray-700 bg-white border border-gray-300 select-none transition hover:border-amber-400 peer-checked:bg-amber-700 peer-checked:border-amber-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-amber-300 [&>i]:hidden peer-checked:[&>i]:inline';
?>
<div class="crm-step-panel hidden" id="crm-step-2">
    <div class="flex items-center gap-3 mb-5">
        <span
            class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0"><i
                class="fa-solid fa-ruler-combined"></i></span>
        <div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">Project Details</h2>
            <p class="text-xs text-gray-400 mt-0.5">What the client needs.</p>
        </div>
    </div>

    <!-- Project Nature: dictates the Mode below -->
    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 mb-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 mb-3">Project Nature <span
                class="text-red-500">*</span></p>
        <div id="crm_nature_group" class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl">
            <?php foreach ([
                ['modular', 'crm_nature_modular', 'fa-cubes', 'Modular', 'Custom build — needs a site visit for measurement.'],
                ['material', 'crm_nature_material', 'fa-boxes-stacked', 'Material Only', 'Client already has the 2D — straight to quotation.'],
            ] as [$val, $id, $icon, $title, $sub]): ?>
                <label class="<?= $crmNatureLocked !== '' ? 'cursor-not-allowed' : 'cursor-pointer' ?>">
                    <input type="radio" name="project_nature" id="<?= $id ?>" value="<?= $val ?>" class="peer sr-only"
                        onchange="crmSyncModeFromNature()" <?= $postedNature === $val ? 'checked' : '' ?>     <?= $crmNatureLocked !== '' ? 'disabled' : '' ?>>
                    <span
                        class="flex items-center gap-3 p-3.5 bg-white border-[1.5px] border-gray-200 rounded-xl transition hover:border-amber-300
                        peer-checked:border-amber-700 peer-checked:bg-amber-50/60 peer-checked:ring-2 peer-checked:ring-amber-100
                        peer-focus-visible:ring-2 peer-focus-visible:ring-amber-300
                        peer-checked:[&_.crm-ci]:bg-amber-700 peer-checked:[&_.crm-ci]:text-white
                        peer-checked:[&_.crm-cc]:bg-amber-700 peer-checked:[&_.crm-cc]:border-amber-700 peer-checked:[&_.crm-cc]:text-white">
                        <span
                            class="crm-ci w-10 h-10 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center shrink-0 transition"><i
                                class="fa-solid <?= $icon ?>"></i></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-gray-900"><?= $title ?></span>
                            <span class="block text-[11px] text-gray-500 mt-0.5"><?= $sub ?></span>
                        </span>
                        <span
                            class="crm-cc w-5 h-5 rounded-full border-[1.5px] border-gray-300 text-transparent text-[10px] flex items-center justify-center shrink-0 transition"><i
                                class="fa-solid fa-check"></i></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="crm-error hidden mt-1.5 text-[11px] text-red-600" data-error-for="project_nature"></p>
        <?php if ($crmNatureLocked !== ''): ?>
            <p class="mt-2 flex items-start gap-1.5 text-[11.5px] text-gray-600"><i
                    class="fa-solid fa-lock text-[10px] text-gray-400 mt-1"></i><span><?= htmlspecialchars($crmNatureLocked) ?></span>
            </p>
        <?php endif; ?>

        <!-- Mode: auto-set base sa Project Nature (naka-lock) -->
        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3 py-2">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mr-1"><i
                    class="fa-solid fa-lock text-[9px] mr-1"></i>Mode</span>
            <label class="cursor-pointer">
                <input type="radio" name="inquiry_mode" id="crm_mode_sitevisit" value="site_visit" class="peer sr-only"
                    <?= $postedMode === 'site_visit' ? 'checked' : '' ?>>
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-gray-400 bg-white border border-gray-200 peer-checked:text-white peer-checked:bg-gray-800 peer-checked:border-gray-800"><i
                        class="fa-solid fa-person-walking"></i> Site Visit Needed</span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="inquiry_mode" id="crm_mode_ready" value="ready_for_quotation"
                    class="peer sr-only" <?= $postedMode === 'ready_for_quotation' ? 'checked' : '' ?>>
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-gray-400 bg-white border border-gray-200 peer-checked:text-white peer-checked:bg-gray-800 peer-checked:border-gray-800"><i
                        class="fa-solid fa-file-invoice"></i> Ready for Quotation</span>
            </label>
            <span class="text-[11px] text-gray-400">· set automatically from Project Nature</span>
        </div>
    </section>

    <!-- Type of Project -->
    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 mb-4">
        <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="crm_project_type">Type of Project</label>
        <div class="relative">
            <i
                class="fa-solid fa-building absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-300 pointer-events-none"></i>
            <input type="text" name="project_type" id="crm_project_type" list="crm_project_type_list"
                value="<?= htmlspecialchars($crmForm['project_type'] ?? '') ?>"
                placeholder="e.g. Residential, Commercial, Renovation" class="<?= $crmInputCls ?> pl-9">
            <datalist id="crm_project_type_list">
                <option value="Residential">
                <option value="Commercial">
                <option value="Renovation">
                <option value="Condominium">
            </datalist>
        </div>
        <div class="flex flex-wrap gap-1.5 mt-2">
            <?php foreach (['Residential', 'Commercial', 'Renovation', 'Condominium'] as $pt): ?>
                <button type="button" onclick="crmSetProjectType(<?= htmlspecialchars(json_encode($pt), ENT_QUOTES) ?>)"
                    class="px-2.5 py-1 rounded-full text-[11px] font-semibold text-gray-500 bg-gray-50 border border-gray-200 hover:border-amber-400 hover:text-amber-800"><?= $pt ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Scope of Project + Scope Area (chips + gear/CRUD) -->
    <div class="grid md:grid-cols-2 gap-4 mb-4">
        <?php foreach ([
            ['project_scope', 'Scope of Project', 'project_scope_checkboxes', 'project_scope[]', $projectScopeOptions, $postedProjectScope],
            ['measuring_space', 'Scope Area', 'measuring_space_checkboxes', 'measuring_space[]', $measuringSpaceOptions, $postedMeasuringSpace],
        ] as [$type, $title, $boxId, $inputName, $opts, $posted]): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400"><?= $title ?> <span
                            data-chip-count="<?= $boxId ?>" class="text-amber-700 normal-case tracking-normal"></span></p>
                    <button type="button" onclick="openOptionsModal('<?= $type ?>')"
                        title="Manage <?= strtolower($title) ?> options"
                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-400 px-2 py-1 rounded-md hover:text-amber-700 hover:bg-amber-50">
                        <i class="fa-solid fa-gear"></i> Manage
                    </button>
                </div>
                <div id="<?= $boxId ?>" class="flex flex-wrap gap-2 max-h-48 overflow-y-auto [scrollbar-width:thin]">
                    <?php foreach ($opts as $opt): ?>
                        <label class="cursor-pointer">
                            <input type="checkbox" name="<?= $inputName ?>" value="<?= htmlspecialchars($opt['label']) ?>"
                                <?= in_array($opt['label'], $posted) ? 'checked' : '' ?> class="peer sr-only">
                            <span class="<?= $crmChipCls ?>"><i
                                    class="fa-solid fa-check text-[10px]"></i><?= htmlspecialchars($opt['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($opts)): ?>
                        <p class="text-xs text-gray-400">No options yet. Click Manage to add one.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- Measurement Date & Time: itinatakda mamaya sa Sales & Market List -->
    <div
        class="flex gap-2.5 text-xs text-gray-600 bg-amber-50/50 border border-dashed border-amber-200 rounded-xl px-3.5 py-3 leading-relaxed">
        <i class="fa-regular fa-calendar text-amber-700 mt-0.5"></i>
        <p>The <b>measurement date &amp; time</b> is set later from the <b>Sales &amp; Market List</b>, once Sales has
            confirmed the client's schedule.</p>
    </div>

    <div class="flex items-center justify-between gap-2 mt-6 pt-5 border-t border-gray-100">
        <button type="button" onclick="crmGoToStep(1)"
            class="inline-flex items-center gap-2 h-11 px-4 rounded-xl text-gray-600 text-sm font-semibold border border-gray-300 bg-white hover:text-gray-800 hover:border-gray-400">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="button" onclick="crmGoToStep3()"
            class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-gray-900 hover:bg-black text-white text-sm font-semibold">
            Next: Assignment <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>