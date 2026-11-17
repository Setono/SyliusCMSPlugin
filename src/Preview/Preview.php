<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * What the builder's preview shows: the edited entry, in a channel and a locale, to a shopper (Shown to) at a time
 * (Date). A snippet shows in one of its placements, on the host page of a sample object, or on a plain page when it has
 * no placement. See "Preview mode" and "Snippet preview" in docs/spec/builder.md and "Shown to and Date" in
 * docs/spec/targeting.md.
 *
 * A preview of one of the entry's revisions shows the revision's content instead of the working copy, read-only.
 * A share link shows the entry's working copy read-only too, with the channel, locale, Shown to and Date it fixed when
 * it was made. See "Share links" in docs/spec/publishing.md
 */
final readonly class Preview
{
    /**
     * @param int|null $revisionId the revision it shows, or null for the entry's working copy
     * @param string|null $shownTo the customer group value it shows the page to: a customer group code, "@guest" or
     *                             "@no_group", or null for the shopper's own
     * @param \DateTimeImmutable|null $date the time it shows the page at, or null for now
     * @param bool $shared whether a share link shows it, rather than the builder
     * @param string|null $placementUuid the placement a snippet shows in, or null for a plain page
     * @param SampleObject|null $sample the host-page object a snippet shows with, or null for none
     */
    public function __construct(
        public string $entryUuid,
        public string $channelCode,
        public string $localeCode,
        public ?int $revisionId = null,
        public ?string $shownTo = null,
        public ?\DateTimeImmutable $date = null,
        public bool $shared = false,
        public ?string $placementUuid = null,
        public ?SampleObject $sample = null,
    ) {
    }

    /**
     * A revision's preview and a share link can't be edited, so they get no bridge
     */
    public function isReadOnly(): bool
    {
        return null !== $this->revisionId || $this->shared;
    }

    /**
     * share on a share link, otherwise builder
     *
     * @return RenderContext::PREVIEW_MODE_*
     */
    public function getMode(): string
    {
        return $this->shared ? RenderContext::PREVIEW_MODE_SHARE : RenderContext::PREVIEW_MODE_BUILDER;
    }
}
