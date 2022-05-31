<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\PageExistsChecker;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Setono\SyliusCMSPlugin\Routing\PageExistsChecker
 */
final class PageExistsCheckerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_checks_longest_paths_first(): void
    {
        $pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $pageRepository->exists('en_US/articles/electronics/wifi-repeater')->willReturn(false)->shouldBeCalledOnce();
        $pageRepository->exists('articles/electronics/wifi-repeater')->willReturn(true)->shouldBeCalledOnce();
        $pageRepository->exists('electronics/wifi-repeater')->willReturn(false)->shouldNotBeCalled();

        $request = new class() extends Request {
            public function getPathInfo(): string
            {
                return '/en_US/articles/electronics/wifi-repeater';
            }
        };

        $checker = new PageExistsChecker($pageRepository->reveal());
        self::assertTrue($checker->checkUrl($request));
    }

    /**
     * @test
     */
    public function it_checks_path_without_slashes(): void
    {
        $pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $pageRepository->exists('wifi-repeater')->willReturn(true)->shouldBeCalledOnce();

        $request = new class() extends Request {
            public function getPathInfo(): string
            {
                return '/wifi-repeater';
            }
        };

        $checker = new PageExistsChecker($pageRepository->reveal());
        self::assertTrue($checker->checkUrl($request));
    }

    /**
     * @test
     */
    public function it_returns_false_if_path_is_not_a_slug(): void
    {
        $pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $pageRepository->exists('wifi-repeater')->willReturn(false)->shouldBeCalledOnce();

        $request = new class() extends Request {
            public function getPathInfo(): string
            {
                return '/wifi-repeater';
            }
        };

        $checker = new PageExistsChecker($pageRepository->reveal());
        self::assertFalse($checker->checkUrl($request));
    }
}
