<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewModeListener;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PreviewModeListenerTest extends TestCase
{
    use ProphecyTrait;

    private RequestStack $requestStack;

    private PreviewTokenSigner $signer;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->signer = new PreviewTokenSigner('secret', new MockClock());
    }

    /**
     * @test
     */
    public function it_tells_the_main_request_it_is_in_builder_preview_mode(): void
    {
        $request = $this->previewRequest();

        $this->listener()->onRequest($this->requestEvent($request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('builder', $request->attributes->get('_cms_preview_mode'));
    }

    /**
     * @test
     */
    public function it_leaves_a_shopper_request_alone(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $this->listener()->onRequest($this->requestEvent($request, HttpKernelInterface::MAIN_REQUEST));

        self::assertFalse($request->attributes->has('_cms_preview_mode'));
    }

    /**
     * @test
     */
    public function it_sets_the_attribute_on_the_main_request_only(): void
    {
        $this->previewRequest();
        $subRequest = new Request();
        $this->requestStack->push($subRequest);

        $this->listener()->onRequest($this->requestEvent($subRequest, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($subRequest->attributes->has('_cms_preview_mode'));
    }

    /**
     * @test
     */
    public function a_preview_response_is_neither_cached_nor_indexed(): void
    {
        $request = $this->previewRequest();
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(600);

        $this->listener()->onResponse($this->responseEvent($request, $response, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
    }

    /**
     * @test
     */
    public function it_leaves_a_shopper_response_alone(): void
    {
        $request = new Request();
        $this->requestStack->push($request);
        $response = new Response();
        $response->setMaxAge(600);

        $this->listener()->onResponse($this->responseEvent($request, $response, HttpKernelInterface::MAIN_REQUEST));

        self::assertTrue($response->headers->hasCacheControlDirective('max-age'));
        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    /**
     * @test
     */
    public function it_leaves_the_responses_of_sub_requests_alone(): void
    {
        $this->previewRequest();
        $subRequest = new Request();
        $this->requestStack->push($subRequest);
        $response = new Response();
        $response->setMaxAge(600);

        $this->listener()->onResponse($this->responseEvent($subRequest, $response, HttpKernelInterface::SUB_REQUEST));

        self::assertTrue($response->headers->hasCacheControlDirective('max-age'));
        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    private function previewRequest(): Request
    {
        $request = new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'en_US'))]);
        $this->requestStack->push($request);

        return $request;
    }

    private function listener(): PreviewModeListener
    {
        return new PreviewModeListener(new PreviewContext($this->requestStack, $this->signer));
    }

    private function requestEvent(Request $request, int $type): RequestEvent
    {
        return new RequestEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, $type);
    }

    private function responseEvent(Request $request, Response $response, int $type): ResponseEvent
    {
        return new ResponseEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, $type, $response);
    }
}
