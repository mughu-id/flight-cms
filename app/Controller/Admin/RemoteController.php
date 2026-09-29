<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Support\Row;

class RemoteController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('edit_posts');
        $userId = (int) $this->app->get('capabilities')->userId();
        $db = $this->app->db();
        $tokens = Row::all($db->fetchAll(
            'SELECT id, name, last_used_at, created_at FROM api_tokens WHERE user_id = ? ORDER BY id DESC',
            [$userId]
        ));
        $recent = Row::all($db->fetchAll(
            "SELECT p.id, p.title, p.slug, p.status, p.published_at, m.meta_value AS token_name
             FROM posts p
             JOIN post_meta m ON m.post_id = p.id AND m.meta_key = '_remote'
             WHERE p.author_id = ?
             ORDER BY p.id DESC LIMIT 8",
            [$userId]
        ));
        $stats = [
            'tokens' => count($tokens),
            'posts' => (int) $db->fetchField(
                "SELECT COUNT(*) FROM posts p JOIN post_meta m ON m.post_id = p.id AND m.meta_key = '_remote' WHERE p.author_id = ?",
                [$userId]
            ),
            'published' => (int) $db->fetchField(
                "SELECT COUNT(*) FROM posts p JOIN post_meta m ON m.post_id = p.id AND m.meta_key = '_remote' WHERE p.author_id = ? AND p.status = 'publish'",
                [$userId]
            ),
            'words' => (int) $db->fetchField(
                "SELECT COALESCE(SUM(meta_value), 0) FROM post_meta WHERE meta_key = '_remote_words' AND post_id IN (SELECT id FROM posts WHERE author_id = ?)",
                [$userId]
            ),
        ];
        $this->admin('admin/remote/index', [
            'title' => 'Remote post',
            'tokens' => $tokens,
            'recent' => $recent,
            'stats' => $stats,
            'new_token' => $this->pullToken(),
            'endpoint' => rtrim((string) $this->app->get('flight.base_url'), '/') . '/api/v1/posts',
        ]);
    }

    public function createToken(): void
    {
        $this->forbid('edit_posts');
        $name = $this->input('name');
        if ($name === '') {
            $this->flash->set('error', 'Give the token a name.');
            $this->redirect('/admin/remote');
        }
        $token = bin2hex(random_bytes(32));
        $this->app->db()->insert('api_tokens', [
            'user_id' => (int) $this->app->get('capabilities')->userId(),
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'expires_at' => null,
            'last_used_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->flash->set('success', 'Token created. Copy it below, it will not be shown again.');
        $this->app->session()->set('new_remote_token', $token);
        $this->redirect('/admin/remote');
    }

    public function deleteToken(string $id): void
    {
        $this->forbid('edit_posts');
        $this->app->db()->delete('api_tokens', 'id = ? AND user_id = ?', [
            (int) $id,
            (int) $this->app->get('capabilities')->userId(),
        ]);
        $this->flash->set('success', 'Token revoked.');
        $this->redirect('/admin/remote');
    }

    private function pullToken(): ?string
    {
        $token = $this->app->session()->get('new_remote_token');
        $this->app->session()->delete('new_remote_token');
        return is_string($token) && $token !== '' ? $token : null;
    }
}
