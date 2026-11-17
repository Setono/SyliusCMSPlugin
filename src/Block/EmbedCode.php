<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Code;

/**
 * Raw HTML, scripts included, printed unescaped in the shop, such as a review widget, a social feed or an iframe. It's
 * off until a project sets setono_sylius_cms.embed_code: true, and the builder's preview shows a placeholder in its
 * place, so its scripts never run in the builder. See "Embed code" in docs/spec/blocks.md and ADR 0018
 */
#[BlockType(
    code: self::CODE,
    label: 'setono_sylius_cms.block_type.embed_code',
    template: '@SetonoSyliusCMSPlugin/block/embed_code.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/embed_code.svg',
)]
final class EmbedCode
{
    public const CODE = 'embed_code';

    #[Code(label: 'setono_sylius_cms.field.embed_code.code', help: 'setono_sylius_cms.field.embed_code.code_help')]
    public ?string $code = null;
}
