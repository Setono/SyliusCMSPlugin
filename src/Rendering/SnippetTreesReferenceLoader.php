<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;

/**
 * Includes the trees of the snippets references show in the same pass: it loads the snippets first, a round per level
 * of snippets inside snippets, each once, and reads the tree of each one the shopper may see. Then the references of
 * the trees and of those snippets' trees load together, with one query per type, so a snippet's media items load with
 * the page's. A snippet the shopper may not see renders nothing, so its tree isn't read. See "Rendering a block tree" in
 * docs/spec/rendering.md
 *
 * @internal
 */
final readonly class SnippetTreesReferenceLoader implements ReferenceLoaderInterface
{
    public function __construct(
        private ReferenceLoaderInterface $referenceLoader,
        private BlockReferences $blockReferences,
        private StoredDataUpgraderInterface $upgrader,
    ) {
    }

    public function load(iterable $references, RenderContext $context): ReferenceMap
    {
        /** @var list<Reference> $all */
        $all = [];
        /** @var array<string, object> $snippets the records of the snippet references loaded, by UUID */
        $snippets = [];
        /** @var array<string, true> $tried the UUIDs of the snippet references loaded, found or not */
        $tried = [];

        $round = $references;
        while (true) {
            $next = [];
            foreach ($round as $reference) {
                $all[] = $reference;
                if (SnippetReferences::isSnippetReference($reference) && !isset($tried[$reference->id])) {
                    $tried[$reference->id] = true;
                    $next[] = $reference;
                }
            }

            if ([] === $next) {
                break;
            }

            $loaded = $this->referenceLoader->load($next, $context)->all(SnippetReferences::TYPE);
            $snippets += $loaded;

            $round = (function () use ($loaded): \Generator {
                foreach ($loaded as $snippet) {
                    if ($snippet instanceof SnippetInterface) {
                        yield from $this->blockReferences->in($this->upgrader->readBlockTree($snippet)->blocks);
                    }
                }
            })();
        }

        // Everything but the snippets, which are loaded already
        $rest = array_filter($all, static fn (Reference $reference): bool => SnippetReferences::TYPE !== $reference->type || !isset($tried[$reference->id]));

        return $this->referenceLoader->load($rest, $context)->with(SnippetReferences::TYPE, $snippets);
    }
}
