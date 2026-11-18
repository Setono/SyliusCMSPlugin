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
     * @param EntryInterface $entry the entry, whose rows hold its published content. Some checks compare with that, such
     *                              as the Embed code block's while setono_sylius_cms.embed_code is off
     * @param EntryInterface $workingCopy the entry as it would be published: its working copy, which for an entry that
     *                                    was never published is the entry itself
     *
     * @return list<ValidationError> empty when it can be published
     */
    public function validate(EntryInterface $entry, EntryInterface $workingCopy): array;
}
