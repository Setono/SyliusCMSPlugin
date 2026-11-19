<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\SnippetReferences;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * The entries whose block trees show a snippet through a Snippet field, in their published content or in their working
 * copy: what the snippet's overview page lists under Used in, and what holds its declared context to what they provide.
 * One query finds the entries that hold its UUID anywhere, and each one's trees tell whether a Snippet field holds it.
 * A snippet that shows itself isn't among them: that never renders, and is flagged as a problem of its own.
 * See "Entry overview page" in docs/spec/admin.md and "Snippets and their context" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class SnippetUses
{
    public function __construct(
        private EntryRepositoryInterface $entries,
        private WorkingCopyProviderInterface $workingCopies,
        private ReferenceFinder $referenceFinder,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * @return list<SnippetUse> in the order the entries were created
     */
    public function of(SnippetInterface $snippet): array
    {
        $uses = [];
        foreach ($this->entries->findReferencing([$snippet->getUuid()]) as $entry) {
            if ($entry->getUuid() === $snippet->getUuid()) {
                continue;
            }

            $published = $entry->isPublished() && $this->shows($entry, $snippet);
            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $drafted = $this->shows($workingCopy, $snippet);

            if ($published || $drafted) {
                $uses[] = new SnippetUse($entry, $published, $drafted ? $workingCopy : null);
            }
        }

        return $uses;
    }

    /**
     * Whether a Snippet field holds the snippet: one of its content type's own, or one in its tree, in a block or in its layout
     */
    private function shows(EntryInterface $entry, SnippetInterface $snippet): bool
    {
        try {
            $contentType = $this->contentTypes->getByEntry($entry);
        } catch (UnknownContentTypeException) {
            // An entry of a content type the code no longer has shows nowhere
            return false;
        }

        // Its content type's own Snippet fields show a snippet too
        foreach ($this->referenceFinder->inEntry($entry, $contentType) as $found) {
            if (SnippetReferences::isSnippetReference($found->reference) && $found->reference->id === $snippet->getUuid()) {
                return true;
            }
        }

        return false;
    }
}
