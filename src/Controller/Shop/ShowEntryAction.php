<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryUrlMatcher;
use Setono\SyliusCMSPlugin\Twig\CmsRuntime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Renders an entry at its own URL. See "Rendering an entry at its own URL" in docs/spec/rendering.md
 */
final readonly class ShowEntryAction
{
    public function __construct(
        private EntryUrlMatcher $entryUrlMatcher,
        private EntryRepositoryInterface $entryRepository,
        private ContentTypeRegistryInterface $contentTypes,
        private RenderContextFactoryInterface $renderContextFactory,
        private BlockViewBuilder $blockViewBuilder,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $path): Response
    {
        // 1. The URL lookup, which the route condition already did, so this reads the memoised match
        $match = $this->entryUrlMatcher->match($request->attributes->getString('_locale'), $path);
        $entry = null === $match ? null : $this->entryRepository->findOneByUuid($match->uuid);

        // 2. Visibility. In this slice an entry is visible once it has been published.
        // Enabled, the date window, channels, translations held back and the audience come with their tickets
        if (null === $entry || !$entry->isPublished()) {
            throw new NotFoundHttpException(sprintf('No visible entry at "%s"', $path));
        }

        $renderContext = $this->renderContextFactory->create(['entry' => $entry]);
        $contentType = $this->contentTypes->getByEntry($entry);

        // 4. The layout. Page's standard layout is the only one until the layout machinery (#236) resolves the tree's layout
        return new Response($this->twig->render(sprintf('@SetonoSyliusCMSPlugin/layout/%s/standard.html.twig', $contentType->code), [
            'entry' => $entry,
            'layout' => null,
            'blocks' => $this->blockViewBuilder->build($entry->getBlockTree()->blocks, $renderContext),
            'context' => $renderContext->getHostPageContext(),
            CmsRuntime::RENDER_CONTEXT => $renderContext,
        ]));
    }
}
