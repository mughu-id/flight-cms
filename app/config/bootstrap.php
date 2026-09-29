<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Hooks;
use App\Core\Migrator;
use App\Core\Settings;
use App\Security\Capabilities;
use flight\database\SimplePdo;
use flight\Engine;
use flight\Session;
use Tracy\Debugger;

$root = dirname(__DIR__, 2);
$ds = DIRECTORY_SEPARATOR;

require $root . $ds . 'vendor' . $ds . 'autoload.php';

$configFile = __DIR__ . $ds . 'config.php';
if (!is_file($configFile)) {
    $sample = __DIR__ . $ds . 'config.sample.php';
    $key = bin2hex(random_bytes(32));
    file_put_contents($configFile, str_replace('__APP_KEY__', $key, (string) file_get_contents($sample)));
}

$config = new Config(require $configFile);
date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

$app = Flight::app();
$app->set('config', $config);
$app->set('root', $root);
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
$baseUrl = $host !== ''
    ? ($https ? 'https' : 'http') . '://' . $host
    : rtrim((string) $config->get('app.url'), '/');
$app->set('flight.base_url', $baseUrl);
$app->set('flight.case_sensitive', false);
$app->set('flight.log_errors', true);
$app->set('flight.handle_errors', $config->get('app.debug') !== true);
$app->set('flight.views.path', $root . $ds . 'app' . $ds . 'views');
$app->set('flight.views.extension', '.twig');

if ($config->get('app.debug') === true) {
    Debugger::enable(Debugger::DEVELOPMENT, $root . $ds . 'storage' . $ds . 'logs');
    Debugger::$strictMode = false;
} else {
    Debugger::enable(Debugger::PRODUCTION, $root . $ds . 'storage' . $ds . 'logs');
}

require __DIR__ . $ds . 'services.php';

$dbPath = $root . $ds . str_replace('/', $ds, (string) $config->get('database.path'));
$installed = is_file($dbPath) && (new Migrator($app->db(), $root . $ds . 'database' . $ds . 'migrations'))->isInstalled();
$app->set('installed', $installed);

if ($installed) {
    $capabilities = new Capabilities($app->session(), require __DIR__ . $ds . 'roles.php', $app);
    $app->set('capabilities', $capabilities);

    $pluginManager = $app->make(\App\Core\PluginManager::class);
    $pluginManager->loadActive();

    $theme = $app->make(\App\Core\ThemeManager::class)->active();
    if ($theme !== null) {
        $app->make(\App\Core\ThemeManager::class)->load($theme);
    }

    $app->get('hooks')->doAction('init');

    set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($app): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        $level = ($severity & (E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) !== 0 ? 'error'
            : (($severity & (E_WARNING | E_USER_WARNING)) !== 0 ? 'warning' : 'notice');
        \App\Core\Log::write($app, $level, $message . ' in ' . $file . ':' . $line);
        return false;
    });

    if ($app->get('settings')->get('maintenance') && !$capabilities->can('manage_options') && !str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin')) {
        http_response_code(503);
        echo 'Site under maintenance.';
        return;
    }

    // Full-page cache for anonymous GET
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $cached = !in_array($path, ['/sitemap.xml', '/robots.txt', '/feed'], true) && !str_starts_with($path, '/sitemap-');
    if ($method === 'GET' && $cached && !$app->session()->get('user_id') && !$app->session()->get('flash') && !str_starts_with($uri, '/admin') && !str_starts_with($uri, '/api')) {
        $cacheDir = $root . $ds . 'storage' . $ds . 'cache' . $ds . 'pages';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . $ds . md5($uri) . '.html';
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 300) {
            echo file_get_contents($cacheFile);
            return;
        }
        $app->set('page_cache_file', $cacheFile);
        $app->after('start', static function () use ($app): void {
            $file = $app->get('page_cache_file');
            if (is_string($file) && $file !== '') {
                @file_put_contents($file, $app->response()->getBody());
            }
        });
    }

    $purge = static function () use ($root, $ds): void {
        foreach (glob($root . $ds . 'storage' . $ds . 'cache' . $ds . 'pages' . $ds . '*') ?: [] as $file) {
            @unlink($file);
        }
    };
    foreach (['post.saved', 'comment.posted', 'theme.switched', 'settings.saved'] as $event) {
        $app->get('hooks')->addAction($event, $purge);
    }
}

require __DIR__ . $ds . 'routes.php';

$app->map('notFound', static function () use ($app): void {
    $app->response()->status(404);
    if ($app->get('installed')) {
        $app->render('errors/404');
        return;
    }
    echo 'Not found';
});

try {
    $app->start();
} catch (Throwable $e) {
    if ($app->get('installed')) {
        \App\Core\Log::exception($app, $e);
    }
    http_response_code(500);
    echo $config->get('app.debug') ? $e : 'Server error';
} finally {
    if ($app->get('installed')) {
        \App\Core\Log::slow($app);
    }
}
