<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Collection\PaginationBreach;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The paginated Entries block's publish rules, each refused at the block's paginate field: only in an entry with its
 * own URL, once in its tree, and filled by a source (PaginationBreach). See "Pagination" in docs/spec/collections.md
 *
 * @internal
 */
final readonly class PaginationValidator
{
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
        foreach (PaginationBreach::in($tree->allBlocks(), $contentType->hasOwnUrl()) as $key => $breach) {
            $errors[] = new ValidationError($key, PaginationBreach::PAGINATE_FIELD, null, $this->translator->trans('setono_sylius_cms.block.paginate_' . $breach->value, [], 'validators'));
        }

        return $errors;
    }
}
