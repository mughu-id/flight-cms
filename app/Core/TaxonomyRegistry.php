<?php

declare(strict_types=1);

namespace App\Core;

class TaxonomyRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $extra = [];

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $builtin = [
            'category' => ['slug' => 'category', 'label' => 'Categories', 'label_singular' => 'Category', 'hierarchical' => true],
            'tag' => ['slug' => 'tag', 'label' => 'Tags', 'label_singular' => 'Tag', 'hierarchical' => false],
        ];
        return $builtin + $this->extra;
    }

    public function get(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** @param array<string, mixed> $taxonomy */
    public function register(array $taxonomy): void
    {
        $this->extra[$taxonomy['slug']] = $taxonomy;
    }
}
