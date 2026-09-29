<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class RevisionRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    /** @param array<string, mixed> $post */
    public function save(array $post, int $authorId, bool $autosave, int $keep): void
    {
        if ($autosave) {
            $existing = $this->db->fetchField(
                'SELECT id FROM post_revisions WHERE post_id = ? AND is_autosave = 1',
                [(int) $post['id']]
            );
            $data = $this->payload($post, $authorId, true);
            if ($existing) {
                $this->db->update('post_revisions', $data, 'id = ?', [(int) $existing]);
            } else {
                $this->db->insert('post_revisions', $data + ['post_id' => (int) $post['id']]);
            }
            return;
        }
        $this->db->insert('post_revisions', $this->payload($post, $authorId, false) + ['post_id' => (int) $post['id']]);
        $ids = $this->db->fetchColumn(
            'SELECT id FROM post_revisions WHERE post_id = ? AND is_autosave = 0 ORDER BY id DESC',
            [(int) $post['id']]
        );
        foreach (array_slice($ids, $keep) as $old) {
            $this->db->delete('post_revisions', 'id = ?', [(int) $old]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function forPost(int $postId): array
    {
        return Row::all($this->db->fetchAll(
            'SELECT r.*, u.display_name AS author_name FROM post_revisions r
             LEFT JOIN users u ON u.id = r.author_id
             WHERE r.post_id = ? ORDER BY r.created_at DESC',
            [$postId]
        ));
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM post_revisions WHERE id = ?', [$id]));
    }

    /** @param array<string, mixed> $post */
    private function payload(array $post, int $authorId, bool $autosave): array
    {
        return [
            'author_id' => $authorId,
            'title' => (string) $post['title'],
            'content' => (string) $post['content'],
            'excerpt' => (string) $post['excerpt'],
            'meta' => '{}',
            'is_autosave' => $autosave ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
}
