<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates one field's values as publish does, whether the field is a block's, a layout's or an entry's: required,
 * the field type handler's validate(), and the Symfony Validator constraints on its property. See "Validation" in
 * docs/spec/publishing.md
 *
 * @internal
 */
final readonly class FieldValidator
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param class-string $class the class whose property declares the field, and carries its constraints
     * @param string|null $block the key of the block the field is in, or null for an entry's field
     *
     * @return list<ValidationError>
     */
    public function validateShared(string $class, FieldMetadata $field, mixed $value, ?string $block = null): array
    {
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
            $handler = $this->fieldHandlers->get($field->field);
            $violations = $this->validator->validate($value, new Callback(
                static fn (mixed $value, ExecutionContextInterface $context) => $handler->validate($field->field, $value, $context),
            ));

            if (0 === count($violations)) {
                $violations = $this->validator->validatePropertyValue($class, $field->name, $value);
            }
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
