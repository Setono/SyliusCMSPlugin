<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\Kind\UrlTakenByRoute;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AddedRouteKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatableMessage;

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

    /**
     * The lists and the Content badge read the problems counted when an entry was written, so a route added since
     * shows there once the command ran, as at a deploy. See "Problems" in docs/spec/admin.md
     *
     * @test
     */
    public function it_counts_the_published_entries_again_so_the_admin_warns_of_a_url_a_route_added_since_takes(): void
    {
        // Published before the route was added
        static::$class = Kernel::class;
        self::bootKernel();
        $stores = self::createPage('Stores', 'stores', 'Our stores', published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($stores));

        self::ensureKernelShutdown();
        static::$class = AddedRouteKernel::class;
        self::bootKernel();
        self::assertEquals(new ProblemCount(), self::storedCount($stores), 'Nothing counts it again by itself');

        self::checkRoutes();

        self::assertEquals(new ProblemCount(0, 1), self::storedCount($stores));
    }

    /**
     * A route takes the URL shoppers have, so the admin warns of it as check-routes reports it, whatever slug the draft
     * has meanwhile: the URL is still taken until the new slug is published
     *
     * @test
     */
    public function it_warns_in_the_admin_of_the_live_url_a_route_takes_while_the_draft_has_another_slug(): void
    {
        static::$class = Kernel::class;
        self::bootKernel();
        $stores = self::createPage('Stores', 'stores', 'Our stores', published: true);
        self::saveChanges($stores, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'our-stores']]);

        self::ensureKernelShutdown();
        static::$class = AddedRouteKernel::class;
        self::bootKernel();

        self::assertSame(Command::FAILURE, self::checkRoutes()->getStatusCode());
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($stores));
        self::assertSame(['/en_US/stores'], self::urlsTakenByRoutes($stores));
    }

    /**
     * A drafted slug isn't a URL shoppers have yet: saving refuses one a route takes, and a route added since the draft
     * was saved is caught when it's published
     *
     * @test
     */
    public function it_doesnt_warn_of_a_drafted_slug_a_route_takes_as_it_isnt_live(): void
    {
        static::$class = Kernel::class;
        self::bootKernel();
        $stores = self::createPage('Stores', 'our-stores', 'Our stores', published: true);
        self::saveChanges($stores, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'stores']]);

        self::ensureKernelShutdown();
        static::$class = AddedRouteKernel::class;
        self::bootKernel();

        self::assertSame(Command::SUCCESS, self::checkRoutes()->getStatusCode());
        self::assertEquals(new ProblemCount(), self::storedCount($stores));
        self::assertSame([], self::urlsTakenByRoutes($stores));
    }

    /**
     * A URL counts only in the locales the entry shows in: not a held-back translation's, nor one without a title,
     * which is no translation
     *
     * @test
     */
    public function it_checks_only_the_locales_the_entry_shows_in(): void
    {
        self::bootKernel();
        self::addLocales('WEB', 'da_DK', 'de_DE');
        $about = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::addTranslation($about, 'da_DK', 'Butikker', 'stores', false);
        self::addTranslation($about, 'de_DE', null, 'stores');

        self::assertSame(Command::SUCCESS, self::checkRoutes()->getStatusCode());
        self::assertEquals(new ProblemCount(), self::storedCount($about));
        self::assertSame([], self::urlsTakenByRoutes($about));
    }

    /**
     * The URLs the entry's overview page names in its "URL taken by a Sylius route" warnings
     *
     * @return list<string>
     */
    private static function urlsTakenByRoutes(PageInterface $page): array
    {
        /** @var ProblemFinderInterface $finder */
        $finder = self::getContainer()->get(ProblemFinderInterface::class);

        $urls = [];
        foreach ($finder->findByEntry(self::reloadPage($page)) as $problem) {
            if (UrlTakenByRoute::CODE === $problem->kind) {
                self::assertInstanceOf(TranslatableMessage::class, $problem->message);
                $url = $problem->message->getParameters()['%url%'] ?? null;
                self::assertIsString($url);
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private static function checkRoutes(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:check-routes'));
        $tester->execute([], ['decorated' => false]);

        return $tester;
    }
}
