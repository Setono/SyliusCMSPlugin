<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Checks the Symfony Validator constraints on the entry's properties (its shared fields) and on its translations'
 * (its translatable fields), and validates its block tree in each of its locales. The slug checks come with #234
 */
final readonly class EntryValidator implements EntryValidatorInterface
{
    public function __construct(
        private BlockTreeValidator $blockTreeValidator,
        private ContentTypeRegistryInterface $contentTypes,
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(EntryInterface $entry): array
    {
        $translations = $entry->getTranslations()->toArray();
        ksort($translations);

        $errors = $this->violations($entry, null);
        foreach ($translations as $locale => $translation) {
            $errors = [...$errors, ...$this->violations($translation, $locale)];
        }

        return [
            ...$errors,
            ...$this->blockTreeValidator->validate($entry->getBlockTree(), $this->contentTypes->getByEntry($entry)->code, array_keys($translations)),
        ];
    }

    /**
     * @return list<ValidationError>
     */
    private function violations(object $object, ?string $locale): array
    {
        $errors = [];
        foreach ($this->validator->validate($object) as $violation) {
            $field = $violation->getPropertyPath();
            $errors[] = new ValidationError(null, '' === $field ? null : $field, $locale, $violation->getMessage());
        }

        return $errors;
    }
}
