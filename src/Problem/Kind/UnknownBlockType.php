<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A block of a type the code no longer has, a warning. It's kept as it is, with what's inside it, and isn't shown.
 * It's fixed in the builder, by removing the block or bringing the block type back.
 * See "A block" in docs/spec/content-model.md and "Problems" in docs/spec/admin.md
 */
final readonly class UnknownBlockType implements ProblemKindInterface
{
    public const CODE = 'unknown_block_type';

    public function __construct(private BlockTypeRegistryInterface $blockTypes)
    {
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
            if ($this->blockTypes->has($block->type)) {
                yield from $this->find($entry, $block->children);

                continue;
            }

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.unknown_block_type', ['%type%' => $block->type]),
                Fix::inBuilder($entry, $block->key),
                $block->key,
            );
        }
    }
}
