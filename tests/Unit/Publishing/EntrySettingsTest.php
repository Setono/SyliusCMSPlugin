<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifier;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;

final class EntrySettingsTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_reads_the_on_save_settings_of_an_entry(): void
    {
        $page = new Page();
        $page->setEnabled(false);
        $page->setDateWindow(self::window());
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB', 'B2B']);
        ChannelsAndAudience::modifier()->setAudience($page, Audience::only('wholesale'));
        $this->homepages()->setHomepageOf($page, ['WEB', 'MOBILE']);

        $settings = EntrySettings::of($page);

        self::assertFalse($settings->enabled);
        self::assertTrue($settings->dateWindow->equals(self::window()));
        self::assertSame(['B2B', 'WEB'], $settings->channels);
        self::assertTrue($settings->audience->equals(Audience::only('wholesale')));
        self::assertSame(['MOBILE', 'WEB'], $settings->homepageOf);
    }

    /**
     * Only a page can be a channel's homepage. See "Homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function an_entry_other_than_a_page_is_the_homepage_of_no_channel(): void
    {
        self::assertSame([], EntrySettings::of(new Announcement())->homepageOf);
    }

    /**
     * @test
     */
    public function it_applies_them_to_an_entry_changing_its_channels_audience_and_homepages_through_the_modifiers(): void
    {
        $page = new Page();

        (new EntrySettings(false, self::window(), ['WEB', 'B2B', 'WEB'], Audience::except('retail'), ['MOBILE']))
            ->applyTo($page, ChannelsAndAudience::modifier(), $this->homepages());

        self::assertFalse($page->isEnabled());
        self::assertTrue($page->getDateWindow()->equals(self::window()));
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
        self::assertTrue($page->getAudience()->equals(Audience::except('retail')));
        self::assertSame(['MOBILE'], $page->getHomepageChannelCodes());
    }

    /**
     * @test
     */
    public function it_applies_the_other_settings_to_an_entry_other_than_a_page(): void
    {
        $announcement = new Announcement();

        (new EntrySettings(false, new DateWindow(), ['WEB'], Audience::everyone()))->applyTo($announcement, ChannelsAndAudience::modifier(), $this->homepages());

        self::assertFalse($announcement->isEnabled());
        self::assertSame(['WEB'], $announcement->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_keeps_its_channels_and_homepages_as_sorted_codes_without_duplicates(): void
    {
        $settings = new EntrySettings(true, new DateWindow(), ['WEB', 'B2B', 'WEB'], Audience::everyone(), ['WEB', 'MOBILE', 'WEB']);
        self::assertSame(['B2B', 'WEB'], $settings->channels);
        self::assertSame(['MOBILE', 'WEB'], $settings->homepageOf);

        $generated = new EntrySettings(true, new DateWindow(), self::generate('WEB', 'B2B'), Audience::everyone(), self::generate('WEB', 'MOBILE'));
        self::assertSame(['B2B', 'WEB'], $generated->channels);
        self::assertSame(['MOBILE', 'WEB'], $generated->homepageOf);

        self::assertSame([], (new EntrySettings(true, new DateWindow(), [], Audience::everyone()))->homepageOf, 'The homepage of no channel unless it says so');
    }

    /**
     * @return \Generator<int, string>
     */
    private static function generate(string ...$codes): \Generator
    {
        foreach ($codes as $code) {
            yield $code;
        }
    }

    /**
     * @test
     */
    public function it_lists_what_changed_in_the_order_the_spec_lists_them(): void
    {
        $before = new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone(), ['WEB']);

        self::assertSame([], $before->changesFrom($before));
        self::assertSame([], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone(), ['WEB']))->changesFrom($before));
        self::assertSame(['enabled'], (new EntrySettings(false, new DateWindow(), ['WEB'], Audience::everyone(), ['WEB']))->changesFrom($before));
        self::assertSame(['date_window'], (new EntrySettings(true, self::window(), ['WEB'], Audience::everyone(), ['WEB']))->changesFrom($before));
        self::assertSame(['channels'], (new EntrySettings(true, new DateWindow(), ['WEB', 'B2B'], Audience::everyone(), ['WEB']))->changesFrom($before));
        self::assertSame(['audience'], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::only('@guest'), ['WEB']))->changesFrom($before));
        self::assertSame(['homepage'], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone(), ['WEB', 'B2B']))->changesFrom($before));
        self::assertSame(['homepage'], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone()))->changesFrom($before));
        self::assertSame(
            ['enabled', 'date_window', 'channels', 'audience', 'homepage'],
            (new EntrySettings(false, self::window(), [], Audience::except('retail')))->changesFrom($before),
        );
    }

    private static function window(): DateWindow
    {
        return new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'));
    }

    private function homepages(): HomepageModifier
    {
        return Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal());
    }
}
