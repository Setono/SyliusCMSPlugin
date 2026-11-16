<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Seo;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Seo\MetaValues;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\PostTranslation;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Each SEO field falls back through the fallback chain first, and only then to the field it names.
 * See "SEO fields" in docs/spec/urls-and-seo.md
 */
final class MetaValuesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_meta_title_in_the_locale(): void
    {
        $page = self::page(['de_CH' => ['title' => 'Über uns', 'metaTitle' => 'Über uns | Schweiz']]);

        self::assertSame('Über uns | Schweiz', $this->metaValues()->title($page, 'de_CH'));
    }

    /**
     * @test
     */
    public function the_meta_title_falls_back_through_the_chain_before_it_falls_back_to_the_title(): void
    {
        $page = self::page([
            'de_CH' => ['title' => 'Über uns (CH)', 'metaTitle' => ''],
            'de_DE' => ['title' => 'Über uns', 'metaTitle' => 'Über uns | Deutschland'],
        ]);

        self::assertSame('Über uns | Deutschland', $this->metaValues()->title($page, 'de_CH'));
    }

    /**
     * @test
     */
    public function the_meta_title_falls_back_to_the_title_through_the_chain_when_the_chain_has_no_meta_title(): void
    {
        $page = self::page([
            'de_CH' => ['title' => '', 'metaTitle' => null],
            'de_DE' => ['title' => 'Über uns', 'metaTitle' => null],
            'en_US' => ['title' => 'About us', 'metaTitle' => null],
        ]);

        self::assertSame('Über uns', $this->metaValues()->title($page, 'de_CH'));
    }

    /**
     * @test
     */
    public function there_is_no_meta_title_without_a_title(): void
    {
        self::assertNull($this->metaValues()->title(self::page(['de_CH' => ['title' => null]]), 'de_CH'));
    }

    /**
     * @test
     */
    public function the_meta_description_falls_back_through_the_chain(): void
    {
        $post = self::post([
            'de_CH' => ['metaDescription' => '', 'excerpt' => 'Auszug (CH)'],
            'de_DE' => ['metaDescription' => 'Alles über uns'],
        ]);

        self::assertSame('Alles über uns', $this->metaValues()->description($post, 'de_CH'));
    }

    /**
     * A blog post's meta description falls back to its excerpt
     *
     * @test
     */
    public function the_meta_description_falls_back_to_the_field_the_content_type_names_through_the_chain_when_the_chain_has_none(): void
    {
        $post = self::post([
            'de_CH' => ['metaDescription' => null, 'excerpt' => ''],
            'de_DE' => ['metaDescription' => null, 'excerpt' => 'Auszug'],
            'en_US' => ['metaDescription' => null, 'excerpt' => 'Excerpt'],
        ]);

        self::assertSame('Auszug', $this->metaValues()->description($post, 'de_CH'));
    }

    /**
     * @test
     */
    public function there_is_no_meta_description_when_neither_it_nor_the_field_it_names_has_a_value(): void
    {
        self::assertNull($this->metaValues()->description(self::post(['de_CH' => ['metaDescription' => null, 'excerpt' => null]]), 'de_CH'));
    }

    /**
     * @test
     */
    public function a_value_of_the_field_it_names_that_is_not_text_is_no_meta_description(): void
    {
        $post = self::post(['de_CH' => ['metaDescription' => null]]);
        $translation = $post->getTranslation('de_CH');
        self::assertInstanceOf(PostTranslation::class, $translation);
        $translation->excerpt = ['not', 'text'];

        self::assertNull($this->metaValues()->description($post, 'de_CH'));
    }

    /**
     * @test
     */
    public function there_is_no_meta_description_to_fall_back_to_when_the_content_type_names_no_field(): void
    {
        $page = self::page(['en_US' => ['title' => 'About us', 'metaDescription' => 'All about us']]);

        self::assertSame('All about us', $this->metaValues()->description($page, 'de_CH'));

        $page->getTranslation('en_US')->setMetaDescription(null);
        self::assertNull($this->metaValues()->description($page, 'de_CH'));
    }

    /**
     * @param array<string, array{title?: string|null, metaTitle?: string|null, metaDescription?: string|null}> $translations by locale
     */
    private static function page(array $translations): Page
    {
        $page = new Page();
        foreach ($translations as $locale => $fields) {
            $translation = new PageTranslation();
            self::fill($translation, $locale, $fields);
            $page->addTranslation($translation);
        }

        return $page;
    }

    /**
     * @param array<string, array{metaDescription?: string|null, excerpt?: string|null}> $translations by locale
     */
    private static function post(array $translations): Post
    {
        $post = new Post();
        foreach ($translations as $locale => $fields) {
            $translation = new PostTranslation();
            $translation->excerpt = $fields['excerpt'] ?? null;
            self::fill($translation, $locale, $fields);
            $post->addTranslation($translation);
        }

        return $post;
    }

    /**
     * @param array{title?: string|null, metaTitle?: string|null, metaDescription?: string|null} $fields
     */
    private static function fill(PageTranslation|PostTranslation $translation, string $locale, array $fields): void
    {
        $translation->setLocale($locale);
        $translation->setTitle(array_key_exists('title', $fields) ? $fields['title'] : 'Title');
        $translation->setMetaTitle($fields['metaTitle'] ?? null);
        $translation->setMetaDescription($fields['metaDescription'] ?? null);
    }

    private function metaValues(): MetaValues
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('de_CH')->willReturn(['de_CH', 'de_DE', 'en_US']);

        return new MetaValues(
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'app.post'],
            ]),
            new FallbackValues(new FieldHandlerLocator(new ServiceLocator([])), $fallbackChain->reveal()),
        );
    }
}
