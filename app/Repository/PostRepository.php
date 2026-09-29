<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class PostRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM posts WHERE id = ?', [$id]));
    }

    /** @param array<string, mixed> $filters */
    public function list(string $type, array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->where($type, $filters);
        $total = (int) $this->db->fetchField("SELECT COUNT(*) FROM posts p WHERE {$where}", $params);
        $order = in_array($filters['orderby'] ?? '', ['title', 'published_at', 'updated_at'], true)
            ? $filters['orderby'] : 'updated_at';
        $dir = ($filters['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Row::all($this->db->fetchAll(
            "SELECT p.*, u.display_name AS author_name, m.path AS featured_path FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             LEFT JOIN media m ON m.id = p.featured_media_id
             WHERE {$where} ORDER BY p.{$order} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        ));
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string, int> */
    public function statusCounts(string $type): array
    {
        $rows = Row::all($this->db->fetchAll(
            'SELECT status, COUNT(*) AS n FROM posts WHERE type = ? GROUP BY status',
            [$type]
        ));
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return (int) $this->db->insert('posts', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->db->update('posts', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('posts', 'id = ?', [$id]);
        $this->db->runQuery('DELETE FROM posts_fts WHERE rowid = ?', [$id]);
    }

    public function slugTaken(string $type, string $slug, int $parentId, ?int $ignore): bool
    {
        $row = $this->db->fetchField(
            'SELECT id FROM posts WHERE type = ? AND slug = ? AND parent_id = ? AND id != ?',
            [$type, $slug, $parentId, $ignore ?? 0]
        );
        return $row !== null && $row !== false;
    }

    public function indexSearch(int $id, string $title, string $body): void
    {
        $this->db->runQuery('DELETE FROM posts_fts WHERE rowid = ?', [$id]);
        $this->db->runQuery('INSERT INTO posts_fts (rowid, title, body) VALUES (?, ?, ?)', [$id, $title, $body]);
    }

    /** @param array<string, mixed> $filters */
    private function where(string $type, array $filters): array
    {
        $where = ['p.type = ?'];
        $params = [$type];
        $status = $filters['status'] ?? '';
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if (($filters['author'] ?? 0) > 0) {
            $where[] = 'p.author_id = ?';
            $params[] = (int) $filters['author'];
        }
        if (($filters['month'] ?? '') !== '') {
            $where[] = "strftime('%Y-%m', p.published_at) = ?";
            $params[] = $filters['month'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.title LIKE ? OR p.content LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        return [implode(' AND ', $where), $params];
    }
}
