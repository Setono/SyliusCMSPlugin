<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Collection\CardDisplays;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_card(): an entry listed among others, through the card its content type declares for the display, such as the
 * Entries block's grid. See "Cards" in docs/spec/collections.md
 */
final readonly class CardRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private CardDisplays $cardDisplays,
        private RenderContexts $renderContexts,
    ) {
    }

    /**
     * Renders the card the entry's content type has for the display, which gets entry and display, and the render
     * context, so it reads the entry's fields with cms_value() and cms_field(). Nothing when it has no card for it
     *
     * @param array<string, mixed> $context
     */
    public function card(Environment $environment, array $context, EntryInterface $entry, string $display): string
    {
        try {
            $card = $this->cardDisplays->cardOf($this->contentTypes->getByEntry($entry)->code, $display);
        } catch (UnknownContentTypeException) {
            return '';
        }

        if (null === $card) {
            return '';
        }

        return $environment->render($card->template, [
            'entry' => $entry,
            'display' => $display,
            CMSRuntime::RENDER_CONTEXT => $this->renderContexts->of($context),
        ]);
    }
}
