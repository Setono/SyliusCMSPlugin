<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Context\HostPageContextResolver;
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
 * 1. its context: the context keys it provides, taken from what it's passed, and those it isn't passed from their
 *    context resolvers. A key that can't be resolved, such as the product on a page that isn't a product page, makes it
 *    print nothing
 * 2. the channel's placements in it, which load once a request, when the first slot renders
 * 3. those whose snippet is visible and needs no more context than the slot provides, in editor order (page scopes
 *    join with #258)
 * 4. its cap (#259)
 * 5. each shown snippet's tree, rendered with the slot's context in the slot template, inside the slot's wrapper
 *
 * It takes the placements before it resolves its context, so a slot without placements resolves nothing: a page
 * doesn't look up its product for an empty product.top. A slot with nothing to show prints nothing, not even its
 * wrapper
 */
final class SlotRenderer implements SlotRendererInterface, ResetInterface
{
    /** The template a slot with something to show renders in. It gets slot, snippets (PlacedSnippet), context and the render context */
    public const TEMPLATE = '@SetonoSyliusCMSPlugin/slot/slot.html.twig';

    /** @var array<string, array<string, list<PlacementData>>> channel code => slot identifier => its placements */
    private array $placements = [];

    public function __construct(
        private readonly SlotRegistryInterface $slots,
        private readonly HostPageContextResolver $hostPageContext,
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

        // 3. Those whose snippet is visible and needs no more context than the slot provides, in editor order
        $shown = $this->withVisibleSnippets($slot, $placements, $renderContext);

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
     * The placements whose snippet is visible, each with its snippet, in the order given. A snippet whose published
     * content needs a context key the slot doesn't provide, such as one the code no longer declares, isn't shown
     *
     * @param non-empty-list<PlacementData> $placements
     *
     * @return list<array{PlacementData, SnippetInterface}>
     */
    private function withVisibleSnippets(SlotMetadata $slot, array $placements, RenderContext $context): array
    {
        $snippets = $this->visibleSnippets->get(
            array_values(array_unique(array_map(static fn (PlacementData $placement): string => $placement->snippetUuid, $placements))),
            $context,
        );

        $shown = [];
        foreach ($placements as $placement) {
            $snippet = $snippets[$placement->snippetUuid] ?? null;
            if (null !== $snippet && $slot->providesAll($snippet->getContext())) {
                $shown[] = [$placement, $snippet];
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
            $blocks = $this->blockViewBuilder->build($trees[$index]->blocks, $context, $references, $snippet->getUuid());
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
