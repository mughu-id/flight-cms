<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\PostTypeRegistry;
use App\Core\Settings;
use App\Repository\PostRepository;
use App\Repository\TermRepository;
use App\Support\Row;
use flight\Engine;

class FeedController
{
    public function __construct(private Engine $app, private Settings $settings)
    {
    }

    public function feed(): void
    {
        $posts = $this->app->make(PostRepository::class)->list('post', ['status' => 'publish'], 1, 20)['rows'];
        $posts = apply_filters('feed.items', $posts);
        $this->rss($posts, (string) $this->settings->get('site_title', 'Feed'));
    }

    public function robots(): void
    {
        $discourage = (bool) $this->settings->get('discourage_search', false);
        $body = $discourage
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nAllow: /\nSitemap: " . rtrim((string) $this->app->get('config')->get('app.url'), '/') . "/sitemap.xml\n";
        $this->app->response()->header('Content-Type', 'text/plain; charset=utf-8');
        $this->app->response()->write($body);
    }

    public function sitemap(): void
    {
        $base = rtrim((string) $this->app->get('config')->get('app.url'), '/');
        $urls = apply_filters('sitemap.urls', [
            ['loc' => $base . '/sitemap-posts.xml'],
            ['loc' => $base . '/sitemap-pages.xml'],
            ['loc' => $base . '/sitemap-taxonomies.xml'],
        ]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<sitemap><loc>' . htmlspecialchars($url['loc']) . '</loc></sitemap>';
        }
        $xml .= '</sitemapindex>';
        $this->app->response()->header('Content-Type', 'application/xml; charset=utf-8');
        $this->app->response()->write($xml);
    }

    public function sitemapName(string $name): void
    {
        $base = rtrim((string) $this->app->get('config')->get('app.url'), '/');
        $urls = [];
        if ($name === 'posts' || $name === 'pages') {
            $type = $name === 'pages' ? 'page' : 'post';
            foreach ($this->app->make(PostRepository::class)->list($type, ['status' => 'publish'], 1, 500)['rows'] as $post) {
                $urls[] = $base . ($post['path'] ?: '/' . $post['slug']);
            }
        } elseif ($name === 'taxonomies') {
            foreach (['category', 'tag'] as $tax) {
                foreach ($this->app->make(TermRepository::class)->forTaxonomy($tax) as $term) {
                    $urls[] = $base . '/' . ($tax === 'tag' ? 'tag' : 'category') . '/' . $term['slug'];
                }
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>' . htmlspecialchars($url) . '</loc></url>';
        }
        $xml .= '</urlset>';
        $this->app->response()->header('Content-Type', 'application/xml; charset=utf-8');
        $this->app->response()->write($xml);
    }

    /** @param list<array<string, mixed>> $posts */
    private function rss(array $posts, string $title): void
    {
        $base = rtrim((string) $this->app->get('config')->get('app.url'), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>';
        $xml .= '<title>' . htmlspecialchars($title) . '</title><link>' . htmlspecialchars($base) . '</link>';
        foreach ($posts as $post) {
            $link = $base . ($post['path'] ?: '/' . $post['slug']);
            $xml .= '<item><title>' . htmlspecialchars((string) $post['title']) . '</title>';
            $xml .= '<link>' . htmlspecialchars($link) . '</link>';
            $xml .= '<description><![CDATA[' . $post['content'] . ']]></description>';
            $xml .= '<pubDate>' . gmdate(DATE_RSS, strtotime((string) $post['published_at'] . ' UTC') ?: time()) . '</pubDate></item>';
        }
        $xml .= '</channel></rss>';
        $this->app->response()->header('Content-Type', 'application/rss+xml; charset=utf-8');
        $this->app->response()->write($xml);
    }
}
