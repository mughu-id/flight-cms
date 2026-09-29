<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\Csrf;
use flight\Engine;

class CsrfMiddleware
{
    public function __construct(private Engine $app, private Csrf $csrf)
    {
    }

    public function before(): void
    {
        $method = strtoupper($this->app->request()->method);
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $token = $this->app->request()->data->csrf
            ?? $this->app->request()->getHeader('X-CSRF-Token');
        if (!$this->csrf->check(is_string($token) ? $token : null)) {
            $this->app->halt(419, 'Invalid CSRF token');
        }
    }
}
