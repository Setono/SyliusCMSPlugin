<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Setono\SyliusCMSPlugin\Field\MediaItemReferences;
use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;

/**
 * The snippets and media items that references an export follows point at, by UUID, each once: those the field type
 * handlers mark as dependencies. A link to an entry isn't one. See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final class Dependencies
{
    /** @var array<string, true> */
    private array $snippets = [];

    /** @var array<string, true> */
    private array $mediaItems = [];

    /**
     * @param iterable<FoundReference> $found
     */
    public function add(iterable $found): void
    {
        foreach (ReferenceFinder::references($found) as $reference) {
            if (SnippetReferences::isSnippetReference($reference)) {
                $this->snippets[strtolower($reference->id)] = true;
            } elseif (MediaItemReferences::TYPE === $reference->type && $reference->dependency) {
                $this->mediaItems[strtolower($reference->id)] = true;
            }
        }
    }

    /**
     * @return list<string> UUIDs
     */
    public function snippets(): array
    {
        return array_map(strval(...), array_keys($this->snippets));
    }

    /**
     * @return list<string> UUIDs
     */
    public function mediaItems(): array
    {
        return array_map(strval(...), array_keys($this->mediaItems));
    }
}
