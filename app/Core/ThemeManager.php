<?php

declare(strict_types=1);

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ThemeManager
{
    private string $root;

    public function __construct(private Settings $settings, private Environment $twig, ?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 2);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $themes = [];
        foreach (glob($this->root . '/content/themes/*/theme.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $data['slug'] = basename(dirname($file));
                $themes[$data['slug']] = $data;
            }
        }
        return $themes;
    }

    public function active(): ?array
    {
        return $this->all()[(string) $this->settings->get('active_theme', '')] ?? null;
    }

    public function activate(string $slug): void
    {
        if (!isset($this->all()[$slug])) {
            return;
        }
        $this->settings->set('active_theme', $slug);
        $this->load($this->all()[$slug]);
    }

    /** @param array<string, mixed> $theme */
    public function load(array $theme): void
    {
        $loader = $this->twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }
        $dir = $this->root . '/content/themes/' . $theme['slug'] . '/templates';
        if (is_dir($dir)) {
            $loader->addPath($dir, 'theme');
        }
        $parent = (string) ($theme['parent'] ?? '');
        $parentDir = $this->root . '/content/themes/' . $parent . '/templates';
        if ($parent !== '' && is_dir($parentDir)) {
            $loader->addPath($parentDir, 'theme');
        }
        $functions = $this->root . '/content/themes/' . $theme['slug'] . '/theme.php';
        if (is_file($functions)) {
            require_once $functions;
        }
    }

    /** @param list<string> $candidates */
    public function render(array $candidates, array $data): string
    {
        $candidates = apply_filters('template.candidates', $candidates);
        $names = array_map(static fn (string $name): string => '@theme/' . $name . '.twig', $candidates);
        $names[] = 'errors/404.twig';
        return $this->twig->resolveTemplate($names)->render($data);
    }
}
