<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Controller\Shop\SnippetPreviewAction;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where a snippet's preview and share links show it: the host page of one of its placements, the page of a sample
 * object of a context key the placement's slot provides, with the snippet's draft in the slot. A snippet with no
 * placement, or one in a slot whose page the plugin doesn't know, shows on a plain page, with a sample object of its
 * declared context. See "Snippet preview" in docs/spec/builder.md and "Share links" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class SnippetHostPages
{
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private SampleObjects $samples,
        private HostPageUrlGeneratorInterface $hostPageUrls,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The snippet's placement with the UUID
     *
     * @throws \InvalidArgumentException when the snippet has no placement with the UUID
     */
    public function placement(SnippetInterface $snippet, string $uuid): PlacementInterface
    {
        return $this->placements->findOneOfSnippet($snippet, $uuid) ?? throw new \InvalidArgumentException(sprintf('The snippet has no placement %s', $uuid));
    }

    /**
     * The context key of the snippet's sample object: the first one the placement's slot provides that has sample
     * objects, or without a placement, or with one in a slot the code no longer declares, the first one the snippet
     * declares. Null when there's none, such as for a snippet for any page in shop.footer
     */
    public function sampleKey(SnippetInterface $snippet, ?PlacementInterface $placement): ?string
    {
        return SampleObjects::keyOf($this->slotOf($placement)?->provides ?? $snippet->getContext());
    }

    /**
     * The sample object the snippet shows with: the one with the ref, while the shopper can see it, or else the one a
     * preview of the placement starts with, whose page shows the placement's slot and which matches its page scope if
     * one does
     *
     * @param string|null $ref the ref of the sample object the editor picked, or null for the starting one
     */
    public function sample(SnippetInterface $snippet, ?PlacementInterface $placement, ?string $ref, RenderContext $context): ?object
    {
        $key = $this->sampleKey($snippet, $placement);
        if (null === $key) {
            return null;
        }

        $picked = null === $ref ? null : $this->samples->load(new SampleObject($key, $ref), $context);

        return $picked ?? $this->samples->starting($key, $placement?->getPageScope() ?? [], $context, $this->slotOf($placement));
    }

    /**
     * The URL of the page the snippet shows on, on the router's host, with the query, such as the preview's token
     *
     * @param array<string, string> $query
     */
    public function url(?PlacementInterface $placement, ?object $sample, ChannelInterface $channel, string $localeCode, array $query): string
    {
        $slot = $this->slotOf($placement);
        if (null !== $slot) {
            $url = $this->hostPageUrls->generate($slot, $sample, $channel, $localeCode);
            if (null !== $url) {
                return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
            }
        }

        return $this->urlGenerator->generate(SnippetPreviewAction::ROUTE, ['_locale' => $localeCode] + $query, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The placement's slot, or null without a placement, or for one in a slot the code no longer declares
     */
    private function slotOf(?PlacementInterface $placement): ?SlotMetadata
    {
        return null === $placement ? null : $this->slots->find($placement->getSlot());
    }
}
