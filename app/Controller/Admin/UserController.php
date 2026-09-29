<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PostRepository;
use App\Repository\UserRepository;

class UserController extends DashboardController
{
    public function index(): void
    {
        $this->forbid('list_users');
        $users = $this->app->make(UserRepository::class)->all($this->input('q'));
        $this->admin('admin/users/index', ['title' => 'Users', 'users' => $users, 'q' => $this->input('q')]);
    }

    public function create(): void
    {
        $this->forbid('create_users');
        $this->admin('admin/users/form', ['title' => 'Add user', 'user' => null, 'roles' => $this->roles()]);
    }

    public function store(): void
    {
        $this->forbid('create_users');
        $data = $this->validated();
        if (is_string($data)) {
            $this->flash->set('error', $data);
            $this->redirect('/admin/users/new');
        }
        $this->app->make(UserRepository::class)->create($data + [
            'password_hash' => password_hash((string) $this->app->request()->data->password, PASSWORD_DEFAULT),
        ]);
        $this->flash->set('success', 'User created.');
        $this->redirect('/admin/users');
    }

    public function edit(string $id): void
    {
        $this->forbid('edit_users');
        $user = $this->requireUser((int) $id);
        $this->admin('admin/users/form', ['title' => 'Edit user', 'user' => $user, 'roles' => $this->roles()]);
    }

    public function update(string $id): void
    {
        $this->forbid('edit_users');
        $user = $this->requireUser((int) $id);
        $data = $this->validated((int) $user['id']);
        if (is_string($data)) {
            $this->flash->set('error', $data);
            $this->redirect('/admin/users/' . $id);
        }
        $password = (string) ($this->app->request()->data->password ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                $this->flash->set('error', 'Password must be at least 8 characters.');
                $this->redirect('/admin/users/' . $id);
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $this->app->make(UserRepository::class)->bumpSession((int) $user['id']);
        }
        if (!$this->guardLastAdmin($user, (string) $data['role'], false)) {
            $this->redirect('/admin/users/' . $id);
        }
        $this->app->make(UserRepository::class)->update((int) $user['id'], $data);
        $this->flash->set('success', 'User updated.');
        $this->redirect('/admin/users');
    }

    public function delete(string $id): void
    {
        $this->forbid('delete_users');
        $user = $this->requireUser((int) $id);
        $reassign = (int) $this->input('reassign');
        if ((int) $user['id'] === $this->app->get('capabilities')->userId()) {
            $this->flash->set('error', 'You cannot delete your own account.');
            $this->redirect('/admin/users');
        }
        if (!$this->guardLastAdmin($user, '', true)) {
            $this->redirect('/admin/users');
        }
        if ($reassign < 1 || $reassign === (int) $user['id']) {
            $this->flash->set('error', 'Choose another user to receive this content.');
            $this->redirect('/admin/users/' . $id);
        }
        $this->app->db()->runQuery('UPDATE posts SET author_id = ? WHERE author_id = ?', [$reassign, (int) $user['id']]);
        $this->app->make(UserRepository::class)->delete((int) $user['id']);
        $this->flash->set('success', 'User deleted.');
        $this->redirect('/admin/users');
    }

    public function profile(): void
    {
        $user = $this->requireUser((int) $this->app->get('capabilities')->userId());
        $this->admin('admin/users/profile', ['title' => 'Profile', 'user' => $user]);
    }

    public function updateProfile(): void
    {
        $user = $this->requireUser((int) $this->app->get('capabilities')->userId());
        $display = $this->input('display_name');
        $email = $this->input('email');
        $bio = $this->input('bio');
        if ($display === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash->set('error', 'Display name and a valid email are required.');
            $this->redirect('/admin/profile');
        }
        $data = ['display_name' => $display, 'email' => $email, 'bio' => $bio];
        $password = (string) ($this->app->request()->data->password ?? '');
        $current = (string) ($this->app->request()->data->current_password ?? '');
        if ($password !== '') {
            if (!password_verify($current, (string) $user['password_hash']) || strlen($password) < 8) {
                $this->flash->set('error', 'Current password is wrong, or the new one is too short.');
                $this->redirect('/admin/profile');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $this->app->make(UserRepository::class)->bumpSession((int) $user['id']);
        }
        $this->app->make(UserRepository::class)->update((int) $user['id'], $data);
        $this->flash->set('success', 'Profile saved.');
        $this->redirect('/admin/profile');
    }

    /** @return array<string, string>|string */
    private function validated(?int $ignoreId = null): array|string
    {
        $username = $this->input('username');
        $email = $this->input('email');
        $display = $this->input('display_name');
        $role = $this->input('role');
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            return 'Username must be 3-30 characters: lowercase letters, numbers, underscore.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $display === '') {
            return 'Display name and a valid email are required.';
        }
        if (!isset($this->roles()[$role])) {
            return 'Unknown role.';
        }
        if ($ignoreId === null && strlen((string) ($this->app->request()->data->password ?? '')) < 8) {
            return 'Password must be at least 8 characters.';
        }
        $users = $this->app->make(UserRepository::class);
        $taken = $users->findByLogin($username);
        if ($taken !== null && (int) $taken['id'] !== $ignoreId) {
            return 'That username is taken.';
        }
        $taken = $users->findByEmail($email);
        if ($taken !== null && (int) $taken['id'] !== $ignoreId) {
            return 'That email is taken.';
        }
        return [
            'username' => $username,
            'email' => $email,
            'display_name' => $display,
            'role' => $role,
            'bio' => $this->input('bio'),
            'status' => $this->input('status') === 'disabled' ? 'disabled' : 'active',
        ];
    }

    /** @param array<string, mixed> $user */
    private function guardLastAdmin(array $user, string $newRole, bool $deleting): bool
    {
        if ($user['role'] !== 'administrator') {
            return true;
        }
        if (!$deleting && $newRole === 'administrator') {
            return true;
        }
        if ($this->app->make(UserRepository::class)->countByRole('administrator') <= 1) {
            $this->flash->set('error', 'The last administrator cannot be removed.');
            return false;
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function requireUser(int $id): array
    {
        $user = $this->app->make(UserRepository::class)->find($id);
        if ($user === null) {
            $this->app->halt(404, 'User not found');
        }
        return $user;
    }

    /** @return array<string, string> */
    private function roles(): array
    {
        return $this->app->get('capabilities')->labels();
    }
}
