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
    public function a_layout_is_at_version_1_with_no_thumbnail_isnt_the_default_and_shows_no_entry_fields_unless_it_says_otherwise(): void
    {
        $layout = new Layout('landing', Page::class, 'app.landing', 'landing.html.twig');

        self::assertSame(1, $layout->version);
        self::assertNull($layout->thumbnail);
        self::assertFalse($layout->default);
        self::assertSame([], $layout->shows);
    }
}
