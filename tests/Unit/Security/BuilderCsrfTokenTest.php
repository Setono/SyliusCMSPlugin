<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class BuilderCsrfTokenTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CsrfTokenManagerInterface> */
    private ObjectProphecy $csrfTokenManager;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $this->csrfTokenManager->getToken('setono_sylius_cms_builder')->willReturn(new CsrfToken('setono_sylius_cms_builder', 'the-token'));
        $this->csrfTokenManager->isTokenValid(Argument::type(CsrfToken::class))->willReturn(false);
        $this->csrfTokenManager->isTokenValid(new CsrfToken('setono_sylius_cms_builder', 'the-token'))->willReturn(true);
    }

    /**
     * @test
     */
    public function it_hands_out_the_builder_token(): void
    {
        self::assertSame('the-token', $this->csrfToken()->value());
    }

    /**
     * @test
     */
    public function it_accepts_the_builder_token_in_the_header(): void
    {
        self::assertTrue($this->csrfToken()->isValid(self::request('the-token')));
    }

    /**
     * @test
     */
    public function it_refuses_a_wrong_token(): void
    {
        self::assertFalse($this->csrfToken()->isValid(self::request('wrong')));
    }

    /**
     * @test
     */
    public function it_refuses_a_request_without_a_token(): void
    {
        $this->csrfTokenManager->isTokenValid(Argument::any())->shouldNotBeCalled();

        self::assertFalse($this->csrfToken()->isValid(self::request(null)));
    }

    private function csrfToken(): BuilderCsrfToken
    {
        return new BuilderCsrfToken($this->csrfTokenManager->reveal());
    }

    private static function request(?string $token): Request
    {
        $request = new Request();
        if (null !== $token) {
            $request->headers->set('X-CSRF-Token', $token);
        }

        return $request;
    }
}
