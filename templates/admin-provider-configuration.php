<?php if ($demoMode): ?>
    <section class="panel provider-config-card demo-provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">Demo Alpha</p><h3>Demo Alpha</h3></div>
            <span class="run-status success"><?= e($translator->get('admin.configuration.configured')) ?></span>
        </div>
        <p><?= e($translator->get('admin.demo.provider_local_hint')) ?></p>
    </section>

    <section class="panel provider-config-card demo-provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">Demo Beta</p><h3>Demo Beta</h3></div>
            <span class="run-status success"><?= e($translator->get('admin.configuration.configured')) ?></span>
        </div>
        <p><?= e($translator->get('admin.demo.provider_local_hint')) ?></p>
    </section>
<?php endif; ?>

<section class="panel configuration-heading">
    <p class="eyebrow"><?= e($translator->get('admin.configuration.provider_credentials_eyebrow')) ?></p>
    <h2><?= e($translator->get('admin.configuration.provider_credentials_title')) ?></h2>
    <p><?= e($translator->get('admin.configuration.provider_credentials_intro')) ?></p>
    <?php if (!$localConfigurationWritable): ?>
        <div class="notice error"><?= e($translator->get('admin.configuration.local_config_not_writable')) ?></div>
    <?php endif; ?>
</section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">Chaturbate</p><h3>Chaturbate</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['chaturbate']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['chaturbate']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.chaturbate_wm')) ?><input name="chaturbate_wm" maxlength="120" value="<?= e($providerConfigurationValues['chaturbate_wm']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.player_mode_label')) ?>
                <select name="provider_player_mode[chaturbate]">
                    <option value="stream_only" <?= ($operationalValues['provider_player_modes']['chaturbate'] ?? 'stream_only') === 'stream_only' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.stream_only')) ?></option>
                    <option value="full_embed" <?= ($operationalValues['provider_player_modes']['chaturbate'] ?? 'stream_only') === 'full_embed' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.full_embed')) ?></option>
                </select>
            </label>
            <label><?= e($translator->get('admin.configuration.chaturbate_postback_salt')) ?><input type="password" name="chaturbate_postback_validation_salt" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['chaturbate_postback_secret_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
        </div>
        <div class="provider-config-subsection">
            <strong><?= e($translator->get('admin.configuration.postback_settings')) ?></strong>
            <label class="checkbox-field"><input type="checkbox" name="chaturbate_postback_enabled" value="1" <?= $operationalValues['chaturbate_postback_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.postback_enabled')) ?></span></label>
            <label><?= e($translator->get('admin.configuration.track')) ?><input name="chaturbate_postback_track" maxlength="80" value="<?= e($operationalValues['chaturbate_postback_track']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.postback_endpoint')) ?><input readonly value="<?= e($postbackEndpoint . '?provider=chaturbate') ?>"></label>
        </div>
        <label class="checkbox-field"><input type="checkbox" name="clear_chaturbate_postback_validation_salt" value="1"> <span><?= e($translator->get('admin.configuration.clear_secret')) ?></span></label>
        <small><?= e($translator->get('admin.configuration.provider_player_mode_hint')) ?></small>
        <small><?= e($translator->get('admin.configuration.secret_leave_blank')) ?></small>
    </section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">BongaCams</p><h3>BongaCams</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['bongacams']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['bongacams']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.bongacams_campaign_id')) ?><input type="number" name="bongacams_campaign_id" min="0" value="<?= e($providerConfigurationValues['bongacams_campaign_id']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.bongacams_client_ip')) ?><input name="bongacams_client_ip" maxlength="45" value="<?= e($providerConfigurationValues['bongacams_client_ip']) ?>" placeholder="<?= e($translator->get('admin.configuration.optional')) ?>"></label>
            <label><?= e($translator->get('admin.configuration.player_mode_label')) ?>
                <select name="provider_player_mode[bongacams]">
                    <option value="stream_only" <?= ($operationalValues['provider_player_modes']['bongacams'] ?? 'stream_only') === 'stream_only' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.stream_only')) ?></option>
                    <option value="full_embed" <?= ($operationalValues['provider_player_modes']['bongacams'] ?? 'stream_only') === 'full_embed' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.full_embed')) ?></option>
                </select>
            </label>
        </div>
        <label class="checkbox-field"><input type="checkbox" name="bongacams_detect_public_ip" value="1" <?= $operationalValues['bongacams_detect_public_ip'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.bongacams_detect_ip')) ?></span></label>
        <small><?= e($translator->get('admin.configuration.provider_player_mode_hint')) ?></small>
        <small><?= e($translator->get('admin.configuration.bongacams_client_ip_hint')) ?></small>
    </section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">CAM4</p><h3>CAM4</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['cam4']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['cam4']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.cam4_affiliate_id')) ?><input type="number" name="cam4_affiliate_id" min="0" value="<?= e($providerConfigurationValues['cam4_affiliate_id']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.cam4_tune_network_id')) ?><input name="cam4_tune_network_id" maxlength="100" value="<?= e($providerConfigurationValues['cam4_tune_network_id']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.cam4_tune_api_key')) ?><input type="password" name="cam4_tune_api_key" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['cam4_tune_api_key_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
        </div>
        <label class="checkbox-field"><input type="checkbox" name="clear_cam4_tune_api_key" value="1"> <span><?= e($translator->get('admin.configuration.clear_secret')) ?></span></label>
        <small><?= e($translator->get('admin.configuration.cam4_tune_hint')) ?></small>
    </section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">LiveJasmin</p><h3>LiveJasmin</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['livejasmin']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['livejasmin']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.livejasmin_ps_id')) ?><input name="livejasmin_ps_id" maxlength="120" value="<?= e($providerConfigurationValues['livejasmin_ps_id']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.livejasmin_access_key')) ?><input type="password" name="livejasmin_access_key" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['livejasmin_access_key_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
            <label><?= e($translator->get('admin.configuration.player_mode_label')) ?>
                <select name="provider_player_mode[livejasmin]">
                    <option value="stream_only" <?= ($operationalValues['provider_player_modes']['livejasmin'] ?? 'stream_only') === 'stream_only' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.stream_only')) ?></option>
                    <option value="full_embed" <?= ($operationalValues['provider_player_modes']['livejasmin'] ?? 'stream_only') === 'full_embed' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.player_mode.full_embed')) ?></option>
                </select>
            </label>
            <label><?= e($translator->get('admin.configuration.livejasmin_postback_secret')) ?><input type="password" name="livejasmin_postback_secret" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['livejasmin_postback_secret_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
        </div>
        <fieldset>
            <legend><?= e($translator->get('admin.configuration.livejasmin_categories')) ?></legend>
            <div class="checkbox-grid">
                <?php foreach (LiveCamForge\Core\OperationalSettings::liveJasminCategories() as $category): ?>
                    <label class="checkbox-field"><input type="checkbox" name="livejasmin_categories[]" value="<?= e($category) ?>" <?= in_array($category, $operationalValues['livejasmin_categories'], true) ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.livejasmin_category.' . $category)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <small><?= e($translator->get('admin.configuration.livejasmin_categories_hint')) ?></small>
        </fieldset>
        <div class="provider-config-subsection">
            <strong><?= e($translator->get('admin.configuration.postback_settings')) ?></strong>
            <label class="checkbox-field"><input type="checkbox" name="livejasmin_postback_enabled" value="1" <?= $operationalValues['livejasmin_postback_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.postback_enabled')) ?></span></label>
            <div class="field-grid">
                <label><?= e($translator->get('admin.configuration.track')) ?><input name="livejasmin_postback_track" maxlength="80" value="<?= e($operationalValues['livejasmin_postback_track']) ?>"></label>
                <label><?= e($translator->get('admin.configuration.currency')) ?><input name="livejasmin_postback_currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?= e($operationalValues['livejasmin_postback_currency']) ?>"></label>
                <label><?= e($translator->get('admin.configuration.postback_endpoint')) ?><input readonly value="<?= e($postbackEndpoint . '?provider=livejasmin') ?>"></label>
            </div>
            <label class="checkbox-field"><input type="checkbox" name="livejasmin_postback_accept_signups" value="1" <?= $operationalValues['livejasmin_postback_accept_signups'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.accept_signups')) ?></span></label>
        </div>
        <small><?= e($translator->get('admin.configuration.provider_player_mode_hint')) ?></small>
        <div class="secret-clear-row">
            <label class="checkbox-field"><input type="checkbox" name="clear_livejasmin_access_key" value="1"> <span><?= e($translator->get('admin.configuration.clear_access_key')) ?></span></label>
            <label class="checkbox-field"><input type="checkbox" name="clear_livejasmin_postback_secret" value="1"> <span><?= e($translator->get('admin.configuration.clear_postback_secret')) ?></span></label>
        </div>
        <small><?= e($translator->get('admin.configuration.secret_leave_blank')) ?></small>
    </section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">Stripchat</p><h3>Stripchat</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['stripchat']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['stripchat']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.stripchat_user_id')) ?><input name="stripchat_user_id" maxlength="200" value="<?= e($providerConfigurationValues['stripchat_user_id']) ?>"></label>
            <label><?= e($translator->get('admin.configuration.stripchat_api_key')) ?><input type="password" name="stripchat_api_key" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['stripchat_api_key_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
            <label><?= e($translator->get('admin.configuration.stripchat_autoplay')) ?>
                <select name="stripchat_autoplay">
                    <option value="all" <?= ($operationalValues['stripchat_autoplay'] ?? 'all') === 'all' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.stripchat_autoplay.all')) ?></option>
                    <option value="playButton" <?= ($operationalValues['stripchat_autoplay'] ?? 'all') === 'playButton' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.stripchat_autoplay.playButton')) ?></option>
                    <option value="notAtAll" <?= ($operationalValues['stripchat_autoplay'] ?? 'all') === 'notAtAll' ? 'selected' : '' ?>><?= e($translator->get('admin.configuration.stripchat_autoplay.notAtAll')) ?></option>
                </select>
            </label>
            <label><?= e($translator->get('admin.configuration.stripchat_postback_secret')) ?><input type="password" name="stripchat_postback_secret" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['stripchat_postback_secret_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
        </div>
        <div class="provider-config-subsection">
            <strong><?= e($translator->get('admin.configuration.postback_settings')) ?></strong>
            <label class="checkbox-field"><input type="checkbox" name="stripchat_postback_enabled" value="1" <?= $operationalValues['stripchat_postback_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.postback_enabled')) ?></span></label>
            <div class="field-grid">
                <label><?= e($translator->get('admin.configuration.currency')) ?><input name="stripchat_postback_currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?= e($operationalValues['stripchat_postback_currency']) ?>"></label>
                <label><?= e($translator->get('admin.configuration.postback_endpoint')) ?><input readonly value="<?= e($postbackEndpoint . '?provider=stripchat') ?>"></label>
            </div>
        </div>
        <div class="secret-clear-row">
            <label class="checkbox-field"><input type="checkbox" name="clear_stripchat_api_key" value="1"> <span><?= e($translator->get('admin.configuration.clear_api_key')) ?></span></label>
            <label class="checkbox-field"><input type="checkbox" name="clear_stripchat_postback_secret" value="1"> <span><?= e($translator->get('admin.configuration.clear_postback_secret')) ?></span></label>
        </div>
        <small><?= e($translator->get('admin.configuration.stripchat_autoplay_hint')) ?></small>
        <small><?= e($translator->get('admin.configuration.secret_leave_blank')) ?></small>
    </section>

    <section class="panel provider-config-card">
        <div class="provider-config-heading">
            <div><p class="eyebrow">CrakRevenue</p><h3>CrakRevenue</h3></div>
            <span class="run-status <?= !empty($providerCredentialStatus['crakrevenue_mfc']) ? 'success' : 'failed' ?>"><?= e($translator->get(!empty($providerCredentialStatus['crakrevenue_mfc']) ? 'admin.configuration.configured' : 'admin.configuration.need_configuration')) ?></span>
        </div>
        <p><?= e($translator->get('admin.configuration.crakrevenue_shared_hint')) ?></p>
        <div class="field-grid">
            <label><?= e($translator->get('admin.configuration.crakrevenue_api_key')) ?><input type="password" name="crakrevenue_api_key" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['crakrevenue_api_key_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
            <label><?= e($translator->get('admin.configuration.crakrevenue_token')) ?><input type="password" name="crakrevenue_token" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['crakrevenue_token_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
            <label><?= e($translator->get('admin.configuration.crakrevenue_postback_secret')) ?><input type="password" name="crakrevenue_postback_secret" autocomplete="new-password" placeholder="<?= e($providerConfigurationValues['crakrevenue_postback_secret_set'] ? $translator->get('admin.configuration.secret_stored') : '') ?>"></label>
        </div>
        <div class="secret-clear-row">
            <label class="checkbox-field"><input type="checkbox" name="clear_crakrevenue_api_key" value="1"> <span><?= e($translator->get('admin.configuration.clear_api_key')) ?></span></label>
            <label class="checkbox-field"><input type="checkbox" name="clear_crakrevenue_token" value="1"> <span><?= e($translator->get('admin.configuration.clear_token')) ?></span></label>
            <label class="checkbox-field"><input type="checkbox" name="clear_crakrevenue_postback_secret" value="1"> <span><?= e($translator->get('admin.configuration.clear_postback_secret')) ?></span></label>
        </div>
        <div class="provider-config-subsection">
            <strong><?= e($translator->get('admin.configuration.postback_settings')) ?></strong>
            <label class="checkbox-field"><input type="checkbox" name="crakrevenue_postback_enabled" value="1" <?= $operationalValues['crakrevenue_postback_enabled'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.configuration.postback_enabled')) ?></span></label>
            <p><?= e($translator->get('admin.configuration.crakrevenue_postback_hint')) ?></p>
            <?php $crakPostbackNames = array_values(array_filter($providerNames, static fn (string $name): bool => str_starts_with($name, 'crakrevenue_'))); ?>
            <?php if ($crakPostbackNames !== []): ?>
                <div class="postback-provider-list">
                    <?php foreach ($crakPostbackNames as $crakPostbackName): ?>
                        <div class="postback-endpoint"><strong><?= e($providerLabels[$crakPostbackName] ?? $availableProviderLabels[$crakPostbackName] ?? $crakPostbackName) ?></strong><code><?= e($postbackEndpoint . '?provider=' . rawurlencode($crakPostbackName)) ?></code></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <small><?= e($translator->get('admin.configuration.crakrevenue_postback_no_sources')) ?></small>
            <?php endif; ?>
        </div>
        <div class="provider-config-subsection">
            <div class="provider-config-heading compact">
                <div><strong><?= e($translator->get('admin.configuration.crakrevenue_source_verification')) ?></strong></div>
                <?php if ($crakRevenueCheckedAt !== null): ?><small><?= e($translator->get('admin.configuration.crakrevenue_checked_at', ['date' => $crakRevenueCheckedAt])) ?></small><?php endif; ?>
            </div>
            <p><?= e($translator->get('admin.configuration.crakrevenue_test_optional')) ?></p>
            <div class="postback-provider-list crak-source-status-list">
                <?php foreach ($crakRevenueBrandLabels as $brand => $label):
                    $status = $crakRevenueBrandStatuses[$brand];
                    $statusClass = $status === 'authorized' ? 'success' : ($status === 'not_authorized' ? 'failed' : 'running');
                ?>
                    <div class="postback-endpoint"><strong><?= e($label) ?></strong><span class="run-status <?= e($statusClass) ?>"><?= e($translator->get('admin.configuration.connection_status.' . $status)) ?></span></div>
                <?php endforeach; ?>
            </div>
            <button class="button secondary" type="submit" name="action" value="test_crakrevenue_access" <?= empty($providerCredentialStatus['crakrevenue_mfc']) ? 'disabled' : '' ?>><?= e($translator->get('admin.configuration.crakrevenue_test_button')) ?></button>
        </div>
    </section>

