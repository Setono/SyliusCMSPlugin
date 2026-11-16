<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UrlTakenByRoute;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
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

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    protected function setUp(): void
    {
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_finds_a_live_url_a_route_takes_once_per_locale_as_a_warning_fixed_in_the_builder_in_that_locale(): void
    {
        $rows = self::page(7, 'cart');
        $this->entries->findOneById(7)->willReturn($rows);
        $this->takenUrls->findLive($rows)->willReturn([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'en_US', 'cart', 'app_cart'),
            new TakenUrl('WEB', 'da_DK', 'kurv', 'app_kurv'),
        ]);
        // The draft has another slug, which isn't live yet
        $workingCopy = clone $rows;
        $workingCopy->setSlug('basket');

        self::assertEquals([
            self::problem($workingCopy, 'en_US', '/en_US/cart', 'sylius_shop_cart_summary'),
            self::problem($workingCopy, 'da_DK', '/da_DK/kurv', 'app_kurv'),
        ], [...$this->kind()->findIn($workingCopy)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_never_published_or_unsaved_entry_as_it_has_no_live_url(): void
    {
        $this->entries->findOneById(Argument::any())->willReturn(self::page(7, 'cart'));
        $this->takenUrls->findLive(Argument::any())->willReturn([new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary')]);

        self::assertSame([], [...$this->kind()->findIn(self::page(7, 'cart', false))]);
        self::assertSame([], [...$this->kind()->findIn(self::page(null, 'cart'))]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_an_entry_without_a_url_of_its_own(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setSlug('cart');
        $recipe->publish(new \DateTimeImmutable());
        self::setId($recipe, 7);
        $this->entries->findOneById(7)->willReturn($recipe);
        $this->takenUrls->findLive(Argument::any())->willReturn([new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary')]);

        self::assertSame([], [...$this->kind()->findIn($recipe)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_no_route_takes_a_url_or_the_entry_is_gone(): void
    {
        $this->entries->findOneById(7)->willReturn(self::page(7, 'about-us'));
        $this->entries->findOneById(8)->willReturn(null);
        $this->takenUrls->findLive(Argument::any())->willReturn([]);

        self::assertSame([], [...$this->kind()->findIn(self::page(7, 'about-us'))]);
        self::assertSame([], [...$this->kind()->findIn(self::page(8, 'about-us'))]);
    }

    private function kind(): UrlTakenByRoute
    {
        return new UrlTakenByRoute(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]), $this->takenUrls->reveal(), $this->entries->reveal());
    }

    private static function page(?int $id, string $slug, bool $published = true): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setSlug($slug);
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB', 'B2B']);
        if (null !== $id) {
            self::setId($page, $id);
        }

        if ($published) {
            $page->publish(new \DateTimeImmutable());
        }

        return $page;
    }

    private static function setId(EntryInterface $entry, int $id): void
    {
        (new \ReflectionProperty(Entry::class, 'id'))->setValue($entry, $id);
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
