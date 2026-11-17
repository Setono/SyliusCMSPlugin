<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;

final class ResolvedLinkTest extends TestCase
{
    /**
     * @test
     */
    public function it_keeps_the_target_and_the_ref_it_resolved_beside_the_url_and_the_name(): void
    {
        $link = new ResolvedLink('product', 'T_SHIRT_RED', 'https://localhost/en_US/products/red-t-shirt', 'Red T-shirt');

        self::assertSame('product', $link->target);
        self::assertSame('T_SHIRT_RED', $link->ref);
        self::assertSame('https://localhost/en_US/products/red-t-shirt', $link->url);
        self::assertSame('Red T-shirt', $link->name);
    }

    /**
     * @test
     */
    public function it_keeps_the_ref_when_it_adds_an_anchor(): void
    {
        $link = (new ResolvedLink('entry', '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'https://localhost/en_US/about', 'About us'))->withAnchor('opening-hours');

        self::assertSame('https://localhost/en_US/about#opening-hours', $link->url);
        self::assertSame('entry', $link->target);
        self::assertSame('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', $link->ref);
        self::assertSame('About us', $link->name);
    }

    /**
     * @test
     */
    public function its_anchor_replaces_the_fragment_the_url_has(): void
    {
        self::assertSame('https://example.com/page#second', (new ResolvedLink('url', 'https://example.com/page#first', 'https://example.com/page#first', ''))->withAnchor('second')->url);
    }

    /**
     * @test
     */
    public function it_encodes_the_anchor(): void
    {
        self::assertSame('/faq#delivery%20times', (new ResolvedLink('url', '/faq', '/faq', ''))->withAnchor('delivery times')->url);
    }

    /**
     * @test
     */
    public function it_leaves_the_url_as_it_is_without_an_anchor(): void
    {
        $link = new ResolvedLink('url', '/faq#top', '/faq#top', '');

        self::assertSame($link, $link->withAnchor(null));
        self::assertSame($link, $link->withAnchor(''));
    }
}
