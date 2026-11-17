<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Resolves a binding against the host-page context, the channel or the customer: the bound property's value, as the
 * field takes it. It's empty when the code no longer has the property, the field doesn't accept its type, the page
 * doesn't have its context key's object (a guest has no customer), or the property gives null or ''. Then the binding's
 * fallback shows. See "Bindings" in docs/spec/slots.md and "Hydration" in docs/spec/rendering.md
 *
 * @internal
 */
final readonly class BindingResolver
{
    public function __construct(
        private BindablePropertyRegistryInterface $properties,
        private ParamsHydrator $paramsHydrator,
    ) {
    }

    /**
     * The property's value, or null when it's empty. A number in a field that accepts text is given as its string, as
     * such a field stores it
     *
     * @param StoredBinding $binding the property and its stored parameter values; its fallback is the field's to show
     * @param list<BindingType> $accepts the binding types the field accepts
     */
    public function resolve(StoredBinding $binding, array $accepts, HydrationContext $context): string|int|float|null
    {
        if (!$this->properties->has($binding->property)) {
            return null;
        }

        $metadata = $this->properties->get($binding->property);
        if (!$metadata->fits($accepts)) {
            return null;
        }

        $subject = self::subject($metadata->contextKey, $context->renderContext);
        if (null === $subject) {
            return null;
        }

        $value = $metadata->property->resolve(
            $subject,
            $this->paramsHydrator->hydrate($metadata->params, $metadata->fields, $binding->params, $context),
            $context->renderContext,
        );

        if (null === $value || '' === $value) {
            return null;
        }

        return !is_string($value) && in_array(BindingType::Text, $accepts, true) ? (string) $value : $value;
    }

    /**
     * What the property reads: the channel, the customer, or the context key's object on the page
     */
    private static function subject(string $contextKey, RenderContext $context): ?object
    {
        return match ($contextKey) {
            'channel' => $context->channel,
            'customer' => $context->customer,
            default => $context->get($contextKey),
        };
    }
}
