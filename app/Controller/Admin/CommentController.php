<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CommentRepository;

class CommentController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('moderate_comments');
        $status = (string) ($this->app->request()->query->status ?? 'pending');
        if (!in_array($status, ['pending', 'approved', 'spam', 'trash'], true)) {
            $status = 'pending';
        }
        $page = max(1, (int) ($this->app->request()->query->page ?? 1));
        $repo = $this->app->make(CommentRepository::class);
        $result = $repo->list($status, $page, 20);
        $this->admin('admin/comments/index', [
            'title' => 'Comments',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'per_page' => 20,
            'status' => $status,
            'counts' => $repo->statusCounts(),
        ]);
    }

    public function bulk(): void
    {
        $this->forbid('moderate_comments');
        $action = $this->input('action');
        $ids = $this->app->request()->data->ids ?? [];
        $repo = $this->app->make(CommentRepository::class);
        foreach ((array) $ids as $id) {
            match ($action) {
                'approve' => $repo->updateStatus((int) $id, 'approved'),
                'spam' => $repo->updateStatus((int) $id, 'spam'),
                'trash' => $repo->updateStatus((int) $id, 'trash'),
                'delete' => $repo->delete((int) $id),
                default => null,
            };
        }
        $this->flash->set('success', 'Updated.');
        $this->redirect('/admin/comments?status=' . $this->input('status', 'pending'));
    }
}
