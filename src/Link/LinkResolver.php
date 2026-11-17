<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistryInterface;

/**
 * Resolves links through their link targets, for every field type that holds them: the Link field, and rich text's
 * links. A link to a record, such as a product, is built from the record the pass before rendering loaded for the
 * shopper, so the tree's links to one type of record take one query. A link to an unknown target doesn't resolve.
 * See "Link targets" in docs/spec/extending.md
 *
 * @internal
 */
final readonly class LinkResolver
{
    public function __construct(private LinkTargetRegistryInterface $linkTargets)
    {
    }

    /**
     * The record a link's ref refers to, which the pass before rendering loads, or null when its target refers to none,
     * as a URL doesn't
     */
    public function reference(string $target, string $ref): ?Reference
    {
        $linkTarget = $this->linkTarget($target);

        return $linkTarget instanceof ReferencingLinkTargetInterface ? $linkTarget->reference($ref) : null;
    }

    /**
     * Why publish refuses the ref, as a translation key, when its target refuses it, as the url target refuses a
     * javascript: URL. Null when the target takes it or doesn't check refs
     */
    public function refError(string $target, string $ref): ?string
    {
        $linkTarget = $this->linkTarget($target);

        return $linkTarget instanceof RefCheckingLinkTargetInterface ? $linkTarget->refError($ref) : null;
    }

    /**
     * Where the link leads for the shopper, with the anchor, or null when it doesn't resolve and isn't rendered
     *
     * @param string $ref the current locale's ref
     */
    public function resolve(string $target, string $ref, ?string $anchor, HydrationContext $context): ?ResolvedLink
    {
        $linkTarget = $this->linkTarget($target);
        if (null === $linkTarget) {
            return null;
        }

        if ($linkTarget instanceof ReferencingLinkTargetInterface) {
            $reference = $linkTarget->reference($ref);
            $record = null === $reference ? null : $context->references->get($reference->type, $reference->id);
            $resolved = null === $record ? null : $linkTarget->link($record, $ref, $context->renderContext);
        } else {
            $resolved = $linkTarget->resolve($ref, $context->renderContext);
        }

        return $resolved?->withAnchor($anchor);
    }

    private function linkTarget(string $code): ?LinkTargetInterface
    {
        return $this->linkTargets->has($code) ? $this->linkTargets->get($code)->target : null;
    }
}
