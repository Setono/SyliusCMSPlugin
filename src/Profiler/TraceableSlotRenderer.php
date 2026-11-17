<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Rendering\SlotRendererInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Records each slot rendered, in a sub-request too, with how long it took, for the profiler panel. The other traceable
 * decorators record what happens inside it. Registered in debug only
 *
 * @internal
 */
final readonly class TraceableSlotRenderer implements SlotRendererInterface
{
    public function __construct(
        private SlotRendererInterface $renderer,
        private RenderedSlots $slots,
        private RequestStack $requestStack,
    ) {
    }

    public function render(string $identifier, array $context = []): string
    {
        $slot = $this->slots->start($identifier, $this->subRequest());
        $start = hrtime(true);

        try {
            return $this->renderer->render($identifier, $context);
        } finally {
            $this->slots->end($slot, (hrtime(true) - $start) / 1_000_000);
        }
    }

    /**
     * The route of the sub-request rendering now, or null in the main request
     */
    private function subRequest(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || $request === $this->requestStack->getMainRequest()) {
            return null;
        }

        $route = $request->attributes->get('_route');

        return is_string($route) ? $route : $request->getPathInfo();
    }
}
