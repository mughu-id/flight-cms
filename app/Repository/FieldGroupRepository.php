<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class FieldGroupRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map($this->decode(...), Row::all($this->db->fetchAll('SELECT * FROM field_groups ORDER BY sort_order, id')));
    }

    public function find(int $id): ?array
    {
        $row = Row::one($this->db->fetchRow('SELECT * FROM field_groups WHERE id = ?', [$id]));
        return $row ? $this->decode($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function forType(string $type): array
    {
        return array_values(array_filter($this->all(), static function (array $group) use ($type): bool {
            return $group['active'] && in_array($type, $group['post_types'], true);
        }));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->db->insert('field_groups', $this->encode($data));
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->update('field_groups', $this->encode($data), 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('field_groups', 'id = ?', [$id]);
    }

    public function getMeta(int $postId, string $key, mixed $default = null): mixed
    {
        $value = $this->db->fetchField('SELECT meta_value FROM post_meta WHERE post_id = ? AND meta_key = ?', [$postId, $key]);
        if ($value === null || $value === false) {
            return $default;
        }
        $decoded = json_decode((string) $value, true);
        return $decoded === null && $value !== 'null' ? $value : $decoded;
    }

    public function setMeta(int $postId, string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $existing = $this->db->fetchField('SELECT id FROM post_meta WHERE post_id = ? AND meta_key = ?', [$postId, $key]);
        if ($existing) {
            $this->db->update('post_meta', ['meta_value' => $json], 'id = ?', [(int) $existing]);
        } else {
            $this->db->insert('post_meta', ['post_id' => $postId, 'meta_key' => $key, 'meta_value' => $json]);
        }
    }

    /** @return array<string, mixed> */
    public function allMeta(int $postId): array
    {
        $out = [];
        foreach (Row::all($this->db->fetchAll('SELECT meta_key, meta_value FROM post_meta WHERE post_id = ?', [$postId])) as $row) {
            $decoded = json_decode((string) $row['meta_value'], true);
            $out[$row['meta_key']] = $decoded === null && $row['meta_value'] !== 'null' ? $row['meta_value'] : $decoded;
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): array
    {
        $row['post_types'] = json_decode((string) $row['post_types'], true) ?: [];
        $row['fields'] = json_decode((string) $row['fields'], true) ?: [];
        $row['active'] = (bool) $row['active'];
        return $row;
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'post_types' => json_encode($data['post_types'] ?? [], JSON_UNESCAPED_SLASHES),
            'fields' => json_encode($data['fields'] ?? [], JSON_UNESCAPED_SLASHES),
            'position' => (string) ($data['position'] ?? 'normal'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
        ];
    }
}
