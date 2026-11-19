<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * The displays content types have cards for, such as grid, carousel and accordion, which the Entries block lists its
 * entries in: each entry renders through its own content type's card for the block's display. The block offers the
 * displays the listed content type has cards for, those every picked entry's content type has when they're
 * hand-picked, or every display when it can't tell. See "The Entries block" and "Cards" in docs/spec/collections.md
 */
final readonly class CardDisplays
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
    }

    /**
     * Every display a content type that isn't switched off has a card for, each once, in the order the registry has the
     * content types, and each content type its cards
     *
     * @return list<string>
     */
    public function all(): array
    {
        $displays = [];
        foreach ($this->contentTypes->enabled() as $contentType) {
            foreach ($contentType->cards as $card) {
                $displays[$card->display] = true;
            }
        }

        return array_keys($displays);
    }

    /**
     * The displays the Entries block offers for entries of the content types: those every one of them has a card for,
     * in the order the first has them, or every display when it can't tell, given no content type the code has
     *
     * @param list<string>|null $contentTypes the codes of the listed content types, or null when it can't tell
     *
     * @return list<string>
     */
    public function offeredFor(?array $contentTypes): array
    {
        $known = array_values(array_filter(array_unique($contentTypes ?? []), $this->contentTypes->has(...)));
        if ([] === $known) {
            return $this->all();
        }

        $offered = null;
        foreach ($known as $code) {
            $displays = array_map(static fn (Card $card): string => $card->display, $this->contentTypes->get($code)->cards);
            $offered = null === $offered ? $displays : array_values(array_intersect($offered, $displays));
        }

        return $offered ?? [];
    }

    /**
     * The card the content type has for the display, or null when it has none, or the code has no such content type
     */
    public function cardOf(string $contentType, string $display): ?Card
    {
        if (!$this->contentTypes->has($contentType)) {
            return null;
        }

        foreach ($this->contentTypes->get($contentType)->cards as $card) {
            if ($card->display === $display) {
                return $card;
            }
        }

        return null;
    }
}
