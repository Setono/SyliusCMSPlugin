<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyMetadata;

final class BindablePropertyMetadataTest extends TestCase
{
    /**
     * A binding of a property the code no longer has still needs its context key, read from the binding
     *
     * @test
     */
    public function it_needs_the_context_key_it_reads_but_neither_the_channel_nor_the_customer_which_are_everywhere(): void
    {
        self::assertSame(['product'], BindablePropertyMetadata::needsFor('product'));
        self::assertSame(['store_location'], BindablePropertyMetadata::needsFor('store_location'));
        self::assertSame([], BindablePropertyMetadata::needsFor(BindablePropertyMetadata::CHANNEL));
        self::assertSame([], BindablePropertyMetadata::needsFor(BindablePropertyMetadata::CUSTOMER));
    }
}
