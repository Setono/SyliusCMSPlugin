<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;

/**
 * Resolves the Allow lists of block types and content types into the block types each spot may hold.
 * A block type fits a spot when the spot allows it, by its class or by '*', and its "parents" allow the spot:
 * a block type that limits its parents is never allowed elsewhere, and so never at the top level
 */
final readonly class NestingRules implements NestingRulesInterface
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function children(string $blockType): array
    {
        return $this->resolve($this->blockTypes->get($blockType)->children, $blockType);
    }

    public function topLevel(string $contentType): array
    {
        return $this->resolve($this->contentTypes->get($contentType)->blocks ?? [], null);
    }

    /**
     * A block type named by its class keeps its own min and max, also where '*' allows it too
     *
     * @param list<Allow> $allows
     * @param string|null $parent the code of the block type whose children the spot is, or null for the top level
     *
     * @return list<AllowedBlockType>
     */
    private function resolve(array $allows, ?string $parent): array
    {
        $allowed = [];

        foreach ($allows as $allow) {
            if ('*' === $allow->type) {
                foreach ($this->blockTypes->all() as $blockType) {
                    if ($this->fits($blockType, $parent)) {
                        $allowed[$blockType->code] ??= new AllowedBlockType($blockType->code, null, null);
                    }
                }

                continue;
            }

            /** @var class-string $class */
            $class = $allow->type;
            $blockType = $this->blockTypes->getByClass($class);
            if ($this->fits($blockType, $parent)) {
                $allowed[$blockType->code] = new AllowedBlockType($blockType->code, $allow->min, $allow->max);
            }
        }

        return array_values($allowed);
    }

    private function fits(BlockTypeMetadata $blockType, ?string $parent): bool
    {
        if (null === $blockType->parents) {
            return true;
        }

        foreach ($blockType->parents as $class) {
            if ($this->blockTypes->getByClass($class)->code === $parent) {
                return true;
            }
        }

        return false;
    }
}
