<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\EntryReference;

/**
 * A row of the reference table. See "Categories" in docs/spec/collections.md
 */
final class EntryReferenceTest extends TestCase
{
    /**
     * @test
     */
    public function it_holds_a_uuid_an_indexed_field_of_its_entry_holds_lowercase(): void
    {
        $post = new BlogPost();
        $reference = new EntryReference();
        $reference->setEntry($post);
        $reference->setField('categories');
        $reference->setUuid('0193A5BE-7255-7F00-8A1B-2C3D4E5F6A7B');

        self::assertNull($reference->getId());
        self::assertSame($post, $reference->getEntry());
        self::assertSame('categories', $reference->getField());
        self::assertSame('0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b', $reference->getUuid());
    }
}
