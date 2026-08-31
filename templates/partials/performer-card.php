<?php
$cardGenderMeta = [
    'f' => ['symbol' => '♀', 'key' => 'performer.gender.female', 'class' => 'female'],
    'm' => ['symbol' => '♂', 'key' => 'performer.gender.male', 'class' => 'male'],
    't' => ['symbol' => '⚧', 'key' => 'performer.gender.trans', 'class' => 'trans'],
    'c' => ['symbol' => '👥', 'key' => 'performer.gender.couple', 'class' => 'couple'],
];
$tags = json_decode((string) $performer['tags_json'], true) ?: [];
$gender = $cardGenderMeta[$performer['gender']] ?? ['symbol' => '•', 'key' => 'performer.gender.other', 'class' => 'other'];
$genderLabel = $translator->get($gender['key']);
$performerProfileUrl = $profileUrl((string) $performer['provider'], (string) $performer['username']);
$roomStatus = in_array($performer['room_status'] ?? '', ['public', 'private', 'group', 'away'], true)
    ? (string) $performer['room_status']
    : 'unknown';
$cardProviderCapabilities = $providerCapabilitiesByName[$performer['provider']] ?? null;
$hasReliableRoomStatus = $cardProviderCapabilities?->roomStatus ?? true;
$effectiveRoomStatus = $hasReliableRoomStatus ? $roomStatus : 'public';
$isPublicRoom = $effectiveRoomStatus === 'public';
$blockNonPublicRooms = (bool) $config->get('rooms.block_non_public', true);
$canOpenRoom = $isPublicRoom || !$blockNonPublicRooms;
$roomStatusLabel = $translator->get('room_status.' . $effectiveRoomStatus);
?>
<article class="card">
    <div class="image-wrap">
        <?php if ($canOpenRoom): ?><a class="card-image-link" href="<?= e($performerProfileUrl) ?>" aria-label="<?= e($translator->get('performer.view_profile_of', ['name' => $performer['display_name']])) ?>"><?php else: ?><div class="card-image-link room-disabled" aria-label="<?= e($roomStatusLabel) ?>"><?php endif; ?>
            <?php $cardImageUrl = \LiveCamForge\Core\DemoMode::isDemoProvider((string) $performer['provider'])
                ? $mediaUrl((string) $performer['provider'], (string) $performer['username'])
                : ($performer['preview_url'] ?: $performer['image_url']); ?>
            <img
                src="<?= e($cardImageUrl) ?>"
                data-fallback-src="<?= e($mediaUrl((string) $performer['provider'], (string) $performer['username'])) ?>"
                alt="<?= e($performer['display_name']) ?>"
                loading="lazy"
                referrerpolicy="no-referrer"
            >
        <?= $canOpenRoom ? '</a>' : '</div>' ?>
        <span class="live room-status-badge <?= e($effectiveRoomStatus) ?>"><?= e($roomStatusLabel) ?></span>
        <?php if ((int) ($performer['provider_is_new'] ?? 0) === 1): ?><span class="new-badge" aria-label="<?= e($translator->get('performer.new_label')) ?>"><?= e($translator->get('performer.new_badge')) ?></span><?php endif; ?>
        <span class="gender-badge <?= e($gender['class']) ?>" aria-label="<?= e($translator->get('performer.gender_label', ['gender' => $genderLabel])) ?>"><?= e($gender['symbol']) ?> <?= e($genderLabel) ?></span>
        <?php if ($cardProviderCapabilities?->viewers && $performer['viewers'] !== null): ?><span class="viewers"><?= e($translator->get('performer.viewers', ['count' => $performer['viewers']])) ?></span><?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($showProviderBadges): ?><span class="provider-badge"><?= e($providerLabels[$performer['provider']] ?? ucfirst((string) $performer['provider'])) ?></span><?php endif; ?>
        <h2><?php if ($canOpenRoom): ?><a class="card-title-link" href="<?= e($performerProfileUrl) ?>"><?= e($performer['display_name']) ?></a><?php else: ?><?= e($performer['display_name']) ?><?php endif; ?></h2>
        <p>@<?= e($performer['username']) ?><?= $performer['age'] ? ' · ' . e($performer['age']) : '' ?></p>
        <div class="tags"><?php foreach (array_slice($tags, 0, 4) as $tag): ?><a href="<?= e($tagUrl((string) $tag)) ?>">#<?= e($tag) ?></a><?php endforeach; ?></div>
        <?php if ($canOpenRoom): ?><a class="room-link" href="<?= e($performerProfileUrl) ?>"><?= e($translator->get('performer.view_profile')) ?></a><?php else: ?><span class="room-link room-link-disabled"><?= e($roomStatusLabel) ?></span><?php endif; ?>
    </div>
</article>
