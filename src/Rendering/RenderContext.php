<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

/**
 * Everything the read pipeline may read about the shopper and the page. Nothing in the pipeline reads
 * the session, the request or the "sylius" Twig global. See "One read pipeline" in docs/spec/rendering.md
 */
final readonly class RenderContext
{
    public const PREVIEW_MODE_BUILDER = 'builder';

    public const PREVIEW_MODE_SHARE = 'share';

    /**
     * @param string $customerGroup a customer group code, "@guest" or "@no_group": the preview's Shown to in preview mode
     * @param \DateTimeImmutable $now the time date windows are compared with: the preview's Date in preview mode, which
     *                                publishes nothing
     * @param array<string, object> $hostPageContext context key => object
     * @param self::PREVIEW_MODE_*|null $previewMode
     */
    public function __construct(
        public ChannelInterface $channel,
        public string $localeCode,
        public string $currencyCode,
        public ?CustomerInterface $customer,
        public string $customerGroup,
        public \DateTimeImmutable $now,
        private array $hostPageContext = [],
        public ?string $previewMode = null,
    ) {
    }

    /**
     * The host-page context object for this key, or null on pages that don't have one
     */
    public function get(string $key): ?object
    {
        return $this->hostPageContext[$key] ?? null;
    }

    /**
     * @return array<string, object>
     */
    public function getHostPageContext(): array
    {
        return $this->hostPageContext;
    }

    /**
     * The same context in another preview mode, such as none for what a preview shows as shoppers see it, which keeps
     * the preview's Shown to and Date
     *
     * @param self::PREVIEW_MODE_*|null $previewMode
     */
    public function withPreviewMode(?string $previewMode): self
    {
        return new self($this->channel, $this->localeCode, $this->currencyCode, $this->customer, $this->customerGroup, $this->now, $this->hostPageContext, $previewMode);
    }
}
