<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Context\HostPageContextResolverInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownSlotException;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * Renders a slot in the steps of "Rendering a slot" in docs/spec/rendering.md:
 *
 * 1. its context: the context keys it provides, taken from what it's passed, and those it isn't passed from their
 *    context resolvers. A key that can't be resolved, such as the product on a page that isn't a product page, makes it
 *    print nothing
 * 2. the channel's placements in it, which load once a request, when the first slot renders
 * 3. those whose page scope matches the page, then of those, the ones whose snippet is visible and needs no more context
 *    than the slot provides, in editor order
 * 4. of those, in a capped slot, the first `cap`. Steps 3 and 4 are PlacementPickerInterface's, which decides without
 *    rendering, so a snippet whose blocks are all hidden still wins and shows nothing
 * 5. each winning snippet's tree, rendered with the slot's context in the slot template, inside the slot's wrapper
 *
 * A slot the code doesn't declare, such as one a cms_slot() call misspells, goes to the failure handler, and in
 * production prints nothing. A winning snippet whose tree can't be read, such as one no upgrader brings up to date,
 * goes to the failure handler, and in production the others still show. A block that throws is BlockViewBuilder's and the block renderer's
 * (FailSafeBlockRenderer)
 *
 * It takes the placements before it resolves its context, so a slot without placements resolves nothing: a page
 * doesn't look up its product for an empty product.top. A slot with nothing to show prints nothing, not even its
 * wrapper
 */
final class SlotRenderer implements SlotRendererInterface, ResetInterface
{
    /**
     * The template a slot with something to show renders in. It gets slot, snippets (PlacedSnippet), whether it's
     * outlined for the builder's preview of a snippet, context and the render context
     */
    public const TEMPLATE = '@SetonoSyliusCMSPlugin/slot/slot.html.twig';

    /** @var array<string, list<PlacementData>> channel code => its placements */
    private array $placements = [];

    public function __construct(
        private readonly SlotRegistryInterface $slots,
        private readonly HostPageContextResolverInterface $hostPageContext,
        private readonly RenderContextFactoryInterface $renderContextFactory,
        private readonly PlacementProviderInterface $placementProvider,
        private readonly PlacementPickerInterface $placementPicker,
        private readonly StoredDataUpgraderInterface $upgrader,
        private readonly BlockViewBuilder $blockViewBuilder,
        private readonly ReferenceLoaderInterface $referenceLoader,
        private readonly Environment $twig,
        private readonly FailureHandlerInterface $failures,
    ) {
    }

    public function render(string $identifier, array $context = []): string
    {
        $slot = $this->slots->find($identifier);
        if (null === $slot) {
            $this->failures->slotFailed($identifier, new UnknownSlotException($identifier));

            return '';
        }

        // 2. The channel's placements in it, before its context, which a slot without placements doesn't need
        $placements = $this->placementsIn($slot, $this->renderContextFactory->create()->channel);
        if ([] === $placements) {
            return '';
        }

        // 1. The context keys it provides, of what it's passed and from their resolvers
        $provided = $this->hostPageContext->resolve($slot->provides, $context);
        if (null === $provided) {
            return '';
        }

        $renderContext = $this->renderContextFactory->create($provided);

        // 3. Those whose page scope matches the page, and whose snippet is visible and needs no more context than the slot
        // provides, in editor order, and 4. in a capped slot, the first `cap` of them, then 5. each winning snippet's tree
        // with the slot's context
        return $this->renderSnippets($slot, $this->placementPicker->pick($slot, $placements, $renderContext), $renderContext);
    }

    /**
     * Each request reads the placements again
     */
    public function reset(): void
    {
        $this->placements = [];
    }

    /**
     * The placements in the slot, in editor order. All of the channel's placements are read once, and every later slot
     * reuses them
     *
     * @return list<PlacementData>
     */
    private function placementsIn(SlotMetadata $slot, ChannelInterface $channel): array
    {
        $channelCode = (string) $channel->getCode();

        return PlacementData::inSlot($this->placements[$channelCode] ??= $this->placementProvider->getPlacements($channel), $slot->identifier);
    }

    /**
     * The winning snippets' references load together, in one pass, before any of them renders. A snippet whose blocks
     * all stay hidden shows nothing, and a slot whose snippets show nothing prints nothing. In the builder's preview of
     * a snippet, its draft is marked for the builder, in the slot outlined and labelled, even when none of its blocks
     * show, so the editor sees where it goes
     *
     * @param list<CandidatePlacement> $candidates every placement in the slot, with whether it wins
     */
    private function renderSnippets(SlotMetadata $slot, array $candidates, RenderContext $context): string
    {
        $trees = [];
        foreach ($candidates as $index => $candidate) {
            if (!$candidate->wins()) {
                continue;
            }

            try {
                $trees[$index] = $this->upgrader->readBlockTree($candidate->snippet);
            } catch (\Throwable $e) {
                $this->failures->snippetFailed($candidate->placement, $e);
            }
        }

        if ([] === $trees) {
            return '';
        }

        $shoppers = self::asShoppersSeeIt($context);

        $references = $this->referenceLoader->load((function () use ($trees): \Generator {
            foreach ($trees as $tree) {
                yield from $this->blockViewBuilder->references($tree->blocks);
            }
        })(), $shoppers);

        $outlined = false;
        $snippets = [];
        foreach ($candidates as $index => $winner) {
            // Each winner whose tree could be read
            if (!$winner->wins() || !isset($trees[$index])) {
                continue;
            }

            $marked = $winner->previewed && RenderContext::PREVIEW_MODE_BUILDER === $context->previewMode;
            $snippetContext = $marked ? $context : $shoppers;
            $blocks = $this->blockViewBuilder->build($trees[$index]->blocks, $snippetContext, $references, $winner->snippet->getUuid());
            if ([] !== $blocks || $marked) {
                $snippets[] = new PlacedSnippet($winner->placement, $winner->snippet, $blocks, $winner->previewed, $snippetContext);
                $outlined = $outlined || $marked;
            }
        }

        if ([] === $snippets) {
            return '';
        }

        return $this->twig->render(self::TEMPLATE, [
            'slot' => $slot,
            'snippets' => $snippets,
            'outlined' => $outlined,
            'context' => $context->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $shoppers,
        ]);
    }

    /**
     * In the builder's preview, only the edited entry is marked for the builder to select and type in. Snippets in
     * slots show as shoppers see them, with the preview's Shown to and Date, but for a snippet's own draft in the slot
     * of the placement its preview shows
     */
    private static function asShoppersSeeIt(RenderContext $context): RenderContext
    {
        return RenderContext::PREVIEW_MODE_BUILDER === $context->previewMode ? $context->withPreviewMode(null) : $context;
    }
}
