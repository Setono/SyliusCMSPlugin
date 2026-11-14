<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TextHandler implements FieldHandlerInterface
{
    public function schema(Field $field): array
    {
        $field = self::text($field);

        $schema = ['type' => 'string'];
        if (null !== $field->maxLength) {
            $schema['maxLength'] = $field->maxLength;
        }

        $schema['x-cms-options'] = ['maxLength' => $field->maxLength];

        return $schema;
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?string
    {
        return is_string($value) ? $value : null;
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::text($field);

        if (null === $value) {
            return;
        }

        if (!is_string($value)) {
            $context->buildViolation('setono_sylius_cms.field.not_text')->addViolation();

            return;
        }

        if (null !== $field->maxLength && mb_strlen($value) > $field->maxLength) {
            $context->buildViolation('setono_sylius_cms.field.too_long')
                ->setParameter('{{ limit }}', (string) $field->maxLength)
                ->addViolation()
            ;
        }
    }

    private static function text(Field $field): Text
    {
        if (!$field instanceof Text) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Text::class, $field::class));
        }

        return $field;
    }
}
