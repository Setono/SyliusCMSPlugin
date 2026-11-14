<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * Every field attribute extends this class. See "Shipped field types" in docs/spec/extending.md
 */
abstract class Field
{
    /**
     * @param string $label a translation key
     * @param string|null $help a translation key
     * @param bool|null $translatable null means the field type's default
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly bool $required = false,
        public readonly ?bool $translatable = null,
    ) {
    }

    /**
     * The handler's service id. Defaults to the attribute's class name plus "Handler"
     */
    public function handledBy(): string
    {
        return static::class . 'Handler';
    }
}
