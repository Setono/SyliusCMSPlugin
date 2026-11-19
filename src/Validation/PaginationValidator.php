<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The paginated Entries block's publish rules, each refused at the block's paginate field. Only an entry with its own
 * URL paginates, so a snippet can't: one in a slot on a taxon page would clash with Sylius's own ?page=N. It paginates
 * through one Entries block in its own tree, so a second one is refused, in tree order. Its page size is the list's
 * $limit, so it needs a source to fill it. See "Pagination" in docs/spec/collections.md
 *
 * @internal
 */
final readonly class PaginationValidator
{
    /** The Entries block's field that holds its list */
    private const ENTRIES_FIELD = 'entries';

    /** The Entries block's field that turns pagination on */
    private const PAGINATE_FIELD = 'paginate';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param StoredTree $tree the tree that would be published
     * @param ContentTypeMetadata $contentType the content type of the entry whose tree it is
     *
     * @return list<ValidationError>
     */
    public function validate(StoredTree $tree, ContentTypeMetadata $contentType): array
    {
        $errors = [];
        $seen = false;
        foreach ($tree->allBlocks() as $block) {
            if (!self::paginates($block)) {
                continue;
            }

            $message = match (true) {
                !$contentType->hasOwnUrl() => 'setono_sylius_cms.block.paginate_without_url',
                $seen => 'setono_sylius_cms.block.paginate_twice',
                !StoredSource::isSource($block->fields[self::ENTRIES_FIELD] ?? null) => 'setono_sylius_cms.block.paginate_without_source',
                default => null,
            };
            $seen = true;

            if (null !== $message) {
                $errors[] = new ValidationError($block->key, self::PAGINATE_FIELD, null, $this->translator->trans($message, [], 'validators'));
            }
        }

        return $errors;
    }

    private static function paginates(StoredBlock $block): bool
    {
        return Entries::CODE === $block->type && true === ($block->fields[self::PAGINATE_FIELD] ?? null);
    }
}
