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
    public function it_reads_no_homepage_channels_of_an_entry_other_than_a_page(): void
    {
        self::assertSame([], EntrySettings::of(new Announcement())->homepageOf);
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
        self::assertSame(['position'], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone(), ['WEB'], 3))->changesFrom($before));
        self::assertSame(
            ['enabled', 'date_window', 'channels', 'audience', 'homepage', 'position'],
            (new EntrySettings(false, self::window(), [], Audience::except('retail'), position: 0))->changesFrom($before),
        );
    }

    /**
     * An entry of a content type ordered by position has one, which editors drag it into in the admin list
     *
     * @test
     */
    public function it_reads_the_position_of_an_entry(): void
    {
        $announcement = new Announcement();
        self::assertNull(EntrySettings::of($announcement)->position);

        $announcement->setPosition(4);
        self::assertSame(4, EntrySettings::of($announcement)->position);
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
