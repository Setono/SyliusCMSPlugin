<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Setono\SyliusCMSPlugin\Collection\PageNumber;
use Setono\SyliusCMSPlugin\Collection\PaginatedBlock;
use Setono\SyliusCMSPlugin\Collection\Pagination;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_pagination(view): the page of the entry's paginated Entries block that the Entries block's template shows, with
 * the links its <nav class="cms-pagination"> holds, each to the entry's own URL with ?page=N, page 1 without it. The
 * pass before rendering made sure the page exists. See "Pagination" in docs/spec/collections.md
 */
final readonly class PaginationRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CanonicalUrlGeneratorInterface $canonicalUrls,
        private RenderContexts $renderContexts,
    ) {
    }

    /**
     * The page of the view's list that shows, and its links, or null when the view isn't the paginated Entries block of
     * the entry it renders for, such as an Entries block that doesn't paginate, or a snippet's. Publish refuses a second
     * paginated Entries block, but older or imported data may hold one: it shows the same page, or its first items when
     * its list has no such page, since the pass before rendering counts only the first
     *
     * @param array<string, mixed> $context
     *
     * @return Pagination<EntryInterface>|null
     */
    public function pagination(array $context, BlockView $view): ?Pagination
    {
        $renderContext = $this->renderContexts->of($context);
        $list = PaginatedBlock::listOf($view, $renderContext);
        $entry = $renderContext->get(EntryContext::ENTRY);
        if (null === $list || !$entry instanceof EntryInterface) {
            return null;
        }

        $url = $this->canonicalUrls->generate($entry, $renderContext->channel, $renderContext->localeCode, UrlGeneratorInterface::ABSOLUTE_PATH);
        if (null === $url) {
            return null;
        }

        try {
            $pager = $list->pager($renderContext->page);
        } catch (OutOfRangeCurrentPageException) {
            // A second paginated Entries block, which older or imported data may hold, past its last page
            return null;
        }

        return Pagination::of($pager, static fn (int $page): string => PageNumber::url($url, $page));
    }
}
