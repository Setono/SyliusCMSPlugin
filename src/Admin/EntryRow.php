<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;

/**
 * An entry as a row of the admin's lists shows it, and the header of its overview page. The title and URL are its
 * working copy's: its draft's, when it has one (ADR 0009). Last edited is read from the entry. See "Entry lists" and
 * "Entry overview page" in docs/spec/admin.md
 */
final readonly class EntryRow
{
    /**
     * @param string|null $title the title in the locale shown: the admin user's, or else the store's default, or else the first
     * @param string|null $url the path of its URL in that locale, or null when it has no URL of its own or no slug
     * @param list<StatusLabel> $statusLabels
     * @param string $status the entry's state, an EntryStatus: never published, published, or with unpublished changes
     * @param DraftInterface|null $draft the entry's draft, loaded with its page, or null when it has none
     * @param ScheduledPublishInterface|null $scheduledPublish the entry's scheduled publish, loaded with its page, or
     *                                                        null when it has none
     * @param string|null $scheduledPublishStatus its state, an EntryStatus: publishes at a time, overdue or failed, or
     *                                           null when there's none
     * @param ProblemCount $problems its errors and warnings, as the lists' problem marker shows them
     */
    public function __construct(
        public EntryInterface $entry,
        public ContentTypeMetadata $contentType,
        public ?string $title,
        public ?string $url,
        public array $statusLabels,
        public string $status,
        public ?DraftInterface $draft = null,
        public ?ScheduledPublishInterface $scheduledPublish = null,
        public ?string $scheduledPublishStatus = null,
        public ProblemCount $problems = new ProblemCount(),
    ) {
    }
}
