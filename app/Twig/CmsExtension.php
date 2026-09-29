<?php

declare(strict_types=1);

namespace App\Twig;

use App\Core\Icons;
use App\Core\Permalinks;
use App\Core\Settings;
use App\Core\Shortcodes;
use App\Repository\FieldGroupRepository;
use App\Repository\MenuRepository;
use App\Repository\MediaRepository;
use App\Repository\PostRepository;
use App\Repository\TermRepository;
use App\Service\Csrf;
use App\Service\Seo;
use flight\Engine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CmsExtension extends AbstractExtension
{
    public function __construct(private Engine $app)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', fn (string $name, mixed $default = null): mixed => $this->settings()->get($name, $default)),
            new TwigFunction('theme_mod', fn (string $key, mixed $default = null): mixed => ($this->settings()->get('theme_mods_' . (string) $this->settings()->get('active_theme', ''), []) ?? [])[$key] ?? $default),
            new TwigFunction('permalink', fn (?array $post): string => $post ? $this->app->make(Permalinks::class)->post($post) : ''),
            new TwigFunction('menu', fn (string $location): array => $this->menu($location)),
            new TwigFunction('get_posts', fn (array $args = []): array => $this->posts($args)),
            new TwigFunction('categories', fn (): array => $this->app->make(TermRepository::class)->forTaxonomy('category')),
            new TwigFunction('tags', fn (): array => $this->app->make(TermRepository::class)->forTaxonomy('tag')),
            new TwigFunction('archives', fn (): array => $this->archives()),
            new TwigFunction('recent_comments', fn (): array => $this->recentComments()),
            new TwigFunction('thumb', fn (?array $post): string => $post ? $this->thumb($post) : ''),
            new TwigFunction('topic', fn (?array $post): string => $post ? $this->topic($post) : ''),
            new TwigFunction('category', fn (?array $post): ?array => $post ? $this->category($post) : null),
            new TwigFunction('asset', fn (string $path): string => '/assets/themes/' . $this->settings()->get('active_theme', '') . '/' . ltrim($path, '/')),
            new TwigFunction('field', fn (?array $post, string $key, mixed $default = null): mixed => $post ? $this->app->make(FieldGroupRepository::class)->getMeta((int) $post['id'], $key, $default) : $default),
            new TwigFunction('seo_head', fn (?array $post = null): string => $this->app->make(Seo::class)->head($post), ['is_safe' => ['html']]),
            new TwigFunction('csrf_field', function (): string {
                return '<input type="hidden" name="csrf" value="' . e($this->app->make(Csrf::class)->token()) . '">';
            }, ['is_safe' => ['html']]),
            new TwigFunction('can', fn (string $cap): bool => (bool) $this->app->get('capabilities')?->can($cap)),
            new TwigFunction('do_action', function (string $hook, mixed ...$args): void {
                $this->app->get('hooks')->doAction($hook, ...$args);
            }),
            new TwigFunction('body_class', fn (): string => implode(' ', (array) apply_filters('body_class', ['site']))),
            new TwigFunction('icon', static fn (?string $name): string => Icons::svg($name ?? ''), ['is_safe' => ['html']]),
            new TwigFunction('sidebar_widgets', fn (): array => $this->sidebarWidgets()),
            new TwigFunction('toc', fn (string $html): array => $this->toc($html)),
        ];
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('excerpt', static function (string $html, int $words = 40): string {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
                $parts = explode(' ', $text);
                return count($parts) > $words ? implode(' ', array_slice($parts, 0, $words)) . '...' : $text;
            }),
            new TwigFilter('local_date', function (string $utc, string $format = ''): string {
                $format = $format !== '' ? $format : (string) $this->settings()->get('date_format', 'F j, Y');
                $time = strtotime($utc . ' UTC');
                return $time ? date($format, $time) : '';
            }),
            new TwigFilter('shortcodes', function (string $html): string {
                $html = (string) apply_filters('the_content', $html);
                return $this->app->get('shortcodes')->process($html);
            }, ['is_safe' => ['html']]),
            new TwigFilter('anchors', fn (string $html): string => $this->anchors($html), ['is_safe' => ['html']]),
        ];
    }

    private function settings(): Settings
    {
        return $this->app->get('settings');
    }

    /** @return list<array<string, mixed>> */
    private function menu(string $location): array
    {
        $menu = $this->app->make(MenuRepository::class)->forLocation($location);
        return $menu === null ? [] : $this->app->make(MenuRepository::class)->tree((int) $menu['id']);
    }

    /** @param array<string, mixed> $args */
    private function posts(array $args): array
    {
        $sort = (string) ($args['sort'] ?? '');
        if ($sort === 'random' || $sort === 'popular') {
            $order = $sort === 'random' ? 'RANDOM()' : 'p.comment_count DESC, p.published_at DESC';
            $limit = max(1, min(10, (int) ($args['per_page'] ?? 5)));
            return \App\Support\Row::all($this->app->db()->fetchAll(
                "SELECT p.*, u.display_name AS author_name FROM posts p
                 LEFT JOIN users u ON u.id = p.author_id
                 WHERE p.type = 'post' AND p.status = 'publish'
                 ORDER BY {$order} LIMIT {$limit}"
            ));
        }
        $result = $this->app->make(PostRepository::class)->list(
            (string) ($args['type'] ?? 'post'),
            ['status' => 'publish'],
            (int) ($args['page'] ?? 1),
            (int) ($args['per_page'] ?? 10)
        );
        return $result['rows'];
    }

    /** @return list<string> */
    private function sidebarWidgets(): array
    {
        $saved = $this->settings()->get('sidebar_widgets');
        if (is_array($saved)) {
            return array_values(array_map('strval', $saved));
        }
        $mods = (array) ($this->settings()->get('theme_mods_' . (string) $this->settings()->get('active_theme', ''), []) ?? []);
        $on = [];
        foreach (['sidebar_search' => 'search', 'sidebar_about' => 'about', 'sidebar_topics' => 'topics', 'sidebar_recent' => 'recent'] as $mod => $key) {
            if (($mods[$mod] ?? '1') !== '0') {
                $on[] = $key;
            }
        }
        return $on;
    }

    /** @return list<array{year: string, month: string, label: string, count: int}> */
    private function archives(): array
    {
        $rows = \App\Support\Row::all($this->app->db()->fetchAll(
            "SELECT substr(published_at, 1, 7) AS ym, COUNT(*) AS n FROM posts
             WHERE type = 'post' AND status = 'publish' AND published_at IS NOT NULL
             GROUP BY ym ORDER BY ym DESC LIMIT 8"
        ));
        $out = [];
        foreach ($rows as $row) {
            [$year, $month] = explode('-', (string) $row['ym']);
            $out[] = [
                'year' => $year,
                'month' => $month,
                'label' => date('F Y', strtotime($row['ym'] . '-01 UTC') ?: time()),
                'count' => (int) $row['n'],
            ];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function recentComments(): array
    {
        $rows = \App\Support\Row::all($this->app->db()->fetchAll(
            "SELECT c.author_name, c.content, p.slug, p.published_at FROM comments c
             JOIN posts p ON p.id = c.post_id
             WHERE c.status = 'approved' AND p.status = 'publish'
             ORDER BY c.created_at DESC LIMIT 5"
        ));
        $links = $this->app->make(Permalinks::class);
        foreach ($rows as &$row) {
            $row['content'] = (string) $row['content'];
            $row['url'] = $links->post([
                'type' => 'post',
                'slug' => $row['slug'],
                'published_at' => $row['published_at'],
                'path' => '/' . $row['slug'],
            ]);
        }
        return $rows;
    }

    /** @param array<string, mixed> $post */
    private function thumb(array $post): string
    {
        $src = '';
        $id = (int) ($post['featured_media_id'] ?? 0);
        if ($id > 0) {
            $media = $this->app->make(MediaRepository::class)->find($id);
            $src = $media ? '/uploads/' . $media['path'] : '';
        }
        if ($src === '' && preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', (string) ($post['content'] ?? ''), $m)) {
            $src = html_entity_decode($m[1], ENT_QUOTES);
        }
        return $src === '' ? '' : $this->resized($src);
    }

    /** Serve a cached ~960px jpeg so homepage tiles stay small. */
    private function resized(string $src): string
    {
        if (!function_exists('imagecreatefromstring') || str_ends_with(strtolower($src), '.svg')) {
            return $src;
        }
        $rel = 'uploads/thumbs/' . substr(sha1($src), 0, 16) . '.jpg';
        $file = dirname(__DIR__, 2) . '/' . $rel;
        if (is_file($file)) {
            return '/' . $rel;
        }
        $bytes = $this->imageBytes($src);
        $img = $bytes === null ? false : @imagecreatefromstring($bytes);
        if ($img === false) {
            return $src;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 960;
        if ($w > $max || $h > $max) {
            $scale = min($max / $w, $max / $h);
            $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
            imagedestroy($img);
            $img = $out;
        }
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        imagejpeg($img, $file, 72);
        imagedestroy($img);
        return '/' . $rel;
    }

    private function imageBytes(string $src): ?string
    {
        if (str_starts_with($src, '/')) {
            $file = dirname(__DIR__, 2) . $src;
            return is_file($file) ? (string) file_get_contents($file) : null;
        }
        if (!preg_match('#^https?://#i', $src)) {
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'follow_location' => 1,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36\r\nAccept: image/jpeg,*/*\r\n",
            ],
        ]);
        $bytes = @file_get_contents($this->smallSource($src), false, $ctx, 0, 6_000_000);
        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /** Wikimedia already serves a resized copy; the original file is rate limited. */
    private function smallSource(string $src): string
    {
        if (preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons/)((?:[0-9a-f]/){1,2})([^/]+\.(?:jpe?g|png|webp))$#i', $src, $m)) {
            return $m[1] . 'thumb/' . $m[2] . $m[3] . '/960px-' . $m[3];
        }
        return $src;
    }

    /** @param array<string, mixed> $post */
    private function topic(array $post): string
    {
        return (string) ($this->category($post)['name'] ?? '');
    }

    /** @return list<array{id: string, text: string, level: int}> */
    private function toc(string $html): array
    {
        preg_match_all('/<h([2-4])\b[^>]*\sid="([^"]+)"[^>]*>(.*?)<\/h\1>/is', $html, $found, PREG_SET_ORDER);
        $items = [];
        foreach ($found as $heading) {
            $text = trim(html_entity_decode(strip_tags($heading[3])));
            if ($text === '') {
                continue;
            }
            $items[] = ['id' => $heading[2], 'text' => $text, 'level' => (int) $heading[1]];
        }
        return $items;
    }

    private function anchors(string $html): string
    {
        $seen = [];
        $out = preg_replace_callback(
            '/<h([2-4])(\s[^>]*)?>(.*?)<\/h\1>/is',
            function (array $m) use (&$seen): string {
                $attrs = $m[2] ?? '';
                if (preg_match('/\sid="/i', $attrs)) {
                    return $m[0];
                }
                $text = trim(html_entity_decode(strip_tags($m[3])));
                $base = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
                $base = $base !== '' ? 's-' . $base : 's-section';
                $id = $base;
                $n = 2;
                while (isset($seen[$id])) {
                    $id = $base . '-' . $n++;
                }
                $seen[$id] = true;
                return '<h' . $m[1] . $attrs . ' id="' . $id . '">' . $m[3] . '</h' . $m[1] . '>';
            },
            $html
        );
        return $out ?? $html;
    }

    /** @param array<string, mixed> $post @return array<string, mixed>|null */
    private function category(array $post): ?array
    {
        $terms = $this->app->make(TermRepository::class)->forPost((int) $post['id'], 'category');
        return $terms[0] ?? null;
    }
}
