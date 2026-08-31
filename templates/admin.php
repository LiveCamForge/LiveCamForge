<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($translator->get('admin.title')) ?> · <?= e($siteAppearance['site_name']) ?></title>
    <link rel="stylesheet" href="../public/assets/app.css?v=<?= e(rawurlencode((string) $config->get('version'))) ?>">
    <link rel="stylesheet" href="../public/assets/admin.css?v=<?= e(rawurlencode((string) $config->get('version'))) ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
    <?php if ($authenticated && $adminSection === 'appearance'): ?><script src="../public/assets/appearance-preview.js" defer></script><?php endif; ?>
    <?php if ($authenticated): ?><script src="../public/assets/admin-ux.js?v=<?= e(rawurlencode((string) $config->get('version'))) ?>" defer></script><?php endif; ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="../"><?php require $root . '/templates/partials/brand.php'; ?></a>
    <div class="topbar-actions">
        <?php if ($authenticated): ?>
            <a class="admin-link" href="../"><?= e($translator->get('admin.catalog')) ?></a>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="text-button"><?= e($translator->get('admin.logout')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="admin-container">
    <p class="eyebrow"><?= e($translator->get('admin.eyebrow')) ?></p>
    <h1><?= e($translator->get('admin.heading')) ?></h1>
    <p class="intro"><?= e($translator->get('admin.intro')) ?></p>

    <?php if ($notice): ?>
        <div class="alert <?= $notice['type'] === 'success' ? 'success' : 'error' ?>"><?= e($notice['message']) ?></div>
    <?php endif; ?>

    <?php if ($demoMode && $authenticated): ?>
        <section class="panel demo-mode-banner">
            <p class="eyebrow"><?= e($translator->get('admin.demo.eyebrow')) ?></p>
            <h2><?= e($translator->get('admin.demo.title')) ?></h2>
            <p><?= e($translator->get('admin.demo.intro')) ?></p>
            <form method="post" onsubmit="return confirm(<?= e(json_encode($translator->get('admin.demo.reset_confirm'))) ?>)">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="reset_demo">
                <button class="button secondary" type="submit"><?= e($translator->get('admin.demo.reset_button')) ?></button>
            </form>
        </section>
    <?php endif; ?>

    <?php if (!$configured): ?>
        <section class="panel auth-panel">
            <h2><?= e($translator->get('admin.setup_title')) ?></h2>
            <p><?= e($translator->get('admin.setup_intro')) ?></p>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="setup">
                <label><?= e($translator->get('admin.username')) ?><input name="username" minlength="3" autocomplete="username" required></label>
                <label><?= e($translator->get('admin.password')) ?><input name="password" type="password" minlength="12" autocomplete="new-password" required></label>
                <label><?= e($translator->get('admin.password_confirmation')) ?><input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
                <button class="button" type="submit"><?= e($translator->get('admin.create_account')) ?></button>
            </form>
        </section>
    <?php elseif (!$authenticated): ?>
        <section class="panel auth-panel">
            <h2><?= e($translator->get('admin.login_title')) ?></h2>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="login">
                <label><?= e($translator->get('admin.username')) ?><input name="username" autocomplete="username" required></label>
                <label><?= e($translator->get('admin.password')) ?><input name="password" type="password" autocomplete="current-password" required></label>
                <button class="button" type="submit"><?= e($translator->get('admin.login')) ?></button>
            </form>
        </section>
    <?php else: ?>
        <nav class="admin-tabs" aria-label="<?= e($translator->get('admin.nav.label')) ?>">
            <a href="./" class="<?= $adminSection === 'operations' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.operations')) ?></a>
            <a href="?section=configuration" class="<?= $adminSection === 'configuration' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.configuration')) ?></a>
            <a href="?section=catalog" class="<?= $adminSection === 'catalog' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.catalog')) ?></a>
            <a href="?section=landings" class="<?= $adminSection === 'landings' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.landings')) ?></a>
            <a href="?section=conversions" class="<?= $adminSection === 'conversions' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.conversions')) ?></a>
            <a href="?section=appearance" class="<?= $adminSection === 'appearance' ? 'active' : '' ?>"><?= e($translator->get('admin.nav.appearance')) ?></a>
        </nav>

        <?php if ($adminSection === 'operations'): ?>
        <?php
        $formatDbBytes = static function (?int $bytes): string {
            if ($bytes === null) {
                return '—';
            }
            return number_format($bytes / 1048576, 0) . ' MiB';
        };
        $dbPerformanceHasWarning = count(array_filter(
            $dbPerformanceRecommendations,
            static fn (array $item): bool => !$item['ok']
        )) > 0;
        ?>
        <?php if (!$demoMode && $cronSetupConfirmedAt === ''): ?>
        <section class="panel cron-reminder-panel">
            <p class="eyebrow"><?= e($translator->get('admin.cron.eyebrow')) ?></p>
            <h2><?= e($translator->get('admin.cron.title')) ?></h2>
            <p><?= e($translator->get('admin.cron.intro')) ?></p>

            <div class="cron-reminder-task">
                <strong><?= e($translator->get('admin.cron.performer_title')) ?></strong>
                <p class="muted"><?= e($translator->get('admin.cron.performer_frequency')) ?></p>
                <code>/usr/bin/php /home/account/public_html/livecamforge/bin/sync.php &gt;/dev/null 2&gt;&amp;1</code>
            </div>
            <div class="cron-reminder-task">
                <strong><?= e($translator->get('admin.cron.conversion_title')) ?></strong>
                <p class="muted"><?= e($translator->get('admin.cron.conversion_frequency')) ?></p>
                <code>/usr/bin/php /home/account/public_html/livecamforge/bin/sync-conversions.php cam4 &gt;/dev/null 2&gt;&amp;1</code>
            </div>
            <p class="muted"><?= e($translator->get('admin.cron.adapt_hint')) ?></p>
            <form method="post" class="cron-confirm-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="confirm_cron_setup">
                <label class="checkbox-field"><input type="checkbox" name="cron_setup_confirmed" value="1"> <span><?= e($translator->get('admin.cron.confirm_label')) ?></span></label>
                <button class="button secondary" type="submit"><?= e($translator->get('admin.cron.confirm_button')) ?></button>
            </form>
            <small><?= e($translator->get('admin.cron.disclaimer')) ?></small>
        </section>
        <?php endif; ?>

        <section class="panel sync-panel db-performance-panel">
            <div>
                <h2><?= e($translator->get('admin.db_performance.title')) ?></h2>
                <p><?= e($translator->get('admin.db_performance.intro')) ?></p>
                <p class="muted">
                    <?= e($translator->get('admin.db_performance.buffer_pool', [
                        'value' => $formatDbBytes($dbPerformanceRecommendations['buffer_pool']['bytes']),
                        'recommended' => $formatDbBytes($dbPerformanceRecommendations['buffer_pool']['recommended_bytes']),
                    ])) ?><br>
                    <?= e($translator->get('admin.db_performance.packet', [
                        'value' => $formatDbBytes($dbPerformanceRecommendations['packet']['bytes']),
                        'recommended' => $formatDbBytes($dbPerformanceRecommendations['packet']['recommended_bytes']),
                    ])) ?>
                </p>
            </div>
            <span class="run-status <?= $dbPerformanceHasWarning ? 'running' : 'success' ?>">
                <?= e($translator->get($dbPerformanceHasWarning ? 'admin.db_performance.warning' : 'admin.db_performance.ready')) ?>
            </span>
        </section>

        <section class="panel sync-panel geo-safeguard-panel">
            <div>
                <h2><?= e($translator->get('admin.geo.title')) ?></h2>
                <p><?= e($adminVisitorGeo->complete()
                    ? $translator->get('admin.geo.detected', [
                        'location' => $adminVisitorGeo->country
                            . ($adminVisitorGeo->region !== null ? ':' . $adminVisitorGeo->region : ''),
                        'source' => $adminVisitorGeo->source,
                    ])
                    : $translator->get('admin.geo.unknown')) ?></p>
            </div>
            <span class="run-status <?= $adminVisitorGeo->complete() ? 'success' : 'running' ?>">
                <?= e($adminVisitorGeo->complete()
                    ? $translator->get('admin.geo.ready')
                    : $translator->get('admin.geo.restricted')) ?>
            </span>
        </section>

        <section class="provider-grid <?= count($providerStats) === 1 ? 'single' : '' ?>">
            <?php foreach ($providerStats as $name => $stats): ?>
                <article class="panel provider-card">
                    <div class="provider-card-heading">
                        <div>
                            <span><?= e($translator->get('admin.provider')) ?></span>
                            <strong><?= e($providerLabels[$name] ?? ucfirst($name)) ?></strong>
                        </div>
                        <?php if ($stats['active']): ?><span class="active-provider <?= $catalogPreferences['mode'] === 'combined' ? 'subtle' : '' ?>"><?= e($translator->get($catalogPreferences['mode'] === 'combined' ? 'admin.fallback_provider' : 'admin.active_catalog')) ?></span><?php endif; ?>
                    </div>
                    <dl>
                        <div><dt><?= e($translator->get('admin.online')) ?></dt><dd><?= e(number_format($stats['online'])) ?></dd></div>
                        <div><dt><?= e($translator->get('admin.last_success')) ?></dt><dd><?php if (!empty($stats['last_success']['finished_at'])): ?><time class="relative-time" datetime="<?= e($stats['last_success']['finished_at']) ?>" title="<?= e($stats['last_success']['finished_at']) ?>"><?= e($stats['last_success']['finished_at']) ?></time><?php else: ?><?= e($translator->get('admin.never')) ?><?php endif; ?></dd></div>
                    </dl>
                    <div class="capability-list" aria-label="<?= e($translator->get('admin.capabilities')) ?>">
                        <?php foreach ($stats['capabilities'] as $capability): ?><span><?= e($translator->get('provider.capability.' . $capability)) ?></span><?php endforeach; ?>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="sync">
                        <input type="hidden" name="provider" value="<?= e($name) ?>">
                        <button class="button secondary" type="submit"><?= e($translator->get('admin.sync_button', ['provider' => $providerLabels[$name] ?? ucfirst($name)])) ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="panel sync-panel sync-action-panel">
            <div>
                <h2><?= e($translator->get('admin.sync_title')) ?></h2>
                <p><?= e($translator->get('admin.sync_intro')) ?></p>
            </div>
            <?php if (count($providerNames) > 1): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="sync">
                    <input type="hidden" name="provider" value="__all__">
                    <button class="button" type="submit"><?= e($translator->get('admin.sync_all')) ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel history-panel">
            <h2><?= e($translator->get('admin.history')) ?></h2>
            <p class="history-note"><?= e($translator->get('admin.history_retention', ['days' => (int) $config->get('sync.history_days', 7)])) ?></p>
            <?php if ($recentRuns === []): ?>
                <p class="muted"><?= e($translator->get('admin.no_runs')) ?></p>
            <?php else: ?>
                <?php $showRunDetails = count(array_filter($recentRuns, static fn (array $run): bool => trim((string) ($run['error_message'] ?? '')) !== '')) > 0; ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th><?= e($translator->get('admin.started')) ?></th>
                            <th><?= e($translator->get('admin.provider')) ?></th>
                            <th><?= e($translator->get('admin.trigger')) ?></th>
                            <th><?= e($translator->get('admin.status')) ?></th>
                            <th><?= e($translator->get('admin.imported')) ?></th>
                            <th><?= e($translator->get('admin.duration')) ?></th>
                            <?php if ($showRunDetails): ?><th><?= e($translator->get('admin.details')) ?></th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <td><?= e($run['started_at']) ?></td>
                                <td><?= e($providerLabels[$run['provider']] ?? ucfirst($run['provider'])) ?></td>
                                <td><?= e($run['trigger_source']) ?></td>
                                <td><span class="run-status <?= e($run['status']) ?>"><?= e($translator->get('admin.run.' . $run['status'])) ?></span></td>
                                <td><?= e($run['imported_count'] ?? '—') ?></td>
                                <td><?= $run['duration_ms'] !== null ? e(number_format(((int) $run['duration_ms']) / 1000, 1)) . ' s' : '—' ?></td>
                                <?php if ($showRunDetails): ?><td class="error-detail"><?= e($run['error_message'] ?? '—') ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php elseif ($adminSection === 'configuration'): ?>
            <?php require $root . '/templates/admin-configuration.php'; ?>
        <?php elseif ($adminSection === 'catalog'): ?>
        <section class="panel catalog-settings-panel">
            <p class="eyebrow"><?= e($translator->get('admin.catalog_settings.eyebrow')) ?></p>
            <h2><?= e($translator->get('admin.catalog_settings.title')) ?></h2>
            <p class="catalog-settings-intro"><?= e($translator->get('admin.catalog_settings.intro')) ?></p>

            <div class="configuration-boundary">
                <strong><?= e($translator->get('admin.catalog_settings.boundary_title')) ?></strong>
                <p><?= e($translator->get('admin.catalog_settings.boundary_text')) ?></p>
            </div>
        </section>

        <form method="post" class="catalog-settings-form" data-catalog-settings-form>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="return_section" value="catalog">

        <?php require $root . '/templates/admin-catalog-sources.php'; ?>

        <section class="panel catalog-settings-panel">
            <h2><?= e($translator->get('admin.configuration.languages')) ?></h2>
            <p class="catalog-settings-intro"><?= e($translator->get('admin.catalog_settings.languages_intro')) ?></p>
            <div class="field-grid">
                <label><?= e($translator->get('admin.configuration.locale')) ?>
                    <select name="locale">
                        <?php foreach ($landingLanguages as $code => $language): ?>
                            <option value="<?= e($code) ?>" <?= $operationalValues['locale'] === $code ? 'selected' : '' ?>><?= e($language['name'] ?? strtoupper($code)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e($translator->get('admin.configuration.fallback_locale')) ?>
                    <select name="fallback_locale">
                        <?php foreach ($landingLanguages as $code => $language): ?>
                            <option value="<?= e($code) ?>" <?= $operationalValues['fallback_locale'] === $code ? 'selected' : '' ?>><?= e($language['name'] ?? strtoupper($code)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <small><?= e($translator->get('admin.configuration.language_hint')) ?></small>
        </section>

        <section class="panel catalog-settings-panel">
            <h2><?= e($translator->get('admin.catalog_settings.presentation_title')) ?></h2>

                <fieldset>
                    <legend><?= e($translator->get('admin.catalog_settings.source')) ?></legend>
                    <?php if (count($providerNames) > 1 && $catalogPreferences['mode'] === 'single'): ?>
                        <div class="alert warning"><?= e($translator->get('admin.catalog_settings.single_multiple_warning', [
                            'provider' => $providerLabels[$catalogPreferences['primary_provider']] ?? ucfirst($catalogPreferences['primary_provider']),
                        ])) ?></div>
                    <?php endif; ?>
                    <label><?= e($translator->get('admin.catalog_settings.mode')) ?>
                        <select name="catalog_mode" data-catalog-mode>
                            <option value="single" <?= $catalogPreferences['mode'] === 'single' ? 'selected' : '' ?>><?= e($translator->get('admin.catalog_settings.mode.single')) ?></option>
                            <option value="combined" <?= $catalogPreferences['mode'] === 'combined' ? 'selected' : '' ?> <?= count($providerNames) < 2 ? 'disabled' : '' ?> data-catalog-combined><?= e($translator->get('admin.catalog_settings.mode.combined')) ?></option>
                        </select>
                        <?php if (count($providerNames) < 2): ?><small><?= e($translator->get('admin.catalog_settings.combined_hint')) ?></small><?php endif; ?>
                    </label>
                    <label><?= e($translator->get($catalogPreferences['mode'] === 'combined' ? 'admin.catalog_settings.primary_provider_fallback' : 'admin.catalog_settings.primary_provider')) ?>
                        <select name="primary_provider">
                            <?php foreach ($providerNames as $name): ?>
                                <option value="<?= e($name) ?>" <?= $catalogPreferences['primary_provider'] === $name ? 'selected' : '' ?>><?= e($providerLabels[$name] ?? ucfirst($name)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small><?= e($translator->get($catalogPreferences['mode'] === 'combined' ? 'admin.catalog_settings.primary_hint_combined' : 'admin.catalog_settings.primary_hint')) ?></small>
                    </label>
                </fieldset>

                <fieldset>
                    <legend><?= e($translator->get('admin.catalog_settings.public_features')) ?></legend>
                    <label class="checkbox-field"><input type="checkbox" name="show_provider_filter" value="1" <?= $catalogPreferences['show_provider_filter'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.catalog_settings.show_filter')) ?></span></label>
                    <label class="checkbox-field"><input type="checkbox" name="show_provider_badges" value="1" <?= $catalogPreferences['show_provider_badges'] ? 'checked' : '' ?>> <span><?= e($translator->get('admin.catalog_settings.show_badges')) ?></span></label>
                    <small><?= e($translator->get('admin.catalog_settings.public_hint')) ?></small>
                </fieldset>

                <?php $fallbackProviders = array_filter($providerStats, static fn (array $stats): bool => in_array('offline_fallback', $stats['capabilities'], true)); ?>
                <?php if ($fallbackProviders): ?>
                <fieldset>
                    <legend><?= e($translator->get('admin.catalog_settings.offline_behavior')) ?></legend>
                    <?php foreach ($fallbackProviders as $name => $stats): ?>
                        <label><?= e($translator->get('admin.catalog_settings.offline_provider', ['provider' => $providerLabels[$name] ?? ucfirst($name)])) ?>
                            <select name="offline_fallback_<?= e($name) ?>">
                                <?php foreach (LiveCamForge\Core\CatalogSettings::OFFLINE_FALLBACKS as $fallback): ?>
                                    <option value="<?= e($fallback) ?>" <?= ($catalogPreferences['offline_fallbacks'][$name] ?? 'profile') === $fallback ? 'selected' : '' ?>><?= e($translator->get('admin.catalog_settings.offline.' . $fallback)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><?= e($translator->get('admin.catalog_settings.offline_hint')) ?></small>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php endif; ?>

                <button class="button" type="submit" name="action" value="save_catalog_all"><?= e($translator->get('admin.catalog_settings.save')) ?></button>
        </section>
        </form>
        <?php elseif ($adminSection === 'landings'): ?>
            <?php require $root . '/templates/admin-landings.php'; ?>
        <?php elseif ($adminSection === 'conversions'): ?>
        <section class="panel conversion-config-panel">
            <div>
                <p class="eyebrow"><?= e($translator->get('admin.conversions.eyebrow')) ?></p>
                <h2><?= e($translator->get('admin.conversions.title')) ?></h2>
                <p><?= e($translator->get('admin.conversions.intro')) ?></p>
            </div>
            <div class="postback-provider-list">
                <?php foreach ($postbackProviders as $name => $postback): ?>
                    <div class="postback-endpoint">
                        <strong><?= e($postback['label']) ?></strong>
                        <span class="run-status <?= $postback['ready'] ? 'success' : 'failed' ?>">
                            <?= e($postback['ready'] ? $translator->get('admin.conversions.ready') : $translator->get('admin.conversions.disabled')) ?>
                        </span>
                        <code><?= e($postback['endpoint']) ?></code>
                    </div>
                <?php endforeach; ?>
                <?php if ($postbackProviders === []): ?><p class="muted"><?= e($translator->get('admin.conversions.no_supported_providers')) ?></p><?php endif; ?>
                <small><?= e($translator->get('admin.conversions.endpoint_hint')) ?></small>
            </div>
        </section>

        <nav class="period-tabs" aria-label="<?= e($translator->get('admin.conversions.period')) ?>">
            <?php foreach (['today', '7d', '30d', 'all'] as $period): ?>
                <a href="?section=conversions&amp;period=<?= e($period) ?>" class="<?= $conversionPeriod === $period ? 'active' : '' ?>">
                    <?= e($translator->get('admin.conversions.period.' . $period)) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php
        $payoutParts = array_map(
            static fn (array $row): string => (string) $row['currency'] . ' ' . number_format((float) $row['payout'], 2),
            $conversionCurrencyTotals
        );
        $payoutDisplay = $payoutParts !== [] ? implode(' / ', $payoutParts) : '—';
        $epcDisplay = count($clickPerformance) === 1 && $conversionSummary['clicks'] > 0
            ? (string) $clickPerformance[0]['currency'] . ' ' . number_format((float) $clickPerformance[0]['payout'] / $conversionSummary['clicks'], 3)
            : '—';
        ?>
        <section class="conversion-metrics">
            <article class="panel"><strong><?= e(number_format($conversionSummary['clicks'])) ?></strong><span><?= e($translator->get('admin.conversions.clicks')) ?></span></article>
            <article class="panel"><strong><?= e(number_format($conversionSummary['conversions'])) ?></strong><span><?= e($translator->get('admin.conversions.conversions')) ?></span></article>
            <article class="panel"><strong><?= e(number_format($conversionSummary['attributed'])) ?></strong><span><?= e($translator->get('admin.conversions.attributed')) ?></span></article>
            <article class="panel"><strong><?= e($payoutDisplay) ?></strong><span><?= e($translator->get('admin.conversions.payout')) ?></span></article>
            <article class="panel"><strong><?= e(number_format($conversionSummary['conversion_rate'], 2)) ?>%</strong><span><?= e($translator->get('admin.conversions.rate')) ?></span></article>
            <article class="panel"><strong><?= e($epcDisplay) ?></strong><span><?= e($translator->get('admin.conversions.epc')) ?></span></article>
        </section>

        <details class="panel testing-tools">
            <summary><?= e($translator->get('admin.conversions.testing_tools')) ?></summary>
            <p class="muted"><?= e($translator->get('admin.conversions.testing_tools_intro')) ?></p>
            <div class="conversion-grid">
            <?php foreach ($postbackProviders as $name => $postback): ?>
                <section class="panel conversion-test-panel">
                    <h2><?= e($translator->get('admin.conversions.test_title_provider', ['provider' => $postback['label']])) ?></h2>
                    <p><?= e($translator->get('admin.conversions.test_intro')) ?></p>
                    <form method="post" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="test_postback">
                        <input type="hidden" name="provider" value="<?= e($name) ?>">
                        <input type="hidden" name="return_section" value="conversions">
                        <label>SID
                            <input name="sid" value="<?= e($latestPostbackClicks[$name]['sid'] ?? '') ?>" placeholder="lcf_…">
                            <small><?= e($translator->get('admin.conversions.test_sid_hint_provider', ['provider' => $postback['label']])) ?></small>
                        </label>
                        <div class="button-row">
                            <button class="button secondary" type="submit" name="test_mode" value="conversion" <?= !$postback['ready'] ? 'disabled' : '' ?>><?= e($translator->get('admin.conversions.test_button')) ?></button>
                            <?php if (str_starts_with($name, 'crakrevenue_')): ?>
                                <button class="button secondary" type="submit" name="test_mode" value="duplicate" <?= !$postback['ready'] ? 'disabled' : '' ?>><?= e($translator->get('admin.conversions.duplicate_test_button')) ?></button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
            </div>
        </details>

            <section class="panel event-types-panel">
                <h2><?= e($translator->get('admin.conversions.events')) ?></h2>
                <?php if ($conversionEventTypes === []): ?>
                    <p class="muted"><?= e($translator->get('admin.conversions.no_events')) ?></p>
                <?php else: ?>
                    <table>
                        <thead><tr><th><?= e($translator->get('admin.conversions.event')) ?></th><th><?= e($translator->get('admin.conversions.conversions')) ?></th><th><?= e($translator->get('admin.conversions.payout')) ?></th></tr></thead>
                        <tbody><?php foreach ($conversionEventTypes as $event): ?><tr><td><?= e($event['event_type']) ?></td><td><?= e($event['conversions']) ?></td><td><?= e($event['currency']) ?> <?= e(number_format((float) $event['payout'], 2)) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
            </section>

        <section class="panel history-panel conversion-history">
            <h2><?= e($translator->get('admin.conversions.sync_history')) ?></h2>
            <p class="muted"><?= e($translator->get('admin.conversions.sync_history_intro')) ?></p>
            <?php if ($recentConversionSyncRuns === []): ?>
                <p class="muted"><?= e($translator->get('admin.conversions.sync_history_empty')) ?></p>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th><?= e($translator->get('admin.conversions.sync_started')) ?></th>
                        <th><?= e($translator->get('admin.provider')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_trigger')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_status')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_received')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_inserted')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_duplicates')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_attributed')) ?></th>
                        <th><?= e($translator->get('admin.conversions.sync_duration')) ?></th>
                    </tr></thead>
                    <tbody><?php foreach ($recentConversionSyncRuns as $conversionRun):
                        $conversionRunStatus = (string) ($conversionRun['status'] ?? 'running');
                        $conversionRunClass = $conversionRunStatus === 'success' ? 'success' : (in_array($conversionRunStatus, ['failed', 'interrupted'], true) ? 'failed' : 'running');
                    ?><tr>
                        <td><?= e((string) $conversionRun['started_at']) ?></td>
                        <td><?= e($providerLabels[$conversionRun['provider']] ?? strtoupper((string) $conversionRun['provider'])) ?></td>
                        <td><?= e((string) $conversionRun['trigger_source']) ?></td>
                        <td><span class="run-status <?= e($conversionRunClass) ?>" title="<?= e((string) ($conversionRun['error_message'] ?? '')) ?>"><?= e(strtoupper($conversionRunStatus)) ?></span></td>
                        <td><?= $conversionRun['received_count'] === null ? '—' : e((string) $conversionRun['received_count']) ?></td>
                        <td><?= $conversionRun['inserted_count'] === null ? '—' : e((string) $conversionRun['inserted_count']) ?></td>
                        <td><?= $conversionRun['duplicate_count'] === null ? '—' : e((string) $conversionRun['duplicate_count']) ?></td>
                        <td><?= $conversionRun['attributed_count'] === null ? '—' : e((string) $conversionRun['attributed_count']) ?></td>
                        <td><?= $conversionRun['duration_ms'] === null ? '—' : e(number_format(((int) $conversionRun['duration_ms']) / 1000, 1)) . ' s' ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <section class="panel history-panel conversion-history">
            <h2><?= e($translator->get('admin.conversions.sources')) ?></h2>
            <?php if ($sourcePerformance === []): ?><p class="muted"><?= e($translator->get('admin.conversions.no_sources')) ?></p><?php else: ?>
            <div class="table-wrap"><table><thead><tr>
                <th><?= e($translator->get('admin.conversions.source')) ?></th><th><?= e($translator->get('admin.conversions.clicks')) ?></th><th><?= e($translator->get('admin.conversions.conversions')) ?></th><th><?= e($translator->get('admin.conversions.rate')) ?></th><th><?= e($translator->get('admin.conversions.payout')) ?></th>
            </tr></thead><tbody><?php foreach ($sourcePerformance as $source): $rate = (int) $source['clicks'] > 0 ? 100 * (int) $source['conversions'] / (int) $source['clicks'] : 0; ?><tr>
                <td><?= e($source['source_page']) ?></td><td><?= e($source['clicks']) ?></td><td><?= e($source['conversions']) ?></td><td><?= e(number_format($rate, 2)) ?>%</td><td><?= e(number_format((float) $source['payout'], 2)) ?></td>
            </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>

        <section class="panel history-panel conversion-history">
            <div class="conversion-history-heading">
                <h2><?= e($translator->get('admin.conversions.recent')) ?></h2>
                <?php if ($recentConversions !== []): ?>
                <div class="conversion-filter-controls">
                    <label><?= e($translator->get('admin.provider')) ?><select data-conversion-provider-filter><option value=""><?= e($translator->get('admin.conversions.all')) ?></option><?php foreach (array_values(array_unique(array_map(static fn (array $row): string => (string) $row['provider'], $recentConversions))) as $conversionProvider): ?><option value="<?= e($conversionProvider) ?>"><?= e($providerLabels[$conversionProvider] ?? ucfirst($conversionProvider)) ?></option><?php endforeach; ?></select></label>
                    <label><?= e($translator->get('admin.conversions.attribution')) ?><select data-conversion-attribution-filter><option value=""><?= e($translator->get('admin.conversions.all')) ?></option><option value="attributed"><?= e($translator->get('admin.conversions.attributed_short')) ?></option><option value="unattributed"><?= e($translator->get('admin.conversions.unattributed_short')) ?></option></select></label>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($recentConversions === []): ?>
                <p class="muted"><?= e($translator->get('admin.conversions.no_events')) ?></p>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th><?= e($translator->get('admin.conversions.received')) ?></th>
                        <th><?= e($translator->get('admin.provider')) ?></th>
                        <th><?= e($translator->get('admin.conversions.event')) ?></th>
                        <th><?= e($translator->get('admin.conversions.performer')) ?></th>
                        <th><?= e($translator->get('admin.conversions.source')) ?></th>
                        <th><?= e($translator->get('admin.conversions.attribution')) ?></th>
                        <th><?= e($translator->get('admin.conversions.payout')) ?></th>
                        <th><?= e($translator->get('admin.conversions.amount')) ?></th>
                        <th><?= e($translator->get('admin.conversions.details')) ?></th>
                    </tr></thead>
                    <tbody><?php foreach ($recentConversions as $conversion):
                        $conversionDetails = json_decode((string) ($conversion['details_json'] ?? ''), true);
                        $conversionDetails = is_array($conversionDetails) ? $conversionDetails : [];
                        $conversionAttributionFilter = $conversion['affiliate_click_id'] !== null ? 'attributed' : 'unattributed';
                    ?><tr data-conversion-row data-provider="<?= e((string) $conversion['provider']) ?>" data-attribution="<?= e($conversionAttributionFilter) ?>">
                        <td><?= e($conversion['received_at']) ?></td>
                        <td><?= e($providerLabels[$conversion['provider']] ?? ucfirst((string) $conversion['provider'])) ?></td>
                        <td><?= e($conversion['event_type']) ?></td>
                        <td><?= e($conversion['username'] ?? '—') ?></td>
                        <td><?= e($conversion['source_page'] ?? '—') ?></td>
                        <td><span class="run-status <?= $conversion['affiliate_click_id'] !== null ? 'success' : 'failed' ?>"><?= e($conversion['affiliate_click_id'] !== null ? $translator->get('admin.conversions.attributed_short') : $translator->get('admin.conversions.unattributed_short')) ?></span></td>
                        <td><?= e($conversion['currency']) ?> <?= e(number_format((float) $conversion['payout'], 2)) ?></td>
                        <td><?= e($conversion['currency']) ?> <?= e(number_format((float) $conversion['amount'], 2)) ?></td>
                        <td>
                            <details class="conversion-details">
                                <summary><?= e($translator->get('admin.conversions.show_details')) ?></summary>
                                <dl>
                                    <dt>SID</dt><dd><code><?= e($conversion['sid'] ?? '—') ?></code></dd>
                                    <dt>Transaction ID</dt><dd><code><?= e($conversion['transaction_id'] ?? '—') ?></code></dd>
                                    <dt>Offer ID</dt><dd><code><?= e($conversionDetails['offer_id'] ?? '—') ?></code></dd>
                                    <dt>Goal ID</dt><dd><code><?= e($conversionDetails['goal_id'] ?? '—') ?></code></dd>
                                    <dt><?= e($translator->get('admin.conversions.kind')) ?></dt><dd><?= e((int) ($conversion['is_test'] ?? 0) === 1 ? $translator->get('admin.conversions.test_kind') : $translator->get('admin.conversions.real_kind')) ?></dd>
                                </dl>
                            </details>
                        </td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="appearance-layout">
            <form method="post" enctype="multipart/form-data" class="panel appearance-form" data-appearance-form>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save_appearance">
                <input type="hidden" name="return_section" value="appearance">

                <div class="appearance-heading">
                    <div>
                        <p class="eyebrow"><?= e($translator->get('admin.appearance.eyebrow')) ?></p>
                        <h2><?= e($translator->get('admin.appearance.title')) ?></h2>
                        <p><?= e($translator->get('admin.appearance.intro')) ?></p>
                    </div>
                    <span class="language-pill"><?= e($translator->get('admin.appearance.language', ['language' => $translator->available()[$translator->locale()]['name'] ?? strtoupper($translator->locale())])) ?></span>
                </div>

                <fieldset>
                    <legend><?= e($translator->get('admin.appearance.branding')) ?></legend>
                    <label><?= e($translator->get('admin.appearance.site_name')) ?>
                        <input name="site_name" value="<?= e($siteAppearance['site_name']) ?>" maxlength="80" required data-preview-name>
                    </label>
                    <div class="field-grid">
                        <label><?= e($translator->get('admin.appearance.logo')) ?>
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                            <small><?= e($translator->get('admin.appearance.logo_hint')) ?></small>
                            <?php if ($siteAppearance['logo_file']): ?><span class="remove-asset"><input type="checkbox" name="remove_logo" value="1"> <?= e($translator->get('admin.appearance.remove_logo')) ?></span><?php endif; ?>
                        </label>
                        <label><?= e($translator->get('admin.appearance.favicon')) ?>
                            <input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon">
                            <small><?= e($translator->get('admin.appearance.favicon_hint')) ?></small>
                            <?php if ($siteAppearance['favicon_file']): ?><span class="remove-asset"><input type="checkbox" name="remove_favicon" value="1"> <?= e($translator->get('admin.appearance.remove_favicon')) ?></span><?php endif; ?>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?= e($translator->get('admin.appearance.colors')) ?></legend>
                    <?php $themePresets = LiveCamForge\Core\AppearanceSettings::themePresets(); ?>
                    <div class="theme-preset-picker">
                        <label><?= e($translator->get('admin.appearance.preset')) ?>
                            <select data-theme-preset>
                                <option value="custom" <?= $siteAppearance['preset'] === 'custom' ? 'selected' : '' ?>><?= e($translator->get('admin.appearance.preset.custom')) ?></option>
                                <?php foreach ($themePresets as $presetName => $preset): ?>
                                    <option value="<?= e($presetName) ?>" <?= $siteAppearance['preset'] === $presetName ? 'selected' : '' ?>><?= e($translator->get('admin.appearance.preset.' . $presetName)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><?= e($translator->get('admin.appearance.preset_hint')) ?></small>
                        </label>
                        <div class="theme-preset-grid">
                            <?php foreach ($themePresets as $presetName => $preset): ?>
                                <button
                                    type="button"
                                    class="theme-preset-card <?= $siteAppearance['preset'] === $presetName ? 'active' : '' ?>"
                                    data-theme-preset-card="<?= e($presetName) ?>"
                                    data-theme-font="<?= e($preset['font']) ?>"
                                    <?php foreach ($preset['colors'] as $presetColorName => $presetColor): ?>data-theme-<?= e($presetColorName) ?>="<?= e($presetColor) ?>" <?php endforeach; ?>
                                >
                                    <span class="theme-preset-swatches" aria-hidden="true">
                                        <i style="--swatch:<?= e($preset['colors']['background']) ?>"></i>
                                        <i style="--swatch:<?= e($preset['colors']['surface']) ?>"></i>
                                        <i style="--swatch:<?= e($preset['colors']['primary']) ?>"></i>
                                        <i style="--swatch:<?= e($preset['colors']['accent']) ?>"></i>
                                    </span>
                                    <strong><?= e($translator->get('admin.appearance.preset.' . $presetName)) ?></strong>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="color-grid">
                        <?php foreach (['primary', 'accent', 'background', 'surface', 'text', 'muted'] as $colorName): ?>
                            <label><?= e($translator->get('admin.appearance.color.' . $colorName)) ?>
                                <span class="color-control"><input type="color" value="<?= e($siteAppearance['colors'][$colorName]) ?>" data-theme-color="<?= e($colorName) ?>"><input class="color-hex" type="text" name="color_<?= e($colorName) ?>" value="<?= e(strtoupper($siteAppearance['colors'][$colorName])) ?>" maxlength="7" spellcheck="false" autocomplete="off" data-theme-hex="<?= e($colorName) ?>" aria-label="<?= e($translator->get('admin.appearance.color_hex', ['color' => $translator->get('admin.appearance.color.' . $colorName)])) ?>"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label><?= e($translator->get('admin.appearance.font')) ?>
                        <select name="font" data-preview-font>
                            <?php foreach (LiveCamForge\Core\AppearanceSettings::fontNames() as $fontName): ?>
                                <option value="<?= e($fontName) ?>" <?= $siteAppearance['font'] === $fontName ? 'selected' : '' ?>><?= e($translator->get('admin.appearance.font.' . $fontName)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="card-style-picker">
                        <label><?= e($translator->get('admin.appearance.card_style')) ?>
                            <select name="card_style" data-card-style>
                                <?php foreach (LiveCamForge\Core\AppearanceSettings::cardStyles() as $styleName => $style): ?>
                                    <option value="<?= e($styleName) ?>" <?= $siteAppearance['card_style'] === $styleName ? 'selected' : '' ?>><?= e($translator->get('admin.appearance.card_style.' . $styleName)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><?= e($translator->get('admin.appearance.card_style_hint')) ?></small>
                        </label>
                        <div class="card-style-grid">
                            <?php foreach (LiveCamForge\Core\AppearanceSettings::cardStyles() as $styleName => $style): ?>
                                <button type="button" class="card-style-card <?= $siteAppearance['card_style'] === $styleName ? 'active' : '' ?>" data-card-style-card="<?= e($styleName) ?>" data-card-radius="<?= e($style['radius']) ?>" data-cards-gap="<?= e($style['gap']) ?>" data-card-body-padding="<?= e($style['body_padding']) ?>" data-card-title-size="<?= e($style['title_size']) ?>" data-card-border="<?= e($style['border']) ?>">
                                    <span class="card-style-demo"><i></i><b></b><em></em></span>
                                    <strong><?= e($translator->get('admin.appearance.card_style.' . $styleName)) ?></strong>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><?= e($translator->get('admin.appearance.homepage')) ?></legend>
                    <label class="checkbox-field"><input type="checkbox" name="show_hero" value="1" <?= $siteAppearance['show_hero'] ? 'checked' : '' ?> data-preview-hero> <span><?= e($translator->get('admin.appearance.show_hero')) ?></span></label>
                    <?php $appearanceFallbackName = (string) ($landingLanguages[$translator->fallbackLocale()]['name'] ?? strtoupper($translator->fallbackLocale())); ?>
                    <div class="localized-editor appearance-localized-editor" data-localized-editor data-default-locale="<?= e($translator->locale()) ?>">
                        <div class="localized-language-tabs" role="tablist">
                            <?php foreach ($landingLanguages as $locale => $language):
                                $appearanceLocaleContent = is_array($siteAppearance['localized_content'][$locale] ?? null) ? $siteAppearance['localized_content'][$locale] : [];
                                $appearanceComplete = trim((string) ($appearanceLocaleContent['hero_title'] ?? '')) !== '' || trim((string) ($appearanceLocaleContent['hero_intro'] ?? '')) !== '';
                            ?>
                                <button type="button" class="localized-language-tab<?= $locale === $translator->locale() ? ' active' : '' ?>" data-language-tab="<?= e($locale) ?>"><?= e($language['name'] ?? strtoupper((string) $locale)) ?> <span><?= $appearanceComplete ? '✓' : '—' ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <small class="localized-fallback-note"><?= e($translator->get('admin.i18n.fallback_notice', ['language' => $appearanceFallbackName])) ?></small>
                        <?php foreach ($landingLanguages as $locale => $language):
                            $appearanceLocaleContent = is_array($siteAppearance['localized_content'][$locale] ?? null) ? $siteAppearance['localized_content'][$locale] : [];
                            $isPreviewLocale = $locale === $translator->locale();
                        ?>
                            <div class="localized-language-panel<?= $isPreviewLocale ? ' active' : '' ?>" data-language-panel="<?= e($locale) ?>">
                                <label><?= e($translator->get('admin.appearance.hero_eyebrow')) ?><input name="hero_eyebrow_<?= e($locale) ?>" value="<?= e($appearanceLocaleContent['hero_eyebrow'] ?? '') ?>" maxlength="120" <?= $isPreviewLocale ? 'data-preview-eyebrow' : '' ?>></label>
                                <label><?= e($translator->get('admin.appearance.hero_title')) ?><input name="hero_title_<?= e($locale) ?>" value="<?= e($appearanceLocaleContent['hero_title'] ?? '') ?>" maxlength="180" <?= $isPreviewLocale ? 'data-preview-title' : '' ?>></label>
                                <label><?= e($translator->get('admin.appearance.hero_intro')) ?><textarea name="hero_intro_<?= e($locale) ?>" maxlength="400" rows="3" <?= $isPreviewLocale ? 'data-preview-intro' : '' ?>><?= e($appearanceLocaleContent['hero_intro'] ?? '') ?></textarea></label>
                                <label><?= e($translator->get('admin.appearance.footer')) ?><textarea name="footer_text_<?= e($locale) ?>" maxlength="240" rows="2"><?= e($appearanceLocaleContent['footer_text'] ?? '') ?></textarea><small><?= e($translator->get('admin.appearance.footer_hint')) ?></small></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <button class="button" type="submit"><?= e($translator->get('admin.appearance.save')) ?></button>
            </form>

            <aside class="appearance-sidebar">
                <button class="button secondary responsive-preview-open" type="button" data-responsive-preview-open><?= e($translator->get('admin.appearance.responsive_preview')) ?></button>
                <div class="appearance-preview" data-appearance-preview>
                    <div class="preview-topbar"><strong data-preview-name-output><?= e($siteAppearance['site_name']) ?></strong></div>
                    <div class="preview-body" data-preview-hero-output>
                        <small data-preview-eyebrow-output><?= e($siteAppearance['hero_eyebrow']) ?></small>
                        <h3 data-preview-title-output><?= e($siteAppearance['hero_title']) ?></h3>
                        <p data-preview-intro-output><?= e($siteAppearance['hero_intro']) ?></p>
                        <div class="preview-card"><span></span><strong><?= e($translator->get('admin.appearance.preview_card')) ?></strong><button type="button"><?= e($translator->get('admin.appearance.preview_button')) ?></button></div>
                    </div>
                </div>
                <form method="post" class="panel reset-theme-form" onsubmit="return confirm(<?= e(json_encode($translator->get('admin.appearance.reset_confirm'))) ?>)">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="reset_appearance">
                    <input type="hidden" name="return_section" value="appearance">
                    <h3><?= e($translator->get('admin.appearance.reset_title')) ?></h3>
                    <p><?= e($translator->get('admin.appearance.reset_intro')) ?></p>
                    <button class="button secondary" type="submit"><?= e($translator->get('admin.appearance.reset')) ?></button>
                </form>
            </aside>
            <div class="responsive-preview-modal" data-responsive-preview-modal hidden>
                <div class="responsive-preview-dialog" role="dialog" aria-modal="true" aria-label="<?= e($translator->get('admin.appearance.responsive_preview')) ?>">
                    <div class="responsive-preview-header"><strong><?= e($translator->get('admin.appearance.responsive_preview')) ?></strong><button type="button" class="button secondary" data-responsive-preview-close><?= e($translator->get('admin.appearance.close_preview')) ?></button></div>
                    <div class="preview-device-toolbar" role="group" aria-label="<?= e($translator->get('admin.appearance.preview_device')) ?>"><button type="button" class="active" data-modal-preview-device="desktop"><?= e($translator->get('admin.appearance.preview_device.desktop')) ?></button><button type="button" data-modal-preview-device="tablet"><?= e($translator->get('admin.appearance.preview_device.tablet')) ?></button><button type="button" data-modal-preview-device="mobile"><?= e($translator->get('admin.appearance.preview_device.mobile')) ?></button></div>
                    <div class="responsive-preview-stage"><div class="appearance-preview responsive-preview-frame" data-responsive-preview-frame data-preview-device-current="desktop"></div></div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<footer class="admin-version-footer">LiveCamForge <?= e($config->get('version')) ?> · <a href="https://livecamforge.com/" target="_blank" rel="noopener noreferrer">livecamforge.com</a></footer>
</body>
</html>
