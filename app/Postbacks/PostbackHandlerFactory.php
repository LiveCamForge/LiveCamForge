<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;
use RuntimeException;

final class PostbackHandlerFactory
{
    public static function supports(string $provider): bool
    {
        $provider = strtolower(trim($provider));
        return in_array($provider, ['chaturbate', 'livejasmin', 'stripchat', 'crakrevenue'], true)
            || str_starts_with($provider, 'crakrevenue_');
    }

    public static function make(
        string $provider,
        Config $config,
        ClickRepository $clicks,
        ConversionRepository $conversions,
    ): PostbackHandlerInterface {
        $provider = strtolower(trim($provider));
        if ($provider === 'crakrevenue' || str_starts_with($provider, 'crakrevenue_')) {
            return new CrakRevenuePostbackHandler($config, $clicks, $conversions);
        }
        return match ($provider) {
            'chaturbate' => new ChaturbatePostbackHandler($config, $clicks, $conversions),
            'livejasmin' => new LiveJasminPostbackHandler($config, $clicks, $conversions),
            'stripchat' => new StripchatPostbackHandler($config, $clicks, $conversions),
            default => throw new RuntimeException('Unsupported postback provider.'),
        };
    }
}
