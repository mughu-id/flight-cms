<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Settings;
use App\Repository\UserRepository;
use App\Security\Capabilities;
use App\Service\Csrf;
use App\Service\Flash;
use flight\Engine;

class Controller
{
    public function __construct(
        protected Engine $app,
        protected Settings $settings,
        protected Csrf $csrf,
        protected Flash $flash,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $view, array $data = []): void
    {
        $caps = $this->app->get('capabilities');
        $user = null;
        if ($caps instanceof Capabilities && $caps->userId() !== null) {
            $user = $this->app->make(UserRepository::class)->find($caps->userId());
        }
        $this->app->render($view, $data + [
            'csrf' => $this->csrf->token(),
            'flash' => $this->flash->pull(),
            'nonce' => $this->app->get('csp_nonce'),
            'site_title' => (string) $this->settings->get('site_title', 'Flight CMS'),
            'current_user' => $user,
            'can' => $caps instanceof Capabilities ? $caps : null,
        ]);
    }

    protected function redirect(string $url): void
    {
        $this->app->redirect($url);
        exit;
    }

    protected function input(string $key, string $default = ''): string
    {
        $value = $this->app->request()->data->{$key} ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}
