<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A rule of the paginated Entries block that a block breaks where it is. Only an entry with its own URL paginates, so a
 * snippet can't: one in a slot on a taxon page would clash with Sylius's own ?page=N. It paginates through one Entries
 * block in its own tree, so a second one breaks the rule, in tree order. Its page size is the list's $limit, so it needs
 * a source to fill it. Publish refuses a breach at the block's paginate field, and the preset fit check leaves out a
 * preset whose paginated block would break one. See "Pagination" in docs/spec/collections.md
 */
enum PaginationBreach: string
{
    /** In an entry without its own URL, such as a snippet */
    case WithoutUrl = 'without_url';

    /** After another paginated Entries block in the entry's tree */
    case Twice = 'twice';

    /** With a list no source fills, such as a hand-picked one */
    case WithoutSource = 'without_source';

    /** The Entries block's field that holds its list */
    private const ENTRIES_FIELD = 'entries';

    /** The Entries block's field that turns pagination on */
    public const PAGINATE_FIELD = 'paginate';

    /**
     * The rule each paginated Entries block among the blocks breaks, by its key. A block that breaks several breaks the
     * first of these: no URL, then a second one, then no source
     *
     * @param list<StoredBlock> $blocks every block of an entry's tree, in tree order, as StoredTree::allBlocks() lists them
     * @param bool $ownUrl whether the entry has its own URL
     *
     * @return array<string, self>
     */
    public static function in(array $blocks, bool $ownUrl): array
    {
        $breaches = [];
        $seen = false;
        foreach ($blocks as $block) {
            if (!self::paginates($block)) {
                continue;
            }

            $breach = match (true) {
                !$ownUrl => self::WithoutUrl,
                $seen => self::Twice,
                !StoredSource::isSource($block->fields[self::ENTRIES_FIELD] ?? null) => self::WithoutSource,
                default => null,
            };
            $seen = true;

            if (null !== $breach) {
                $breaches[$block->key] = $breach;
            }
        }

        return $breaches;
    }

    private static function paginates(StoredBlock $block): bool
    {
        return Entries::CODE === $block->type && true === ($block->fields[self::PAGINATE_FIELD] ?? null);
    }
}
