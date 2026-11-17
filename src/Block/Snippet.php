<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Snippet as SnippetField;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * A reference to a snippet that stays in sync wherever it's used. It has no markup of its own: the read pipeline hands
 * its template the snippet's blocks as its children, built with the context of the tree it sits in. A snippet the
 * shopper may not see, or one that would render inside itself, gives it no children, so it shows nothing.
 * See "Snippet" in docs/spec/blocks.md and "Snippet references" in docs/spec/builder.md
 */
#[BlockType(
    code: self::CODE,
    label: 'setono_sylius_cms.block_type.snippet',
    template: '@SetonoSyliusCMSPlugin/block/snippet.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/snippet.svg',
)]
final class Snippet
{
    public const CODE = 'snippet';

    #[SnippetField(label: 'setono_sylius_cms.field.snippet.snippet', required: true)]
    public ?SnippetInterface $snippet = null;
}
