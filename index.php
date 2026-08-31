<?php

declare(strict_types=1);

$installed = is_file(__DIR__ . '/config/local.php');
if (!$installed) {
    header('Location: install/');
    exit;
}

require __DIR__ . '/public/index.php';
