<?php

declare(strict_types=1);

namespace App\Core;

use flight\database\SimplePdo;
use PDOException;

class Settings
{
    /** @var array<string, mixed> */
    private array $cache = [];

    private bool $loaded = false;

    public function __construct(private SimplePdo $db)
    {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        $this->load();
        return array_key_exists($name, $this->cache) ? $this->cache[$name] : $default;
    }

    public function set(string $name, mixed $value, bool $autoload = true): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $existing = $this->db->fetchField('SELECT name FROM options WHERE name = ?', [$name]);
        if ($existing) {
            $this->db->update('options', ['value' => $json, 'autoload' => $autoload ? 1 : 0], 'name = ?', [$name]);
        } else {
            $this->db->insert('options', ['name' => $name, 'value' => $json, 'autoload' => $autoload ? 1 : 0]);
        }
        $this->cache[$name] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->load();
        return $this->cache;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        try {
            $rows = $this->db->fetchAll('SELECT name, value FROM options WHERE autoload = 1');
        } catch (PDOException) {
            return;
        }
        foreach ($rows as $row) {
            $data = is_array($row) ? $row : $row->getData();
            $decoded = json_decode((string) $data['value'], true);
            $this->cache[$data['name']] = $decoded === null && $data['value'] !== 'null' ? $data['value'] : $decoded;
        }
    }
}
