<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\EntryKind;
use Setono\SyliusCMSPlugin\Condition\Kind\EntryParams;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;

final class EntryKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_when_the_entry_being_shown_is_one_of_the_entries(): void
    {
        $about = new Page();
        $params = self::params(new Page(), $about);

        self::assertTrue((new EntryKind())->matches($params, self::showing($about)));
        self::assertFalse((new EntryKind())->matches($params, self::showing(new Page())));
    }

    /**
     * @test
     */
    public function it_matches_nothing_without_entries(): void
    {
        self::assertFalse((new EntryKind())->matches(self::params(), self::showing(new Page())));
        self::assertFalse((new EntryKind())->matches(new EntryParams(), self::showing(new Page())));
    }

    /**
     * @test
     */
    public function it_matches_nothing_where_no_entry_is_shown(): void
    {
        self::assertFalse((new EntryKind())->matches(self::params(new Page()), Rendering::context()));
        self::assertFalse((new EntryKind())->matches(self::params(new Page()), Rendering::context(hostPageContext: ['entry' => new Product()])), 'Not an entry');
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The entry condition takes %s, got %s', EntryParams::class, NoParams::class));

        (new EntryKind())->matches(new NoParams(), self::showing(new Page()));
    }

    private static function params(EntryInterface ...$entries): EntryParams
    {
        $params = new EntryParams();
        $params->entries = ItemList::of(array_values($entries));

        return $params;
    }

    private static function showing(EntryInterface $entry): RenderContext
    {
        return Rendering::context(hostPageContext: ['entry' => $entry]);
    }
}
