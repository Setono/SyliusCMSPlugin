<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Entry;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

final class EntriesFromCollectionParams
{
    /** The code of the collection's content type whose entries it lists, such as blog_post. Without one, the list is empty */
    #[Choice(label: 'setono_sylius_cms.field.entries_from_collection_source.content_type', required: true, choicesProvider: CollectionChoicesProvider::class)]
    public ?string $contentType = null;

    /**
     * @var ItemList<EntryInterface>|null the chosen categories of that collection the shopper may see, stored as entry
     *                                    UUIDs: only the entries in any of them are listed. None chosen lists them all
     */
    #[Entries(label: 'setono_sylius_cms.field.entries_from_collection_source.categories', categoriesOf: 'contentType', sources: false, help: 'setono_sylius_cms.field.entries_from_collection_source.categories_help')]
    public ?ItemList $categories = null;
}
