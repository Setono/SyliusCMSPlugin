<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewLocaleContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PreviewLocaleContextTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_the_previews_locale(): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $requestStack = new RequestStack();
        $requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'da_DK'))]));

        self::assertSame('da_DK', (new PreviewLocaleContext(new PreviewContext($requestStack, $signer)))->getLocaleCode());
    }

    /**
     * @test
     */
    public function it_leaves_a_request_that_previews_nothing_to_the_other_locale_contexts(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $this->expectException(LocaleNotFoundException::class);

        (new PreviewLocaleContext(new PreviewContext($requestStack, new PreviewTokenSigner('secret', new MockClock()))))->getLocaleCode();
    }
}
