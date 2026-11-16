<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * A cell of the overview page's Where it shows grid, one channel and locale: Live, or why shoppers don't see the entry
 * there. Only the first reason that applies is given, in the order the constants list them. See "Entry overview page"
 * in docs/spec/admin.md and "When an entry shows" in docs/spec/content-model.md
 */
final readonly class WhereItShowsCell
{
    public const LIVE = 'live';

    public const NOT_IN_CHANNEL = 'not_in_channel';

    /** The entry has no translation in the locale: none with a title */
    public const NO_TRANSLATION = 'no_translation';

    public const HELD_BACK = 'held_back';

    public const DISABLED = 'disabled';

    /** The entry was never published, and no scheduled publish waits to publish it */
    public const NOT_PUBLISHED_YET = 'not_published_yet';

    /** The entry was never published, and a scheduled publish waits to publish it. Parameters: `at`, a \DateTimeImmutable in UTC */
    public const PUBLISHES_AT_A_TIME = 'publishes_at_a_time';

    /** The entry's date window has ended. Parameters: `until`, a \DateTimeImmutable in UTC */
    public const ENDED = 'ended';

    /** The entry's date window hasn't started. Parameters: `from`, a \DateTimeImmutable in UTC */
    public const SHOWS_FROM_A_DATE = 'shows_from_a_date';

    /** A Sylius or app route takes the entry's URL in the channel and locale. Parameters: `route`, the route's name */
    public const HIDDEN_BY_ROUTE = 'hidden_by_route';

    /**
     * @param array<string, mixed> $parameters what the cell's text needs, which depends on the code
     */
    public function __construct(
        public string $code,
        public array $parameters = [],
    ) {
    }
}
