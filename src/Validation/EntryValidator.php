<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Context\ContextCheckInterface;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates the content type's fields as block fields are validated: its shared fields on the entry, and its
 * translatable fields on its translations, in each of the entry's locales. Then it checks the Symfony Validator
 * constraints on the entry's and its translations' other properties, checks its URLs, and validates the block tree in
 * each locale. Last, it holds the tree's Embed code blocks against the entry's published tree. A translation without a
 * title is no translation (docs/spec/localization.md), such as one whose adding was undone in the builder, so it isn't
 * validated, unless the entry has none with a title. What needs host-page context the entry doesn't provide, or a
 * snippet doesn't declare, is refused too, as is a snippet's placement in a slot that doesn't provide what it declares
 */
final readonly class EntryValidator implements EntryValidatorInterface
{
    public function __construct(
        private BlockTreeValidator $blockTreeValidator,
        private FieldValidator $fieldValidator,
        private FieldReader $fieldReader,
        private ContentTypeRegistryInterface $contentTypes,
        private ValidatorInterface $validator,
        private EntryUrlValidatorInterface $urlValidator,
        private Availability $availability,
        private EmbedCodeValidator $embedCodeValidator,
        private ContextCheckInterface $contextCheck,
    ) {
    }

    public function validate(EntryInterface $entry, EntryInterface $workingCopy): array
    {
        $errors = [...$this->validateWorkingCopy($workingCopy), ...$this->contextCheck->mismatches($workingCopy)];

        // A never-published entry is its own working copy, and its rows hold what it would publish, not what's published
        $publishedTree = $entry->isPublished() ? $entry->getBlockTree() : new StoredTree(null, []);

        return [...$errors, ...$this->embedCodeValidator->validate($workingCopy->getBlockTree(), $publishedTree)];
    }

    /**
     * @return list<ValidationError>
     */
    private function validateWorkingCopy(EntryInterface $entry): array
    {
        $contentType = $this->contentTypes->getByEntry($entry);
        $translations = $this->translationsOf($entry);
        $locales = array_keys($translations);

        $fields = $this->fieldReader->readContentType($contentType->modelClass, false);
        $translationClass = $contentType->translationModelClass;
        $translationFields = null === $translationClass ? [] : $this->fieldReader->readContentType($translationClass, true);

        $errors = $this->validateFields($entry, $translations, $contentType->modelClass, $fields);
        if (null !== $translationClass) {
            $errors = [...$errors, ...$this->validateFields($entry, $translations, $translationClass, $translationFields)];
        }

        $errors = [...$errors, ...$this->violations($entry, null, array_keys($fields))];
        foreach ($translations as $locale => $translation) {
            $errors = [...$errors, ...$this->violations($translation, $locale, array_keys($translationFields))];
        }

        return [...$errors, ...$this->urlValidator->validate($entry), ...$this->blockTreeValidator->validate($entry->getBlockTree(), $contentType->code, $locales)];
    }

    /**
     * The entry's translations that are translations, those with a title, or all of them when none has one, by locale
     *
     * @return array<string, EntryTranslationInterface>
     */
    private function translationsOf(EntryInterface $entry): array
    {
        $translations = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            $translations[$localeCode] = $translation;
        }
        ksort($translations);

        $titled = array_filter($translations, fn (string $localeCode): bool => $this->availability->hasTranslation($entry, $localeCode), \ARRAY_FILTER_USE_KEY);

        return [] === $titled ? $translations : $titled;
    }

    /**
     * Validates the fields one class of the content type declares: a shared field's value on the entry, and a
     * translatable field's values on the translations
     *
     * @param array<string, object> $translations by locale
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     *
     * @return list<ValidationError>
     */
    private function validateFields(EntryInterface $entry, array $translations, string $class, array $fields): array
    {
        $errors = [];
        foreach ($fields as $name => $field) {
            $errors = [...$errors, ...($field->translatable
                ? $this->fieldValidator->validateTranslatable($class, $field, array_map(static fn (object $translation): mixed => self::value($translation, $class, $name), $translations), array_keys($translations))
                : $this->fieldValidator->validateShared($class, $field, self::value($entry, $class, $name)))];
        }

        return $errors;
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
     * @param class-string $class the class that declares the field, which the object is
     */
    private static function value(object $object, string $class, string $property): mixed
    {
        return (new \ReflectionProperty($class, $property))->getValue($object);
    }
}
