<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AddedRouteKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:check-routes finds the published URLs that a route added later now matches, and exits non-zero on any,
 * for deploys and CI. The app here added a route at /{locale}/stores. See "Routes added later" in
 * docs/spec/urls-and-seo.md
 */
final class CheckRoutesCommandTest extends KernelTestCase
{
    use Entries;

    protected static function getKernelClass(): string
    {
        return AddedRouteKernel::class;
    }

    /**
     * @test
     */
    public function it_exits_zero_when_no_other_route_matches_a_published_url(): void
    {
        self::bootKernel();
        self::createPage('About us', 'about-us', 'Hello', published: true);
        // Never published, so it has no URL a route can take yet
        self::createPage('Stores', 'stores', 'Our stores');

        $tester = self::checkRoutes();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] No other route matches an entry URL.', $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_lists_the_channel_locale_url_and_route_of_a_published_url_a_route_added_later_matches_and_exits_non_zero(): void
    {
        self::bootKernel();
        self::createPage('About us', 'about-us', 'Hello', published: true);
        self::createPage('Stores', 'stores', 'Our stores', published: true);

        $tester = self::checkRoutes();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Channel Locale URL Route', $display);
        self::assertStringContainsString('WEB en_US /en_US/stores app_store_locator', $display);
        self::assertStringNotContainsString('about-us', $display);
        self::assertStringContainsString('Another route matches 1 entry URL', $display);
    }

    private static function checkRoutes(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:check-routes'));
        $tester->execute([], ['decorated' => false]);

        return $tester;
    }
}
