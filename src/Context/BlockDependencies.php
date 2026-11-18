<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The blocks whose types need context keys, wherever they sit in the tree. See "Blocks and nesting" in
 * docs/spec/content-model.md
 *
 * @internal
 */
final readonly class BlockDependencies implements ContextDependencyFinderInterface
{
    public function __construct(private BlockTypeRegistryInterface $blockTypes)
    {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        foreach ($workingCopy->getBlockTree()->allBlocks() as $block) {
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            if ([] !== $blockType->needs) {
                yield new ContextDependency(
                    new TranslatableMessage('setono_sylius_cms.context.block', ['%block%' => new TranslatableMessage($blockType->label)], 'validators'),
                    $blockType->needs,
                    block: $block->key,
                );
            }
        }
    }
}
