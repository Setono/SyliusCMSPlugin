<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Stores a revision of what a publish made the content shoppers see: step 7 of "Publishing now" in
 * docs/spec/publishing.md. Every publish stores one: manual, scheduled, or after an import
 */
interface RevisionRecorderInterface
{
    /**
     * Stores the entry's rows, which hold its published content, as a revision. It's written on the next flush, so it
     * goes with the publish's transaction
     *
     * @param bool $scheduled whether a scheduled publish publishes it
     * @param string|null $publishedBy who published it, or null for the signed-in admin user. A scheduled publish runs in
     *                                 the worker, where no one is signed in, so it passes who scheduled it
     */
    public function record(EntryInterface $entry, \DateTimeImmutable $publishedAt, bool $scheduled = false, ?string $publishedBy = null): void;
}
