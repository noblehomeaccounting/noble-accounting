<?php
//step1.php
// Tailwind-only. Ang $crmInputCls / $crmSelectCls ay galing sa crm-main.php (may fallback dito).
$crmInputCls = $crmInputCls ?? 'w-full h-11 px-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-xl outline-none transition placeholder:text-gray-400 focus:border-amber-600 focus:ring-2 focus:ring-amber-100';
$crmSelectCls = $crmSelectCls ?? $crmInputCls . ' appearance-none pr-9 cursor-pointer disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed';
?>
<div class="crm-step-panel" id="crm-step-1">
    <div class="flex items-center gap-3 mb-5">
        <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0"><i class="fa-solid fa-user"></i></span>
        <div>
            <h2 class="text-base font-bold text-gray-900 leading-tight">Client Information</h2>
            <p class="text-xs text-gray-400 mt-0.5">Basic client details and project address.</p>
        </div>
    </div>

    <!-- Client -->
    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 mb-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 mb-3">Client</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="crm_client_name">Client Name <span class="text-red-500">*</span></label>
                <div class="relative">
                    <i class="fa-regular fa-user absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-300 pointer-events-none"></i>
                    <input type="text" name="client_name" id="crm_client_name"
                        value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>" placeholder="Juan Dela Cruz"
                        class="<?= $crmInputCls ?> pl-9" autocomplete="off">
                </div>
                <p class="crm-error hidden mt-1.5 text-[11px] text-red-600" data-error-for="crm_client_name"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="crm_contact_number">Contact Number <span class="text-red-500">*</span></label>
                <div class="relative">
                    <i class="fa-solid fa-phone absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-300 pointer-events-none"></i>
                    <input type="text" name="contact_number" id="crm_contact_number"
                        value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX"
                        inputmode="numeric" pattern="[0-9]*" maxlength="11"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); crmContactCount();"
                        class="<?= $crmInputCls ?> pl-9 pr-14 font-mono tracking-wide">
                    <span id="crm_contact_count" class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-300">0/11</span>
                </div>
                <p class="crm-error hidden mt-1.5 text-[11px] text-red-600" data-error-for="crm_contact_number"></p>
            </div>
        </div>
    </section>

    <!-- Address -->
    <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 mb-3">Project Address</p>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="crm_house_street">House No. / Street <span class="text-red-500">*</span></label>
            <div class="relative">
                <i class="fa-solid fa-house absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-300 pointer-events-none"></i>
                <input type="text" name="house_street" id="crm_house_street" placeholder="e.g. Unit 2B, 123 Rizal Street"
                    class="<?= $crmInputCls ?> pl-9">
            </div>
            <p class="crm-error hidden mt-1.5 text-[11px] text-red-600" data-error-for="crm_house_street"></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <?php foreach ([
                ['crm_region', 'Region', '— Select Region —', false, false],
                ['crm_province', 'Province', '— Select Province —', true, false],
                ['crm_city', 'City / Municipality', '— Select City / Municipality —', true, true],
                ['crm_barangay', 'Barangay', '— Select Barangay —', true, true],
            ] as [$id, $label, $ph, $disabled, $req]): ?>
                <div <?= $id === 'crm_province' ? 'id="crm_province_wrapper"' : '' ?>>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="<?= $id ?>"><?= $label ?><?= $req ? ' <span class="text-red-500">*</span>' : '' ?></label>
                    <div class="relative">
                        <select id="<?= $id ?>" <?= $disabled ? 'disabled' : '' ?> class="<?= $crmSelectCls ?>">
                            <option value=""><?= $ph ?></option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"></i>
                    </div>
                    <?php if ($req): ?><p class="crm-error hidden mt-1.5 text-[11px] text-red-600" data-error-for="<?= $id ?>"></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- live preview ng buong address -->
        <div class="mt-4 flex items-start gap-2.5 rounded-xl bg-amber-50/50 border border-dashed border-amber-200 px-3.5 py-2.5">
            <i class="fa-solid fa-location-dot text-amber-700 mt-0.5 text-sm"></i>
            <p id="crm_address_preview" class="text-xs text-gray-400 leading-relaxed">The full address will appear here as you fill it in.</p>
        </div>

        <!-- Hidden fields: nakukuha mula sa selected labels ng region/province/city/barangay -->
        <input type="hidden" name="region_name" id="crm_region_name">
        <input type="hidden" name="province_name" id="crm_province_name">
        <input type="hidden" name="city_name" id="crm_city_name">
        <input type="hidden" name="barangay_name" id="crm_barangay_name">
        <input type="hidden" name="postal_code" id="crm_postal_code">
    </section>

    <div class="flex items-center justify-end gap-2 mt-6 pt-5 border-t border-gray-100">
        <button type="button" onclick="crmGoToStep2()"
            class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-gray-900 hover:bg-black text-white text-sm font-semibold">
            Next: Project Details <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>