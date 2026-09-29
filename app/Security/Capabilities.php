<?php

declare(strict_types=1);

namespace App\Security;

use flight\Permission;
use flight\Session;

class Capabilities
{
    private Permission $permission;

    /** @param array<string, mixed> $roles */
    public function __construct(private Session $session, private array $roles, private ?\flight\Engine $app = null)
    {
        $this->permission = new Permission('guest', $app);
        $map = $roles;
        unset($map['labels']);
        $this->permission->defineRule('cap', static function (string $role) use ($map): array {
            return $map[$role] ?? [];
        });
    }

    public function can(string $capability, ?string $role = null): bool
    {
        $role ??= $this->role();
        if ($role === null) {
            return false;
        }
        $this->permission->setCurrentRole($role);
        return $this->permission->can('cap.' . $capability);
    }

    public function role(): ?string
    {
        $role = $this->session()->get('role');
        return is_string($role) && $role !== '' ? $role : null;
    }

    public function userId(): ?int
    {
        $id = $this->session()->get('user_id');
        return is_numeric($id) ? (int) $id : null;
    }

    private function session(): Session
    {
        if ($this->app instanceof \flight\Engine) {
            return $this->app->session();
        }
        return $this->session;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return $this->roles['labels'];
    }
}
