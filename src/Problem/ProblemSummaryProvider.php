<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Counts the problems of every entry, which goes through every entry's working copy, so the count is kept in a cache
 * pool between requests: the Content badge shows on every admin page. ProblemSummaryListener drops it whenever an
 * entry, a translation, its channels or audience, or a draft is written, and the pool's lifetime (five minutes, by
 * default) bounds how long a problem from outside the CMS, such as a deleted product or a removed block type, takes to
 * show in the count. The overview page reads an entry's problems as they are now. See "Problems" in docs/spec/admin.md
 */
final class ProblemSummaryProvider implements ProblemSummaryProviderInterface, ResetInterface
{
    public const CACHE_KEY = 'summary';

    private ?ProblemSummary $summary = null;

    public function __construct(
        private readonly EntryRepositoryInterface $entries,
        private readonly ProblemFinderInterface $finder,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSummary(): ProblemSummary
    {
        return $this->summary ??= $this->cache->get(self::CACHE_KEY, $this->summarize(...));
    }

    public function clear(): void
    {
        $this->summary = null;
        $this->cache->delete(self::CACHE_KEY);
    }

    public function reset(): void
    {
        $this->summary = null;
    }

    private function summarize(): ProblemSummary
    {
        $entries = [];
        foreach ($this->entries->iterateAll() as $entry) {
            try {
                $count = ProblemCount::of($this->finder->findByEntry($entry));
            } catch (InvalidStoredTreeException|InvalidEntryFormatException) {
                // Content that can't be read at all fails where it's opened, not on every admin page
                continue;
            }

            if ($count->total() > 0) {
                $entries[$entry->getUuid()] = $count;
            }
        }

        return new ProblemSummary($entries);
    }
}
