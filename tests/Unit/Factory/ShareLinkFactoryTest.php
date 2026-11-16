<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Factory\ShareLinkFactory;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectShareLink;

/**
 * The share_link resource's factory gives every new share link its own random token, which alone decides what the link
 * shows. See "Share links" in docs/spec/publishing.md
 */
final class ShareLinkFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_a_share_link_of_the_configured_model(): void
    {
        $shareLink = (new ShareLinkFactory(ProjectShareLink::class))->createNew();

        self::assertInstanceOf(ProjectShareLink::class, $shareLink);
        self::assertNull($shareLink->getId());
    }

    /**
     * 32 random bytes, base64url without padding, so it fits a URL's query string as it is
     *
     * @test
     */
    public function it_gives_a_new_share_link_a_random_url_safe_token_of_256_bits(): void
    {
        $factory = new ShareLinkFactory(ShareLink::class);

        $tokens = [];
        for ($i = 0; $i < 50; ++$i) {
            $token = $factory->createNew()->getToken();
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
            $tokens[] = $token;
        }

        self::assertCount(50, array_unique($tokens), 'Every share link gets a token of its own');
        // base64url's own characters, which base64 would write as + and /, show up in 50 random tokens
        self::assertStringContainsString('-', implode('', $tokens));
        self::assertStringContainsString('_', implode('', $tokens));
    }
}
