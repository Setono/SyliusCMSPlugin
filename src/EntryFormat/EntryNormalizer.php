<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EntryFormat;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an entry in the entry format, a contract shared by drafts, revisions and exports.
 * See "Entry format" in docs/spec/content-model.md. Encode the result with CanonicalJson
 *
 * @internal
 */
final readonly class EntryNormalizer
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
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
            // The shared base fields: "Hide from search engines" for a content type with its own URL, and a snippet's context
            'fields' => (object) self::sharedFields($entry, $contentType),
            'translations' => (object) $translations,
            'tree' => (new StoredTreeSerializer())->normalize($entry->getBlockTree()),
        ]);
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
