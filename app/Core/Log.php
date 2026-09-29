<?php

declare(strict_types=1);

namespace App\Core;

use flight\Engine;

/** Application log like Laravel's laravel.log. Off until Enable is turned on, then everything is written. */
class Log
{
    /** @var array<string, int> */
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    private static bool $writing = false;

    public static function enabled(Engine $app): bool
    {
        $settings = $app->get('settings');
        if (!$settings instanceof Settings) {
            return false;
        }
        if ($settings->get('log_enabled') !== null) {
            return (bool) $settings->get('log_enabled');
        }
        $legacy = (string) $settings->get('log_level', 'off');
        return $legacy !== '' && $legacy !== 'off';
    }

    public static function write(Engine $app, string $level, string $message, array $context = []): void
    {
        if (self::$writing || !$app->get('installed') || !self::enabled($app) || !isset(self::LEVELS[$level])) {
            return;
        }
        $env = $app->get('config')->get('app.debug') === true ? 'local' : 'production';
        $extra = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::line($app, '[' . gmdate('Y-m-d H:i:s') . "] {$env}." . strtoupper($level) . ': ' . $message . $extra);
    }

    public static function exception(Engine $app, \Throwable $e): void
    {
        $trace = $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
        self::write($app, 'error', $trace);
    }

    /** One warning when this request was slow. Normal page views are not logged. */
    public static function slow(Engine $app): void
    {
        if (!self::enabled($app)) {
            return;
        }
        $settings = $app->get('settings');
        $limit = max(50, (int) $settings->get('log_slow_ms', 500));
        $ms = (microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000;
        if ($ms < $limit) {
            return;
        }
        $uri = substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 180);
        $env = $app->get('config')->get('app.debug') === true ? 'local' : 'production';
        self::line($app, '[' . gmdate('Y-m-d H:i:s') . "] {$env}.WARNING: Slow request " . ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . $uri . ' took ' . (int) round($ms) . 'ms');
    }

    public static function tail(string $root, int $lines = 120, string $filter = 'all'): string
    {
        $file = $root . '/storage/logs/cms.log';
        if (!is_file($file)) {
            return '';
        }
        $rows = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        if ($filter !== 'all' && isset(self::LEVELS[$filter])) {
            $needle = '.' . strtoupper($filter) . ':';
            $rows = array_values(array_filter($rows, static fn (string $row): bool => str_contains($row, $needle)));
        }
        return implode("\n", array_slice($rows, -$lines));
    }

    public static function clear(string $root): void
    {
        $file = $root . '/storage/logs/cms.log';
        if (is_file($file)) {
            unlink($file);
        }
    }

    private static function line(Engine $app, string $text): void
    {
        self::$writing = true;
        $file = $app->get('root') . '/storage/logs/cms.log';
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        if (is_file($file) && filesize($file) > 2_000_000) {
            @rename($file, $file . '.1');
        }
        @file_put_contents($file, $text . "\n", FILE_APPEND | LOCK_EX);
        self::$writing = false;
    }
}
