<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EntryFormat;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Copies an entry in the entry format onto the entry: what's drafted, which is its translations with their fields and
 * shown switches, and its block tree. Publishing uses it to copy a draft onto the rows. See "Entry format" in
 * docs/spec/content-model.md.
 *
 * The UUID, the content type and createdAt identify the entry and aren't drafted, so they're checked, not copied.
 * Translation state and the content type's own fields come with their tickets
 *
 * @internal
 */
final readonly class EntryDenormalizer
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
    }

    /**
     * @param mixed $data the entry in the entry format, decoded with CanonicalJson::decode()
     *
     * @throws InvalidEntryFormatException
     */
    public function denormalize(mixed $data, EntryInterface $entry): void
    {
        $data = self::map($data, 'An entry');
        $contentType = $this->contentTypes->getByEntry($entry);

        if (($data['uuid'] ?? null) !== $entry->getUuid() || ($data['contentType'] ?? null) !== $contentType->code) {
            throw new InvalidEntryFormatException(sprintf('The data is not the entry %s of the content type "%s"', $entry->getUuid(), $contentType->code));
        }

        $translations = self::map($data['translations'] ?? null, 'The translations');
        // Translations are indexed by locale
        foreach ($entry->getTranslations()->toArray() as $localeCode => $translation) {
            if (!array_key_exists($localeCode, $translations)) {
                $entry->removeTranslation($translation);
            }
        }

        foreach ($translations as $localeCode => $translationData) {
            $translationData = self::map($translationData, sprintf('The translation %s', $localeCode));
            $fields = self::map($translationData['fields'] ?? null, sprintf('The fields of the translation %s', $localeCode));
            $title = self::nullableString($fields, 'title', $localeCode);
            $slug = self::nullableString($fields, 'slug', $localeCode);
            $shown = $translationData['shown'] ?? null;
            if (!is_bool($shown)) {
                throw new InvalidEntryFormatException(sprintf('The shown switch of the translation %s must be true or false', $localeCode));
            }

            $translation = $entry->getTranslations()->get($localeCode);
            if (!$translation instanceof EntryTranslationInterface) {
                $translation = $contentType->createTranslation($localeCode);
                $entry->addTranslation($translation);
            }

            $translation->setTitle($title);
            if ($contentType->hasOwnUrl()) {
                $translation->setSlug($slug);
            }
            $translation->setShown($shown);
        }

        try {
            $entry->setBlockTree((new StoredTreeSerializer())->denormalize($data['tree'] ?? null));
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidEntryFormatException(sprintf('The tree is invalid: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @throws InvalidEntryFormatException
     */
    private static function nullableString(array $fields, string $field, string $localeCode): ?string
    {
        $value = $fields[$field] ?? null;
        if (null !== $value && !is_string($value)) {
            throw new InvalidEntryFormatException(sprintf('The %s of the translation %s must be a string or null', $field, $localeCode));
        }

        return $value;
    }

    /**
     * A JSON object, decoded as an array that isn't a list, or as an empty stdClass when it's empty
     *
     * @return array<string, mixed>
     *
     * @throws InvalidEntryFormatException
     */
    private static function map(mixed $data, string $what): array
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }

        if (!is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new InvalidEntryFormatException(sprintf('%s must be a JSON object', $what));
        }

        /** @var array<string, mixed> $map */
        $map = $data;

        return $map;
    }
}
