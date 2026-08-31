<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

use InvalidArgumentException;

final readonly class ProviderPlayer
{
    public const MODE_IFRAME = 'iframe';
    public const MODE_SCRIPT = 'script';
    public const MODE_WRAPPED_IFRAME = 'wrapped_iframe';
    public const MODE_HLS = 'hls';

    public function __construct(
        public string $mode,
        public string $url,
        public ?int $timeoutMs = null,
        public ?string $fallbackMode = null,
        public ?string $fallbackUrl = null,
        public ?int $fallbackTimeoutMs = null,
        public bool $sandboxWrapper = false,
    ) {
        $modes = [self::MODE_IFRAME, self::MODE_SCRIPT, self::MODE_WRAPPED_IFRAME, self::MODE_HLS];
        if (!in_array($this->mode, $modes, true)) {
            throw new InvalidArgumentException('Unsupported provider player mode.');
        }
        if ($this->timeoutMs !== null && $this->timeoutMs < 2000) {
            throw new InvalidArgumentException('The provider player timeout must be at least 2000 milliseconds.');
        }
        if (($this->fallbackMode === null) !== ($this->fallbackUrl === null)) {
            throw new InvalidArgumentException('The provider player fallback mode and URL must be configured together.');
        }
        if ($this->fallbackMode !== null && !in_array($this->fallbackMode, $modes, true)) {
            throw new InvalidArgumentException('Unsupported provider player fallback mode.');
        }
        if ($this->fallbackTimeoutMs !== null && $this->fallbackTimeoutMs < 2000) {
            throw new InvalidArgumentException('The provider player fallback timeout must be at least 2000 milliseconds.');
        }
    }
}
