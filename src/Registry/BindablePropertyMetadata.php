<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * A bindable property as its #[AsBindableProperty], or the bindable_properties config, declares it, with the fields of
 * its parameters class
 */
final readonly class BindablePropertyMetadata
{
    /** What a property of the channel reads in place of a context key */
    public const CHANNEL = 'channel';

    /** What a property of the signed-in customer reads in place of a context key */
    public const CUSTOMER = 'customer';

    /** The keys a property reads that are available everywhere, so never context keys a snippet declares */
    public const EVERYWHERE = [self::CHANNEL, self::CUSTOMER];

    /**
     * @param string $contextKey the context key whose object it reads, or "channel" or "customer"
     * @param string $label a translation key
     * @param class-string|null $params its parameters class, or null when it has none
     * @param array<string, FieldMetadata> $fields the parameters editors fill in, by property name
     */
    public function __construct(
        public string $contextKey,
        public string $code,
        public string $label,
        public BindingType $type,
        public ?string $params,
        public array $fields,
        public BindablePropertyInterface $property,
    ) {
    }

    /**
     * What a binding stores: "<context key>.<code>", such as product.name
     */
    public function binding(): string
    {
        return sprintf('%s.%s', $this->contextKey, $this->code);
    }

    /**
     * The context keys it needs the entry to provide or the snippet to declare: none for the channel's and the
     * customer's, which are available everywhere
     *
     * @return list<string>
     */
    public function needs(): array
    {
        return self::needsFor($this->contextKey);
    }

    /**
     * The context keys a property of the key needs: the key itself, or none for the channel's and the customer's
     *
     * @return list<string>
     */
    public static function needsFor(string $contextKey): array
    {
        return in_array($contextKey, self::EVERYWHERE, true) ? [] : [$contextKey];
    }

    /**
     * Whether a field that accepts these binding types can show it
     *
     * @param list<BindingType> $types
     */
    public function fits(array $types): bool
    {
        return in_array($this->type, $types, true);
    }
}
