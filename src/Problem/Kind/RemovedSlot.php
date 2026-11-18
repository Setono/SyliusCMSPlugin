<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A placement of a snippet in a slot the code no longer declares, a warning for each. The placement is kept and shows
 * nothing. It's fixed by removing it in the Placements section of the snippet's overview page. A project may remove a
 * slot on purpose, so setono:cms:lint doesn't report it. See "Mismatches" in docs/spec/slots.md and "Problems" in
 * docs/spec/admin.md
 */
final readonly class RemovedSlot implements ProblemKindInterface
{
    public const CODE = 'removed_slot';

    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        foreach ($this->placements->findBySnippet($entry) as $placement) {
            if ($this->slots->has($placement->getSlot())) {
                continue;
            }

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.removed_slot', ['%slot%' => $placement->getSlot()]),
                Fix::inPlacements($entry),
            );
        }
    }
}
