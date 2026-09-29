<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class CommentRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM comments WHERE id = ?', [$id]));
    }

    /** @return list<array<string, mixed>> */
    public function forPost(int $postId, string $status = 'approved'): array
    {
        return Row::all($this->db->fetchAll(
            'SELECT * FROM comments WHERE post_id = ? AND status = ? ORDER BY created_at',
            [$postId, $status]
        ));
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function list(string $status, int $page, int $perPage): array
    {
        $where = $status !== '' ? 'c.status = ?' : '1=1';
        $params = $status !== '' ? [$status] : [];
        $total = (int) $this->db->fetchField("SELECT COUNT(*) FROM comments c WHERE {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Row::all($this->db->fetchAll(
            "SELECT c.*, p.title AS post_title FROM comments c
             LEFT JOIN posts p ON p.id = c.post_id
             WHERE {$where} ORDER BY c.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        ));
        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $id = (int) $this->db->insert('comments', $data + ['created_at' => gmdate('Y-m-d H:i:s')]);
        $this->recount((int) $data['post_id']);
        return $id;
    }

    public function updateStatus(int $id, string $status): void
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return;
        }
        $this->db->update('comments', ['status' => $status], 'id = ?', [$id]);
        $this->recount((int) $comment['post_id']);
    }

    public function delete(int $id): void
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return;
        }
        $this->db->delete('comments', 'id = ?', [$id]);
        $this->recount((int) $comment['post_id']);
    }

    public function pendingCount(): int
    {
        return (int) $this->db->fetchField("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'spam' => 0, 'trash' => 0];
        foreach (Row::all($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM comments GROUP BY status')) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    public function hasApprovedFrom(string $email): bool
    {
        return (bool) $this->db->fetchField(
            "SELECT 1 FROM comments WHERE author_email = ? AND status = 'approved' LIMIT 1",
            [$email]
        );
    }

    private function recount(int $postId): void
    {
        $count = (int) $this->db->fetchField(
            "SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved'",
            [$postId]
        );
        $this->db->update('posts', ['comment_count' => $count], 'id = ?', [$postId]);
    }
}
