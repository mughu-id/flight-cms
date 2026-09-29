<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\PostTypeRegistry;
use App\Core\TaxonomyRegistry;
use App\Repository\FieldGroupRepository;
use App\Repository\PostRepository;
use App\Repository\RevisionRepository;
use App\Repository\TermRepository;
use App\Service\HtmlSanitizer;
use App\Service\RevisionDiff;
use App\Service\Slugger;

class ContentController extends DashboardController
{
    public function index(string $type): void
    {
        $def = $this->type($type);
        $this->forbid($this->cap($type, 'edit'));
        $status = $this->input('status');
        $page = max(1, (int) $this->input('page', '1'));
        $result = $this->posts()->list($type, [
            'status' => $status,
            'author' => (int) $this->input('author', '0'),
            'month' => $this->input('month'),
            'q' => $this->input('q'),
            'orderby' => $this->input('orderby', 'updated_at'),
            'order' => $this->input('order', 'desc'),
        ], $page, 20);
        $this->admin('admin/content/index', [
            'title' => $def['label'],
            'type' => $def,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'counts' => $this->posts()->statusCounts($type),
            'status' => $status,
            'q' => $this->input('q'),
            'per_page' => 20,
        ]);
    }

    public function create(string $type): void
    {
        $def = $this->type($type);
        $this->forbid($this->cap($type, 'edit'));
        $this->admin('admin/content/form', [
            'title' => 'New ' . $def['label_singular'],
            'type' => $def,
            'post' => null,
            'pages' => $this->pageOptions($type),
            'taxonomies' => $this->taxonomyData($type, null),
            'field_groups' => $this->app->make(FieldGroupRepository::class)->forType($type),
            'meta' => [],
        ]);
    }

    public function store(string $type): void
    {
        $this->type($type);
        $this->forbid($this->cap($type, 'edit'));
        $id = $this->saveNew($type);
        $fresh = $this->posts()->find($id);
        $this->app->make(RevisionRepository::class)->save(
            $fresh,
            (int) $this->app->get('capabilities')->userId(),
            false,
            (int) $this->settings->get('revisions_keep', 20)
        );
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/content/' . $type . '/' . $id);
    }

    public function edit(string $type, string $id): void
    {
        $def = $this->type($type);
        $post = $this->requirePost($type, (int) $id);
        $this->assertCanEdit($post);
        $autosave = $this->app->make(RevisionRepository::class);
        $latest = null;
        foreach ($autosave->forPost((int) $post['id']) as $rev) {
            if ((int) $rev['is_autosave'] === 1 && $rev['created_at'] > $post['updated_at']) {
                $latest = $rev;
            }
        }
        $this->admin('admin/content/form', [
            'title' => 'Edit ' . $def['label_singular'],
            'type' => $def,
            'post' => $post,
            'pages' => $this->pageOptions($type, (int) $post['id']),
            'autosave' => $latest,
            'taxonomies' => $this->taxonomyData($type, (int) $post['id']),
            'field_groups' => $this->app->make(FieldGroupRepository::class)->forType($type),
            'meta' => $this->app->make(FieldGroupRepository::class)->allMeta((int) $post['id']),
        ]);
    }

    public function update(string $type, string $id): void
    {
        $post = $this->requirePost($type, (int) $id);
        $this->assertCanEdit($post);
        $this->write($post, false);
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/content/' . $type . '/' . $id);
    }

    public function autosave(string $type, string $id): void
    {
        $post = $id === '0' ? null : $this->requirePost($type, (int) $id);
        if ($post === null) {
            $this->forbid($this->cap($type, 'edit'));
            $newId = $this->saveNew($type, 'draft');
            $post = $this->requirePost($type, $newId);
        } else {
            $this->assertCanEdit($post);
        }
        $this->write($post, true);
        $this->app->json(['id' => (int) $post['id']]);
    }

