<?php
$landingLocale = $translator->locale();
$landingFallbackLocale = (string) $config->get('fallback_locale', 'en');
$landingTitle = static function (array $landing) use ($landingLocale, $landingFallbackLocale): string {
    $content = is_array($landing['content'] ?? null) ? $landing['content'] : [];
    $firstContent = reset($content);
    $localized = $content[$landingLocale]
        ?? $content[$landingFallbackLocale]
        ?? $content['en']
        ?? (is_array($firstContent) ? $firstContent : []);
    return trim((string) ($localized['title'] ?? '')) ?: (string) $landing['slug'];
};
$recruitmentValues = is_array($operationalValues['recruitment'] ?? null) ? $operationalValues['recruitment'] : [];
$recruitmentProviderValues = is_array($recruitmentValues['providers'] ?? null) ? $recruitmentValues['providers'] : [];
$webmasterRecruitmentValues = is_array($operationalValues['webmaster_recruitment'] ?? null) ? $operationalValues['webmaster_recruitment'] : [];
$localizedFieldValue = static function (mixed $value, string $locale, string $currentLocale): string {
    if (is_array($value)) {
        return trim((string) ($value[$locale] ?? ''));
    }
    return $locale === $currentLocale ? trim((string) $value) : '';
};
$localizedFaqItems = static function (mixed $faq, string $locale): array {
    if (!is_array($faq)) {
        return [];
    }
    if (isset($faq[$locale]) && is_array($faq[$locale])) {
        return array_values(array_filter($faq[$locale], 'is_array'));
    }
    $items = [];
    foreach ($faq as $item) {
        if (!is_array($item)) {
            continue;
        }
        $question = $item['question'] ?? '';
        $answer = $item['answer'] ?? '';
        $items[] = [
            'question' => is_array($question) ? trim((string) ($question[$locale] ?? '')) : '',
            'answer' => is_array($answer) ? trim((string) ($answer[$locale] ?? '')) : '',
        ];
    }
    return $items;
};
$translationComplete = static function (array $values, array $fields): bool {
    foreach ($fields as $field) {
        if (trim((string) ($values[$field] ?? '')) !== '') {
            return true;
        }
    }
    return false;
};
$landingFallbackName = (string) ($landingLanguages[$landingFallbackLocale]['name'] ?? strtoupper($landingFallbackLocale));
?>
<section class="landing-manager-heading panel">
    <div>
        <p class="eyebrow"><?= e($translator->get('admin.landings.eyebrow')) ?></p>
        <h2><?= e($translator->get('admin.landings.title')) ?></h2>
        <p><?= e($translator->get('admin.landings.intro')) ?></p>
    </div>
    <a class="button" href="?section=landings&amp;edit=new"><?= e($translator->get('admin.landings.add')) ?></a>
</section>

