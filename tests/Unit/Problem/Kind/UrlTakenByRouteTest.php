<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UrlTakenByRoute;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Symfony\Component\Translation\TranslatableMessage;

final class UrlTakenByRouteTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TakenUrlFinderInterface> */
    private ObjectProphecy $takenUrls;

    protected function setUp(): void
    {
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
    }

    /**
     * @test
     */
    public function it_finds_a_published_url_a_route_takes_once_per_locale_as_a_warning_fixed_in_the_builder_in_that_locale(): void
    {
        $page = self::page(['en_US' => 'cart', 'da_DK' => 'kurv', 'de_DE' => '/bad', 'fr_FR' => null]);
        $page->publish(new \DateTimeImmutable());
        $this->takenUrls->find($page, ['en_US' => 'cart', 'da_DK' => 'kurv'], ['B2B', 'WEB'])->willReturn([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'en_US', 'cart', 'app_cart'),
            new TakenUrl('WEB', 'da_DK', 'kurv', 'app_kurv'),
        ]);

        self::assertEquals([
            self::problem($page, 'en_US', '/en_US/cart', 'sylius_shop_cart_summary'),
            self::problem($page, 'da_DK', '/da_DK/kurv', 'app_kurv'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function a_never_published_entry_has_no_url_for_a_route_to_take(): void
    {
        $this->takenUrls->find(Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary')]);

        self::assertSame([], [...$this->kind()->findIn(self::page(['en_US' => 'cart']))]);
    }

    /**
     * @test
     */
    public function an_entry_without_a_url_of_its_own_has_no_url_for_a_route_to_take(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setSlug('cart');
        $recipe->publish(new \DateTimeImmutable());
        $this->takenUrls->find(Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary')]);

        self::assertSame([], [...$this->kind()->findIn($recipe)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_no_route_takes_a_url(): void
    {
        $page = self::page(['en_US' => 'about-us']);
        $page->publish(new \DateTimeImmutable());
        $this->takenUrls->find(Argument::cetera())->willReturn([]);

        self::assertSame([], [...$this->kind()->findIn($page)]);
    }

    private function kind(): UrlTakenByRoute
    {
        return new UrlTakenByRoute(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]), $this->takenUrls->reveal());
    }

    /**
     * @param array<string, string|null> $slugs
     */
    private static function page(array $slugs): Page
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setSlug($slug);
            $page->addTranslation($translation);
        }
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB', 'B2B']);

        return $page;
    }

    private static function problem(Page $page, string $localeCode, string $url, string $route): Problem
    {
        return new Problem(
            'url_taken_by_route',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.url_taken_by_route', ['%url%' => $url, '%route%' => $route]),
            Fix::inBuilder($page, null, $localeCode),
            null,
            'slug',
            $localeCode,
        );
    }
}
