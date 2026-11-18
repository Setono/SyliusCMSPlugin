<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * Slots are data, declared in config, which the container hands this registry as the config holds them
 */
final class SlotRegistry implements SlotRegistryInterface
{
    /** @var array<string, SlotMetadata>|null */
    private ?array $slots = null;

    /**
     * @param array<string, array{label: string, provides: list<string>, cap: int|null, template_event?: array{name: string, priority: int}}> $config
     *        setono_sylius_cms.slots: identifier => the slot's declaration
     */
    public function __construct(private readonly array $config)
    {
    }

    public function has(string $identifier): bool
    {
        return isset($this->slots()[$identifier]);
    }

    public function find(string $identifier): ?SlotMetadata
    {
        return $this->slots()[$identifier] ?? null;
    }

    public function get(string $identifier): SlotMetadata
    {
        return $this->slots()[$identifier] ?? throw new UnknownSlotException($identifier);
    }

    public function all(): array
    {
        return array_values($this->slots());
    }

    public function providing(array $keys): array
    {
        return array_values(array_filter($this->slots(), static fn (SlotMetadata $slot): bool => $slot->providesAll($keys)));
    }

    /**
     * @return array<string, SlotMetadata>
     */
    private function slots(): array
    {
        if (null === $this->slots) {
            $this->slots = [];
            foreach ($this->config as $identifier => $slot) {
                $this->slots[$identifier] = new SlotMetadata(
                    $identifier,
                    $slot['label'],
                    $slot['provides'],
                    $slot['cap'],
                    $slot['template_event']['name'] ?? null,
                    $slot['template_event']['priority'] ?? 0,
                );
            }
        }

        return $this->slots;
    }
}
