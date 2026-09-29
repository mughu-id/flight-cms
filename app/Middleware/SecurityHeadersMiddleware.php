<?php

declare(strict_types=1);

namespace App\Middleware;

use flight\Engine;

class SecurityHeadersMiddleware
{
    public function __construct(private Engine $app)
    {
    }

    public function before(): void
    {
        $response = $this->app->response();
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $nonce = bin2hex(random_bytes(16));
        $this->app->set('csp_nonce', $nonce);
        $response->header(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; " .
            "script-src 'self' 'nonce-{$nonce}'; frame-src https:; font-src 'self' data:"
        );
    }
}
