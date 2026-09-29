<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\UserRepository;
use App\Security\Capabilities;
use App\Support\Row;
use flight\database\SimplePdo;
use flight\Engine;
use flight\Session;

class ApiAuthMiddleware
{
    public function __construct(
        private Engine $app,
        private Session $session,
        private SimplePdo $db,
    ) {
    }

    public function before(): void
    {
        $method = strtoupper($this->app->request()->method);
        $path = (string) $this->app->request()->url;
        if ($method === 'GET' && (str_ends_with($path, '/api/v1') || str_contains($path, '/api/v1/search') || preg_match('#/api/v1/content/[^/]+$#', $path))) {
            // public read endpoints still work without auth for published content
        }
        $token = $this->bearer();
        if ($token === '') {
            $this->app->jsonHalt(['error' => 'need token', 'build' => 2], 401);
        }
        if ($token !== '') {
            $row = Row::one($this->db->fetchRow(
                'SELECT * FROM api_tokens WHERE token_hash = ?',
                [hash('sha256', $token)]
            ));
            if ($row === null || ($row['expires_at'] && strtotime((string) $row['expires_at'] . ' UTC') < time())) {
                $this->app->jsonHalt(['error' => 'Invalid token'], 401);
            }
            $user = $this->app->make(UserRepository::class)->find((int) $row['user_id']);
            if ($user === null || $user['status'] !== 'active') {
                $this->app->jsonHalt(['error' => 'Invalid token'], 401);
            }
            $this->session->set('user_id', (int) $user['id']);
            $this->session->set('role', (string) $user['role']);
            $this->session->set('session_version', (int) $user['session_version']);
            $this->db->update('api_tokens', ['last_used_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
            $this->app->set('capabilities', new Capabilities($this->session, require dirname(__DIR__) . '/config/roles.php', $this->app));
return;
        }
        if ($this->session->get('user_id')) {
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $csrf = $this->app->request()->getHeader('X-CSRF-Token');
                $stored = $this->session->get('csrf');
                if (!is_string($csrf) || !is_string($stored) || !hash_equals($stored, $csrf)) {
                    $this->app->jsonHalt(['error' => 'Invalid CSRF token'], 419);
                }
            }
            $this->app->set('capabilities', new Capabilities($this->session, require dirname(__DIR__) . '/config/roles.php', $this->app));
        }
    }

    private function bearer(): string
    {
        $value = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($value === '' && !empty($_SERVER['HTTP_X_API_TOKEN'])) {
            return trim((string) $_SERVER['HTTP_X_API_TOKEN']);
        }
        if ($value === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $header) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $value = trim((string) $header);
                    break;
                }
            }
        }
        return str_starts_with($value, 'Bearer ') ? trim(substr($value, 7)) : '';
    }
}
