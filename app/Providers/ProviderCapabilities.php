<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $embed,
        public bool $roomStatus,
        public bool $age,
        public bool $viewers,
        public bool $tags,
        public bool $mediaProxy,
        public bool $affiliateLinks,
        public bool $offlineFallback,
        public bool $geoRestrictions = false,
        public bool $postbackTracking = false,
        public bool $conversionPolling = false,
    ) {
    }

    public function enabled(): array
    {
        return array_keys(array_filter([
            'embed' => $this->embed,
            'room_status' => $this->roomStatus,
            'age' => $this->age,
            'viewers' => $this->viewers,
            'tags' => $this->tags,
            'media_proxy' => $this->mediaProxy,
            'affiliate_links' => $this->affiliateLinks,
            'offline_fallback' => $this->offlineFallback,
            'geo_restrictions' => $this->geoRestrictions,
            'postback_tracking' => $this->postbackTracking,
            'conversion_polling' => $this->conversionPolling,
        ]));
    }
}
