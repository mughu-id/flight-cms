<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\TaxonomyRegistry;
use App\Repository\TermRepository;
use App\Service\Slugger;

class TermController extends DashboardController
{
    public function index(string $taxonomy): void
    {
        $def = $this->taxonomy($taxonomy);
        $this->forbid('manage_categories');
        $this->admin('admin/terms/index', [
            'title' => $def['label'],
            'taxonomy' => $def,
            'terms' => $this->app->make(TermRepository::class)->forTaxonomy($taxonomy),
        ]);
    }

    public function store(string $taxonomy): void
    {
        $this->taxonomy($taxonomy);
        $this->forbid('manage_categories');
        $name = $this->input('name');
        if ($name === '') {
            $this->flash->set('error', 'Name is required.');
            $this->redirect('/admin/terms/' . $taxonomy);
        }
        $slug = $this->uniqueSlug($taxonomy, $this->input('slug') !== '' ? $this->input('slug') : $name);
        $this->app->make(TermRepository::class)->create([
            'taxonomy' => $taxonomy,
            'name' => $name,
            'slug' => $slug,
            'description' => $this->input('description'),
            'parent_id' => (int) $this->input('parent_id', '0'),
        ]);
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/terms/' . $taxonomy);
    }

    public function update(string $taxonomy, string $id): void
    {
        $this->taxonomy($taxonomy);
        $this->forbid('manage_categories');
        $term = $this->requireTerm($taxonomy, (int) $id);
        $slug = $this->uniqueSlug($taxonomy, $this->input('slug') !== '' ? $this->input('slug') : $this->input('name'), (int) $term['id']);
        $this->app->make(TermRepository::class)->update((int) $term['id'], [
            'name' => $this->input('name'),
            'slug' => $slug,
            'description' => $this->input('description'),
            'parent_id' => (int) $this->input('parent_id', '0'),
        ]);
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/terms/' . $taxonomy);
    }

    public function delete(string $taxonomy, string $id): void
    {
        $this->taxonomy($taxonomy);
        $this->forbid('manage_categories');
        $this->requireTerm($taxonomy, (int) $id);
        $this->app->make(TermRepository::class)->delete((int) $id);
        $this->flash->set('success', 'Deleted.');
        $this->redirect('/admin/terms/' . $taxonomy);
    }

    private function uniqueSlug(string $taxonomy, string $source, ?int $ignore = null): string
    {
        $terms = $this->app->make(TermRepository::class);
        $slug = $this->app->make(Slugger::class)->slug($source);
        $base = $slug;
        $n = 2;
        while (true) {
            $found = $terms->findBySlug($taxonomy, $slug);
            if ($found === null || (int) $found['id'] === $ignore) {
                return $slug;
            }
            $slug = $base . '-' . $n;
            $n++;
        }
    }

    /** @return array<string, mixed> */
    private function taxonomy(string $slug): array
    {
        $def = $this->app->make(TaxonomyRegistry::class)->get($slug);
        if ($def === null) {
            $this->app->halt(404, 'Unknown taxonomy');
        }
        return $def;
    }

    /** @return array<string, mixed> */
    private function requireTerm(string $taxonomy, int $id): array
    {
        $term = $this->app->make(TermRepository::class)->find($id);
        if ($term === null || $term['taxonomy'] !== $taxonomy) {
            $this->app->halt(404, 'Not found');
        }
        return $term;
    }
}
