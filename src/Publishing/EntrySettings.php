<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * An entry's on-save settings: they take effect as soon as they're saved, outside the draft. See "What takes effect on
 * save" in docs/spec/publishing.md
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
     * "Homepage of": the codes of the channels a page is the homepage of, sorted and without duplicates. Empty for
     * every content type but Page
     *
     * @var list<string>
     */
    public array $homepageOf;

    /**
     * @param iterable<string> $channels channel codes
     * @param iterable<string> $homepageOf channel codes
     * @param int|null $position where it sits among the entries of a content type ordered by position, or null for an
     *                           entry of a content type ordered otherwise
     */
    public function __construct(
        public bool $enabled,
        public DateWindow $dateWindow,
        iterable $channels,
        public Audience $audience,
        iterable $homepageOf = [],
        public ?int $position = null,
    ) {
        $this->channels = self::codes($channels);
        $this->homepageOf = self::codes($homepageOf);
    }

    public static function of(EntryInterface $entry): self
    {
        return new self(
            $entry->isEnabled(),
            $entry->getDateWindow(),
            $entry->getChannelCodes(),
            $entry->getAudience(),
            $entry instanceof PageInterface ? $entry->getHomepageChannelCodes() : [],
            $entry->getPosition(),
        );
    }

    /**
     * What differs from $before, as EntrySettingsChanged lists it
     *
     * @return list<EntrySettingsChanged::ENABLED|EntrySettingsChanged::DATE_WINDOW|EntrySettingsChanged::CHANNELS|EntrySettingsChanged::AUDIENCE|EntrySettingsChanged::HOMEPAGE|EntrySettingsChanged::POSITION>
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

        if ($this->homepageOf !== $before->homepageOf) {
            $changes[] = EntrySettingsChanged::HOMEPAGE;
        }

        if ($this->position !== $before->position) {
            $changes[] = EntrySettingsChanged::POSITION;
        }

        return $changes;
    }

    /**
     * @param iterable<string> $codes
     *
     * @return list<string> sorted, without duplicates
     */
    private static function codes(iterable $codes): array
    {
        $codes = array_unique([...$codes]);
        sort($codes, \SORT_STRING);

        return $codes;
    }
}
