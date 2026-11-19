<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Entry;

use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What From a collection's contentType parameter is picked from: the collections, by the code of their entries'
 * content type, such as blog_post, in the order the admin lists them. Each is named as the hub names it: by its
 * collection's name, such as "Blog", or else by its content type's plural, or else by its label. See "Entry sources"
 * in docs/spec/sources.md
 */
final readonly class CollectionChoicesProvider implements ChoicesProviderInterface
{
    public function __construct(
        private Collections $collections,
        private TranslatorInterface $translator,
    ) {
    }

    public function getChoices(string $localeCode): array
    {
        $choices = [];
        foreach ($this->collections->all() as $collection) {
            $choices[$collection->code()] = $this->name($collection->entries, $localeCode);
        }

        return $choices;
    }

    private function name(ContentTypeMetadata $contentType, string $localeCode): string
    {
        foreach ([sprintf('setono_sylius_cms.collection.%s', $contentType->code), sprintf('setono_sylius_cms.content_type_plural.%s', $contentType->code)] as $key) {
            $name = $this->translator->trans($key, [], null, $localeCode);
            if ($name !== $key) {
                return $name;
            }
        }

        return $this->translator->trans($contentType->label, [], null, $localeCode);
    }
}
