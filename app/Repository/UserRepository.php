<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Row;
use flight\database\SimplePdo;

class UserRepository
{
    public function __construct(private SimplePdo $db)
    {
    }

    public function find(int $id): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM users WHERE id = ?', [$id]));
    }

    public function findByLogin(string $login): ?array
    {
        return Row::one($this->db->fetchRow(
            'SELECT * FROM users WHERE username = ? OR email = ?',
            [$login, $login]
        ));
    }

    public function findByEmail(string $email): ?array
    {
        return Row::one($this->db->fetchRow('SELECT * FROM users WHERE email = ?', [$email]));
    }

    /** @return list<array<string, mixed>> */
    public function all(string $search = ''): array
    {
        if ($search === '') {
            return Row::all($this->db->fetchAll('SELECT * FROM users ORDER BY id'));
        }
        $like = '%' . $search . '%';
        return Row::all($this->db->fetchAll(
            'SELECT * FROM users WHERE username LIKE ? OR email LIKE ? OR display_name LIKE ? ORDER BY id',
            [$like, $like, $like]
        ));
    }

    public function countByRole(string $role): int
    {
        return (int) $this->db->fetchField('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return (int) $this->db->insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'display_name' => $data['display_name'],
            'role' => $data['role'] ?? 'subscriber',
            'bio' => $data['bio'] ?? '',
            'status' => $data['status'] ?? 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function bumpSession(int $id): void
    {
        $this->db->runQuery('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
    }
}
