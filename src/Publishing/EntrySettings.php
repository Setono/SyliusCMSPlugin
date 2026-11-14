<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * An entry's on-save settings: they take effect as soon as they're saved, outside the draft. "Homepage of" (#237) and
 * the position (#265) join them with their tickets. See "What takes effect on save" in docs/spec/publishing.md
 */
final readonly class EntrySettings
{
    /**
     * Channel codes, sorted and without duplicates
     *
     * @var list<string>
     */
    public array $channels;

    /**
     * @param iterable<string> $channels channel codes
     */
    public function __construct(
        public bool $enabled,
        public DateWindow $dateWindow,
        iterable $channels,
        public Audience $audience,
    ) {
        $codes = array_values(array_unique([...$channels]));
        sort($codes, \SORT_STRING);
        $this->channels = $codes;
    }

    public static function of(EntryInterface $entry): self
    {
        return new self($entry->isEnabled(), $entry->getDateWindow(), $entry->getChannelCodes(), $entry->getAudience());
    }

    public function applyTo(EntryInterface $entry): void
    {
        $entry->setEnabled($this->enabled);
        $entry->setDateWindow($this->dateWindow);
        $entry->setChannelCodes($this->channels);
        $entry->setAudience($this->audience);
    }

    /**
     * What differs from $before, as EntrySettingsChanged lists it
     *
     * @return list<EntrySettingsChanged::ENABLED|EntrySettingsChanged::DATE_WINDOW|EntrySettingsChanged::CHANNELS|EntrySettingsChanged::AUDIENCE>
     */
    public function changesFrom(self $before): array
    {
        $changes = [];

        if ($this->enabled !== $before->enabled) {
            $changes[] = EntrySettingsChanged::ENABLED;
        }

        if (!$this->dateWindow->equals($before->dateWindow)) {
            $changes[] = EntrySettingsChanged::DATE_WINDOW;
        }

        if ($this->channels !== $before->channels) {
            $changes[] = EntrySettingsChanged::CHANNELS;
        }

        if (!$this->audience->equals($before->audience)) {
            $changes[] = EntrySettingsChanged::AUDIENCE;
        }

        return $changes;
    }
}
