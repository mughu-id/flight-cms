<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Installer;
use App\Core\Migrator;
use flight\commands\AbstractBaseCommand;
use flight\database\SimplePdo;

class MigrateCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('migrate', 'Run database migrations', $config);
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $root = dirname(__DIR__, 2);
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) ($this->config['database']['path'] ?? 'storage/database/cms.sqlite'));
        $db = new SimplePdo('sqlite:' . $path);
        $migrator = new Migrator($db, $root . '/database/migrations');
        $applied = $migrator->migrate();
        $io->ok(count($applied) ? 'Applied: ' . implode(', ', $applied) : 'Nothing to migrate.');
    }
}
