<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ReferencingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A link target to records the plugin loads before rendering, like the shipped entry, product and taxon targets: a
 * recipe, by its code, loaded as a product
 */
#[AsLinkTarget(code: 'recipe', label: 'app.link_target.recipe')]
final class RecipeLinkTarget implements ReferencingLinkTargetInterface
{
    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        throw new \LogicException('A link to a recipe is resolved from the record loaded before rendering');
    }

    public function search(string $query, string $localeCode): iterable
    {
        return [];
    }

    public function reference(string $ref): ?Reference
    {
        return '' === trim($ref) ? null : new Reference('product', $ref, false);
    }

    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink
    {
        return $record instanceof Recipe ? new ResolvedLink('recipe', $ref, sprintf('/%s/recipes/%s', $context->localeCode, $record->slug), $record->name) : null;
    }
}
