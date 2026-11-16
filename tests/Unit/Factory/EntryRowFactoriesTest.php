<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectEntryAudienceGroup;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectEntryChannel;
use Sylius\Resource\Exception\UnsupportedMethodException;

/**
 * The entry_channel and entry_audience_group resources' factories create rows of the configured model, a project's own
 * subclass when it overrides it
 */
final class EntryRowFactoriesTest extends TestCase
{
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
}
