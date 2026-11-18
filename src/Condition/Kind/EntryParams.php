<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

final class EntryParams
{
    /** @var ItemList<EntryInterface>|null the picked entries the shopper may see, stored as entry UUIDs */
    #[Entries(label: 'setono_sylius_cms.field.entry_condition.entries', sources: false, required: true)]
    public ?ItemList $entries = null;
}
