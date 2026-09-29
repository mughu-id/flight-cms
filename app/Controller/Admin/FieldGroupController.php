<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\FieldGroupRepository;

class FieldGroupController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('manage_post_types');
        $this->admin('admin/field-groups/index', [
            'title' => 'Field groups',
            'groups' => $this->app->make(FieldGroupRepository::class)->all(),
        ]);
    }

    public function create(): void
    {
        $this->forbid('manage_post_types');
        $this->admin('admin/field-groups/form', ['title' => 'Add field group', 'group' => null]);
    }

    public function store(): void
    {
        $this->forbid('manage_post_types');
        $id = $this->app->make(FieldGroupRepository::class)->create($this->payload());
        $this->flash->set('success', 'Created.');
        $this->redirect('/admin/field-groups/' . $id);
    }

    public function edit(string $id): void
    {
        $this->forbid('manage_post_types');
        $group = $this->app->make(FieldGroupRepository::class)->find((int) $id);
        if ($group === null) {
            $this->app->halt(404, 'Not found');
        }
        $this->admin('admin/field-groups/form', ['title' => 'Edit field group', 'group' => $group]);
    }

    public function update(string $id): void
    {
        $this->forbid('manage_post_types');
        $this->app->make(FieldGroupRepository::class)->update((int) $id, $this->payload());
        $this->flash->set('success', 'Saved.');
        $this->redirect('/admin/field-groups/' . $id);
    }

    public function delete(string $id): void
    {
        $this->forbid('manage_post_types');
        $this->app->make(FieldGroupRepository::class)->delete((int) $id);
        $this->flash->set('success', 'Deleted.');
        $this->redirect('/admin/field-groups');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $fields = json_decode((string) ($this->app->request()->data->fields_json ?? '[]'), true) ?: [];
        $types = (array) ($this->app->request()->data->post_types ?? []);
        return [
            'title' => $this->input('title'),
            'post_types' => array_values($types),
            'fields' => $fields,
            'position' => $this->input('position', 'normal'),
            'sort_order' => (int) $this->input('sort_order', '0'),
            'active' => $this->input('active') === '1',
        ];
    }
}
