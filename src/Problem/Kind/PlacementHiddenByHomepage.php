<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A placement in a slot on Sylius's own homepage, such as store_homepage, whose snippet is in a channel whose homepage
 * is a CMS page: that channel doesn't render Sylius's homepage, so the placement doesn't show there. A warning for each
 * such placement, naming those channels, fixed in the Placements section of the snippet's overview page. A page made a
 * channel's homepage, or no longer one, has every snippet placed on Sylius's homepage counted again. setono:cms:lint
 * doesn't report it. See "Rendering the homepage" in docs/spec/urls-and-seo.md and "Problems" in docs/spec/admin.md
 */
final readonly class PlacementHiddenByHomepage implements ReferenceProblemKindInterface
{
    public const CODE = 'placement_hidden_by_homepage';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private HomepageRepositoryInterface $homepages,
        private ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [HomepageInterface::class];
    }

    /**
     * None: a homepage row added or removed is what changes the problem
     */
    public static function getReferenceFields(): array
    {
        return [];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        $onStoreHomepage = array_values(array_map(
            static fn (SlotMetadata $slot): string => $slot->identifier,
            array_filter($this->slots->all(), static fn (SlotMetadata $slot): bool => $slot->isOnStoreHomepage()),
        ));

        $snippets = [];
        foreach ($this->placements->findBySlots($onStoreHomepage) as $placement) {
            $snippets[$placement->getSnippet()->getUuid()] ??= $placement->getSnippet();
        }

        return array_values($snippets);
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        $onStoreHomepage = array_values(array_filter(
            $this->placements->findBySnippet($entry),
            fn (PlacementInterface $placement): bool => $this->slots->has($placement->getSlot()) && $this->slots->get($placement->getSlot())->isOnStoreHomepage(),
        ));
        if ([] === $onStoreHomepage) {
            return;
        }

        $channels = $this->channelsWithCMSHomepage($entry->getChannelCodes());
        if ([] === $channels) {
            return;
        }

        foreach ($onStoreHomepage as $placement) {
            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.placement_hidden_by_homepage', [
                    '%slot%' => new TranslatableMessage($this->slots->get($placement->getSlot())->label),
                    '%channels%' => implode(', ', $channels),
                ]),
                Fix::inPlacements($entry),
            );
        }
    }

    /**
     * Of these channels, those whose homepage is a CMS page, by name, or by code for a deleted one, in code order
     *
     * @param list<string> $channelCodes
     *
     * @return list<string>
     */
    private function channelsWithCMSHomepage(array $channelCodes): array
    {
        sort($channelCodes);
        $codes = array_map(static fn (HomepageInterface $homepage): string => $homepage->getChannelCode(), $this->homepages->findByChannelCodes($channelCodes));
        if ([] === $codes) {
            return [];
        }

        sort($codes);
        $names = [];
        foreach ($this->channelRepository->findBy(['code' => $codes]) as $channel) {
            $names[(string) $channel->getCode()] = $channel->getName() ?? (string) $channel->getCode();
        }

        return array_map(static fn (string $code): string => $names[$code] ?? $code, $codes);
    }
}
