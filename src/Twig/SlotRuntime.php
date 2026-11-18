<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Rendering\SlotRendererInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_slot(): renders a slot from a template. See "Rendering a slot" in docs/spec/slots.md
 */
final readonly class SlotRuntime implements RuntimeExtensionInterface
{
    public function __construct(private SlotRendererInterface $slotRenderer)
    {
    }

    /**
     * @param array<array-key, mixed> $context host-page context by key, such as {store_location: location}. The slot
     *                                         takes the keys it provides
     */
    public function slot(string $identifier, array $context = []): string
    {
        return $this->slotRenderer->render($identifier, $context);
    }
}
