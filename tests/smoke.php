<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$runtimeConfig = require $root . '/app/bootstrap.php';
$config = new LiveCamForge\Core\Config(require $root . '/config/app.php');
$provider = new LiveCamForge\Providers\DemoProvider($root . '/database/demo-performers.json');
$performers = $provider->fetch();
$english = new LiveCamForge\Core\Translator($root . '/languages', 'en', 'en');
$italian = new LiveCamForge\Core\Translator($root . '/languages', 'it', 'en');
$frenchWithItalianFallback = new LiveCamForge\Core\Translator($root . '/languages', 'fr', 'it');

assert($config->get('version') === '1.0.1');
assert(is_file($root . '/app/Core/SyncPerformanceProfiler.php'));
assert(str_contains((string) file_get_contents($root . '/bin/sync.php'), '--profile'));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), "db.upsert_all"));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), 'upsertMany($performers, $this->dbBatchSize)'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'markProviderOfflineBefore'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "db.performers_upsert"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "db.geo_delete"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "db.geo_insert"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "db.existing_lookup"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "db.volatile_update"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'volatile_update_mode'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'ON DUPLICATE KEY UPDATE'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "rows_structural_unchanged"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'existingStructuralHashes'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'structuralHash'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'volatile_batches'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'structuralChangedFieldsFromRow'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'structural_lookup_mode'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "SELECT provider_id, structural_hash FROM performers"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "volatile_update_mode', 'case_update_'"));
assert(str_contains((string) file_get_contents($root . '/bin/sync.php'), "--db-batch="));
assert(str_contains((string) file_get_contents($root . '/bin/sync.php'), 'min(250, $requestedBatchSize)'));
assert(str_contains((string) file_get_contents($root . '/admin/index.php'), "innodb_buffer_pool_size"));
assert(str_contains((string) file_get_contents($root . '/admin/index.php'), "max_allowed_packet"));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), '$this->dbBatchSize'));
assert(is_file($root . '/database/migrations/025_add_structural_hash.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/025_add_structural_hash.sql'), 'ADD COLUMN structural_hash'));
assert(!str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'CREATE TEMPORARY TABLE IF NOT EXISTS lcf_sync_volatile'));
assert(!str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "DELETE FROM lcf_sync_volatile"));
assert(!str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'INNER JOIN lcf_sync_volatile AS s'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "structural_change."));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'structural_combo_'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "geo_changed_performers"));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), "media_cache_maintenance"));
assert(!str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), 'providerMediaUrls($this->provider->name())'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'INSERT INTO performers'));


$providerDevelopmentSource = (string) file_get_contents($root . '/docs/PROVIDER_DEVELOPMENT.md');
assert(str_contains($providerDevelopmentSource, 'app/Providers/ProviderInterface.php'));
assert(str_contains($providerDevelopmentSource, 'app/Providers/ProviderFactory.php'));
assert(str_contains($providerDevelopmentSource, 'app/Core/LocalConfigManager.php'));
assert(str_contains($providerDevelopmentSource, 'app/Postbacks/PostbackHandlerFactory.php'));
assert(str_contains($providerDevelopmentSource, 'app/Repositories/ConversionRepository.php'));
assert(str_contains($providerDevelopmentSource, 'app/Services/Cam4ConversionSync.php'));
assert(str_contains($providerDevelopmentSource, 'crakrevenue_examplecams'));
assert(str_contains($providerDevelopmentSource, 'CrakRevenueAdapter::providerForOfferId'));
assert(str_contains($providerDevelopmentSource, 'Definition of done'));
assert(is_file($root . '/docs/UPGRADE_1.0.0.md'));
assert(is_file($root . '/docs/UPGRADE_1.0.1.md'));
assert(is_file($root . '/LICENSE'));
assert(is_file($root . '/SECURITY.md'));
assert(is_file($root . '/recruitment-go.php'));
assert(is_file($root . '/database/migrations/026_add_conversion_sync_runs.sql'));
assert(is_file($root . '/app/Repositories/ConversionSyncRunRepository.php'));
assert(str_contains((string) file_get_contents($root . '/bin/sync-conversions.php'), 'ConversionSyncRunRepository'));
assert(str_contains((string) file_get_contents($root . '/bin/sync-conversions.php'), 'start($provider, \'cron\')'));
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), 'admin.conversions.sync_history'));
assert(str_contains((string) file_get_contents($root . '/docs/CONVERSION_TRACKING.md'), 'Conversion polling observability'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/SyncRunRepository.php'), 'interruptStaleRunning'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/ConversionSyncRunRepository.php'), 'interruptStaleRunning'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/ConversionSyncRunRepository.php'), 'DELETE FROM conversion_sync_runs'));
assert(str_contains((string) file_get_contents($root . '/bin/sync-conversions.php'), 'prune(30)'));
assert(str_contains((string) file_get_contents($root . '/docs/CRON_AND_SYNC.md'), 'memory_limit=256M'));
assert(str_contains((string) file_get_contents($root . '/docs/CRON_AND_SYNC.md'), 'sync-conversions.php cam4'));
assert(str_contains((string) file_get_contents($root . '/docs/CONVERSION_TRACKING.md'), 'older than 30 days'));
$rootHtaccessSource = (string) file_get_contents($root . '/.htaccess');
assert(str_contains($rootHtaccessSource, 'RewriteCond %{HTTPS} !=on'));
assert(str_contains($rootHtaccessSource, 'localhost|127\\.0\\.0\\.1'));
assert(str_contains($rootHtaccessSource, '[R=301,L]'));
assert(str_contains((string) file_get_contents($root . '/docs/DEPLOYMENT.md'), 'redirects non-local HTTP requests to HTTPS'));
assert(!str_contains((string) file_get_contents($root . '/docs/DEPLOYMENT.md'), '## Backups'));
assert(str_contains((string) file_get_contents($root . '/templates/model.php'), '$tagUrl((string) $tag)'));
assert(!str_contains((string) file_get_contents($root . '/templates/model.php'), "footer.environment"));
assert(!str_contains((string) file_get_contents($root . '/templates/home.php'), "footer.environment"));
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), 'admin-version-footer'));
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), 'https://livecamforge.com/'));
foreach (['home.php', 'model.php', 'recruitment.php', 'webmaster-recruitment.php'] as $publicTemplate) {
    $publicTemplateContents = (string) file_get_contents($root . '/templates/' . $publicTemplate);
    assert(!str_contains($publicTemplateContents, "site_name'] . ' ' . \$config->get('version')"));
}


$recruitmentTemplateSource = (string) file_get_contents($root . '/templates/recruitment.php');
$webmasterRecruitmentTemplateSource = (string) file_get_contents($root . '/templates/webmaster-recruitment.php');
$adminLandingsSource = (string) file_get_contents($root . '/templates/admin-landings.php');
$publicIndexSource = (string) file_get_contents($root . '/public/index.php');
assert(is_file($root . '/templates/webmaster-recruitment.php'));
assert(str_contains($rootHtaccessSource, '^for-webmasters/?$'));
assert(str_contains($publicIndexSource, "route'] ?? '') === 'webmaster-recruitment'"));
assert(str_contains($publicIndexSource, "path('recruitment-go.php')"));
assert(str_contains($adminLandingsSource, 'save_webmaster_recruitment'));
assert(str_contains($adminLandingsSource, 'webmaster_recruitment_cta_url'));
assert(str_contains($adminLandingsSource, 'admin.landings.call_to_action'));
assert(str_contains($adminLandingsSource, 'webmaster_cta_editor_hint'));
assert(str_contains($webmasterRecruitmentTemplateSource, 'preview_cta_missing_url'));
assert(str_contains($webmasterRecruitmentTemplateSource, 'webmaster-recruitment-final-cta'));
assert(str_contains($webmasterRecruitmentTemplateSource, "webmaster_recruitment.cta_heading"));
assert(str_contains((string) file_get_contents($root . '/public/assets/traffic.css'), '.webmaster-recruitment-final-cta'));
assert(str_contains($adminLandingsSource, 'recruitment_seo_title['));
assert(str_contains($adminLandingsSource, 'recruitment_faq_question['));
assert(str_contains($recruitmentTemplateSource, 'FAQPage'));
assert(str_contains($recruitmentTemplateSource, 'SafeMarkdown::render'));
assert(str_contains($adminLandingsSource, 'recruitment_eyebrow['));
assert(str_contains($adminLandingsSource, 'webmaster_recruitment_eyebrow['));
assert(str_contains($recruitmentTemplateSource, "path('recruitment-go.php')"));
assert(strpos($recruitmentTemplateSource, '<?php if ($recruitmentBodyHtml') < strpos($recruitmentTemplateSource, 'recruitment-provider-area-final'));
$recruitmentRedirectSource = file_get_contents($root . '/recruitment-go.php');
assert(is_string($recruitmentRedirectSource));
assert(str_contains($recruitmentRedirectSource, "\$entry['url']"));
assert(str_contains($recruitmentRedirectSource, "header('Location: ' . \$destination, true, 302)"));
assert(str_contains($recruitmentTemplateSource, 'recruitment-grid-count-'));
assert(str_contains($webmasterRecruitmentTemplateSource, 'target="_blank"'));
assert($config->get('recruitment.models.eyebrow.en') === 'Performer opportunities');
assert($config->get('recruitment.webmasters.eyebrow.en') === 'Webmaster resources');
assert(str_contains($webmasterRecruitmentTemplateSource, 'FAQPage'));
assert(str_contains($webmasterRecruitmentTemplateSource, 'for-webmasters/'));
assert($config->get('recruitment.webmasters.enabled') === false);
assert(trim((string) $config->get('recruitment.webmasters.cta_url', '')) === '');
assert(str_contains((string) file_get_contents($root . '/templates/sitemap.php'), 'recruitment.webmasters.index'));
assert(str_contains((string) file_get_contents($root . '/docs/ADMIN_GUIDE.md'), '/for-webmasters/'));

assert(str_contains((string) file_get_contents($root . '/public/assets/admin-ux.js'), 'enabledSourceCount'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'persisted_hash_chunked'));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), 'unset($fetchedPerformers)'));
foreach (['locks', 'cache', 'logs', 'branding'] as $storageDirectory) {
    assert(is_dir($root . '/storage/' . $storageDirectory));
}

$performerRepositorySource = (string) file_get_contents($root . '/app/Repositories/PerformerRepository.php');
assert(!str_contains($performerRepositorySource, '\'tags_json\' => "IFNULL(tags_json, $null)"'));
assert(str_contains($performerRepositorySource, '$data[\'tags_json\'] = json_encode($performer->tags, JSON_UNESCAPED_SLASHES);'));
assert(str_contains($performerRepositorySource, "FORCE INDEX (idx_online_geo_country)"));
assert(str_contains($performerRepositorySource, "count_strategy"));
assert(str_contains($performerRepositorySource, "optimizer_text"));
assert(str_contains($performerRepositorySource, "geo_catalog_text"));
assert(str_contains((string) file_get_contents($root . '/app/Core/PerformanceProfiler.php'), "count_strategy"));
$settingsRepositorySource = (string) file_get_contents($root . '/app/Repositories/SettingsRepository.php');
assert(str_contains($settingsRepositorySource, '$ownsTransaction = !$this->pdo->inTransaction();'));
assert(str_contains($settingsRepositorySource, 'if ($ownsTransaction)'));
$publicIndexSource = (string) file_get_contents($root . '/public/index.php');
assert(str_contains($publicIndexSource, "unset(\$query['page'], \$query['tag_cursor'], \$query['tag_dir']);"));
assert(str_contains($publicIndexSource, "\$query['tag'] = \$safeTag;"));
assert(str_contains($publicIndexSource, 'CatalogReturn::query($_GET)'));
assert(str_contains($publicIndexSource, 'deferred_tag_window'));
assert(is_file($root . '/app/Core/TagCursor.php'));
assert(str_contains($performerRepositorySource, 'onlineByPopularityCursor'));
assert(str_contains($publicIndexSource, 'tag_cursor_window'));
assert(str_contains($publicIndexSource, 'TagCursor::decode'));
assert(str_contains((string) file_get_contents($root . '/app/Core/CatalogReturn.php'), "'tag_cursor'"));
assert(str_contains($publicIndexSource, "page_tag_window"));
assert(str_contains($publicIndexSource, '$perPage + 1'));
assert(str_contains((string) file_get_contents($root . '/templates/home.php'), 'catalog.summary_window'));
assert(is_file($root . '/database/migrations/024_remove_performer_tag_index.sql'));
assert(!is_file($root . '/bin/rebuild-tag-index.php'));
assert(str_contains($publicIndexSource, "preg_match('~^[a-z0-9_/-]{1,80}$~', " . '$requestedTag' . ")"));
assert(str_contains((string) file_get_contents($root . '/templates/home.php'), 'pattern="#?[A-Za-z0-9_/-]+"'));

