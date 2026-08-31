<?php
$localized = static function (mixed $value, string $fallback = '') use ($translator, $config): string {
    if (is_array($value)) {
        $fallbackLocale = (string) $config->get('fallback_locale', 'en');
        $localizedValue = $value[$translator->locale()] ?? $value[$fallbackLocale] ?? $value['en'] ?? null;
        $firstValue = reset($value);
        $value = $localizedValue ?? (is_scalar($firstValue) ? $firstValue : $fallback);
    }
    return trim((string) ($value ?? $fallback));
};
$localizedFaq = static function (mixed $faq) use ($translator, $config): array {
    if (!is_array($faq)) {
        return [];
    }
    $locale = $translator->locale();
    $fallbackLocale = (string) $config->get('fallback_locale', 'en');
    if (isset($faq[$locale]) && is_array($faq[$locale])) {
        return array_values(array_filter($faq[$locale], static fn (mixed $item): bool => is_array($item)));
    }
    if (isset($faq[$fallbackLocale]) && is_array($faq[$fallbackLocale])) {
        return array_values(array_filter($faq[$fallbackLocale], static fn (mixed $item): bool => is_array($item)));
    }
    $items = [];
    foreach ($faq as $item) {
        if (!is_array($item)) {
            continue;
        }
        $question = $item['question'] ?? '';
        $answer = $item['answer'] ?? '';
        $items[] = [
            'question' => is_array($question) ? trim((string) ($question[$locale] ?? $question[$fallbackLocale] ?? $question['en'] ?? '')) : '',
            'answer' => is_array($answer) ? trim((string) ($answer[$locale] ?? $answer[$fallbackLocale] ?? $answer['en'] ?? '')) : '',
        ];
    }
    return array_values(array_filter($items, static fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== ''));
};
$recruitmentEyebrow = $localized($recruitment['eyebrow'] ?? null, $translator->get('recruitment.eyebrow'));
$legacyTitle = $localized($recruitment['title'] ?? null, $translator->get('recruitment.title'));
$recruitmentSeoTitle = $localized($recruitment['seo_title'] ?? null, $legacyTitle);
$recruitmentHeading = $localized($recruitment['heading'] ?? null, $legacyTitle);
$recruitmentDescription = $localized($recruitment['description'] ?? null, $localized($recruitment['intro'] ?? null, $translator->get('recruitment.intro')));
$recruitmentIntro = $localized($recruitment['intro'] ?? null, $translator->get('recruitment.intro'));
$recruitmentBody = $localized($recruitment['body'] ?? null, '');
$recruitmentFaq = $localizedFaq($recruitment['faq'] ?? []);
$recruitmentVariables = ['site_name' => $siteAppearance['site_name']];
$recruitmentIntro = \LiveCamForge\Core\SafeMarkdown::interpolate($recruitmentIntro, $recruitmentVariables);
$recruitmentBodyHtml = $recruitmentBody !== ''
    ? \LiveCamForge\Core\SafeMarkdown::render($recruitmentBody, $recruitmentVariables)
    : '';
