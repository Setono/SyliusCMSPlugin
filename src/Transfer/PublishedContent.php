<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;

/**
 * The content of an entry an export takes: its rows, which hold its published content, or its content if it was never
 * published, never its draft. Its tree is upgraded to the code's versions, as rendering reads it. See "What a transfer
 * moves" in docs/spec/transfer.md
 *
 * @internal
 */
final readonly class PublishedContent
{
    public function __construct(private StoredDataUpgraderInterface $upgrader)
    {
    }

    /**
     * A copy of the entry that Doctrine doesn't manage, so the entry itself is left as it is
     */
    public function of(EntryInterface $entry): EntryInterface
    {
        $content = clone $entry;
        $content->setBlockTree($this->upgrader->readBlockTree($entry));

        return $content;
    }
}
