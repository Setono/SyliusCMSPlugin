<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * The Overview's Needs attention: the errors and warnings across the store, grouped by kind. The entries that need
 * attention are found as the lists' Needs attention filter finds them, from the counts stored with each entry, so it
 * goes through no other entry, and only the problems of the ones edited last are found, up to a limit. See "Overview"
 * and "Problems" in docs/spec/admin.md
 */
final readonly class NeedsAttention
{
    /** How many entries' problems the panel finds at most */
    public const LIMIT = 50;

    public function __construct(
        private EntryRepositoryInterface $entries,
        private ProblemCountProviderInterface $problemCounts,
        private ProblemFinderInterface $problemFinder,
        private int $limit = self::LIMIT,
    ) {
    }

    public function find(): NeedsAttentionGroups
    {
        $queryBuilder = $this->entries->createListQueryBuilder();
        $entry = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere($this->problemCounts->getNeedsAttentionCondition($queryBuilder, $entry))
            ->addOrderBy(sprintf('%s.lastEditedAt', $entry), 'DESC')
            ->addOrderBy(sprintf('%s.id', $entry), 'DESC')
        ;

        /** @var Pagerfanta<EntryInterface> $paginator */
        $paginator = new Pagerfanta(new QueryAdapter($queryBuilder));
        $paginator->setMaxPerPage($this->limit);

        $entries = iterator_to_array($paginator->getCurrentPageResults(), false);
        $problems = [];
        foreach ($entries as $needingAttention) {
            $problems = [...$problems, ...$this->problemFinder->findByEntry($needingAttention)];
        }

        return new NeedsAttentionGroups(ProblemGroup::byKind($problems), $entries, $paginator->getNbResults());
    }
}
