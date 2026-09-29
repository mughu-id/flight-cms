<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Permalinks;
use App\Core\ThemeManager;
use App\Repository\PostRepository;
use App\Repository\TermRepository;
use App\Repository\UserRepository;

class FrontController extends Controller
{
    public function home(?string $n = null): void
    {
        if ($this->settings->get('show_on_front') === 'page') {
            $pagePost = $this->app->make(PostRepository::class)->find((int) $this->settings->get('page_on_front', 0));
            if ($pagePost !== null) {
                $this->theme(['front-page', 'home', 'index'], ['post' => $pagePost]);
                return;
            }
        }
        $page = max(1, (int) ($n ?? 1));
        $perPage = (int) $this->settings->get('posts_per_page', 10);
        $result = $this->app->make(PostRepository::class)->list('post', ['status' => 'publish'], $page, $perPage);
        $data = [
            'posts' => $result['rows'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / max(1, $perPage)),
            'pagination' => (string) $this->settings->get('pagination_mode', 'numbers'),
        ];
        if (($this->app->request()->query->feed ?? '') === '1') {
            $data['site_title'] = (string) $this->settings->get('site_title', '');
            echo $this->app->make(ThemeManager::class)->render(['feed-items'], $data);
            return;
        }
        $this->theme(['home', 'index'], $data);
    }

    public function archive(string $type, array $templates, string $pageParam = 'n'): void
    {
        $page = max(1, (int) ($this->app->request()->query->{$pageParam} ?? 1));
        $perPage = (int) $this->settings->get('posts_per_page', 10);
        $result = $this->app->make(PostRepository::class)->list($type, ['status' => 'publish'], $page, $perPage);
        $this->theme($templates, [
            'posts' => $result['rows'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / max(1, $perPage)),
        ]);
    }

    public function term(string $taxonomy, string $slug): void
    {
        $term = $this->app->make(TermRepository::class)->findBySlug($taxonomy, $slug);
        if ($term === null) {
            $this->notFound();
            return;
        }
        $base = $taxonomy === 'tag' ? 'tag' : 'category';
        $this->theme([$base . '-' . $slug, $base, 'archive', 'index'], ['term' => $term, 'posts' => $this->postsForTerm($term)]);
    }

    public function author(string $username): void
    {
        $user = $this->app->make(UserRepository::class)->findByLogin($username);
        if ($user === null) {
            $this->notFound();
            return;
        }
        $result = $this->app->make(PostRepository::class)->list('post', ['status' => 'publish', 'author' => (int) $user['id']], 1, 20);
        $this->theme(['author', 'archive', 'index'], ['author' => $user, 'posts' => $result['rows']]);
    }

    public function date(string $year, ?string $month = null, ?string $day = null): void
    {
        $this->theme(['date', 'archive', 'index'], ['year' => $year, 'month' => $month, 'day' => $day, 'posts' => []]);
    }

    public function search(?string $query = null): void
    {
        $q = trim(rawurldecode((string) $query));
        if ($q === '' && ($legacy = trim((string) ($this->app->request()->query->q ?? ''))) !== '') {
            $this->redirect('/search/' . rawurlencode($legacy));
        }
        $rows = [];
        if ($q !== '') {
            $rows = \App\Support\Row::all($this->app->db()->fetchAll(
                "SELECT p.*, u.display_name AS author_name
                 FROM posts_fts JOIN posts p ON p.id = posts_fts.rowid
                 LEFT JOIN users u ON u.id = p.author_id
                 WHERE posts_fts MATCH ? AND p.status = 'publish' ORDER BY bm25(posts_fts) LIMIT 20",
                ['"' . str_replace('"', '""', $q) . '"']
            ));
        }
        $this->theme(['search', 'index'], ['query' => $q, 'posts' => $rows]);
    }

    public function resolve(): void
    {
        $path = '/' . trim((string) $this->app->request()->url, '/');
        $post = \App\Support\Row::one($this->app->db()->fetchRow(
            "SELECT * FROM posts WHERE path = ? AND status = 'publish'",
            [$path]
        ));
        if ($post === null) {
            $slug = basename($path);
            $post = \App\Support\Row::one($this->app->db()->fetchRow(
                "SELECT * FROM posts WHERE slug = ? AND status = 'publish'",
                [$slug]
            ));
            if ($post !== null && $this->app->make(Permalinks::class)->post($post) !== $path) {
                $post = null;
            }
        }
        if ($post === null) {
            $redirect = \App\Support\Row::one($this->app->db()->fetchRow('SELECT * FROM redirects WHERE source_path = ?', [$path]));
            if ($redirect !== null) {
                $this->app->db()->runQuery('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [(int) $redirect['id']]);
                $this->app->redirect((string) $redirect['target_path'], (int) $redirect['status_code']);
                return;
            }
            $this->notFound();
            return;
        }
        $type = (string) $post['type'];
        $templates = $type === 'page'
            ? array_filter(['page-' . $post['template'], 'page-' . $post['slug'], 'page', 'singular', 'index'])
            : ['single-' . $type . '-' . $post['slug'], 'single-' . $type, 'single', 'singular', 'index'];
        $comments = $this->app->make(\App\Repository\CommentRepository::class)->forPost((int) $post['id']);
        $this->theme($templates, ['post' => $post, 'comments' => $comments]);
    }

    /** @param array<string, mixed> $term */
    private function postsForTerm(array $term): array
    {
        return \App\Support\Row::all($this->app->db()->fetchAll(
            "SELECT p.*, u.display_name AS author_name FROM posts p
             JOIN term_relationships r ON r.post_id = p.id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE r.term_id = ? AND p.status = 'publish' ORDER BY p.published_at DESC",
            [(int) $term['id']]
        ));
    }

    /** @param list<string> $templates @param array<string, mixed> $data */
    private function theme(array $templates, array $data): void
    {
        $manager = $this->app->make(ThemeManager::class);
        $templates = apply_filters('template.candidates', $templates);
        $data = apply_filters('template.context', $data);
        echo $manager->render($templates, $data + [
            'site_title' => (string) $this->settings->get('site_title', ''),
            'nonce' => $this->app->get('csp_nonce'),
            'flash' => $this->flash->pull(),
        ]);
    }

    private function notFound(): void
    {
        $this->app->response()->status(404);
        $this->theme(['404', 'index'], []);
    }
}