assert($config->get('crakrevenue.page_size') === 100);
assert($config->withOverrides(['locale' => 'it', 'sync' => ['history_days' => 30]])->get('locale') === 'it');
assert($config->withOverrides(['sync' => ['history_days' => 30]])->get('sync.allow_empty') === false);
assert($config->withOverrides(['sync' => ['history_days' => 30]])->get('sync.history_days') === 30);
assert($config->withOverrides(['catalog' => ['performer_types' => ['t']]])->get('catalog.performer_types') === ['t']);
assert($config->withOverrides(['livejasmin' => ['categories' => ['couple']]])->get('livejasmin.categories') === ['couple']);
assert(is_file($root . '/app/Core/OperationalSettings.php'));
$operationalSource = (string) file_get_contents($root . '/app/Core/OperationalSettings.php');
assert(str_contains($operationalSource, "foreach (['chaturbate', 'livejasmin', 'stripchat', 'crakrevenue'] as \$provider)"));
assert(str_contains($operationalSource, "'crakrevenue' => ['enabled']"));
assert(is_file($root . '/templates/admin-configuration.php'));
assert(is_file($root . '/tests/crakrevenue-diagnostic.php'));
$adminSource = (string) file_get_contents($root . '/admin/index.php');
assert(str_contains($adminSource, "str_starts_with(\$name, 'crakrevenue_')"));
assert(str_contains($adminSource, "\$config->get('crakrevenue.postback.enabled', false)"));
assert(str_contains($adminSource, "\$config->get('crakrevenue.postback.require_secret', true)"));
assert(str_contains($adminSource, "\$testMode === 'duplicate' && str_starts_with(\$testProvider, 'crakrevenue_')"));
assert(!str_contains($adminSource, "\$config->get(\$name . '.postback.enabled', false);\n            \$ready = match (\$name)"));
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), 'value="duplicate"'));
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), "conversion-details"));
foreach (['public/index.php', 'admin/index.php', 'bin/sync.php', 'postback.php'] as $runtimeEntry) {
    assert(str_contains((string) file_get_contents($root . '/' . $runtimeEntry), 'OperationalSettings'));
}
assert(str_contains((string) file_get_contents($root . '/install/index.php'), "'setting_key' => 'runtime.configuration'"));
$installerChecks = LiveCamForge\Core\InstallerPreflight::checks($root);
assert(count($installerChecks) === 5);
assert(array_column($installerChecks, 'key') === ['php', 'pdo', 'schema', 'config', 'storage']);
assert(str_contains((string) file_get_contents($root . '/install/index.php'), "name=\"csrf_token\""));
assert(str_contains((string) file_get_contents($root . '/install/index.php'), "storage/locks/install.lock"));
$installerSource = (string) file_get_contents($root . '/install/index.php');
foreach (['cam4_tune_api_key', 'livejasmin_access_key', 'stripchat_api_key', 'crakrevenue_api_key', 'crakrevenue_token'] as $installerSecretField) {
    assert(str_contains($installerSource, "'" . $installerSecretField . "' => '',"));
    assert(!str_contains($installerSource, "'" . $installerSecretField . "' => \$_POST"));
}
assert(str_contains($installerSource, "\$_POST['cam4_tune_api_key'] ?? ''"));
assert(str_contains($installerSource, 'Migrator::run($pdo'));
assert(str_contains($installerSource, 'name="providers[]"'));
assert(str_contains($installerSource, "'catalog' => ['mode' => count(\$selectedProviders) > 1 ? 'combined' : 'single']"));
assert(str_contains($installerSource, "'catalog.mode' => \$initialCatalogMode"));
assert(str_contains($installerSource, 'DateTimeZone::listIdentifiers()'));
assert(str_contains($installerSource, 'data-provider-config="crakrevenue"'));
assert(str_contains($installerSource, 'minlength="12"'));
assert(str_contains($installerSource, 'AdminPasswordPolicy::isWeak'));
assert(str_contains($installerSource, 'password-strength-fill'));
assert(str_contains($installerSource, "'seo' => ['base_url' => \$baseUrl]"));
assert(!str_contains((string) file_get_contents($root . '/templates/admin-provider-configuration.php'), 'save_provider_configuration'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-provider-configuration.php'), 'chaturbate_postback_validation_salt'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-provider-configuration.php'), 'crakrevenue_postback_secret'));
assert(str_contains((string) file_get_contents($root . '/admin/index.php'), 'saveProviderConfiguration($_POST)'));
assert(str_contains((string) file_get_contents($root . '/admin/index.php'), "save_integrations_all"));
assert($config->get('stripchat.player.autoplay') === 'all');
$providerConfigTemplate = (string) file_get_contents($root . '/templates/admin-provider-configuration.php');
assert(str_contains($providerConfigTemplate, 'name="stripchat_autoplay"'));
assert(str_contains($providerConfigTemplate, 'name="bongacams_detect_public_ip"'));
assert(str_contains($providerConfigTemplate, 'name="livejasmin_categories[]"'));
assert(str_contains($providerConfigTemplate, 'value="test_crakrevenue_access"'));
assert(str_contains($providerConfigTemplate, 'name="stripchat_postback_enabled"'));
assert(str_contains($providerConfigTemplate, '?provider=stripchat'));
assert(str_contains($providerConfigTemplate, 'admin.configuration.provider_player_mode_hint'));
$operationalTemplate = (string) file_get_contents($root . '/templates/admin-configuration.php');
assert(!str_contains($operationalTemplate, "admin.configuration.provider_options"));
assert(!str_contains($operationalTemplate, "admin.configuration.crakrevenue_test"));
assert(str_contains($operationalTemplate, 'value="save_integrations_all"'));
assert(!str_contains($operationalTemplate, 'name="locale"'));
assert(!str_contains($operationalTemplate, 'name="recruitment_enabled"'));
assert(str_contains($operationalTemplate, 'id="catalog-sync"'));
assert(str_contains($operationalTemplate, 'id="player-media"'));
assert(str_contains($operationalTemplate, 'id="data-policies"'));
assert(!str_contains($operationalTemplate, 'admin.configuration.postbacks_intro'));
$catalogTemplate = (string) file_get_contents($root . '/templates/admin.php');
assert(str_contains($catalogTemplate, 'value="save_catalog_all"'));
assert(str_contains($catalogTemplate, 'name="locale"'));
assert(str_contains($catalogTemplate, 'admin.catalog_settings.primary_provider_fallback'));
assert(str_contains($catalogTemplate, 'admin.conversions.testing_tools'));
assert(str_contains($catalogTemplate, 'data-responsive-preview-open'));
assert(str_contains($catalogTemplate, 'data-modal-preview-device="mobile"'));
assert(str_contains($operationalTemplate, 'data-integration-panel="providers"'));
assert(str_contains($operationalTemplate, 'admin.configuration.advanced'));
assert(str_contains($catalogTemplate, 'placeholder="lcf_…"'));
assert(!str_contains($catalogTemplate, 'placeholder="ce_…"'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-landings.php'), 'value="save_recruitment"'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-landings.php'), 'edit=recruitment'));
$landingAdminSource = (string) file_get_contents($root . '/templates/admin-landings.php');
assert(str_contains($landingAdminSource, 'data-localized-editor'));
assert(str_contains($landingAdminSource, 'recruitment_seo_title['));
assert(str_contains($landingAdminSource, 'recruitment_title['));
assert(str_contains($landingAdminSource, 'recruitment-provider-fieldset'));
$appearanceSource = (string) file_get_contents($root . '/app/Core/AppearanceSettings.php');
assert(str_contains($appearanceSource, 'localizedBoundedValue'));
assert(str_contains($catalogTemplate, 'hero_title_'));
assert(str_contains($catalogTemplate, 'localized-language-tabs'));
assert(str_contains((string) file_get_contents($root . '/templates/recruitment.php'), '$translator->fallbackLocale()') || str_contains((string) file_get_contents($root . '/templates/recruitment.php'), "fallback_locale"));
assert(is_file($root . '/public/assets/admin-ux.js'));
assert(!str_contains((string) file_get_contents($root . '/templates/admin-catalog-sources.php'), 'save_catalog_sources'));

