<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Where an entry shows, which the Where it shows section of its overview page gets through cms_admin_where_it_shows():
 * for each of the store's channels and each of its locales, Live, or the first reason shoppers don't see the entry
 * there, in the order the spec lists them. It reads the entry's rows, which hold what shoppers see (ADR 0009), and the
 * reasons are the conditions of the visible-entries query, read from the same rules in PHP: the entry's channels,
 * Availability for its translations, its enabled switch, whether it's published and its date window, and then the route
 * check. The audience depends on the shopper, so it isn't a reason: the section shows it below the grid.
 * See "Entry overview page" in docs/spec/admin.md and "When an entry shows" in docs/spec/content-model.md
 */
final readonly class WhereItShows
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private Availability $availability,
        private TakenUrlFinderInterface $takenUrls,
        private ClockInterface $clock,
    ) {
    }

    public function of(EntryRow $row): WhereItShowsGrid
    {
        $entry = $row->entry;
        $channelCodes = $entry->getChannelCodes();
        $nowhere = $this->whyNowhere($row);
        $takenUrls = null === $nowhere ? $this->takenUrls($entry) : [];

        $localeCodes = $channels = [];
        foreach ($this->channelRepository->findBy([], ['id' => 'ASC']) as $channel) {
            $channelCode = $channel->getCode() ?? '';

            $cells = [];
            foreach (self::localesOf($channel) as $localeCode) {
                $localeCodes[$localeCode] = $localeCode;
                $takenUrl = $takenUrls[$channelCode][$localeCode] ?? null;

                $cells[$localeCode] = match (true) {
                    !in_array($channelCode, $channelCodes, true) => new WhereItShowsCell(WhereItShowsCell::NOT_IN_CHANNEL),
                    !$this->availability->hasTranslation($entry, $localeCode) => new WhereItShowsCell(WhereItShowsCell::NO_TRANSLATION),
                    !$this->availability->showsIn($entry, $localeCode) => new WhereItShowsCell(WhereItShowsCell::HELD_BACK),
                    null !== $nowhere => $nowhere,
                    null !== $takenUrl => new WhereItShowsCell(WhereItShowsCell::HIDDEN_BY_ROUTE, ['route' => $takenUrl->route]),
                    default => new WhereItShowsCell(WhereItShowsCell::LIVE),
                };
            }

            $channels[] = new WhereItShowsChannel($channelCode, $channel->getName() ?? $channelCode, $channel->isEnabled(), $cells);
        }

        return new WhereItShowsGrid(array_values($localeCodes), $channels);
    }

    /**
     * Why the entry shows in none of its channels and locales, whatever its translations: disabled, not published yet
     * or publishes at a time, ended, or shows from a date. Null when none of these applies
     */
    private function whyNowhere(EntryRow $row): ?WhereItShowsCell
    {
        $entry = $row->entry;
        $now = $this->clock->now();

        if (!$entry->isEnabled()) {
            return new WhereItShowsCell(WhereItShowsCell::DISABLED);
        }

        if (!$entry->isPublished()) {
            $scheduledPublish = $row->scheduledPublish;

            return null !== $scheduledPublish && EntryStatus::PUBLISHES_AT_A_TIME === EntryStatus::ofScheduledPublish($scheduledPublish, $now)
                ? new WhereItShowsCell(WhereItShowsCell::PUBLISHES_AT_A_TIME, ['at' => $scheduledPublish->getPublishAt()])
                : new WhereItShowsCell(WhereItShowsCell::NOT_PUBLISHED_YET);
        }

        $window = $entry->getDateWindow();
        if ($window->contains($now)) {
            return null;
        }

        // Outside the window, it has either ended, or not started yet. "Show until" is exclusive
        return null !== $window->until && $window->until <= $now
            ? new WhereItShowsCell(WhereItShowsCell::ENDED, ['until' => $window->until])
            : new WhereItShowsCell(WhereItShowsCell::SHOWS_FROM_A_DATE, ['from' => $window->from]);
    }

    /**
     * The entry's live URLs that another route takes, by channel and locale
     *
     * @return array<string, array<string, TakenUrl>>
     */
    private function takenUrls(EntryInterface $entry): array
    {
        $taken = [];
        foreach ($this->takenUrls->findLive($entry) as $takenUrl) {
            $taken[$takenUrl->channelCode ?? ''][$takenUrl->localeCode] = $takenUrl;
        }

        return $taken;
    }

    /**
     * @return list<string> the channel's locales, its default locale first and then the others by code
     */
    private static function localesOf(ChannelInterface $channel): array
    {
        $localeCodes = [];
        foreach ($channel->getLocales() as $locale) {
            $localeCodes[] = $locale->getCode() ?? '';
        }

        $default = $channel->getDefaultLocale()?->getCode();
        usort($localeCodes, static fn (string $a, string $b): int => [$a !== $default, $a] <=> [$b !== $default, $b]);

        return $localeCodes;
    }
}
