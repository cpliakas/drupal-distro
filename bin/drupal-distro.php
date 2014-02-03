<?php

use Drupal\Distro\Console as Console;
use Symfony\Component\Console\Application;

// Try to find the appropriate autoloader.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    require __DIR__ . '/../../../autoload.php';
} else {
    throw new RuntimeException('Autoloader not found');
}

$application = new Application();
$application->add(new Console\NewCommand());
$application->run();
