<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Search;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\Search\LinkTargetSearch;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\StoreLinkTarget;

final class LinkTargetSearchTest extends TestCase
{
    /**
     * @test
     */
    public function it_finds_what_the_link_target_finds(): void
    {
        $search = new LinkTargetSearch(new StoreLinkTarget());

        self::assertEquals([new SearchResult('aarhus', 'Aarhus')], $search->search('AAR', 'en_US'));
        self::assertEquals([new SearchResult('copenhagen', 'Copenhagen'), new SearchResult('aarhus', 'Aarhus')], $search->search('', 'en_US'));
    }

    /**
     * @test
     */
    public function it_cannot_look_refs_up(): void
    {
        self::assertSame([], (new LinkTargetSearch(new StoreLinkTarget()))->find(['aarhus'], 'en_US'));
    }
}
