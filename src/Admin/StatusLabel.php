<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * One of the labels the admin shows an entry's state and on-save settings with. See "Status labels" in
 * docs/spec/admin.md. A label of the entry's state, or of its scheduled publish's, has the EntryStatus's value as its
 * code: a published entry with a draft gets Published and UnpublishedChanges, and a scheduled publish that waits,
 * is overdue or failed has `at`, a \DateTimeImmutable in UTC, and a failed one a `reason`. The admin renders each code
 * with its own template,
 * `@SetonoSyliusCMSPlugin/admin/status_label/<code>.html.twig`, which gets the label as `label`
 */
final readonly class StatusLabel
{
    public const DISABLED = 'disabled';

    /** A date window that hasn't ended. Parameters: `from` and `until`, each a \DateTimeImmutable in UTC or null */
    public const DATE_WINDOW = 'date_window';

    /** A date window that has ended. Parameters: `until`, a \DateTimeImmutable in UTC */
    public const ENDED = 'ended';

    /** Translations held back, which don't show. Parameters: `locales`, their locale codes in code order */
    public const HELD_BACK = 'held_back';

    /** An audience that isn't everyone. Parameters: `mode` (`only` or `except`) and `groups` (a list of customer group values) */
    public const AUDIENCE = 'audience';

    /**
     * @param array<string, mixed> $parameters what the label's template needs, which depends on the code
     */
    public function __construct(
        public string $code,
        public array $parameters = [],
    ) {
    }
}
