<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewedHostPageContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedSlot;
use Setono\SyliusCMSPlugin\Preview\SampleObject;
use Setono\SyliusCMSPlugin\Preview\SampleObjects;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementPickerInterface;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the builder's top bar says about the snippet preview under the token in the query parameter _cms_preview: its
 * sample object, `{key, ref, name, matches}`, with whether it's inside the placement's page scope (null without a
 * placement), or null without one, and in `shownInstead` the titles of the snippets that shoppers like the preview's
 * Shown to see in the placement's capped slot, where it loses it, or null. It changes nothing, so it needs no CSRF
 * token. See "Snippet preview" in docs/spec/builder.md
 */
final readonly class PreviewSnippetAction
{
    /**
     * @param PlacementPickerInterface $placementPicker the picker that picks for shoppers, not the preview's
     * @param ContainerInterface $searches the builder's searches by kind, which name the sample object
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private PreviewedHostPageContext $hostPageContext,
        private RenderContextFactoryInterface $renderContextFactory,
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private SampleObjects $samples,
        private PlacementProviderInterface $placementProvider,
        private PlacementPickerInterface $placementPicker,
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

        $context = $this->renderContextFactory->create($this->hostPageContext->of($snippet));
        $placement = null === $preview->placementUuid ? null : $this->placements->findOneByUuid($preview->placementUuid);

        return new JsonResponse([
            'sample' => null === $preview->sample ? null : $this->sample($preview->sample, $placement, $context, $request->getLocale()),
            'shownInstead' => null === $placement ? null : $this->shownInstead($placement, $context),
        ]);
    }

    /**
     * @return array{key: string, ref: string, name: string, matches: bool|null}|null
     */
    private function sample(SampleObject $sample, ?PlacementInterface $placement, RenderContext $context, string $adminLocale): ?array
    {
        $object = $context->get($sample->key);
        if (null === $object) {
            return null;
        }

        $search = $this->searches->has($sample->key) ? $this->searches->get($sample->key) : null;
        $found = $search instanceof SearchInterface ? ($search->find([$sample->ref], $adminLocale)[0] ?? null) : null;

        return [
            'key' => $sample->key,
            'ref' => $sample->ref,
            'name' => $found->name ?? $sample->ref,
            'matches' => null === $placement ? null : $this->samples->matches($placement->getPageScope(), $sample->key, $object, $context),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function shownInstead(PlacementInterface $placement, RenderContext $context): ?array
    {
        $slot = $this->slots->find($placement->getSlot());
        if (null === $slot?->cap) {
            return null;
        }

        $inSlot = PlacementData::inSlot($this->placementProvider->getPlacements($context->channel), $slot->identifier);

        $snippets = PreviewedSlot::shownInstead($slot, $this->placementPicker->pick($slot, $inSlot, $context), $placement->getUuid());

        return null === $snippets ? null : array_map(static fn (SnippetInterface $snippet): string => (string) $snippet->getTitle(), $snippets);
    }
}