    public function bulk(string $type): void
    {
        $this->type($type);
        $action = $this->input('action');
        $ids = $this->app->request()->data->ids ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        foreach ($ids as $id) {
            $post = $this->posts()->find((int) $id);
            if ($post === null || $post['type'] !== $type) {
                continue;
            }
            if ($action === 'delete') {
                $this->assertCanDelete($post);
                $this->posts()->delete((int) $post['id']);
                $this->app->get('hooks')->doAction('post.deleted', $post);
                continue;
            }
            $this->assertCanEdit($post);
            $status = match ($action) {
                'publish' => 'publish',
                'draft' => 'draft',
                'trash' => 'trash',
                'restore' => 'draft',
                default => '',
            };
            if ($status === '') {
                continue;
            }
            if ($status === 'publish') {
                $this->forbid($this->cap($type, 'publish'));
            }
            $this->posts()->update((int) $post['id'], ['status' => $status]);
            $this->app->get('hooks')->doAction('post.status_changed', $post, $status);
        }
        $this->flash->set('success', $action === 'delete' ? 'Deleted.' : 'Updated.');
        $this->redirect('/admin/content/' . $type);
    }

    public function feature(string $type, string $id): void
    {
        $post = $this->requirePost($type, (int) $id);
        $this->assertCanEdit($post);
        $mediaId = $this->app->make(\App\Service\FeaturedImage::class)->fromHtml(
            (string) $this->app->get('root'),
            (int) $this->app->get('capabilities')->userId(),
            (string) $post['content']
        );
        if ($mediaId > 0) {
            $this->posts()->update((int) $post['id'], ['featured_media_id' => $mediaId]);
            $this->flash->set('success', 'Featured image set from the first image.');
        } else {
            $this->flash->set('error', 'No usable image found in the content.');
        }
        $this->redirect('/admin/content/' . $type);
    }

    public function revisions(string $type, string $id): void
    {
        $post = $this->requirePost($type, (int) $id);
        $this->assertCanEdit($post);
        $revs = $this->app->make(RevisionRepository::class)->forPost((int) $post['id']);
        $diff = '';
        $a = (int) $this->input('a', '0');
        $b = (int) $this->input('b', '0');
        if ($a > 0 && $b > 0) {
            $left = $this->app->make(RevisionRepository::class)->find($a);
            $right = $this->app->make(RevisionRepository::class)->find($b);
            if ($left && $right) {
                $diff = $this->app->make(RevisionDiff::class)->html(
                    (string) $left['content'],
                    (string) $right['content']
                );
            }
        }
        $this->admin('admin/content/revisions', [
            'title' => 'Revisions',
            'type' => $this->type($type),
            'post' => $post,
            'revisions' => $revs,
            'diff' => $diff,
        ]);
    }

    public function restore(string $type, string $id, string $rev): void
    {
        $post = $this->requirePost($type, (int) $id);
        $this->assertCanEdit($post);
        $revision = $this->app->make(RevisionRepository::class)->find((int) $rev);
        if ($revision === null || (int) $revision['post_id'] !== (int) $post['id']) {
            $this->app->halt(404, 'Revision not found');
        }
        $this->posts()->update((int) $post['id'], [
            'title' => $revision['title'],
            'content' => $revision['content'],
            'excerpt' => $revision['excerpt'],
        ]);
        $this->flash->set('success', 'Revision restored.');
        $this->redirect('/admin/content/' . $type . '/' . $id);
    }

    private function saveNew(string $type, ?string $forceStatus = null): int
    {
        $fields = $this->fields($type, null, $forceStatus);
        $id = $this->posts()->create($fields + ['type' => $type, 'author_id' => (int) $this->app->get('capabilities')->userId()]);
        $post = $this->posts()->find($id);
        $this->posts()->indexSearch($id, (string) $post['title'], strip_tags((string) $post['content']));
        $this->assignTerms($id, $type);
        $this->saveMeta($id);
        $this->app->get('hooks')->doAction('post.saved', $post);
        return $id;
    }

    /** @param array<string, mixed> $post */
    private function write(array $post, bool $autosave): void
    {
        $before = $post;
        $fields = $this->fields((string) $post['type'], $post, $autosave ? (string) $post['status'] : null);
        $this->posts()->update((int) $post['id'], $fields);
        $fresh = $this->posts()->find((int) $post['id']);
        $this->posts()->indexSearch((int) $post['id'], (string) $fresh['title'], strip_tags((string) $fresh['content']));
        $keep = (int) $this->settings->get('revisions_keep', 20);
        $this->app->make(RevisionRepository::class)->save($fresh, (int) $this->app->get('capabilities')->userId(), $autosave, $keep);
        if (!$autosave) {
            $this->assignTerms((int) $post['id'], (string) $post['type']);
            $this->saveMeta((int) $post['id']);
            if (($before['path'] ?? '') !== ($fresh['path'] ?? '') && $before['status'] === 'publish') {
                $this->app->db()->runQuery(
                    'INSERT OR IGNORE INTO redirects (source_path, target_path, status_code, hits) VALUES (?, ?, 301, 0)',
                    [$before['path'], $fresh['path']]
                );
            }
            $this->app->get('hooks')->doAction('post.saved', $fresh, $before);
            if ($before['status'] !== $fresh['status']) {
                $this->app->get('hooks')->doAction('post.status_changed', $fresh, $fresh['status']);
            }
        }
    }

