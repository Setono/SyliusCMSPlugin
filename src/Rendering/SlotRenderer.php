<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * Renders a slot in the steps of "Rendering a slot" in docs/spec/rendering.md:
 *
 * 1. its context: the context keys it provides, taken from what it's passed (context resolvers join with #257)
 * 2. the channel's placements in it, which load once a request, when the first slot renders
 * 3. those whose snippet is visible, in editor order (page scopes join with #258)
 * 4. its cap (#259)
 * 5. each shown snippet's tree, rendered with the slot's context in the slot template, inside the slot's wrapper
 *
 * A slot with nothing to show prints nothing, not even its wrapper
 */
final class SlotRenderer implements SlotRendererInterface, ResetInterface
{
    /** The template a slot with something to show renders in. It gets slot, snippets (PlacedSnippet), context and the render context */
    public const TEMPLATE = '@SetonoSyliusCMSPlugin/slot/slot.html.twig';

    /** @var array<string, array<string, list<PlacementData>>> channel code => slot identifier => its placements */
    private array $placements = [];

    public function __construct(
        private readonly SlotRegistryInterface $slots,
        private readonly RenderContextFactoryInterface $renderContextFactory,
        private readonly PlacementProviderInterface $placementProvider,
        private readonly VisibleSnippetsInterface $visibleSnippets,
        private readonly StoredDataUpgraderInterface $upgrader,
        private readonly BlockViewBuilder $blockViewBuilder,
        private readonly ReferenceLoaderInterface $referenceLoader,
        private readonly Environment $twig,
    ) {
    }

    public function render(string $identifier, array $context = []): string
    {
        $slot = $this->slots->get($identifier);

        // 1. The context keys it provides, of what it's passed
        $renderContext = $this->renderContextFactory->create(self::providedContext($slot, $context));

        // 2. The channel's placements in it
        $placements = $this->placementsIn($slot, $renderContext->channel);
        if ([] === $placements) {
            return '';
        }

        // 3. Those whose snippet is visible, in editor order
        $shown = $this->withVisibleSnippets($placements, $renderContext);

        // 4. The cap joins here with #259

        // 5. Each snippet's tree with the slot's context
        return $this->renderSnippets($slot, $shown, $renderContext);
    }

    /**
     * Each request reads the placements again
     */
    public function reset(): void
    {
        $this->placements = [];
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<string, object>
     */
    private static function providedContext(SlotMetadata $slot, array $context): array
    {
        $provided = [];
        foreach ($slot->provides as $key) {
            if (isset($context[$key]) && is_object($context[$key])) {
                $provided[$key] = $context[$key];
            }
        }

        return $provided;
    }

    /**
     * The placements in the slot, in editor order: by position, and placements at the same position in the order the
     * provider lists them. All of the channel's placements are read once, and every later slot reuses them
     *
     * @return list<PlacementData>
     */
    private function placementsIn(SlotMetadata $slot, ChannelInterface $channel): array
    {
        $channelCode = (string) $channel->getCode();

        if (!isset($this->placements[$channelCode])) {
            $this->placements[$channelCode] = [];
            foreach ($this->placementProvider->getPlacements($channel) as $placement) {
                $this->placements[$channelCode][$placement->slot][] = $placement;
            }
        }

        $placements = $this->placements[$channelCode][$slot->identifier] ?? [];
        usort($placements, static fn (PlacementData $a, PlacementData $b): int => $a->position <=> $b->position);

        return $placements;
    }

    /**
     * The placements whose snippet is visible, each with its snippet, in the order given
     *
     * @param non-empty-list<PlacementData> $placements
     *
     * @return list<array{PlacementData, SnippetInterface}>
     */
    private function withVisibleSnippets(array $placements, RenderContext $context): array
    {
        $snippets = $this->visibleSnippets->get(
            array_values(array_unique(array_map(static fn (PlacementData $placement): string => $placement->snippetUuid, $placements))),
            $context,
        );

        $shown = [];
        foreach ($placements as $placement) {
            if (isset($snippets[$placement->snippetUuid])) {
                $shown[] = [$placement, $snippets[$placement->snippetUuid]];
            }
        }

        return $shown;
    }

    /**
     * The shown snippets' references load together, in one pass, before any of them renders. A snippet whose blocks
     * all stay hidden shows nothing, and a slot whose snippets show nothing prints nothing
     *
     * @param list<array{PlacementData, SnippetInterface}> $shown
     */
    private function renderSnippets(SlotMetadata $slot, array $shown, RenderContext $context): string
    {
        if ([] === $shown) {
            return '';
        }

        $context = self::asShoppersSeeIt($context);

        $trees = array_map(fn (array $placed): StoredTree => $this->upgrader->readBlockTree($placed[1]), $shown);
        $references = $this->referenceLoader->load((function () use ($trees): \Generator {
            foreach ($trees as $tree) {
                yield from $this->blockViewBuilder->references($tree->blocks);
            }
        })(), $context);

        $snippets = [];
        foreach ($shown as $index => [$placement, $snippet]) {
            $blocks = $this->blockViewBuilder->build($trees[$index]->blocks, $context, $references);
            if ([] !== $blocks) {
                $snippets[] = new PlacedSnippet($placement, $snippet, $blocks);
            }
        }

        if ([] === $snippets) {
            return '';
        }

        return $this->twig->render(self::TEMPLATE, [
            'slot' => $slot,
            'snippets' => $snippets,
            'context' => $context->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $context,
        ]);
    }

    /**
     * In the builder's preview, only the edited entry is marked for the builder to select and type in. Snippets in
     * slots show as shoppers see them, with the preview's Shown to and Date. #260 puts the previewed snippet's draft in
     * its slot
     */
    private static function asShoppersSeeIt(RenderContext $context): RenderContext
    {
        return RenderContext::PREVIEW_MODE_BUILDER === $context->previewMode ? $context->withPreviewMode(null) : $context;
    }
}