<div class="landing-manager-layout">
    <section class="panel landing-list-panel">
        <h2><?= e($translator->get('admin.landings.configured')) ?></h2>
        <div class="landing-list">
            <?php foreach ($landingRecords as $slug => $landing): ?>
                <article class="landing-list-item<?= $landingEdit && $landingEdit['slug'] === $slug ? ' selected' : '' ?>" data-edit-url="?section=landings&amp;edit=<?= e(rawurlencode($slug)) ?>">
                    <div>
                        <div class="landing-badges">
                            <span class="run-status <?= $landing['enabled'] ? 'success' : 'failed' ?>"><?= e($landing['enabled'] ? $translator->get('admin.landings.enabled') : $translator->get('admin.landings.disabled')) ?></span>
                            <span class="landing-type<?= $landing['is_standard'] ? ' standard' : '' ?>"><?= e($landing['is_standard'] ? $translator->get('admin.landings.standard') : $translator->get('admin.landings.custom')) ?></span>
                            <?php if ($landing['index']): ?><span class="landing-type indexable"><?= e($translator->get('admin.landings.indexable')) ?></span><?php endif; ?>
                        </div>
                        <strong><?= e($landingTitle($landing)) ?></strong>
                        <code>/cams/<?= e($slug) ?>/</code>
                        <small><?= e($translator->get('admin.landings.matches', ['count' => $landingCounts[$slug] ?? 0])) ?></small>
                    </div>
                    <div class="landing-list-actions">
                        <?php if ($landing['enabled']): ?><a href="../cams/<?= e(rawurlencode($slug)) ?>/" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="landing-list-separator"><span><?= e($translator->get('admin.landings.special_pages')) ?></span></div>
            <article class="landing-list-item<?= $recruitmentEdit ? ' selected' : '' ?>" data-edit-url="?section=landings&amp;edit=recruitment">
                <div>
                    <div class="landing-badges">
                        <span class="run-status <?= !empty($recruitmentValues['enabled']) ? 'success' : 'failed' ?>"><?= e(!empty($recruitmentValues['enabled']) ? $translator->get('admin.landings.enabled') : $translator->get('admin.landings.disabled')) ?></span>
                        <span class="landing-type"><?= e($translator->get('admin.landings.special')) ?></span>
                        <?php if (!empty($recruitmentValues['index'])): ?><span class="landing-type indexable"><?= e($translator->get('admin.landings.indexable')) ?></span><?php endif; ?>
                    </div>
                    <strong><?= e($translator->get('admin.configuration.recruitment')) ?></strong>
                    <code>/become-a-model/</code>
                </div>
                <div class="landing-list-actions">
                    <a href="?section=landings&amp;edit=recruitment&amp;preview=recruitment" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a>
                </div>
            </article>
            <article class="landing-list-item<?= $webmasterRecruitmentEdit ? ' selected' : '' ?>" data-edit-url="?section=landings&amp;edit=webmaster-recruitment">
                <div>
                    <div class="landing-badges">
                        <span class="run-status <?= !empty($webmasterRecruitmentValues['enabled']) ? 'success' : 'failed' ?>"><?= e(!empty($webmasterRecruitmentValues['enabled']) ? $translator->get('admin.landings.enabled') : $translator->get('admin.landings.disabled')) ?></span>
                        <span class="landing-type"><?= e($translator->get('admin.landings.special')) ?></span>
                        <?php if (!empty($webmasterRecruitmentValues['index'])): ?><span class="landing-type indexable"><?= e($translator->get('admin.landings.indexable')) ?></span><?php endif; ?>
                    </div>
                    <strong><?= e($translator->get('admin.configuration.webmaster_recruitment')) ?></strong>
                    <code>/for-webmasters/</code>
                </div>
                <div class="landing-list-actions">
                    <a href="?section=landings&amp;edit=webmaster-recruitment&amp;preview=webmaster-recruitment" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a>
                </div>
            </article>
        </div>
    </section>

    <section class="panel landing-editor-panel">
        <?php if ($recruitmentEdit): ?>
            <form method="post" class="landing-editor-form recruitment-editor-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save_recruitment">
                <input type="hidden" name="return_section" value="landings">
                <div class="landing-editor-title">
                    <div><p class="eyebrow"><?= e($translator->get('admin.landings.special')) ?></p><h2><?= e($translator->get('admin.configuration.recruitment')) ?></h2></div>
                    <a href="?section=landings&amp;edit=recruitment&amp;preview=recruitment" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a>
                </div>
                <fieldset>
                    <legend><?= e($translator->get('admin.landings.settings')) ?></legend>
                    <label class="checkbox-field"><input type="checkbox" name="recruitment_enabled" value="1" <?= !empty($recruitmentValues['enabled']) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.recruitment_enabled')) ?></span></label>
                    <label class="checkbox-field"><input type="checkbox" name="recruitment_index" value="1" <?= !empty($recruitmentValues['index']) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.recruitment_index')) ?></span></label>
                </fieldset>
                <fieldset>
                    <legend><?= e($translator->get('admin.i18n.localized_content')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.i18n.fallback_notice', ['language' => $landingFallbackName])) ?></p>
                    <div class="localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language):
                                $pageLocaleValues = [
                                    'eyebrow' => $localizedFieldValue($recruitmentValues['eyebrow'] ?? [], (string) $locale, $landingLocale),
                                    'seo_title' => $localizedFieldValue($recruitmentValues['seo_title'] ?? ($recruitmentValues['title'] ?? []), (string) $locale, $landingLocale),
                                    'heading' => $localizedFieldValue($recruitmentValues['heading'] ?? ($recruitmentValues['title'] ?? []), (string) $locale, $landingLocale),
                                    'description' => $localizedFieldValue($recruitmentValues['description'] ?? [], (string) $locale, $landingLocale),
                                    'intro' => $localizedFieldValue($recruitmentValues['intro'] ?? [], (string) $locale, $landingLocale),
                                ];
                                $pageComplete = $translationComplete($pageLocaleValues, ['eyebrow', 'seo_title', 'heading', 'description', 'intro']);
                            ?>
                                <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?> <span><?= $pageComplete ? '✓' : '—' ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $eyebrowValue = $localizedFieldValue($recruitmentValues['eyebrow'] ?? [], (string) $locale, $landingLocale);
                            $seoTitleValue = $localizedFieldValue($recruitmentValues['seo_title'] ?? ($recruitmentValues['title'] ?? []), (string) $locale, $landingLocale);
                            $headingValue = $localizedFieldValue($recruitmentValues['heading'] ?? ($recruitmentValues['title'] ?? []), (string) $locale, $landingLocale);
                            $metaDescriptionValue = $localizedFieldValue($recruitmentValues['description'] ?? [], (string) $locale, $landingLocale);
                            $pageIntroValue = $localizedFieldValue($recruitmentValues['intro'] ?? [], (string) $locale, $landingLocale);
                        ?>
                            <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.landings.eyebrow_field')) ?><input name="recruitment_eyebrow[<?= e($locale) ?>]" maxlength="100" value="<?= e($eyebrowValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.seo_title')) ?><input name="recruitment_seo_title[<?= e($locale) ?>]" maxlength="160" value="<?= e($seoTitleValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.heading')) ?><input name="recruitment_heading[<?= e($locale) ?>]" maxlength="160" value="<?= e($headingValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.meta_description')) ?><textarea name="recruitment_meta_description[<?= e($locale) ?>]" maxlength="320" rows="2"><?= e($metaDescriptionValue) ?></textarea></label>
                                <label><?= e($translator->get('admin.configuration.recruitment_page_intro')) ?><textarea name="recruitment_page_intro[<?= e($locale) ?>]" maxlength="1500" rows="4"><?= e($pageIntroValue) ?></textarea></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="recruitment-provider-section">
                    <legend><?= e($translator->get('admin.landings.provider_section')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.landings.provider_section_hint')) ?></p>
                <?php foreach ($availableProviderNames as $name):
                    $entry = is_array($recruitmentProviderValues[$name] ?? null) ? $recruitmentProviderValues[$name] : [];
                    $demoRecruitmentProvider = $demoMode && \LiveCamForge\Core\DemoMode::isDemoProvider($name);
                    $demoLockedRealProvider = $demoMode && !$demoRecruitmentProvider;
                    $providerEnabled = $demoRecruitmentProvider ? true : (!$demoLockedRealProvider && !empty($entry['enabled']));
                    $providerRecruitmentUrl = $demoRecruitmentProvider ? \LiveCamForge\Core\DemoMode::modelRecruitmentUrl() : ($entry['url'] ?? '');
                ?>
                    <details class="recruitment-provider-fieldset" <?= $providerEnabled ? 'open' : '' ?>>
                        <summary><strong><?= e($availableProviderLabels[$name]) ?></strong><span class="run-status <?= $providerEnabled ? 'success' : 'failed' ?>"><?= e($providerEnabled ? $translator->get('admin.landings.enabled') : $translator->get('admin.landings.disabled')) ?></span></summary>
                        <div class="recruitment-provider-body">
                            <label class="checkbox-field"><input type="checkbox" name="recruitment_provider_enabled[<?= e($name) ?>]" value="1" <?= $providerEnabled ? 'checked' : '' ?> <?= $demoMode ? 'disabled' : '' ?>> <span><?= e($translator->get('admin.configuration.recruitment_provider_enabled')) ?></span></label>
                            <label>URL HTTPS<input type="url" name="recruitment_url[<?= e($name) ?>]" maxlength="2000" value="<?= e($providerRecruitmentUrl) ?>" <?= $demoMode ? 'readonly' : '' ?>></label>
                            <?php if ($demoRecruitmentProvider): ?><small><?= e($translator->get('admin.demo.fixed_model_cta')) ?></small><?php elseif ($demoLockedRealProvider): ?><small><?= e($translator->get('admin.demo.real_recruitment_locked')) ?></small><?php endif; ?>
                            <div class="localized-editor compact" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                                <strong class="localized-editor-title"><?= e($translator->get('admin.configuration.recruitment_provider_content')) ?></strong>
                                <div class="localized-language-tabs" role="tablist">
                                    <?php foreach ($landingLanguages as $locale => $language):
                                        $localizedProvider = [
                                            'title' => $localizedFieldValue($entry['title'] ?? [], (string) $locale, $landingLocale),
                                            'description' => $localizedFieldValue($entry['description'] ?? [], (string) $locale, $landingLocale),
                                        ];
                                        $providerComplete = $translationComplete($localizedProvider, ['title', 'description']);
                                    ?>
                                        <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?> <span><?= $providerComplete ? '✓' : '—' ?></span></button>
                                    <?php endforeach; ?>
                                </div>
                                <?php foreach ($landingLanguages as $locale => $language):
                                    $providerTitle = $localizedFieldValue($entry['title'] ?? [], (string) $locale, $landingLocale);
                                    if ($providerTitle === '' && $locale === $landingLocale) { $providerTitle = $availableProviderPublicLabels[$name]; }
                                    $providerDescription = $localizedFieldValue($entry['description'] ?? [], (string) $locale, $landingLocale);
                                ?>
                                    <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                        <label><?= e($translator->get('admin.configuration.recruitment_title')) ?><input name="recruitment_title[<?= e($name) ?>][<?= e($locale) ?>]" maxlength="100" value="<?= e($providerTitle) ?>"></label>
                                        <label><?= e($translator->get('admin.configuration.recruitment_description')) ?><textarea name="recruitment_description[<?= e($name) ?>][<?= e($locale) ?>]" maxlength="500" rows="3"><?= e($providerDescription) ?></textarea></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
                </fieldset>

                <fieldset>
                    <legend><?= e($translator->get('admin.landings.general_content')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.landings.recruitment_body_hint')) ?></p>
                    <div class="localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language): ?>
                                <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $bodyValue = $localizedFieldValue($recruitmentValues['body'] ?? [], (string) $locale, $landingLocale);
                            $faqItems = $localizedFaqItems($recruitmentValues['faq'] ?? [], (string) $locale);
                        ?>
                            <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.landings.general_body')) ?><textarea name="recruitment_body[<?= e($locale) ?>]" maxlength="30000" rows="10"><?= e($bodyValue) ?></textarea><small><?= e($translator->get('admin.landings.markdown_hint')) ?></small></label>
                                <details class="faq-editor">
                                    <summary><?= e($translator->get('admin.landings.faq')) ?></summary>
                                    <?php for ($faqIndex = 0; $faqIndex < 5; $faqIndex++): $faq = $faqItems[$faqIndex] ?? []; ?>
                                        <div class="faq-editor-row">
                                            <label><?= e($translator->get('admin.landings.faq_question', ['number' => $faqIndex + 1])) ?><input name="recruitment_faq_question[<?= e($locale) ?>][]" maxlength="300" value="<?= e($faq['question'] ?? '') ?>"></label>
                                            <label><?= e($translator->get('admin.landings.faq_answer')) ?><textarea name="recruitment_faq_answer[<?= e($locale) ?>][]" rows="2" maxlength="2000"><?= e($faq['answer'] ?? '') ?></textarea></label>
                                        </div>
                                    <?php endfor; ?>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button class="button" type="submit"><?= e($translator->get('admin.configuration.save')) ?></button>
            </form>
        <?php elseif ($webmasterRecruitmentEdit): ?>
            <form method="post" class="landing-editor-form recruitment-editor-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save_webmaster_recruitment">
                <input type="hidden" name="return_section" value="landings">
                <div class="landing-editor-title">
                    <div><p class="eyebrow"><?= e($translator->get('admin.landings.special')) ?></p><h2><?= e($translator->get('admin.configuration.webmaster_recruitment')) ?></h2></div>
                    <a href="?section=landings&amp;edit=webmaster-recruitment&amp;preview=webmaster-recruitment" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a>
                </div>
                <fieldset>
                    <legend><?= e($translator->get('admin.landings.settings')) ?></legend>
                    <label class="checkbox-field"><input type="checkbox" name="webmaster_recruitment_enabled" value="1" <?= !empty($webmasterRecruitmentValues['enabled']) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.webmaster_recruitment_enabled')) ?></span></label>
                    <label class="checkbox-field"><input type="checkbox" name="webmaster_recruitment_index" value="1" <?= !empty($webmasterRecruitmentValues['index']) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.webmaster_recruitment_index')) ?></span></label>
                </fieldset>
                <fieldset>
                    <legend><?= e($translator->get('admin.i18n.localized_content')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.i18n.fallback_notice', ['language' => $landingFallbackName])) ?></p>
                    <div class="localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language):
                                $pageLocaleValues = [
                                    'eyebrow' => $localizedFieldValue($webmasterRecruitmentValues['eyebrow'] ?? [], (string) $locale, $landingLocale),
                                    'seo_title' => $localizedFieldValue($webmasterRecruitmentValues['seo_title'] ?? [], (string) $locale, $landingLocale),
                                    'heading' => $localizedFieldValue($webmasterRecruitmentValues['heading'] ?? [], (string) $locale, $landingLocale),
                                    'description' => $localizedFieldValue($webmasterRecruitmentValues['description'] ?? [], (string) $locale, $landingLocale),
                                    'intro' => $localizedFieldValue($webmasterRecruitmentValues['intro'] ?? [], (string) $locale, $landingLocale),
                                ];
                                $pageComplete = $translationComplete($pageLocaleValues, ['eyebrow', 'seo_title', 'heading', 'description', 'intro']);
                            ?>
                                <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?> <span><?= $pageComplete ? '✓' : '—' ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $eyebrowValue = $localizedFieldValue($webmasterRecruitmentValues['eyebrow'] ?? [], (string) $locale, $landingLocale);
                            $seoTitleValue = $localizedFieldValue($webmasterRecruitmentValues['seo_title'] ?? [], (string) $locale, $landingLocale);
                            $headingValue = $localizedFieldValue($webmasterRecruitmentValues['heading'] ?? [], (string) $locale, $landingLocale);
                            $metaDescriptionValue = $localizedFieldValue($webmasterRecruitmentValues['description'] ?? [], (string) $locale, $landingLocale);
                            $pageIntroValue = $localizedFieldValue($webmasterRecruitmentValues['intro'] ?? [], (string) $locale, $landingLocale);
                            $ctaLabelValue = $localizedFieldValue($webmasterRecruitmentValues['cta_label'] ?? [], (string) $locale, $landingLocale);
                        ?>
                            <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.landings.eyebrow_field')) ?><input name="webmaster_recruitment_eyebrow[<?= e($locale) ?>]" maxlength="100" value="<?= e($eyebrowValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.seo_title')) ?><input name="webmaster_recruitment_seo_title[<?= e($locale) ?>]" maxlength="160" value="<?= e($seoTitleValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.heading')) ?><input name="webmaster_recruitment_heading[<?= e($locale) ?>]" maxlength="160" value="<?= e($headingValue) ?>"></label>
                                <label><?= e($translator->get('admin.landings.meta_description')) ?><textarea name="webmaster_recruitment_meta_description[<?= e($locale) ?>]" maxlength="320" rows="2"><?= e($metaDescriptionValue) ?></textarea></label>
                                <label><?= e($translator->get('admin.configuration.webmaster_recruitment_intro')) ?><textarea name="webmaster_recruitment_intro[<?= e($locale) ?>]" maxlength="1500" rows="4"><?= e($pageIntroValue) ?></textarea></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="webmaster-cta-fieldset">
                    <legend><?= e($translator->get('admin.landings.call_to_action')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.landings.webmaster_cta_editor_hint')) ?></p>
                    <?php $webmasterDemoCtaUrl = $demoMode ? \LiveCamForge\Core\DemoMode::webmasterRecruitmentUrl() : ($webmasterRecruitmentValues['cta_url'] ?? ''); ?>
                    <label><?= e($translator->get('admin.configuration.webmaster_recruitment_cta_url')) ?><input type="url" name="webmaster_recruitment_cta_url" maxlength="2000" placeholder="https://livecamforge.com/" value="<?= e($webmasterDemoCtaUrl) ?>" <?= $demoMode ? 'readonly' : '' ?>><small><?= e($demoMode ? $translator->get('admin.demo.fixed_webmaster_cta') : $translator->get('admin.configuration.webmaster_recruitment_cta_hint')) ?></small></label>
                    <div class="localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language): ?>
                                <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $ctaLabelValue = $localizedFieldValue($webmasterRecruitmentValues['cta_label'] ?? [], (string) $locale, $landingLocale);
                        ?>
                            <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.configuration.webmaster_recruitment_cta_label')) ?><input name="webmaster_recruitment_cta_label[<?= e($locale) ?>]" maxlength="160" value="<?= e($ctaLabelValue) ?>"></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend><?= e($translator->get('admin.landings.general_content')) ?></legend>
                    <p class="muted"><?= e($translator->get('admin.landings.webmaster_body_hint')) ?></p>
                    <div class="localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language): ?>
                                <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $bodyValue = $localizedFieldValue($webmasterRecruitmentValues['body'] ?? [], (string) $locale, $landingLocale);
                            $faqItems = $localizedFaqItems($webmasterRecruitmentValues['faq'] ?? [], (string) $locale);
                        ?>
                            <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.landings.general_body')) ?><textarea name="webmaster_recruitment_body[<?= e($locale) ?>]" maxlength="30000" rows="10"><?= e($bodyValue) ?></textarea><small><?= e($translator->get('admin.landings.markdown_hint')) ?></small></label>
                                <details class="faq-editor">
                                    <summary><?= e($translator->get('admin.landings.faq')) ?></summary>
                                    <?php for ($faqIndex = 0; $faqIndex < 5; $faqIndex++): $faq = $faqItems[$faqIndex] ?? []; ?>
                                        <div class="faq-editor-row">
                                            <label><?= e($translator->get('admin.landings.faq_question', ['number' => $faqIndex + 1])) ?><input name="webmaster_recruitment_faq_question[<?= e($locale) ?>][]" maxlength="300" value="<?= e($faq['question'] ?? '') ?>"></label>
                                            <label><?= e($translator->get('admin.landings.faq_answer')) ?><textarea name="webmaster_recruitment_faq_answer[<?= e($locale) ?>][]" rows="2" maxlength="2000"><?= e($faq['answer'] ?? '') ?></textarea></label>
                                        </div>
                                    <?php endfor; ?>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button class="button" type="submit"><?= e($translator->get('admin.configuration.save')) ?></button>
            </form>
        <?php elseif ($landingEdit === null): ?>
            <h2><?= e($translator->get('admin.landings.select_title')) ?></h2>
            <p class="muted"><?= e($translator->get('admin.landings.select_intro')) ?></p>
        <?php else: ?>
            <?php
            $isNewLanding = $landingEdit['slug'] === '';
            $landingFilters = is_array($landingEdit['filters'] ?? null) ? $landingEdit['filters'] : [];
            $landingContent = is_array($landingEdit['content'] ?? null) ? $landingEdit['content'] : [];
            ?>
            <form method="post" class="landing-editor-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save_landing">
                <input type="hidden" name="return_section" value="landings">
                <input type="hidden" name="original_slug" value="<?= e($landingEdit['slug']) ?>">

                <div class="landing-editor-title">
                    <div>
                        <p class="eyebrow"><?= e($landingEdit['is_standard'] ? $translator->get('admin.landings.standard') : $translator->get('admin.landings.custom')) ?></p>
                        <h2><?= e($isNewLanding ? $translator->get('admin.landings.create_title') : $landingTitle($landingEdit)) ?></h2>
                    </div>
                    <?php if (!$isNewLanding && $landingEdit['enabled']): ?><a href="../cams/<?= e(rawurlencode((string) $landingEdit['slug'])) ?>/" target="_blank" rel="noopener"><?= e($translator->get('admin.landings.preview')) ?></a><?php endif; ?>
                </div>

                <fieldset>
                    <legend><?= e($translator->get('admin.landings.settings')) ?></legend>
                    <label><?= e($translator->get('admin.landings.slug')) ?>
                        <input name="slug" value="<?= e($landingEdit['slug']) ?>" maxlength="61" pattern="[a-z0-9][a-z0-9-]{0,60}" <?= !$isNewLanding ? 'readonly' : 'required' ?>>
                        <small><?= e($translator->get('admin.landings.slug_hint')) ?></small>
                    </label>
                    <div class="field-grid">
                        <label><?= e($translator->get('admin.landings.minimum_results')) ?><input name="minimum_results" type="number" min="0" max="500" value="<?= e($landingEdit['minimum_results']) ?>"></label>
                        <label><?= e($translator->get('admin.landings.sort_order')) ?><input name="sort_order" type="number" min="0" max="999" value="<?= e($landingEdit['sort_order']) ?>"></label>
                    </div>
                    <label class="checkbox-field"><input type="checkbox" name="enabled" value="1" <?= $landingEdit['enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.landings.enable')) ?></span></label>
                    <label class="checkbox-field"><input type="checkbox" name="indexable" value="1" <?= $landingEdit['index'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.landings.allow_index')) ?></span></label>
                    <label class="checkbox-field"><input type="checkbox" name="show_in_navigation" value="1" <?= $landingEdit['show_in_navigation'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.landings.show_navigation')) ?></span></label>
                    <small><?= e($translator->get('admin.landings.index_hint')) ?></small>
                </fieldset>

                <fieldset>
                    <legend><?= e($translator->get('admin.landings.filters')) ?></legend>
                    <div class="field-grid">
                        <label><?= e($translator->get('filters.provider')) ?>
                            <select name="filter_provider"><option value=""><?= e($translator->get('filters.all_providers')) ?></option><?php foreach ($providerNames as $name): ?><option value="<?= e($name) ?>" <?= ($landingFilters['provider'] ?? '') === $name ? 'selected' : '' ?>><?= e($providerLabels[$name] ?? ucfirst($name)) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><?= e($translator->get('filters.gender')) ?>
                            <select name="filter_gender">
                                <option value=""><?= e($translator->get('filters.all_genders')) ?></option>
                                <?php foreach (['f' => 'women', 'm' => 'men', 't' => 'trans', 'c' => 'couples'] as $value => $key): ?><option value="<?= e($value) ?>" <?= ($landingFilters['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($translator->get('filters.' . $key)) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <?php
                        $selectedLandingCountry = strtoupper((string) ($landingFilters['country'] ?? ''));
                        $countryChoices = $landingCountryOptions;
                        if ($selectedLandingCountry !== '' && !isset($countryChoices[$selectedLandingCountry])) {
                            $countryChoices[$selectedLandingCountry] = LiveCamForge\Core\PerformerCountry::label($selectedLandingCountry, $translator->locale());
                        }
                        ?>
                        <label><?= e($translator->get('filters.country')) ?>
                            <select name="filter_country"><option value=""><?= e($translator->get('filters.all_countries')) ?></option><?php foreach ($countryChoices as $code => $label): ?><option value="<?= e($code) ?>" <?= $selectedLandingCountry === $code ? 'selected' : '' ?>><?= e($label) ?> (<?= e($code) ?>)</option><?php endforeach; ?></select>
                        </label>
                        <label><?= e($translator->get('filters.age')) ?>
                            <select name="filter_age"><option value=""><?= e($translator->get('filters.all_ages')) ?></option><?php foreach (['18-20','21-25','26-30','31-35','36-40','41-plus'] as $value): ?><option value="<?= e($value) ?>" <?= ($landingFilters['age'] ?? '') === $value ? 'selected' : '' ?>><?= e($translator->get('filters.age.' . $value)) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><?= e($translator->get('filters.room_status')) ?>
                            <select name="filter_room_status"><option value=""><?= e($translator->get('filters.all_statuses')) ?></option><?php foreach (['public','private','group','away','unknown'] as $value): ?><option value="<?= e($value) ?>" <?= ($landingFilters['room_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($translator->get('room_status.' . $value)) ?></option><?php endforeach; ?></select>
                        </label>
                        <label><?= e($translator->get('filters.tag')) ?><input name="filter_tag" value="<?= e($landingFilters['tag'] ?? '') ?>" maxlength="80" pattern="#?[A-Za-z0-9_/-]+"></label>
                        <label><?= e($translator->get('filters.sort')) ?>
                            <select name="filter_sort"><?php foreach (['popular','provider_popular','newest','youngest','oldest','name'] as $value): ?><option value="<?= e($value) ?>" <?= ($landingFilters['sort'] ?? 'popular') === $value ? 'selected' : '' ?>><?= e($translator->get('filters.sort.' . $value)) ?></option><?php endforeach; ?></select>
                        </label>
                    </div>
                    <div class="new-landing-filter">
                        <label class="checkbox-field"><input type="checkbox" name="filter_new_only" value="1" <?= !empty($landingFilters['new_only']) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.landings.new_only')) ?></span></label>
                        <label><?= e($translator->get('admin.landings.new_days')) ?><input name="filter_new_days" type="number" min="1" max="90" value="<?= e($landingFilters['new_days'] ?? 7) ?>"></label>
                    </div>
                </fieldset>

                <div class="localized-editor landing-localized-editor" data-localized-editor data-default-locale="<?= e($landingLocale) ?>">
                    <div class="localized-language-tabs" role="tablist">
                        <?php foreach ($landingLanguages as $locale => $language):
                            $localized = is_array($landingContent[$locale] ?? null) ? $landingContent[$locale] : [];
                            $complete = $translationComplete($localized, ['title', 'description', 'intro']);
                        ?>
                            <button type="button" class="localized-language-tab<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?> <span><?= $complete ? '✓' : '—' ?></span></button>
                        <?php endforeach; ?>
                    </div>
                    <small class="localized-fallback-note"><?= e($translator->get('admin.i18n.fallback_notice', ['language' => $landingFallbackName])) ?></small>
                    <?php foreach ($landingLanguages as $locale => $language): ?>
                        <?php $localized = is_array($landingContent[$locale] ?? null) ? $landingContent[$locale] : []; $faqItems = is_array($localized['faq'] ?? null) ? $localized['faq'] : []; ?>
                        <div class="localized-language-panel<?= $locale === $landingLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                            <label><?= e($translator->get('admin.landings.seo_title')) ?><input name="title_<?= e($locale) ?>" maxlength="160" value="<?= e($localized['title'] ?? '') ?>" data-seo-title-input><small class="seo-character-count" data-seo-title-count data-count-label="<?= e($translator->get('admin.landings.characters')) ?>"></small></label>
                            <label><?= e($translator->get('admin.landings.page_heading')) ?><input name="heading_<?= e($locale) ?>" maxlength="180" value="<?= e($localized['heading'] ?? ($localized['title'] ?? '')) ?>"></label>
                            <label><?= e($translator->get('admin.landings.meta_description')) ?><textarea name="description_<?= e($locale) ?>" rows="2" maxlength="320" data-seo-description-input><?= e($localized['description'] ?? '') ?></textarea><small class="seo-character-count" data-seo-description-count data-count-label="<?= e($translator->get('admin.landings.characters')) ?>"></small></label>
                            <div class="landing-search-preview" data-seo-preview>
                                <small><?= e($translator->get('admin.landings.search_preview')) ?></small>
                                <strong data-seo-preview-title><?= e($localized['title'] ?? '') ?></strong>
                                <span>/cams/<?= e($landingEdit['slug'] ?: 'your-landing') ?>/</span>
                                <p data-seo-preview-description><?= e($localized['description'] ?? '') ?></p>
                            </div>
                            <label><?= e($translator->get('admin.landings.eyebrow_field')) ?><input name="eyebrow_<?= e($locale) ?>" maxlength="100" value="<?= e($localized['eyebrow'] ?? '') ?>"></label>
                            <label><?= e($translator->get('admin.landings.introduction')) ?><textarea name="intro_<?= e($locale) ?>" rows="4" maxlength="3000"><?= e($localized['intro'] ?? '') ?></textarea></label>
                            <label><?= e($translator->get('admin.landings.body')) ?><textarea name="body_<?= e($locale) ?>" rows="12" maxlength="30000" placeholder="## Heading&#10;&#10;Useful paragraph with **bold text**."><?= e($localized['body'] ?? '') ?></textarea><small><?= e($translator->get('admin.landings.markdown_hint')) ?></small></label>
                            <details class="faq-editor">
                                <summary><?= e($translator->get('admin.landings.faq')) ?></summary>
                                <?php for ($faqIndex = 0; $faqIndex < 5; $faqIndex++): $faq = $faqItems[$faqIndex] ?? []; ?>
                                    <div class="faq-editor-row">
                                        <label><?= e($translator->get('admin.landings.faq_question', ['number' => $faqIndex + 1])) ?><input name="faq_question_<?= e($locale) ?>[]" maxlength="300" value="<?= e($faq['question'] ?? '') ?>"></label>
                                        <label><?= e($translator->get('admin.landings.faq_answer')) ?><textarea name="faq_answer_<?= e($locale) ?>[]" rows="2" maxlength="2000"><?= e($faq['answer'] ?? '') ?></textarea></label>
                                    </div>
                                <?php endfor; ?>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="landing-save-actions">
                    <button class="button" type="submit"><?= e($translator->get('admin.landings.save')) ?></button>
                    <details class="landing-placeholders"><summary><?= e($translator->get('admin.landings.placeholders_title')) ?></summary><small><?= e($translator->get('admin.landings.placeholders')) ?></small></details>
                </div>
            </form>

            <?php if (!$isNewLanding): ?>
                <div class="landing-danger-zone">
                    <?php if ($landingEdit['is_standard']): ?>
                        <form method="post" onsubmit="return confirm(<?= e(json_encode($translator->get('admin.landings.reset_confirm'))) ?>)"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="reset_landing"><input type="hidden" name="return_section" value="landings"><input type="hidden" name="slug" value="<?= e($landingEdit['slug']) ?>"><button class="button secondary" type="submit"><?= e($translator->get('admin.landings.reset')) ?></button></form>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('<?= e($translator->get('admin.landings.delete_confirm')) ?>')"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete_landing"><input type="hidden" name="return_section" value="landings"><input type="hidden" name="slug" value="<?= e($landingEdit['slug']) ?>"><button class="button secondary" type="submit"><?= e($translator->get('admin.landings.delete')) ?></button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
