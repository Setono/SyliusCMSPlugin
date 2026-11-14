<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Preview\BridgeScriptListener;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class BridgeScriptListenerTest extends TestCase
{
    use ProphecyTrait;

    private const PAGE = '<html><body><h1>About us</h1></body></html>';

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
    public function it_injects_the_bridge_script_at_the_end_of_the_body_of_a_page_in_the_builders_preview(): void
    {
        $response = $this->respond($this->previewRequest(), new Response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        self::assertSame('<html><body><h1>About us</h1><script data-cms-bridge></script></body></html>', $response->getContent());
    }

    /**
     * @test
     */
    public function it_injects_it_before_the_last_closing_body_tag(): void
    {
        $page = '<html><body><pre>&lt;/body&gt; and </body> in an attribute</pre></body></html>';

        $response = $this->respond($this->previewRequest(), new Response($page));

        self::assertSame('<html><body><pre>&lt;/body&gt; and </body> in an attribute</pre><script data-cms-bridge></script></body></html>', $response->getContent());
    }

    /**
     * @test
     */
    public function it_finds_a_closing_body_tag_in_upper_case(): void
    {
        $response = $this->respond($this->previewRequest(), new Response('<HTML><BODY>Hi</BODY></HTML>'));

        self::assertSame('<HTML><BODY>Hi<script data-cms-bridge></script></BODY></HTML>', $response->getContent());
    }

    /**
     * @test
     */
    public function shoppers_never_get_it(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $response = $this->respond($request, new Response(self::PAGE));

        self::assertSame(self::PAGE, $response->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_the_responses_of_sub_requests_alone(): void
    {
        $this->previewRequest();
        $subRequest = new Request();
        $this->requestStack->push($subRequest);

        $response = $this->respond($subRequest, new Response(self::PAGE), HttpKernelInterface::SUB_REQUEST);

        self::assertSame(self::PAGE, $response->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_a_response_that_is_not_html_alone(): void
    {
        $json = new JsonResponse();
        $json->setJson('{"html": "<body></body>"}');

        $response = $this->respond($this->previewRequest(), $json);

        self::assertSame('{"html": "<body></body>"}', $response->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_a_redirect_alone(): void
    {
        $response = $this->respond($this->previewRequest(), new RedirectResponse('/en_US/about-us'));

        self::assertStringNotContainsString('data-cms-bridge', (string) $response->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_a_page_without_a_body_alone(): void
    {
        $response = $this->respond($this->previewRequest(), new Response('<p>A fragment</p>'));

        self::assertSame('<p>A fragment</p>', $response->getContent());
    }

    /**
     * @test
     */
    public function it_leaves_a_streamed_response_alone(): void
    {
        $response = $this->respond($this->previewRequest(), new StreamedResponse(static function (): void {
            echo self::PAGE;
        }));

        self::assertFalse($response->getContent());
    }

    private function previewRequest(): Request
    {
        $request = new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'en_US'))]);
        $this->requestStack->push($request);

        return $request;
    }

    private function respond(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $listener = new BridgeScriptListener(
            new PreviewContext($this->requestStack, $this->signer),
            new Environment(new ArrayLoader(['@SetonoSyliusCMSPlugin/preview/bridge.html.twig' => '<script data-cms-bridge></script>'])),
        );

        $listener->onResponse(new ResponseEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, $type, $response));

        return $response;
    }
}
