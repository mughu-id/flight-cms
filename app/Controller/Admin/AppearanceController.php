<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\ThemeManager;
use App\Repository\MenuRepository;
use App\Repository\PostRepository;

class AppearanceController extends DashboardController
{
    public function themes(): void
    {
        $this->forbid('edit_theme_options');
        $manager = $this->app->make(ThemeManager::class);
        $this->admin('admin/appearance/themes', [
            'title' => 'Themes',
            'themes' => $manager->all(),
            'active' => (string) $this->settings->get('active_theme', ''),
        ]);
    }

    public function activate(string $slug): void
    {
        $this->forbid('edit_theme_options');
        $this->app->make(ThemeManager::class)->activate($slug);
        $this->app->get('hooks')->doAction('theme.switched', $slug);
        $this->flash->set('success', 'Theme activated.');
        $this->redirect('/admin/appearance/themes');
    }

    public function customize(): void
    {
        $this->forbid('edit_theme_options');
        $theme = $this->app->make(ThemeManager::class)->active();
        if ($theme === null) {
            $this->app->halt(404, 'No active theme');
        }
        $mods = (array) $this->settings->get('theme_mods_' . $theme['slug'], []);
        $groups = [];
        foreach ((array) ($theme['settings'] ?? []) as $field) {
            $groups[(string) ($field['group'] ?? 'theme')][] = $field;
        }
        $labels = ['colors' => 'Colors', 'header' => 'Header', 'home' => 'Homepage', 'footer' => 'Footer', 'theme' => 'Theme'];
        $this->admin('admin/appearance/customize', [
            'title' => 'Customize',
            'theme' => $theme,
            'mods' => $mods,
            'groups' => $groups,
            'group_labels' => $labels,
        ]);
    }

