<?php

declare(strict_types=1);

namespace App\Core;

class Config
{
    public function __construct(private array $data)
    {
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $cursor = $this->data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    public function all(): array
    {
        return $this->data;
    }
}
