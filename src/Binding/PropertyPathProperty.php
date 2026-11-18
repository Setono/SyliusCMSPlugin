<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A bindable property that's a plain property path on its subject, such as brand.name on a product, declared in
 * setono_sylius_cms.bindable_properties. A path that doesn't reach a value, such as through a product without a brand,
 * is empty, and so is a value that isn't a string or a number. See "Bindable properties" in
 * docs/spec/extending.md
 */
final readonly class PropertyPathProperty implements BindablePropertyInterface
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private string $path,
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
    }

    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null
    {
        try {
            $value = $this->propertyAccessor->getValue($subject, $this->path);
        } catch (AccessException|UnexpectedTypeException) {
            return null;
        }

        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
