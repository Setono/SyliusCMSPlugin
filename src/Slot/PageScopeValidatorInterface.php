<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * What a placement's page scope may hold. See "Page scopes" in docs/spec/slots.md
 */
interface PageScopeValidatorInterface
{
    /**
     * What's wrong with the page scope for a placement in the slot, none when it may be saved: each condition is in the
     * stored condition format, of a kind that reads the host page and whose needs the slot provides, with parameter
     * values its fields accept. A slot the code no longer declares takes only an empty scope
     *
     * @param array<mixed> $pageScope the conditions, in order
     *
     * @return list<TranslatableMessage>
     */
    public function validate(string $slot, array $pageScope): array;
}