$localConfigTestRoot = sys_get_temp_dir() . '/livecamforge-local-config-' . bin2hex(random_bytes(6));
mkdir($localConfigTestRoot . '/config', 0777, true);
file_put_contents($localConfigTestRoot . '/config/local.php', "<?php\nreturn " . var_export([
    'database' => ['name' => 'preserve_me', 'password' => 'db_secret'],
    'seo' => ['base_url' => 'https://old.example.test/subdir'],
    'chaturbate' => ['wm' => 'OLD', 'postback' => ['validation_salt' => 'old_salt', 'require_checksum' => true]],
    'cam4' => ['affiliate_id' => 1, 'tune' => ['network_id' => 'cam4com', 'api_key' => 'old_tune']],
], true) . ";\n");
$localManager = new LiveCamForge\Core\LocalConfigManager($localConfigTestRoot);
assert($localManager->writable() === true);
$localManager->saveProviderConfiguration([
    'seo_base_url' => 'https://new.example.test/livecamforge/',
    'chaturbate_wm' => 'NEWCODE',
    'chaturbate_postback_validation_salt' => '', // preserve write-only secret
    'bongacams_campaign_id' => '123',
    'bongacams_client_ip' => '',
    'cam4_affiliate_id' => '12345',
    'cam4_tune_network_id' => 'cam4com',
    'cam4_tune_api_key' => 'new_tune_secret',
    'livejasmin_ps_id' => '',
    'livejasmin_access_key' => '',
    'livejasmin_postback_secret' => '',
    'stripchat_user_id' => '',
    'stripchat_api_key' => '',
    'stripchat_postback_secret' => '',
    'crakrevenue_api_key' => '',
    'crakrevenue_token' => '',
    'crakrevenue_postback_secret' => '',
]);
$updatedLocal = require $localConfigTestRoot . '/config/local.php';
assert($updatedLocal['database']['name'] === 'preserve_me');
assert($updatedLocal['database']['password'] === 'db_secret');
assert($updatedLocal['seo']['base_url'] === 'https://new.example.test/livecamforge');
assert($updatedLocal['chaturbate']['wm'] === 'NEWCODE');
assert($updatedLocal['chaturbate']['postback']['validation_salt'] === 'old_salt');
assert($updatedLocal['cam4']['affiliate_id'] === 12345);
assert($updatedLocal['cam4']['tune']['api_key'] === 'new_tune_secret');
@unlink($localConfigTestRoot . '/config/local.php');
@rmdir($localConfigTestRoot . '/config');
@rmdir($localConfigTestRoot);
assert(!str_contains((string) file_get_contents($root . '/templates/partials/brand.php'), 'brand-version'));
assert(!str_contains((string) file_get_contents($root . '/templates/partials/brand.php'), '0.8'));
assert($italian->fallbackLocale() === 'en');
$availableLanguages = $english->available();
assert(isset($availableLanguages['en'], $availableLanguages['it']));
assert(LiveCamForge\Core\SafeMarkdown::render("## Useful\n\nA **safe** paragraph.") === "<h2>Useful</h2>\n<p>A <strong>safe</strong> paragraph.</p>");
assert(!str_contains(LiveCamForge\Core\SafeMarkdown::render('<script>alert(1)</script>'), '<script>'));
assert(LiveCamForge\Core\SafeMarkdown::interpolate('{site_name}: {result_count}', [
    'site_name' => 'LiveCamForge', 'result_count' => 12,
]) === 'LiveCamForge: 12');
$managedLandings = LiveCamForge\Core\TrafficLanding::enabledDefinitions([
    'custom-page' => [
        'enabled' => true,
        'index' => true,
        'show_in_navigation' => false,
        'content' => [
            'en' => ['title' => 'English title', 'description' => 'English description', 'intro' => 'English intro', 'body' => 'English body', 'faq' => []],
            'it' => ['title' => 'Titolo italiano', 'description' => 'Descrizione italiana', 'intro' => 'Introduzione italiana', 'body' => 'Corpo italiano', 'faq' => [['question' => 'Domanda?', 'answer' => 'Risposta.']]],
        ],
        'filters' => ['gender' => 'f'],
    ],
], $italian);
assert($managedLandings['custom-page']['title'] === 'Titolo italiano');
assert($managedLandings['custom-page']['body'] === 'Corpo italiano');
assert($managedLandings['custom-page']['show_in_navigation'] === false);
assert($managedLandings['custom-page']['faq'][0]['answer'] === 'Risposta.');
$fallbackLanding = LiveCamForge\Core\TrafficLanding::enabledDefinitions([
    'fallback-test' => [
        'enabled' => true,
        'content' => ['it' => ['title' => 'Titolo di fallback']],
    ],
], $frenchWithItalianFallback);
assert($fallbackLanding['fallback-test']['title'] === 'Titolo di fallback');
foreach (['chaturbate', 'bongacams', 'livejasmin', 'future-provider'] as $policyProvider) {
    $policy = LiveCamForge\Core\ProviderPolicy::for($config, $policyProvider);
    assert($policy->offlineRetention === false);
    assert($policy->offlineRetentionDays === 0);
    assert($policy->indexPerformerPages === false);
    assert($policy->includePerformersInSitemap === false);
    assert($policy->cacheImages === false);
}
$retentionPolicy = LiveCamForge\Core\ProviderPolicy::for(new LiveCamForge\Core\Config([
    'provider_policies' => [
        'default' => ['offline_retention' => false, 'cache_images' => false],
        'example' => [
            'offline_retention' => true,
            'offline_retention_days' => 30,
            'index_performer_pages' => true,
            'include_performers_in_sitemap' => true,
            'cache_images' => true,
        ],
    ],
]), 'example');
assert($retentionPolicy->offlineRetention === true);
assert($retentionPolicy->offlineRetentionDays === 30);
assert($retentionPolicy->indexPerformerPages === true);
assert($retentionPolicy->includePerformersInSitemap === true);
assert($retentionPolicy->cacheImages === true);
$stripchatPolicy = LiveCamForge\Core\ProviderPolicy::for(new LiveCamForge\Core\Config([
    'provider_policies' => [
        'default' => ['offline_retention' => false, 'offline_retention_days' => 0, 'cache_images' => true],
        'stripchat' => ['offline_retention' => false, 'offline_retention_days' => 365, 'cache_images' => true],
    ],
]), 'stripchat');
assert($stripchatPolicy->offlineRetention === true);
assert($stripchatPolicy->offlineRetentionDays === 30);
assert($stripchatPolicy->cacheImages === false);
assert(count(LiveCamForge\Core\TrafficLanding::enabled($config, $english)) === 7);
assert(LiveCamForge\Core\TrafficLanding::find($config, $english, 'new-models')['filters']['new_only'] === true);
$seoLanding = LiveCamForge\Core\TrafficLanding::find($config, $english, 'live-cams');
assert($seoLanding['heading'] === 'Live cams online now');
assert($seoLanding['body'] !== '');
assert(count($seoLanding['faq']) >= 2);
$countryLanding = LiveCamForge\Core\TrafficLanding::enabledDefinitions([
    'italy' => [
        'enabled' => true,
        'title' => ['en' => 'Italian cams'],
        'heading' => ['en' => 'Italian live cams'],
        'filters' => ['country' => 'IT', 'sort' => 'popular'],
    ],
], $english)['italy'];
assert(($countryLanding['filters']['country'] ?? '') === 'IT');
assert($countryLanding['heading'] === 'Italian live cams');
assert(str_contains((string) file_get_contents($root . '/templates/home.php'), "activeLanding['heading']"));
assert(str_contains((string) file_get_contents($root . '/templates/admin-landings.php'), 'data-seo-preview'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-landings.php'), 'heading_'));
$testSiteUrl = new LiveCamForge\Core\SiteUrl(new LiveCamForge\Core\Config(['seo' => ['base_url' => 'https://example.com/camsite']]), []);
assert($testSiteUrl->landing('new-models') === '/camsite/cams/new-models/');
assert($testSiteUrl->model('chaturbate', 'demo_user') === '/camsite/model/chaturbate/demo_user/');
assert(str_contains((string) file_get_contents($root . '/.htaccess'), 'sitemap\\.xml'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/011_add_traffic_source.sql'), 'source_page'));
assert($config->get('chaturbate.postback.enabled') === false);
assert($config->get('chaturbate.postback.require_checksum') === true);
assert($config->get('livejasmin.postback.enabled') === false);
assert($config->get('livejasmin.postback.require_secret') === true);
assert(str_contains(
    (string) file_get_contents($root . '/templates/model.php'),
    'sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"'
));
assert(str_contains(
    (string) file_get_contents($root . '/templates/provider-widget.php'),
    "element.style.setProperty('width', '100%', 'important')"
));
assert(str_contains(
    (string) file_get_contents($root . '/templates/provider-widget.php'),
    'startWidgetWhenSized'
));
assert(str_contains(
    (string) file_get_contents($root . '/templates/provider-widget.php'),
    '<div id="object_container" style="width:100%;height:100%"></div>'
));
assert(LiveCamForge\Core\AppearanceSettings::fontNames() === ['system', 'modern', 'rounded', 'serif']);
assert(array_keys(LiveCamForge\Core\AppearanceSettings::themePresets()) === ['livecamforge', 'midnight', 'neon', 'velvet', 'ocean', 'crimson', 'luxury_gold', 'clean_light']);
assert(array_keys(LiveCamForge\Core\AppearanceSettings::cardStyles()) === ['default', 'rounded', 'compact', 'minimal']);
$defaultTheme = LiveCamForge\Core\AppearanceSettings::themePresets()['livecamforge'];
assert(LiveCamForge\Core\AppearanceSettings::detectPreset($defaultTheme['colors'], $defaultTheme['font']) === 'livecamforge');
$customColors = $defaultTheme['colors'];
$customColors['primary'] = '#123456';
assert(LiveCamForge\Core\AppearanceSettings::detectPreset($customColors, $defaultTheme['font']) === 'custom');
assert(str_contains((string) file_get_contents($root . '/templates/admin.php'), 'data-theme-preset-card'));
assert(str_contains((string) file_get_contents($root . '/public/assets/appearance-preview.js'), 'syncPresetState'));
assert(LiveCamForge\Core\CatalogReturn::query([
    'q' => 'aurora',
    'provider' => 'chaturbate',
    'gender' => 'f',
    'country' => 'IT',
    'page' => '3',
]) === 'q=aurora&provider=chaturbate&gender=f&country=IT&page=3');
assert(LiveCamForge\Core\CatalogReturn::query([
    'model' => 'aurora',
    'return' => 'q=luna&gender=f&page=2&redirect=https%3A%2F%2Fevil.example',
]) === 'q=luna&gender=f&page=2');
assert(LiveCamForge\Core\CatalogReturn::query([
    'return' => 'q%5B%5D=invalid&tag=italian',
]) === 'tag=italian');
assert(LiveCamForge\Core\CatalogReturn::url(
    '/camsite/cams/live-cams/',
    'gender=f&page=3'
) === '/camsite/cams/live-cams/?gender=f&page=3');
assert(LiveCamForge\Core\CatalogReturn::url('/camsite/', '') === '/camsite/');
assert(LiveCamForge\Core\PerformerCountry::normalize('it') === 'IT');
assert(LiveCamForge\Core\PerformerCountry::normalize('ROU') === 'RO');
assert(LiveCamForge\Core\PerformerCountry::normalize('Colombia') === 'CO');
assert(LiveCamForge\Core\PerformerCountry::normalize('unknown') === null);
assert(LiveCamForge\Core\PerformerCountry::label('IT', 'it') === 'Italia');
assert(LiveCamForge\Core\PerformerCountry::label('US', 'en') === 'United States');
assert(LiveCamForge\Core\PerformerCountry::label('PH', 'it') === 'Filippine');
assert(LiveCamForge\Core\PerformerCountry::label('IT', 'fr') !== 'IT');
assert($config->get('admin.enabled') === true);
assert($config->get('sync.allow_empty') === false);
assert($config->get('sync.history_days') === 7);
assert($config->get('catalog.new_days') === 7);
assert(LiveCamForge\Core\PerformerTypes::fromConfig($config) === ['f', 'm', 't', 'c']);
assert(LiveCamForge\Core\PerformerTypes::normalize(['couples', 'women', 'women']) === ['f', 'c']);
assert(LiveCamForge\Core\PerformerTypes::fromConfig(new LiveCamForge\Core\Config([
    'catalog' => ['performer_types' => ['m', 't']],
])) === ['m', 't']);
assert(LiveCamForge\Core\PerformerTypes::accepts('t', ['m', 't']) === true);
assert(LiveCamForge\Core\PerformerTypes::accepts('f', ['m', 't']) === false);
assert(LiveCamForge\Core\NewnessStrategy::values() === ['automatic', 'provider', 'first_seen']);
assert(LiveCamForge\Core\NewnessStrategy::for($config, 'chaturbate') === 'automatic');
assert(LiveCamForge\Core\NewnessStrategy::for(new LiveCamForge\Core\Config([
    'catalog' => ['new_strategies' => ['default' => 'first_seen', 'chaturbate' => 'provider']],
]), 'chaturbate') === 'provider');
assert(LiveCamForge\Core\NewnessStrategy::for(new LiveCamForge\Core\Config([
    'catalog' => ['new_strategies' => ['default' => 'first_seen']],
]), 'future-provider') === 'first_seen');
$repositoryWithoutDatabase = (new ReflectionClass(LiveCamForge\Repositories\PerformerRepository::class))->newInstanceWithoutConstructor();
$newnessFilter = new ReflectionMethod($repositoryWithoutDatabase, 'newnessFilterSql');
$automaticParams = [];
$automaticSql = $newnessFilter->invokeArgs($repositoryWithoutDatabase, [[
    'provider' => 'chaturbate', 'new_strategies' => ['chaturbate' => 'automatic'],
], &$automaticParams, 7]);
assert(str_contains($automaticSql, 'provider_is_new = 1'));
assert(str_contains($automaticSql, 'provider_is_new IS NULL'));
assert($automaticParams['new_provider_0'] === 'chaturbate');
$providerParams = [];
$providerSql = $newnessFilter->invokeArgs($repositoryWithoutDatabase, [[
    'provider' => 'chaturbate', 'new_strategies' => ['chaturbate' => 'provider'],
], &$providerParams, 7]);
assert(str_contains($providerSql, 'provider_is_new = 1'));
assert(!str_contains($providerSql, 'provider_is_new IS NULL'));
$firstSeenParams = [];
$firstSeenSql = $newnessFilter->invokeArgs($repositoryWithoutDatabase, [[
    'provider' => 'bongacams', 'new_strategies' => ['bongacams' => 'first_seen'],
], &$firstSeenParams, 7]);
assert(str_contains($firstSeenSql, 'created_at >= DATE_SUB'));
assert(!str_contains($firstSeenSql, 'provider_is_new'));
assert($config->get('geo.source') === 'auto');
$cloudflareGeo = LiveCamForge\Core\VisitorGeo::detect(new LiveCamForge\Core\Config([
    'debug' => false,
    'geo' => ['source' => 'cloudflare'],
]), [
    'HTTP_CF_IPCOUNTRY' => 'US',
    'HTTP_CF_REGION_CODE' => 'NY',
    'HTTP_ACCEPT_LANGUAGE' => 'it-IT,it;q=0.9,en;q=0.8',
]);
assert($cloudflareGeo->known() === true);
assert($cloudflareGeo->complete() === true);
assert($cloudflareGeo->restrictionCodes() === ['US', 'US:NY', 'LANG:IT', 'LANG:EN']);
$incompleteUsGeo = LiveCamForge\Core\VisitorGeo::detect(new LiveCamForge\Core\Config([
    'debug' => false,
    'geo' => ['source' => 'cloudflare'],
]), ['HTTP_CF_IPCOUNTRY' => 'US']);
assert($incompleteUsGeo->known() === true);
assert($incompleteUsGeo->complete() === false);
$missingRegionGeo = LiveCamForge\Core\VisitorGeo::detect(new LiveCamForge\Core\Config([
    'debug' => false,
    'geo' => ['source' => 'cloudflare'],
]), [
    'HTTP_CF_IPCOUNTRY' => 'US',
    'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
]);
assert($missingRegionGeo->known() === true);
assert($missingRegionGeo->complete() === false);
$missingLanguageGeo = LiveCamForge\Core\VisitorGeo::detect(new LiveCamForge\Core\Config([
    'debug' => false,
    'geo' => ['source' => 'cloudflare'],
]), [
    'HTTP_CF_IPCOUNTRY' => 'IT',
    'HTTP_CF_REGION_CODE' => '62',
]);
assert($missingLanguageGeo->known() === true);
assert($missingLanguageGeo->complete() === false);
assert(LiveCamForge\Core\VisitorGeo::normalizeBlock('UK') === 'GB');
assert(LiveCamForge\Core\VisitorGeo::normalizeBlock('us:ny') === 'US:NY');
assert(LiveCamForge\Core\VisitorGeo::normalizeBlock('it:62') === 'IT:62');
assert(LiveCamForge\Core\VisitorGeo::normalizeLanguageBlock('pt-BR') === 'LANG:PT');
assert(LiveCamForge\Providers\ProviderFactory::enabledNames($config) === ['demo']);
assert(array_diff([
    'stripchat',
    'cam4',
    'crakrevenue_mfc',
    'crakrevenue_streamate',
    'crakrevenue_chaturbate',
    'crakrevenue_awempire',
    'crakrevenue_stripchat',
    'crakrevenue_imlive',
    'crakrevenue_bongacash',
], LiveCamForge\Providers\ProviderFactory::availableNames()) === []);
assert(LiveCamForge\Providers\ProviderFactory::affiliateRouteGroups()['stripchat']['options'] === [
    'stripchat', 'crakrevenue_stripchat',
]);
assert(LiveCamForge\Providers\ProviderFactory::affiliateRouteGroups()['chaturbate']['options'] === [
    'chaturbate', 'crakrevenue_chaturbate',
]);
assert(LiveCamForge\Providers\ProviderFactory::affiliateRouteGroups()['livejasmin']['options'] === [
    'livejasmin', 'crakrevenue_awempire',
]);
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('chaturbate', $config, $root) === 'Chaturbate');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('crakrevenue_chaturbate', $config, $root) === 'Chaturbate');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('livejasmin', $config, $root) === 'LiveJasmin');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('crakrevenue_awempire', $config, $root) === 'LiveJasmin');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('stripchat', $config, $root) === 'Stripchat');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('crakrevenue_stripchat', $config, $root) === 'Stripchat');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('crakrevenue_streamate', $config, $root) === 'Jerkmate');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('bongacams', $config, $root) === 'BongaCams');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('cam4', $config, $root) === 'CAM4');
assert(LiveCamForge\Providers\ProviderFactory::publicDisplayName('crakrevenue_bongacash', $config, $root) === 'BongaCams');
assert(LiveCamForge\Providers\ProviderFactory::make('cam4', $config, $root)->displayName() === 'CAM4');
assert(LiveCamForge\Providers\ProviderFactory::make('stripchat', $config, $root)->displayName() === 'Stripchat');
assert(LiveCamForge\Providers\ProviderFactory::make('crakrevenue_stripchat', $config, $root)->displayName() === 'Stripchat via CrakRevenue');
assert(LiveCamForge\Providers\ProviderFactory::make('crakrevenue_streamate', $config, $root)->displayName() === 'Jerkmate via CrakRevenue');
assert(LiveCamForge\Providers\ProviderFactory::make('crakrevenue_bongacash', $config, $root)->displayName() === 'BongaCams via CrakRevenue');
assert(LiveCamForge\Providers\ProviderFactory::make('crakrevenue_awempire', $config, $root)->displayName() === 'LiveJasmin via CrakRevenue');
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueClient::BRANDS === [
    'mfc', 'streamate', 'chaturbate', 'bongacash', 'awempire', 'stripchat', 'imlive',
]);
$multiProviderConfig = new LiveCamForge\Core\Config([
    'provider' => 'chaturbate',
    'providers' => ['enabled' => ['demo', 'chaturbate', 'demo']],
]);
assert(LiveCamForge\Providers\ProviderFactory::enabledNames($multiProviderConfig) === ['chaturbate', 'demo']);
assert(LiveCamForge\Providers\ProviderFactory::isEnabled('demo', $multiProviderConfig) === true);
$conflictingRouteConfig = new LiveCamForge\Core\Config([
    'provider' => 'stripchat',
    'providers' => ['enabled' => ['stripchat', 'crakrevenue_stripchat', 'demo']],
]);
assert(LiveCamForge\Providers\ProviderFactory::enabledNames($conflictingRouteConfig) === ['stripchat', 'demo']);
$conflictingChaturbateRouteConfig = new LiveCamForge\Core\Config([
    'provider' => 'chaturbate',
    'providers' => ['enabled' => ['crakrevenue_chaturbate', 'demo']],
]);
assert(LiveCamForge\Providers\ProviderFactory::enabledNames($conflictingChaturbateRouteConfig) === ['chaturbate', 'demo']);
assert($config->get('player.aspect_ratio_width') === 16);
assert($config->get('player.aspect_ratio_height') === 9);
assert($config->get('rooms.block_non_public') === true);
assert(count($performers) === 4);
assert($performers[0] instanceof LiveCamForge\Models\Performer);
assert($performers[0]->provider === 'demo');
assert($performers[0]->embedUrl === null);
assert($performers[0]->roomStatus === 'public');
assert($performers[0]->providerNew === true);
assert($performers[0]->countryCode === 'IT');
$syncReflection = new ReflectionClass(LiveCamForge\Services\SyncPerformers::class);
$syncWithoutDatabase = $syncReflection->newInstanceWithoutConstructor();
$syncProviderProperty = $syncReflection->getProperty('provider');
$syncProviderProperty->setValue($syncWithoutDatabase, $provider);
$normalizePopularity = $syncReflection->getMethod('normalizePopularity');
$rankedDemoPerformers = $normalizePopularity->invoke($syncWithoutDatabase, $performers);
assert($rankedDemoPerformers[0]->popularityScore === 1.0);
assert($rankedDemoPerformers[3]->popularityScore === 0.0);
$applySortScores = $syncReflection->getMethod('applySortScores');
$sortedDemoPerformers = $applySortScores->invoke($syncWithoutDatabase, $rankedDemoPerformers);
assert($sortedDemoPerformers[0]->watchSortScore === 8500000000343000000);
assert($sortedDemoPerformers[3]->watchSortScore === 8500000000096000000);
assert($sortedDemoPerformers[0]->providerSortScore === 1030000005000000342);
assert($provider->isEmbedUrlAllowed('https://chaturbate.com/embed/example/') === false);
assert($provider->isMediaUrlAllowed('https://placehold.co/640x480') === true);
assert($provider->capabilities()->enabled() === ['age', 'viewers', 'tags', 'media_proxy']);
assert($english->get('filters.submit') === 'Filter');
assert($italian->get('filters.submit') === 'Filtra');
assert($italian->get('performer.viewers', ['count' => 4]) === '4 spettatori');


