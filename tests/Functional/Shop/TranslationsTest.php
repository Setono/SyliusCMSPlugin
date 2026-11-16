<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Where an entry shows, and how its fields fall back per field. The test app configures de_CH to fall back to de_DE.
 * See "Availability" and "Fallback chain" in docs/spec/localization.md
 */
final class TranslationsTest extends WebTestCase
{
    use Entries;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const ENGLISH_ONLY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const RICH_TEXT = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    /**
     * Whole entries never fall back to another locale
     *
     * @test
     */
    public function a_page_answers_404_in_a_locale_it_has_no_translation_in(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/da_DK/about-us');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * A URL exists only in locales with a slug
     *
     * @test
     */
    public function a_slug_never_falls_back(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'da_DK', 'Om os', null);

        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'), 'No entry has the URL in Danish');

        self::translate($page, 'da_DK', 'Om os', 'om-os');

        $client->request('GET', '/da_DK/om-os');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('h1', 'Om os');
    }

    /**
     * @test
     */
    public function a_page_answers_404_in_a_locale_whose_translation_is_held_back(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'da_DK', 'Om os', 'om-os', shown: false);

        $client->request('GET', '/da_DK/om-os');
        self::assertResponseStatusCodeSame(404);
        self::assertRouteSame('setono_sylius_cms_shop_entry', [], 'The URL exists, so the route claims it, and the controller answers 404');

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * An empty string, an empty rich text document and a missing locale all count as missing
     *
     * @test
     */
    public function a_field_empty_in_de_ch_renders_the_de_de_value_and_one_missing_in_both_the_en_us_value(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'de_DE', 'de_CH');

        $heading = new StoredBlock(self::HEADING, 'heading', 1);
        $heading->setValue('text', 'en_US', 'Hello');
        $heading->setValue('text', 'de_DE', 'Hallo');
        $heading->setValue('text', 'de_CH', '');
        $englishOnly = new StoredBlock(self::ENGLISH_ONLY, 'heading', 1);
        $englishOnly->setValue('text', 'en_US', 'Only in English');
        $richText = new StoredBlock(self::RICH_TEXT, 'rich_text', 1);
        $richText->setValue('content', 'de_DE', self::document('Auf Deutsch'));
        $richText->setValue('content', 'de_CH', ['type' => 'doc', 'content' => [['type' => 'paragraph']]]);

        $page = self::createPageWithBlocks('About us', 'about-us', [$heading, $englishOnly, $richText], published: true);
        self::translate($page, 'de_DE', 'Über uns', 'ueber-uns');
        self::translate($page, 'de_CH', 'Über uns', 'ueber-uns');

        $crawler = $client->request('GET', '/de_CH/ueber-uns');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Hallo', 'Only in English'], $crawler->filter('.cms-tree .cms-heading')->each(static fn ($heading): string => $heading->text()));
        self::assertSelectorTextSame('.cms-rich-text', 'Auf Deutsch');
    }

    /**
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    private static function document(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    /**
     * Gives the published page a translation in the locale, or changes the one it has
     */
    private static function translate(PageInterface $page, string $locale, ?string $title, ?string $slug, bool $shown = true): void
    {
        $page = self::reloadPage($page);
        $translation = $page->getTranslations()->get($locale);
        if (null === $translation) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $page->addTranslation($translation);
        }

        $translation->setTitle($title);
        $translation->setSlug($slug);
        $translation->setShown($shown);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
        $manager->clear();
    }
}
