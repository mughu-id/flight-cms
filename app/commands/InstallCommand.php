<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Installer;
use App\Core\Migrator;
use App\Core\Settings;
use App\Service\Slugger;
use flight\commands\AbstractBaseCommand;
use flight\database\SimplePdo;

class InstallCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('cms:install', 'Install Flight CMS from the CLI', $config);
        $this->option('-t --title', 'Site title', null, 'Flight CMS');
        $this->option('-u --username', 'Admin username', null, 'admin');
        $this->option('-e --email', 'Admin email', null, 'admin@example.com');
        $this->option('-p --password', 'Admin password', null, 'password123');
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $values = $this->values();
        $root = dirname(__DIR__, 2);
        $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) ($this->config['database']['path'] ?? 'storage/database/cms.sqlite'));
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $db = new SimplePdo('sqlite:' . $path);
        foreach (['journal_mode = WAL', 'foreign_keys = ON'] as $pragma) {
            $db->exec('PRAGMA ' . $pragma);
        }
        $installer = new Installer($db, new Migrator($db), new Settings($db), new Slugger());
        $installer->install([
            'site_title' => (string) ($values['title'] ?? 'Flight CMS'),
            'username' => (string) ($values['username'] ?? 'admin'),
            'email' => (string) ($values['email'] ?? 'admin@example.com'),
            'password' => (string) ($values['password'] ?? 'password123'),
            'display_name' => (string) ($values['username'] ?? 'admin'),
        ]);
        $io->ok('Installed. Login with ' . ($values['username'] ?? 'admin'));
    }
}
