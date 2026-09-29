<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class TermRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM terms WHERE id = ?', [$id]));
    }

    public function findBySlug(string $taxonomy, string $slug): ?array
    {
        return Row::one($this->db->fetchRow(
            'SELECT * FROM terms WHERE taxonomy = ? AND slug = ?',
            [$taxonomy, $slug]
        ));
    }

    /** @return list<array<string, mixed>> */
    public function forTaxonomy(string $taxonomy): array
    {
        return Row::all($this->db->fetchAll(
            'SELECT * FROM terms WHERE taxonomy = ? ORDER BY name',
            [$taxonomy]
        ));
    }

    /** @return list<array<string, mixed>> */
    public function forPost(int $postId, string $taxonomy): array
    {
        return Row::all($this->db->fetchAll(
            'SELECT t.* FROM terms t
             JOIN term_relationships r ON r.term_id = t.id
             WHERE r.post_id = ? AND t.taxonomy = ? ORDER BY t.name',
            [$postId, $taxonomy]
        ));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->db->insert('terms', $data + [
            'description' => $data['description'] ?? '',
            'parent_id' => $data['parent_id'] ?? 0,
            'count' => 0,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->update('terms', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('terms', 'id = ?', [$id]);
    }

    /** @param list<int> $termIds */
    public function sync(int $postId, string $taxonomy, array $termIds): void
    {
        $current = array_column($this->forPost($postId, $taxonomy), 'id');
        $this->db->runQuery(
            'DELETE FROM term_relationships WHERE post_id = ? AND term_id IN (SELECT id FROM terms WHERE taxonomy = ?)',
            [$postId, $taxonomy]
        );
        foreach (array_unique($termIds) as $termId) {
            $this->db->insert('term_relationships', ['post_id' => $postId, 'term_id' => (int) $termId]);
        }
        foreach (array_unique(array_merge($current, $termIds)) as $termId) {
            $this->recount((int) $termId);
        }
    }

    public function recount(int $termId): void
    {
        $count = (int) $this->db->fetchField(
            "SELECT COUNT(*) FROM term_relationships r JOIN posts p ON p.id = r.post_id
             WHERE r.term_id = ? AND p.status = 'publish'",
            [$termId]
        );
        $this->db->update('terms', ['count' => $count], 'id = ?', [$termId]);
    }
}
