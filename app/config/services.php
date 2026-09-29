<?php

declare(strict_types=1);

use App\Core\Hooks;
use App\Core\Settings;
use App\Twig\CmsExtension;
use flight\database\SimplePdo;
use flight\Engine;
use flight\Session;

/** @var Engine $app */
/** @var App\Core\Config $config */
/** @var string $root */

// Runway requires this file while reading config.php. Skip the web bootstrap then.
if (!isset($app) || !$app instanceof Engine) {
    return;
}

$ds = DIRECTORY_SEPARATOR;
$dbPath = $root . $ds . str_replace('/', $ds, (string) $config->get('database.path'));
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0775, true);
}

$db = new SimplePdo('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
foreach (['journal_mode = WAL', 'foreign_keys = ON', 'busy_timeout = 5000', 'synchronous = NORMAL'] as $pragma) {
    $db->exec('PRAGMA ' . $pragma);
}

$sessionDir = $root . $ds . 'storage' . $ds . 'sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}

$secure = str_starts_with((string) $config->get('app.url'), 'https://');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $secure,
]);

$session = new Session([
    'save_path' => $sessionDir,
    'prefix' => 'cms_',
    'serialization' => 'json',
]);

$hooks = new Hooks();
$settings = new Settings($db);
$shortcodes = new \App\Core\Shortcodes();

$loader = new Twig\Loader\FilesystemLoader($root . $ds . 'app' . $ds . 'views');
$twig = new Twig\Environment($loader, [
    'cache' => $config->get('app.debug') ? false : $root . $ds . 'storage' . $ds . 'cache' . $ds . 'twig',
    'autoescape' => 'html',
    'strict_variables' => false,
]);
$twig->addExtension(new CmsExtension($app));

$app->map('render', static function (string $template, array $data = [], ?string $key = null) use ($app, $twig): void {
    $html = $twig->render($template . '.twig', $data);
    if ($key !== null) {
        $app->view()->set($key, $html);
        return;
    }
    $app->response()->write($html);
});

$app->set('db', $db);
$app->set('session', $session);
$app->set('hooks', $hooks);
$app->set('settings', $settings);
$app->set('shortcodes', $shortcodes);
$app->set('twig', $twig);

$app->map('db', static fn (): SimplePdo => $app->get('db'));
$app->map('session', static fn (): Session => $app->get('session'));

$container = new Dice\Dice();
$container = $container->addRule('*', [
    'substitutions' => [
        Engine::class => $app,
        SimplePdo::class => $db,
        Session::class => $session,
        Hooks::class => $hooks,
        Settings::class => $settings,
        \App\Core\Shortcodes::class => $shortcodes,
        Twig\Environment::class => $twig,
    ],
]);

$app->registerContainerHandler(static function (string $class, array $params) use ($container): object {
    return $container->create($class, $params);
});

$app->map('make', static function (string $class, array $params = []) use ($container): object {
    return $container->create($class, $params);
});
