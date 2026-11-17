<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\SampleObjects;
use Setono\SyliusCMSPlugin\Preview\SnippetHostPages;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the sample object picker of a snippet's preview finds for the query in `q`, under the token in the query
 * parameter _cms_preview: the objects of the context key of the preview's placement, or of the snippet's declared
 * context, that the builder's search of the key finds and shoppers can see, each `{ref, name, thumbnail, matches}`,
 * where matches says whether it's inside the placement's page scope, or is null without a placement. It changes
 * nothing, so it needs no CSRF token. See "Snippet preview" in docs/spec/builder.md
 */
final readonly class PreviewSamplesAction
{
    /**
     * @param ContainerInterface $searches the builder's searches by kind
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private RenderContextFactoryInterface $renderContextFactory,
        private PlacementRepositoryInterface $placements,
        private SnippetHostPages $snippetHostPages,
        private SampleObjects $samples,
        private ReferenceLoaderInterface $referenceLoader,
        private ContainerInterface $searches,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $preview = $this->previewContext->getPreview();
        $snippet = null === $preview ? null : $this->previewedEntry->getPreviewedContent($preview->localeCode);
        if (null === $preview || !$snippet instanceof SnippetInterface) {
            return BuilderRequest::badRequest('The request has no valid preview token of a snippet');
        }

        $placement = null === $preview->placementUuid ? null : $this->placements->findOneByUuid($preview->placementUuid);
        $key = $this->snippetHostPages->sampleKey($snippet, $placement);
        $search = null === $key || !$this->searches->has($key) ? null : $this->searches->get($key);
        if (null === $key || !$search instanceof SearchInterface) {
            return new JsonResponse([]);
        }

        $query = $request->query->all()['q'] ?? '';
        $found = $search->search(is_string($query) ? $query : '', $request->getLocale());

        // Only what shoppers can see has a page to show
        $context = $this->renderContextFactory->create();
        $loaded = $this->referenceLoader->load(array_map(static fn (SearchResult $result): Reference => new Reference($key, $result->ref, false), $found), $context);
        $objects = [];
        foreach ($found as $result) {
            $object = $loaded->get($key, $result->ref);
            if (null !== $object) {
                $objects[$result->ref] = $object;
            }
        }

        $matches = null === $placement ? [] : $this->samples->matchesEach($placement->getPageScope(), $key, $objects, $context);

        return new JsonResponse(array_values(array_map(static fn (SearchResult $result): array => $result->jsonSerialize() + ['matches' => $matches[$result->ref] ?? null], array_filter(
            $found,
            static fn (SearchResult $result): bool => isset($objects[$result->ref]),
        ))));
    }
}
