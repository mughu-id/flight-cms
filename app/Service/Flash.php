<?php

declare(strict_types=1);

namespace App\Service;

use flight\Session;

class Flash
{
    public function __construct(private Session $session)
    {
    }

    public function set(string $type, string $message): void
    {
        $this->session->set('flash', ['type' => $type, 'message' => $message]);
    }

    /** @return array{type: string, message: string}|null */
    public function pull(): ?array
    {
        $flash = $this->session->get('flash');
        $this->session->delete('flash');
        if (!is_array($flash) || !isset($flash['message'])) {
            return null;
        }
        return ['type' => (string) ($flash['type'] ?? 'info'), 'message' => (string) $flash['message']];
    }
}
