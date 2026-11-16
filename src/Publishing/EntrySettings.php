<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifierInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

/**
 * An entry's on-save settings: they take effect as soon as they're saved, outside the draft. The position (#265) joins
 * them with its ticket. See "What takes effect on save" in docs/spec/publishing.md
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
     */
    public function __construct(
        public bool $enabled,
        public DateWindow $dateWindow,
        iterable $channels,
        public Audience $audience,
        iterable $homepageOf = [],
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
        );
    }

    /**
     * The channels, the audience and the homepages are rows beside the entry, so they change through the modifiers, which
     * create the rows. Only a page has homepages, and a channel has one homepage, so take the page's channels off the
     * pages that are their homepages now first (HomepageModifierInterface::takeOffOtherPages())
     */
    public function applyTo(EntryInterface $entry, ChannelsAndAudienceModifierInterface $channelsAndAudience, HomepageModifierInterface $homepages): void
    {
        $entry->setEnabled($this->enabled);
        $entry->setDateWindow($this->dateWindow);
        $channelsAndAudience->setChannels($entry, $this->channels);
        $channelsAndAudience->setAudience($entry, $this->audience);

        if ($entry instanceof PageInterface) {
            $homepages->setHomepageOf($entry, $this->homepageOf);
        }
    }

    /**
     * What differs from $before, as EntrySettingsChanged lists it
     *
     * @return list<EntrySettingsChanged::ENABLED|EntrySettingsChanged::DATE_WINDOW|EntrySettingsChanged::CHANNELS|EntrySettingsChanged::AUDIENCE|EntrySettingsChanged::HOMEPAGE>
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
