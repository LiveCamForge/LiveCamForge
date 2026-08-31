<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($translator->get('model.not_found_title')) ?> · <?= e($siteAppearance['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e(isset($assetUrl) ? $assetUrl('app.css') : 'public/assets/app.css') ?>">
    <link rel="stylesheet" href="<?= e(isset($assetUrl) ? $assetUrl('model-page.css') : 'public/assets/model-page.css') ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
</head>
<body>
<main class="container not-found">
    <p class="eyebrow">404 · LiveCamForge</p>
    <h1><?= e($translator->get('model.not_found_title')) ?></h1>
    <p class="intro"><?= e($translator->get('model.not_found_text')) ?></p>
    <a class="button" href="<?= e($catalogBackUrl) ?>"><?= e($translator->get('model.back_to_catalog')) ?></a>
</main>
</body>
</html>
