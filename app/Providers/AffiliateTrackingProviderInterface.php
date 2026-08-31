<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

interface AffiliateTrackingProviderInterface
{
    /** Add a provider-supported click identifier to an already validated affiliate URL. */
    public function trackedRoomUrl(string $url, string $sid, string $track): string;
}
