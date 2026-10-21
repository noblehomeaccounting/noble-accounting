<?php
//step2.php
$postedMode = $_POST['inquiry_mode'] ?? '';
$postedNature = $_POST['project_nature'] ?? '';
?>
<div class="crm-step-panel" id="crm-step-2">
    <div class="crm-step-head">
        <span class="crm-step-icon"><i class="fa-solid fa-ruler-combined"></i></span>
        <div>
            <h2 class="crm-step-title">Project Details</h2>
            <p class="crm-step-sub">What the client needs.</p>
        </div>
    </div>

    <!-- Project Nature: dictates the Mode below -->
    <section class="crm-section">
        <p class="crm-section-label">Project Nature <span class="text-red-500">*</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="project_nature" id="crm_nature_modular" value="modular"
                    class="peer sr-only" onchange="crmSyncModeFromNature()"
                    <?= $postedNature === 'modular' ? 'checked' : '' ?>>
                <span class="crm-choice">
                    <span class="crm-choice-icon"><i class="fa-solid fa-cubes"></i></span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">Modular</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">Custom build — needs a site visit for measurement.</span>
                    </span>
                    <span class="crm-choice-check"><i class="fa-solid fa-check"></i></span>
                </span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="project_nature" id="crm_nature_material" value="material"
                    class="peer sr-only" onchange="crmSyncModeFromNature()"
                    <?= $postedNature === 'material' ? 'checked' : '' ?>>
                <span class="crm-choice">
                    <span class="crm-choice-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">Material Only</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">Client already has the 2D — straight to quotation.</span>
                    </span>
                    <span class="crm-choice-check"><i class="fa-solid fa-check"></i></span>
                </span>
            </label>
        </div>
        <p class="crm-error" data-error-for="project_nature"></p>

        <!-- Mode: auto-set base sa Project Nature (naka-lock) -->
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mr-1"><i class="fa-solid fa-lock text-[9px] mr-1"></i>Mode</span>
            <label class="cursor-pointer">
                <input type="radio" name="inquiry_mode" id="crm_mode_sitevisit" value="site_visit"
                    class="peer sr-only" <?= $postedMode === 'site_visit' ? 'checked' : '' ?>>
                <span class="crm-mode-chip"><i class="fa-solid fa-person-walking"></i> Site Visit Needed</span>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="inquiry_mode" id="crm_mode_ready" value="ready_for_quotation"
                    class="peer sr-only" <?= $postedMode === 'ready_for_quotation' ? 'checked' : '' ?>>
                <span class="crm-mode-chip"><i class="fa-solid fa-file-invoice"></i> Ready for Quotation</span>
            </label>
            <span class="text-[11px] text-gray-400">· set automatically from Project Nature</span>
        </div>
    </section>

    <!-- Type of Project -->
    <section class="crm-section">
        <label class="crm-label" for="crm_project_type">Type of Project</label>
        <div class="crm-field">
            <i class="fa-solid fa-building crm-field-icon"></i>
            <input type="text" name="project_type" id="crm_project_type" list="crm_project_type_list"
                value="<?= htmlspecialchars($_POST['project_type'] ?? '') ?>"
                placeholder="e.g. Residential, Commercial, Renovation" class="crm-input pl-9">
            <datalist id="crm_project_type_list">
                <option value="Residential"><option value="Commercial"><option value="Renovation"><option value="Condominium">
            </datalist>
        </div>
    </section>

    <!-- Scope of Project (chips + gear/CRUD) -->
    <section class="crm-section">
        <div class="flex items-center justify-between mb-2">
            <p class="crm-section-label mb-0">Scope of Project</p>
            <button type="button" onclick="openOptionsModal('project_scope')" class="crm-gear" title="Manage scope of project options">
                <i class="fa-solid fa-gear"></i> Manage
            </button>
        </div>
        <div id="project_scope_checkboxes" class="flex flex-wrap gap-2">
            <?php foreach ($projectScopeOptions as $opt): ?>
                <label class="cursor-pointer">
                    <input type="checkbox" name="project_scope[]" value="<?= htmlspecialchars($opt['label']) ?>"
                        <?= in_array($opt['label'], $postedProjectScope) ? 'checked' : '' ?> class="peer sr-only">
                    <span class="crm-chip"><i class="fa-solid fa-check crm-chip-tick"></i><?= htmlspecialchars($opt['label']) ?></span>
                </label>
            <?php endforeach; ?>
            <?php if (empty($projectScopeOptions)): ?>
                <p class="text-xs text-gray-400">No options yet. Click Manage to add one.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Scope area / Measuring Space (chips + gear/CRUD) -->
    <section class="crm-section">
        <div class="flex items-center justify-between mb-2">
            <p class="crm-section-label mb-0">Scope Area</p>
            <button type="button" onclick="openOptionsModal('measuring_space')" class="crm-gear" title="Manage scope area options">
                <i class="fa-solid fa-gear"></i> Manage
            </button>
        </div>
        <div id="measuring_space_checkboxes" class="flex flex-wrap gap-2">
            <?php foreach ($measuringSpaceOptions as $opt): ?>
                <label class="cursor-pointer">
                    <input type="checkbox" name="measuring_space[]" value="<?= htmlspecialchars($opt['label']) ?>"
                        <?= in_array($opt['label'], $postedMeasuringSpace) ? 'checked' : '' ?> class="peer sr-only">
                    <span class="crm-chip"><i class="fa-solid fa-check crm-chip-tick"></i><?= htmlspecialchars($opt['label']) ?></span>
                </label>
            <?php endforeach; ?>
            <?php if (empty($measuringSpaceOptions)): ?>
                <p class="text-xs text-gray-400">No options yet. Click Manage to add one.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Measurement Date & Time: itinatakda mamaya sa Sales & Market List -->
    <div class="crm-note">
        <i class="fa-regular fa-calendar text-amber-700 mt-0.5"></i>
        <p>The <b>measurement date &amp; time</b> is set later from the <b>Sales &amp; Market List</b>, once Sales has confirmed the client's schedule.</p>
    </div>

    <div class="crm-actions justify-between">
        <button type="button" onclick="crmGoToStep(1)" class="crm-btn-ghost">
            <i class="fa-solid fa-arrow-left text-[11px]"></i> Back
        </button>
        <button type="button" onclick="crmGoToStep3()" class="crm-btn-primary">
            Next: Assignment <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>