    /** @param array<string, mixed>|null $existing */
    private function fields(string $type, ?array $existing, ?string $forceStatus): array
    {
        $title = $this->input('title');
        $slug = $this->input('slug');
        if ($slug === '') {
            $slug = $this->app->make(Slugger::class)->slug($title !== '' ? $title : 'untitled');
        } else {
            $slug = $this->app->make(Slugger::class)->slug($slug);
        }
        $parent = (int) $this->input('parent_id', '0');
        $base = $slug;
        $n = 2;
        while ($this->posts()->slugTaken($type, $slug, $parent, $existing['id'] ?? null)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $content = (string) ($this->app->request()->data->content ?? '');
        if (!$this->app->get('capabilities')->can('unfiltered_html')) {
            $content = $this->app->make(HtmlSanitizer::class)->clean($content);
        }
        $status = $forceStatus ?? $this->input('status', 'draft');
        if (!in_array($status, ['draft', 'pending', 'publish', 'private', 'trash'], true)) {
            $status = 'draft';
        }
        if ($status === 'publish' && !$this->app->get('capabilities')->can($this->cap($type, 'publish'))) {
            $status = 'pending';
        }
        $published = str_replace('T', ' ', $this->input('published_at'));
        if ($published === '') {
            $published = $status === 'publish' ? ($existing['published_at'] ?? gmdate('Y-m-d H:i:s')) : null;
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $published)) {
            $published = substr($published, 0, 16) . ':00';
        } else {
            $published = gmdate('Y-m-d H:i:s', strtotime($published) ?: time());
        }
        $path = $type === 'page' ? $this->pagePath($slug, $parent, $existing['id'] ?? null) : '/' . $slug;
        return [
            'status' => $status,
            'title' => $title,
            'slug' => $slug,
            'path' => $path,
            'content' => $content,
            'excerpt' => $this->input('excerpt'),
            'parent_id' => $parent,
            'menu_order' => (int) $this->input('menu_order', '0'),
            'template' => $this->input('template'),
            'comment_status' => $this->input('comment_status') === 'closed' ? 'closed' : 'open',
            'published_at' => $published,
        ];
    }

    private function pagePath(string $slug, int $parentId, ?int $self): string
    {
        $parts = [$slug];
        $guard = 0;
        while ($parentId > 0 && $guard < 10) {
            $parent = $this->posts()->find($parentId);
            if ($parent === null || ($self !== null && (int) $parent['id'] === $self)) {
                break;
            }
            array_unshift($parts, (string) $parent['slug']);
            $parentId = (int) $parent['parent_id'];
            $guard++;
        }
        return '/' . implode('/', $parts);
    }

    /** @return list<array<string, mixed>> */
    private function pageOptions(string $type, ?int $ignore = null): array
    {
        if ($type !== 'page') {
            return [];
        }
        $rows = $this->posts()->list('page', ['status' => ''], 1, 200)['rows'];
        return array_values(array_filter($rows, static fn (array $row): bool => (int) $row['id'] !== $ignore));
    }

    /** @return array<string, mixed> */
    private function type(string $type): array
    {
        $def = $this->app->make(PostTypeRegistry::class)->get($type);
        if ($def === null) {
            $this->app->halt(404, 'Unknown content type');
        }
        return $def;
    }

    /** @return array<string, mixed> */
    private function requirePost(string $type, int $id): array
    {
        $post = $this->posts()->find($id);
        if ($post === null || $post['type'] !== $type) {
            $this->app->halt(404, 'Not found');
        }
        return $post;
    }

    /** @param array<string, mixed> $post */
    private function assertCanEdit(array $post): void
    {
        $caps = $this->app->get('capabilities');
        $own = (int) $post['author_id'] === $caps->userId();
        $cap = $own ? $this->cap((string) $post['type'], 'edit') : $this->cap((string) $post['type'], 'edit_others');
        if (!$caps->can($cap)) {
            $this->app->halt(403, 'You cannot edit this.');
        }
    }

