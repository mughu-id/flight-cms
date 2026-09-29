<?php

declare(strict_types=1);

namespace App\Core;

class Hooks
{
    /** @var array<string, list<array{priority: int, callback: callable}>> */
    private array $listeners = [];

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->add($hook, $callback, $priority);
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->add($hook, $callback, $priority);
    }

    public function removeAction(string $hook, callable $callback): void
    {
        $this->remove($hook, $callback);
    }

    public function removeFilter(string $hook, callable $callback): void
    {
        $this->remove($hook, $callback);
    }

    public function has(string $hook): bool
    {
        return isset($this->listeners[$hook]) && $this->listeners[$hook] !== [];
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->sorted($hook) as $listener) {
            $listener['callback'](...$args);
        }
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($hook) as $listener) {
            $value = $listener['callback']($value, ...$args);
        }
        return $value;
    }

    private function add(string $hook, callable $callback, int $priority): void
    {
        $this->listeners[$hook][] = ['priority' => $priority, 'callback' => $callback];
    }

    private function remove(string $hook, callable $callback): void
    {
        if (!isset($this->listeners[$hook])) {
            return;
        }
        $this->listeners[$hook] = array_values(array_filter(
            $this->listeners[$hook],
            static fn (array $listener): bool => $listener['callback'] !== $callback
        ));
    }

    /** @return list<array{priority: int, callback: callable}> */
    private function sorted(string $hook): array
    {
        $listeners = $this->listeners[$hook] ?? [];
        usort($listeners, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        return $listeners;
    }
}
