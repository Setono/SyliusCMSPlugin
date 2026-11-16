<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;

final class EntrySettingsTest extends TestCase
{
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

        $settings = EntrySettings::of($page);

        self::assertFalse($settings->enabled);
        self::assertTrue($settings->dateWindow->equals(self::window()));
        self::assertSame(['B2B', 'WEB'], $settings->channels);
        self::assertTrue($settings->audience->equals(Audience::only('wholesale')));
    }

    /**
     * @test
     */
    public function it_applies_them_to_an_entry_changing_its_channels_and_audience_through_the_modifier(): void
    {
        $page = new Page();

        (new EntrySettings(false, self::window(), ['WEB', 'B2B', 'WEB'], Audience::except('retail')))->applyTo($page, ChannelsAndAudience::modifier());

        self::assertFalse($page->isEnabled());
        self::assertTrue($page->getDateWindow()->equals(self::window()));
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
        self::assertTrue($page->getAudience()->equals(Audience::except('retail')));
    }

    /**
     * @test
     */
    public function it_keeps_its_channels_as_sorted_codes_without_duplicates(): void
    {
        self::assertSame(['B2B', 'WEB'], (new EntrySettings(true, new DateWindow(), ['WEB', 'B2B', 'WEB'], Audience::everyone()))->channels);
        self::assertSame(['B2B', 'WEB'], (new EntrySettings(true, new DateWindow(), self::generate('WEB', 'B2B'), Audience::everyone()))->channels);
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
        $before = new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone());

        self::assertSame([], $before->changesFrom($before));
        self::assertSame([], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::everyone()))->changesFrom($before));
        self::assertSame(['enabled'], (new EntrySettings(false, new DateWindow(), ['WEB'], Audience::everyone()))->changesFrom($before));
        self::assertSame(['date_window'], (new EntrySettings(true, self::window(), ['WEB'], Audience::everyone()))->changesFrom($before));
        self::assertSame(['channels'], (new EntrySettings(true, new DateWindow(), ['WEB', 'B2B'], Audience::everyone()))->changesFrom($before));
        self::assertSame(['audience'], (new EntrySettings(true, new DateWindow(), ['WEB'], Audience::only('@guest')))->changesFrom($before));
        self::assertSame(
            ['enabled', 'date_window', 'channels', 'audience'],
            (new EntrySettings(false, self::window(), [], Audience::except('retail')))->changesFrom($before),
        );
    }

    private static function window(): DateWindow
    {
        return new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'));
    }
}
