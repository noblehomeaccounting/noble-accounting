<?php
//step1.php
?>
<div class="crm-step-panel active" id="crm-step-1">
    <div class="crm-step-head">
        <span class="crm-step-icon"><i class="fa-solid fa-user"></i></span>
        <div>
            <h2 class="crm-step-title">Client Information</h2>
            <p class="crm-step-sub">Basic client details and project address.</p>
        </div>
    </div>

    <!-- Client -->
    <section class="crm-section">
        <p class="crm-section-label">Client</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="crm-label" for="crm_client_name">Client Name <span class="text-red-500">*</span></label>
                <div class="crm-field">
                    <i class="fa-regular fa-user crm-field-icon"></i>
                    <input type="text" name="client_name" id="crm_client_name"
                        value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>" placeholder="Juan Dela Cruz"
                        class="crm-input pl-9" autocomplete="off">
                </div>
                <p class="crm-error" data-error-for="crm_client_name"></p>
            </div>

            <div>
                <label class="crm-label" for="crm_contact_number">Contact Number <span class="text-red-500">*</span></label>
                <div class="crm-field">
                    <i class="fa-solid fa-phone crm-field-icon"></i>
                    <input type="text" name="contact_number" id="crm_contact_number"
                        value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX"
                        inputmode="numeric" pattern="[0-9]*" maxlength="11"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); document.getElementById('crm_contact_count').textContent = this.value.length + '/11';"
                        class="crm-input pl-9 pr-12 font-mono tracking-wide">
                    <span id="crm_contact_count" class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-gray-300">0/11</span>
                </div>
                <p class="crm-error" data-error-for="crm_contact_number"></p>
            </div>
        </div>
    </section>

    <!-- Address -->
    <section class="crm-section">
        <p class="crm-section-label">Project Address</p>

        <div>
            <label class="crm-label" for="crm_house_street">House No. / Street <span class="text-red-500">*</span></label>
            <div class="crm-field">
                <i class="fa-solid fa-house crm-field-icon"></i>
                <input type="text" name="house_street" id="crm_house_street" placeholder="e.g. Unit 2B, 123 Rizal Street"
                    class="crm-input pl-9">
            </div>
            <p class="crm-error" data-error-for="crm_house_street"></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="crm-label" for="crm_region">Region</label>
                <select id="crm_region" class="crm-input crm-select">
                    <option value="">— Select Region —</option>
                </select>
            </div>

            <div id="crm_province_wrapper">
                <label class="crm-label" for="crm_province">Province</label>
                <select id="crm_province" disabled class="crm-input crm-select">
                    <option value="">— Select Province —</option>
                </select>
            </div>

            <div>
                <label class="crm-label" for="crm_city">City / Municipality <span class="text-red-500">*</span></label>
                <select id="crm_city" disabled class="crm-input crm-select">
                    <option value="">— Select City / Municipality —</option>
                </select>
                <p class="crm-error" data-error-for="crm_city"></p>
            </div>

            <div>
                <label class="crm-label" for="crm_barangay">Barangay <span class="text-red-500">*</span></label>
                <select id="crm_barangay" disabled class="crm-input crm-select">
                    <option value="">— Select Barangay —</option>
                </select>
                <p class="crm-error" data-error-for="crm_barangay"></p>
            </div>
        </div>

        <!-- live preview ng buong address -->
        <div class="mt-4 flex items-start gap-2.5 rounded-lg bg-white border border-dashed border-[#DCD5C3] px-3.5 py-2.5">
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

    <div class="crm-actions justify-end">
        <button type="button" onclick="crmGoToStep2()" class="crm-btn-primary">
            Next: Project Details <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </button>
    </div>
</div>