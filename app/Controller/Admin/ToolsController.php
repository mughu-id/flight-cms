<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\Migrator;
use App\Support\Row;

class ToolsController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('manage_options');
        $root = $this->app->get('root');
        $health = [
            ['label' => 'PHP', 'ok' => PHP_VERSION_ID >= 80300, 'detail' => PHP_VERSION],
            ['label' => 'pdo_sqlite', 'ok' => extension_loaded('pdo_sqlite'), 'detail' => ''],
            ['label' => 'gd', 'ok' => extension_loaded('gd'), 'detail' => ''],
            ['label' => 'storage writable', 'ok' => is_writable($root . '/storage'), 'detail' => ''],
            ['label' => 'uploads writable', 'ok' => is_writable($root . '/uploads'), 'detail' => ''],
            ['label' => 'Pending migrations', 'ok' => $this->app->make(Migrator::class)->pending() === [], 'detail' => implode(', ', $this->app->make(Migrator::class)->pending())],
        ];
        $this->admin('admin/tools/index', [
            'title' => 'Tools',
            'health' => $health,
            'maintenance' => (bool) $this->settings->get('maintenance', false),
        ]);
    }

    public function redirects(): void
    {
        $this->forbid('manage_options');
        $this->admin('admin/tools/redirects', [
            'title' => 'Redirects',
            'redirects' => Row::all($this->app->db()->fetchAll('SELECT * FROM redirects ORDER BY id DESC LIMIT 100')),
        ]);
    }

    public function log(): void
    {
        $this->forbid('manage_options');
        $filter = (string) ($this->app->request()->query->filter ?? 'all');
        if (!in_array($filter, ['all', 'error', 'warning', 'info', 'debug'], true)) {
            $filter = 'all';
        }
        $enabled = \App\Core\Log::enabled($this->app);
        $this->admin('admin/tools/log', [
            'title' => 'Log',
            'log_enabled' => $enabled,
            'log_slow_ms' => (int) $this->settings->get('log_slow_ms', 500),
            'log_filter' => $filter,
            'log_text' => \App\Core\Log::tail((string) $this->app->get('root'), 120, $filter),
        ]);
    }

    public function backup(): void
    {
        $this->forbid('manage_options');
        $src = $this->app->get('root') . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $this->app->get('config')->get('database.path'));
        $dest = $this->app->get('root') . '/storage/backups/cms-' . gmdate('Ymd-His') . '.sqlite';
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        $this->app->db()->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($dest) . '"');
        readfile($dest);
        exit;
    }

    public function optimize(): void
    {
        $this->forbid('manage_options');
        $this->app->db()->exec('PRAGMA optimize');
        $this->flash->set('success', 'Database optimized.');
        $this->redirect('/admin/tools');
    }

    public function purgeCache(): void
    {
        $this->forbid('manage_options');
        $dir = $this->app->get('root') . '/storage/cache/pages';
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        $this->flash->set('success', 'Cache purged.');
        $this->redirect('/admin/tools');
    }

    public function maintenance(): void
    {
        $this->forbid('manage_options');
        $this->settings->set('maintenance', !$this->settings->get('maintenance', false));
        $this->flash->set('success', 'Maintenance mode toggled.');
        $this->redirect('/admin/tools');
    }

    public function saveLog(): void
    {
        $this->forbid('manage_options');
        $this->settings->set('log_enabled', $this->input('log_enabled') === '1');
        $this->settings->set('log_slow_ms', max(50, (int) $this->input('log_slow_ms', '500')));
        $this->flash->set('success', $this->input('log_enabled') === '1' ? 'Logging is on.' : 'Logging is off.');
        $this->redirect('/admin/tools/log');
    }

    public function clearLog(): void
    {
        $this->forbid('manage_options');
        \App\Core\Log::clear((string) $this->app->get('root'));
        $this->flash->set('success', 'Log cleared.');
        $this->redirect('/admin/tools/log');
    }

    public function saveRedirect(): void
    {
        $this->forbid('manage_options');
        $this->app->db()->insert('redirects', [
            'source_path' => '/' . trim($this->input('source_path'), '/'),
            'target_path' => $this->input('target_path'),
            'status_code' => (int) $this->input('status_code', '301'),
            'hits' => 0,
        ]);
        $this->flash->set('success', 'Redirect added.');
        $this->redirect('/admin/tools/redirects');
    }

    public function deleteRedirect(string $id): void
    {
        $this->forbid('manage_options');
        $this->app->db()->delete('redirects', 'id = ?', [(int) $id]);
        $this->redirect('/admin/tools/redirects');
    }
}
