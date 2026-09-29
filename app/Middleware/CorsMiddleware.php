<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Settings;
use flight\Engine;

class CorsMiddleware
{
    public function __construct(private Engine $app, private Settings $settings)
    {
    }

    public function before(): void
    {
        $origin = $this->app->request()->getHeader('Origin');
        $allow = array_filter(array_map('trim', explode(',', (string) $this->settings->get('api_cors', ''))));
        if ($origin !== '' && ($allow === [] || in_array($origin, $allow, true) || in_array('*', $allow, true))) {
            $this->app->response()->header('Access-Control-Allow-Origin', $allow === [] || in_array('*', $allow, true) ? '*' : $origin);
            $this->app->response()->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-CSRF-Token');
            $this->app->response()->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        }
        if (strtoupper($this->app->request()->method) === 'OPTIONS') {
            $this->app->halt(204);
        }
    }
}
