<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * The paginated Entries block of an entry rendered with its own tree, such as at its own URL: an Entries block with
 * paginate on, in the tree of the entry the render context has as entry, that shows. A snippet's blocks never paginate,
 * not even those a Snippet block shows in the entry's tree, because a snippet in a slot on a taxon page would clash with
 * Sylius's own ?page=N. Publish refuses a second paginated Entries block, but older or imported data may hold one: the
 * pass before rendering counts only the first in tree order. See "Pagination" in docs/spec/collections.md
 */
final class PaginatedBlock
{
    private function __construct()
    {
    }

    /**
     * The pass before rendering's check that the page the render context asks for exists, so a page that doesn't
     * answers 404 before any output: a block that throws while rendering is only dropped and logged. Page 1 always
     * exists, even when the list is empty, so it isn't counted. Any other page needs a paginated Entries block that
     * shows, whose source is counted
     *
     * @param list<BlockView> $views the entry's top-level views, built with the render context
     *
     * @throws PageNotFoundException
     */
    public static function assertPageExists(array $views, RenderContext $context): void
    {
        if (1 === $context->page) {
            return;
        }

        $view = self::in($views, $context);
        $list = null === $view ? null : self::listOf($view, $context);
        if (null === $list) {
            throw new PageNotFoundException(sprintf('Page %d doesn\'t exist: the entry has no paginated Entries block that shows', $context->page));
        }

        try {
            $list->pager($context->page);
        } catch (OutOfRangeCurrentPageException) {
            throw new PageNotFoundException(sprintf('Page %d doesn\'t exist: the list has %d pages', $context->page, $list->pager(1)->getNbPages()));
        }
    }

    /**
     * The view of the paginated Entries block among the views and those inside them, depth first, or null when none
     * shows
     *
     * @param list<BlockView> $views
     */
    public static function in(array $views, RenderContext $context): ?BlockView
    {
        foreach ($views as $view) {
            if (null !== self::listOf($view, $context)) {
                return $view;
            }

            $inside = self::in($view->children, $context);
            if (null !== $inside) {
                return $inside;
            }
        }

        return null;
    }

    /**
     * The list the view pages, when it's a paginated Entries block in the tree of the entry it renders for, or null
     *
     * @return ItemList<EntryInterface>|null
     */
    public static function listOf(BlockView $view, RenderContext $context): ?ItemList
    {
        $block = $view->block;
        if (!$block instanceof Entries || !$block->paginate) {
            return null;
        }

        $entry = $context->get(EntryContext::ENTRY);
        if (!$entry instanceof EntryInterface || $entry->getUuid() !== $view->entry) {
            return null;
        }

        return $block->entries;
    }
}
