<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * Registers a bindable property: a service implementing Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface,
 * whose value editors can show in a field. A binding stores it as "<contextKey>.<code>". See "Bindable properties" in
 * docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsBindableProperty
{
    /**
     * @param string $contextKey the context key whose object it reads, such as "product", or "channel" or "customer"
     * @param string $code matches ^[a-z][a-z0-9_]*$, is unique per context key and never changes
     * @param string $label a translation key
     * @param BindingType $type what it resolves to, which decides the fields it can be bound to
     * @param class-string|null $params its parameters class, whose typed properties with field attributes are the
     *                                  parameters editors fill in, or null for none, when it gets a NoParams
     */
    public function __construct(
        public string $contextKey,
        public string $code,
        public string $label,
        public BindingType $type,
        public ?string $params = null,
    ) {
    }
}
