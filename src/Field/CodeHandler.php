<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Code fields: a string kept exactly as it was entered. It's never sanitised, because what it holds is printed
 * unescaped on purpose
 */
final class CodeHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'string'];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?string
    {
        return is_string($value) ? $value : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return is_string($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || is_string($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_code')->addViolation();
    }
}
