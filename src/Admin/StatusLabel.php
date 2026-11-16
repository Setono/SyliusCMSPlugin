<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * One of the labels the admin shows an entry's state and on-save settings with. See "Status labels" in
 * docs/spec/admin.md. The admin renders each code with its own template,
 * `@SetonoSyliusCMSPlugin/admin/status_label/<code>.html.twig`, which gets the label as `label`
 */
final readonly class StatusLabel
{
    /** Published. A published entry with a draft also gets UNPUBLISHED_CHANGES */
    public const PUBLISHED = 'published';

    public const UNPUBLISHED_CHANGES = 'unpublished_changes';

    public const NEVER_PUBLISHED = 'never_published';

    /** A scheduled publish that waits. Parameters: `at`, a \DateTimeImmutable in UTC */
    public const PUBLISHES_AT_A_TIME = 'publishes_at_a_time';

    /** A scheduled publish whose time passed without it running. Parameters: `at`, a \DateTimeImmutable in UTC */
    public const SCHEDULED_PUBLISH_OVERDUE = 'scheduled_publish_overdue';

    /** A scheduled publish that was stopped when it ran. Parameters: `at`, a \DateTimeImmutable in UTC, and `reason` */
    public const SCHEDULED_PUBLISH_FAILED = 'scheduled_publish_failed';

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
