<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * Reads and writes the on-save settings as JSON, in the shape of the export format's "settings"
 * (docs/spec/transfer.md#export-format). Encode the result with CanonicalJson
 *
 * @internal
 */
final class EntrySettingsNormalizer
{
    private const DATE_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    public function normalize(EntrySettings $settings): ListedObject
    {
        return new ListedObject([
            'enabled' => $settings->enabled,
            'showFrom' => $settings->dateWindow->from?->format('Y-m-d\TH:i:s\Z'),
            'showUntil' => $settings->dateWindow->until?->format('Y-m-d\TH:i:s\Z'),
            'channels' => $settings->channels,
            'audience' => new ListedObject([
                'mode' => $settings->audience->mode->value,
                'groups' => $settings->audience->groups,
            ]),
            'homepageOf' => $settings->homepageOf,
            'position' => $settings->position,
        ]);
    }

    /**
     * Reads the settings $data holds onto $current: a setting it leaves out keeps its current value
     *
     * @throws InvalidEntrySettingsException
     */
    public function denormalize(mixed $data, EntrySettings $current): EntrySettings
    {
        if (!is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new InvalidEntrySettingsException('The settings must be a JSON object');
        }

        return new EntrySettings(
            array_key_exists('enabled', $data) ? self::enabled($data['enabled']) : $current->enabled,
            self::dateWindow(
                array_key_exists('showFrom', $data) ? self::dateTime('showFrom', $data['showFrom']) : $current->dateWindow->from,
                array_key_exists('showUntil', $data) ? self::dateTime('showUntil', $data['showUntil']) : $current->dateWindow->until,
            ),
            array_key_exists('channels', $data) ? self::channelCodes('channels', $data['channels']) : $current->channels,
            array_key_exists('audience', $data) ? self::audience($data['audience']) : $current->audience,
            array_key_exists('homepageOf', $data) ? self::channelCodes('homepageOf', $data['homepageOf']) : $current->homepageOf,
            array_key_exists('position', $data) ? self::position($data['position']) : $current->position,
        );
    }

    private static function position(mixed $value): ?int
    {
        return null === $value || (is_int($value) && $value >= 0) ? $value : throw new InvalidEntrySettingsException('"position" must be an integer from 0, or null');
    }

    private static function enabled(mixed $value): bool
    {
        return is_bool($value) ? $value : throw new InvalidEntrySettingsException('"enabled" must be true or false');
    }

    private static function dateTime(string $key, mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $error = sprintf('"%s" must be a date and time such as 2026-11-27T00:00:00Z, or null', $key);
        if (!is_string($value) || 1 !== preg_match(self::DATE_TIME, $value)) {
            throw new InvalidEntrySettingsException($error);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new InvalidEntrySettingsException($error, previous: $e);
        }
    }

    private static function dateWindow(?\DateTimeImmutable $from, ?\DateTimeImmutable $until): DateWindow
    {
        try {
            return new DateWindow($from, $until);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidEntrySettingsException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return list<string>
     */
    private static function channelCodes(string $key, mixed $value): array
    {
        $error = sprintf('"%s" must be a list of channel codes', $key);
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidEntrySettingsException($error);
        }

        $codes = [];
        foreach ($value as $code) {
            if (!is_string($code) || '' === $code) {
                throw new InvalidEntrySettingsException($error);
            }

            $codes[] = $code;
        }

        return $codes;
    }

    private static function audience(mixed $value): Audience
    {
        $error = '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values';

        $mode = is_array($value) && is_string($value['mode'] ?? null) ? AudienceMode::tryFrom($value['mode']) : null;
        $groups = is_array($value) ? $value['groups'] ?? null : null;
        if (null === $mode || !is_array($groups) || !array_is_list($groups)) {
            throw new InvalidEntrySettingsException($error);
        }

        $codes = [];
        foreach ($groups as $group) {
            $codes[] = is_string($group) ? $group : throw new InvalidEntrySettingsException($error);
        }

        try {
            return new Audience($mode, $codes);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidEntrySettingsException($e->getMessage(), previous: $e);
        }
    }
}
