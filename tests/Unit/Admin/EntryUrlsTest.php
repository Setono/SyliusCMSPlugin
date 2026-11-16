<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryUrl;
use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\OldSlug;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntryUrlsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    /** @var ObjectProphecy<OldSlugRepositoryInterface> */
    private ObjectProphecy $oldSlugs;

    protected function setUp(): void
    {
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);
        $this->drafts->findOneByEntry(Argument::any())->willReturn(null);
        $this->oldSlugs = $this->prophesize(OldSlugRepositoryInterface::class);
        $this->oldSlugs->findByEntry(Argument::any())->willReturn([]);
    }

    /**
     * @test
     */
    public function it_gives_the_urls_of_each_locales_old_slugs_which_redirect_to_its_url(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-our-shop'], 'da_DK' => ['Om os', 'om-os']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $this->oldSlugs->findByEntry($page)->willReturn([
            self::oldSlug($page, 'en_US', 'about-us'),
            self::oldSlug($page, 'en_US', 'about'),
            self::oldSlug($page, 'fr_FR', 'a-propos'),
        ]);

        self::assertEquals([
            new EntryUrl('da_DK', '/da_DK/om-os', null),
            new EntryUrl('en_US', '/en_US/about-our-shop', null, ['/en_US/about-us', '/en_US/about']),
        ], $this->entryUrls()->of($page));
        self::assertSame(['en_US' => ['/en_US/about-us', '/en_US/about'], 'fr_FR' => ['/fr_FR/a-propos']], $this->entryUrls()->oldUrlsOf($page));
    }

    /**
     * @test
     */
    public function it_reads_no_old_slugs_of_an_entry_never_published_or_without_a_url_of_its_own(): void
    {
        $this->oldSlugs->findByEntry(Argument::any())->shouldNotBeCalled();
        $recipe = new Recipe();
        $recipe->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));

        self::assertSame([], $this->entryUrls()->oldUrlsOf(self::page(['en_US' => ['About us', 'about-us']])));
        self::assertSame([], $this->entryUrls()->oldUrlsOf($recipe));
    }

    /**
     * @test
     */
    public function it_gives_the_url_of_each_locale_with_a_slug_by_locale_code(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os'], 'de_DE' => ['Über uns', null]]);

        self::assertEquals([
            new EntryUrl('da_DK', '/da_DK/om-os', null),
            new EntryUrl('en_US', '/en_US/about-us', null),
        ], $this->entryUrls()->of($page));
    }

    /**
     * A channel's homepage shows at /{locale}/, and its own URL redirects there in that channel. See "Homepage" in
     * docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_gives_a_homepage_the_channels_root_beside_its_own_url(): void
    {
        $page = self::page(['en_US' => ['Welcome', 'welcome'], 'da_DK' => ['Velkommen', 'velkommen']]);
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, ['WEB']);

        self::assertEquals([
            new EntryUrl('da_DK', '/da_DK/velkommen', null, [], '/da_DK/'),
            new EntryUrl('en_US', '/en_US/welcome', null, [], '/en_US/'),
        ], $this->entryUrls()->of($page));
    }

    /**
     * Only a page can be a channel's homepage
     *
     * @test
     */
    public function it_gives_an_entry_of_another_content_type_with_its_own_url_no_homepage_url(): void
    {
        $article = new Article();
        $article->setCurrentLocale('en_US');
        $article->setTitle('News');
        $article->setSlug('news');

        self::assertEquals([new EntryUrl('en_US', '/en_US/articles/news', null)], $this->entryUrls()->of($article));
    }

    /**
     * @test
     */
    public function it_gives_a_url_the_draft_changes_beside_the_published_one(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $this->drafts->findOneByEntry($page)->willReturn(self::draft($page, [
            'da_DK' => ['Om os', 'om-os'],
            'en_US' => ['About us', 'about-our-shop'],
            'fr_FR' => ['À propos', 'a-propos'],
        ]));

        self::assertEquals([
            new EntryUrl('da_DK', '/da_DK/om-os', null),
            new EntryUrl('en_US', '/en_US/about-us', '/en_US/about-our-shop'),
            new EntryUrl('fr_FR', null, '/fr_FR/a-propos'),
        ], $this->entryUrls()->of($page));
    }

    /**
     * @test
     */
    public function it_gives_the_url_a_published_locale_has_while_the_draft_takes_its_slug_away(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $this->drafts->findOneByEntry($page)->willReturn(self::draft($page, ['en_US' => ['About us', null]]));

        self::assertEquals([new EntryUrl('en_US', '/en_US/about-us', null)], $this->entryUrls()->of($page));
    }

    /**
     * @test
     */
    public function it_gives_the_urls_of_the_published_locales_a_draft_has_no_translation_in(): void
    {
        $page = self::page(['da_DK' => ['Om os', 'om-os'], 'en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $this->drafts->findOneByEntry($page)->willReturn(self::draft($page, ['fr_FR' => ['À propos', 'a-propos']]));

        self::assertEquals([
            new EntryUrl('da_DK', '/da_DK/om-os', null),
            new EntryUrl('en_US', '/en_US/about-us', null),
            new EntryUrl('fr_FR', null, '/fr_FR/a-propos'),
        ], $this->entryUrls()->of($page));
    }

    /**
     * @test
     */
    public function it_reads_no_draft_of_an_entry_never_published(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $this->drafts->findOneByEntry(Argument::any())->shouldNotBeCalled();

        self::assertEquals([new EntryUrl('en_US', '/en_US/about-us', null)], $this->entryUrls()->of($page));
    }

    /**
     * @test
     */
    public function it_gives_no_urls_for_an_entry_whose_content_type_has_no_url_of_its_own(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Apple pie');
        $recipe->setSlug('apple-pie');

        self::assertSame([], $this->entryUrls()->of($recipe));
    }

    private function entryUrls(): EntryUrls
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_cms_shop_entry', Argument::type('array'))->will(static function (array $arguments): string {
            /** @var array{_locale: string, path: string} $parameters */
            $parameters = $arguments[1];

            return sprintf('/%s/%s', $parameters['_locale'], $parameters['path']);
        });
        $urlGenerator->generate('sylius_shop_homepage', Argument::type('array'))->will(static function (array $arguments): string {
            /** @var array{_locale: string} $parameters */
            $parameters = $arguments[1];

            return sprintf('/%s/', $parameters['_locale']);
        });

        return new EntryUrls(
            $this->drafts->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
                'article' => ['model' => Article::class, 'translation' => EntryTranslation::class, 'resource' => 'app.article'],
            ]),
            $urlGenerator->reveal(),
            $this->oldSlugs->reveal(),
        );
    }

    private static function oldSlug(Page $page, string $localeCode, string $slug): OldSlug
    {
        $oldSlug = new OldSlug();
        $oldSlug->setEntry($page);
        $oldSlug->setLocaleCode($localeCode);
        $oldSlug->setSlug($slug);

        return $oldSlug;
    }

    /**
     * @param array<string, array{string, string|null}> $translations locale => [title, slug]
     */
    private static function page(array $translations): Page
    {
        $page = new Page();
        foreach ($translations as $localeCode => [$title, $slug]) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setTitle($title);
            $translation->setSlug($slug);
            $page->addTranslation($translation);
        }

        return $page;
    }

    /**
     * A draft in the entry format, with these translations
     *
     * @param array<string, array{string, string|null}> $translations locale => [title, slug]
     */
    private static function draft(Page $page, array $translations): Draft
    {
        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(CanonicalJson::encode([
            'uuid' => $page->getUuid(),
            'contentType' => 'page',
            'translations' => array_map(static fn (array $translation): array => [
                'shown' => true,
                'fields' => ['slug' => $translation[1], 'title' => $translation[0]],
                'translated' => new \stdClass(),
            ], $translations),
        ]));

        return $draft;
    }
}
