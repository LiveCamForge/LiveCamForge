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
$eyebrow = $localized($webmasterRecruitment['eyebrow'] ?? null, $translator->get('webmaster_recruitment.eyebrow'));
$seoTitle = $localized($webmasterRecruitment['seo_title'] ?? null, $translator->get('webmaster_recruitment.title'));
$heading = $localized($webmasterRecruitment['heading'] ?? null, $translator->get('webmaster_recruitment.title'));
$description = $localized($webmasterRecruitment['description'] ?? null, $translator->get('webmaster_recruitment.intro'));
$intro = $localized($webmasterRecruitment['intro'] ?? null, $translator->get('webmaster_recruitment.intro'));
$body = $localized($webmasterRecruitment['body'] ?? null, '');
$ctaLabel = $localized($webmasterRecruitment['cta_label'] ?? null, $translator->get('webmaster_recruitment.cta'));
$ctaUrl = \LiveCamForge\Core\DemoMode::enabled($config)
    ? \LiveCamForge\Core\DemoMode::webmasterRecruitmentUrl()
    : trim((string) ($webmasterRecruitment['cta_url'] ?? ''));
$ctaValid = filter_var($ctaUrl, FILTER_VALIDATE_URL) && str_starts_with(strtolower($ctaUrl), 'https://');
$faq = $localizedFaq($webmasterRecruitment['faq'] ?? []);
$variables = ['site_name' => $siteAppearance['site_name']];
$intro = \LiveCamForge\Core\SafeMarkdown::interpolate($intro, $variables);
$bodyHtml = $body !== '' ? \LiveCamForge\Core\SafeMarkdown::render($body, $variables) : '';
foreach ($faq as &$faqItem) {
    $faqItem['question'] = \LiveCamForge\Core\SafeMarkdown::interpolate((string) ($faqItem['question'] ?? ''), $variables);
    $faqItem['answer'] = \LiveCamForge\Core\SafeMarkdown::interpolate((string) ($faqItem['answer'] ?? ''), $variables);
}
unset($faqItem);
$canonical = $siteUrl->absolute('for-webmasters/');
$pageIndexable = (bool) ($webmasterRecruitment['index'] ?? true);
?>
<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seoTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= $pageIndexable ? 'index,follow' : 'noindex,follow' ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($seoTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($pageIndexable && $faq !== []): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn (array $item): array => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ], $faq),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($assetUrl('app.css')) ?>"><link rel="stylesheet" href="<?= e($assetUrl('traffic.css')) ?>?v=<?= e(rawurlencode((string) $config->get('version'))) ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
</head>
<body>
<header class="topbar"><a class="brand" href="<?= e($siteUrl->path()) ?>"><?php require $root . '/templates/partials/brand.php'; ?></a></header>
<main class="container">
    <section class="hero webmaster-recruitment-hero"><div><p class="eyebrow"><?= e($eyebrow) ?></p><h1><?= e($heading) ?></h1><p class="intro"><?= e($intro) ?></p></div></section>
    <?php if ($bodyHtml !== ''): ?><section class="landing-content webmaster-recruitment-content"><?= $bodyHtml ?></section><?php endif; ?>
    <?php if ($faq !== []): ?><section class="landing-faq panel webmaster-recruitment-faq"><h2><?= e($translator->get('traffic.faq')) ?></h2><?php foreach ($faq as $item): ?><details><summary><?= e($item['question']) ?></summary><p><?= e($item['answer']) ?></p></details><?php endforeach; ?></section><?php endif; ?>
    <?php if ($ctaValid || !empty($adminPreview)): ?>
    <section class="webmaster-recruitment-final-cta panel">
        <div class="webmaster-recruitment-final-cta-copy">
            <p class="eyebrow"><?= e($translator->get('webmaster_recruitment.cta_eyebrow')) ?></p>
            <h2><?= e($translator->get('webmaster_recruitment.cta_heading')) ?></h2>
            <p><?= e($translator->get('webmaster_recruitment.cta_description')) ?></p>
        </div>
        <div class="webmaster-recruitment-cta">
            <?php if ($ctaValid): ?><a class="button" href="<?= e($ctaUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($ctaLabel) ?></a><?php else: ?><span class="button" aria-disabled="true"><?= e($ctaLabel) ?></span><small class="preview-cta-note"><?= e($translator->get('webmaster_recruitment.preview_cta_missing_url')) ?></small><?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    <p class="recruitment-note webmaster-recruitment-note"><?= e($translator->get('webmaster_recruitment.disclaimer')) ?></p>
</main>
<footer><?= e($siteAppearance['footer_text'] ?: $siteAppearance['site_name']) ?></footer>
</body></html>
