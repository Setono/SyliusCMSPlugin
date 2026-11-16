<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The share_link resource's factory: it creates share links of the configured model, each with its own random token,
 * which alone decides what the link shows. A token is 32 random bytes in base64url without padding, so it goes into a
 * URL's query string as it is. See "Share links" in docs/spec/publishing.md
 *
 * @implements FactoryInterface<ShareLinkInterface>
 */
final readonly class ShareLinkFactory implements FactoryInterface
{
    /**
     * @param class-string<ShareLinkInterface> $className the share_link resource's model
     */
    public function __construct(private string $className)
    {
    }

    public function createNew(): ShareLinkInterface
    {
        $shareLink = new $this->className();
        $shareLink->setToken(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));

        return $shareLink;
    }
}
