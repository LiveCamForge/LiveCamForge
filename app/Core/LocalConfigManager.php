<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use RuntimeException;

/**
 * Safely updates the machine-local configuration file from the authenticated Admin UI.
 * Secrets remain file-owned and are never stored in the database.
 */
final class LocalConfigManager
{
    private string $path;

    public function __construct(private string $root)
    {
        $this->path = rtrim($root, '/\\') . '/config/local.php';
    }

    public function writable(): bool
    {
        return is_file($this->path) && is_readable($this->path) && is_writable($this->path)
            && is_writable(dirname($this->path));
    }

    /** @return array<string, mixed> */
    public function values(Config $config): array
    {
        return [
            'seo_base_url' => (string) $config->get('seo.base_url', ''),
            'chaturbate_wm' => (string) $config->get('chaturbate.wm', ''),
            'chaturbate_postback_secret_set' => trim((string) $config->get('chaturbate.postback.validation_salt', '')) !== '',
            'bongacams_campaign_id' => (int) $config->get('bongacams.campaign_id', 0),
            'bongacams_client_ip' => (string) $config->get('bongacams.client_ip', ''),
            'cam4_affiliate_id' => (int) $config->get('cam4.affiliate_id', 0),
            'cam4_tune_network_id' => (string) $config->get('cam4.tune.network_id', 'cam4com'),
            'cam4_tune_api_key_set' => trim((string) $config->get('cam4.tune.api_key', '')) !== '',
            'livejasmin_ps_id' => (string) $config->get('livejasmin.ps_id', ''),
            'livejasmin_access_key_set' => trim((string) $config->get('livejasmin.access_key', '')) !== '',
            'livejasmin_postback_secret_set' => trim((string) $config->get('livejasmin.postback.secret', '')) !== '',
            'stripchat_user_id' => (string) $config->get('stripchat.user_id', ''),
            'stripchat_api_key_set' => trim((string) $config->get('stripchat.api_key', '')) !== '',
            'stripchat_postback_secret_set' => trim((string) $config->get('stripchat.postback.secret', '')) !== '',
            'crakrevenue_api_key_set' => trim((string) $config->get('crakrevenue.api_key', '')) !== '',
            'crakrevenue_token_set' => trim((string) $config->get('crakrevenue.token', '')) !== '',
            'crakrevenue_postback_secret_set' => trim((string) $config->get('crakrevenue.postback.secret', '')) !== '',
        ];
    }

