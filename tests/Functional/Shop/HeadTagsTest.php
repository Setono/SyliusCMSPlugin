<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The head tags of an entry shown at its own URL, which layout/base.html.twig prints into the shop layout's title and
 * metatags blocks. The test app's channel WEB is on localhost, with en_US as its default locale.
 * See "SEO fields" and "Head tags" in docs/spec/urls-and-seo.md
 */
final class HeadTagsTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_gives_a_pages_head_its_title_description_robots_tag_canonical_url_and_a_hreflang_link_per_locale_it_shows_in(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'en_US', ['metaTitle' => 'About us | Shop', 'metaDescription' => 'All about "us"'], noindex: true);
        self::translate($page, 'da_DK', ['title' => 'Om os', 'slug' => 'om-os']);

        $head = $client->request('GET', '/en_US/about-us')->filter('head');

        self::assertResponseIsSuccessful();
        self::assertSame('About us | Shop', $head->filter('title')->text());
        self::assertSame('All about "us"', self::attribute($head, 'meta[name="description"]', 'content'));
        self::assertSame('noindex', self::attribute($head, 'meta[name="robots"]', 'content'));
        self::assertSame('http://localhost/en_US/about-us', self::attribute($head, 'link[rel="canonical"]', 'href'));
        self::assertSame([
            'da-DK' => 'http://localhost/da_DK/om-os',
            'en-US' => 'http://localhost/en_US/about-us',
            'x-default' => 'http://localhost/en_US/about-us',
        ], self::alternates($head));
        self::assertCount(0, $head->filter('meta[property^="og:"]'), 'The CMS prints no Open Graph tags');
    }

    /**
     * @test
     */
    public function it_gives_the_danish_page_its_own_canonical_url_and_the_same_hreflang_links(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'da_DK', ['title' => 'Om os', 'slug' => 'om-os']);

        $head = $client->request('GET', '/da_DK/om-os')->filter('head');

        self::assertSame('Om os', $head->filter('title')->text());
        self::assertSame('http://localhost/da_DK/om-os', self::attribute($head, 'link[rel="canonical"]', 'href'));
        self::assertSame([
            'da-DK' => 'http://localhost/da_DK/om-os',
            'en-US' => 'http://localhost/en_US/about-us',
            'x-default' => 'http://localhost/en_US/about-us',
        ], self::alternates($head));
    }

    /**
     * @test
     */
    public function it_gives_a_page_without_seo_fields_its_title_and_neither_a_description_nor_a_robots_tag(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $head = $client->request('GET', '/en_US/about-us')->filter('head');

        self::assertSame('About us', $head->filter('title')->text());
        self::assertCount(0, $head->filter('meta[name="description"]'));
        self::assertCount(0, $head->filter('meta[name="robots"]'));
    }

    /**
     * @test
     */
    public function it_leaves_a_held_back_locale_out_of_hreflang(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'da_DK', ['title' => 'Om os', 'slug' => 'om-os'], shown: false);

        $head = $client->request('GET', '/en_US/about-us')->filter('head');

        self::assertSame([
            'en-US' => 'http://localhost/en_US/about-us',
            'x-default' => 'http://localhost/en_US/about-us',
        ], self::alternates($head));
    }

    /**
     * @test
     */
    public function it_drops_the_query_string_from_the_canonical_url(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $head = $client->request('GET', '/en_US/about-us?utm_source=newsletter&utm_medium=email')->filter('head');

        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/en_US/about-us', self::attribute($head, 'link[rel="canonical"]', 'href'));
    }

    /**
     * The SEO fields are drafted, so they reach the head once they're published
     *
     * @test
     */
    public function it_shows_seo_fields_saved_in_a_draft_once_they_are_published(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::saveChanges($page, [
            ['op' => 'set_entry_value', 'field' => 'metaTitle', 'locale' => 'en_US', 'value' => 'About us | Shop'],
            ['op' => 'set_entry_value', 'field' => 'metaDescription', 'locale' => 'en_US', 'value' => 'All about us'],
            ['op' => 'set_entry_value', 'field' => 'noindex', 'locale' => null, 'value' => true],
        ]);

        $head = $client->request('GET', '/en_US/about-us')->filter('head');
        self::assertSame('About us', $head->filter('title')->text());
        self::assertCount(0, $head->filter('meta[name="robots"]'));

        self::publishEntry(self::reloadPage($page));

        $head = $client->request('GET', '/en_US/about-us')->filter('head');
        self::assertSame('About us | Shop', $head->filter('title')->text());
        self::assertSame('All about us', self::attribute($head, 'meta[name="description"]', 'content'));
        self::assertSame('noindex', self::attribute($head, 'meta[name="robots"]', 'content'));
    }

    /**
     * @param array{title?: string, slug?: string, metaTitle?: string, metaDescription?: string} $fields
     */
    private static function translate(PageInterface $page, string $locale, array $fields, bool $shown = true, bool $noindex = false): void
    {
        $page = self::reloadPage($page);
        $translation = $page->getTranslations()->get($locale);
        $translation ??= self::addTranslation($page, $locale, null);

        $translation->setTitle($fields['title'] ?? $translation->getTitle());
        $translation->setSlug($fields['slug'] ?? $translation->getSlug());
        $translation->setMetaTitle($fields['metaTitle'] ?? null);
        $translation->setMetaDescription($fields['metaDescription'] ?? null);
        $translation->setShown($shown);
        $page->setNoindex($noindex || $page->isNoindex());

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
        $manager->clear();
    }

    private static function attribute(Crawler $head, string $selector, string $attribute): ?string
    {
        $elements = $head->filter($selector);
        self::assertCount(1, $elements, sprintf('The head has one %s', $selector));

        return $elements->attr($attribute);
    }

    /**
     * @return array<string, string> hreflang => URL
     */
    private static function alternates(Crawler $head): array
    {
        $alternates = [];
        foreach ($head->filter('link[rel="alternate"][hreflang]') as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            $alternates[$link->getAttribute('hreflang')] = $link->getAttribute('href');
        }

        return $alternates;
    }
}
