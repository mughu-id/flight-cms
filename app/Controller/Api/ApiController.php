<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Security\Capabilities;

use App\Core\PostTypeRegistry;
use App\Repository\CommentRepository;
use App\Repository\FieldGroupRepository;
use App\Repository\MediaRepository;
use App\Repository\MenuRepository;
use App\Repository\PostRepository;
use App\Repository\TermRepository;
use App\Repository\UserRepository;
use App\Service\HtmlSanitizer;
use App\Service\Slugger;
use flight\Engine;

class ApiController
{
    public function __construct(private Engine $app)
    {
    }

    public function index(): void
    {
        $this->ok([
            'name' => 'Flight CMS API',
            'version' => 'v1',
            'endpoints' => [
                'POST /posts' => 'Create a post from JSON or raw HTML. Auth: Bearer token.',
                '/content/{type}',
                '/terms/{taxonomy}',
                '/media',
                '/comments',
                '/menus/{location}',
                '/users/me',
                '/settings',
                '/search',
            ],
        ]);
    }

    public function contentIndex(string $type): void
    {
        $page = max(1, (int) ($this->app->request()->query->page ?? 1));
        $perPage = min(100, max(1, (int) ($this->app->request()->query->per_page ?? 20)));
        $status = (string) ($this->app->request()->query->status ?? 'publish');
        if ($status !== 'publish' && !$this->can('edit_posts')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $result = $this->app->make(PostRepository::class)->list($type, [
            'status' => $status,
            'author' => (int) ($this->app->request()->query->author ?? 0),
            'q' => (string) ($this->app->request()->query->search ?? ''),
            'orderby' => (string) ($this->app->request()->query->orderby ?? 'published_at'),
            'order' => (string) ($this->app->request()->query->order ?? 'desc'),
        ], $page, $perPage);
        $rows = array_map($this->serializePost(...), $result['rows']);
        $this->ok($rows, ['page' => $page, 'per_page' => $perPage, 'total' => $result['total'], 'total_pages' => (int) ceil($result['total'] / $perPage)]);
    }

    public function contentShow(string $type, string $id): void
    {
        $post = $this->app->make(PostRepository::class)->find((int) $id);
        if ($post === null || $post['type'] !== $type) {
            $this->app->jsonHalt(['error' => 'Not found'], 404);
        }
        if ($post['status'] !== 'publish' && !$this->can('edit_posts')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $this->ok($this->serializePost($post));
    }

    public function importPost(): void
    {
        if (!$this->can('edit_posts')) {
            $this->app->jsonHalt(['error' => 'A bearer token with edit_posts is required.'], 401);
        }
        $raw = (string) file_get_contents('php://input');
        $data = json_decode($raw, true);
        $html = is_array($data) ? (string) ($data['html'] ?? $data['content'] ?? '') : $raw;
        $html = trim($html);
        if ($html === '' || !str_contains($html, '<')) {
            $this->app->jsonHalt(['error' => 'Send the article HTML as the body, or JSON with an html field.'], 422);
        }
        $title = is_array($data) ? trim((string) ($data['title'] ?? '')) : '';
        if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $match)) {
            $title = trim(html_entity_decode(strip_tags($match[1])));
            $html = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $html, 1) ?? $html;
        }
        if ($title === '') {
            $title = trim((string) strtok(strip_tags($html), "\n"));
        }
        $title = mb_substr($title !== '' ? $title : 'Untitled', 0, 180);
        $slugger = $this->app->make(Slugger::class);
        $slug = is_array($data) && !empty($data['slug']) ? $slugger->slug((string) $data['slug']) : $slugger->slug($title);
        $base = $slug;
        $n = 2;
        while ($this->app->make(PostRepository::class)->slugTaken('post', $slug, 0, null)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        $excerpt = is_array($data) && !empty($data['excerpt']) ? (string) $data['excerpt'] : mb_substr($text, 0, 180);
        $featured = $this->app->make(\App\Service\FeaturedImage::class)->fromHtml(
            (string) $this->app->get('root'),
            (int) $this->caps()->userId(),
            $html,
            is_array($data) ? trim((string) ($data['featured_image'] ?? '')) : ''
        );
        if (!$this->can('unfiltered_html')) {
            $html = $this->app->make(HtmlSanitizer::class)->clean($html);
        }
        $status = is_array($data) && in_array($data['status'] ?? '', ['draft', 'pending', 'publish', 'private'], true)
            ? (string) $data['status'] : 'publish';
        if ($status === 'publish' && !$this->can('publish_posts')) {
            $status = 'pending';
        }
        $id = $this->app->make(PostRepository::class)->create([
            'type' => 'post',
            'status' => $status,
            'title' => $title,
            'slug' => $slug,
            'path' => '/' . $slug,
            'content' => $html,
            'excerpt' => $excerpt,
            'author_id' => (int) $this->caps()->userId(),
            'parent_id' => 0,
            'menu_order' => 0,
            'template' => '',
            'comment_status' => is_array($data) && ($data['comment_status'] ?? '') === 'closed' ? 'closed' : 'open',
            'comment_count' => 0,
            'featured_media_id' => $featured,
            'published_at' => $status === 'publish' ? gmdate('Y-m-d H:i:s') : null,
        ]);
        $post = $this->app->make(PostRepository::class)->find($id);
        $this->app->db()->runQuery('INSERT INTO posts_fts (rowid, title, body) VALUES (?, ?, ?)', [$id, $title, $text]);
        $this->app->make(\App\Repository\FieldGroupRepository::class)->setMeta($id, '_remote', 'api');
        $this->app->make(\App\Repository\FieldGroupRepository::class)->setMeta($id, '_remote_words', str_word_count($text));
        $meta = $this->app->make(\App\Repository\FieldGroupRepository::class);
        if (is_array($data)) {
            foreach (['seo_title' => '_seo_title', 'seo_description' => '_seo_description', 'seo_canonical' => '_seo_canonical', 'seo_image' => '_seo_image'] as $key => $metaKey) {
                if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                    $meta->setMeta($id, $metaKey, trim($data[$key]));
                }
            }
            if (array_key_exists('seo_noindex', $data)) {
                $meta->setMeta($id, '_seo_noindex', filter_var($data['seo_noindex'], FILTER_VALIDATE_BOOLEAN));
            }
            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $key => $value) {
                    if (is_string($key) && $key !== '' && !str_starts_with($key, '_')) {
                        $meta->setMeta($id, $key, $value);
                    }
                }
            }
            $this->attachNames($id, 'tag', $data['tags'] ?? []);
            $categories = $data['categories'] ?? [];
            if (is_string($categories)) {
                $categories = explode(',', $categories);
            }
            if (trim((string) ($data['category'] ?? '')) !== '') {
                $categories = array_merge([(string) $data['category']], (array) $categories);
            }
            $this->attachNames($id, 'category', $categories);
        }
        $this->app->get('hooks')->doAction('post.saved', $post);
        $this->ok($this->serializePost($post), ['url' => '/' . $slug], 201);
    }

    /** @param list<mixed>|string $names */
    private function attachNames(int $postId, string $taxonomy, array|string $names): void
    {
        if (is_string($names)) {
            $names = explode(',', $names);
        }
        $terms = $this->app->make(TermRepository::class);
        $slugger = $this->app->make(Slugger::class);
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $slug = $slugger->slug($name);
            $term = $terms->findBySlug($taxonomy, $slug);
            $ids[] = $term !== null ? (int) $term['id'] : $terms->create([
                'taxonomy' => $taxonomy,
                'name' => $name,
                'slug' => $slug,
            ]);
        }
        if ($ids !== []) {
            $terms->sync($postId, $taxonomy, $ids);
        }
    }

    public function contentCreate(string $type): void
    {
        if (!$this->can('edit_posts')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $data = $this->jsonBody();
        $id = $this->app->make(PostRepository::class)->create([
            'type' => $type,
            'status' => (string) ($data['status'] ?? 'draft'),
            'title' => (string) ($data['title'] ?? ''),
            'slug' => (string) ($data['slug'] ?? 'item'),
            'path' => '/' . ($data['slug'] ?? 'item'),
            'content' => (string) ($data['content'] ?? ''),
            'excerpt' => (string) ($data['excerpt'] ?? ''),
            'author_id' => (int) $this->caps()->userId(),
            'parent_id' => 0,
            'menu_order' => 0,
            'template' => '',
            'comment_status' => 'open',
            'comment_count' => 0,
            'published_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->ok($this->serializePost($this->app->make(PostRepository::class)->find($id)), [], 201);
    }

    public function contentUpdate(string $type, string $id): void
    {
        if (!$this->can('edit_posts')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $post = $this->app->make(PostRepository::class)->find((int) $id);
        if ($post === null || $post['type'] !== $type) {
            $this->app->jsonHalt(['error' => 'Not found'], 404);
        }
        $data = $this->jsonBody();
        $fields = [];
        foreach (['title', 'slug', 'content', 'excerpt', 'status'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        if ($fields !== []) {
            $this->app->make(PostRepository::class)->update((int) $id, $fields);
        }
        $this->ok($this->serializePost($this->app->make(PostRepository::class)->find((int) $id)));
    }

    public function contentDelete(string $type, string $id): void
    {
        if (!$this->can('delete_posts')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $this->app->make(PostRepository::class)->delete((int) $id);
        $this->ok(['deleted' => true]);
    }

    public function terms(string $taxonomy): void
    {
        $this->ok($this->app->make(TermRepository::class)->forTaxonomy($taxonomy));
    }

    public function media(): void
    {
        $result = $this->app->make(MediaRepository::class)->list([], 1, 40);
        $this->ok($result['rows'], ['total' => $result['total']]);
    }

    public function comments(): void
    {
        if (!$this->can('moderate_comments')) {
            $this->app->jsonHalt(['error' => 'Forbidden'], 403);
        }
        $result = $this->app->make(CommentRepository::class)->list('approved', 1, 40);
        $this->ok($result['rows']);
    }

    public function menu(string $location): void
    {
        $menu = $this->app->make(MenuRepository::class)->forLocation($location);
        $this->ok($menu ? $this->app->make(MenuRepository::class)->tree((int) $menu['id']) : []);
    }

    public function me(): void
    {
        $id = $this->caps()?->userId();
        if ($id === null) {
            $this->app->jsonHalt(['error' => 'Unauthorized'], 401);
        }
        $user = $this->app->make(UserRepository::class)->find($id);
        unset($user['password_hash']);
        $this->ok($user);
    }

    public function settings(): void
    {
        $settings = $this->app->get('settings');
        $public = [
            'site_title' => $settings->get('site_title'),
            'tagline' => $settings->get('tagline'),
            'posts_per_page' => $settings->get('posts_per_page'),
        ];
        if (strtoupper($this->app->request()->method) !== 'GET') {
            if (!$this->can('manage_options')) {
                $this->app->jsonHalt(['error' => 'Forbidden'], 403);
            }
            $data = $this->jsonBody();
            foreach ($data as $key => $value) {
                if (in_array($key, ['site_title', 'tagline', 'posts_per_page'], true)) {
                    $settings->set($key, $value);
                }
            }
            $public = [
                'site_title' => $settings->get('site_title'),
                'tagline' => $settings->get('tagline'),
                'posts_per_page' => $settings->get('posts_per_page'),
            ];
        }
        $this->ok($public);
    }

    public function search(): void
    {
        $q = trim((string) ($this->app->request()->query->q ?? ''));
        $rows = [];
        if ($q !== '') {
            $rows = \App\Support\Row::all($this->app->db()->fetchAll(
                "SELECT p.id, p.title, p.type, p.path FROM posts_fts JOIN posts p ON p.id = posts_fts.rowid
                 WHERE posts_fts MATCH ? AND p.status = 'publish' ORDER BY bm25(posts_fts) LIMIT 20",
                [$q]
            ));
        }
        $this->ok($rows);
    }

    /** @param array<string, mixed> $post */
    private function serializePost(array $post): array
    {
        $fields = $this->app->make(FieldGroupRepository::class)->allMeta((int) $post['id']);
        $public = array_filter($fields, static fn ($_, $k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_BOTH);
        $post['fields'] = $public;
        return apply_filters('api.response', $post);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function can(string $cap): bool
    {
        return $this->caps()->can($cap);
    }

    private function caps(): Capabilities
    {
        return new Capabilities(
            $this->app->session(),
            require dirname(__DIR__, 2) . '/config/roles.php',
            $this->app
        );
    }

    private function ok(mixed $data, array $meta = [], int $code = 200): void
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        $this->app->json($payload, $code);
    }
}
