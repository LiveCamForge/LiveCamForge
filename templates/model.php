<?php
$modelGenderMeta = [
    'f' => ['symbol' => '♀', 'key' => 'performer.gender.female', 'class' => 'female'],
    'm' => ['symbol' => '♂', 'key' => 'performer.gender.male', 'class' => 'male'],
    't' => ['symbol' => '⚧', 'key' => 'performer.gender.trans', 'class' => 'trans'],
    'c' => ['symbol' => '👥', 'key' => 'performer.gender.couple', 'class' => 'couple'],
];
$gender = $modelGenderMeta[$performer['gender']] ?? ['symbol' => '•', 'key' => 'performer.gender.other', 'class' => 'other'];
$genderLabel = $translator->get($gender['key']);
$tags = json_decode((string) $performer['tags_json'], true) ?: [];
$isOnline = (int) $performer['is_online'] === 1;
$roomStatus = in_array($performer['room_status'] ?? '', ['public', 'private', 'group', 'away'], true)
    ? (string) $performer['room_status']
    : 'unknown';
$hasReliableRoomStatus = $providerCapabilities->roomStatus;
$effectiveRoomStatus = $hasReliableRoomStatus ? $roomStatus : 'public';
$isPublicRoom = $effectiveRoomStatus === 'public';
$blockNonPublicRooms = (bool) $config->get('rooms.block_non_public', true);
$canOpenRoom = $isPublicRoom || !$blockNonPublicRooms;
$roomStatusLabel = $translator->get('room_status.' . $effectiveRoomStatus);
$hasRoom = $isOnline && $canOpenRoom && $provider->isRoomUrlAllowed((string) $performer['room_url']);
$hasEmbed = $isOnline
    && $canOpenRoom
    && $providerCapabilities->embed
    && (bool) $config->get('player.enabled', true)
    && $player !== null
    && is_string($playerFrameUrl)
    && $playerFrameUrl !== '';
$playerTimeout = max(2000, min(30000, (int) ($player?->timeoutMs ?? $config->get('player.load_timeout_ms', 8000))));
$isHlsPlayer = $hasEmbed && $player?->mode === \LiveCamForge\Providers\ProviderPlayer::MODE_HLS;
$hasHlsFallback = $hasEmbed
    && $player?->fallbackMode === \LiveCamForge\Providers\ProviderPlayer::MODE_HLS
    && is_string($playerFallbackUrl)
    && $playerFallbackUrl !== '';
$needsHls = $isHlsPlayer || $hasHlsFallback;
$sandboxPlayerWrapper = $hasEmbed
    && $player?->mode === \LiveCamForge\Providers\ProviderPlayer::MODE_SCRIPT
    && $player->sandboxWrapper;
$playerFallbackTimeout = max(2000, min(30000, (int) ($player?->fallbackTimeoutMs ?? $playerTimeout)));
$playerRatioWidth = max(1, min(32, (int) $config->get('player.aspect_ratio_width', 16)));
$playerRatioHeight = max(1, min(32, (int) $config->get('player.aspect_ratio_height', 9)));
$externalRel = $providerCapabilities->affiliateLinks ? 'nofollow sponsored' : 'nofollow';
$showProviderIdentity = (bool) ($catalog['show_provider_filter'] ?? true)
    || (bool) ($catalog['show_provider_badges'] ?? true);
$modelMetaDescription = $showProviderIdentity
    ? $translator->get('model.meta_description', ['name' => $performer['display_name'], 'provider' => $publicProviderName])
    : $translator->get('model.meta_description_generic', ['name' => $performer['display_name']]);
$assetVersion = rawurlencode((string) $config->get('version'));
?>
<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($performer['display_name']) ?> · <?= e($siteAppearance['site_name']) ?></title>
    <meta name="description" content="<?= e($modelMetaDescription) ?>">
    <meta name="robots" content="<?= $performerPolicy->indexPerformerPages ? 'index,follow' : 'noindex,follow' ?>">
    <?php if ((bool) $config->get('seo.adult_rating', true)): ?><meta name="rating" content="adult"><?php endif; ?>
    <link rel="canonical" href="<?= e($siteUrl->absolute('model/' . rawurlencode((string) $performer['provider']) . '/' . rawurlencode((string) $performer['username']) . '/')) ?>">
    <meta property="og:type" content="profile"><meta property="og:title" content="<?= e($performer['display_name']) ?>"><meta property="og:description" content="<?= e($modelMetaDescription) ?>"><meta property="og:image" content="<?= e($performer['preview_url'] ?: $performer['image_url']) ?>">
    <?php if ($performerPolicy->indexPerformerPages): ?><script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'mainEntity' => ['@type' => 'Person', 'name' => (string) $performer['display_name'], 'alternateName' => (string) $performer['username']]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
    <link rel="stylesheet" href="<?= e($assetUrl('app.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('gender-badges.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('card-links.css')) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('model-page.css')) ?>?v=<?= e($assetVersion) ?>">
    <link rel="stylesheet" href="<?= e($assetUrl('room-status.css')) ?>">
    <?php require $root . '/templates/partials/theme.php'; ?>
    <script src="<?= e($assetUrl('media-fallback.js')) ?>" defer></script>
    <?php if ($needsHls): ?><script src="<?= e($assetUrl('vendor/hls.min.js')) ?>?v=<?= e($assetVersion) ?>" defer></script><?php endif; ?>
    <?php if ($hasEmbed): ?><script src="<?= e($assetUrl('live-player.js')) ?>?v=<?= e($assetVersion) ?>" defer></script><?php endif; ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e($siteUrl->path()) ?>"><?php require $root . '/templates/partials/brand.php'; ?></a>
    <div class="status"><i class="<?= $isOnline && $isPublicRoom ? '' : 'offline-dot' ?>"></i><?= e($isOnline ? $roomStatusLabel : $translator->get('model.offline')) ?></div>
