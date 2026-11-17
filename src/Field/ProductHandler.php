<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Product fields: a product's code, hydrated as the product from the references loaded before rendering, which
 * hold only the products the shopper may see. One the shopper may not see, or that was deleted, hydrates as missing
 */
final class ProductHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'string', 'minLength' => 1];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?ProductInterface
    {
        $product = Codes::isCode($value) ? $context->references->get(ReferenceType::PRODUCT, $value) : null;

        return $product instanceof ProductInterface ? $product : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return Codes::isCode($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return Codes::references(ReferenceType::PRODUCT, [$value]);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || Codes::isCode($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_product')->addViolation();
    }
}
