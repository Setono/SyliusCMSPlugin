<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The Snippet blocks in the tree: each needs what its snippet declares in its published content, which is what it
 * renders, read with one query for all of them. A snippet that shows itself never renders there, and a deleted one is
 * a broken reference, so neither needs anything. See "Snippets and their context" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class ShownSnippetDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private ReferenceFinder $referenceFinder,
        private ContentTypeRegistryInterface $contentTypes,
        private EntryRepositoryInterface $entries,
    ) {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        $found = $this->snippetReferences($workingCopy);
        if ([] === $found) {
            return;
        }

        $uuids = array_values(array_unique(array_map(static fn (FoundReference $reference): string => $reference->reference->id, $found)));

        /** @var array<string, SnippetInterface> $snippets */
        $snippets = [];
        foreach ($this->entries->findByUuids($uuids) as $entry) {
            if ($entry instanceof SnippetInterface) {
                $snippets[$entry->getUuid()] = $entry;
            }
        }

        foreach ($found as $reference) {
            $snippet = $snippets[$reference->reference->id] ?? null;
            if (null !== $snippet && [] !== $snippet->getContext()) {
                yield new ContextDependency(
                    new TranslatableMessage('setono_sylius_cms.context.snippet', ['%snippet%' => EntryTitle::of($snippet)], 'validators'),
                    $snippet->getContext(),
                    block: $reference->block,
                );
            }
        }
    }

    /**
     * The references of the Snippet fields in the entry's tree, but those to the entry itself
     *
     * @return list<FoundReference>
     */
    private function snippetReferences(EntryInterface $entry): array
    {
        try {
            $contentType = $this->contentTypes->getByEntry($entry)->code;
        } catch (UnknownContentTypeException) {
            return [];
        }

        $found = [];
        foreach ($this->referenceFinder->inTree($entry->getBlockTree(), $contentType) as $reference) {
            if (SnippetReferences::isSnippetReference($reference->reference) && $reference->reference->id !== $entry->getUuid()) {
                $found[] = $reference;
            }
        }

        return $found;
    }
}
