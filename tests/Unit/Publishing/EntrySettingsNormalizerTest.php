<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * The settings in the shape the export format gives them (docs/spec/transfer.md#export-format)
 */
final class EntrySettingsNormalizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_writes_the_settings_with_the_keys_in_the_order_the_spec_lists_them(): void
    {
        $settings = new EntrySettings(
            false,
            new DateWindow(new \DateTimeImmutable('2026-11-27T01:00:00+01:00'), new \DateTimeImmutable('2026-12-01T00:00:00Z')),
            ['WEB', 'B2B'],
            Audience::only('wholesale', '@guest'),
        );

        self::assertSame(
            '{"enabled":false,"showFrom":"2026-11-27T00:00:00Z","showUntil":"2026-12-01T00:00:00Z","channels":["B2B","WEB"],"audience":{"mode":"only","groups":["@guest","wholesale"]}}',
            CanonicalJson::encode((new EntrySettingsNormalizer())->normalize($settings)),
        );
    }

    /**
     * @test
     */
    public function it_writes_an_open_date_window_and_an_audience_of_everyone_as_null_and_an_empty_list(): void
    {
        self::assertSame(
            '{"enabled":true,"showFrom":null,"showUntil":null,"channels":[],"audience":{"mode":"everyone","groups":[]}}',
            CanonicalJson::encode((new EntrySettingsNormalizer())->normalize(self::current())),
        );
    }

    /**
     * @test
     */
    public function it_reads_every_setting(): void
    {
        $settings = (new EntrySettingsNormalizer())->denormalize([
            'enabled' => false,
            'showFrom' => '2026-11-27T01:00:00+01:00',
            'showUntil' => '2026-12-01T00:00:00.500Z',
            'channels' => ['WEB', 'B2B'],
            'audience' => ['mode' => 'except', 'groups' => ['retail', '@no_group']],
        ], self::current());

        self::assertFalse($settings->enabled);
        self::assertTrue($settings->dateWindow->equals(new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'))));
        self::assertSame(['B2B', 'WEB'], $settings->channels);
        self::assertTrue($settings->audience->equals(Audience::except('@no_group', 'retail')));
    }

    /**
     * @test
     */
    public function settings_left_out_keep_their_current_values(): void
    {
        $current = new EntrySettings(false, new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z')), ['WEB'], Audience::only('wholesale'));
        $normalizer = new EntrySettingsNormalizer();

        self::assertEquals($current, $normalizer->denormalize([], $current));

        $onlyFrom = $normalizer->denormalize(['showFrom' => null], $current);
        self::assertTrue($onlyFrom->dateWindow->equals(new DateWindow(null, new \DateTimeImmutable('2026-12-01T00:00:00Z'))));
        self::assertFalse($onlyFrom->enabled);
        self::assertSame(['WEB'], $onlyFrom->channels);
        self::assertTrue($onlyFrom->audience->equals(Audience::only('wholesale')));

        $onlyUntil = $normalizer->denormalize(['showUntil' => null, 'enabled' => true], $current);
        self::assertTrue($onlyUntil->dateWindow->equals(new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'))));
        self::assertTrue($onlyUntil->enabled);
    }

    /**
     * @test
     *
     * @dataProvider invalidData
     */
    public function it_refuses_what_it_cannot_read(mixed $data, string $error): void
    {
        $this->expectException(InvalidEntrySettingsException::class);
        $this->expectExceptionMessage($error);

        (new EntrySettingsNormalizer())->denormalize($data, self::current());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidData(): iterable
    {
        yield 'a list' => [['WEB'], 'The settings must be a JSON object'];
        yield 'a string' => ['WEB', 'The settings must be a JSON object'];
        yield 'enabled as a string' => [['enabled' => 'true'], '"enabled" must be true or false'];
        yield 'enabled as null' => [['enabled' => null], '"enabled" must be true or false'];
        yield 'show from without a time zone' => [['showFrom' => '2026-11-27T00:00:00'], '"showFrom" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'show from as a date only' => [['showFrom' => '2026-11-27'], '"showFrom" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'show from with text around it' => [['showFrom' => 'x2026-11-27T00:00:00Zx'], '"showFrom" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'show until as a number' => [['showUntil' => 1764547200], '"showUntil" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'show until on a day that does not exist' => [['showUntil' => '2026-13-45T00:00:00Z'], '"showUntil" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'an end before the start' => [['showFrom' => '2026-12-01T00:00:00Z', 'showUntil' => '2026-11-27T00:00:00Z'], '"Show until" (2026-11-27T00:00:00Z) must come after "Show from" (2026-12-01T00:00:00Z)'];
        yield 'channels as a string' => [['channels' => 'WEB'], '"channels" must be a list of channel codes'];
        yield 'channels as a map' => [['channels' => ['web' => 'WEB']], '"channels" must be a list of channel codes'];
        yield 'an empty channel code' => [['channels' => ['WEB', '']], '"channels" must be a list of channel codes'];
        yield 'a channel code that is no string' => [['channels' => [7]], '"channels" must be a list of channel codes'];
        yield 'an audience that is no object' => [['audience' => 'everyone'], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an audience without groups' => [['audience' => ['mode' => 'only']], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an audience without a mode' => [['audience' => ['groups' => []]], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an unknown audience mode' => [['audience' => ['mode' => 'some', 'groups' => []]], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'audience groups as a map' => [['audience' => ['mode' => 'only', 'groups' => ['a' => 'wholesale']]], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an audience group that is no string' => [['audience' => ['mode' => 'only', 'groups' => [7]]], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an audience group that is no customer group value' => [['audience' => ['mode' => 'only', 'groups' => ['@admin']]], '"@admin" is neither a customer group code, "@guest" nor "@no_group"'];
        yield 'groups for everyone' => [['audience' => ['mode' => 'everyone', 'groups' => ['wholesale']]], 'An audience of everyone has no groups'];
    }

    private static function current(): EntrySettings
    {
        return new EntrySettings(true, new DateWindow(), [], Audience::everyone());
    }
}
