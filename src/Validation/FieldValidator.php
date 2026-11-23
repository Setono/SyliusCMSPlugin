<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Binding\BindingValidator;
use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Field\LinkLocaleParts;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates one field's values as publish does, whether the field is a block's, a layout's or an entry's: required,
 * the field type handler's validate(), and the Symfony Validator constraints on its property. A bound field has a value in
 * every locale, so it's never missing: its binding is checked, and its fallback, when it has one, as the field's value.
 * See "Validation" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class FieldValidator
{
    public function __construct(
        private FieldConstraints $constraints,
        private FallbackValues $fallbackValues,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
        private BindingValidator $bindingValidator,
    ) {
    }

    /**
     * Validates the stored values of the fields one class declares, a block type's, a layout's or a condition kind's
     * parameters': each by its field, a translatable one in each locale, then together against the constraints on the
     * class, with validateTogether()
     *
     * @param class-string $class the class whose properties declare the fields, and carry their constraints
     * @param array<string, FieldMetadata> $fields by name
     * @param array<string, mixed> $values the stored values by field name, a translatable field's as a map from locale
     *                                     code to value
     * @param list<string>|null $locales the locales translatable fields are validated in, or null when every value is
     *                                   shared by all translations, as a condition's parameter values are
     * @param string|null $block the key of the block the fields are in
     *
     * @return list<ValidationError>
     */
    public function validateFields(string $class, array $fields, array $values, ?array $locales, ?string $block = null): array
    {
        $errors = [];
        foreach ($fields as $name => $field) {
            $stored = $values[$name] ?? null;
            $errors = [...$errors, ...(null !== $locales && $field->translatable
                ? $this->validateTranslatable($class, $field, $stored, $locales, $block)
                : $this->validateShared($class, $field, $stored, $block))];
        }

        return [...$errors, ...$this->validateTogether($class, $fields, $values, $locales ?? [], $block)];
    }

    /**
     * @param class-string $class the class whose property declares the field, and carries its constraints
     * @param string|null $block the key of the block the field is in, or null for an entry's field
     *
     * @return list<ValidationError>
     */
    public function validateShared(string $class, FieldMetadata $field, mixed $value, ?string $block = null): array
    {
        if (BoundValue::isBound($field, $value)) {
            return $this->validateBound($class, $field, $value, [], $block);
        }

        return array_map(
            static fn (string|\Stringable $message): ValidationError => new ValidationError($block, $field->name, null, $message),
            $this->validateValue($class, $field, $value),
        );
    }

    /**
     * A translatable field is validated in each locale, with the value that renders there: its own, or the one its
     * fallback chain finds. Each error is reported in the locale whose value it is, once
     *
     * @param class-string $class the class whose property declares the field, and carries its constraints
     * @param mixed $stored the field's map from locale code to value
     * @param list<string> $locales the locales it's validated in
     * @param string|null $block the key of the block the field is in, or null for an entry's field
     *
     * @return list<ValidationError>
     */
    public function validateTranslatable(string $class, FieldMetadata $field, mixed $stored, array $locales, ?string $block = null): array
    {
        if (BoundValue::isBound($field, $stored)) {
            return $this->validateBound($class, $field, $stored, $locales, $block);
        }

        $errors = [];
        $found = [];
        foreach ($locales as $locale) {
            [$source, $value] = $this->resolve($field, $stored, $locale);
            foreach ($this->validateValue($class, $field, $value) as $message) {
                $error = new ValidationError($block, $field->name, $source, $message);

                // The same error can be found again through another locale's fallback chain
                if (!in_array([$source, $error->message], $found, true)) {
                    $found[] = [$source, $error->message];
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Checks the constraints on the class itself, which read several of its fields together, such as ExactlyOneOf.
     * They get the class's FieldValues in each locale, or once when none of its fields is translatable. An error found
     * in every locale is reported once, without a locale, and one found in some locales in each of them. A violation
     * at a property path is reported at that field, and one without at the block
     *
     * @param class-string $class the class whose attributes carry the constraints
     * @param array<string, FieldMetadata> $fields by name
     * @param array<string, mixed> $stored the stored values by field name, a translatable field's as a map from locale
     *                                     code to value
     * @param list<string> $locales the locales the translatable fields are checked in
     * @param string|null $block the key of the block the fields are in
     *
     * @return list<ValidationError>
     */
    public function validateTogether(string $class, array $fields, array $stored, array $locales, ?string $block = null): array
    {
        $constraints = $this->validator->getMetadataFor($class)->getConstraints();
        if ([] === $constraints) {
            return [];
        }

        $labels = array_map(fn (FieldMetadata $field): string => $this->translator->trans($field->field->label), $fields);
        // A link keeps its label per locale inside its one value
        $translatable = [] !== array_filter($fields, static fn (FieldMetadata $field): bool => $field->translatable || LinkLocaleParts::areKeptBy($field->field));
        $checkedIn = $translatable && [] !== $locales ? $locales : [null];

        /** @var array<string, array{field: string|null, message: string|\Stringable, locales: list<string|null>}> $found */
        $found = [];
        foreach ($checkedIn as $locale) {
            $values = [];
            foreach ($fields as $name => $field) {
                foreach ($this->rendered($field, $stored[$name] ?? null, $locale) as $value) {
                    $values[$name] = $value;
                }
            }

            $fallbackLocales = null === $locale ? [] : $this->fallbackValues->localesFor($locale);
            foreach ($this->validator->validate(new FieldValues($values, $labels, $locale, $fallbackLocales), $constraints) as $violation) {
                $path = $violation->getPropertyPath();
                $key = $path . "\0" . $violation->getMessage();
                $found[$key] ??= ['field' => '' === $path ? null : $path, 'message' => $violation->getMessage(), 'locales' => []];
                $found[$key]['locales'][] = $locale;
            }
        }

        $errors = [];
        foreach ($found as ['field' => $field, 'message' => $message, 'locales' => $foundIn]) {
            foreach (count($foundIn) === count($checkedIn) ? [null] : $foundIn as $locale) {
                $errors[] = new ValidationError($block, $field, $locale, $message);
            }
        }

        return $errors;
    }

    /**
     * The value the field renders in the locale, as stored, when it has one that isn't missing and fits its field type:
     * a shared field's own, and a translatable field's first along the locale's fallback chain
     *
     * @return iterable<mixed> the value, or nothing
     */
    private function rendered(FieldMetadata $field, mixed $stored, ?string $locale): iterable
    {
        // A binding renders a value in every locale, or its fallback
        if (BoundValue::isBound($field, $stored)) {
            yield $stored;

            return;
        }

        if (!$field->translatable) {
            if (!$this->fallbackValues->isMissing($field->field, $stored) && $this->fallbackValues->fits($field->field, $stored)) {
                yield $stored;
            }

            return;
        }

        if (null === $locale) {
            return;
        }

        foreach ($this->fallbackValues->inFallbackOrder($field->field, $stored, $locale) as $value) {
            if ($this->fallbackValues->fits($field->field, $value)) {
                yield $value;

                return;
            }
        }
    }

    /**
     * A bound field's binding, reported without a locale, and its fallback, which isn't required, as the field's value: a
     * translatable field's in each locale, with the value that renders there
     *
     * @param class-string $class
     * @param list<string> $locales the locales a translatable field's fallback is validated in
     *
     * @return list<ValidationError>
     */
    private function validateBound(string $class, FieldMetadata $field, mixed $stored, array $locales, ?string $block): array
    {
        $binding = BoundValue::of($field, $stored);
        if (null === $binding) {
            return [new ValidationError($block, $field->name, null, $this->translator->trans('setono_sylius_cms.field.not_binding', [], 'validators'))];
        }

        $violations = $this->validator->validate($binding, new Callback(
            fn (StoredBinding $value, ExecutionContextInterface $context) => $this->bindingValidator->validate($value, $field->type->bindable, $context),
        ));
        $errors = array_map(static fn (string|\Stringable $message): ValidationError => new ValidationError($block, $field->name, null, $message), self::messages($violations));

        if (!$field->translatable) {
            $fallback = $binding->fallback;
            if ($this->fallbackValues->isMissing($field->field, $fallback)) {
                return $errors;
            }

            return [...$errors, ...array_map(static fn (string|\Stringable $message): ValidationError => new ValidationError($block, $field->name, null, $message), $this->validateValue($class, $field, $fallback))];
        }

        $found = [];
        foreach ($locales as $locale) {
            [$source, $value] = $this->resolve($field, $binding->fallback, $locale);
            if ($this->fallbackValues->isMissing($field->field, $value)) {
                continue;
            }

            foreach ($this->validateValue($class, $field, $value) as $message) {
                $error = new ValidationError($block, $field->name, $source, $message);
                if (!in_array([$source, $error->message], $found, true)) {
                    $found[] = [$source, $error->message];
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * The value a translatable field renders in a locale, and the locale it's from: the first value the fallback
     * chain finds that isn't missing, or null in the locale itself
     *
     * @return array{string, mixed}
     */
    private function resolve(FieldMetadata $field, mixed $stored, string $locale): array
    {
        foreach ($this->fallbackValues->inFallbackOrder($field->field, $stored, $locale) as $source => $value) {
            return [$source, $value];
        }

        return [$locale, null];
    }

    /**
     * A missing value fails a required field, and otherwise goes on to the constraints as null. A value that's there
     * goes through the field type handler's validation first, and to the constraints only when the handler accepts it
     *
     * @param class-string $class
     *
     * @return list<string|\Stringable> the messages
     */
    private function validateValue(string $class, FieldMetadata $field, mixed $value): array
    {
        if ($this->fallbackValues->isMissing($field->field, $value)) {
            if ($field->field->required) {
                return [$this->translator->trans('setono_sylius_cms.field.required', [], 'validators')];
            }

            $violations = $this->validator->validatePropertyValue($class, $field->name, null);
        } else {
            $violations = $this->constraints->validate($class, $field, $value);
        }

        return self::messages($violations);
    }

    /**
     * @return list<string|\Stringable>
     */
    private static function messages(ConstraintViolationListInterface $violations): array
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }
}
