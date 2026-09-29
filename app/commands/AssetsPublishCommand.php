<?php

declare(strict_types=1);

namespace App\Command;

use flight\commands\AbstractBaseCommand;

class AssetsPublishCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('assets:publish', 'Copy vendor assets into assets/', $config);
    }

    public function execute(): void
    {
        require dirname(__DIR__, 2) . '/scripts/setup.php';
        $this->app()->io()->ok('Vendor assets published.');
    }
}
