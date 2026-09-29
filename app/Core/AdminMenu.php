<?php

declare(strict_types=1);

namespace App\Core;

use App\Security\Capabilities;

class AdminMenu
{
    public function __construct(private Hooks $hooks, private Capabilities $capabilities)
    {
    }

    /** @return list<array{label: string, url: string, icon: string, children?: list<array{label: string, url: string, icon: string}>}> */
    public function items(): array
    {
        $items = [
            ['label' => 'Dashboard', 'url' => '/admin', 'icon' => 'speedometer2', 'cap' => 'read', 'position' => 0],
            ['label' => 'Posts', 'url' => '/admin/content/post', 'icon' => 'file-text', 'cap' => 'edit_posts', 'position' => 5],
            ['label' => 'Pages', 'url' => '/admin/content/page', 'icon' => 'file-earmark', 'cap' => 'edit_pages', 'position' => 10],
            ['label' => 'Categories', 'url' => '/admin/terms/category', 'icon' => 'folder', 'cap' => 'manage_categories', 'position' => 6],
            ['label' => 'Tags', 'url' => '/admin/terms/tag', 'icon' => 'tags', 'cap' => 'manage_categories', 'position' => 7],
            ['label' => 'Media', 'url' => '/admin/media', 'icon' => 'images', 'cap' => 'upload_files', 'position' => 15],
            ['label' => 'Comments', 'url' => '/admin/comments', 'icon' => 'chat', 'cap' => 'moderate_comments', 'position' => 20],
            ['label' => 'Remote post', 'url' => '/admin/remote', 'icon' => 'send', 'cap' => 'edit_posts', 'position' => 25],
            [
                'label' => 'Appearance',
                'url' => '/admin/appearance/themes',
                'icon' => 'palette',
                'cap' => 'edit_theme_options',
                'position' => 40,
                'match' => '/admin/appearance',
                'children' => [
                    ['label' => 'Themes', 'url' => '/admin/appearance/themes', 'icon' => 'palette', 'cap' => 'edit_theme_options'],
                    ['label' => 'Menus', 'url' => '/admin/appearance/menus', 'icon' => 'menu', 'cap' => 'edit_theme_options'],
                    ['label' => 'Customize', 'url' => '/admin/appearance/customize', 'icon' => 'sliders', 'cap' => 'edit_theme_options'],
                    ['label' => 'Sidebar', 'url' => '/admin/appearance/sidebar', 'icon' => 'panel', 'cap' => 'edit_theme_options'],
                ],
            ],
            ['label' => 'Plugins', 'url' => '/admin/plugins', 'icon' => 'plug', 'cap' => 'activate_plugins', 'position' => 45],
            ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'people', 'cap' => 'list_users', 'position' => 50],
            ['label' => 'Post types', 'url' => '/admin/post-types', 'icon' => 'collection', 'cap' => 'edit_posts', 'position' => 52, 'match' => '/admin/post-types', 'extra' => '/admin/field-groups', 'children' => [
                ['label' => 'Post types', 'url' => '/admin/post-types', 'icon' => 'collection', 'cap' => 'manage_post_types'],
                ['label' => 'Field groups', 'url' => '/admin/field-groups', 'icon' => 'sliders', 'cap' => 'manage_post_types'],
            ]],
            ['label' => 'Settings', 'url' => '/admin/settings/general', 'icon' => 'gear', 'cap' => 'manage_options', 'position' => 60],
            ['label' => 'Tools', 'url' => '/admin/tools', 'icon' => 'tools', 'cap' => 'manage_options', 'position' => 70, 'match' => '/admin/tools', 'children' => [
                ['label' => 'Tools', 'url' => '/admin/tools', 'icon' => 'tools', 'cap' => 'manage_options'],
                ['label' => 'Redirects', 'url' => '/admin/tools/redirects', 'icon' => 'external-link', 'cap' => 'manage_options'],
                ['label' => 'Log', 'url' => '/admin/tools/log', 'icon' => 'file-text', 'cap' => 'manage_options'],
            ]],
        ];
        try {
            $types = Flight::app()->make(\App\Core\PostTypeRegistry::class)->all();
            foreach ($types as $slug => $type) {
                if (in_array($slug, ['post', 'page'], true) || !(int) $type['public']) {
                    continue;
                }
                foreach ($items as &$item) {
                    if (($item['url'] ?? '') !== '/admin/post-types') {
                        continue;
                    }
                    $item['children'][] = [
                        'label' => $type['label'],
                        'url' => '/admin/content/' . $slug,
                        'icon' => $type['icon'] ?: 'file-earmark',
                        'cap' => 'edit_posts',
                    ];
                }
                unset($item);
            }
        } catch (\Throwable) {
        }
        $items = $this->hooks->applyFilters('admin.menu', $items);
        $can = fn (array $item): bool => $this->capabilities->can((string) ($item['cap'] ?? 'read'));
        $items = array_values(array_filter($items, $can));
        foreach ($items as &$item) {
            if (!isset($item['children']) || !is_array($item['children'])) {
                continue;
            }
            $item['children'] = array_values(array_filter($item['children'], $can));
        }
        unset($item);
        $items = array_values(array_filter($items, static function (array $item) use ($can): bool {
            if (($item['url'] ?? '') !== '/admin/post-types') {
                return true;
            }
            return ($item['children'] ?? []) !== [] || $can(['cap' => 'manage_post_types']);
        }));
        usort($items, static fn (array $a, array $b): int => ($a['position'] ?? 50) <=> ($b['position'] ?? 50));
        return $items;
    }
}
