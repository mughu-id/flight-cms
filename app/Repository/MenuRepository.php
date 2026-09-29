<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class MenuRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return Row::all($this->db->fetchAll('SELECT * FROM menus ORDER BY name'));
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM menus WHERE id = ?', [$id]));
    }

    public function forLocation(string $location): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM menus WHERE location = ?', [$location]));
    }

    /** @return list<array<string, mixed>> */
    public function items(int $menuId): array
    {
        return Row::all($this->db->fetchAll(
            'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order, id',
            [$menuId]
        ));
    }

    /** @return list<array<string, mixed>> */
    public function tree(int $menuId): array
    {
        return $this->nest($this->items($menuId), 0);
    }

    public function create(string $name): int
    {
        return (int) $this->db->insert('menus', ['name' => $name, 'location' => '']);
    }

    public function assign(int $id, string $location): void
    {
        if ($location !== '') {
            $this->db->runQuery('UPDATE menus SET location = \'\' WHERE location = ?', [$location]);
        }
        $this->db->update('menus', ['location' => $location], 'id = ?', [$id]);
    }

    public function rename(int $id, string $name): void
    {
        $this->db->update('menus', ['name' => $name], 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('menus', 'id = ?', [$id]);
    }

    /** @param list<array<string, mixed>> $items */
    public function replaceItems(int $menuId, array $items): void
    {
        $this->db->delete('menu_items', 'menu_id = ?', [$menuId]);
        $this->insertItems($menuId, $items, 0);
    }

    /** @param list<array<string, mixed>> $items */
    private function insertItems(int $menuId, array $items, int $parent): void
    {
        foreach (array_values($items) as $order => $item) {
            $id = (int) $this->db->insert('menu_items', [
                'menu_id' => $menuId,
                'parent_id' => $parent,
                'title' => (string) ($item['title'] ?? ''),
                'type' => (string) ($item['type'] ?? 'custom'),
                'object_id' => (int) ($item['object_id'] ?? 0),
                'url' => (string) ($item['url'] ?? ''),
                'target' => (string) ($item['target'] ?? ''),
                'css_class' => (string) ($item['css_class'] ?? ''),
                'sort_order' => $order,
            ]);
            $this->insertItems($menuId, (array) ($item['children'] ?? []), $id);
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function nest(array $items, int $parent): array
    {
        $branch = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parent) {
                $item['children'] = $this->nest($items, (int) $item['id']);
                $branch[] = $item;
            }
        }
        return $branch;
    }
}
