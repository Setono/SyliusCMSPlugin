<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Entries, hand-picked by UUID in the order editors put them. Hydrated as ItemList<EntryInterface> of the entries the
 * shopper may see. The entry sources and the options for collections join with the Entries block (#268).
 * See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'entries')]
final class Entries extends ListField
{
    public function itemType(): string
    {
        return ReferenceType::ENTRY;
    }

    public function itemClass(): string
    {
        return EntryInterface::class;
    }
}
