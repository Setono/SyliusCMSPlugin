<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\SnippetCycles;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A reference through which a snippet would render inside itself: a Snippet field in the snippet that shows the snippet
 * itself, or a snippet that shows it, through other snippets as they're published (Tree\SnippetCycles). Publish refuses
 * such a field, so it's found in imported or older data. Rendering skips the snippet the second time, so it's an error,
 * fixed in the builder at the block. Only snippets can show each other, so only snippets are flagged, and a snippet
 * written has the snippets that show it, directly or through others, counted again. See "Rendering a block tree" in
 * docs/spec/rendering.md and "Problems" in docs/spec/admin.md
 */
final readonly class SnippetInsideItself implements ReferenceProblemKindInterface
{
    public const CODE = 'snippet_inside_itself';

    public function __construct(
        private SnippetCycles $snippetCycles,
        private BlockTypeRegistryInterface $blockTypes,
        private EntryRepositoryInterface $entries,
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [EntryInterface::class];
    }

    /**
     * Publishing a snippet changes what it shows, which is all this kind reads, so every write of an entry counts
     */
    public static function getReferenceFields(): array
    {
        return [];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        /** @var array<string, true> $visited */
        $visited = [];
        $frontier = [];
        foreach ($referenced as $record) {
            if ($record instanceof SnippetInterface) {
                $visited[$record->getUuid()] = true;
                $frontier[] = $record->getUuid();
            }
        }

        $found = [];
        while ([] !== $frontier) {
            $next = [];
            foreach ($this->entries->findReferencing($frontier) as $entry) {
                if (!$entry instanceof SnippetInterface || isset($visited[$entry->getUuid()])) {
                    continue;
                }

                $visited[$entry->getUuid()] = true;
                $found[] = $entry;
                $next[] = $entry->getUuid();
            }

            $frontier = $next;
        }

        return $found;
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        $tree = $entry->getBlockTree();
        foreach ($this->snippetCycles->in($entry, $tree) as $cycle) {
            yield $this->problem($entry, $tree, $cycle->reference);
        }
    }

    private function problem(SnippetInterface $snippet, StoredTree $tree, FoundReference $found): Problem
    {
        $type = (string) $tree->findBlock((string) $found->block)?->type;

        return new Problem(
            self::CODE,
            Severity::Error,
            $snippet,
            new TranslatableMessage('setono_sylius_cms.problem.snippet_inside_itself', [
                '%block%' => new TranslatableMessage($this->blockTypes->has($type) ? $this->blockTypes->get($type)->label : $type),
            ]),
            Fix::inBuilder($snippet, $found->block),
            $found->block,
            $found->field,
        );
    }
}
