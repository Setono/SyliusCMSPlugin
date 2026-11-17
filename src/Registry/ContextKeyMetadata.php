<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A context key as the code declares it under setono_sylius_cms.context_keys: its name, the class of the object it
 * holds and its label. See "Context keys" in docs/spec/slots.md
 */
final readonly class ContextKeyMetadata
{
    /**
     * @param string $key such as product
     * @param class-string $class the class or interface of the object it holds
     * @param string $label a translation key, naming the pages that provide it, such as "Product page"
     */
    public function __construct(
        public string $key,
        public string $class,
        public string $label,
    ) {
    }

    /**
     * Whether the value is an object this key holds, which a slot can take from what it's passed
     */
    public function holds(mixed $value): bool
    {
        return $value instanceof $this->class;
    }
}
