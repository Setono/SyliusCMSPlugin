<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Doctrine;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;

final class SearchPatternTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_what_an_editor_typed_in_lower_case_without_the_spaces_around_it(): void
    {
        self::assertSame('%summer sale%', SearchPattern::containing('  Summer Sale '));
    }

    /**
     * @test
     */
    public function it_takes_the_text_s_own_wildcards_and_escape_character_as_they_are(): void
    {
        self::assertSame('%"50!% off!_now!!"%', SearchPattern::holding('"50% off_now!"'));
    }
}
