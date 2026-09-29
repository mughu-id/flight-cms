<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Migrator;
use App\Service\Csrf;
use App\Service\HtmlSanitizer;
use App\Service\RateLimiter;
use flight\database\SimplePdo;
use flight\Session;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testSanitizerStripsScript(): void
    {
        $clean = (new HtmlSanitizer())->clean('<p>Hi</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringContainsString('<p>Hi</p>', $clean);
    }

    public function testCsrf(): void
    {
        $session = new Session(['test_mode' => true, 'start_session' => false]);
        $csrf = new Csrf($session);
        $token = $csrf->token();
        $this->assertTrue($csrf->check($token));
        $this->assertFalse($csrf->check('nope'));
    }

    public function testRateLimiter(): void
    {
        $db = new SimplePdo('sqlite::memory:');
        $db->exec('CREATE TABLE rate_limits (bucket TEXT PRIMARY KEY, hits INTEGER NOT NULL, reset_at INTEGER NOT NULL)');
        $limiter = new RateLimiter($db);
        $this->assertTrue($limiter->allow('t', 2, 60));
        $this->assertTrue($limiter->allow('t', 2, 60));
        $this->assertFalse($limiter->allow('t', 2, 60));
    }

    public function testMigrator(): void
    {
        $dir = sys_get_temp_dir() . '/cms-mig-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/0001_test.sql', 'CREATE TABLE demo (id INTEGER PRIMARY KEY);');
        $db = new SimplePdo('sqlite::memory:');
        $migrator = new Migrator($db, $dir);
        $this->assertSame(['0001_test.sql'], $migrator->pending());
        $this->assertSame(['0001_test.sql'], $migrator->migrate());
        $this->assertSame([], $migrator->pending());
    }
}
