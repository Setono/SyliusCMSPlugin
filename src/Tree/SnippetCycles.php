<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * Finds the Snippet fields through which a snippet would show inside itself: one that shows the snippet itself, or a
 * snippet that shows it, through other snippets as they're published, which is what a Snippet block renders. A snippet
 * that was never published shows nothing, so the way leads on only through published ones. Publish refuses such a field
 * (Validation\SnippetCycleValidator), and the snippet_inside_itself problem flags one that imported or older data
 * holds. See "Snippet" in docs/spec/blocks.md and "Validation" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class SnippetCycles
{
    public function __construct(
        private ReferenceFinder $referenceFinder,
        private EntryRepositoryInterface $entries,
    ) {
    }

    /**
     * The Snippet fields in the tree, in the order the tree holds them, that lead back to the snippet, each with the
     * shortest way back. The snippets on the way are read once for all of them, one query a level
     *
     * @param StoredTree $tree the snippet's tree, such as its working copy's
     *
     * @return list<SnippetCycle>
     */
    public function in(SnippetInterface $snippet, StoredTree $tree): array
    {
        /** @var array<string, SnippetInterface|null> $loaded the snippets read so far, by UUID, null for one there's no snippet with */
        $loaded = [];

        $cycles = [];
        foreach ($this->snippetReferences($tree) as $found) {
            $through = $this->wayBack($found->reference->id, $snippet->getUuid(), $loaded);
            if (null !== $through) {
                $cycles[] = new SnippetCycle($found, $through);
            }
        }

        return $cycles;
    }

    /**
     * The snippets on the shortest way from the shown snippet back to the target, through the published trees of the
     * snippets on it, or null when there's none
     *
     * @param array<string, SnippetInterface|null> $loaded
     *
     * @return list<SnippetInterface>|null
     */
    private function wayBack(string $shown, string $target, array &$loaded): ?array
    {
        if ($shown === $target) {
            return [];
        }

        /** @var array<string, string|null> $shownBy each snippet reached, by UUID, with the one that shows it on the way, null for the first */
        $shownBy = [$shown => null];
        $frontier = [$shown];
        while ([] !== $frontier) {
            $this->load($frontier, $loaded);

            $next = [];
            foreach ($frontier as $uuid) {
                $snippet = $loaded[$uuid] ?? null;
                if (null === $snippet || !$snippet->isPublished()) {
                    continue;
                }

                foreach ($this->snippetReferences($snippet->getBlockTree()) as $found) {
                    $id = $found->reference->id;
                    if ($id === $target) {
                        return self::way($uuid, $shownBy, $loaded);
                    }

                    if (!array_key_exists($id, $shownBy)) {
                        $shownBy[$id] = $uuid;
                        $next[] = $id;
                    }
                }
            }

            $frontier = $next;
        }

        return null;
    }

    /**
     * The snippets from the first on the way to the last, which shows the target
     *
     * @param array<string, string|null> $shownBy
     * @param array<string, SnippetInterface|null> $loaded
     *
     * @return list<SnippetInterface>
     */
    private static function way(string $last, array $shownBy, array $loaded): array
    {
        $way = [];
        for ($uuid = $last; null !== $uuid; $uuid = $shownBy[$uuid] ?? null) {
            $snippet = $loaded[$uuid] ?? null;
            if (null !== $snippet) {
                array_unshift($way, $snippet);
            }
        }

        return $way;
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
}
