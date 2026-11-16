<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Targeting\Audience;
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
    public function it_keeps_its_on_save_settings(): void
    {
        $page = new Page();
        $window = new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'));

        $page->setEnabled(false);
        $page->setDateWindow($window);
        $page->setAudience(Audience::except('wholesale', '@guest'), ChannelsAndAudience::audienceGroupFactory());
        $page->setChannelCodes(['WEB', 'B2B', 'WEB'], ChannelsAndAudience::channelFactory());

        self::assertFalse($page->isEnabled());
        self::assertTrue($page->getDateWindow()->equals($window));
        self::assertTrue($page->getAudience()->equals(Audience::except('@guest', 'wholesale')));
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());

        $page->setAudience(Audience::only('retail'), ChannelsAndAudience::audienceGroupFactory());
        $page->setChannelCodes(['WEB', 'MOBILE'], ChannelsAndAudience::channelFactory());
        $page->setDateWindow(new DateWindow());

        self::assertTrue($page->getAudience()->equals(Audience::only('retail')));
        self::assertSame(['MOBILE', 'WEB'], $page->getChannelCodes());
        self::assertTrue($page->getDateWindow()->equals(new DateWindow()));

        $page->setAudience(Audience::everyone(), ChannelsAndAudience::audienceGroupFactory());
        $page->setChannelCodes([], ChannelsAndAudience::channelFactory());

        self::assertTrue($page->getAudience()->equals(Audience::everyone()));
        self::assertSame([], $page->getChannelCodes());

        $page->setChannelCodes((static function (): \Generator {
            yield 'WEB';
            yield 'B2B';
        })(), ChannelsAndAudience::channelFactory());

        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
    }

    /**
     * Doctrine removes rows after it inserts them, so a code that stays must keep its row, or the insert would clash
     * with the row's unique key
     *
     * @test
     */
    public function it_keeps_the_rows_of_the_codes_that_stay_when_its_channels_and_groups_change(): void
    {
        $page = new Page();
        $page->setChannelCodes(['WEB', 'B2B'], ChannelsAndAudience::channelFactory());
        $page->setAudience(Audience::only('retail', 'wholesale'), ChannelsAndAudience::audienceGroupFactory());
        $web = self::rows($page, 'channels')['WEB'];
        $wholesale = self::rows($page, 'audienceGroups')['wholesale'];

        $page->setChannelCodes(['WEB', 'MOBILE'], ChannelsAndAudience::channelFactory());
        $page->setAudience(Audience::except('wholesale', '@guest'), ChannelsAndAudience::audienceGroupFactory());

        self::assertSame($web, self::rows($page, 'channels')['WEB']);
        self::assertSame(['MOBILE', 'WEB'], array_keys(self::rows($page, 'channels')));
        self::assertSame($wholesale, self::rows($page, 'audienceGroups')['wholesale']);
        self::assertSame(['@guest', 'wholesale'], array_keys(self::rows($page, 'audienceGroups')));
        self::assertSame($page, $web->getEntry());
        self::assertSame('WEB', $web->getCode());
        self::assertNull($web->getId());
        self::assertSame($page, $wholesale->getEntry());
        self::assertSame('wholesale', $wholesale->getCode());
        self::assertNull($wholesale->getId());
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
     * @test
     */
    public function it_clones_the_settings_and_leaves_the_originals_alone_when_the_clones_change(): void
    {
        $page = new Page();
        $page->setChannelCodes(['WEB'], ChannelsAndAudience::channelFactory());
        $page->setAudience(Audience::only('wholesale'), ChannelsAndAudience::audienceGroupFactory());

        $copy = clone $page;

        self::assertSame(['WEB'], $copy->getChannelCodes());
        self::assertTrue($copy->getAudience()->equals(Audience::only('wholesale')));

        $copy->setChannelCodes(['B2B'], ChannelsAndAudience::channelFactory());
        $copy->setAudience(Audience::except('retail'), ChannelsAndAudience::audienceGroupFactory());

        self::assertSame(['B2B'], $copy->getChannelCodes());
        self::assertTrue($copy->getAudience()->equals(Audience::except('retail')));
        self::assertSame($copy, self::rows($copy, 'channels')['B2B']->getEntry());
        self::assertSame($copy, self::rows($copy, 'audienceGroups')['retail']->getEntry());
        self::assertSame(['WEB'], $page->getChannelCodes());
        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));
    }

    /**
     * A model can't reach the factories that create rows, so a clone reads the original's rows. A row never changes
     *
     * @test
     */
    public function its_clone_reads_the_rows_of_the_original(): void
    {
        $page = new Page();
        $page->setChannelCodes(['WEB'], ChannelsAndAudience::channelFactory());
        $page->setAudience(Audience::only('wholesale'), ChannelsAndAudience::audienceGroupFactory());

        $copy = clone $page;

        self::assertSame(self::rows($page, 'channels'), self::rows($copy, 'channels'));
        self::assertSame(self::rows($page, 'audienceGroups'), self::rows($copy, 'audienceGroups'));
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

    /**
     * @param 'channels'|'audienceGroups' $property
     *
     * @return array<string, EntryChannelInterface|EntryAudienceGroupInterface> by code
     */
    private static function rows(Entry $entry, string $property): array
    {
        /** @var Collection<array-key, EntryChannelInterface|EntryAudienceGroupInterface> $rows */
        $rows = (new \ReflectionProperty(Entry::class, $property))->getValue($entry);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row->getCode()] = $row;
        }
        ksort($byCode);

        return $byCode;
    }
}
