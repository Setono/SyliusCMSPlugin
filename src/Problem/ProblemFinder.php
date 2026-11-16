<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;

final readonly class ProblemFinder implements ProblemFinderInterface
{
    /**
     * @param iterable<ProblemKindInterface> $kinds every kind, the services tagged setono_sylius_cms.problem_kind
     */
    public function __construct(
        private iterable $kinds,
        private WorkingCopyProviderInterface $workingCopies,
    ) {
    }

    public function findByEntry(EntryInterface $entry): array
    {
        $workingCopy = $this->workingCopies->getWorkingCopy($entry);

        $errors = $warnings = [];
        foreach ($this->kinds as $kind) {
            foreach ($kind->findIn($workingCopy) as $problem) {
                if (Severity::Error === $problem->severity) {
                    $errors[] = $problem;
                } else {
                    $warnings[] = $problem;
                }
            }
        }

        return [...$errors, ...$warnings];
    }
}