</header>

<main class="container model-container">
    <a class="back-link" href="<?= e($catalogBackUrl) ?>"><?= e($translator->get('model.back_to_catalog')) ?></a>

    <section class="model-hero panel">
        <div class="model-preview<?= $hasEmbed ? ' live-player' : '' ?>"
             style="--player-aspect-ratio: <?= e($playerRatioWidth) ?> / <?= e($playerRatioHeight) ?>"
             <?= $hasEmbed ? 'data-live-player data-timeout="' . e($playerTimeout) . '" data-fallback-timeout="' . e($playerFallbackTimeout) . '"' : '' ?>>
            <img
                class="player-preview"
                src="<?= e(\LiveCamForge\Core\DemoMode::isDemoProvider((string) $performer['provider'])
                    ? $mediaUrl((string) $performer['provider'], (string) $performer['username'])
                    : ($performer['preview_url'] ?: $performer['image_url'])) ?>"
                data-fallback-src="<?= e($mediaUrl((string) $performer['provider'], (string) $performer['username'])) ?>"
                alt="<?= e($performer['display_name']) ?>"
                referrerpolicy="no-referrer"
            >
            <?php if ($hasEmbed): ?>
                <div class="player-loading" data-player-loading>
                    <span class="player-spinner" aria-hidden="true"></span>
                    <strong><?= e($translator->get('model.player_loading')) ?></strong>
                </div>
                <?php if ($isHlsPlayer): ?>
                    <video
                        class="player-frame"
                        data-player-video
                        data-src="<?= e($playerFrameUrl) ?>"
                        title="<?= e($translator->get('model.player_title', ['name' => $performer['display_name']])) ?>"
                        controls
                        muted
                        autoplay
                        playsinline
                    ></video>
                <?php else: ?>
                    <iframe
                        class="player-frame"
                        data-player-frame
                        data-src="<?= e($playerFrameUrl) ?>"
                        title="<?= e($translator->get('model.player_title', ['name' => $performer['display_name']])) ?>"
                        allow="autoplay; fullscreen"
                        referrerpolicy="strict-origin-when-cross-origin"
                        <?= $sandboxPlayerWrapper ? 'sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox"' : '' ?>
                    ></iframe>
                <?php endif; ?>
                <?php if ($hasHlsFallback): ?>
                    <video
                        class="player-frame player-secondary"
                        data-player-fallback-video
                        data-src="<?= e($playerFallbackUrl) ?>"
                        title="<?= e($translator->get('model.player_title', ['name' => $performer['display_name']])) ?>"
                        controls
                        muted
                        autoplay
                        playsinline
                        hidden
                    ></video>
                <?php endif; ?>
                <div class="player-fallback" data-player-fallback hidden>
                    <strong><?= e($translator->get('model.player_unavailable')) ?></strong>
                    <?php if ($hasRoom): ?>
                        <a href="<?= e($goUrl((string) $performer['provider'], (string) $performer['username'])) ?>" target="_blank" rel="<?= e($externalRel) ?>"><?= e($translator->get('model.open_full_room')) ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="model-details">
            <?php if ($showProviderIdentity): ?><p class="eyebrow"><?= e($publicProviderName) ?></p><?php endif; ?>
            <h1><?= e($performer['display_name']) ?></h1>
            <p class="model-username">@<?= e($performer['username']) ?></p>

            <div class="model-facts">
                <span><?= e($gender['symbol']) ?> <?= e($genderLabel) ?></span>
                <?php if ($providerCapabilities->age && $performer['age']): ?><span><?= e($translator->get('model.age', ['age' => $performer['age']])) ?></span><?php endif; ?>
                <?php if ($providerCapabilities->viewers && $performer['viewers'] !== null): ?><span><?= e($translator->get('performer.viewers', ['count' => $performer['viewers']])) ?></span><?php endif; ?>
            </div>

            <?php if ($providerCapabilities->tags && $tags): ?>
                <div class="model-tags"><?php foreach ($tags as $tag): ?><a href="<?= e($tagUrl((string) $tag)) ?>">#<?= e($tag) ?></a><?php endforeach; ?></div>
            <?php endif; ?>

            <?php if ($isOnline && $hasReliableRoomStatus && !$isPublicRoom && $blockNonPublicRooms): ?>
                <div class="alert offline-alert"><?= e($translator->get('model.non_public_message')) ?></div>
            <?php elseif ($hasRoom): ?>
                <a class="button model-cta" href="<?= e($goUrl((string) $performer['provider'], (string) $performer['username'])) ?>" target="_blank" rel="<?= e($externalRel) ?>"><?= e($translator->get('model.open_full_room')) ?></a>
                <p class="cta-note"><?= e($showProviderIdentity
                    ? $translator->get('model.external_note', ['provider' => $publicProviderName])
                    : $translator->get('model.external_note_generic')) ?></p>
            <?php elseif (!$isOnline): ?>
                <div class="alert offline-alert"><?= e($translator->get('model.offline_message')) ?></div>
            <?php else: ?>
                <div class="alert offline-alert"><?= e($translator->get('model.room_unavailable')) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($similarPerformers): ?>
        <section class="similar-section">
            <p class="eyebrow"><?= e($translator->get('model.keep_exploring')) ?></p>
            <h2><?= e($translator->get('model.similar_performers')) ?></h2>
            <div class="cards similar-cards">
                <?php foreach ($similarPerformers as $performer) { require $root . '/templates/partials/performer-card.php'; } ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<footer><?= $siteAppearance['footer_text'] !== '' ? e($siteAppearance['footer_text']) : e($siteAppearance['site_name']) ?></footer>
</body>
</html>
