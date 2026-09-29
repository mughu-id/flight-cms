<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Hooks;
use App\Service\Slugger;
use PHPUnit\Framework\TestCase;

final class HooksTest extends TestCase
{
    public function testFilterPriorityOrder(): void
    {
        $hooks = new Hooks();
        $hooks->addFilter('y', static fn (string $v): string => $v . 'a', 10);
        $hooks->addFilter('y', static fn (string $v): string => $v . 'b', 20);
        $this->assertSame('yab', $hooks->applyFilters('y', 'y'));
    }

    public function testSlugger(): void
    {
        $this->assertSame('hello-world', (new Slugger())->slug('Hello World!'));
    }
}
