<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * Context keys are data, declared in config, which the container hands this registry as the config holds them
 */
final class ContextKeyRegistry implements ContextKeyRegistryInterface
{
    /** @var array<string, ContextKeyMetadata>|null */
    private ?array $keys = null;

    /**
     * @param array<string, array{class: class-string, label: string}> $config setono_sylius_cms.context_keys: key => its
     *                                                                         class and label
     */
    public function __construct(private readonly array $config)
    {
    }

    public function has(string $key): bool
    {
        return isset($this->keys()[$key]);
    }

    public function get(string $key): ContextKeyMetadata
    {
        return $this->keys()[$key] ?? throw new UnknownContextKeyException($key);
    }

    public function all(): array
    {
        return array_values($this->keys());
    }

    /**
     * @return array<string, ContextKeyMetadata>
     */
    private function keys(): array
    {
        if (null === $this->keys) {
            $this->keys = [];
            foreach ($this->config as $key => $declared) {
                $this->keys[$key] = new ContextKeyMetadata($key, $declared['class'], $declared['label']);
            }
        }

        return $this->keys;
    }
}
