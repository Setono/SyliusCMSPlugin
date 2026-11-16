<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Targeting;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifier;

final class ChannelsAndAudienceModifierTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryChannelFactoryInterface> */
    private ObjectProphecy $channelFactory;

    /** @var ObjectProphecy<EntryAudienceGroupFactoryInterface> */
    private ObjectProphecy $audienceGroupFactory;

    protected function setUp(): void
    {
        $this->channelFactory = $this->prophesize(EntryChannelFactoryInterface::class);
        $this->audienceGroupFactory = $this->prophesize(EntryAudienceGroupFactoryInterface::class);
    }

    /**
     * @test
     */
    public function it_creates_the_rows_of_new_channels_through_the_entry_channel_factory(): void
    {
        $page = new Page();
        $web = new EntryChannel($page, 'WEB');
        $b2b = new EntryChannel($page, 'B2B');
        $this->channelFactory->createForEntry($page, 'WEB')->willReturn($web)->shouldBeCalledOnce();
        $this->channelFactory->createForEntry($page, 'B2B')->willReturn($b2b)->shouldBeCalledOnce();

        $this->modifier()->setChannels($page, ['WEB', 'B2B', 'WEB']);

        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_creates_rows_only_for_the_channels_an_entry_is_not_in_yet(): void
    {
        $page = new Page();
        $this->channelFactory->createForEntry($page, 'WEB')->willReturn(new EntryChannel($page, 'WEB'));
        $this->channelFactory->createForEntry($page, 'B2B')->willReturn(new EntryChannel($page, 'B2B'));
        $this->modifier()->setChannels($page, ['WEB', 'B2B']);

        $this->channelFactory->createForEntry($page, 'MOBILE')->willReturn(new EntryChannel($page, 'MOBILE'))->shouldBeCalledOnce();
        $this->channelFactory->createForEntry($page, 'WEB')->shouldBeCalledOnce();

        $this->modifier()->setChannels($page, ['WEB', 'MOBILE']);

        self::assertSame(['MOBILE', 'WEB'], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_creates_the_rows_of_new_audience_groups_through_the_entry_audience_group_factory(): void
    {
        $page = new Page();
        $this->audienceGroupFactory->createForEntry($page, 'wholesale')->willReturn(new EntryAudienceGroup($page, 'wholesale'))->shouldBeCalledOnce();
        $this->audienceGroupFactory->createForEntry($page, '@guest')->willReturn(new EntryAudienceGroup($page, '@guest'))->shouldBeCalledOnce();

        $this->modifier()->setAudience($page, Audience::except('wholesale', '@guest'));

        self::assertTrue($page->getAudience()->equals(Audience::except('@guest', 'wholesale')));
    }

    /**
     * @test
     */
    public function it_creates_rows_only_for_the_groups_an_audience_does_not_have_yet(): void
    {
        $page = new Page();
        $this->audienceGroupFactory->createForEntry($page, 'wholesale')->willReturn(new EntryAudienceGroup($page, 'wholesale'));
        $this->modifier()->setAudience($page, Audience::only('wholesale'));

        $this->audienceGroupFactory->createForEntry($page, 'retail')->willReturn(new EntryAudienceGroup($page, 'retail'))->shouldBeCalledOnce();
        $this->audienceGroupFactory->createForEntry($page, 'wholesale')->shouldBeCalledOnce();

        $this->modifier()->setAudience($page, Audience::except('wholesale', 'retail'));
        $this->modifier()->setAudience($page, Audience::except('retail'));

        self::assertTrue($page->getAudience()->equals(Audience::except('retail')));
    }

    /**
     * @test
     */
    public function it_creates_no_rows_for_an_audience_of_everyone(): void
    {
        $page = new Page();
        $this->audienceGroupFactory->createForEntry(Argument::cetera())->shouldNotBeCalled();

        $this->modifier()->setAudience($page, Audience::everyone());

        self::assertTrue($page->getAudience()->equals(Audience::everyone()));
    }

    /**
     * Doctrine removes rows after it inserts them, so a code that stays must keep its row, or the insert would clash
     * with the row's unique key
     *
     * @test
     */
    public function it_keeps_the_rows_of_the_channels_that_stay_and_removes_the_others(): void
    {
        $page = new Page();
        $web = new EntryChannel($page, 'WEB');
        $b2b = new EntryChannel($page, 'B2B');
        $mobile = new EntryChannel($page, 'MOBILE');
        $this->channelFactory->createForEntry($page, 'WEB')->willReturn($web);
        $this->channelFactory->createForEntry($page, 'B2B')->willReturn($b2b);
        $this->channelFactory->createForEntry($page, 'MOBILE')->willReturn($mobile);
        $this->modifier()->setChannels($page, ['WEB', 'B2B']);

        $this->modifier()->setChannels($page, (static function (): \Generator {
            yield 'MOBILE';
            yield 'WEB';
            yield 'MOBILE';
        })());

        self::assertSame([$web, $mobile], $page->getChannels()->getValues());

        $this->modifier()->setChannels($page, []);

        self::assertSame([], $page->getChannels()->getValues());
    }

    /**
     * @test
     */
    public function it_keeps_the_rows_of_the_audience_groups_that_stay_and_removes_the_others(): void
    {
        $page = new Page();
        $retail = new EntryAudienceGroup($page, 'retail');
        $wholesale = new EntryAudienceGroup($page, 'wholesale');
        $guest = new EntryAudienceGroup($page, '@guest');
        $this->audienceGroupFactory->createForEntry($page, 'retail')->willReturn($retail);
        $this->audienceGroupFactory->createForEntry($page, 'wholesale')->willReturn($wholesale);
        $this->audienceGroupFactory->createForEntry($page, '@guest')->willReturn($guest);
        $this->modifier()->setAudience($page, Audience::only('retail', 'wholesale'));

        $this->modifier()->setAudience($page, Audience::except('wholesale', '@guest'));

        self::assertSame([$wholesale, $guest], $page->getAudienceGroups()->getValues());
        self::assertTrue($page->getAudience()->equals(Audience::except('@guest', 'wholesale')));
    }

    /**
     * The mode and the groups change together, so the entry never shows to everyone with groups left over
     *
     * @test
     */
    public function it_changes_the_audience_mode_with_its_groups(): void
    {
        $page = new Page();
        $this->audienceGroupFactory->createForEntry($page, 'wholesale')->willReturn(new EntryAudienceGroup($page, 'wholesale'));

        $this->modifier()->setAudience($page, Audience::only('wholesale'));

        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));

        $this->modifier()->setAudience($page, Audience::except('wholesale'));

        self::assertTrue($page->getAudience()->equals(Audience::except('wholesale')));

        $this->modifier()->setAudience($page, Audience::everyone());

        self::assertSame([], $page->getAudienceGroups()->getValues());
        self::assertTrue($page->getAudience()->equals(Audience::everyone()));

        $this->modifier()->setAudience($page, Audience::only());

        self::assertTrue($page->getAudience()->equals(Audience::only()));
    }

    /**
     * A project's own content type implements EntryInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_changes_the_channels_and_audience_of_any_entry_through_its_interface(): void
    {
        $entry = $this->prophesize(EntryInterface::class);
        $entry->getChannels()->willReturn(new ArrayCollection());
        $entry->getAudienceGroups()->willReturn(new ArrayCollection());
        $revealed = $entry->reveal();
        $channel = $this->prophesize(EntryChannelInterface::class)->reveal();
        $group = $this->prophesize(EntryAudienceGroupInterface::class)->reveal();
        $this->channelFactory->createForEntry($revealed, 'WEB')->willReturn($channel);
        $this->audienceGroupFactory->createForEntry($revealed, 'wholesale')->willReturn($group);

        $this->modifier()->setChannels($revealed, ['WEB']);
        $this->modifier()->setAudience($revealed, Audience::only('wholesale'));

        $entry->addChannel($channel)->shouldHaveBeenCalledOnce();
        $entry->addAudienceGroup($group)->shouldHaveBeenCalledOnce();
        $entry->setAudienceMode(AudienceMode::Only)->shouldHaveBeenCalledOnce();
    }

    private function modifier(): ChannelsAndAudienceModifier
    {
        return new ChannelsAndAudienceModifier($this->channelFactory->reveal(), $this->audienceGroupFactory->reveal());
    }
}
