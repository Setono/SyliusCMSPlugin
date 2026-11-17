<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Link\ReferencingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Where a link to the subject leads, through the link target that links to its kind of record: a product's or a
 * taxon's shop page, or an entry's URL, as a path in the current channel and locale. It's empty when the target can't
 * link to it, such as an entry without a URL. The shipped bindable properties product.url, taxon.url and entry.url are
 * declared with it. See "Shipped bindable properties" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class LinkUrlProperty implements BindablePropertyInterface
{
    public function __construct(private ReferencingLinkTargetInterface $linkTarget)
    {
    }

    public function resolve(object $subject, object $params, RenderContext $context): ?string
    {
        // The ref is only what a resolved link reports it resolved, and the record is at hand
        return $this->linkTarget->link($subject, '', $context)?->url;
    }
}
