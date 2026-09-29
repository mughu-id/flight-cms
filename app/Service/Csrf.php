<?php

declare(strict_types=1);

namespace App\Service;

use flight\Session;

class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf', $token);
        }
        return $token;
    }

    public function check(?string $token): bool
    {
        $stored = $this->session->get('csrf');
        return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }
}
