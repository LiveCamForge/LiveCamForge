<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

use LiveCamForge\Models\Performer;

interface ProviderInterface
{
    public function name(): string;

    public function displayName(): string;

    public function capabilities(): ProviderCapabilities;

    /** @return list<Performer> */
    public function fetch(): array;

    /** Build the provider-specific player for a normalized performer record. */
    public function player(array $performer, array $options = []): ?ProviderPlayer;

    /** Resolve a deferred provider player before it is rendered by the isolated local wrapper. */
    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer;

    /** Validate a normalized embed URL according to this provider's policy. */
    public function isEmbedUrlAllowed(string $url): bool;

    /** Validate an outbound performer destination before recording and redirecting a click. */
    public function isRoomUrlAllowed(string $url): bool;

    /** Validate a normalized image URL before the optional same-origin proxy fetches it. */
    public function isMediaUrlAllowed(string $url): bool;
}
