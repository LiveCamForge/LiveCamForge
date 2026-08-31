<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="<?= e($pageRobots) ?>">
    <?php if ((bool) $config->get('seo.adult_rating', true)): ?><meta name="rating" content="adult"><?php endif; ?>
    <link rel="canonical" href="<?= e($pageCanonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($pageCanonical) ?>">
    <?php if ($activeLanding !== null && $landingFaq !== [] && $pageRobots === 'index,follow'): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ], $landingFaq),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($assetUrl('app.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('gender-badges.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('catalog-pagination.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('catalog-filters.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('card-links.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('room-status.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('traffic.css')) ?>?v=<?= e(rawurlencode((string) $config->get('version'))) ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
    <script src="<?= e($assetUrl('media-fallback.js')) ?>" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e($siteUrl->path()) ?>"><?php require $root . '/templates/partials/brand.php'; ?></a>
    <?php if ((bool) $config->get('admin.enabled', true)): ?>
        <div class="topbar-actions"><a class="admin-link" href="<?= e($siteUrl->path('admin/')) ?>"><?= e($translator->get('common.admin')) ?></a></div>
    <?php endif; ?>
</header>
<?php if ($navigationLandings !== []): ?>
<nav class="traffic-nav traffic-nav-discovery" aria-label="<?= e($translator->get('traffic.navigation')) ?>">
    <div>
        <?php foreach ($navigationLandings as $landingNav): ?><a href="<?= e($siteUrl->landing($landingNav['slug'])) ?>" class="<?= $activeLanding && $activeLanding['slug'] === $landingNav['slug'] ? 'active' : '' ?>"><?= e($landingNav['title']) ?></a><?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>
<?php if ($recruitmentEnabled || $webmasterRecruitmentEnabled): ?>
<nav class="traffic-special-nav" aria-label="<?= e($translator->get('traffic.opportunities_navigation')) ?>">
    <div>
        <span class="traffic-special-label"><?= e($translator->get('traffic.opportunities')) ?></span>
        <div class="traffic-special-links">
            <?php if ($recruitmentEnabled): ?><a href="<?= e($siteUrl->path('become-a-model/')) ?>"><?= e($translator->get('recruitment.nav')) ?></a><?php endif; ?>
            <?php if ($webmasterRecruitmentEnabled): ?><a href="<?= e($siteUrl->path('for-webmasters/')) ?>"><?= e($translator->get('webmaster_recruitment.nav')) ?></a><?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container<?= $siteAppearance['show_hero'] ? '' : ' hero-hidden' ?>">
    <?php if ($activeLanding !== null): ?><section class="hero landing-hero">
        <div>
            <p class="eyebrow"><?= e($activeLanding['eyebrow']) ?></p>
            <h1><?= e($activeLanding['heading']) ?></h1>
            <p class="intro"><?= e($landingIntro) ?></p>
        </div>
    </section>
    <?php elseif ($siteAppearance['show_hero']): ?><section class="hero">
        <div>
            <p class="eyebrow"><?= e($siteAppearance['hero_eyebrow']) ?></p>
            <h1><?= e($siteAppearance['hero_title']) ?></h1>
            <p class="intro"><?= e($siteAppearance['hero_intro']) ?></p>
        </div>
    </section><?php endif; ?>

    <?php if ($notice): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>

    <form method="get" class="filters panel<?= $showProviderFilter ? ' has-provider-filter' : '' ?>">
        <div class="filter-fields">
            <label class="filter-search"><?= e($translator->get('filters.search')) ?>
                <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e($translator->get('filters.search_placeholder')) ?>">
            </label>
            <label><?= e($translator->get('filters.tag')) ?>
                <input name="tag" value="<?= e($filters['tag']) ?>" placeholder="<?= e($translator->get('filters.tag_placeholder')) ?>" maxlength="80" pattern="#?[A-Za-z0-9_/-]+">
            </label>
            <?php if ($showProviderFilter): ?><label><?= e($translator->get('filters.provider')) ?>
                <select name="provider">
                    <option value=""><?= e($translator->get('filters.all_providers')) ?></option>
                    <?php foreach ($enabledProviders as $providerOption): ?>
                        <option value="<?= e($providerOption) ?>" <?= $filters['provider'] === $providerOption ? 'selected' : '' ?>><?= e($providerLabels[$providerOption] ?? ucfirst($providerOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label><?php endif; ?>
            <?php if ($showGenderFilter): ?><label><?= e($translator->get('filters.gender')) ?>
                <select name="gender">
                    <option value=""><?= e($translator->get('filters.all_genders')) ?></option>
                    <?php foreach ($performerTypes as $performerType): ?>
                        <option value="<?= e($performerType) ?>" <?= $filters['gender'] === $performerType ? 'selected' : '' ?>><?= e($translator->get('filters.' . $performerTypeTranslationKeys[$performerType])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label><?php endif; ?>
            <?php if ($showCountryFilter): ?><label><?= e($translator->get('filters.country')) ?>
                <select name="country">
                    <option value=""><?= e($translator->get('filters.all_countries')) ?></option>
                    <?php foreach ($countryOptions as $countryCode => $countryLabel): ?>
                        <option value="<?= e($countryCode) ?>" <?= $filters['country'] === $countryCode ? 'selected' : '' ?>><?= e($countryLabel) ?> (<?= e($countryCode) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label><?php endif; ?>
            <label><?= e($translator->get('filters.age')) ?>
                <select name="age">
                    <option value=""><?= e($translator->get('filters.all_ages')) ?></option>
                    <?php foreach ($ageOptions as $ageOption): ?>
                        <option value="<?= e($ageOption) ?>" <?= $filters['age'] === $ageOption ? 'selected' : '' ?>><?= e($translator->get('filters.age.' . $ageOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e($translator->get('filters.room_status')) ?>
                <select name="room_status">
                    <option value=""><?= e($translator->get('filters.all_statuses')) ?></option>
                    <?php foreach ($roomStatusOptions as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $filters['room_status'] === $statusOption ? 'selected' : '' ?>><?= e($translator->get('room_status.' . $statusOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e($translator->get('filters.sort')) ?>
                <select name="sort">
                    <?php foreach ($sortOptions as $sortOption): ?>
                        <option value="<?= e($sortOption) ?>" <?= $filters['sort'] === $sortOption ? 'selected' : '' ?>><?= e($translator->get('filters.sort.' . $sortOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e($translator->get('catalog.per_page')) ?>
                <select name="per_page">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?= e($option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="filter-actions">
            <label class="new-filter"><input type="checkbox" name="new" value="1" <?= $filters['new_only'] ? 'checked' : '' ?>> <span><?= e($translator->get('filters.new_only', ['days' => $filters['new_days']])) ?></span></label>
            <div class="filter-buttons">
                <?php if ($hasActiveFilters): ?><a class="reset-link" href="<?= e($activeLanding ? $siteUrl->landing($activeLanding['slug']) : $siteUrl->path()) ?>"><?= e($translator->get('filters.reset')) ?></a><?php endif; ?>
                <button class="button secondary" type="submit"><?= e($translator->get('filters.submit')) ?></button>
            </div>
        </div>
    </form>

    <div class="catalog-summary">
        <?php if ($deferredTagPagination): ?>
            <?= e($translator->get('catalog.summary_window', ['from' => $rangeFrom, 'to' => $rangeTo])) ?>
        <?php else: ?>
            <?= e($translator->get('catalog.summary', ['from' => $rangeFrom, 'to' => $rangeTo, 'total' => $totalPerformers])) ?>
        <?php endif; ?>
    </div>

    <?php if ($performers === []): ?>
        <section class="empty panel">
            <h2><?= e($translator->get($hasActiveFilters ? 'empty.filtered_title' : 'empty.title')) ?></h2>
            <p><?= e($translator->get($hasActiveFilters ? 'empty.filtered_text' : 'empty.text')) ?></p>
        </section>
    <?php else: ?>
        <section class="cards">
            <?php foreach ($performers as $performer) { require $root . '/templates/partials/performer-card.php'; } ?>
        </section>

        <?php if ($deferredTagPagination && ($currentPage > 1 || $hasNextPage)): ?>
            <nav class="pagination" aria-label="<?= e($translator->get('pagination.label')) ?>">
                <?php if ($currentPage > 1): ?>
                    <a class="page-link direction" href="<?= e($tagCursorPagination ? $tagPageUrl($currentPage - 1, $tagPreviousCursor, 'prev') : $pageUrl($currentPage - 1)) ?>"><?= e($translator->get('pagination.previous')) ?></a>
                <?php else: ?>
                    <span class="page-link direction disabled"><?= e($translator->get('pagination.previous')) ?></span>
                <?php endif; ?>
                <span class="page-link current" aria-current="page"><?= e($currentPage) ?></span>
                <?php if ($hasNextPage): ?>
                    <a class="page-link direction" href="<?= e($tagCursorPagination ? $tagPageUrl($currentPage + 1, $tagNextCursor, 'next') : $pageUrl($currentPage + 1)) ?>"><?= e($translator->get('pagination.next')) ?></a>
                <?php else: ?>
                    <span class="page-link direction disabled"><?= e($translator->get('pagination.next')) ?></span>
                <?php endif; ?>
            </nav>
        <?php elseif (!$deferredTagPagination && $totalPages > 1): ?>
            <nav class="pagination" aria-label="<?= e($translator->get('pagination.label')) ?>">
                <?php if ($currentPage > 1): ?>
                    <a class="page-link direction" href="<?= e($pageUrl($currentPage - 1)) ?>"><?= e($translator->get('pagination.previous')) ?></a>
                <?php else: ?>
                    <span class="page-link direction disabled"><?= e($translator->get('pagination.previous')) ?></span>
                <?php endif; ?>

                <?php $previousPrinted = 0; foreach ($pageNumbers as $pageNumber): ?>
                    <?php if ($previousPrinted > 0 && $pageNumber > $previousPrinted + 1): ?><span class="ellipsis">…</span><?php endif; ?>
                    <a class="page-link <?= $pageNumber === $currentPage ? 'current' : '' ?>"
                       href="<?= e($pageUrl($pageNumber)) ?>"
                       aria-label="<?= e($translator->get('pagination.page', ['page' => $pageNumber])) ?>"
                       <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>><?= e($pageNumber) ?></a>
                    <?php $previousPrinted = $pageNumber; ?>
                <?php endforeach; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a class="page-link direction" href="<?= e($pageUrl($currentPage + 1)) ?>"><?= e($translator->get('pagination.next')) ?></a>
                <?php else: ?>
                    <span class="page-link direction disabled"><?= e($translator->get('pagination.next')) ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($activeLanding !== null && $landingBodyHtml !== ''): ?>
        <section class="landing-content panel"><?= $landingBodyHtml ?></section>
    <?php endif; ?>

    <?php if ($activeLanding !== null && $landingFaq !== []): ?>
        <section class="landing-faq panel">
            <h2><?= e($translator->get('traffic.faq')) ?></h2>
            <?php foreach ($landingFaq as $faq): ?><details><summary><?= e($faq['question']) ?></summary><p><?= e($faq['answer']) ?></p></details><?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<footer><?= $siteAppearance['footer_text'] !== '' ? e($siteAppearance['footer_text']) : e($siteAppearance['site_name']) ?></footer>
</body>
</html>