    public function saveCustomize(): void
    {
        $this->forbid('edit_theme_options');
        $theme = $this->app->make(ThemeManager::class)->active();
        if ($theme === null) {
            $this->redirect('/admin/appearance/themes');
        }
        $mods = (array) $this->settings->get('theme_mods_' . $theme['slug'], []);
        foreach ((array) ($theme['settings'] ?? []) as $field) {
            $key = (string) $field['key'];
            $type = (string) ($field['type'] ?? 'text');
            if ($type === 'color') {
                $color = $this->input($key);
                $mods[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : (string) ($field['default'] ?? '');
            } elseif ($type === 'check') {
                $mods[$key] = $this->input($key) === '1' ? '1' : '0';
            } elseif ($type === 'select') {
                $value = $this->input($key);
                $mods[$key] = array_key_exists($value, (array) ($field['options'] ?? [])) ? $value : (string) ($field['default'] ?? '');
            } else {
                $mods[$key] = $this->input($key);
            }
        }
        $this->settings->set('theme_mods_' . $theme['slug'], $mods);
        $this->app->get('hooks')->doAction('settings.saved');
        $this->flash->set('success', 'Customizations saved.');
        $this->redirect('/admin/appearance/customize');
    }

    public function sidebar(): void
    {
        $this->forbid('edit_theme_options');
        $this->admin('admin/appearance/sidebar', [
            'title' => 'Sidebar',
            'widgets' => $this->widgetNames(),
            'enabled' => $this->sidebarWidgets(),
        ]);
    }

    public function saveSidebar(): void
    {
        $this->forbid('edit_theme_options');
        $raw = json_decode((string) ($this->app->request()->data->widgets_json ?? ''), true);
        $known = $this->widgetNames();
        $widgets = [];
        foreach (is_array($raw) ? $raw : [] as $key) {
            if (isset($known[$key]) && !in_array($key, $widgets, true)) {
                $widgets[] = $key;
            }
        }
        $this->settings->set('sidebar_widgets', $widgets);
        $this->app->get('hooks')->doAction('settings.saved');
        $this->flash->set('success', 'Sidebar saved.');
        $this->redirect('/admin/appearance/sidebar');
    }

    /** @return array<string, string> */
    private function widgetNames(): array
    {
        return [
            'search' => 'Search',
            'about' => 'About',
            'topics' => 'Topics',
            'recent' => 'Recent posts',
            'popular' => 'Popular posts',
            'random' => 'Random posts',
            'tags' => 'Tags',
            'pages' => 'Pages',
            'archives' => 'Archives',
            'comments' => 'Latest comments',
            'feed' => 'RSS feed',
        ];
    }

    /** @return list<string> */
    private function sidebarWidgets(): array
    {
        $saved = $this->settings->get('sidebar_widgets');
        if (is_array($saved)) {
            return array_values(array_filter($saved, fn (mixed $key): bool => isset($this->widgetNames()[(string) $key])));
        }
        $mods = (array) $this->settings->get('theme_mods_' . (string) $this->settings->get('active_theme', ''), []);
        $on = [];
        foreach (['sidebar_search' => 'search', 'sidebar_about' => 'about', 'sidebar_topics' => 'topics', 'sidebar_recent' => 'recent'] as $mod => $key) {
            if (($mods[$mod] ?? '1') !== '0') {
                $on[] = $key;
            }
        }
        return $on;
    }

    public function menus(): void
    {
        $this->forbid('edit_theme_options');
        $menus = $this->app->make(MenuRepository::class);
        $id = (int) ($this->app->request()->query->id ?? $this->input('id', '0'));
        $all = $menus->all();
        if ($id < 1 && $all !== []) {
            $id = (int) $all[0]['id'];
        }
        $menu = $id > 0 ? $menus->find($id) : null;
        $theme = $this->app->make(ThemeManager::class)->active();
        $this->admin('admin/appearance/menus', [
            'title' => 'Menus',
            'menus' => $all,
            'nav' => $menu,
            'items' => $menu ? $menus->tree((int) $menu['id']) : [],
            'locations' => (array) ($theme['menus'] ?? []),
            'pages' => $this->app->make(PostRepository::class)->list('page', ['status' => 'publish'], 1, 100)['rows'],
        ]);
    }

    public function createMenu(): void
    {
        $this->forbid('edit_theme_options');
        $name = $this->input('name');
        if ($name === '') {
            $this->flash->set('error', 'Name required.');
            $this->redirect('/admin/appearance/menus');
        }
        $id = $this->app->make(MenuRepository::class)->create($name);
        $this->redirect('/admin/appearance/menus?id=' . $id);
    }

    public function saveMenu(string $id): void
    {
        $this->forbid('edit_theme_options');
        $menus = $this->app->make(MenuRepository::class);
        $menu = $menus->find((int) $id);
        if ($menu === null) {
            $this->app->halt(404, 'Not found');
        }
        $raw = $this->app->request()->data->items_json ?? null;
        if (is_string($raw) && $raw !== '') {
            $items = json_decode($raw, true);
            if (!is_array($items)) {
                $this->flash->set('error', 'Could not read the menu items, so nothing was changed.');
                $this->redirect('/admin/appearance/menus?id=' . $id);
            }
            $menus->replaceItems((int) $menu['id'], $items);
        }
        $name = trim($this->input('name'));
        if ($name !== '') {
            $menus->rename((int) $menu['id'], $name);
        }
        $menus->assign((int) $menu['id'], $this->input('location'));
        $this->flash->set('success', 'Menu saved.');
        $this->redirect('/admin/appearance/menus?id=' . $id);
    }

    public function deleteMenu(string $id): void
    {
        $this->forbid('edit_theme_options');
        $this->app->make(MenuRepository::class)->delete((int) $id);
        $this->flash->set('success', 'Menu deleted.');
        $this->redirect('/admin/appearance/menus');
    }

    private function storeBrandImage(string $theme, string $key): ?string
    {
        $file = $this->app->request()->files->{$key} ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg'], true)) {
            $this->flash->set('error', 'Use a PNG, JPG, WEBP, SVG, or ICO image.');
            return null;
        }
        $dir = $this->app->get('root') . '/content/themes/' . $theme . '/assets';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $key . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return null;
        }
        return $name;
    }
}
