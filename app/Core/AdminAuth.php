<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use LiveCamForge\Repositories\SettingsRepository;

final class AdminAuth
{
    private const USERNAME_KEY = 'admin.username';
    private const PASSWORD_KEY = 'admin.password_hash';

    public function __construct(
        private SettingsRepository $settings,
        private int $idleTimeoutSeconds = 3600,
    ) {
        $this->idleTimeoutSeconds = max(300, min(86400, $this->idleTimeoutSeconds));
    }

    public function isConfigured(): bool
    {
        return $this->settings->get(self::USERNAME_KEY) !== null
            && $this->settings->get(self::PASSWORD_KEY) !== null;
    }

    public function setup(string $username, string $password): void
    {
        $this->settings->set(self::USERNAME_KEY, trim($username));
        $this->settings->set(self::PASSWORD_KEY, password_hash($password, PASSWORD_DEFAULT));
        $this->regenerateAndLogin(trim($username));
    }

    public function attempt(string $username, string $password): bool
    {
        $storedUsername = $this->settings->get(self::USERNAME_KEY);
        $passwordHash = $this->settings->get(self::PASSWORD_KEY);
        if ($storedUsername === null || $passwordHash === null
            || !hash_equals($storedUsername, trim($username))
            || !password_verify($password, $passwordHash)
        ) {
            return false;
        }

        $this->regenerateAndLogin($storedUsername);
        return true;
    }

    public function check(): bool
    {
        if (!isset($_SESSION['livecamforge_admin']) || $_SESSION['livecamforge_admin'] !== true) {
            return false;
        }

        $lastActivity = (int) ($_SESSION['livecamforge_admin_last_activity'] ?? 0);
        if ($lastActivity > 0 && $lastActivity < time() - $this->idleTimeoutSeconds) {
            unset(
                $_SESSION['livecamforge_admin'],
                $_SESSION['livecamforge_admin_username'],
                $_SESSION['livecamforge_admin_last_activity']
            );
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return false;
        }

        $_SESSION['livecamforge_admin_last_activity'] = time();
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict',
            ]);
        }
        session_destroy();
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function regenerateAndLogin(string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['livecamforge_admin'] = true;
        $_SESSION['livecamforge_admin_username'] = $username;
        $_SESSION['livecamforge_admin_last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
