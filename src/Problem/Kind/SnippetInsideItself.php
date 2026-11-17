<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A reference through which a snippet would render inside itself: a Snippet field in the snippet that shows the snippet
 * itself, or a snippet that shows it, through other snippets as they're published. Rendering skips the snippet the
 * second time, so it's an error, fixed in the builder at the block. Only snippets can show each other, so only
 * snippets are flagged, and a snippet written has the snippets that show it, directly or through others, counted
 * again. See "Rendering a block tree" in docs/spec/rendering.md and "Problems" in docs/spec/admin.md
 */
final readonly class SnippetInsideItself implements ReferenceProblemKindInterface
{
    public const CODE = 'snippet_inside_itself';

    public function __construct(
        private ReferenceFinder $referenceFinder,
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
        /** @var array<string, SnippetInterface|null> $loaded the snippets read so far, by UUID, null for one there's no snippet with */
        $loaded = [];

        foreach ($this->snippetReferences($tree) as $found) {
            if ($this->leadsTo($found->reference->id, $entry->getUuid(), $loaded)) {
                yield $this->problem($entry, $tree, $found);
            }
        }
    }

    /**
     * Whether the snippet, or one it shows, directly or through others, is the target, as their rows show them
     *
     * @param array<string, SnippetInterface|null> $loaded
     */
    private function leadsTo(string $snippet, string $target, array &$loaded): bool
    {
        $visited = [];
        $frontier = [$snippet];
        while ([] !== $frontier) {
            if (in_array($target, $frontier, true)) {
                return true;
            }

            foreach ($frontier as $uuid) {
                $visited[$uuid] = true;
            }

            $this->load($frontier, $loaded);

            $next = [];
            foreach ($frontier as $uuid) {
                $shown = $loaded[$uuid] ?? null;
                if (null === $shown) {
                    continue;
                }

                foreach ($this->snippetReferences($shown->getBlockTree()) as $found) {
                    if (!isset($visited[$found->reference->id])) {
                        $next[$found->reference->id] = true;
                    }
                }
            }

            $frontier = array_map(strval(...), array_keys($next));
        }

        return false;
    }

    /**
     * Reads the snippets not read yet, with one query
     *
     * @param list<string> $uuids
     * @param array<string, SnippetInterface|null> $loaded
     */
    private function load(array $uuids, array &$loaded): void
    {
        $missing = array_values(array_filter($uuids, static fn (string $uuid): bool => !array_key_exists($uuid, $loaded)));
        if ([] === $missing) {
            return;
        }

        foreach ($missing as $uuid) {
            $loaded[$uuid] = null;
        }

        foreach ($this->entries->findByUuids($missing) as $found) {
            if ($found instanceof SnippetInterface) {
                $loaded[$found->getUuid()] = $found;
            }
        }
    }

    /**
     * The references of the Snippet fields in the tree's blocks
     *
     * @return \Generator<FoundReference>
     */
    private function snippetReferences(StoredTree $tree): \Generator
    {
        foreach ($this->referenceFinder->inBlocks($tree->blocks) as $found) {
            if (SnippetReferences::isSnippetReference($found->reference)) {
                yield $found;
            }
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
