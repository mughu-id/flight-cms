<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\PostTypeRegistry;
use App\Repository\FieldGroupRepository;
use App\Service\Slugger;

class PostTypeController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('manage_post_types');
        $this->admin('admin/post-types/index', [
            'title' => 'Post types',
            'types' => $this->app->make(PostTypeRegistry::class)->all(),
        ]);
    }

    public function create(): void
    {
        $this->forbid('manage_post_types');
        $this->admin('admin/post-types/form', ['title' => 'Add post type', 'type' => null]);
    }

    public function store(): void
    {
        $this->forbid('manage_post_types');
        $slug = $this->app->make(Slugger::class)->slug($this->input('slug') ?: $this->input('label_singular'));
        $reserved = ['post', 'page', 'admin', 'api', 'feed', 'search', 'category', 'tag', 'author', 'assets', 'preview', 'comments', 'install'];
        if (in_array($slug, $reserved, true) || $this->app->make(PostTypeRegistry::class)->get($slug)) {
            $this->flash->set('error', 'Slug is reserved or taken.');
            $this->redirect('/admin/post-types/new');
        }
        $this->app->db()->insert('post_types', $this->payload($slug));
        $this->flash->set('success', 'Created.');
        $this->redirect('/admin/post-types');
    }

    public function edit(string $slug): void
    {
        $this->forbid('manage_post_types');
        $type = $this->app->make(PostTypeRegistry::class)->get($slug);
        if ($type === null) {
            $this->app->halt(404, 'Not found');
        }
        $this->admin('admin/post-types/form', ['title' => 'Edit post type', 'type' => $type]);
    }

    public function update(string $slug): void
    {
        $this->forbid('manage_post_types');
        $type = $this->app->make(PostTypeRegistry::class)->get($slug);
        if ($type === null || $type['is_builtin']) {
            $this->flash->set('error', 'Cannot edit built-in types this way.');
            $this->redirect('/admin/post-types');
        }
        $data = $this->payload($slug);
        unset($data['slug']);
        $this->app->db()->update('post_types', $data, 'slug = ?', [$slug]);
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/post-types');
    }

    public function delete(string $slug): void
    {
        $this->forbid('manage_post_types');
        $type = $this->app->make(PostTypeRegistry::class)->get($slug);
        if ($type === null || $type['is_builtin']) {
            $this->flash->set('error', 'Cannot delete built-in types.');
            $this->redirect('/admin/post-types');
        }
        $this->app->db()->delete('post_types', 'slug = ?', [$slug]);
        $this->flash->set('success', 'Deleted.');
        $this->redirect('/admin/post-types');
    }

    /** @return array<string, mixed> */
    private function payload(string $slug): array
    {
        $supports = (array) ($this->app->request()->data->supports ?? []);
        $taxonomies = (array) ($this->app->request()->data->taxonomies ?? []);
        return [
            'slug' => $slug,
            'label' => $this->input('label'),
            'label_singular' => $this->input('label_singular'),
            'rewrite' => $this->input('rewrite') ?: $slug,
            'public' => $this->input('public') === '1' ? 1 : 0,
            'has_archive' => $this->input('has_archive') === '1' ? 1 : 0,
            'hierarchical' => $this->input('hierarchical') === '1' ? 1 : 0,
            'supports' => json_encode(array_values($supports)),
            'taxonomies' => json_encode(array_values($taxonomies)),
            'icon' => $this->input('icon') ?: 'file-earmark',
            'is_builtin' => 0,
            'menu_position' => (int) $this->input('menu_position', '20'),
        ];
    }
}
