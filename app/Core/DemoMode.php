<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class DemoMode
{
    /** @return list<string> */
    public static function providerNames(): array
    {
        return ['demo_alpha', 'demo_beta'];
    }

    public static function enabled(Config $config): bool
    {
        return (bool) $config->get('demo_mode.enabled', false);
    }

    public static function isDemoProvider(string $provider): bool
    {
        return in_array(strtolower(trim($provider)), self::providerNames(), true);
    }

    /** @return array{provider:string,providers:array{enabled:list<string>},catalog:array{mode:string}} */
    public static function runtimeConfiguration(): array
    {
        return [
            'provider' => 'demo_alpha',
            'providers' => ['enabled' => self::providerNames()],
            'catalog' => ['mode' => 'combined'],
        ];
    }

    public static function modelRecruitmentUrl(): string
    {
        return 'https://livecamforge.com/become-a-model/';
    }

    public static function webmasterRecruitmentUrl(): string
    {
        return 'https://livecamforge.com/for-webmasters/';
    }

    /** @return list<string> */
    public static function blockedAdminActions(): array
    {
        return [
            'save_configuration_all',
            'save_integrations',
            'save_integrations_all',
            'save_provider_configuration',
            'test_crakrevenue_access',
            'reset_operational_settings',
            'test_postback',
        ];
    }
}
