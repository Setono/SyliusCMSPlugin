<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates the content type's fields as block fields are validated: its shared fields on the entry, and its
 * translatable fields on its translations, in each of the entry's locales. Then it checks the Symfony Validator
 * constraints on the entry's and its translations' other properties, and validates the block tree in each locale.
 * The slug checks come with #234
 */
final readonly class EntryValidator implements EntryValidatorInterface
{
    public function __construct(
        private BlockTreeValidator $blockTreeValidator,
        private FieldValidator $fieldValidator,
        private FieldReader $fieldReader,
        private ContentTypeRegistryInterface $contentTypes,
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(EntryInterface $entry): array
    {
        $contentType = $this->contentTypes->getByEntry($entry);
        $translations = $entry->getTranslations()->toArray();
        ksort($translations);
        $locales = array_map(strval(...), array_keys($translations));

        $errors = [];

        $entryClass = $contentType->modelClass;
        $fields = $this->fieldReader->readContentType($entryClass, false);
        foreach ($fields as $name => $field) {
            $errors = [...$errors, ...$this->fieldValidator->validateShared($entryClass, $field, self::value($entry, $entryClass, $name))];
        }

        $errors = [...$errors, ...$this->violations($entry, null, array_keys($fields))];

        $translationFields = [];
        $translationClass = $contentType->translationModelClass;
        if (null !== $translationClass) {
            $translationFields = $this->fieldReader->readContentType($translationClass, true);
            foreach ($translationFields as $name => $field) {
                $stored = array_map(static fn (object $translation): mixed => self::value($translation, $translationClass, $name), $translations);
                $errors = [...$errors, ...$this->fieldValidator->validateTranslatable($translationClass, $field, $stored, $locales)];
            }
        }

        foreach ($translations as $locale => $translation) {
            $errors = [...$errors, ...$this->violations($translation, (string) $locale, array_keys($translationFields))];
        }

        return [...$errors, ...$this->blockTreeValidator->validate($entry->getBlockTree(), $contentType->code, $locales)];
    }

    /**
     * The constraint violations on the object's properties, except the fields, whose constraints were checked already
     *
     * @param list<string> $fields
     *
     * @return list<ValidationError>
     */
    private function violations(object $object, ?string $locale, array $fields): array
    {
        $errors = [];
        foreach ($this->validator->validate($object) as $violation) {
            $field = $violation->getPropertyPath();
            if (!in_array($field, $fields, true)) {
                $errors[] = new ValidationError(null, '' === $field ? null : $field, $locale, $violation->getMessage());
            }
        }

        return $errors;
    }

    /**
     * A field's value, which a mapped property can hold privately
     *
     * @param class-string $class the class that declares the field
     */
    private static function value(object $object, string $class, string $property): mixed
    {
        return $object instanceof $class ? (new \ReflectionProperty($class, $property))->getValue($object) : null;
    }
}
