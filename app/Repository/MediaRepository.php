<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class MediaRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function find(int $id): ?array
    {
        $row = Row::one($this->db->fetchRow('SELECT * FROM media WHERE id = ?', [$id]));
        return $row === null ? null : $this->decode($row);
    }

    /** @param array{q?: string, type?: string, month?: string} $filters */
    public function list(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(title LIKE ? OR alt LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'mime_type LIKE ?';
            $params[] = $filters['type'] . '/%';
        }
        if (($filters['month'] ?? '') !== '') {
            $where[] = "strftime('%Y-%m', created_at) = ?";
            $params[] = $filters['month'];
        }
        $sql = implode(' AND ', $where);
        $total = (int) $this->db->fetchField("SELECT COUNT(*) FROM media WHERE {$sql}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Row::all($this->db->fetchAll(
            "SELECT * FROM media WHERE {$sql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        ));
        return ['rows' => array_map($this->decode(...), $rows), 'total' => $total];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data['sizes'] = json_encode($data['sizes'] ?? [], JSON_UNESCAPED_SLASHES);
        $data['created_at'] = gmdate('Y-m-d H:i:s');
        return (int) $this->db->insert('media', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->update('media', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('media', 'id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): array
    {
        $row['sizes'] = json_decode((string) $row['sizes'], true) ?: [];
        return $row;
    }
}
