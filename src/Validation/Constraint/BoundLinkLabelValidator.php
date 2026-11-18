<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Field\LinkLocaleParts;
use Setono\SyliusCMSPlugin\Validation\FieldValues;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class BoundLinkLabelValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BoundLinkLabel) {
            throw new UnexpectedTypeException($constraint, BoundLinkLabel::class);
        }

        if (!$value instanceof FieldValues) {
            throw new UnexpectedValueException($value, FieldValues::class);
        }

        $link = LinkLocaleParts::normalized($value->get($constraint->field));
        if (!is_array($link) || !StoredBinding::isBinding($link['ref'] ?? null) || self::hasLabel($link['label'] ?? null, $value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($constraint->field)
            ->addViolation()
        ;
    }

    /**
     * Whether the link has a label in the values' locale: one string for every locale, or one along the locale's
     * fallback chain, or in any locale when the values have none
     */
    private static function hasLabel(mixed $label, FieldValues $values): bool
    {
        if (is_string($label)) {
            return '' !== $label;
        }

        if (!is_array($label)) {
            return false;
        }

        foreach (null === $values->locale ? array_keys($label) : $values->fallbackLocales as $locale) {
            $localized = $label[$locale] ?? null;
            if (is_string($localized) && '' !== $localized) {
                return true;
            }
        }

        return false;
    }
}
