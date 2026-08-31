<?php if ($siteAppearance['logo_file']): ?>
    <img class="brand-logo" src="<?= e($brandingAssetBase . 'logo&v=' . rawurlencode($siteAppearance['logo_file'])) ?>" alt="<?= e($siteAppearance['site_name']) ?>">
<?php else: ?>
    <span class="brand-name"><?= e($siteAppearance['site_name']) ?></span>
<?php endif; ?>
