<?php
//step2.php
$postedMode = $_POST['inquiry_mode'] ?? '';
$postedNature = $_POST['project_nature'] ?? '';
?>
<div class="crm-step-panel" id="crm-step-2">
    <p class="text-xs text-gray-400 mb-4">What the client needs and when to measure.</p>

    <div class="space-y-5">

        <!-- Project Nature: dictates the Mode below -->
        <div>
            <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                Project Nature <span class="text-red-500">*</span>
            </label>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden h-[38px]">
                <label class="cursor-pointer">
                    <input type="radio" name="project_nature" id="crm_nature_modular" value="modular"
                        class="peer sr-only" onchange="crmSyncModeFromNature()"
                        <?= $postedNature === 'modular' ? 'checked' : '' ?>>
                    <span
                        class="flex items-center h-[36px] px-4 text-xs text-gray-600 peer-checked:bg-amber-700 peer-checked:text-white transition-colors">
                        Modular
                    </span>
                </label>
                <label class="cursor-pointer border-l border-gray-300">
                    <input type="radio" name="project_nature" id="crm_nature_material" value="material"
                        class="peer sr-only" onchange="crmSyncModeFromNature()"
                        <?= $postedNature === 'material' ? 'checked' : '' ?>>
                    <span
                        class="flex items-center h-[36px] px-4 text-xs text-gray-600 peer-checked:bg-amber-700 peer-checked:text-white transition-colors">
                        Material Only
                    </span>
                </label>
            </div>
            <p class="text-[11px] text-gray-400 mt-1.5">
                Modular = Site Visit Needed. Material Only = Ready for Quotation lang, walang site visit.
            </p>
        </div>

        <!-- Mode: Site Visit Needed vs Ready for Quotation (auto-set base sa Project Nature) -->
        <div>
            <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                Mode <span class="text-gray-400 normal-case">(auto-set base sa Project Nature)</span>
            </label>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden h-[38px]">
                <label class="cursor-pointer">
                    <input type="radio" name="inquiry_mode" id="crm_mode_sitevisit" value="site_visit"
                        class="peer sr-only" <?= $postedMode === 'site_visit' ? 'checked' : '' ?>>
                    <span
                        class="flex items-center h-[36px] px-4 text-xs text-gray-600 peer-checked:bg-amber-700 peer-checked:text-white transition-colors">
                        Site Visit Needed
                    </span>
                </label>
                <label class="cursor-pointer border-l border-gray-300">
                    <input type="radio" name="inquiry_mode" id="crm_mode_ready" value="ready_for_quotation"
                        class="peer sr-only" <?= $postedMode === 'ready_for_quotation' ? 'checked' : '' ?>>
                    <span
                        class="flex items-center h-[36px] px-4 text-xs text-gray-600 peer-checked:bg-amber-700 peer-checked:text-white transition-colors">
                        Ready for Quotation (the client has provided the 2D)
                    </span>
                </label>
            </div>
            <p class="text-[11px] text-gray-400 mt-1.5">
                Awtomatikong naka-lock ito base sa Project Nature na pinili sa itaas.
            </p>
        </div>

        <!-- Type of Project -->
        <div>
            <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                Type of Project
            </label>
            <input type="text" name="project_type" id="crm_project_type"
                value="<?= htmlspecialchars($_POST['project_type'] ?? '') ?>"
                placeholder="e.g. Residential, Commercial, Renovation"
                class="w-full px-3 py-2.5 text-sm text-gray-800 bg-white border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-600 focus:border-amber-600 transition">
        </div>

        <!-- Scope of Project (checkbox + gear/CRUD) -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500">
                    Scope of Project
                </label>
                <button type="button" onclick="openOptionsModal('project_scope')"
                    class="text-gray-400 hover:text-amber-700 transition" title="Manage scope of project options">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div id="project_scope_checkboxes"
                class="flex flex-wrap gap-3 px-3 py-2.5 bg-white border border-gray-300 rounded-md min-h-[44px]">
                <?php foreach ($projectScopeOptions as $opt): ?>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="project_scope[]" value="<?= htmlspecialchars($opt['label']) ?>"
                            <?= in_array($opt['label'], $postedProjectScope) ? 'checked' : '' ?>
                            class="rounded border-gray-300 text-amber-700 focus:ring-amber-600">
                        <?= htmlspecialchars($opt['label']) ?>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($projectScopeOptions)): ?>
                    <p class="text-xs text-gray-400">No options yet. Click the gear icon to add one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Measuring Space (checkbox + gear/CRUD) -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500">
                    Scope area
                </label>
                <button type="button" onclick="openOptionsModal('measuring_space')"
                    class="text-gray-400 hover:text-amber-700 transition" title="Manage measuring space options">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div id="measuring_space_checkboxes"
                class="flex flex-wrap gap-3 px-3 py-2.5 bg-white border border-gray-300 rounded-md min-h-[44px]">
                <?php foreach ($measuringSpaceOptions as $opt): ?>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="measuring_space[]" value="<?= htmlspecialchars($opt['label']) ?>"
                            <?= in_array($opt['label'], $postedMeasuringSpace) ? 'checked' : '' ?>
                            class="rounded border-gray-300 text-amber-700 focus:ring-amber-600">
                        <?= htmlspecialchars($opt['label']) ?>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($measuringSpaceOptions)): ?>
                    <p class="text-xs text-gray-400">No options yet. Click the gear icon to add one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Desired Measurement Date & Time: removed from this form on purpose.
             Sales must contact the client first to confirm their available
             schedule; the actual date/time (and Designer Assign) is set later
             from the Sales & Market List (crmlist.php -> crmlistajax.php
             "assign" action), once confirmed with the client. -->
        <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5">
            <p class="text-xs text-gray-500">
                Measurement Date &amp; Time will be set later, once Sales has confirmed the client's available
                schedule — from the <strong>Sales &amp; Market List</strong>.
            </p>
        </div>

    </div>

    <div class="flex justify-between mt-6">
        <button type="button" onclick="crmGoToStep(1)"
            class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2.5 rounded-md border border-gray-300 hover:border-gray-400 transition">
             Back
        </button>
        <button type="button" onclick="crmGoToStep3()"
            class="bg-gray-900 hover:bg-gray-800 text-white font-medium px-6 py-2.5 rounded-md text-sm uppercase tracking-wide transition">
            Next: Assignment
        </button>
    </div>
</div>