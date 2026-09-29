<?php

declare(strict_types=1);

namespace App\Core;

class Shortcodes
{
    /** @var array<string, callable> */
    private array $tags = [];

    public function add(string $tag, callable $callback): void
    {
        $this->tags[$tag] = $callback;
    }

    public function process(string $content): string
    {
        return (string) preg_replace_callback('/\[([a-z0-9_-]+)([^\]]*)\]/i', function (array $m): string {
            $tag = strtolower($m[1]);
            if (!isset($this->tags[$tag])) {
                return $m[0];
            }
            $attrs = [];
            if (preg_match_all('/(\w+)="([^"]*)"/', $m[2], $parts, PREG_SET_ORDER)) {
                foreach ($parts as $part) {
                    $attrs[$part[1]] = $part[2];
                }
            }
            return (string) ($this->tags[$tag])($attrs);
        }, $content);
    }
}
