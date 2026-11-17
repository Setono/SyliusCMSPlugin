<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched once setono:cms:bestsellers:compute has replaced the bestseller scores of its channels and committed them.
 * Every Bestsellers list in those channels may have changed, and which entries hold one isn't known, so the change
 * can't be narrowed to records. See "Events" in docs/spec/extending.md
 */
final readonly class BestsellersComputed implements StorefrontChangedInterface
{
    /**
     * @param list<string> $channelCodes the channels whose scores it replaced
     */
    public function __construct(public array $channelCodes)
    {
    }

    public function getAffectedUuids(): ?array
    {
        return null;
    }
}
