<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Preview\SampleObject;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PreviewContextTest extends TestCase
{
    use ProphecyTrait;

    private const ENTRY = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SHARE_TOKEN = 'k3Rq0Zl8vN2mX5pT7wY1aB4cD6eF9gH0iJ2kL5mN8oP';

    private MockClock $clock;

    private PreviewTokenSigner $signer;

    private RequestStack $requestStack;

    /** @var ObjectProphecy<ShareLinkRepositoryInterface> */
    private ObjectProphecy $shareLinks;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 10:00:00 UTC');
        $this->signer = new PreviewTokenSigner('secret', $this->clock);
        $this->requestStack = new RequestStack();
        $this->shareLinks = $this->prophesize(ShareLinkRepositoryInterface::class);
        $this->shareLinks->lookUpToken(Argument::any())->willReturn(null);
    }

    /**
     * @test
     */
    public function it_turns_on_builder_preview_mode_for_a_valid_token_on_the_main_request(): void
    {
        $this->requestStack->push(self::request(['_cms_preview' => $this->token()]));

        $context = $this->context();

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'en_US'), $context->getPreview());
        self::assertSame('builder', $context->getMode());
    }

    /**
     * @test
     */
    public function it_gives_a_shopper_request_no_preview(): void
    {
        $this->requestStack->push(self::request());

        self::assertNull($this->context()->getPreview());
        self::assertNull($this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_has_no_preview_outside_a_request(): void
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
    public function it_keeps_the_preview_a_request_started_with_even_when_its_token_expires_meanwhile(): void
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
    public function it_gives_each_main_request_its_own_preview(): void
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
    public function it_keeps_a_request_without_a_preview_without_one(): void
    {
        $request = self::request();
        $this->requestStack->push($request);
        $context = $this->context();
        self::assertNull($context->getPreview());

        $request->query->set('_cms_preview', $this->token());

        self::assertNull($context->getPreview());
    }

    /**
     * A share link shows its entry's working copy, in its channel and locale, to its Shown to at its Date, read-only.
     * See "Share links" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_turns_on_share_mode_for_a_share_links_token_on_the_main_request(): void
    {
        $date = new \DateTimeImmutable('2026-12-01 08:00:00 UTC');
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('wholesale', $date));
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        $context = $this->context();

        $preview = $context->getPreview();
        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'da_DK', null, 'wholesale', $date, true), $preview);
        self::assertTrue($preview?->isReadOnly(), 'A share link has no bridge');
        self::assertSame('share', $context->getMode());
    }

    /**
     * A snippet's share link fixes the placement whose host page shows it and the sample object
     *
     * @test
     */
    public function it_shows_a_snippet_s_share_link_in_its_placement_with_its_sample_object(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(['placement' => '0193a5be-0000-7000-8000-0000000000c1', 'sampleKey' => 'product', 'sampleRef' => 'MUG'] + self::shareLink('@guest', null));
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        $preview = $this->context()->getPreview();

        self::assertSame('0193a5be-0000-7000-8000-0000000000c1', $preview?->placementUuid);
        self::assertEquals(new SampleObject('product', 'MUG'), $preview->sample);
    }

    /**
     * @test
     */
    public function it_shows_a_share_link_at_the_time_it_is_opened_when_it_has_no_date(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('@guest', null));
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'da_DK', null, '@guest', null, true), $this->context()->getPreview());
    }

    /**
     * A revoked share link is gone, so its token answers as for a shopper
     *
     * @test
     */
    public function it_gives_the_token_of_a_revoked_or_unknown_share_link_no_preview(): void
    {
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        self::assertNull($this->context()->getPreview());
        self::assertNull($this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_reads_the_share_token_from_the_main_request_also_inside_a_sub_request(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('@guest', null));
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));
        $this->requestStack->push(self::request());

        self::assertSame('share', $this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_ignores_a_share_token_in_a_sub_request(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('@guest', null));
        $this->requestStack->push(self::request());
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        self::assertNull($this->context()->getPreview());
    }

    /**
     * @test
     *
     * @dataProvider invalidShareTokens
     */
    public function it_looks_up_no_share_link_for_a_share_token_that_is_not_a_string_or_is_empty(mixed $token): void
    {
        $this->shareLinks->lookUpToken(Argument::any())->shouldNotBeCalled();
        $this->requestStack->push(self::request(['_cms_share' => $token]));

        self::assertNull($this->context()->getPreview());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidShareTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'an array' => [['a', 'b']];
    }

    /**
     * @test
     */
    public function it_looks_a_share_link_up_once_per_main_request(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('@guest', null))->shouldBeCalledOnce();
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));
        $context = $this->context();

        self::assertSame('share', $context->getMode());
        self::assertSame('share', $context->getMode());
    }

    /**
     * @test
     */
    public function it_takes_a_valid_preview_token_over_a_share_token(): void
    {
        $this->shareLinks->lookUpToken(Argument::any())->shouldNotBeCalled();
        $this->requestStack->push(self::request(['_cms_preview' => $this->token(), '_cms_share' => self::SHARE_TOKEN]));

        self::assertSame('builder', $this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_reads_a_share_token_beside_an_invalid_preview_token(): void
    {
        $this->shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(self::shareLink('@guest', null));
        $this->requestStack->push(self::request(['_cms_preview' => 'tampered', '_cms_share' => self::SHARE_TOKEN]));

        self::assertSame('share', $this->context()->getMode());
    }

    /**
     * @test
     */
    public function it_reads_no_share_links_without_their_repository(): void
    {
        $this->requestStack->push(self::request(['_cms_share' => self::SHARE_TOKEN]));

        self::assertNull((new PreviewContext($this->requestStack, $this->signer))->getPreview());
    }

    private function context(): PreviewContext
    {
        $shareLinks = $this->shareLinks->reveal();

        return new PreviewContext($this->requestStack, $this->signer, static fn (): ShareLinkRepositoryInterface => $shareLinks);
    }

    /**
     * What the share link repository looks the token up as
     *
     * @return array{entry: string, channel: string, locale: string, shownTo: string, date: \DateTimeImmutable|null}
     */
    private static function shareLink(string $shownTo, ?\DateTimeImmutable $date): array
    {
        return ['entry' => self::ENTRY, 'channel' => 'WEB', 'locale' => 'da_DK', 'shownTo' => $shownTo, 'date' => $date, 'placement' => null, 'sampleKey' => null, 'sampleRef' => null];
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
