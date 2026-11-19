<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * Something in an entry, or around a snippet, that depends on the host-page context the entry provides or the snippet
 * declares: a block whose type needs keys, a condition whose kind does, a source that fits only some content types as the
 * entry, a snippet's placement in a slot. Publish refuses
 * while one doesn't fit, and a snippet's declared context can't change in a way that would break one.
 * See "Snippets and their context" in docs/spec/slots.md
 */
final readonly class ContextDependency
{
    /**
     * @param TranslatableMessage $name what depends, as the messages about it name it, such as 'The block "Product note"'
     * @param list<string> $needs the context keys it needs the entry to provide: removing one of them from a snippet's
     *                            declared context is blocked
     * @param list<string>|null $provides the context keys it provides a snippet, such as a placement's slot, which must
     *                                    hold every key the snippet declares: adding one outside them is blocked. Null
     *                                    when it doesn't limit what's declared
     * @param string|null $block the key of the block it's in, which a publish error points at, or null
     * @param list<string>|null $entryContentTypes the codes of the content types the entry it needs must be of, such as
     *                                          the category content types for In this category, or null for any.
     *                                          Publish refuses it in an entry of another, and a preset holding it isn't
     *                                          offered there. A snippet's entry can be of any
     */
    public function __construct(
        public TranslatableMessage $name,
        public array $needs = [],
        public ?array $provides = null,
        public ?string $block = null,
        public ?array $entryContentTypes = null,
    ) {
    }
}
