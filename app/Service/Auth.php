<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use flight\database\SimplePdo;
use flight\Engine;
use flight\Session;

class Auth
{
    public function __construct(
        private Engine $app,
        private SimplePdo $db,
        private Session $session,
        private UserRepository $users,
        private RateLimiter $limiter,
    ) {
    }

    public function attempt(string $login, string $password, bool $remember): ?string
    {
        $ip = (string) ($this->app->request()->ip ?? '0.0.0.0');
        if (!$this->limiter->allow('login:' . $ip, 10, 900)) {
            return 'Too many attempts. Try again later.';
        }
        $user = $this->users->findByLogin($login);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return 'Unknown username or password.';
        }
        if ($user['status'] !== 'active') {
            return 'This account is disabled.';
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }
        $this->login($user, $remember);
        return null;
    }

    /** @param array<string, mixed> $user */
    public function login(array $user, bool $remember = false): void
    {
        $this->session->regenerate(true);
        $this->session->set('user_id', (int) $user['id']);
        $this->session->set('role', (string) $user['role']);
        $this->session->set('session_version', (int) $user['session_version']);
        $this->users->update((int) $user['id'], ['last_login_at' => gmdate('Y-m-d H:i:s')]);
        if ($remember) {
            $this->remember((int) $user['id']);
        }
    }

    public function logout(): void
    {
        $selector = $_COOKIE['cms_remember'] ?? '';
        if (is_string($selector) && str_contains($selector, ':')) {
            [$sel] = explode(':', $selector, 2);
            $this->db->delete('remember_tokens', 'selector = ?', [$sel]);
            setcookie('cms_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
        $this->session->clear();
        $this->session->regenerate(true);
    }

    public function remember(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $token = bin2hex(random_bytes(32));
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'selector' => $selector,
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30),
        ]);
        setcookie('cms_remember', $selector . ':' . $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => str_starts_with((string) $this->app->get('config')->get('app.url'), 'https://'),
        ]);
    }

    public function fromRememberCookie(): bool
    {
        if ($this->session->get('user_id')) {
            return true;
        }
        $cookie = $_COOKIE['cms_remember'] ?? '';
        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return false;
        }
        [$selector, $token] = explode(':', $cookie, 2);
        $row = \App\Support\Row::one($this->db->fetchRow(
            'SELECT * FROM remember_tokens WHERE selector = ?',
            [$selector]
        ));
        if ($row === null || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
            return false;
        }
        if (strtotime((string) $row['expires_at'] . ' UTC') < time()) {
            $this->db->delete('remember_tokens', 'id = ?', [(int) $row['id']]);
            return false;
        }
        $user = $this->users->find((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            return false;
        }
        $this->db->delete('remember_tokens', 'id = ?', [(int) $row['id']]);
        $this->login($user, true);
        return true;
    }

    public function startReset(array $user): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->delete('password_resets', 'user_id = ?', [(int) $user['id']]);
        $this->db->insert('password_resets', [
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public function consumeReset(string $token, string $password): ?string
    {
        $row = \App\Support\Row::one($this->db->fetchRow(
            'SELECT * FROM password_resets WHERE token_hash = ?',
            [hash('sha256', $token)]
        ));
        if ($row === null || strtotime((string) $row['expires_at'] . ' UTC') < time()) {
            return 'This reset link is invalid or expired.';
        }
        $this->users->update((int) $row['user_id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $this->users->bumpSession((int) $row['user_id']);
        $this->db->delete('password_resets', 'user_id = ?', [(int) $row['user_id']]);
        $this->db->delete('remember_tokens', 'user_id = ?', [(int) $row['user_id']]);
        return null;
    }
}
