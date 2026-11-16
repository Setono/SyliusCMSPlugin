<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;

/**
 * Reads revisions the way all stored data is read: through the upgraders. See "Revisions" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class RevisionReader
{
    public function __construct(
        private StoredDataUpgraderInterface $upgrader,
        private EntryDenormalizer $entryDenormalizer,
    ) {
    }

    /**
     * The revision's content in the entry format, upgraded. Content that isn't a JSON object reads as an empty one,
     * which EntryDenormalizer refuses
     *
     * @throws \JsonException when the content isn't JSON
     */
    public function read(RevisionInterface $revision): Upgraded
    {
        $data = CanonicalJson::decode($revision->getContent());

        return is_array($data) ? $this->upgrader->upgradeEntry($data) : new Upgraded([]);
    }

    /**
     * A copy of the revision's entry that holds the revision's content, which Doctrine doesn't manage, as a working copy
     * is. It's what a preview of the revision shows
     *
     * @throws InvalidEntryFormatException
     */
    public function entry(RevisionInterface $revision): EntryInterface
    {
        $entry = clone $revision->getEntry();
        $this->entryDenormalizer->denormalize($this->read($revision)->data, $entry);

        return $entry;
    }
}
