<?php

declare(strict_types=1);

use App\Core\Hooks;

function cms_hooks(): Hooks
{
    return Flight::app()->get('hooks');
}

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    cms_hooks()->addAction($hook, $callback, $priority);
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    cms_hooks()->addFilter($hook, $callback, $priority);
}

function do_action(string $hook, mixed ...$args): void
{
    cms_hooks()->doAction($hook, ...$args);
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return cms_hooks()->applyFilters($hook, $value, ...$args);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function log_write(string $level, string $message, array $context = []): void
{
    \App\Core\Log::write(Flight::app(), $level, $message, $context);
}