foreach ($recruitmentFaq as &$faqItem) {
    $faqItem['question'] = \LiveCamForge\Core\SafeMarkdown::interpolate((string) ($faqItem['question'] ?? ''), $recruitmentVariables);
    $faqItem['answer'] = \LiveCamForge\Core\SafeMarkdown::interpolate((string) ($faqItem['answer'] ?? ''), $recruitmentVariables);
}
unset($faqItem);
$recruitmentProviders = [];
foreach (is_array($recruitment['providers'] ?? null) ? $recruitment['providers'] : [] as $name => $entry) {
    if (!is_array($entry) || !($entry['enabled'] ?? false)) { continue; }
    $url = trim((string) ($entry['url'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) { continue; }
    $defaultTitle = ucfirst((string) $name);
    if (in_array((string) $name, \LiveCamForge\Providers\ProviderFactory::availableNames(), true)) {
        $adapterTitle = \LiveCamForge\Providers\ProviderFactory::make((string) $name, $config, $root)->displayName();
        $publicTitle = \LiveCamForge\Providers\ProviderFactory::publicDisplayName((string) $name, $config, $root);
        $configuredTitle = $localized($entry['title'] ?? '');
        $defaultTitle = $configuredTitle === '' || $configuredTitle === $adapterTitle ? $publicTitle : $configuredTitle;
    }
    $entry['title'] = $defaultTitle;
    $recruitmentProviders[$name] = $entry + ['url' => $url, 'description' => ''];
}
$canonical = $siteUrl->absolute('become-a-model/');
$pageIndexable = (bool) ($recruitment['index'] ?? true) && $recruitmentProviders !== [];
?>
<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($recruitmentSeoTitle) ?></title>
    <meta name="description" content="<?= e($recruitmentDescription) ?>">
    <meta name="robots" content="<?= $pageIndexable ? 'index,follow' : 'noindex,follow' ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($recruitmentSeoTitle) ?>">
    <meta property="og:description" content="<?= e($recruitmentDescription) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($pageIndexable && $recruitmentFaq !== []): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn (array $faq): array => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ], $recruitmentFaq),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($assetUrl('app.css')) ?>"><link rel="stylesheet" href="<?= e($assetUrl('traffic.css')) ?>?v=<?= e(rawurlencode((string) $config->get('version'))) ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
</head>
<body>
<header class="topbar"><a class="brand" href="<?= e($siteUrl->path()) ?>"><?php require $root . '/templates/partials/brand.php'; ?></a></header>
<main class="container">
    <section class="hero recruitment-hero"><div><p class="eyebrow"><?= e($recruitmentEyebrow) ?></p><h1><?= e($recruitmentHeading) ?></h1><p class="intro"><?= e($recruitmentIntro) ?></p></div></section>
    <?php if ($recruitmentBodyHtml !== ''): ?><section class="landing-content recruitment-content"><?= $recruitmentBodyHtml ?></section><?php endif; ?>
    <?php if ($recruitmentFaq !== []): ?><section class="landing-faq panel recruitment-faq"><h2><?= e($translator->get('traffic.faq')) ?></h2><?php foreach ($recruitmentFaq as $faq): ?><details><summary><?= e($faq['question']) ?></summary><p><?= e($faq['answer']) ?></p></details><?php endforeach; ?></section><?php endif; ?>
    <?php if ($recruitmentProviders === []): ?><section class="panel empty"><h2><?= e($translator->get('recruitment.unavailable')) ?></h2></section><?php else: ?>
    <section class="recruitment-provider-area recruitment-provider-area-final">
        <div class="recruitment-section-heading"><h2><?= e($translator->get('recruitment.providers_heading')) ?></h2><p><?= e($translator->get('recruitment.providers_intro')) ?></p></div>
        <div class="recruitment-grid recruitment-grid-count-<?= min(3, count($recruitmentProviders)) ?>">
            <?php foreach ($recruitmentProviders as $providerName => $entry):
                $recruitmentGoUrl = $siteUrl->path('recruitment-go.php') . '?' . http_build_query(['recruit_provider' => (string) $providerName]);
            ?><article class="panel recruitment-card"><div><h3><?= e($localized($entry['title'])) ?></h3><p><?= e($localized($entry['description'])) ?></p></div><a class="button" href="<?= e($recruitmentGoUrl) ?>" target="_blank" rel="sponsored noopener noreferrer"><?= e($translator->get('recruitment.apply')) ?></a></article><?php endforeach; ?>
        </div>
    </section><?php endif; ?>
    <p class="recruitment-note"><?= e($translator->get('recruitment.disclaimer')) ?></p>
</main>
<footer><?= e($siteAppearance['footer_text'] ?: $siteAppearance['site_name']) ?></footer>
</body></html>
