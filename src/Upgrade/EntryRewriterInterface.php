<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Writes an entry's stored data back upgraded, for setono:cms:upgrade
 *
 * @internal
 */
interface EntryRewriterInterface
{
    /**
     * Upgrades the entry's rows and draft in place, without flushing
     */
    public function rewrite(EntryInterface $entry): EntryRewrite;
}
