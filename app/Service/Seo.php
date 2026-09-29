<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Permalinks;
use App\Core\Settings;
use App\Repository\FieldGroupRepository;
use App\Repository\TermRepository;

class Seo
{
    public function __construct(
        private Settings $settings,
        private FieldGroupRepository $meta,
        private Permalinks $permalinks,
        private TermRepository $terms,
    ) {
    }

    /** @param array<string, mixed>|null $post */
    public function head(?array $post = null): string
    {
        $site = (string) $this->settings->get('site_title', '');
        $sep = (string) $this->settings->get('title_separator', '-');
        $title = $post
            ? ((string) $this->meta->getMeta((int) $post['id'], '_seo_title', $post['title']) . " {$sep} {$site}")
            : ((string) $this->settings->get('seo_home_title') ?: $site);
        $desc = $post
            ? (string) $this->meta->getMeta((int) $post['id'], '_seo_description', strip_tags((string) ($post['excerpt'] ?: $post['content'])))
            : (string) $this->settings->get('seo_home_description', '');
        $desc = mb_substr(trim(preg_replace('/\s+/', ' ', $desc) ?? ''), 0, 160);
        $canonical = $post
            ? (string) $this->meta->getMeta((int) $post['id'], '_seo_canonical', '')
            : '';
        if ($post && $canonical === '') {
            $canonical = $this->absolute($this->permalinks->post($post));
        }
        $noindex = $post ? (bool) $this->meta->getMeta((int) $post['id'], '_seo_noindex', false) : (bool) $this->settings->get('discourage_search', false);
        $image = $post ? (string) $this->meta->getMeta((int) $post['id'], '_seo_image', '') : (string) $this->settings->get('seo_default_image', '');
        $meta = apply_filters('seo.meta', [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'noindex' => $noindex,
            'image' => $image,
            'post' => $post,
        ]);
        $html = '<meta name="description" content="' . e($meta['description']) . '">' . "\n";
        if ($meta['canonical'] !== '') {
            $html .= '<link rel="canonical" href="' . e($meta['canonical']) . '">' . "\n";
        }
        if ($meta['noindex']) {
            $html .= '<meta name="robots" content="noindex,nofollow">' . "\n";
        }
        $html .= '<meta property="og:site_name" content="' . e($site) . '">' . "\n";
        $html .= '<meta property="og:title" content="' . e($meta['title']) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . e($meta['description']) . '">' . "\n";
        $html .= '<meta property="og:type" content="' . ($post ? 'article' : 'website') . '">' . "\n";
        if ($meta['canonical'] !== '') {
            $html .= '<meta property="og:url" content="' . e($meta['canonical']) . '">' . "\n";
        }
        if ($meta['image'] !== '') {
            $html .= '<meta property="og:image" content="' . e($meta['image']) . '">' . "\n";
            $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
            $html .= '<meta name="twitter:image" content="' . e($meta['image']) . '">' . "\n";
        } else {
            $html .= '<meta name="twitter:card" content="summary">' . "\n";
        }
        $html .= '<meta name="twitter:title" content="' . e($meta['title']) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . e($meta['description']) . '">' . "\n";
        $html .= '<link rel="alternate" type="application/rss+xml" title="' . e($site) . ' Feed" href="/feed">' . "\n";
        if ($post) {
            $published = $this->iso((string) ($post['published_at'] ?? ''));
            if ($published !== '') {
                $html .= '<meta property="article:published_time" content="' . e($published) . '">' . "\n";
            }
            $crumbs = [
                ['name' => $site !== '' ? $site : 'Home', 'url' => $this->absolute('/')],
            ];
            $category = $this->terms->forPost((int) $post['id'], 'category')[0] ?? null;
            if ($category) {
                $crumbs[] = ['name' => (string) $category['name'], 'url' => $this->absolute($this->permalinks->term($category))];
            }
            if ($meta['canonical'] !== '') {
                $crumbs[] = ['name' => (string) $post['title'], 'url' => $meta['canonical']];
            }
            $json = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Article',
                        'headline' => $post['title'],
                        'datePublished' => $published,
                        'dateModified' => $this->iso((string) ($post['updated_at'] ?? $post['published_at'] ?? '')),
                        'description' => $meta['description'],
                        'mainEntityOfPage' => $meta['canonical'],
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => array_values(array_map(static fn (array $crumb, int $i): array => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'name' => $crumb['name'],
                            'item' => $crumb['url'],
                        ], $crumbs, array_keys($crumbs))),
                    ],
                ],
            ];
            $html .= '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_SLASHES) . '</script>';
        }
        return $html;
    }

    private function absolute(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = rtrim((string) $this->settings->get('site_url', ''), '/');
        if ($base === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $https . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return $base . ($path === '' ? '/' : $path);
    }

    private function iso(string $utc): string
    {
        $time = strtotime($utc . ' UTC');
        return $time ? gmdate('c', $time) : '';
    }
}
