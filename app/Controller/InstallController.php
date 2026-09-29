<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Installer;
use App\Core\Migrator;

class InstallController extends Controller
{
    public function form(): void
    {
        if ($this->app->get('installed')) {
            $this->redirect('/admin');
        }
        $installer = $this->app->make(Installer::class);
        $checks = $installer->requirements();
        $ready = !in_array(false, array_column($checks, 'ok'), true);
        $this->render('install/form', ['checks' => $checks, 'ready' => $ready]);
    }

    public function run(): void
    {
        if ($this->app->get('installed')) {
            $this->redirect('/admin');
        }
        $token = $this->app->request()->data->csrf ?? null;
        if (!$this->csrf->check(is_string($token) ? $token : null)) {
            $this->app->halt(419, 'Invalid CSRF token');
        }
        $title = $this->input('site_title');
        $username = $this->input('username');
        $email = $this->input('email');
        $password = (string) ($this->app->request()->data->password ?? '');
        $errors = [];
        if ($title === '') {
            $errors[] = 'Site title is required.';
        }
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters: lowercase letters, numbers, underscore.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        $installer = $this->app->make(Installer::class);
        $checks = $installer->requirements();
        if (in_array(false, array_column($checks, 'ok'), true)) {
            $errors[] = 'Server requirements are not met.';
        }
        if ($errors !== []) {
            $this->render('install/form', ['checks' => $checks, 'ready' => false, 'errors' => $errors]);
            return;
        }
        $installer->install([
            'site_title' => $title,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'display_name' => $this->input('display_name') !== '' ? $this->input('display_name') : $username,
        ]);
        $this->redirect('/admin/login');
    }
}
