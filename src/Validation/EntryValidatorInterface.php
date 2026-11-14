<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Validates an entry as publish does, before it publishes. See "Validation" in docs/spec/publishing.md
 */
interface EntryValidatorInterface
{
    /**
     * @param EntryInterface $entry the entry as it would be published: its working copy
     *
     * @return list<ValidationError> empty when it can be published
     */
    public function validate(EntryInterface $entry): array;
}
