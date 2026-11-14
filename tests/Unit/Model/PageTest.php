<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class PageTest extends TestCase
{
    /**
     * @test
     */
    public function it_gets_a_lowercase_uuid_v7(): void
    {
        $page = new Page();

        self::assertInstanceOf(UuidV7::class, Uuid::fromString($page->getUuid()));
        self::assertSame(strtolower($page->getUuid()), $page->getUuid());
        self::assertNotSame((new Page())->getUuid(), $page->getUuid());
    }

    /**
     * @test
     */
    public function it_is_created_and_changed_now_in_utc(): void
    {
        $before = new \DateTimeImmutable();
        $page = new Page();

        self::assertSame('UTC', $page->getCreatedAt()->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $page->getCreatedAt());
        self::assertEquals($page->getCreatedAt(), $page->getChangedAt());
    }

    /**
     * @test
     */
    public function it_starts_with_an_empty_tree_in_the_standard_layout(): void
    {
        $tree = (new Page())->getBlockTree();

        self::assertNotNull($tree->layout);
        self::assertSame('standard', $tree->layout->code);
        self::assertSame(1, $tree->layout->version);
        self::assertSame([], $tree->blocks);
    }

    /**
     * @test
     */
    public function it_stores_its_block_tree_as_canonical_json(): void
    {
        $page = new Page();
        $block = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1);
        $block->fields = ['text' => ['en_US' => 'Hello']];

        $page->setBlockTree(new StoredTree($page->getBlockTree()->layout, [$block]));

        self::assertSame(
            '{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            $page->getBlockTreeJson(),
        );
        self::assertSame(['en_US' => 'Hello'], $page->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_is_never_published_until_it_is_published(): void
    {
        $page = new Page();
        self::assertFalse($page->isPublished());
        self::assertNull($page->getLastPublishedAt());

        $at = new \DateTimeImmutable('2026-11-14 10:00:00', new \DateTimeZone('UTC'));
        $page->publish($at);

        self::assertTrue($page->isPublished());
        self::assertSame($at, $page->getLastPublishedAt());
        self::assertSame($at, $page->getChangedAt());
    }

    /**
     * @test
     */
    public function it_reads_its_title_and_slug_from_the_current_translation(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');

        $page->setTitle('About us');
        $page->setSlug('about-us');

        self::assertSame('About us', $page->getTitle());
        self::assertSame('about-us', $page->getSlug());
        self::assertInstanceOf(PageTranslation::class, $page->getTranslation('en_US'));
        self::assertSame('About us', $page->getTranslation('en_US')->getTitle());
    }
}
