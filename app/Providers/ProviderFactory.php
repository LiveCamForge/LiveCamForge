<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

use LiveCamForge\Core\Config;
use LiveCamForge\Providers\Chaturbate\ChaturbateAdapter;
use LiveCamForge\Providers\BongaCams\BongaCamsAdapter;
use LiveCamForge\Providers\Cam4\Cam4Adapter;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter;
use LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter;
use LiveCamForge\Providers\Stripchat\StripchatAdapter;
use RuntimeException;

final class ProviderFactory
{
    /** @return list<string> */
    public static function availableNames(): array
    {
        return [
            'demo',
            'demo_alpha',
            'demo_beta',
            'chaturbate',
            'livejasmin',
            'bongacams',
            'cam4',
            'stripchat',
            ...CrakRevenueAdapter::providerNames(),
        ];
    }

    /**
     * Route groups expose one mutually exclusive affiliate source per network.
     *
     * @return array<string, array{label: string, options: list<string>}>
     */
    public static function affiliateRouteGroups(): array
    {
        return [
            'mfc' => ['label' => 'MyFreeCams', 'options' => ['crakrevenue_mfc']],
            'streamate' => ['label' => 'Jerkmate', 'options' => ['crakrevenue_streamate']],
            'chaturbate' => ['label' => 'Chaturbate', 'options' => ['chaturbate', 'crakrevenue_chaturbate']],
            'livejasmin' => ['label' => 'LiveJasmin', 'options' => ['livejasmin', 'crakrevenue_awempire']],
            'stripchat' => ['label' => 'Stripchat', 'options' => ['stripchat', 'crakrevenue_stripchat']],
            'imlive' => ['label' => 'ImLive', 'options' => ['crakrevenue_imlive']],
            'bongacams' => ['label' => 'BongaCams', 'options' => ['bongacams', 'crakrevenue_bongacash']],
        ];
    }

    /**
     * Returns the commercial network name exposed to visitors.
     *
     * The public catalog must not reveal whether the webmaster selected a
     * direct integration or the equivalent CrakRevenue route. Adapter
     * displayName() values remain intentionally technical for the Admin.
     */
    public static function publicDisplayName(string $name, Config $config, string $root): string
    {
        $normalized = strtolower(trim($name));
        foreach (self::affiliateRouteGroups() as $group) {
            if (in_array($normalized, $group['options'], true)) {
                return $group['label'];
            }
        }

        return self::make($normalized, $config, $root)->displayName();
    }

    /** @return list<string> */
    public static function routedProviderNames(): array
    {
        $names = [];
        foreach (self::affiliateRouteGroups() as $group) {
            foreach ($group['options'] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /** @return list<string> */
    public static function enabledNames(Config $config): array
    {
        $configured = $config->get('providers.enabled', []);
        $names = is_array($configured) ? $configured : [];
        $active = trim((string) $config->get('provider', 'demo'));
        if ($active !== '') {
            array_unshift($names, $active);
        }

        $names = array_values(array_unique(array_filter(array_map(
            static fn (mixed $name): string => strtolower(trim((string) $name)),
            $names
        ))));

        $routeByProvider = [];
        foreach (self::affiliateRouteGroups() as $route => $group) {
            foreach ($group['options'] as $providerName) {
                $routeByProvider[$providerName] = $route;
            }
        }
        $selectedRoutes = [];
        $names = array_values(array_filter($names, static function (string $name) use ($routeByProvider, &$selectedRoutes): bool {
            $route = $routeByProvider[$name] ?? null;
            if ($route === null) {
                return true;
            }
            if (isset($selectedRoutes[$route])) {
                return false;
            }
            $selectedRoutes[$route] = true;
            return true;
        }));

        return $names !== [] ? $names : ['demo'];
    }

    public static function isEnabled(string $name, Config $config): bool
    {
        return in_array(strtolower(trim($name)), self::enabledNames($config), true);
    }

    public static function make(string $name, Config $config, string $root): ProviderInterface
    {
        return match ($name) {
            'demo' => new DemoProvider($root . '/database/demo-performers.json'),
            'demo_alpha' => new DemoProvider($root . '/database/demo-alpha-performers.json', 'demo_alpha', 'Demo Alpha'),
            'demo_beta' => new DemoProvider($root . '/database/demo-beta-performers.json', 'demo_beta', 'Demo Beta'),
            'chaturbate' => new ChaturbateAdapter($config),
            'bongacams' => new BongaCamsAdapter($config),
            'cam4' => new Cam4Adapter($config),
            'livejasmin' => new LiveJasminAdapter($config),
            'stripchat' => new StripchatAdapter($config, $root),
            'crakrevenue_mfc',
            'crakrevenue_streamate',
            'crakrevenue_chaturbate',
            'crakrevenue_awempire',
            'crakrevenue_stripchat',
            'crakrevenue_imlive',
            'crakrevenue_bongacash' => new CrakRevenueAdapter($config, $name),
            default => throw new RuntimeException("Unsupported provider: {$name}"),
        };
    }
}
