<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;

/**
 * A snippet's declared context is drafted (docs/spec/content-model.md), and its placements take effect on save
 * (ADR 0005). So a placement must fit both what its rows declare, which it shows with until the next publish, and what
 * its working copy declares, which a change of it is held against
 */
final readonly class DeclaredContext implements DeclaredContextInterface
{
    public function __construct(private WorkingCopyProviderInterface $workingCopies)
    {
    }

    public function toPlace(SnippetInterface $snippet): array
    {
        $workingCopy = $this->workingCopies->getWorkingCopy($snippet);
        $drafted = $workingCopy instanceof SnippetInterface ? $workingCopy->getContext() : [];

        return array_values(array_unique([...$snippet->getContext(), ...$drafted]));
    }
}
