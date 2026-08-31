<section class="panel configuration-heading">
    <p class="eyebrow"><?= e($translator->get('admin.configuration.eyebrow')) ?></p>
    <h2><?= e($translator->get('admin.configuration.title')) ?></h2>
    <p><?= e($translator->get('admin.configuration.intro')) ?></p>
    <nav class="admin-subtabs" aria-label="<?= e($translator->get('admin.configuration.section_nav')) ?>">
        <button type="button" class="active" data-integration-tab="providers"><?= e($translator->get('admin.configuration.section.providers')) ?></button>
        <button type="button" data-integration-tab="catalog-sync"><?= e($translator->get('admin.configuration.section.catalog_sync')) ?></button>
        <button type="button" data-integration-tab="player-media"><?= e($translator->get('admin.configuration.section.player_media')) ?></button>
        <button type="button" data-integration-tab="data-policies"><?= e($translator->get('admin.configuration.section.data_policies')) ?></button>
    </nav>
</section>

<form method="post" class="configuration-form" data-integration-tabs data-show-label="<?= e($translator->get('admin.configuration.show')) ?>" data-hide-label="<?= e($translator->get('admin.configuration.hide')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="return_section" value="configuration">
    <div id="providers" class="admin-integration-panel active" data-integration-panel="providers">
    <?php require $root . '/templates/admin-provider-configuration.php'; ?>
    </div>
    <div id="catalog-sync" class="admin-integration-panel" data-integration-panel="catalog-sync">
    <section class="panel">
        <h2><?= e($translator->get('admin.configuration.sync')) ?></h2>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.history_days')) ?><input type="number" name="sync_history_days" min="1" max="365" value="<?= e($operationalValues['sync_history_days']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.new_days')) ?><input type="number" name="catalog_new_days" min="1" max="90" value="<?= e($operationalValues['catalog_new_days']) ?>"></label>
        </div>
        <details class="advanced-settings">
            <summary><?= e($translator->get('admin.configuration.advanced')) ?></summary>
        <fieldset>
            <legend><?= e($translator->get('admin.configuration.new_strategies')) ?></legend>
            <div class="field-grid">
                <?php foreach ($availableProviderNames as $name): ?>
                    <label><?= e($availableProviderLabels[$name]) ?>
                        <select name="catalog_new_strategy[<?= e($name) ?>]">
                            <?php foreach (LiveCamForge\Core\NewnessStrategy::values() as $strategy): ?>
                                <option value="<?= e($strategy) ?>" <?= ($operationalValues['catalog_new_strategies'][$name] ?? 'automatic') === $strategy ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.new_strategy.' . $strategy)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
            </div>
            <small><?= e($translator->get('admin.configuration.new_strategies_hint')) ?></small>
        </fieldset>
        <label class="checkbox-field"><input type="checkbox" name="sync_allow_empty" value="1" <?= $operationalValues['sync_allow_empty'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.allow_empty')) ?></span></label>
        <small><?= e($translator->get('admin.configuration.allow_empty_warning')) ?></small>
        </details>
    </section>

    </div>
    <div id="player-media" class="admin-integration-panel" data-integration-panel="player-media">
    <section class="panel">
        <h2><?= e($translator->get('admin.configuration.player')) ?></h2>
        <label class="checkbox-field"><input type="checkbox" name="player_enabled" value="1" <?= $operationalValues['player_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.player_enabled')) ?></span></label>
        <label class="checkbox-field"><input type="checkbox" name="rooms_block_non_public" value="1" <?= $operationalValues['rooms_block_non_public'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.block_non_public')) ?></span></label>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.player_timeout')) ?><input type="number" name="player_load_timeout_ms" min="2000" max="60000" step="500" value="<?= e($operationalValues['player_load_timeout_ms']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.aspect_width')) ?><input type="number" name="player_aspect_ratio_width" min="1" max="100" value="<?= e($operationalValues['player_aspect_ratio_width']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.aspect_height')) ?><input type="number" name="player_aspect_ratio_height" min="1" max="100" value="<?= e($operationalValues['player_aspect_ratio_height']) ?>"></label>
        </div>
        
    </section>

    <section class="panel">
        <h2><?= e($translator->get('admin.configuration.media_seo')) ?></h2>
        <label class="checkbox-field"><input type="checkbox" name="media_proxy_enabled" value="1" <?= $operationalValues['media_proxy_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.media_proxy_enabled')) ?></span></label>
        <label class="checkbox-field"><input type="checkbox" name="seo_adult_rating" value="1" <?= $operationalValues['seo_adult_rating'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.adult_rating')) ?></span></label>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.media_ttl')) ?><input type="number" name="media_proxy_ttl_seconds" min="0" max="86400" value="<?= e($operationalValues['media_proxy_ttl_seconds']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.media_timeout')) ?><input type="number" name="media_proxy_timeout_seconds" min="2" max="30" value="<?= e($operationalValues['media_proxy_timeout_seconds']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.sitemap_max_models')) ?><input type="number" name="seo_sitemap_max_models" min="0" max="50000" value="<?= e($operationalValues['seo_sitemap_max_models']) ?>"></label>
        </div>
        <label class="wide"><?= e($translator->get('admin.configuration.base_url')) ?>
            <input type="url" name="seo_base_url" value="<?= e($providerConfigurationValues['seo_base_url']) ?>" placeholder="https://example.com/livecamforge">
            <small><?= e($translator->get('admin.configuration.base_url_hint')) ?></small>
        </label>
    </section>

    </div>
    <div id="data-policies" class="admin-integration-panel" data-integration-panel="data-policies">
    <section class="panel">
        <h2><?= e($translator->get('admin.configuration.policies')) ?></h2>
        <p><?= e($translator->get('admin.configuration.policies_warning')) ?></p>
        <div class="table-wrap"><table>
            <thead><tr><th><?= e($translator->get('admin.provider')) ?></th><th><?= e($translator->get('admin.configuration.offline_retention')) ?></th><th><?= e($translator->get('admin.configuration.retention_days')) ?></th><th><?= e($translator->get('admin.configuration.index_profiles')) ?></th><th><?= e($translator->get('admin.configuration.sitemap_profiles')) ?></th><th><?= e($translator->get('admin.configuration.cache_images')) ?></th></tr></thead>
            <tbody><?php foreach ($availableProviderNames as $name):
                $policy = $operationalValues['provider_policies'][$name];
                $directMediaOnly = !$providerCapabilities[$name]->mediaProxy;
                $retentionMaximum = $name === 'stripchat' ? 30 : 3650;
            ?><tr>
                <td><?= e($availableProviderLabels[$name]) ?></td>
                <td><input type="checkbox" name="policy_offline_retention[<?= e($name) ?>]" value="1" <?= $policy['offline_retention'] ? 'checked' : '' ?>></td>
                <td><input type="number" name="policy_offline_retention_days[<?= e($name) ?>]" min="<?= $name === 'stripchat' ? '1' : '0' ?>" max="<?= $retentionMaximum ?>" value="<?= e($policy['offline_retention_days']) ?>"></td>
                <td><input type="checkbox" name="policy_index_performer_pages[<?= e($name) ?>]" value="1" <?= $policy['index_performer_pages'] ? 'checked' : '' ?>></td>
                <td><input type="checkbox" name="policy_include_performers_in_sitemap[<?= e($name) ?>]" value="1" <?= $policy['include_performers_in_sitemap'] ? 'checked' : '' ?>></td>
                <td><input type="checkbox" name="policy_cache_images[<?= e($name) ?>]" value="1" <?= $policy['cache_images'] ? 'checked' : '' ?> <?= $directMediaOnly ? 'disabled' : '' ?> title="<?= e($directMediaOnly ? $translator->get('admin.configuration.direct_media_only') : '') ?>"></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    </section>

    </div>
    <button class="button" type="submit" name="action" value="save_integrations_all" <?= (!$localConfigurationWritable || $demoMode) ? 'disabled' : '' ?>><?= e($translator->get('admin.configuration.save')) ?></button>
</form>

<form method="post" class="panel reset-theme-form integrations-reset-form" onsubmit="return confirm(<?= e(json_encode($translator->get('admin.configuration.reset_confirm'))) ?>)">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="reset_operational_settings">
    <input type="hidden" name="return_section" value="configuration">
    <h3><?= e($translator->get('admin.configuration.reset')) ?></h3>
    <p><?= e($translator->get('admin.configuration.reset_intro')) ?></p>
    <button class="button secondary" type="submit" <?= $demoMode ? 'disabled' : '' ?>><?= e($translator->get('admin.configuration.reset_button')) ?></button>
</form>
