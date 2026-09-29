<?php

declare(strict_types=1);

namespace App\Core;

use App\Service\Slugger;
use flight\database\SimplePdo;

class Installer
{
    public function __construct(
        private SimplePdo $db,
        private Migrator $migrator,
        private Settings $settings,
        private Slugger $slugger,
    ) {
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    public function requirements(): array
    {
        $root = FlightRoot();
        $writable = static fn (string $dir): bool => is_dir($dir) && is_writable($dir);
        return [
            $this->row('PHP 8.3 or newer', PHP_VERSION_ID >= 80300, PHP_VERSION),
            $this->row('pdo_sqlite', extension_loaded('pdo_sqlite'), ''),
            $this->row('gd', extension_loaded('gd'), ''),
            $this->row('fileinfo', extension_loaded('fileinfo'), ''),
            $this->row('mbstring', extension_loaded('mbstring'), ''),
            $this->row('storage/ writable', $writable($root . '/storage'), ''),
            $this->row('public/uploads/ writable', $writable($root . '/public/uploads'), ''),
        ];
    }

    /** @param array{site_title: string, username: string, email: string, password: string, display_name: string} $input */
    public function install(array $input): void
    {
        $this->migrator->migrate();
        $now = gmdate('Y-m-d H:i:s');
        $adminId = (int) $this->db->insert('users', [
            'username' => $input['username'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'display_name' => $input['display_name'],
            'role' => 'administrator',
            'bio' => '',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->db->insert('post_types', [
            ['slug' => 'post', 'label' => 'Posts', 'label_singular' => 'Post', 'rewrite' => '', 'public' => 1, 'has_archive' => 0, 'hierarchical' => 0, 'supports' => '["title","editor","excerpt","thumbnail","comments","revisions"]', 'taxonomies' => '["category","tag"]', 'icon' => 'file-text', 'is_builtin' => 1, 'menu_position' => 5],
            ['slug' => 'page', 'label' => 'Pages', 'label_singular' => 'Page', 'rewrite' => '', 'public' => 1, 'has_archive' => 0, 'hierarchical' => 1, 'supports' => '["title","editor","excerpt","thumbnail","comments","revisions","page-attributes"]', 'taxonomies' => '[]', 'icon' => 'file-earmark', 'is_builtin' => 1, 'menu_position' => 10],
        ]);

        $categoryId = (int) $this->db->insert('terms', [
            'taxonomy' => 'category', 'name' => 'Uncategorized', 'slug' => 'uncategorized',
            'description' => '', 'parent_id' => 0, 'count' => 1,
        ]);

        $postId = (int) $this->db->insert('posts', [
            'type' => 'post', 'status' => 'publish', 'title' => 'Hello world!', 'slug' => 'hello-world',
            'path' => '/hello-world', 'content' => '<p>Welcome to Flight CMS. This is your first post. Edit or delete it, then start writing.</p>',
            'excerpt' => '', 'author_id' => $adminId, 'parent_id' => 0, 'menu_order' => 0, 'template' => '',
            'comment_status' => 'open', 'comment_count' => 1, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->db->insert('term_relationships', ['post_id' => $postId, 'term_id' => $categoryId]);
        $this->db->runQuery('INSERT INTO posts_fts (rowid, title, body) VALUES (?, ?, ?)', [
            $postId, 'Hello world!', 'Welcome to Flight CMS. This is your first post. Edit or delete it, then start writing.',
        ]);

        $pageId = (int) $this->db->insert('posts', [
            'type' => 'page', 'status' => 'publish', 'title' => 'Sample Page', 'slug' => 'sample-page',
            'path' => '/sample-page', 'content' => '<p>This is a sample page. Pages are for content that lives outside the blog.</p>',
            'excerpt' => '', 'author_id' => $adminId, 'parent_id' => 0, 'menu_order' => 0, 'template' => '',
            'comment_status' => 'closed', 'comment_count' => 0, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->db->insert('comments', [
            'post_id' => $postId, 'parent_id' => 0, 'user_id' => null, 'author_name' => 'A Flight CMS commenter',
            'author_email' => 'commenter@example.com', 'author_url' => '', 'ip' => '127.0.0.1', 'user_agent' => '',
            'content' => 'Hi, this is a comment. Comments appear once approved.', 'status' => 'approved', 'created_at' => $now,
        ]);

        $menuId = (int) $this->db->insert('menus', ['name' => 'Primary', 'location' => 'primary']);
        $this->db->insert('menu_items', [
            ['menu_id' => $menuId, 'parent_id' => 0, 'title' => 'Home', 'type' => 'custom', 'object_id' => 0, 'url' => '/', 'target' => '', 'css_class' => '', 'sort_order' => 0],
            ['menu_id' => $menuId, 'parent_id' => 0, 'title' => 'Sample Page', 'type' => 'post', 'object_id' => $pageId, 'url' => '/sample-page', 'target' => '', 'css_class' => '', 'sort_order' => 1],
        ]);

        $defaults = [
            'site_title' => $input['site_title'],
            'tagline' => 'Just another Flight CMS site',
            'installed_at' => $now,
            'active_theme' => 'horizon',
            'permalink_structure' => 'post',
            'category_base' => 'category',
            'tag_base' => 'tag',
            'posts_per_page' => 10,
            'show_on_front' => 'posts',
            'page_on_front' => 0,
            'default_category' => $categoryId,
            'comments_open' => true,
            'comment_moderation' => true,
            'comment_max_links' => 2,
            'comment_disallowed' => '',
            'thread_depth' => 5,
            'registration_open' => false,
            'default_role' => 'subscriber',
            'mail_dsn' => '',
            'mail_from' => 'cms@localhost',
            'title_separator' => '-',
            'seo_home_title' => '',
            'seo_home_description' => '',
            'discourage_search' => false,
            'revisions_keep' => 20,
            'image_sizes' => ['thumbnail' => [150, 150, true], 'medium' => [300, 300, false], 'large' => [1024, 1024, false]],
            'maintenance' => false,
            'api_cors' => '',
            'api_rate_limit' => 60,
            'timezone' => 'UTC',
            'date_format' => 'F j, Y',
            'plugins_active' => [],
            'theme_mods_horizon' => ['accent_color' => '#2563eb', 'logo' => ''],
        ];
        foreach ($defaults as $name => $value) {
            $this->settings->set($name, $value);
        }
    }

    /** @return array{label: string, ok: bool, detail: string} */
    private function row(string $label, bool $ok, string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}

function FlightRoot(): string
{
    return dirname(__DIR__, 2);
}