$cam4 = new LiveCamForge\Providers\Cam4\Cam4Adapter(new LiveCamForge\Core\Config([
    'cam4' => ['affiliate_id' => 12345],
]));
assert($cam4->capabilities()->enabled() === ['embed', 'room_status', 'age', 'viewers', 'tags', 'media_proxy', 'affiliate_links', 'conversion_polling']);
assert($cam4->isRoomUrlAllowed('https://offers.cam4tracking.com/aff_c?offer_id=278&aff_id=12345') === true);
assert($cam4->isRoomUrlAllowed('https://example.com/aff_c?offer_id=278') === false);
assert($cam4->isEmbedUrlAllowed('https://cam4-hls.xcdnpro.com/live/example/playlist.m3u8') === true);
assert($cam4->isMediaUrlAllowed('https://snapshots.xcdnpro.com/thumbnails/example') === true);
$cam4Tracked = $cam4->trackedRoomUrl(
    'https://offers.cam4tracking.com/aff_c?offer_id=278&aff_id=12345',
    'lcf_abc123',
    'livecamforge'
);
assert(str_contains($cam4Tracked, 'aff_sub=lcf_abc123'));
assert(str_contains($cam4Tracked, 'aff_sub2=livecamforge'));

$adapter = new LiveCamForge\Providers\Chaturbate\ChaturbateAdapter($config);
assert($adapter->capabilities()->enabled() === ['embed', 'room_status', 'age', 'viewers', 'tags', 'media_proxy', 'affiliate_links', 'postback_tracking']);
assert($adapter->isEmbedUrlAllowed('https://chaturbate.com/embed/example/') === true);
assert($adapter->isEmbedUrlAllowed('https://example.com/embed/example/') === false);
assert($adapter->isMediaUrlAllowed('https://roomimg.stream.highwebmedia.com/ri/example.jpg') === true);
assert($adapter->isMediaUrlAllowed('https://thumb.live.mmcdn.com/ri/example.jpg') === true);
assert($adapter->isMediaUrlAllowed('https://example.com/example.jpg') === false);
$trackingAdapter = new LiveCamForge\Providers\Chaturbate\ChaturbateAdapter(new LiveCamForge\Core\Config([
    'chaturbate' => ['postback' => ['enabled' => true]],
]));
$trackedRoomUrl = $trackingAdapter->trackedRoomUrl(
    'https://chaturbate.com/in/?tour=abc&campaign=def&room=aurora_demo',
    'lcf_1234567890abcdef',
    'livecamforge'
);
assert(str_contains($trackedRoomUrl, 'sid=lcf_1234567890abcdef'));
assert(str_contains($trackedRoomUrl, 'track=livecamforge'));
assert(str_contains($trackedRoomUrl, 'room=aurora_demo'));
assert($adapter->trackedRoomUrl('https://chaturbate.com/in/?room=aurora_demo', 'lcf_123', 'livecamforge') === 'https://chaturbate.com/in/?room=aurora_demo');
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::belongsToTracker('lcf_123', 'livecamforge') === true);
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::belongsToTracker('ce_123', 'livecamforge') === false);
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::belongsToTracker('c_123', 'home') === false);
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::belongsToTracker('', '') === true);
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::tokenAmount(['tokens' => '42']) === 42);
assert(LiveCamForge\Postbacks\ChaturbatePostbackHandler::tokenAmount(['token' => '7']) === 7);
assert(LiveCamForge\Postbacks\PostbackHandlerFactory::supports('chaturbate') === true);
assert(LiveCamForge\Postbacks\PostbackHandlerFactory::supports('livejasmin') === true);
assert(LiveCamForge\Postbacks\PostbackHandlerFactory::supports('stripchat') === true);
assert(LiveCamForge\Postbacks\PostbackHandlerFactory::supports('crakrevenue') === true);
assert(class_exists(LiveCamForge\Postbacks\StripchatPostbackHandler::class));
assert(LiveCamForge\Postbacks\PostbackHandlerFactory::supports('bongacams') === false);
$extractEmbedUrl = new ReflectionMethod($adapter, 'extractEmbedUrl');
$embedUrl = $extractEmbedUrl->invoke($adapter, [
    'iframe_embed_revshare' => '<iframe src="//chaturbate.com/embed/demo/?campaign=TESTCAMPAIGN&amp;room=aurora_demo"></iframe>',
]);
assert($embedUrl === 'https://chaturbate.com/embed/demo/?campaign=TESTCAMPAIGN&room=aurora_demo');
assert($extractEmbedUrl->invoke($adapter, ['iframe_embed_revshare' => '<iframe src="https://example.com/"></iframe>']) === null);
$chaturbatePlayer = $adapter->player(['embed_url' => 'https://chaturbate.com/embed/demo/?campaign=TESTCAMPAIGN&room=aurora_demo']);
assert($chaturbatePlayer?->mode === LiveCamForge\Providers\ProviderPlayer::MODE_IFRAME);
assert(str_contains($chaturbatePlayer?->url ?? '', 'embed_video_only=1'));
$chaturbateFull = new LiveCamForge\Providers\Chaturbate\ChaturbateAdapter(new LiveCamForge\Core\Config([
    'chaturbate' => ['player_mode' => 'full_embed'],
]));
$chaturbateFullPlayer = $chaturbateFull->player(['embed_url' => 'https://chaturbate.com/embed/demo/?campaign=TESTCAMPAIGN&room=aurora_demo']);
assert(!str_contains($chaturbateFullPlayer?->url ?? '', 'embed_video_only=1'));
$normalizeRoomStatus = new ReflectionMethod($adapter, 'normalizeRoomStatus');
assert($normalizeRoomStatus->invoke($adapter, ['current_show' => 'public']) === 'public');
assert($normalizeRoomStatus->invoke($adapter, ['current_show' => 'Public Show']) === 'public');
assert($normalizeRoomStatus->invoke($adapter, ['current_show' => 'private']) === 'private');
assert($normalizeRoomStatus->invoke($adapter, ['room_status' => 'group_show']) === 'group');
assert($normalizeRoomStatus->invoke($adapter, ['is_private' => true]) === 'private');
assert($normalizeRoomStatus->invoke($adapter, []) === 'public');
$normalizeAge = new ReflectionMethod($adapter, 'normalizeAge');
assert($normalizeAge->invoke($adapter, 18) === 18);
assert($normalizeAge->invoke($adapter, 98) === 98);
assert($normalizeAge->invoke($adapter, 99) === null);
assert($normalizeAge->invoke($adapter, 17) === null);
$chaturbateNewFlag = new ReflectionMethod($adapter, 'newFlag');
assert($chaturbateNewFlag->invoke($adapter, ['is_new' => true]) === true);
assert($chaturbateNewFlag->invoke($adapter, ['is_new' => '0']) === false);
assert($chaturbateNewFlag->invoke($adapter, []) === null);

