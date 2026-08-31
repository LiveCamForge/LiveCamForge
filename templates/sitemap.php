<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc><?= e($siteUrl->absolute()) ?></loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
    <?php foreach ($sitemapLandings as $landing): ?>
        <url><loc><?= e($siteUrl->absolute('cams/' . $landing['slug'] . '/')) ?></loc><?php if (!empty($landing['updated_at'])): ?><lastmod><?= e(date('c', strtotime((string) $landing['updated_at']))) ?></lastmod><?php endif; ?><changefreq>hourly</changefreq><priority>0.8</priority></url>
    <?php endforeach; ?>
    <?php if ($recruitmentEnabled && (bool) $config->get('recruitment.models.index', true)): ?>
        <url><loc><?= e($siteUrl->absolute('become-a-model/')) ?></loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    <?php endif; ?>
    <?php if ($webmasterRecruitmentEnabled && (bool) $config->get('recruitment.webmasters.index', true)): ?>
        <url><loc><?= e($siteUrl->absolute('for-webmasters/')) ?></loc><changefreq>monthly</changefreq><priority>0.6</priority></url>
    <?php endif; ?>
    <?php foreach ($sitemapModels as $sitemapModel): ?>
        <url><loc><?= e($siteUrl->absolute('model/' . rawurlencode((string) $sitemapModel['provider']) . '/' . rawurlencode((string) $sitemapModel['username']) . '/')) ?></loc><lastmod><?= e(date('c', strtotime((string) $sitemapModel['updated_at']))) ?></lastmod><changefreq>daily</changefreq><priority>0.5</priority></url>
    <?php endforeach; ?>
</urlset>
