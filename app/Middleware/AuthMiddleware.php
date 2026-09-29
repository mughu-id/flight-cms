<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Security\Capabilities;
use App\Support\Row;
use flight\database\SimplePdo;
use flight\Engine;
use flight\Session;

class AuthMiddleware
{
    public function __construct(
        private Engine $app,
        private Session $session,
        private SimplePdo $db,
    ) {
    }

    public function before(): void
    {
        $id = $this->session->get('user_id');
        if (!is_numeric($id)) {
            $this->app->redirect('/admin/login');
            exit;
        }
        $user = Row::one($this->db->fetchRow(
            'SELECT id, status, session_version FROM users WHERE id = ?',
            [(int) $id]
        ));
        $version = (int) $this->session->get('session_version', 0);
        if ($user === null || $user['status'] !== 'active' || (int) $user['session_version'] !== $version) {
            $this->session->clear();
            $this->app->redirect('/admin/login');
            exit;
        }
    }
}
