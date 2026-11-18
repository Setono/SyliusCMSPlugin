<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tree\SnippetUses;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The entries that show a snippet through a Snippet block: each holds the snippet's declared context to what it
 * provides, so adding a key it doesn't provide is blocked. One that shows it in both its published content and its
 * working copy provides what both provide. See "Snippets and their context" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class SnippetUseDependencies implements ContextDependencyFinderInterface
{
    public function __construct(private SnippetUses $snippetUses)
    {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        if (!$workingCopy instanceof SnippetInterface) {
            return;
        }

        foreach ($this->snippetUses->of($workingCopy) as $use) {
            $provides = null;
            if ($use->published) {
                $provides = EntryContext::keys($use->entry);
            }

            if (null !== $use->workingCopy) {
                $drafted = EntryContext::keys($use->workingCopy);
                $provides = null === $provides ? $drafted : array_values(array_intersect($provides, $drafted));
            }

            yield new ContextDependency(
                new TranslatableMessage('setono_sylius_cms.context.used_in', ['%entry%' => EntryTitle::of($use->entry)], 'validators'),
                provides: $provides ?? [],
            );
        }
    }
}
