<?php

declare(strict_types=1);

namespace LiveCamForge\Database;

use LiveCamForge\Core\Config;
use PDO;

final class Connection
{
    public static function make(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->get('database.host'),
            $config->get('database.port', 3306),
            $config->get('database.name'),
            $config->get('database.charset', 'utf8mb4')
        );

        return new PDO($dsn, (string) $config->get('database.user'), (string) $config->get('database.password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