    public function saveProviderConfiguration(array $input): void
    {
        if (!$this->writable()) {
            throw new RuntimeException('config/local.php or its directory is not writable by PHP.');
        }

        $config = require $this->path;
        if (!is_array($config)) {
            throw new RuntimeException('config/local.php does not return a valid PHP array.');
        }

        if (array_key_exists('seo_base_url', $input)) {
            $baseUrl = rtrim(trim((string) $input['seo_base_url']), '/');
            if ($baseUrl !== '') {
                $parts = parse_url($baseUrl);
                $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
                if (!is_array($parts)
                    || !in_array($scheme, ['http', 'https'], true)
                    || empty($parts['host'])
                    || isset($parts['user'])
                    || isset($parts['pass'])
                    || isset($parts['query'])
                    || isset($parts['fragment'])
                ) {
                    throw new RuntimeException('The canonical base URL must be a valid HTTP/HTTPS URL without credentials, query string or fragment.');
                }
            }
            $config['seo'] = $this->section($config, 'seo');
            $config['seo']['base_url'] = $baseUrl;
        }

        $wm = $this->text($input['chaturbate_wm'] ?? '', 120);
        $campaignId = max(0, (int) ($input['bongacams_campaign_id'] ?? 0));
        $clientIp = trim((string) ($input['bongacams_client_ip'] ?? ''));
        if ($clientIp !== '' && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException('The BongaCams manual client IP must be a valid IPv4 address.');
        }
        $cam4AffiliateId = max(0, (int) ($input['cam4_affiliate_id'] ?? 0));
        $cam4NetworkId = $this->token($input['cam4_tune_network_id'] ?? 'cam4com', 100, true);
        $liveJasminPsId = $this->text($input['livejasmin_ps_id'] ?? '', 120);
        $stripchatUserId = $this->text($input['stripchat_user_id'] ?? '', 200);

        $config['chaturbate'] = $this->section($config, 'chaturbate');
        $config['chaturbate']['wm'] = $wm;
        $config['chaturbate']['postback'] = $this->subsection($config['chaturbate'], 'postback');
        $this->updateSecret($config['chaturbate']['postback'], 'validation_salt', $input, 'chaturbate_postback_validation_salt');
        $config['chaturbate']['postback']['require_checksum'] = (bool) ($config['chaturbate']['postback']['require_checksum'] ?? true);

        $config['bongacams'] = $this->section($config, 'bongacams');
        $config['bongacams']['campaign_id'] = $campaignId;
        $config['bongacams']['client_ip'] = $clientIp;

        $config['cam4'] = $this->section($config, 'cam4');
        $config['cam4']['affiliate_id'] = $cam4AffiliateId;
        $config['cam4']['tune'] = $this->subsection($config['cam4'], 'tune');
        $config['cam4']['tune']['network_id'] = $cam4NetworkId !== '' ? $cam4NetworkId : 'cam4com';
        $this->updateSecret($config['cam4']['tune'], 'api_key', $input, 'cam4_tune_api_key');

        $config['livejasmin'] = $this->section($config, 'livejasmin');
        $config['livejasmin']['ps_id'] = $liveJasminPsId;
        $this->updateSecret($config['livejasmin'], 'access_key', $input, 'livejasmin_access_key');
        $config['livejasmin']['postback'] = $this->subsection($config['livejasmin'], 'postback');
        $this->updateSecret($config['livejasmin']['postback'], 'secret', $input, 'livejasmin_postback_secret');
        $config['livejasmin']['postback']['require_secret'] = (bool) ($config['livejasmin']['postback']['require_secret'] ?? true);

        $config['stripchat'] = $this->section($config, 'stripchat');
        $config['stripchat']['user_id'] = $stripchatUserId;
        $this->updateSecret($config['stripchat'], 'api_key', $input, 'stripchat_api_key');
        $config['stripchat']['postback'] = $this->subsection($config['stripchat'], 'postback');
        $this->updateSecret($config['stripchat']['postback'], 'secret', $input, 'stripchat_postback_secret');
        $config['stripchat']['postback']['require_secret'] = (bool) ($config['stripchat']['postback']['require_secret'] ?? true);

        $config['crakrevenue'] = $this->section($config, 'crakrevenue');
        $this->updateSecret($config['crakrevenue'], 'api_key', $input, 'crakrevenue_api_key');
        $this->updateSecret($config['crakrevenue'], 'token', $input, 'crakrevenue_token');
        $config['crakrevenue']['postback'] = $this->subsection($config['crakrevenue'], 'postback');
        $this->updateSecret($config['crakrevenue']['postback'], 'secret', $input, 'crakrevenue_postback_secret');
        $config['crakrevenue']['postback']['require_secret'] = (bool) ($config['crakrevenue']['postback']['require_secret'] ?? true);

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        $temporary = dirname($this->path) . '/.local.php.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the temporary local configuration file.');
        }
        @chmod($temporary, 0600);
        $loaded = require $temporary;
        if (!is_array($loaded)) {
            @unlink($temporary);
            throw new RuntimeException('The generated local configuration is invalid.');
        }
        if (!@rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace config/local.php atomically.');
        }
        @chmod($this->path, 0600);
    }

    /** @param array<string, mixed> $section */
    private function updateSecret(array &$section, string $key, array $input, string $field): void
    {
        if (isset($input['clear_' . $field])) {
            $section[$key] = '';
            return;
        }
        $value = trim((string) ($input[$field] ?? ''));
        if ($value === '') {
            return; // Empty secret fields intentionally preserve the existing value.
        }
        if (strlen($value) > 4096 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            throw new RuntimeException('A secret value is invalid or too long.');
        }
        $section[$key] = $value;
    }

    /** @return array<string, mixed> */
    private function section(array $config, string $key): array
    {
        return isset($config[$key]) && is_array($config[$key]) ? $config[$key] : [];
    }

    /** @return array<string, mixed> */
    private function subsection(array $section, string $key): array
    {
        return isset($section[$key]) && is_array($section[$key]) ? $section[$key] : [];
    }

    private function text(mixed $value, int $max): string
    {
        $value = trim((string) $value);
        if (strlen($value) > $max || preg_match('/[\x00-\x1F]/', $value) === 1) {
            throw new RuntimeException('A provider configuration value is invalid.');
        }
        return $value;
    }

    private function token(mixed $value, int $max, bool $allowEmpty = false): string
    {
        $value = trim((string) $value);
        if ($value === '' && $allowEmpty) {
            return '';
        }
        if (strlen($value) > $max || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            throw new RuntimeException('A provider token/identifier is invalid.');
        }
        return $value;
    }
}
