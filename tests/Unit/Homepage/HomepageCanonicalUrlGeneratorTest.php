<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Homepage;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Homepage\HomepageCanonicalUrlGenerator;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A homepage's canonical URL is its channel's root, /{locale}/, so its canonical and hreflang links point there.
 * See "Rendering the homepage" and "Head tags" in docs/spec/urls-and-seo.md
 */
final class HomepageCanonicalUrlGeneratorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CanonicalUrlGeneratorInterface> */
    private ObjectProphecy $decorated;

    protected function setUp(): void
    {
        $this->decorated = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $this->decorated->generate(Argument::cetera())->willReturn('https://shop.example/en_US/welcome');
    }

    /**
     * @test
     */
    public function it_puts_a_channels_homepage_at_the_channels_root_in_each_locale(): void
    {
        $page = new Page();
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, ['B2B', 'WEB']);

        self::assertSame('https://shop.example/en_US/', $this->generator()->generate($page, self::channel('WEB'), 'en_US'));
        self::assertSame('https://shop.example/da_DK/', $this->generator()->generate($page, self::channel('B2B'), 'da_DK'));
        $this->decorated->generate(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * Links to a homepage lead to its channel's root as a path, as Sylius's path() gives
     *
     * @test
     */
    public function it_puts_a_channels_homepage_at_the_channels_root_as_a_path_when_asked_for_a_path(): void
    {
        $page = new Page();
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, ['WEB']);

        self::assertSame('/en_US/', $this->generator()->generate($page, self::channel('WEB'), 'en_US', UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    /**
     * @test
     */
    public function it_leaves_a_page_at_its_own_url_in_a_channel_it_is_not_the_homepage_of(): void
    {
        $page = new Page();
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, ['B2B']);
        $web = self::channel('WEB');

        self::assertSame('https://shop.example/en_US/welcome', $this->generator()->generate($page, $web, 'en_US'));
        $this->decorated->generate($page, $web, 'en_US', UrlGeneratorInterface::ABSOLUTE_URL)->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_asks_for_a_path_of_a_page_it_is_not_the_homepage_of_when_asked_for_a_path(): void
    {
        $page = new Page();
        $web = self::channel('WEB');
        $this->decorated->generate($page, $web, 'en_US', UrlGeneratorInterface::ABSOLUTE_PATH)->willReturn('/en_US/welcome');

        self::assertSame('/en_US/welcome', $this->generator()->generate($page, $web, 'en_US', UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    /**
     * @test
     */
    public function it_leaves_an_entry_other_than_a_page_at_its_own_url(): void
    {
        $announcement = new Announcement();
        $this->decorated->generate($announcement, Argument::cetera())->willReturn(null);

        self::assertNull($this->generator()->generate($announcement, self::channel('WEB'), 'en_US'));
    }

    private function generator(): HomepageCanonicalUrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('sylius_shop_homepage', new Route('/{_locale}/'));

        return new HomepageCanonicalUrlGenerator($this->decorated->reveal(), new UrlGenerator($routes, new RequestContext(host: 'shop.example', scheme: 'https')));
    }

    private static function channel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
