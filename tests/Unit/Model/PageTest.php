<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class PageTest extends TestCase
{
    use ProphecyTrait;

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

    /**
     * A translation made before the first publish starts shown. One added after it is held back where it's added
     * (docs/spec/publishing.md#held-back-translations)
     *
     * @test
     */
    public function it_starts_a_translation_shown_until_it_is_held_back(): void
    {
        $translation = new PageTranslation();
        self::assertTrue($translation->isShown());

        $translation->setShown(false);
        self::assertFalse($translation->isShown());
    }

    /**
     * @test
     */
    public function it_is_the_homepage_of_the_channels_of_its_homepage_rows(): void
    {
        $page = new Page();
        $web = self::homepage($page, 'WEB');
        $b2b = self::homepage($page, 'B2B');
        self::assertSame([], $page->getHomepageChannelCodes());
        self::assertFalse($page->isHomepageOf('WEB'));

        $page->addHomepage($web);
        $page->addHomepage($b2b);

        self::assertSame([$web, $b2b], $page->getHomepages()->getValues());
        self::assertTrue($page->hasHomepage($web));
        self::assertSame(['B2B', 'WEB'], $page->getHomepageChannelCodes());
        self::assertTrue($page->isHomepageOf('WEB'));
        self::assertTrue($page->isHomepageOf('B2B'));
        self::assertFalse($page->isHomepageOf('MOBILE'));

        $page->removeHomepage($web);

        self::assertSame([$b2b], $page->getHomepages()->getValues());
        self::assertFalse($page->hasHomepage($web));
        self::assertSame(['B2B'], $page->getHomepageChannelCodes());
        self::assertFalse($page->isHomepageOf('WEB'));
    }

    /**
     * @test
     */
    public function it_adds_a_homepage_row_once(): void
    {
        $page = new Page();
        $web = self::homepage($page, 'WEB');

        $page->addHomepage($web);
        $page->addHomepage($web);

        self::assertCount(1, $page->getHomepages());
    }

    /**
     * A row's page is set when it's created, so it can't move to another page
     *
     * @test
     */
    public function it_refuses_a_homepage_row_of_another_page(): void
    {
        $page = new Page();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The homepage row of the channel WEB belongs to another page');

        $page->addHomepage(self::homepage(new Page(), 'WEB'));
    }

    /**
     * A project's own homepage model implements HomepageInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_takes_homepage_rows_of_any_model_that_implements_their_interface(): void
    {
        $page = new Page();
        $homepage = $this->prophesize(HomepageInterface::class);
        $homepage->getPage()->willReturn($page);
        $homepage->getChannelCode()->willReturn('WEB');

        $page->addHomepage($homepage->reveal());

        self::assertSame(['WEB'], $page->getHomepageChannelCodes());
    }

    /**
     * A working copy is a clone, which reads the original's homepage rows through a collection of its own
     *
     * @test
     */
    public function it_clones_into_a_copy_that_reads_the_original_homepage_rows_and_leaves_the_original_alone_when_they_change(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('Home');
        $web = self::homepage($page, 'WEB');
        $page->addHomepage($web);

        $copy = clone $page;

        self::assertSame([$web], $copy->getHomepages()->getValues());
        self::assertTrue($copy->isHomepageOf('WEB'));
        self::assertNotSame($page->getTranslation('en_US'), $copy->getTranslation('en_US'), 'Its translations are copied as every entry\'s are');

        $copy->removeHomepage($web);

        self::assertSame([], $copy->getHomepageChannelCodes());
        self::assertSame([$web], $page->getHomepages()->getValues());
    }

    private static function homepage(Page $page, string $channelCode): HomepageInterface
    {
        return Homepages::factory()->createForPage($page, $channelCode);
    }
}
