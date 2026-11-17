<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Context\Needs;

/**
 * Block types, condition kinds, sources and bindings need context keys, which an entry provides or a snippet declares.
 * See "Context keys" in docs/spec/slots.md
 */
final class NeedsTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideNeeds
     *
     * @param list<string> $needs
     * @param list<string> $provided
     * @param list<string> $missing
     */
    public function it_finds_the_needed_context_keys_that_are_not_provided(array $needs, array $provided, array $missing): void
    {
        self::assertSame($missing, Needs::missing($needs, $provided));
        self::assertSame([] === $missing, Needs::areMet($needs, $provided));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, list<string>}>
     */
    public function provideNeeds(): iterable
    {
        yield 'needing nothing on any page' => [[], [], []];
        yield 'needing nothing where something is provided' => [[], ['product'], []];
        yield 'needing what is provided' => [['product'], ['product'], []];
        yield 'needing less than is provided' => [['product'], ['taxon', 'product'], []];
        yield 'needing what is not provided' => [['product'], ['entry'], ['product']];
        yield 'needing more than is provided' => [['product', 'taxon', 'brand'], ['taxon'], ['product', 'brand']];
        yield 'needing on any page' => [['product'], [], ['product']];
    }
}
