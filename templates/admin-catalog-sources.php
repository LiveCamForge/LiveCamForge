<?php
$standaloneProviderNames = array_values(array_diff($availableProviderNames, $routedProviderNames));
$sourceStatusClass = static fn (string $status): string => $status === 'authorized' || $status === 'configured'
    ? 'success'
    : ($status === 'not_authorized' || $status === 'not_configured' ? 'failed' : 'running');
$sourceStatusLabel = static fn (string $status): string => $translator->get(
    'admin.configuration.connection_status.' . ($status === 'authorized' ? 'configured' : $status)
);
?>
<section class="panel catalog-settings-panel">
    <h2><?= e($translator->get('admin.catalog_settings.sources_title')) ?></h2>
    <p class="catalog-settings-intro"><?= e($translator->get('admin.catalog_settings.sources_intro')) ?></p>

        <fieldset>
            <legend><?= e($translator->get('admin.configuration.providers')) ?></legend>
            <p><?= e($translator->get('admin.configuration.providers_intro')) ?></p>
            <div class="postback-provider-list">
                <?php foreach ($standaloneProviderNames as $name): ?>
                    <label class="postback-endpoint checkbox-field">
                        <?php $demoCatalogProvider = $demoMode && \LiveCamForge\Core\DemoMode::isDemoProvider($name); ?>
                        <?php if ($demoCatalogProvider): ?><input type="hidden" name="enabled_providers[]" value="<?= e($name) ?>"><?php endif; ?>
                        <input type="checkbox" name="enabled_providers[]" value="<?= e($name) ?>" <?= ($demoCatalogProvider || (!$demoMode && in_array($name, $operationalValues['enabled_providers'], true))) ? 'checked' : '' ?> <?= $demoMode ? 'disabled' : '' ?>>
                        <span><strong><?= e($availableProviderLabels[$name]) ?></strong><small class="run-status <?= e($sourceStatusClass($providerConnectionStatus[$name])) ?>"><?= e($sourceStatusLabel($providerConnectionStatus[$name])) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend><?= e($translator->get('admin.configuration.affiliate_routes')) ?></legend>
            <p><?= e($translator->get('admin.configuration.affiliate_routes_intro')) ?></p>
            <div class="field-grid">
                <?php foreach ($affiliateRouteGroups as $routeGroup):
                    $selectedSource = '';
                    foreach ($routeGroup['options'] as $candidate) {
                        if (in_array($candidate, $operationalValues['enabled_providers'], true)) {
                            $selectedSource = $candidate;
                            break;
                        }
                    }
                ?>
                    <label><?= e($routeGroup['label']) ?>
                        <select name="enabled_providers[]" <?= $demoMode ? 'disabled' : '' ?>>
                            <option value=""><?= e($translator->get('admin.configuration.route_disabled')) ?></option>
                            <?php foreach ($routeGroup['options'] as $source): ?>
                                <option value="<?= e($source) ?>" <?= $selectedSource === $source ? 'selected' : '' ?>><?= e($availableProviderLabels[$source]) ?> — <?= e($sourceStatusLabel($providerConnectionStatus[$source])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend><?= e($translator->get('admin.configuration.performer_types')) ?></legend>
            <p><?= e($translator->get('admin.configuration.performer_types_intro')) ?></p>
            <div class="postback-provider-list">
                <?php foreach (['f', 'm', 't', 'c'] as $performerType): ?>
                    <label class="postback-endpoint checkbox-field">
                        <input type="checkbox" name="catalog_performer_types[]" value="<?= e($performerType) ?>" <?= in_array($performerType, $operationalValues['catalog_performer_types'], true) ? 'checked' : '' ?>>
                        <span><strong><?= e($translator->get('admin.configuration.performer_type.' . $performerType)) ?></strong></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small><?= e($translator->get('admin.configuration.performer_types_hint')) ?></small>
        </fieldset>

</section>
