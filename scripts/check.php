<?php

require __DIR__ . '/../vendor/autoload.php';

$classes = [
    App\Core\Hooks::class,
    App\Core\Migrator::class,
    App\Core\Installer::class,
    App\Controller\InstallController::class,
    App\Controller\AuthController::class,
    App\Controller\Admin\UserController::class,
    App\Controller\Admin\SettingsController::class,
    App\Service\HtmlSanitizer::class,
    App\Service\Mailer::class,
    App\Security\Capabilities::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "MISSING {$class}\n");
        exit(1);
    }
}

echo "autoload ok\n";
