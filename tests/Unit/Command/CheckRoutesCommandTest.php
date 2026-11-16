<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Command;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Command\CheckRoutesCommand;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckRoutesCommandTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<TakenUrlFinderInterface> */
    private ObjectProphecy $takenUrls;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $this->takenUrls->find(Argument::cetera())->willReturn([]);
        $this->manager = $this->prophesize(ObjectManager::class);
    }

    /**
     * @test
     */
    public function it_succeeds_when_no_other_route_matches_an_entry_url(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os'], ['WEB'], true);
        $this->entries->iterateAll()->willReturn([$page]);
        $this->manager->clear()->shouldNotBeCalled();

        $tester = $this->check();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] No other route matches an entry URL.', $tester->getDisplay());
        $this->takenUrls->find($page, ['da_DK' => 'om-os', 'en_US' => 'about-us'], ['WEB'], true)->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_lists_each_published_url_another_route_matches_in_a_channel_and_locale_it_shows_in_and_fails(): void
    {
        $cart = self::page(['en_US' => 'cart', 'da_DK' => 'kurv', 'de_DE' => '/bad'], ['B2B', 'WEB'], true);
        $login = self::page(['en_US' => 'login'], ['WEB'], true);
        $this->entries->iterateAll()->willReturn([$cart, $login]);
        $this->takenUrls->find($cart, ['da_DK' => 'kurv', 'en_US' => 'cart'], ['B2B', 'WEB'], true)->willReturn([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary'),
        ]);
        $this->takenUrls->find($login, ['en_US' => 'login'], ['WEB'], true)->willReturn([new TakenUrl('WEB', 'en_US', 'login', 'sylius_shop_login')]);

        $tester = $this->check();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Channel\s+Locale\s+URL\s+Route/', $display);
        self::assertMatchesRegularExpression('#B2B\s+en_US\s+/en_US/cart\s+sylius_shop_cart_summary#', $display);
        self::assertMatchesRegularExpression('#WEB\s+en_US\s+/en_US/cart\s+sylius_shop_cart_summary#', $display);
        self::assertMatchesRegularExpression('#WEB\s+en_US\s+/en_US/login\s+sylius_shop_login#', $display);
        self::assertStringContainsString('Another route matches 3 entry URLs', $display);
    }

    /**
     * @test
     */
    public function it_names_one_url_as_one(): void
    {
        $login = self::page(['en_US' => 'login'], ['WEB'], true);
        $this->entries->iterateAll()->willReturn([$login]);
        $this->takenUrls->find($login, Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'login', 'sylius_shop_login')]);

        self::assertStringContainsString('Another route matches 1 entry URL, so shoppers', $this->check()->getDisplay());
    }

    /**
     * @test
     */
    public function a_never_published_entry_or_one_in_no_channel_has_no_url_to_check(): void
    {
        $neverPublished = self::page(['en_US' => 'cart'], ['WEB'], false);
        $nowhere = self::page(['en_US' => 'cart'], [], true);
        $this->entries->iterateAll()->willReturn([$neverPublished, $nowhere]);
        $this->takenUrls->find(Argument::any(), ['en_US' => 'cart'], Argument::cetera())->willReturn([new TakenUrl(null, 'en_US', 'cart', 'sylius_shop_cart_summary')]);

        self::assertSame(Command::SUCCESS, $this->check()->getStatusCode());
    }

    /**
     * @test
     *
     * @testWith [199, 1]
     *           [200, 2]
     */
    public function it_drops_the_entries_it_has_checked_every_hundred_entries(int $entries, int $clears): void
    {
        $pages = [];
        for ($i = 0; $i < $entries; ++$i) {
            $pages[] = new Page();
        }

        $this->entries->iterateAll()->willReturn($pages);
        $this->manager->clear()->shouldBeCalledTimes($clears);

        self::assertSame(Command::SUCCESS, $this->check()->getStatusCode());
    }

    private function check(): CommandTester
    {
        $tester = new CommandTester(new CheckRoutesCommand($this->entries->reveal(), $this->takenUrls->reveal(), $this->manager->reveal()));
        $tester->execute([], ['decorated' => false]);

        return $tester;
    }

    /**
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     */
    private static function page(array $slugs, array $channelCodes, bool $published): Page
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setSlug($slug);
            $page->addTranslation($translation);
        }
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);
        if ($published) {
            $page->publish(new \DateTimeImmutable());
        }

        return $page;
    }
}
