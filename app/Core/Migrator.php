<?php

declare(strict_types=1);

namespace App\Core;

use flight\database\SimplePdo;
use PDOException;

class Migrator
{
    private string $directory;

    public function __construct(private SimplePdo $db, ?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    public function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTable();
        $applied = $this->db->fetchColumn('SELECT name FROM migrations');
        $pending = [];
        foreach ($this->files() as $name => $path) {
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $applied = [];
        foreach ($this->pending() as $name) {
            $sql = (string) file_get_contents($this->files()[$name]);
            $this->db->beginTransaction();
            try {
                foreach ($this->statements($sql) as $statement) {
                    $this->db->exec($statement);
                }
                $this->db->insert('migrations', [
                    'name' => $name,
                    'applied_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $this->db->commit();
                $applied[] = $name;
            } catch (PDOException $e) {
                $this->db->rollBack();
                throw new PDOException($name . ': ' . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }
        return $applied;
    }

    public function isInstalled(): bool
    {
        try {
            $value = $this->db->fetchField("SELECT value FROM options WHERE name = 'installed_at'");
        } catch (PDOException) {
            return false;
        }
        return $value !== null && $value !== false && $value !== '';
    }

    /** @return array<string, string> */
    private function files(): array
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);
        $map = [];
        foreach ($files as $file) {
            $map[basename($file)] = $file;
        }
        return $map;
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote && ($sql[$i - 1] ?? '') !== '\\') {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $statements;
    }
}
