<?php

declare(strict_types=1);

namespace LiveCamForge\Models;

final class Performer
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerId,
        public readonly string $username,
        public readonly string $displayName,
        public readonly ?string $gender,
        public readonly ?int $age,
        public readonly ?string $imageUrl,
        public readonly ?string $previewUrl,
        public readonly ?string $embedUrl,
        public readonly string $roomStatus,
        public readonly string $roomUrl,
        public readonly ?int $viewers,
        public readonly array $tags,
        public readonly bool $online,
        public readonly ?bool $providerNew = null,
        public readonly array $geoBlocks = [],
        public readonly ?string $countryCode = null,
        public readonly ?float $popularityScore = null,
        public readonly ?int $watchSortScore = null,
        public readonly ?int $providerSortScore = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_id' => $this->providerId,
            'username' => $this->username,
            'display_name' => $this->displayName,
            'gender' => $this->gender,
            'age' => $this->age,
            'image_url' => $this->imageUrl,
            'preview_url' => $this->previewUrl,
            'embed_url' => $this->embedUrl,
            'room_status' => $this->roomStatus,
            'room_url' => $this->roomUrl,
            'viewers' => $this->viewers,
            'popularity_score' => $this->popularityScore,
            'watch_sort_score' => $this->watchSortScore,
            'provider_sort_score' => $this->providerSortScore,
            'tags' => $this->tags,
            'online' => $this->online,
            'provider_is_new' => $this->providerNew,
            'geo_blocks' => $this->geoBlocks,
            'country_code' => $this->countryCode,
        ];
    }

    public function withPopularityScore(?float $popularityScore): self
    {
        return $this->copy(
            popularityScore: $popularityScore === null ? null : max(0.0, min(1.0, $popularityScore)),
            watchSortScore: $this->watchSortScore,
            providerSortScore: $this->providerSortScore,
        );
    }

    public function withSortScores(bool $publicPriority): self
    {
        $popularity = max(0.0, min(1.0, $this->popularityScore ?? 0.0));
        $popularityMicros = (int) round($popularity * 1000000);
        $viewerCount = $this->viewers === null ? null : max(0, min(4294967295, $this->viewers));
        $watchContent = $viewerCount === null
            ? $popularityMicros
            : 4000000000000000000 + ($viewerCount * 1000000) + $popularityMicros;
        $watchSortScore = ($publicPriority ? 4500000000000000000 : 0) + $watchContent;
        $providerSortScore = ($publicPriority ? 1000000000000000000 : 0)
            + ($this->popularityScore === null ? 0 : 20000000000000000)
            + ($popularityMicros * 10000000000)
            + ($viewerCount === null ? 0 : 5000000000 + $viewerCount);

        return $this->copy($this->popularityScore, $watchSortScore, $providerSortScore);
    }

    private function copy(
        ?float $popularityScore,
        ?int $watchSortScore,
        ?int $providerSortScore,
    ): self {
        return new self(
            provider: $this->provider,
            providerId: $this->providerId,
            username: $this->username,
            displayName: $this->displayName,
            gender: $this->gender,
            age: $this->age,
            imageUrl: $this->imageUrl,
            previewUrl: $this->previewUrl,
            embedUrl: $this->embedUrl,
            roomStatus: $this->roomStatus,
            roomUrl: $this->roomUrl,
            viewers: $this->viewers,
            tags: $this->tags,
            online: $this->online,
            providerNew: $this->providerNew,
            geoBlocks: $this->geoBlocks,
            countryCode: $this->countryCode,
            popularityScore: $popularityScore,
            watchSortScore: $watchSortScore,
            providerSortScore: $providerSortScore,
        );
    }
}
