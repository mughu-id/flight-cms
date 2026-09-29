<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Settings;
use App\Service\RateLimiter;
use flight\Engine;

class RateLimitMiddleware
{
    public function __construct(private Engine $app, private Settings $settings, private RateLimiter $limiter)
    {
    }

    public function before(): void
    {
        $ip = (string) ($this->app->request()->ip ?? '0.0.0.0');
        $max = (int) $this->settings->get('api_rate_limit', 60);
        if (!$this->limiter->allow('api:' . $ip, $max, 60)) {
            $this->app->jsonHalt(['error' => 'Rate limit exceeded'], 429);
        }
    }
}
