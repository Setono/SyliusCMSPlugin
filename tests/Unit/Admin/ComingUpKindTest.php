<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\ComingUpKind;

final class ComingUpKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_shows_each_kind_with_an_icon_of_the_admin_s_icon_set(): void
    {
        self::assertSame('clock outline', ComingUpKind::Publishes->icon());
        self::assertSame('eye', ComingUpKind::Starts->icon());
        self::assertSame('eye slash', ComingUpKind::Ends->icon());
    }
}
