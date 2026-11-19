<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A category whose URL is its archive, but whose tree holds no Entries block, a warning: the archive lists none of the
 * category's entries. A category with its own URL and a tree lists them through an Entries block filled by In this
 * category, which its default starting point puts there and editors can remove. Any Entries block, wherever it is in the
 * tree and however it's filled, counts, as an editor may list the entries another way. It's fixed in the builder.
 * See "Listings" in docs/spec/collections.md and "Problems" in docs/spec/admin.md
 */
final readonly class CategoryArchiveWithoutEntries implements ProblemKindInterface
{
    public const CODE = 'category_archive_without_entries';

    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private Collections $collections,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$this->hasArchive($entry)) {
            return;
        }

        $entries = array_filter($entry->getBlockTree()->allBlocks(), static fn (StoredBlock $block): bool => Entries::CODE === $block->type);
        if ([] !== $entries) {
            return;
        }

        yield new Problem(
            self::CODE,
            Severity::Warning,
            $entry,
            new TranslatableMessage('setono_sylius_cms.problem.category_archive_without_entries', ['%source%' => new TranslatableMessage('setono_sylius_cms.source.entries_in_this_category')]),
            Fix::inBuilder($entry),
        );
    }

    /**
     * Whether the entry is a category whose URL is its archive: of a collection's category content type, with its own
     * URL and a block tree
     */
    private function hasArchive(EntryInterface $entry): bool
    {
        $contentType = $this->contentTypes->getByEntry($entry);
        if (!$contentType->hasOwnUrl() || null === $contentType->blocks) {
            return false;
        }

        return $contentType->code === $this->collections->of($contentType->code)?->categories?->code;
    }
}
