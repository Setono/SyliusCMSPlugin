<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link;

use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A link target whose refs refer to records the plugin loads before a block tree renders, such as entries, products and
 * taxons. A Link field lists their references, so they're loaded with one query per type with every other reference of
 * the tree, and the link is built from the record that was loaded for the shopper. Its resolve() loads the one record
 * itself. See "Rendering a block tree" in docs/spec/rendering.md
 *
 * @internal
 */
interface ReferencingLinkTargetInterface extends LinkTargetInterface
{
    /**
     * What the ref refers to, or null when it can't refer to anything, such as a UUID that isn't one. A link isn't an
     * export's dependency
     */
    public function reference(string $ref): ?Reference;

    /**
     * Where a link to the record leads, or null when it leads nowhere for the shopper, such as an entry without a URL
     *
     * @param object $record what the reference resolved to for the shopper
     */
    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink;
}
