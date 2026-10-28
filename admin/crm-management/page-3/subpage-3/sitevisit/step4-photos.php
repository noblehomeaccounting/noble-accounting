<?php
// sitevisit/step4-photos.php — Step 4 · Other site photos
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                            <!-- 4. Other photos -->
                            <section data-step="4" class="sv-card p-5 hidden">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">4</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Other site photos <span class="text-[11px] font-normal text-gray-400">· optional</span></h2>
                                        <p class="text-[11px] text-gray-400">Photos not tied to one space — facade, entrance, surroundings.</p>
                                    </div>
                                </div>
                                <label for="sv_photos" id="sv_photo_drop" class="sv-drop">
                                    <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-camera"></i></span>
                                    <span class="text-[12.5px] font-semibold text-gray-700">Upload site photos</span>
                                    <span class="text-[11px] text-gray-400">JPG, PNG, WEBP</span>
                                </label>
                                <input id="sv_photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                                <div id="sv_photo_preview" class="mt-2 grid grid-cols-5 sm:grid-cols-8 gap-1.5"></div>
                            </section>
