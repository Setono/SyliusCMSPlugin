<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectEntryAudienceGroup;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectEntryChannel;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectHomepage;
use Sylius\Resource\Exception\UnsupportedMethodException;

/**
 * The entry_channel and entry_audience_group resources' factories create rows of the configured model, a project's own
 * subclass when it overrides it
 */
final class EntryRowFactoriesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * A project's own content type implements EntryInterface, and its own page model PageInterface, as a Sylius model
     * implements its interface
     *
     * @test
     */
    public function it_creates_rows_for_any_entry_or_page(): void
    {
        $entry = $this->prophesize(EntryInterface::class)->reveal();
        $page = $this->prophesize(PageInterface::class)->reveal();

        self::assertSame($entry, (new EntryChannelFactory(ProjectEntryChannel::class))->createForEntry($entry, 'WEB')->getEntry());
        self::assertSame($entry, (new EntryAudienceGroupFactory(ProjectEntryAudienceGroup::class))->createForEntry($entry, '@guest')->getEntry());
        self::assertSame($page, (new HomepageFactory(ProjectHomepage::class))->createForPage($page, 'WEB')->getPage());
    }

    /**
     * @test
     */
    public function it_creates_a_channel_row_of_the_configured_model_for_an_entry(): void
    {
        $page = new Page();

        $row = (new EntryChannelFactory(ProjectEntryChannel::class))->createForEntry($page, 'WEB');

        self::assertInstanceOf(ProjectEntryChannel::class, $row);
        self::assertSame($page, $row->getEntry());
        self::assertSame('WEB', $row->getCode());
        self::assertNull($row->getId());
    }

    /**
     * @test
     */
    public function it_creates_an_audience_group_row_of_the_configured_model_for_an_entry(): void
    {
        $page = new Page();

        $row = (new EntryAudienceGroupFactory(ProjectEntryAudienceGroup::class))->createForEntry($page, '@guest');

        self::assertInstanceOf(ProjectEntryAudienceGroup::class, $row);
        self::assertSame($page, $row->getEntry());
        self::assertSame('@guest', $row->getCode());
        self::assertNull($row->getId());
    }

    /**
     * A row always belongs to an entry and has a code
     *
     * @test
     */
    public function it_creates_a_channel_row_only_for_an_entry(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        (new EntryChannelFactory(ProjectEntryChannel::class))->createNew();
    }

    /**
     * @test
     */
    public function it_creates_an_audience_group_row_only_for_an_entry(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        (new EntryAudienceGroupFactory(ProjectEntryAudienceGroup::class))->createNew();
    }

    /**
     * @test
     */
    public function it_creates_a_homepage_row_of_the_configured_model_for_a_page(): void
    {
        $page = new Page();

        $row = (new HomepageFactory(ProjectHomepage::class))->createForPage($page, 'WEB');

        self::assertInstanceOf(ProjectHomepage::class, $row);
        self::assertSame($page, $row->getPage());
        self::assertSame('WEB', $row->getChannelCode());
        self::assertNull($row->getId());
    }

    /**
     * A homepage row always belongs to a page and has a channel code
     *
     * @test
     */
    public function it_creates_a_homepage_row_only_for_a_page(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        (new HomepageFactory(ProjectHomepage::class))->createNew();
    }
}
