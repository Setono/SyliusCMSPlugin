<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The slots the code declares under setono_sylius_cms.slots, the stock slots first. Editors fill slots but never create
 * them. See "Slots" in docs/spec/slots.md
 */
interface SlotRegistryInterface
{
    public function has(string $identifier): bool;

    /**
     * @throws UnknownSlotException
     */
    public function get(string $identifier): SlotMetadata;

    /**
     * Every slot, in the order the config declares them
     *
     * @return list<SlotMetadata>
     */
    public function all(): array;

    /**
     * The slots that provide at least these context keys, where a snippet that needs them can be placed, in the order
     * the config declares them
     *
     * @param list<string> $keys
     *
     * @return list<SlotMetadata>
     */
    public function providing(array $keys): array;
}
