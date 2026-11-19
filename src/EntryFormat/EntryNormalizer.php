<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EntryFormat;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an entry in the entry format, a contract shared by drafts, revisions and exports.
 * See "Entry format" in docs/spec/content-model.md. Encode the result with CanonicalJson
 *
 * @internal
 */
final readonly class EntryNormalizer
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private ContentTypeFields $contentTypeFields = new ContentTypeFields(new FieldReader()),
    ) {
    }

    public function normalize(EntryInterface $entry): ListedObject
    {
        $contentType = $this->contentTypes->getByEntry($entry);

        $translations = [];
        foreach ($entry->getTranslations()->getKeys() as $localeCode) {
            $translation = $entry->getTranslation($localeCode);

            $fields = [];
            foreach (EntryFields::translatable($contentType) as $field) {
                $fields[$field] = EntryFields::read($translation, $field);
            }

            $fields = StoredSource::canonicalFields($fields + $this->ownFields($translation, $this->contentTypeFields->translatable($contentType)));

            $translations[$localeCode] = new ListedObject([
                'shown' => $translation->isShown(),
                'fields' => $fields,
                'translated' => (object) array_map(
                    static fn (array $source): ListedObject => new ListedObject(['from' => $source['from'], 'fingerprint' => $source['fingerprint']]),
                    $translation->getTranslated(),
                ),
            ]);
        }

        return new ListedObject([
            'uuid' => $entry->getUuid(),
            'contentType' => $contentType->code,
            'version' => $contentType->version,
            'createdAt' => $entry->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            // The shared base fields: "Hide from search engines" for a content type with its own URL, and a snippet's
            // context, and the content type's own shared fields
            'fields' => (object) StoredSource::canonicalFields(self::sharedFields($entry, $contentType) + $this->ownFields($entry, $this->contentTypeFields->shared($contentType))),
            'translations' => (object) $translations,
            'tree' => (new StoredTreeSerializer())->normalize($entry->getBlockTree()),
        ]);
    }

    /**
     * The values of the content type's own fields on the entry or a translation, in the field value encoding. A field
     * with no value is left out
     *
     * @param array<string, FieldMetadata> $fields
     *
     * @return array<string, mixed>
     */
    private function ownFields(object $subject, array $fields): array
    {
        $values = [];
        foreach (array_keys($fields) as $name) {
            $value = $this->contentTypeFields->read($subject, $name);
            if (null !== $value) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, bool|list<string>>
     */
    private static function sharedFields(EntryInterface $entry, ContentTypeMetadata $contentType): array
    {
        $fields = [];
        foreach (EntryFields::shared($contentType) as $field) {
            $fields[$field] = EntryFields::readShared($entry, $field);
        }

        return $fields;
    }
}
