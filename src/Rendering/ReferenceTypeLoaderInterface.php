<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Loads the records of one reference type, such as media items, by their codes or UUIDs, each with one query: those the
 * shopper may see for rendering, and those that exist at all for the admin's broken references. ReferenceLoader finds
 * one by the "type" attribute of its setono_sylius_cms.reference_type_loader tag. A reference type without one loads
 * nothing, such as a channel or a customer group, which conditions read by code. See "References" in
 * docs/spec/content-model.md
 *
 * @internal
 */
interface ReferenceTypeLoaderInterface
{
    /**
     * The records the ids refer to that resolve for the shopper, such as only the visible products or entries, by id.
     * One query
     *
     * @param non-empty-list<string> $ids each once
     *
     * @return array<string, object>
     */
    public function load(array $ids, RenderContext $context): array;

    /**
     * The ids that refer to a record at all, whether the shopper may see it or not. Those left out are broken
     * references. One query
     *
     * @param non-empty-list<string> $ids each once
     *
     * @return list<string>
     */
    public function existing(array $ids): array;
}
