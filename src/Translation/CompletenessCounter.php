<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Problem\EntryCounterInterface;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;

/**
 * Stores the locales each entry's working copy is complete in, whenever its problems are counted, so the Overview's
 * Translations and the lists' translation filter read them in SQL. See "Completeness and outdated flags" in
 * docs/spec/localization.md
 *
 * @internal
 */
final readonly class CompletenessCounter implements EntryCounterInterface
{
    public function __construct(
        private CompleteLocales $completeLocales,
        private CompletenessQueryInterface $query,
    ) {
    }

    public function count(array $workingCopies): void
    {
        $this->query->store(array_map($this->completeLocales->of(...), $workingCopies));
    }
}
