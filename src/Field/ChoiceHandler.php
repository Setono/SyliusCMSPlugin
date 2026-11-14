<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ChoiceHandler implements FieldHandlerInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function schema(Field $field): array
    {
        $field = self::choice($field);

        $one = ['type' => 'string', 'enum' => $field->values()];
        $schema = $field->multiple ? ['type' => 'array', 'items' => $one, 'uniqueItems' => true] : $one;

        $labels = [];
        foreach ($field->choices as $value => $label) {
            $labels[$value] = $this->translator->trans($label);
        }

        $schema['x-cms-options'] = ['choices' => $labels, 'multiple' => $field->multiple];

        return $schema;
    }

    /**
     * @return string|list<string>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): string|array|null
    {
        $field = self::choice($field);
        $values = $field->values();

        if ($field->multiple) {
            return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => in_array($item, $values, true))) : [];
        }

        return in_array($value, $values, true) ? $value : null;
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::choice($field);

        if (null === $value || self::fits($field, $value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.invalid_choice')->addViolation();
    }

    private static function fits(Choice $field, mixed $value): bool
    {
        $values = $field->values();

        if (!$field->multiple) {
            return in_array($value, $values, true);
        }

        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!in_array($item, $values, true)) {
                return false;
            }
        }

        return true;
    }

    private static function choice(Field $field): Choice
    {
        if (!$field instanceof Choice) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Choice::class, $field::class));
        }

        return $field;
    }
}
