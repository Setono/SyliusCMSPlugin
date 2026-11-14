<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * What each spot in a block tree may hold. See "Blocks and nesting" in docs/spec/content-model.md
 */
interface NestingRulesInterface
{
    /**
     * The block types a block of this type may hold. Disabled block types are included,
     * because blocks of them already in trees stay where they are
     *
     * @return list<AllowedBlockType>
     *
     * @throws UnknownBlockTypeException
     */
    public function children(string $blockType): array;

    /**
     * The block types the top level of an entry of this content type may hold. Empty when it has no block tree
     *
     * @return list<AllowedBlockType>
     *
     * @throws UnknownContentTypeException
     */
    public function topLevel(string $contentType): array;
}