    /** @param array<string, mixed> $post */
    private function assertCanDelete(array $post): void
    {
        $caps = $this->app->get('capabilities');
        $own = (int) $post['author_id'] === $caps->userId();
        $cap = $own ? $this->cap((string) $post['type'], 'delete') : $this->cap((string) $post['type'], 'delete_others');
        if (!$caps->can($cap)) {
            $this->app->halt(403, 'You cannot delete this.');
        }
    }

    private function cap(string $type, string $action): string
    {
        $noun = $type === 'page' ? 'pages' : 'posts';
        return match ($action) {
            'edit' => 'edit_' . $noun,
            'edit_others' => 'edit_others_' . $noun,
            'publish' => 'publish_' . $noun,
            'delete' => 'delete_' . $noun,
            'delete_others' => 'delete_others_' . $noun,
            default => 'edit_' . $noun,
        };
    }

    private function saveMeta(int $postId): void
    {
        $repo = $this->app->make(FieldGroupRepository::class);
        $repo->setMeta($postId, '_seo_title', $this->input('seo_title'));
        $repo->setMeta($postId, '_seo_description', $this->input('seo_description'));
        $repo->setMeta($postId, '_seo_canonical', $this->input('seo_canonical'));
        $repo->setMeta($postId, '_seo_noindex', $this->input('seo_noindex') === '1');
        $repo->setMeta($postId, '_seo_image', $this->input('seo_image'));
        $featured = (int) $this->input('featured_media_id', '0');
        if ($featured > 0) {
            $this->posts()->update($postId, ['featured_media_id' => $featured]);
        }
        foreach ((array) ($this->app->request()->data->fields ?? []) as $key => $value) {
            if (is_string($key) && !str_starts_with($key, '_')) {
                $repo->setMeta($postId, $key, $value);
            }
        }
    }

    private function assignTerms(int $postId, string $type): void
    {
        $def = $this->app->make(PostTypeRegistry::class)->get($type);
        $terms = $this->app->make(TermRepository::class);
        $taxonomies = $this->app->make(TaxonomyRegistry::class);
        foreach ((array) ($def['taxonomies'] ?? []) as $slug) {
            $taxonomy = $taxonomies->get($slug);
            if ($taxonomy === null) {
                continue;
            }
            $ids = [];
            if ($taxonomy['hierarchical']) {
                $raw = $this->app->request()->data->{'terms_' . $slug} ?? [];
                $ids = array_map('intval', is_array($raw) ? $raw : []);
            } else {
                foreach (explode(',', (string) ($this->app->request()->data->{'terms_' . $slug} ?? '')) as $name) {
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }
                    $found = $terms->findBySlug($slug, $this->app->make(Slugger::class)->slug($name));
                    $ids[] = $found !== null ? (int) $found['id'] : $terms->create([
                        'taxonomy' => $slug,
                        'name' => $name,
                        'slug' => $this->app->make(Slugger::class)->slug($name),
                    ]);
                }
            }
            if ($slug === 'category' && $ids === []) {
                $ids[] = (int) $this->settings->get('default_category', 0);
            }
            $terms->sync($postId, $slug, array_filter($ids));
        }
    }

    /** @return array<string, array{def: array<string, mixed>, terms: list<array<string, mixed>>, selected: list<int>}> */
    private function taxonomyData(string $type, ?int $postId): array
    {
        $def = $this->app->make(PostTypeRegistry::class)->get($type);
        $terms = $this->app->make(TermRepository::class);
        $taxonomies = $this->app->make(TaxonomyRegistry::class);
        $out = [];
        foreach ((array) ($def['taxonomies'] ?? []) as $slug) {
            $taxonomy = $taxonomies->get($slug);
            if ($taxonomy === null) {
                continue;
            }
            $selected = $postId === null ? [] : array_column($terms->forPost($postId, $slug), 'id');
            $out[$slug] = ['def' => $taxonomy, 'terms' => $terms->forTaxonomy($slug), 'selected' => array_map('intval', $selected)];
        }
        return $out;
    }

    private function posts(): PostRepository
    {
        return $this->app->make(PostRepository::class);
    }
}
