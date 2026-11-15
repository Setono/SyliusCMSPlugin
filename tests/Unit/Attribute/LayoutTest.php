<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Model\Page;

final class LayoutTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_layout_as_at_version_1_without_a_thumbnail_not_the_default_and_showing_no_entry_fields_unless_it_says_otherwise(): void
    {
        $layout = new Layout('landing', Page::class, 'app.landing', 'landing.html.twig');

        self::assertSame(1, $layout->version);
        self::assertNull($layout->thumbnail);
        self::assertFalse($layout->default);
        self::assertSame([], $layout->shows);
    }
}
