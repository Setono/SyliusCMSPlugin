<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Collection\PaginationBreach;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Context\ContextDependencyFinderInterface;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Context\Needs;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownBlockTypeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Where a preset is offered: at a spot in an entry's tree, when every one of its top-level blocks is allowed there,
 * inserting it keeps the spot within the max of each block type, and the context its blocks need is available, which
 * is what the entry provides its tree, or what a snippet declares, and every source in it fits the entry's content type,
 * as Related fits only entries of a collection with categories. What a preset needs is asked of every context
 * dependency finder, the same ones publish asks, so block types, condition kinds, sources, bindings and Snippet blocks
 * all count, and so does whatever a later finder adds. A paginated Entries block in it must keep to the rules publish
 * holds it to where it would go: only in an entry with its own URL, once in its tree, filled by a source. See "Where a
 * preset is offered" in docs/spec/layouts-and-presets.md and "Pagination" in docs/spec/collections.md
 */
final readonly class PresetFitCheck
{
    /**
     * @param iterable<ContextDependencyFinderInterface> $finders
     */
    public function __construct(
        private NestingRulesInterface $nestingRules,
        private BlockTypeRegistryInterface $blockTypes,
        private ContentTypeRegistryInterface $contentTypes,
        private iterable $finders,
        private ContextKeyRegistryInterface $contextKeys,
    ) {
    }

    /**
     * @param list<StoredBlock> $blocks the blocks the preset inserts (BlocksToInsert::$blocks)
     * @param EntryInterface $workingCopy the entry they'd be inserted into, as the builder edits it
     * @param string|null $parent the key of the block whose children the spot is, or null for the top level
     */
    public function at(array $blocks, EntryInterface $workingCopy, ?string $parent): PresetFit
    {
        if ([] === $blocks) {
            return new PresetFit([new TranslatableMessage('setono_sylius_cms.preset.fit.nothing_to_insert')]);
        }

        return new PresetFit([
            ...$this->nestingReasons($blocks, $workingCopy, $parent),
            ...$this->contextReasons($blocks, $workingCopy),
            ...$this->paginationReasons($blocks, $workingCopy),
        ]);
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<TranslatableMessage>
     */
    private function nestingReasons(array $blocks, EntryInterface $workingCopy, ?string $parent): array
    {
        $tree = $workingCopy->getBlockTree();
        $parentBlock = null === $parent ? null : $tree->findBlock($parent);
        $siblings = $parentBlock->children ?? (null === $parent ? $tree->blocks : []);
        $allowed = [];
        foreach ($this->allowed($workingCopy, $parent, $parentBlock) as $type) {
            $allowed[$type->code] = $type;
        }

        $reasons = [];
        foreach (array_count_values(array_map(static fn (StoredBlock $block): string => $block->type, $blocks)) as $type => $count) {
            $type = (string) $type;
            $label = new TranslatableMessage($this->blockTypes->get($type)->label);
            $rule = $allowed[$type] ?? null;
            if (null === $rule) {
                $reasons[] = new TranslatableMessage('setono_sylius_cms.preset.fit.not_allowed', ['%block%' => $label]);

                continue;
            }

            if (null !== $rule->max && self::count($siblings, $type) + $count > $rule->max) {
                $reasons[] = new TranslatableMessage('setono_sylius_cms.preset.fit.too_many', ['%count%' => $rule->max, '%block%' => $label]);
            }
        }

        return $reasons;
    }

    /**
     * @return list<AllowedBlockType>
     */
    private function allowed(EntryInterface $workingCopy, ?string $parent, ?StoredBlock $parentBlock): array
    {
        if (null === $parent) {
            return $this->nestingRules->topLevel($this->contentTypes->getByEntry($workingCopy)->code);
        }

        if (null === $parentBlock) {
            return [];
        }

        try {
            return $this->nestingRules->children($parentBlock->type);
        } catch (UnknownBlockTypeException) {
            return [];
        }
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<TranslatableMessage>
     */
    private function contextReasons(array $blocks, EntryInterface $workingCopy): array
    {
        $dependencies = $this->dependencies($blocks);
        $needs = array_values(array_unique(array_merge(...array_map(static fn (ContextDependency $dependency): array => $dependency->needs, $dependencies))));
        $missing = Needs::missing($needs, EntryContext::keys($workingCopy));

        $reasons = array_map(fn (string $key): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.preset.fit.needs_context', ['%context%' => $this->contextKeys->labelOf($key)]), $missing);

        // A source such as Related fits only entries of some content types. A snippet's entry can be of any
        $contentType = $workingCopy instanceof SnippetInterface ? null : $this->contentTypes->getByEntry($workingCopy);
        foreach ($dependencies as $dependency) {
            if (null !== $contentType && !$dependency->fitsEntryOf($contentType->code)) {
                $reasons[] = new TranslatableMessage('setono_sylius_cms.preset.fit.wrong_entry', ['%content_type%' => new TranslatableMessage($contentType->label)]);

                break;
            }
        }

        return $reasons;
    }

    /**
     * The pagination rules the preset's paginated Entries blocks would break in the entry, after the tree's own blocks,
     * as publish would refuse them. A breach of the tree's own blocks is the entry's, not the preset's
     *
     * @param list<StoredBlock> $blocks
     *
     * @return list<TranslatableMessage>
     */
    private function paginationReasons(array $blocks, EntryInterface $workingCopy): array
    {
        $inserted = (new StoredTree(null, $blocks))->allBlocks();
        $breaches = PaginationBreach::in([...$workingCopy->getBlockTree()->allBlocks(), ...$inserted], $this->contentTypes->getByEntry($workingCopy)->hasOwnUrl());

        $reasons = [];
        foreach ($inserted as $block) {
            $breach = $breaches[$block->key] ?? null;
            if (null !== $breach) {
                $reasons[$breach->value] = new TranslatableMessage('setono_sylius_cms.preset.fit.paginate_' . $breach->value);
            }
        }

        return array_values($reasons);
    }

    /**
     * What the blocks depend on, the context keys they need and the content types some fit, as the context dependency
     * finders find them in an entry that holds them alone. That entry is a page, which no placement or Snippet block can
     * depend on, so only what the blocks need is found
     *
     * @param list<StoredBlock> $blocks
     *
     * @return list<ContextDependency>
     */
    private function dependencies(array $blocks): array
    {
        $class = $this->contentTypes->get(PageInterface::CONTENT_TYPE)->modelClass;
        /** @var EntryInterface $holder */
        $holder = new $class();
        $holder->setBlockTree(new StoredTree(null, $blocks));

        $dependencies = [];
        foreach ($this->finders as $finder) {
            foreach ($finder->findIn($holder) as $dependency) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function count(array $blocks, string $type): int
    {
        return count(array_filter($blocks, static fn (StoredBlock $block): bool => $block->type === $type));
    }
}
