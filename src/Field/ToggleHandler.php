<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ToggleHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'boolean'];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return is_bool($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || is_bool($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_toggle')->addViolation();
    }
}
