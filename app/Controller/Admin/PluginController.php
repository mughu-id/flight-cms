<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\PluginManager;

class PluginController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('activate_plugins');
        $manager = $this->app->make(PluginManager::class);
        $this->admin('admin/plugins/index', [
            'title' => 'Plugins',
            'plugins' => $manager->all(),
            'active' => $manager->active(),
        ]);
    }

    public function activate(string $slug): void
    {
        $this->forbid('activate_plugins');
        try {
            $this->app->make(PluginManager::class)->activate($slug);
            $this->flash->set('success', 'Plugin activated.');
        } catch (\Throwable $e) {
            $this->flash->set('error', $e->getMessage());
        }
        $this->redirect('/admin/plugins');
    }

    public function deactivate(string $slug): void
    {
        $this->forbid('activate_plugins');
        $this->app->make(PluginManager::class)->deactivate($slug);
        $this->flash->set('success', 'Plugin deactivated.');
        $this->redirect('/admin/plugins');
    }

    public function delete(string $slug): void
    {
        $this->forbid('activate_plugins');
        $this->app->make(PluginManager::class)->uninstall($slug);
        $this->flash->set('success', 'Plugin removed from active list.');
        $this->redirect('/admin/plugins');
    }
}