$factoryProvider = LiveCamForge\Providers\ProviderFactory::make('demo', $config, $root);
assert($factoryProvider instanceof LiveCamForge\Providers\DemoProvider);

$stripchatConfig = new LiveCamForge\Core\Config([
    'stripchat' => ['user_id' => '12345', 'api_key' => 'not-used-in-smoke'],
]);
$stripchat = new LiveCamForge\Providers\Stripchat\StripchatAdapter($stripchatConfig);
assert($stripchat->capabilities()->enabled() === ['embed', 'room_status', 'viewers', 'tags', 'affiliate_links', 'offline_fallback', 'geo_restrictions', 'postback_tracking']);
$stripchatPlayer = $stripchat->player(['username' => 'demo_model'], [
    'click_through_url' => 'https://example.com/public/?go=demo_model&provider=stripchat',
]);
assert($stripchatPlayer?->mode === LiveCamForge\Providers\ProviderPlayer::MODE_SCRIPT);
assert(str_contains($stripchatPlayer?->url ?? '', 'modelName=demo_model'));
assert(str_contains($stripchatPlayer?->url ?? '', 'strict=1'));
assert(str_contains($stripchatPlayer?->url ?? '', 'clickThroughUrl=https%3A%2F%2Fexample.com'));
assert($stripchat->resolvePlayer($stripchatPlayer)?->mode === LiveCamForge\Providers\ProviderPlayer::MODE_WRAPPED_IFRAME);
assert($stripchat->isMediaUrlAllowed('https://img.doppiocdn.com/example.jpg') === true);
assert($stripchat->isMediaUrlAllowed('https://evil.example/example.jpg') === false);
$isDeletedStripchatEndpointAllowed = new ReflectionMethod($stripchat, 'isDeletedEndpointAllowed');
assert($isDeletedStripchatEndpointAllowed->invoke($stripchat, 'https://go.whitetrafsa.com/app/models-ext/models/deleted') === true);
assert($isDeletedStripchatEndpointAllowed->invoke($stripchat, 'https://evil.example/app/models-ext/models/deleted') === false);
$trackedStripchatRoom = $stripchat->trackedRoomUrl(
    'https://go.whitetrafsa.com/?path=%2Fdemo_model&userId=12345',
    'lcf_1234567890abcdef',
    'livecamforge'
);
assert(str_contains($trackedStripchatRoom, 'memberId=lcf_1234567890abcdef'));
$normalizeStripchat = new ReflectionMethod($stripchat, 'normalize');
$normalizedStripchat = $normalizeStripchat->invoke($stripchat, [
    'id' => 123,
    'username' => 'demo_model',
    'tags' => ['girls/brunette', 'outdoors'],
    'avatarUrl' => 'https://img.doppiocdn.com/avatar.jpg',
    'snapshotUrl' => 'https://static-proxy.strpst.com/snapshot.jpg',
    'clickUrl' => 'https://go.whitetrafsa.com/?path=%2Fdemo_model&userId=12345',
    'viewersCount' => 42,
    'status' => 'public',
    'languages' => ['Italian'],
    'modelsCountry' => 'ITA',
    'geobans' => [
        'blockedCountries' => ['GB'],
        'blockedRegions' => ['IT' => ['62']],
        'blockedLanguages' => ['pt-BR'],
    ],
]);
assert($normalizedStripchat instanceof LiveCamForge\Models\Performer);
assert($normalizedStripchat->gender === 'f');
assert($normalizedStripchat->geoBlocks === ['GB', 'IT:62', 'LANG:PT']);
assert($normalizedStripchat->countryCode === 'IT');

$crakConfig = new LiveCamForge\Core\Config([
    'crakrevenue' => ['api_key' => 'not-used-in-smoke', 'token' => 'not-used-in-smoke'],
]);
$crakMfc = LiveCamForge\Providers\ProviderFactory::make('crakrevenue_mfc', $crakConfig, $root);
assert($crakMfc instanceof LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter);
assert($crakMfc->isEmbedUrlAllowed('https://suaplf.live/example') === true);
assert($crakMfc->isEmbedUrlAllowed('https://chaturbate.com/embed/example/') === true);
assert($crakMfc->isEmbedUrlAllowed('https://edwmcr.com/embed/lfcht?performerid=123') === true);
assert($crakMfc->isRoomUrlAllowed('https://t.camsk7.com/example') === true);
assert($crakMfc->isRoomUrlAllowed('https://chaturbate.com/in/?room=example') === true);
assert($crakMfc->isRoomUrlAllowed('https://ctwmsg.com/example') === true);
assert($crakMfc->isMediaUrlAllowed('https://img.mfcimg.com/example.jpg') === true);
assert($crakMfc->isMediaUrlAllowed('https://roomimg.stream.highwebmedia.com/ri/example.jpg') === true);
assert($crakMfc->isMediaUrlAllowed('https://static.vcmdiawe.com/example.jpg') === true);
assert($crakMfc->isEmbedUrlAllowed('https://evil.example/example') === false);
$normalizeCrak = new ReflectionMethod($crakMfc, 'normalize');
$normalizedCrak = $normalizeCrak->invoke($crakMfc, [
    'itemId' => 'mfc_123',
    'name' => 'DemoModel',
    'characteristic' => ['genderCode' => 'f', 'age' => 29, 'country' => 'Colombia'],
    'thumbnailUrl' => 'https://img.mfcimg.com/photos/1/avatar.300x300.jpg',
    'iframeFeedURL' => 'https://suaplf.live/mfc?name=DemoModel',
    'roomUrl' => 'https://t.camsk7.com/1/2?model=DemoModel',
    'live' => true,
    'systemScore' => 1,
]);
assert($normalizedCrak instanceof LiveCamForge\Models\Performer);
assert($normalizedCrak->provider === 'crakrevenue_mfc');
assert($normalizedCrak->providerId === 'mfc_123');
assert($normalizedCrak->age === 29);
assert($normalizedCrak->countryCode === 'CO');
assert($normalizedCrak->viewers === null);
assert($normalizedCrak->popularityScore === 1.0);
$scoredCrak = $normalizedCrak->withSortScores(true);
assert($scoredCrak->watchSortScore === 4500000000001000000);
assert($scoredCrak->providerSortScore === 1030000000000000000);
$crakChaturbate = LiveCamForge\Providers\ProviderFactory::make('crakrevenue_chaturbate', $crakConfig, $root);
$crakAwEmpire = LiveCamForge\Providers\ProviderFactory::make('crakrevenue_awempire', $crakConfig, $root);
assert($crakChaturbate instanceof LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter);
assert($crakAwEmpire instanceof LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter);
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::brandForProvider('crakrevenue_chaturbate') === 'chaturbate');
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::brandForProvider('crakrevenue_awempire') === 'awempire');

