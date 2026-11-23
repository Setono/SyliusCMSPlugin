<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A block's visibility condition of a kind the code no longer has, a warning for each. The condition is kept and never
 * matches, negated or not, so the block stays hidden, and publish refuses it. It's fixed in the builder, by removing the
 * condition or bringing the kind back. A project may remove a condition kind on purpose, so setono:cms:lint doesn't
 * report it. A block of a type the code no longer has isn't shown either, which UnknownBlockType flags, so neither its
 * conditions nor those of the blocks inside it are read. A deploy that removes a kind counts every entry again
 * (ProblemCountFingerprint). See "Condition kinds" in docs/spec/targeting.md and "Problems" in docs/spec/admin.md
 */
final readonly class UnknownConditionKind implements ProblemKindInterface
{
    public const CODE = 'unknown_condition_kind';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ConditionKindRegistryInterface $conditionKinds,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        return $this->find($entry, $entry->getBlockTree()->blocks);
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Problem>
     */
    private function find(EntryInterface $entry, array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            foreach ($block->conditions as $condition) {
                $kind = $condition['kind'] ?? null;
                if (!is_string($kind) || $this->conditionKinds->has($kind)) {
                    continue;
                }

                yield new Problem(
                    self::CODE,
                    Severity::Warning,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.unknown_condition_kind', [
                        '%kind%' => $kind,
                        '%block%' => new TranslatableMessage($this->blockTypes->get($block->type)->label),
                    ]),
                    Fix::inBuilder($entry, $block->key),
                    $block->key,
                );
            }

            yield from $this->find($entry, $block->children);
        }
    }
}
