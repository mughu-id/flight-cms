<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\ThemeManager;
use App\Service\Mailer;

class SettingsController extends DashboardController
{
    public function form(string $section): void
    {
        $this->forbid('manage_options');
        $view = 'admin/settings/' . $section;
        if (!is_file(dirname(__DIR__, 2) . '/views/' . $view . '.twig')) {
            $this->app->halt(404, 'Unknown settings section');
        }
        $theme = $this->app->make(ThemeManager::class)->active();
        $slug = (string) ($theme['slug'] ?? $this->settings->get('active_theme', ''));
        $this->admin($view, [
            'title' => ucfirst($section),
            'section' => $section,
            'settings' => $this->settings->all(),
            'theme_slug' => $slug,
            'mods' => (array) $this->settings->get('theme_mods_' . $slug, []),
            'settings_nav' => [
                'general' => 'General',
                'brand' => 'Brand',
                'reading' => 'Reading',
                'permalinks' => 'Permalinks',
                'discussion' => 'Discussion',
                'media' => 'Media',
                'mail' => 'Mail',
                'seo' => 'SEO',
                'api' => 'API',
            ],
        ]);
    }

    public function save(string $section): void
    {
        $this->forbid('manage_options');
        $method = 'save' . ucfirst($section);
        if (!method_exists($this, $method)) {
            $this->app->halt(404, 'Unknown settings section');
        }
        $this->{$method}();
        $this->app->get('hooks')->doAction('settings.saved', $section);
        $this->flash->set('success', 'Settings saved.');
        $this->redirect('/admin/settings/' . $section);
    }

    private function saveBrand(): void
    {
        $slug = (string) $this->settings->get('active_theme', '');
        $mods = (array) $this->settings->get('theme_mods_' . $slug, []);
        foreach (['logo', 'favicon'] as $key) {
            $file = $this->app->request()->files->{$key} ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $stored = $this->storeBrandFile($slug, $key, $file);
            if ($stored !== null) {
                $mods[$key] = $stored;
            }
        }
        $this->settings->set('theme_mods_' . $slug, $mods);
    }

    /** @param array<string, mixed> $file */
    private function storeBrandFile(string $theme, string $key, array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->flash->set('error', 'The upload did not finish.');
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
            $this->flash->set('error', 'Could not store the image.');
            return null;
        }
        return $name;
    }

    private function saveGeneral(): void
    {
        $this->settings->set('site_title', $this->input('site_title'));
        $this->settings->set('tagline', $this->input('tagline'));
        $this->settings->set('timezone', $this->input('timezone') !== '' ? $this->input('timezone') : 'UTC');
        $this->settings->set('date_format', $this->input('date_format') !== '' ? $this->input('date_format') : 'F j, Y');
        $this->settings->set('registration_open', $this->input('registration_open') === '1');
    }

    private function saveMail(): void
    {
        $this->settings->set('mail_from', $this->input('mail_from'));
        $this->settings->set('mail_dsn', $this->input('mail_dsn'));
        if ($this->input('test_to') !== '') {
            $this->app->make(Mailer::class)->send($this->input('test_to'), 'Flight CMS test', '<p>Mail works.</p>');
        }
    }

    private function saveReading(): void
    {
        $this->settings->set('show_on_front', $this->input('show_on_front') === 'page' ? 'page' : 'posts');
        $this->settings->set('page_on_front', (int) $this->input('page_on_front', '0'));
        $this->settings->set('posts_per_page', max(1, (int) $this->input('posts_per_page', '10')));
        $mode = $this->input('pagination_mode', 'numbers');
        $this->settings->set('pagination_mode', in_array($mode, ['numbers', 'more', 'infinite'], true) ? $mode : 'numbers');
    }

    private function savePermalinks(): void
    {
        $this->settings->set('permalink_structure', $this->input('permalink_structure', 'post'));
        $this->settings->set('category_base', $this->input('category_base', 'category'));
        $this->settings->set('tag_base', $this->input('tag_base', 'tag'));
    }

    private function saveMedia(): void
    {
        $thumb = max(50, (int) $this->input('thumb', '150'));
        $medium = max(100, (int) $this->input('medium', '300'));
        $large = max(200, (int) $this->input('large', '1024'));
        $this->settings->set('image_sizes', [
            'thumbnail' => [$thumb, $thumb, true],
            'medium' => [$medium, $medium, false],
            'large' => [$large, $large, false],
        ]);
    }

    private function saveDiscussion(): void
    {
        $this->settings->set('comments_open', $this->input('comments_open') === '1');
        $this->settings->set('comment_moderation', $this->input('comment_moderation') === '1');
        $this->settings->set('comment_max_links', (int) $this->input('comment_max_links', '2'));
        $this->settings->set('comment_disallowed', $this->input('comment_disallowed'));
        $this->settings->set('thread_depth', (int) $this->input('thread_depth', '5'));
    }

    private function saveSeo(): void
    {
        $this->settings->set('title_separator', $this->input('title_separator', '-'));
        $this->settings->set('seo_home_title', $this->input('seo_home_title'));
        $this->settings->set('seo_home_description', $this->input('seo_home_description'));
        $this->settings->set('seo_default_image', $this->input('seo_default_image'));
        $this->settings->set('discourage_search', $this->input('discourage_search') === '1');
    }

    private function saveApi(): void
    {
        $this->settings->set('api_cors', $this->input('api_cors'));
        $this->settings->set('api_rate_limit', max(1, (int) $this->input('api_rate_limit', '60')));
    }
}
