<?php
// sitevisit/step2-notes.php — Step 2 · Site notes
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.
?>
                            <!-- 2. Notes -->
                            <section data-step="2" class="sv-card p-5 hidden">
                                <div class="flex items-center gap-2.5 mb-4">
                                    <span class="sv-num">2</span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-gray-900 leading-tight">Site notes</h2>
                                        <p class="text-[11px] text-gray-400">What you saw and what the client asked for.</p>
                                    </div>
                                </div>
                                <div class="grid md:grid-cols-3 gap-4">
                                    <?php foreach ([
                                        ['site_conditions', 'Site conditions / notes', 'Terrain, access, utilities, hazards...'],
                                        ['client_requirements', 'Client requirements', 'Preferences, budget notes, must-haves...'],
                                        ['existing_structure', 'Existing structure', 'Age, condition, materials, structures on site...'],
                                    ] as [$nm, $lbl, $ph]): ?>
                                        <div>
                                            <label class="sv-label" for="sv_<?= $nm ?>"><?= $lbl ?></label>
                                            <textarea id="sv_<?= $nm ?>" name="<?= $nm ?>" rows="5" class="sv-field resize-y min-h-[110px]" placeholder="<?= htmlspecialchars($ph) ?>"><?= $svOldVal($nm) ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
