<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryUrlMatcher;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Renders an entry at its own URL. See "Rendering an entry at its own URL" in docs/spec/rendering.md
 */
final readonly class ShowEntryAction
{
    public function __construct(
        private EntryUrlMatcher $entryUrlMatcher,
        private VisibleEntriesQueryInterface $visibleEntries,
        private ContentTypeRegistryInterface $contentTypes,
        private RenderContextFactoryInterface $renderContextFactory,
        private BlockViewBuilder $blockViewBuilder,
        private Environment $twig,
        private PreviewedEntryProvider $previewedEntryProvider,
        private StoredDataUpgraderInterface $upgrader,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $path): Response
    {
        $localeCode = $request->attributes->getString('_locale');

        // 1. The URL lookup, which the route condition already did, so this reads the memoised match
        $match = $this->entryUrlMatcher->match($localeCode, $path);

        $entry = $this->previewedEntry($match, $localeCode) ?? $this->visibleEntry($match)
            ?? throw new NotFoundHttpException(sprintf('No visible entry at "%s"', $path));

        // An old slug redirects to the entry's current URL, once the entry is known to be visible
        if (null !== $match?->redirectTo) {
            return $this->redirect($request, $localeCode, $match->redirectTo);
        }

        // 3. The render context, with the entry as host-page context
        $renderContext = $this->renderContextFactory->create(['entry' => $entry]);
        $contentType = $this->contentTypes->getByEntry($entry);

        // 4. The layout. Page's standard layout is the only one until the layout machinery (#236) resolves the tree's layout
        return new Response($this->twig->render(sprintf('@SetonoSyliusCMSPlugin/layout/%s/standard.html.twig', $contentType->code), [
            'entry' => $entry,
            'layout' => null,
            'blocks' => $this->blockViewBuilder->build($this->upgrader->readBlockTree($entry)->blocks, $renderContext),
            'context' => $renderContext->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $renderContext,
        ]));
    }

    /**
     * A permanent redirect to the entry's current URL in the locale, keeping the query string.
     * See "Old slugs" in docs/spec/urls-and-seo.md
     */
    private function redirect(Request $request, string $localeCode, string $path): RedirectResponse
    {
        $url = $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => $localeCode, 'path' => $path]);
        $query = $request->getQueryString();

        return new RedirectResponse(null === $query ? $url : $url . '?' . $query, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * In the builder's preview, the edited entry renders its working copy, whatever its own visibility
     */
    private function previewedEntry(?EntryUrlMatch $match, string $localeCode): ?EntryInterface
    {
        if (null === $match) {
            return null;
        }

        // An old slug of the previewed entry redirects to its URL, where the preview shows it
        if (null !== $match->redirectTo) {
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
