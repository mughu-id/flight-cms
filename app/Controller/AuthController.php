<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\Auth;
use App\Service\Mailer;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->app->make(Auth::class)->fromRememberCookie();
        if ($this->app->get('capabilities')->userId() !== null) {
            $this->redirect('/admin');
        }
        $this->render('auth/login', [
            'registration' => (bool) $this->settings->get('registration_open', false),
        ]);
    }

    public function login(): void
    {
        $error = $this->app->make(Auth::class)->attempt(
            $this->input('login'),
            (string) ($this->app->request()->data->password ?? ''),
            (bool) $this->app->request()->data->remember
        );
        if ($error !== null) {
            $this->render('auth/login', [
                'error' => $error,
                'registration' => (bool) $this->settings->get('registration_open', false),
            ]);
            return;
        }
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        $this->app->make(Auth::class)->logout();
        $this->redirect('/admin/login');
    }

    public function forgotForm(): void
    {
        $this->render('auth/forgot');
    }

    public function forgot(): void
    {
        $ip = (string) ($this->app->request()->ip ?? '0.0.0.0');
        $limiter = $this->app->make(\App\Service\RateLimiter::class);
        if (!$limiter->allow('reset:' . $ip, 5, 3600)) {
            $this->render('auth/forgot', ['error' => 'Too many requests. Try again later.']);
            return;
        }
        $user = $this->app->make(UserRepository::class)->findByEmail($this->input('email'));
        if ($user !== null) {
            $token = $this->app->make(Auth::class)->startReset($user);
            $url = rtrim((string) $this->app->get('config')->get('app.url'), '/') . '/admin/reset-password/' . $token;
            $this->app->make(Mailer::class)->send(
                (string) $user['email'],
                'Reset your password',
                '<p>Reset your password: <a href="' . e($url) . '">' . e($url) . '</a></p><p>This link expires in one hour.</p>'
            );
        }
        $this->flash->set('success', 'If that email exists, a reset link is on its way.');
        $this->redirect('/admin/login');
    }

    public function resetForm(string $token): void
    {
        $this->render('auth/reset', ['token' => $token]);
    }

    public function reset(string $token): void
    {
        $password = (string) ($this->app->request()->data->password ?? '');
        if (strlen($password) < 8) {
            $this->render('auth/reset', ['token' => $token, 'error' => 'Password must be at least 8 characters.']);
            return;
        }
        $error = $this->app->make(Auth::class)->consumeReset($token, $password);
        if ($error !== null) {
            $this->render('auth/reset', ['token' => $token, 'error' => $error]);
            return;
        }
        $this->flash->set('success', 'Password updated. You can log in now.');
        $this->redirect('/admin/login');
    }

    public function registerForm(): void
    {
        if (!$this->settings->get('registration_open', false)) {
            $this->redirect('/admin/login');
        }
        $this->render('auth/register');
    }

    public function register(): void
    {
        if (!$this->settings->get('registration_open', false)) {
            $this->redirect('/admin/login');
        }
        $users = $this->app->make(UserRepository::class);
        $username = $this->input('username');
        $email = $this->input('email');
        $password = (string) ($this->app->request()->data->password ?? '');
        $error = null;
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters: lowercase letters, numbers, underscore.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'A valid email is required.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($users->findByLogin($username) !== null || $users->findByEmail($email) !== null) {
            $error = 'That username or email is already taken.';
        }
        if ($error !== null) {
            $this->render('auth/register', ['error' => $error]);
            return;
        }
        $users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $username,
            'role' => (string) $this->settings->get('default_role', 'subscriber'),
        ]);
        $this->flash->set('success', 'Account created. You can log in now.');
        $this->redirect('/admin/login');
    }
}
