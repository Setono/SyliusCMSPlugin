<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;

/**
 * Asks the visible-entries query for the snippets, so a slot follows the same visibility as every other place an entry
 * shows
 */
final readonly class VisibleSnippets implements VisibleSnippetsInterface
{
    /** The code of the built-in content type */
    private const CONTENT_TYPE = 'snippet';

    public function __construct(private VisibleEntriesQueryInterface $visibleEntries)
    {
    }

    public function get(array $uuids, RenderContext $context): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<mixed> $entries */
        $entries = $this->visibleEntries->createQueryBuilder(self::CONTENT_TYPE, $context)
            ->andWhere('entry.uuid IN (:snippets)')
            ->setParameter('snippets', $uuids)
            ->getQuery()
            ->getResult()
        ;

        $snippets = [];
        foreach ($entries as $entry) {
            if ($entry instanceof SnippetInterface) {
                $snippets[$entry->getUuid()] = $entry;
            }
        }

        return $snippets;
    }
}
