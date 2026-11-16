<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Rendering\LayoutRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryUrlMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders an entry at its own URL. See "Rendering an entry at its own URL" in docs/spec/rendering.md
 */
final readonly class ShowEntryAction
{
    public function __construct(
        private EntryUrlMatcher $entryUrlMatcher,
        private VisibleEntriesQueryInterface $visibleEntries,
        private RenderContextFactoryInterface $renderContextFactory,
        private LayoutRendererInterface $layoutRenderer,
        private PreviewedEntryProvider $previewedEntryProvider,
    ) {
    }

    public function __invoke(Request $request, string $path): Response
    {
        $localeCode = $request->attributes->getString('_locale');

        // 1. The URL lookup, which the route condition already did, so this reads the memoised match
        $match = $this->entryUrlMatcher->match($localeCode, $path);

        $entry = $this->previewedEntry($match, $localeCode) ?? $this->visibleEntry($match)
            ?? throw new NotFoundHttpException(sprintf('No visible entry at "%s"', $path));

        // 3. and 4. The tree in the entry's layout, with the entry as host-page context
        return new Response($this->layoutRenderer->render($entry, $this->renderContextFactory->create(['entry' => $entry])));
    }

    /**
     * In the builder's preview, the edited entry renders its working copy, whatever its own visibility
     */
    private function previewedEntry(?EntryUrlMatch $match, string $localeCode): ?EntryInterface
    {
        if (null === $match) {
            return null;
        }

        $workingCopy = $this->previewedEntryProvider->getWorkingCopy($localeCode);

        return null !== $workingCopy && $workingCopy->getUuid() === $match->uuid ? $workingCopy : null;
    }

    /**
     * 2. Visibility, which the route condition doesn't check, because it runs before the firewall and the audience needs
     * the signed-in customer. An entry that isn't visible answers 404, with no redirect to sign in
     */
    private function visibleEntry(?EntryUrlMatch $match): ?EntryInterface
    {
        if (null === $match) {
            return null;
        }

        $entry = $this->visibleEntries->createQueryBuilder($match->contentType, $this->renderContextFactory->create())
            ->andWhere('entry.uuid = :uuid')
            ->setParameter('uuid', $match->uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $entry instanceof EntryInterface ? $entry : null;
    }
}
