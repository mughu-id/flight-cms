<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Plugin\BasePlugin;
use flight\Engine;
use Twig\Loader\FilesystemLoader;

class PluginManager
{
    /** @var array<string, BasePlugin> */
    private array $loaded = [];

    private string $root;

    public function __construct(
        private Engine $app,
        private Settings $settings,
        private Hooks $hooks,
        private Shortcodes $shortcodes,
        ?string $root = null,
    ) {
        $this->root = $root ?? dirname(__DIR__, 2);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $plugins = [];
        foreach (glob($this->root . '/content/plugins/*/plugin.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $slug = basename(dirname($file));
                $data['slug'] = $slug;
                $data['path'] = dirname($file);
                $plugins[$slug] = $data;
            }
        }
        return $plugins;
    }

    /** @return list<string> */
    public function active(): array
    {
        return array_values((array) $this->settings->get('plugins_active', []));
    }

    public function loadActive(): void
    {
        foreach ($this->active() as $slug) {
            try {
                $this->boot($slug);
            } catch (\Throwable $e) {
                $this->deactivate($slug, false);
                $notices = (array) $this->settings->get('_admin_notices', []);
                $notices[] = "Plugin {$slug} was deactivated: " . $e->getMessage();
                $this->settings->set('_admin_notices', $notices);
            }
        }
    }

    public function activate(string $slug): void
    {
        $plugin = $this->boot($slug);
        $plugin->activate();
        $active = $this->active();
        if (!in_array($slug, $active, true)) {
            $active[] = $slug;
            $this->settings->set('plugins_active', $active);
        }
        $this->hooks->doAction('plugin.activated', $slug);
    }

    public function deactivate(string $slug, bool $call = true): void
    {
        if ($call && isset($this->loaded[$slug])) {
            $this->loaded[$slug]->deactivate();
        }
        $this->settings->set('plugins_active', array_values(array_filter(
            $this->active(),
            static fn (string $s): bool => $s !== $slug
        )));
        unset($this->loaded[$slug]);
    }

    public function uninstall(string $slug): void
    {
        if (!isset($this->loaded[$slug])) {
            try {
                $this->boot($slug);
            } catch (\Throwable) {
            }
        }
        if (isset($this->loaded[$slug])) {
            $this->loaded[$slug]->uninstall();
        }
        $this->deactivate($slug, false);
    }

    private function boot(string $slug): BasePlugin
    {
        if (isset($this->loaded[$slug])) {
            return $this->loaded[$slug];
        }
        $meta = $this->all()[$slug] ?? null;
        if ($meta === null) {
            throw new \RuntimeException('Plugin not found');
        }
        $file = $meta['path'] . '/plugin.php';
        if (!is_file($file)) {
            throw new \RuntimeException('plugin.php missing');
        }
        $templates = $meta['path'] . '/templates';
        $loader = $this->app->get('twig')->getLoader();
        if (is_dir($templates) && $loader instanceof FilesystemLoader) {
            $loader->addPath($templates, 'plugin_' . $slug);
        }
        $factory = require $file;
        if (!is_callable($factory)) {
            throw new \RuntimeException('plugin.php must return a factory');
        }
        $plugin = $factory($this->app, $this->hooks, $this->shortcodes, $slug, $meta['path']);
        if (!$plugin instanceof BasePlugin) {
            throw new \RuntimeException('Factory must return BasePlugin');
        }
        $plugin->register();
        $this->loaded[$slug] = $plugin;
        return $plugin;
    }
}
