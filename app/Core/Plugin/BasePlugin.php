<?php

declare(strict_types=1);

namespace App\Core\Plugin;

use App\Core\Hooks;
use App\Core\Shortcodes;
use flight\Engine;

abstract class BasePlugin
{
    public function __construct(
        protected Engine $app,
        protected Hooks $hooks,
        protected Shortcodes $shortcodes,
        protected string $slug,
        protected string $path,
    ) {
    }

    abstract public function register(): void;

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }

    public function uninstall(): void
    {
    }
}