$bongaConfig = new LiveCamForge\Core\Config([
    'bongacams' => [
        'campaign_id' => 12345,
        'client_ip' => '1.1.1.1',
        'widget_endpoint' => 'https://bngprm.com/promo.php',
        'offline_fallback_values' => [
            'profile' => 'model_profile',
            'homepage' => 'homepage',
        ],
    ],
]);
$bonga = new LiveCamForge\Providers\BongaCams\BongaCamsAdapter($bongaConfig);
assert($bonga->capabilities()->enabled() === ['embed', 'age', 'viewers', 'tags', 'media_proxy', 'affiliate_links', 'offline_fallback']);
assert($bonga->isEmbedUrlAllowed('https://bngprm.com/promo.php?c=12345&type=embed_chat') === true);
assert($bonga->isEmbedUrlAllowed('https://ded7126-edge65.bcvcdn.com/hls/stream_Test/playlist.m3u8') === true);
assert($bonga->isEmbedUrlAllowed('https://rt.bongacams4.com/chat-popup/Test?c=12345') === true);
assert($bonga->isEmbedUrlAllowed('https://evil.example/chat-popup/Test?c=12345') === false);
assert($bonga->isMediaUrlAllowed('https://i.bgicdn.com/live/example.jpg') === true);
assert($bonga->isRoomUrlAllowed('https://bongacams.com/track?c=12345') === true);
assert($bonga->isRoomUrlAllowed('https://example.com/track?c=12345') === false);
$bongaStream = 'https://ded7126-edge65.bcvcdn.com/hls/stream_Vikusha/playlist.m3u8';
$bongaPlayer = $bonga->player(['username' => 'Vikusha', 'embed_url' => $bongaStream], ['offline_fallback' => 'profile']);
assert($bongaPlayer?->mode === LiveCamForge\Providers\ProviderPlayer::MODE_SCRIPT);
assert($bongaPlayer?->timeoutMs === 12000);
assert(str_contains($bongaPlayer?->url ?? '', 'models%5B0%5D=Vikusha'));
assert(str_contains($bongaPlayer?->url ?? '', 'model_offline=model_profile'));
assert(str_contains($bongaPlayer?->url ?? '', 'stream_only_size=full'));
assert(str_contains($bongaPlayer?->url ?? '', 'top_model=1'));
assert($bongaPlayer?->fallbackMode === LiveCamForge\Providers\ProviderPlayer::MODE_HLS);
assert($bongaPlayer?->fallbackUrl === $bongaStream);
assert($bongaPlayer?->fallbackTimeoutMs === 12000);
$bongaFull = new LiveCamForge\Providers\BongaCams\BongaCamsAdapter(new LiveCamForge\Core\Config([
    'bongacams' => [
        'campaign_id' => 12345,
        'client_ip' => '1.1.1.1',
        'player_mode' => 'full_embed',
        'widget_endpoint' => 'https://bngprm.com/promo.php',
        'offline_fallback_values' => ['profile' => 'model_profile'],
    ],
]));
$bongaFullPlayer = $bongaFull->player(['username' => 'Vikusha', 'embed_url' => $bongaStream], ['offline_fallback' => 'profile']);
assert(!str_contains($bongaFullPlayer?->url ?? '', 'stream_only_size=full'));
$normalizeBongaGender = new ReflectionMethod($bonga, 'normalizeGender');
assert($normalizeBongaGender->invoke($bonga, 'female') === 'f');
assert($normalizeBongaGender->invoke($bonga, 'couple_f_m') === 'c');
$normalizeBonga = new ReflectionMethod($bonga, 'normalize');
$normalizedBonga = $normalizeBonga->invoke($bonga, [
    'username' => 'country_test',
    'homecountry' => 'Ukraine',
]);
assert($normalizedBonga->countryCode === 'UA');
$normalizedBongaCyrillic = $normalizeBonga->invoke($bonga, [
    'username' => 'country_test_cyrillic',
    'homecountry' => 'Германия',
]);
assert($normalizedBongaCyrillic->countryCode === 'DE');
$normalizedBongaInvalid = $normalizeBonga->invoke($bonga, [
    'username' => 'country_test_invalid',
    'homecountry' => 'Planet Earth',
]);
assert($normalizedBongaInvalid->countryCode === null);
assert(LiveCamForge\Core\PerformerCountry::normalize('россия') === 'RU');
assert(LiveCamForge\Core\PerformerCountry::normalize('Europe') === null);
assert(LiveCamForge\Core\PerformerCountry::normalize('-') === null);
$isPublicIpv4 = new ReflectionMethod($bonga, 'isPublicIpv4');
assert($isPublicIpv4->invoke($bonga, '1.1.1.1') === true);
assert($isPublicIpv4->invoke($bonga, '127.0.0.1') === false);
assert($isPublicIpv4->invoke($bonga, '192.168.1.10') === false);

$liveJasminConfig = new LiveCamForge\Core\Config([
    'livejasmin' => [
        'ps_id' => 'affiliate_test',
        'access_key' => '1234567890abcdef1234567890abcdef',
        'widget_endpoint' => 'https://edwmcr.com/embed/lfcht',
        'stream_only_widget_endpoint' => 'https://edwmcr.com/embed/lf',
        'stream_only_widget_tool' => '202_1',
    ],
]);
$liveJasmin = new LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter($liveJasminConfig);
$liveJasminCategories = new ReflectionMethod($liveJasmin, 'categories');
assert($liveJasminCategories->invoke($liveJasmin, ['f', 'm', 't', 'c']) === ['girl', 'gay', 'transgender', 'lesbian', 'couple']);
$legacyLiveJasmin = new LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter(new LiveCamForge\Core\Config([
    'livejasmin' => ['categories' => ['girl', 'boy', 'trans', 'couple']],
]));
assert($liveJasminCategories->invoke($legacyLiveJasmin, ['f', 'm', 't', 'c']) === ['girl', 'gay', 'transgender', 'couple']);
assert($liveJasminCategories->invoke($liveJasmin, ['t']) === ['transgender']);
assert($liveJasminCategories->invoke($liveJasmin, ['c']) === ['lesbian', 'couple']);
assert($liveJasmin->capabilities()->enabled() === ['embed', 'room_status', 'age', 'tags', 'media_proxy', 'affiliate_links', 'geo_restrictions', 'postback_tracking']);
assert($liveJasmin->isRoomUrlAllowed('https://ctwmsg.com/?performerName=EvangelineVoss') === true);
assert($liveJasmin->isRoomUrlAllowed('https://example.com/?performerName=EvangelineVoss') === false);
assert($liveJasmin->isMediaUrlAllowed('https://galleryn2.vcmdiawe.com/example.jpg') === true);
assert($liveJasmin->isMediaUrlAllowed('https://example.com/example.jpg') === false);
$liveJasminPlayer = $liveJasmin->player(['username' => 'EvangelineVoss', 'gender' => 'f']);
assert($liveJasminPlayer?->mode === LiveCamForge\Providers\ProviderPlayer::MODE_SCRIPT);
assert($liveJasminPlayer?->sandboxWrapper === true);
assert(str_contains($liveJasminPlayer?->url ?? '', 'forcedPerformers%5B0%5D=EvangelineVoss'));
assert(str_starts_with($liveJasminPlayer?->url ?? '', 'https://edwmcr.com/embed/lf?'));
assert(str_contains($liveJasminPlayer?->url ?? '', 'pstool=202_1'));
assert(str_contains($liveJasminPlayer?->url ?? '', 'vp%5BshowChat%5D='));
assert(!str_contains($liveJasminPlayer?->url ?? '', 'vp%5BshowChat%5D=true'));
assert($liveJasmin->resolvePlayer($liveJasminPlayer) === $liveJasminPlayer);
$liveJasminFull = new LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter(new LiveCamForge\Core\Config([
    'livejasmin' => [
        'ps_id' => 'affiliate_test',
        'access_key' => '1234567890abcdef1234567890abcdef',
        'player_mode' => 'full_embed',
        'widget_endpoint' => 'https://edwmcr.com/embed/lfcht',
        'widget_tool' => '320_1',
    ],
]));
$liveJasminFullPlayer = $liveJasminFull->player(['username' => 'EvangelineVoss', 'gender' => 'f']);
assert(str_starts_with($liveJasminFullPlayer?->url ?? '', 'https://edwmcr.com/embed/lfcht?'));
assert(str_contains($liveJasminFullPlayer?->url ?? '', 'vp%5BshowChat%5D=true'));
$trackedLiveJasmin = new LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter(new LiveCamForge\Core\Config([
    'livejasmin' => [
        'ps_id' => 'affiliate_test',
        'access_key' => '1234567890abcdef1234567890abcdef',
        'widget_endpoint' => 'https://edwmcr.com/embed/lfcht',
        'postback' => ['enabled' => true],
    ],
]));
$trackedLiveJasminRoom = $trackedLiveJasmin->trackedRoomUrl(
    'https://ctwmsg.com/?performerName=EvangelineVoss',
    'ce_1234567890abcdef',
    'livecamforge'
);
assert(str_contains($trackedLiveJasminRoom, 'subAffId=ce_1234567890abcdef'));
$trackedLiveJasminPlayer = $trackedLiveJasmin->player(
    ['username' => 'EvangelineVoss', 'gender' => 'f'],
    ['sub_aff_id' => 'ce_abcdef1234567890']
);
assert(str_contains($trackedLiveJasminPlayer?->url ?? '', 'subAffId=ce_abcdef1234567890'));
assert(LiveCamForge\Postbacks\LiveJasminPostbackHandler::inferEventType('', 4.2, 21.0, true, false) === 'first_bill');
assert(LiveCamForge\Postbacks\LiveJasminPostbackHandler::inferEventType('', 4.2, 21.0, false, true) === 'rebill');
assert(LiveCamForge\Postbacks\LiveJasminPostbackHandler::inferEventType('', -4.2, -21.0, false, false) === 'chargeback');
assert(LiveCamForge\Postbacks\LiveJasminPostbackHandler::inferEventType('refund', 0, 0, false, false) === 'refund');
$normalizeLiveJasminStatus = new ReflectionMethod($liveJasmin, 'normalizeRoomStatus');
assert($normalizeLiveJasminStatus->invoke($liveJasmin, 'free_chat', []) === 'public');
assert($normalizeLiveJasminStatus->invoke($liveJasmin, 'private_chat', []) === 'private');
assert($normalizeLiveJasminStatus->invoke($liveJasmin, 'offline', []) === 'away');
$normalizeLiveJasminGender = new ReflectionMethod($liveJasmin, 'normalizeGender');
assert($normalizeLiveJasminGender->invoke($liveJasmin, 'gay', []) === 'm');
assert($normalizeLiveJasminGender->invoke($liveJasmin, 'transgender', []) === 't');
assert($normalizeLiveJasminGender->invoke($liveJasmin, 'lesbian', []) === 'f');
assert($normalizeLiveJasminGender->invoke($liveJasmin, 'couple', []) === 'c');
$normalizeLiveJasmin = new ReflectionMethod($liveJasmin, 'normalize');
$normalizedLiveJasmin = $normalizeLiveJasmin->invoke($liveJasmin, [
    'uniqueModelId' => 'model-123',
    'performerId' => 'EvangelineVoss',
    'displayName' => 'Evangeline Voss',
    'status' => 'free_chat',
    'category' => 'girl',
    'ethnicity' => 'latin',
    'details' => [
        'appearances' => ['long nails'],
        'willingnesses' => ['anal sex'],
        'languages' => ['english'],
    ],
    'persons' => [[
        'sex' => 'female',
        'age' => '29',
        'countryCode' => 'RO',
        'body' => ['build' => 'athletic', 'hairColor' => 'blonde'],
    ]],
    'profilePictureUrl' => [
        'size896x504' => 'https://galleryn2.vcmdiawe.com/example.jpg',
    ],
    'chatRoomUrl' => 'https://ctwmsg.com/?performerName=EvangelineVoss',
    'isNewbie' => 1,
    'bannedCountries' => ['UK', 'US:NY', 'US:NY'],
], 'girl', 0.75);
assert($normalizedLiveJasmin instanceof LiveCamForge\Models\Performer);
assert($normalizedLiveJasmin->providerId === 'model-123');
assert($normalizedLiveJasmin->gender === 'f');
assert($normalizedLiveJasmin->age === 29);
assert($normalizedLiveJasmin->countryCode === 'RO');
assert($normalizedLiveJasmin->roomStatus === 'public');
assert($normalizedLiveJasmin->providerNew === true);
assert($normalizedLiveJasmin->geoBlocks === ['GB', 'US:NY']);
assert($normalizedLiveJasmin->viewers === null);
assert($normalizedLiveJasmin->popularityScore === 0.75);
assert(in_array('long-nails', $normalizedLiveJasmin->tags, true));
assert(in_array('anal-sex', $normalizedLiveJasmin->tags, true));


assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::providerForOfferId(6224) === 'crakrevenue_streamate');
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::providerForOfferId(4487) === 'crakrevenue_awempire');
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::goalName('crakrevenue_stripchat', 33281) === 'chargeback');
assert(LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter::goalName('crakrevenue_awempire', 0) === 'click');
$trackedCrak = new LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter(new LiveCamForge\Core\Config([
    'crakrevenue' => ['postback' => ['enabled' => true]],
]), 'crakrevenue_chaturbate');
$trackedCrakRoom = $trackedCrak->trackedRoomUrl(
    'https://t.frtayb.com/999999/3688/0?aff_sub5=STATIC_TEST',
    'lcf_1234567890abcdef',
    'livecamforge'
);
assert(str_contains($trackedCrakRoom, 'aff_sub=lcf_1234567890abcdef'));
assert(str_contains($trackedCrakRoom, 'aff_sub5=STATIC_TEST'));
assert(is_file($root . '/postback.php'));
assert(is_file($root . '/database/migrations/009_add_conversion_tracking.sql'));
assert(is_file($root . '/database/migrations/010_extend_multiprovider_conversion_tracking.sql'));
assert(is_file($root . '/database/migrations/011_add_traffic_source.sql'));
assert(is_file($root . '/database/migrations/012_add_retention_index.sql'));
assert(is_file($root . '/database/migrations/013_create_traffic_landings.sql'));
assert(is_file($root . '/database/migrations/014_add_provider_new_flag.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/014_add_provider_new_flag.sql'), 'provider_is_new'));
assert(is_file($root . '/database/migrations/016_add_dashboard_indexes.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/016_add_dashboard_indexes.sql'), 'idx_online_provider_gender'));
assert(method_exists(LiveCamForge\Repositories\PerformerRepository::class, 'countOnlineByProviders'));
assert(method_exists(LiveCamForge\Repositories\SyncRunRepository::class, 'latestSuccessfulByProviders'));
assert(method_exists(LiveCamForge\Repositories\PerformerRepository::class, 'countOnlineForFilterSets'));
assert(is_file($root . '/database/migrations/017_add_catalog_count_index.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/017_add_catalog_count_index.sql'), 'idx_catalog_online_gender_provider'));
assert(is_file($root . '/database/migrations/018_add_performer_country.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/018_add_performer_country.sql'), 'country_code'));
assert(is_file($root . '/database/migrations/019_separate_viewers_popularity.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/019_separate_viewers_popularity.sql'), 'popularity_score'));
assert(is_file($root . '/database/migrations/020_add_indexed_catalog_sort.sql'));
assert(str_contains((string) file_get_contents($root . '/database/migrations/020_add_indexed_catalog_sort.sql'), 'idx_online_watch_sort'));
assert(is_file($root . '/database/migrations/021_optimize_cold_and_deep_catalog.sql'));
$deepCatalogMigration = (string) file_get_contents($root . '/database/migrations/021_optimize_cold_and_deep_catalog.sql');
assert(str_contains($deepCatalogMigration, 'has_geo_blocks'));
assert(str_contains($deepCatalogMigration, 'idx_online_geo_watch_sort'));
assert(str_contains($deepCatalogMigration, 'idx_online_geo_catalog'));
assert($italian->get('filters.sort.provider_popular') === 'Popolari sui provider');
$performerRepositorySource = (string) file_get_contents($root . '/app/Repositories/PerformerRepository.php');
assert(!str_contains($performerRepositorySource, '\'tags_json\' => "IFNULL(tags_json, $null)"'));
assert(str_contains($performerRepositorySource, '$data[\'tags_json\'] = json_encode($performer->tags, JSON_UNESCAPED_SLASHES);'));
assert(!str_contains($performerRepositorySource, '(viewers IS NULL) ASC, viewers DESC'));
assert(str_contains($performerRepositorySource, 'popularity_score DESC'));
assert(str_contains($performerRepositorySource, 'FORCE INDEX (idx_online_watch_sort)'));
assert(str_contains($performerRepositorySource, 'FORCE INDEX (idx_online_geo_watch_sort)'));
assert(str_contains($performerRepositorySource, 'watch_sort_score DESC, id DESC'));
assert(str_contains($performerRepositorySource, "SELECT id FROM performers"));
assert(str_contains($performerRepositorySource, "return 'AND has_geo_blocks = 0 '"));
assert(str_contains($performerRepositorySource, "\$data['has_geo_blocks']"));
assert(method_exists(LiveCamForge\Repositories\PerformerRepository::class, 'availableCountries'));
assert(is_file($root . '/app/Core/CountryNames.php'));
assert(is_file($root . '/tests/country-feed-diagnostic.php'));
assert(str_contains((string) file_get_contents($root . '/tests/country-feed-diagnostic.php'), 'No credentials'));
assert(is_file($root . '/app/Services/CatalogCountCache.php'));
assert(is_file($root . '/app/Core/PerformanceProfiler.php'));
assert(str_contains((string) file_get_contents($root . '/public/index.php'), 'X-LiveCamForge-Performance'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), "PerformanceProfiler::start('catalog.count_query')"));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'provider_is_new IS NULL'));
$performerCardSource = (string) file_get_contents($root . '/templates/partials/performer-card.php');
assert(str_contains($performerCardSource, "(int) (\$performer['provider_is_new'] ?? 0) === 1"));
assert(str_contains($performerCardSource, 'class="new-badge"'));
assert(str_contains((string) file_get_contents($root . '/public/assets/app.css'), '.new-badge'));
assert($english->get('performer.new_badge') === 'NEW');
assert($italian->get('performer.new_label') === 'Nuova secondo il provider');
$homeTemplateSource = (string) file_get_contents($root . '/templates/home.php');
$modelTemplateSource = (string) file_get_contents($root . '/templates/model.php');
assert(!str_contains($homeTemplateSource, "translator->get('common.provider')"));
assert(str_contains($modelTemplateSource, '$showProviderIdentity'));
assert(str_contains($modelTemplateSource, "catalog['show_provider_filter']"));
assert(str_contains($modelTemplateSource, "catalog['show_provider_badges']"));
assert(str_contains($modelTemplateSource, "model.external_note_generic"));
assert(str_contains($modelTemplateSource, "model.meta_description_generic"));
assert(str_contains($modelTemplateSource, '$publicProviderName'));
assert(!str_contains($modelTemplateSource, '$provider->displayName()'));
assert(str_contains((string) file_get_contents($root . '/templates/recruitment.php'), 'publicDisplayName'));
assert(str_contains((string) file_get_contents($root . '/templates/admin-landings.php'), '$availableProviderPublicLabels'));
assert($english->get('model.external_note_generic') === 'The room opens in a new tab.');
assert($italian->get('model.meta_description_generic', ['name' => 'Aurora']) === 'Scopri Aurora, attualmente live in cam.');
assert(is_file($root . '/templates/sitemap.php'));
assert(is_file($root . '/templates/recruitment.php'));
assert(!str_contains((string) file_get_contents($root . '/templates/model.php'), 'class="model-status'));
assert(is_file($root . '/app/Core/ProviderPolicy.php'));
assert(is_file($root . '/app/Services/CrakRevenueAuthorization.php'));
assert(class_exists(LiveCamForge\Services\CrakRevenueAuthorization::class));
assert(method_exists(LiveCamForge\Providers\CrakRevenue\CrakRevenueClient::class, 'testBrandsDetailed'));
assert(is_file($root . '/app/Core/SafeMarkdown.php'));
assert(is_file($root . '/app/Repositories/TrafficLandingRepository.php'));
assert(is_file($root . '/templates/admin-landings.php'));
foreach ([
    'README.md',
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'docs/README.md',
    'docs/GETTING_STARTED.md',
    'docs/INSTALLATION.md',
    'docs/ADMIN_GUIDE.md',
    'docs/CONFIGURATION.md',
    'docs/providers/README.md',
    'docs/CRON_AND_SYNC.md',
    'docs/CONVERSION_TRACKING.md',
    'docs/PERFORMANCE.md',
    'docs/DEPLOYMENT.md',
    'docs/SECURITY.md',
    'docs/TROUBLESHOOTING.md',
    'docs/UPGRADING.md',
    'docs/DEVELOPMENT.md',
    'docs/PROVIDER_DEVELOPMENT.md',
    'docs/TRANSLATIONS.md',
    'docs/DOCUMENTATION_GUIDE.md',
    'docs/UPGRADE_1.0.0.md',
    'docs/UPGRADE_1.0.1.md',
] as $documentationFile) {
    assert(is_file($root . '/' . $documentationFile));
}
assert(str_contains((string) file_get_contents($root . '/docs/README.md'), 'English only'));
$installationDocs = (string) file_get_contents($root . '/docs/INSTALLATION.md');
$syncDocs = (string) file_get_contents($root . '/docs/CRON_AND_SYNC.md');
$deploymentDocs = (string) file_get_contents($root . '/docs/DEPLOYMENT.md');
$conversionDocs = (string) file_get_contents($root . '/docs/CONVERSION_TRACKING.md');
assert(str_contains($installationDocs, 'Admin → Operations'));
assert(str_contains($installationDocs, 'CLI access is optional'));
assert(str_contains($syncDocs, 'Interactive shell/SSH access is **not required**'));
assert(str_contains($syncDocs, 'Hosting control-panel scheduler/cron'));
assert(str_contains($syncDocs, 'every 10 minutes'));
assert(str_contains($syncDocs, 'around **8 minutes**'));
assert(str_contains($syncDocs, 'at least **2× the typical full-sync duration**'));
assert(!str_contains($syncDocs, 'CLI is the production path'));
assert(str_contains($deploymentDocs, 'Interactive shell/SSH access is not required'));
assert(str_contains($conversionDocs, 'Conversion tracking inside LiveCamForge is **optional**'));
assert(!str_contains($conversionDocs, 'will be expanded during 0.26.x'));
assert(!is_dir($root . '/docs/it'));
assert(!is_file($root . '/docs/USER_GUIDE.md'));
assert(!is_file($root . '/docs/DEVELOPER_GUIDE.md'));
$landingRepositorySource = (string) file_get_contents($root . '/app/Repositories/TrafficLandingRepository.php');
$landingAdminSource = (string) file_get_contents($root . '/templates/admin-landings.php');
assert(!str_contains($landingRepositorySource, 'private const LOCALES'));
assert(str_contains($landingAdminSource, 'foreach ($landingLanguages as $locale => $language)'));
assert(!str_contains($landingAdminSource, "['en' => 'English', 'it' => 'Italiano']"));
assert(str_contains((string) file_get_contents($root . '/templates/model.php'), '$performerPolicy->indexPerformerPages'));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), 'deleteStale'));
assert(str_contains((string) file_get_contents($root . '/app/Services/SyncPerformers.php'), 'PerformerTypes::accepts'));
assert(str_contains((string) file_get_contents($root . '/app/Repositories/PerformerRepository.php'), 'genderScopeSql'));
$catalogSourcesAdminSource = (string) file_get_contents($root . '/templates/admin-catalog-sources.php');
assert(str_contains($catalogSourcesAdminSource, 'enabled_providers[]'));
assert(str_contains($catalogSourcesAdminSource, 'catalog_performer_types[]'));
assert(!str_contains((string) file_get_contents($root . '/templates/admin-configuration.php'), 'enabled_providers[]'));
assert(!str_contains((string) file_get_contents($root . '/templates/admin-configuration.php'), 'catalog_performer_types[]'));
assert(str_contains((string) file_get_contents($root . '/templates/home.php'), '$showGenderFilter'));
assert(str_contains((string) file_get_contents($root . '/templates/home.php'), '$showCountryFilter'));
assert(str_contains($landingAdminSource, 'filter_country'));
assert($english->get('filters.country') === 'Country');
assert($italian->get('filters.all_countries') === 'Tutte le nazioni');
assert(str_contains((string) file_get_contents($root . '/public/index.php'), 'CatalogReturn::url'));
assert(str_contains((string) file_get_contents($root . '/app/Providers/Chaturbate/ChaturbateAdapter.php'), 'isApiEndpointAllowed'));
assert(str_contains((string) file_get_contents($root . '/app/Providers/LiveJasmin/LiveJasminAdapter.php'), 'isFeedEndpointAllowed'));
assert(str_contains((string) file_get_contents($root . '/app/Providers/BongaCams/BongaCamsAdapter.php'), 'isApiEndpointAllowed'));
assert(str_contains((string) file_get_contents($root . '/app/Providers/Cam4/Cam4Adapter.php'), 'api.cam4pays.com'));
assert(str_contains((string) file_get_contents($root . '/app/Services/Cam4ConversionSync.php'), 'isTuneEndpointAllowed'));
assert(str_contains((string) file_get_contents($root . '/app/Services/MediaProxy.php'), 'isSafeRemoteUrl'));
assert(str_contains((string) file_get_contents($root . '/app/Core/Logger.php'), '[redacted]'));
assert(str_contains((string) file_get_contents($root . '/.htaccess'), 'Options -Indexes'));
assert(is_file($root . '/docs/NGINX_SECURITY_EXAMPLE.md'));

$performerRepositorySource = (string) file_get_contents($root . '/app/Repositories/PerformerRepository.php');
assert(!str_contains($performerRepositorySource, '\'tags_json\' => "IFNULL(tags_json, $null)"'));
assert(str_contains($performerRepositorySource, '$data[\'tags_json\'] = json_encode($performer->tags, JSON_UNESCAPED_SLASHES);'));
assert(is_file($root . '/database/migrations/022_add_performer_tag_index.sql'));

