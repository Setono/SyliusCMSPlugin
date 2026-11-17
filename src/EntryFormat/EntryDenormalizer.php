<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EntryFormat;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Copies an entry in the entry format onto the entry: what's drafted, which is its shared fields, its translations with
 * their fields, shown switches and translation state, and its block tree. Publishing uses it to copy a draft onto the rows. See
 * "Entry format" in docs/spec/content-model.md.
 *
 * The UUID, the content type and createdAt identify the entry and aren't drafted, so they're checked, not copied.
 * The content type's own fields come with their ticket
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

        $shared = self::sharedFields(self::map($data['fields'] ?? [], 'The fields'), EntryFields::shared($contentType));
        $translations = self::map($data['translations'] ?? null, 'The translations');
        // Translations are indexed by locale
        foreach ($entry->getTranslations()->toArray() as $localeCode => $translation) {
            if (!array_key_exists($localeCode, $translations)) {
                $entry->removeTranslation($translation);
            }
        }

        foreach ($translations as $localeCode => $translationData) {
            $translationData = self::map($translationData, sprintf('The translation %s', $localeCode));
            $values = self::values($translationData, $localeCode, EntryFields::translatable($contentType));
            $shown = $translationData['shown'] ?? null;
            if (!is_bool($shown)) {
                throw new InvalidEntryFormatException(sprintf('The shown switch of the translation %s must be true or false', $localeCode));
            }

            $translation = $entry->getTranslations()->get($localeCode);
            if (!$translation instanceof EntryTranslationInterface) {
                $translation = $contentType->createTranslation($localeCode);
                $entry->addTranslation($translation);
            }

            foreach ($values as $field => $value) {
                EntryFields::write($translation, $field, $value);
            }
            $translation->setShown($shown);
            $translation->setTranslated(self::translated($translationData['translated'] ?? [], $localeCode));
        }

        foreach ($shared as $field => $value) {
            EntryFields::writeShared($entry, $field, $value);
        }

        try {
            $entry->setBlockTree((new StoredTreeSerializer())->denormalize($data['tree'] ?? null));
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidEntryFormatException(sprintf('The tree is invalid: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * The values of these translatable entry fields in each translation of an entry in the entry format, by locale,
     * without copying anything onto an entry: what the admin's lists read of a draft (ADR 0009). A translation that
     * isn't in the format is left out, so the others are read
     *
     * @param list<string> $fields such as title and slug
     *
     * @return array<string, array<string, string|null>> locale => field => its value
     *
     * @throws InvalidEntryFormatException when the data isn't an entry with translations
     */
    public static function translationValues(mixed $data, array $fields): array
    {
        $translations = self::map(self::map($data, 'An entry')['translations'] ?? null, 'The translations');

        $values = [];
        foreach ($translations as $localeCode => $translationData) {
            try {
                $values[$localeCode] = self::values(self::map($translationData, sprintf('The translation %s', $localeCode)), $localeCode, $fields);
            } catch (InvalidEntryFormatException) {
                continue;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $translationData
     * @param list<string> $fields
     *
     * @return array<string, string|null>
     *
     * @throws InvalidEntryFormatException
     */
    private static function values(array $translationData, string $localeCode, array $fields): array
    {
        $data = self::map($translationData['fields'] ?? null, sprintf('The fields of the translation %s', $localeCode));

        $values = [];
        foreach ($fields as $field) {
            $values[$field] = self::nullableString($data, $field, $localeCode);
        }

        return $values;
    }

    /**
     * The values of the content type's shared base fields, such as "Hide from search engines" and a snippet's context. An
     * entry without one, such as a draft saved before the SEO fields came, has its default: off, or no context keys
     *
     * @param array<string, mixed> $data
     * @param list<string> $fields
     *
     * @return array<string, bool|list<string>>
     *
     * @throws InvalidEntryFormatException
     */
    private static function sharedFields(array $data, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            try {
                $values[$field] = EntryFields::sharedValue($field, $data[$field] ?? null);
            } catch (\InvalidArgumentException $e) {
                throw new InvalidEntryFormatException(sprintf('The field %s %s', $field, $e->getMessage()), previous: $e);
            }
        }

        return $values;
    }

    /**
     * A translation's translation state: field name => the locale its value was translated from and the fingerprint of
     * that value
     *
     * @return array<string, array{from: string, fingerprint: string}>
     *
     * @throws InvalidEntryFormatException
     */
    private static function translated(mixed $data, string $localeCode): array
    {
        $translated = [];
        foreach (self::map($data, sprintf('The translation state of the translation %s', $localeCode)) as $field => $source) {
            $source = self::map($source, sprintf('The translation state of the %s of the translation %s', $field, $localeCode));
            $from = $source['from'] ?? null;
            $fingerprint = $source['fingerprint'] ?? null;
            if (!is_string($from) || '' === $from || !is_string($fingerprint) || !Fingerprint::isValid($fingerprint)) {
                throw new InvalidEntryFormatException(sprintf('The translation state of the %s of the translation %s must have the locale it was translated from and a fingerprint', $field, $localeCode));
            }

            $translated[$field] = ['from' => $from, 'fingerprint' => $fingerprint];
        }

        return $translated;
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
