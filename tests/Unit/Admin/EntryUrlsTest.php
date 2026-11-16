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
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntryUrlsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    protected function setUp(): void
    {
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);
        $this->drafts->findOneByEntry(Argument::any())->willReturn(null);
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
    public function an_entry_whose_content_type_has_no_url_of_its_own_has_no_urls(): void
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

        return new EntryUrls(
            $this->drafts->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
            ]),
            $urlGenerator->reveal(),
        );
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
