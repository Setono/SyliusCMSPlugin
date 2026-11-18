<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The visibility conditions whose kinds need context keys, on any block in the tree. A condition of a kind the code no
 * longer has needs nothing. See "Condition kinds" in docs/spec/targeting.md
 *
 * @internal
 */
final readonly class ConditionDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ConditionKindRegistryInterface $conditionKinds,
    ) {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        foreach ($workingCopy->getBlockTree()->allBlocks() as $block) {
            foreach ($block->conditions as $condition) {
                $code = $condition['kind'] ?? null;
                if (!is_string($code) || !$this->conditionKinds->has($code)) {
                    continue;
                }

                $kind = $this->conditionKinds->get($code);
                if ([] !== $kind->needs) {
                    yield new ContextDependency(
                        new TranslatableMessage('setono_sylius_cms.context.condition', ['%kind%' => new TranslatableMessage($kind->label), '%block%' => $this->blockName($block)], 'validators'),
                        $kind->needs,
                        block: $block->key,
                    );
                }
            }
        }
    }

    /**
     * The block's type's label, or its type for one the code no longer has
     */
    private function blockName(StoredBlock $block): TranslatableMessage|string
    {
        return $this->blockTypes->has($block->type) ? new TranslatableMessage($this->blockTypes->get($block->type)->label) : $block->type;
    }
}
