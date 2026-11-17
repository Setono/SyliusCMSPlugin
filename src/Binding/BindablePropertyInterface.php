<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A property of a context key's object, the channel or the customer that editors can bind a field to, such as the
 * current product's name. Register one with #[AsBindableProperty]. See "Bindable properties" in docs/spec/extending.md
 */
interface BindablePropertyInterface
{
    /**
     * The property's value, or null or '' when it's empty, which shows the binding's fallback
     *
     * @param object $subject the context key's object, the channel or the customer
     * @param object $params the hydrated parameters, or a NoParams for a property without parameters
     */
    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null;
}
