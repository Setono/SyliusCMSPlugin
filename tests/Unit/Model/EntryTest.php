<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Model\AbstractTranslation;
use Sylius\Resource\Model\TranslationInterface;

final class EntryTest extends TestCase
{
    /**
     * @test
     */
    public function an_entry_without_its_own_url_starts_with_an_empty_tree_without_a_layout(): void
    {
        $recipe = new Recipe();

        self::assertNull($recipe->getBlockTree()->layout);
        self::assertSame([], $recipe->getBlockTree()->blocks);
        self::assertSame('{"layout":null,"blocks":[]}', $recipe->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function a_new_entry_and_translation_have_no_id(): void
    {
        self::assertNull((new Recipe())->getId());
        self::assertNull((new EntryTranslation())->getId());
    }

    /**
     * @test
     */
    public function it_clones_the_translations_and_tree_so_they_change_without_the_original(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->getTranslation('da_DK')->setTitle('Om os');

        $copy = clone $page;
        $copy->setTitle('About');
        $copy->getTranslation('da_DK')->setTitle('Om');
        $copy->setBlockTree(new StoredTree(null, [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1)]));

        self::assertSame($page->getUuid(), $copy->getUuid());
        self::assertSame(['en_US', 'da_DK'], $copy->getTranslations()->getKeys());
        self::assertSame($copy, $copy->getTranslation('da_DK')->getTranslatable());
        self::assertSame('About', $copy->getTitle());
        self::assertSame('Om', $copy->getTranslation('da_DK')->getTitle());

        self::assertSame('About us', $page->getTitle());
        self::assertSame('Om os', $page->getTranslation('da_DK')->getTitle());
        self::assertSame($page, $page->getTranslation('da_DK')->getTranslatable());
        self::assertCount(2, $page->getTranslations());
        self::assertSame([], $page->getBlockTree()->blocks);
    }

    /**
     * @test
     */
    public function it_starts_an_entry_enabled_for_everyone_at_any_time_and_in_no_channel_until_it_is_given_some(): void
    {
        $page = new Page();

        self::assertTrue($page->isEnabled());
        self::assertTrue($page->getDateWindow()->equals(new DateWindow()));
        self::assertTrue($page->getAudience()->equals(Audience::everyone()));
        self::assertSame([], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_keeps_its_enabled_switch_and_date_window(): void
    {
        $page = new Page();
        $window = new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'));

        $page->setEnabled(false);
        $page->setDateWindow($window);

        self::assertFalse($page->isEnabled());
        self::assertTrue($page->getDateWindow()->equals($window));

        $page->setDateWindow(new DateWindow());

        self::assertTrue($page->getDateWindow()->equals(new DateWindow()));
    }

    /**
     * @test
     */
    public function it_is_in_the_channels_of_its_channel_rows(): void
    {
        $page = new Page();
        $web = self::channel($page, 'WEB');
        $b2b = self::channel($page, 'B2B');

        $page->addChannel($web);
        $page->addChannel($b2b);

        self::assertSame([$web, $b2b], $page->getChannels()->getValues());
        self::assertTrue($page->hasChannel($web));
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());

        $page->removeChannel($web);

        self::assertSame([$b2b], $page->getChannels()->getValues());
        self::assertFalse($page->hasChannel($web));
        self::assertSame(['B2B'], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_adds_a_channel_row_once(): void
    {
        $page = new Page();
        $web = self::channel($page, 'WEB');

        $page->addChannel($web);
        $page->addChannel($web);

        self::assertCount(1, $page->getChannels());
    }

    /**
     * A row's entry is set when it's created, so it can't move to another entry
     *
     * @test
     */
    public function it_refuses_a_channel_row_of_another_entry(): void
    {
        $page = new Page();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The channel row WEB belongs to another entry');

        $page->addChannel(self::channel(new Page(), 'WEB'));
    }

    /**
     * @test
     */
    public function it_shows_to_the_audience_of_its_mode_and_group_rows(): void
    {
        $page = new Page();
        $wholesale = self::audienceGroup($page, 'wholesale');
        $guest = self::audienceGroup($page, '@guest');

        $page->addAudienceGroup($wholesale);
        $page->addAudienceGroup($guest);
        $page->setAudienceMode(AudienceMode::Except);

        self::assertSame([$wholesale, $guest], $page->getAudienceGroups()->getValues());
        self::assertTrue($page->hasAudienceGroup($guest));
        self::assertTrue($page->getAudience()->equals(Audience::except('@guest', 'wholesale')));

        $page->removeAudienceGroup($guest);
        $page->setAudienceMode(AudienceMode::Only);

        self::assertSame([$wholesale], $page->getAudienceGroups()->getValues());
        self::assertFalse($page->hasAudienceGroup($guest));
        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));
    }

    /**
     * @test
     */
    public function it_adds_an_audience_group_row_once(): void
    {
        $page = new Page();
        $wholesale = self::audienceGroup($page, 'wholesale');

        $page->addAudienceGroup($wholesale);
        $page->addAudienceGroup($wholesale);

        self::assertCount(1, $page->getAudienceGroups());
    }

    /**
     * @test
     */
    public function it_refuses_an_audience_group_row_of_another_entry(): void
    {
        $page = new Page();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The audience group row @guest belongs to another entry');

        $page->addAudienceGroup(self::audienceGroup(new Page(), '@guest'));
    }

    /**
     * Doctrine hydrates a datetime column in PHP's default time zone, while the column holds UTC
     *
     * @test
     */
    public function it_holds_utc_in_its_date_window_columns(): void
    {
        $page = new Page();
        $copenhagen = new \DateTimeZone('Europe/Copenhagen');
        (new \ReflectionProperty(Entry::class, 'showFrom'))->setValue($page, new \DateTimeImmutable('2026-11-27 00:00:00', $copenhagen));
        (new \ReflectionProperty(Entry::class, 'showUntil'))->setValue($page, new \DateTimeImmutable('2026-12-01 00:00:00', $copenhagen));

        self::assertEquals(new \DateTimeImmutable('2026-11-27T00:00:00Z'), $page->getDateWindow()->from);
        self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $page->getDateWindow()->until);

        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2026-11-27 01:00:00', $copenhagen)));

        $showFrom = (new \ReflectionProperty(Entry::class, 'showFrom'))->getValue($page);
        self::assertInstanceOf(\DateTimeImmutable::class, $showFrom);
        self::assertSame('2026-11-27 00:00:00 UTC', $showFrom->format('Y-m-d H:i:s T'));
        self::assertNull((new \ReflectionProperty(Entry::class, 'showUntil'))->getValue($page));
    }

    /**
     * @test
     */
    public function it_bumps_changed_at_when_an_on_save_setting_is_saved(): void
    {
        $page = new Page();
        $at = new \DateTimeImmutable('2026-11-14T12:00:00Z');

        $page->setChangedAt($at);

        self::assertSame($at, $page->getChangedAt());
        self::assertNull($page->getLastPublishedAt());
    }

    /**
     * A clone reads the original's rows, which never change, through collections of its own. A model can't reach the
     * factories that create rows, and the copy is never persisted
     *
     * @test
     */
    public function its_clone_reads_the_original_rows_and_leaves_the_original_alone_when_its_rows_change(): void
    {
        $page = new Page();
        $web = self::channel($page, 'WEB');
        $wholesale = self::audienceGroup($page, 'wholesale');
        $page->addChannel($web);
        $page->addAudienceGroup($wholesale);
        $page->setAudienceMode(AudienceMode::Only);

        $copy = clone $page;

        self::assertSame([$web], $copy->getChannels()->getValues());
        self::assertSame([$wholesale], $copy->getAudienceGroups()->getValues());
        self::assertTrue($copy->getAudience()->equals(Audience::only('wholesale')));

        $b2b = self::channel($copy, 'B2B');
        $copy->removeChannel($web);
        $copy->addChannel($b2b);
        $copy->removeAudienceGroup($wholesale);
        $copy->setAudienceMode(AudienceMode::Everyone);

        self::assertSame([$b2b], $copy->getChannels()->getValues());
        self::assertSame([$web], $page->getChannels()->getValues());
        self::assertSame([$wholesale], $page->getAudienceGroups()->getValues());
        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));
    }

    /**
     * @test
     */
    public function its_translations_must_be_entry_translations(): void
    {
        $entry = new class() extends Entry {
            protected function createTranslation(): TranslationInterface
            {
                return new class() extends AbstractTranslation {
                };
            }
        };
        $entry->setCurrentLocale('en_US');

        $this->expectException(\LogicException::class);

        $entry->getTranslation();
    }

    private static function channel(Page $page, string $code): EntryChannel
    {
        return ChannelsAndAudience::channelFactory()->createForEntry($page, $code);
    }

    private static function audienceGroup(Page $page, string $code): EntryAudienceGroup
    {
        return ChannelsAndAudience::audienceGroupFactory()->createForEntry($page, $code);
    }
}
