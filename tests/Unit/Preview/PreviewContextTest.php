<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PreviewContextTest extends TestCase
{
    private const ENTRY = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private MockClock $clock;

    private PreviewTokenSigner $signer;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 10:00:00 UTC');
        $this->signer = new PreviewTokenSigner('secret', $this->clock);
        $this->requestStack = new RequestStack();
    }

    /**
     * @test
     */
    public function a_valid_token_on_the_main_request_turns_on_builder_preview_mode(): void
    {
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));

        $context = $this->context();

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'en_US'), $context->getPreview());
        self::assertSame('builder', $context->getMode());
    }

    /**
     * @test
     */
    public function a_shopper_request_has_no_preview(): void
    {
        $this->requestStack->push(self::request());

        self::assertNull($this->context()->getPreview());
        self::assertNull($this->context()->getMode());
    }

    /**
     * @test
     */
    public function there_is_no_preview_outside_a_request(): void
    {
        self::assertNull($this->context()->getPreview());
        self::assertNull($this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_reads_the_token_from_the_main_request_also_inside_a_sub_request(): void
    {
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));
        $this->requestStack->push(self::request());

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'en_US'), $this->context()->getPreview());
    }

    /**
     * @test
     */
    public function it_ignores_a_token_in_a_sub_request(): void
    {
        $this->requestStack->push(self::request());
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));

        self::assertNull($this->context()->getPreview());
    }

    /**
     * @test
     *
     * @dataProvider invalidTokens
     */
    public function it_ignores_an_invalid_token(mixed $token): void
    {
        $this->requestStack->push(self::request(['_cms_preview' => $token]));

        self::assertNull($this->context()->getPreview());
        self::assertNull($this->context()->getMode());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'tampered' => ['eyJlbnRyeSI6ImEifQ.c2lnbmF0dXJl'];
        yield 'empty' => [''];
        yield 'an array' => [['a', 'b']];
    }

    /**
     * @test
     */
    public function it_ignores_an_expired_token(): void
    {
        $token = $this->token();
        $this->clock->sleep(PreviewTokenSigner::LIFETIME);
        $this->requestStack->push(self::request(['_cms_preview' => $token]));

        self::assertNull($this->context()->getPreview());
    }

    /**
     * @test
     */
    public function a_request_keeps_the_preview_it_started_with_even_when_its_token_expires_meanwhile(): void
    {
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));
        $context = $this->context();
        self::assertNotNull($context->getPreview());

        $this->clock->sleep(PreviewTokenSigner::LIFETIME);

        self::assertNotNull($context->getPreview());
    }

    /**
     * @test
     */
    public function each_main_request_has_its_own_preview(): void
    {
        $context = $this->context();
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));
        self::assertNotNull($context->getPreview());
        $this->requestStack->pop();

        $this->requestStack->push(self::request());

        self::assertNull($context->getPreview());
    }

    /**
     * @test
     */
    public function a_request_without_a_preview_stays_without_one(): void
    {
        $request = self::request();
        $this->requestStack->push($request);
        $context = $this->context();
        self::assertNull($context->getPreview());

        $request->query->set('_cms_preview', $this->token());

        self::assertNull($context->getPreview());
    }

    private function context(): PreviewContext
    {
        return new PreviewContext($this->requestStack, $this->signer);
    }

    private function token(): string
    {
        return $this->signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function request(array $query = []): Request
    {
        return new Request($query);
    }
}
