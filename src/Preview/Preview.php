<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

/**
 * What the builder's preview shows: the edited entry, in a channel and a locale. Shown to, Date, the sample object
 * and the placement join it with their tickets. See "Preview mode" in docs/spec/builder.md.
 *
 * A preview of one of the entry's revisions shows the revision's content instead of the working copy, read-only
 */
final readonly class Preview
{
    /**
     * @param int|null $revisionId the revision it shows, or null for the entry's working copy
     */
    public function __construct(
        public string $entryUuid,
        public string $channelCode,
        public string $localeCode,
        public ?int $revisionId = null,
    ) {
    }

    /**
     * A revision's preview can't be edited, so it gets no bridge
     */
    public function isReadOnly(): bool
    {
        return null !== $this->revisionId;
    }
}