// 0.27.15 regression: recruitment CTA eligibility is provider-specific and must also work from admin preview before publication.
$recruitmentGoSource = file_get_contents(__DIR__ . '/../recruitment-go.php');
assert(is_string($recruitmentGoSource));
assert(str_contains($recruitmentGoSource, '!($entry[\'enabled\'] ?? false)'));
assert(!str_contains($recruitmentGoSource, '!($recruitment[\'enabled\'] ?? false)'));
assert(str_contains($recruitmentGoSource, "header('Location: ' . \$destination, true, 302)"));

// 0.99.0 RC3 regression: deployment Base URL is editable again and safely file-owned.
$adminConfigurationSource = (string) file_get_contents($root . '/templates/admin-configuration.php');
$localConfigManagerSource = (string) file_get_contents($root . '/app/Core/LocalConfigManager.php');
assert(str_contains($adminConfigurationSource, 'name="seo_base_url"'));
assert(str_contains($localConfigManagerSource, "array_key_exists('seo_base_url', \$input)"));
assert(str_contains($localConfigManagerSource, "\$config['seo']['base_url'] = \$baseUrl"));

// 0.99.0 RC3 regression: SEO landing pagination preserves visitor query filters.
$publicIndexSource = (string) file_get_contents($root . '/public/index.php');
assert(str_contains($publicIndexSource, 'parse_str(CatalogReturn::query($_GET), $query)'));
assert(str_contains($publicIndexSource, "unset(\$query['page'], \$query['tag_cursor'], \$query['tag_dir'])"));

// 0.99.0 RC3 deployment UX: shared-hosting defaults and cron acknowledgement.
$installerSourceRc3 = (string) file_get_contents($root . '/install/index.php');
$adminSourceRc3 = (string) file_get_contents($root . '/admin/index.php');
$adminTemplateRc3 = (string) file_get_contents($root . '/templates/admin.php');
assert(str_contains($installerSourceRc3, "\$_POST['host'] ?? 'localhost'"));
assert(str_contains($adminSourceRc3, "deployment.cron_confirmed_at"));
assert(str_contains($adminTemplateRc3, '/home/account/public_html/livecamforge/bin/sync.php'));
assert(str_contains($adminTemplateRc3, '/home/account/public_html/livecamforge/bin/sync-conversions.php cam4'));


// RC3 hardening regression: Admin sessions are isolated per physical installation path.
$adminIndexSource = (string) file_get_contents($root . '/admin/index.php');
assert(str_contains($adminIndexSource, "\$adminScriptName = str_replace"));
assert(str_contains($adminIndexSource, "\$adminCookiePath = \$adminBasePath === '' || \$adminBasePath === '.' ? '/' : \$adminBasePath . '/';"));
assert(str_contains($adminIndexSource, "\$adminSessionName .= '_' . substr(hash('sha256', \$adminCookiePath), 0, 12);"));
assert(str_contains($adminIndexSource, "'path' => \$adminCookiePath"));

$deriveAdminSessionScope = static function (string $scriptName, string $configuredName = 'livecamforge_admin'): array {
    $scriptName = str_replace('\\', '/', $scriptName);
    $adminDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $adminBasePath = rtrim(str_replace('\\', '/', dirname($adminDirectory)), '/');
    $cookiePath = $adminBasePath === '' || $adminBasePath === '.' ? '/' : $adminBasePath . '/';
    $sessionName = $configuredName;
    if ($cookiePath !== '/') {
        $sessionName .= '_' . substr(hash('sha256', $cookiePath), 0, 12);
    }

    return [$cookiePath, $sessionName];
};

[$rootCookiePath, $rootSessionName] = $deriveAdminSessionScope('/admin/index.php');
assert($rootCookiePath === '/');
assert($rootSessionName === 'livecamforge_admin');

[$testCookiePath, $testSessionName] = $deriveAdminSessionScope('/test/admin/index.php');
assert($testCookiePath === '/test/');
assert($testSessionName !== 'livecamforge_admin');
assert(str_starts_with($testSessionName, 'livecamforge_admin_'));


// 1.0.1 Demo mode regressions.
assert(\LiveCamForge\Core\DemoMode::providerNames() === ['demo_alpha', 'demo_beta']);
assert(in_array('demo_alpha', \LiveCamForge\Providers\ProviderFactory::availableNames(), true));
assert(in_array('demo_beta', \LiveCamForge\Providers\ProviderFactory::availableNames(), true));
$demoAlphaFixture = json_decode((string) file_get_contents($root . '/database/demo-alpha-performers.json'), true, 512, JSON_THROW_ON_ERROR);
$demoBetaFixture = json_decode((string) file_get_contents($root . '/database/demo-beta-performers.json'), true, 512, JSON_THROW_ON_ERROR);
assert(count($demoAlphaFixture) === 40);
assert(count($demoBetaFixture) === 40);
assert(is_file($root . '/public/assets/demo/demo_alpha/01.svg'));
assert(is_file($root . '/public/assets/demo/demo_beta/40.svg'));
$demoAlphaProvider = \LiveCamForge\Providers\ProviderFactory::make('demo_alpha', $config, $root);
assert($demoAlphaProvider->name() === 'demo_alpha');
assert($demoAlphaProvider->displayName() === 'Demo Alpha');
assert(count($demoAlphaProvider->fetch()) === 40);
assert(str_contains((string) file_get_contents($root . '/public/index.php'), "Disallow: /"));
assert(str_contains((string) file_get_contents($root . '/admin/index.php'), 'DemoMode::blockedAdminActions()'));


// 1.0.1 Demo installer UX: public Demo mode owns provider selection.
$installer101 = (string) file_get_contents($root . '/install/index.php');
assert(str_contains($installer101, 'data-demo-mode-choice'));
assert(str_contains($installer101, 'data-provider-fieldset'));
assert(str_contains($installer101, 'choice.checked = false;'));
assert(str_contains($installer101, 'choice.disabled = demoMode;'));
assert(str_contains($installer101, "demoModeChoice?.addEventListener('change', refreshProviderConfigs)"));
assert(str_contains($installer101, 'installer.demo_mode_providers_hint'));


// 1.0.1 Demo catalog configuration: local demo providers require no credentials.
$operationalSettingsSource = (string) file_get_contents($root . '/app/Core/OperationalSettings.php');
assert(str_contains($operationalSettingsSource, "'demo', 'demo_alpha', 'demo_beta' => true"));
$adminProviderTemplate101 = (string) file_get_contents($root . '/templates/admin-provider-configuration.php');
assert(str_contains($adminProviderTemplate101, 'Demo Alpha'));
assert(str_contains($adminProviderTemplate101, 'Demo Beta'));
assert(str_contains($adminProviderTemplate101, 'admin.demo.provider_local_hint'));


// 1.0.1 Public Demo recruitment policy.
assert(\LiveCamForge\Core\DemoMode::modelRecruitmentUrl() === 'https://livecamforge.com/become-a-model/');
assert(\LiveCamForge\Core\DemoMode::webmasterRecruitmentUrl() === 'https://livecamforge.com/for-webmasters/');
assert(!in_array('save_recruitment', \LiveCamForge\Core\DemoMode::blockedAdminActions(), true));
assert(!in_array('save_webmaster_recruitment', \LiveCamForge\Core\DemoMode::blockedAdminActions(), true));
$adminDemo101 = (string) file_get_contents($root . '/admin/index.php');
assert(str_contains($adminDemo101, "\$name !== 'demo'"));
assert(str_contains($adminDemo101, 'DemoMode::modelRecruitmentUrl()'));
assert(str_contains($adminDemo101, 'DemoMode::webmasterRecruitmentUrl()'));
$catalogDemo101 = (string) file_get_contents($root . '/templates/admin-catalog-sources.php');
assert(str_contains($catalogDemo101, 'demoCatalogProvider'));


// 1.0.1 Public Demo catalog source lock.
$catalogSourcesDemo101 = (string) file_get_contents($root . '/templates/admin-catalog-sources.php');
assert(str_contains($catalogSourcesDemo101, "<?= \$demoMode ? 'disabled' : '' ?>"));
$adminDemoCatalog101 = (string) file_get_contents($root . '/admin/index.php');
assert(str_contains($adminDemoCatalog101, 'unexpectedDemoSources'));
assert(str_contains($adminDemoCatalog101, 'admin.demo.catalog_sources_locked'));


// 1.0.1 Public Demo documentation.
$publicDemoDoc101 = (string) file_get_contents($root . '/docs/PUBLIC_DEMO.md');
assert(str_contains($publicDemoDoc101, 'A Public Demo installation is not an upgrade path to production.'));
assert(str_contains($publicDemoDoc101, 'Demo Alpha'));
assert(str_contains($publicDemoDoc101, 'Demo Beta'));
assert(str_contains($publicDemoDoc101, 'https://livecamforge.com/become-a-model/'));
assert(str_contains($publicDemoDoc101, 'https://livecamforge.com/for-webmasters/'));
assert(str_contains($publicDemoDoc101, 'Do not convert the existing Public Demo installation into the live site.'));

echo "Smoke test superato: provider, country performer, routing affiliato, policy, geo e tracking verificati.\n";

// 0.27.16 regression: discovery and opportunity navigation are separate and wrap responsively.
$homeTemplate02716 = file_get_contents($root . '/templates/home.php');
$trafficCss02716 = file_get_contents($root . '/public/assets/traffic.css');
assert(str_contains($homeTemplate02716, 'traffic-nav-discovery'));
assert(str_contains($homeTemplate02716, 'traffic-special-nav'));
assert(str_contains($homeTemplate02716, "traffic.opportunities"));
assert(str_contains($trafficCss02716, 'flex-wrap:wrap'));
assert(str_contains($trafficCss02716, '.traffic-special-nav'));


// 0.99.0 RC2 hardening regressions.
$adminSourceRc2 = (string) file_get_contents($root . '/admin/index.php');
$securityHeadersRc2 = (string) file_get_contents($root . '/app/Core/SecurityHeaders.php');
$adminTemplateRc2 = (string) file_get_contents($root . '/templates/admin.php');
assert(str_contains($adminSourceRc2, "session.use_strict_mode"));
assert(str_contains($adminSourceRc2, "session.use_only_cookies"));
assert(str_contains($adminSourceRc2, 'AdminPasswordPolicy::isWeak'));
assert(str_contains($adminTemplateRc2, 'minlength="12"'));
assert(LiveCamForge\Core\AdminPasswordPolicy::isWeak('password1234', 'admin', 'LiveCamForge'));
assert(LiveCamForge\Core\AdminPasswordPolicy::isWeak('123456789012', 'admin', 'LiveCamForge'));
assert(!LiveCamForge\Core\AdminPasswordPolicy::isWeak('correct horse battery staple', 'admin', 'LiveCamForge'));
assert(str_contains($securityHeadersRc2, "X-Frame-Options: SAMEORIGIN"));
assert(str_contains($securityHeadersRc2, "frame-ancestors 'self'"));
foreach ([
    'app/Providers/Stripchat/StripchatAdapter.php',
    'app/Providers/Chaturbate/ChaturbateAdapter.php',
    'app/Providers/LiveJasmin/LiveJasminAdapter.php',
    'app/Providers/Cam4/Cam4Adapter.php',
    'app/Providers/CrakRevenue/CrakRevenueClient.php',
    'app/Providers/BongaCams/BongaCamsAdapter.php',
    'app/Services/Cam4ConversionSync.php',
    'app/Services/MediaProxy.php',
    'config/app.php',
] as $userAgentSourceFile) {
    $userAgentSource = (string) file_get_contents($root . '/' . $userAgentSourceFile);
    assert(!str_contains($userAgentSource, 'LiveCamForge/0.25.0'));
    assert(!str_contains($userAgentSource, 'LiveCamForge/0.25.10'));
    assert(!str_contains($userAgentSource, 'LiveCamForge-MediaProxy/0.25.0'));
}
