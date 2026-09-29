<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Row;
use flight\database\SimplePdo;

class PostTypeRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $types = null;

    public function __construct(private SimplePdo $db)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->types === null) {
            $this->types = [];
            foreach (Row::all($this->db->fetchAll('SELECT * FROM post_types ORDER BY menu_position')) as $row) {
                $row['supports'] = json_decode((string) $row['supports'], true) ?: [];
                $row['taxonomies'] = json_decode((string) $row['taxonomies'], true) ?: [];
                $this->types[$row['slug']] = $row;
            }
        }
        return $this->types;
    }

    public function get(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    public function supports(string $slug, string $feature): bool
    {
        return in_array($feature, $this->get($slug)['supports'] ?? [], true);
    }
}
