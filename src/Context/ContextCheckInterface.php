<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * Holds what depends on host-page context against the context an entry provides or a snippet declares.
 * See "Snippets and their context" and "Mismatches" in docs/spec/slots.md
 */
interface ContextCheckInterface
{
    /**
     * What doesn't fit the context the entry provides, which publish refuses: what needs a key it doesn't provide, and
     * for a snippet, a placement in a slot that doesn't provide every key it declares
     *
     * @param EntryInterface $workingCopy the entry as it would be published
     *
     * @return list<ValidationError> at the block it's in, or else in the entry field context
     */
    public function mismatches(EntryInterface $workingCopy): array;

    /**
     * What's in the way of changing a snippet's declared context from one list of keys to another: what needs a key the
     * change removes, and a placement in a slot that doesn't provide a key it adds
     *
     * @param EntryInterface $workingCopy the snippet's working copy, with whatever else the same save changes
     * @param list<string> $from the context keys it declared
     * @param list<string> $to the context keys it would declare
     *
     * @return list<ValidationError> in the entry field context, empty when the change can be made
     */
    public function blockers(EntryInterface $workingCopy, array $from, array $to): array;
}
