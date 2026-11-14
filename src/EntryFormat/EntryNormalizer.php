<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EntryFormat;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an entry in the entry format, a contract shared by drafts, revisions and exports.
 * See "Entry format" in docs/spec/content-model.md. Encode the result with CanonicalJson
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

            $fields = ['title' => $translation->getTitle()];
            if ($contentType->hasOwnUrl()) {
                $fields['slug'] = $translation->getSlug();
            }

            $translations[$localeCode] = new ListedObject([
                // Holding back a translation comes with translations (#232), so every translation is shown
                'shown' => true,
                'fields' => $fields,
                'translated' => new \stdClass(),
            ]);
        }

        return new ListedObject([
            'uuid' => $entry->getUuid(),
            'contentType' => $contentType->code,
            'version' => $contentType->version,
            'createdAt' => $entry->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'fields' => new \stdClass(),
            'translations' => (object) $translations,
            'tree' => (new StoredTreeSerializer())->normalize($entry->getBlockTree()),
        ]);
    }
}
