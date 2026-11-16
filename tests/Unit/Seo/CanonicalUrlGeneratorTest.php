<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Seo;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGenerator;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\PostTranslation;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The canonical URL is the entry's own absolute URL in the channel and locale, without the query string.
 * See "Head tags" in docs/spec/urls-and-seo.md
 */
final class CanonicalUrlGeneratorTest extends TestCase
{
    /**
     * @test
     */
    public function it_generates_the_entrys_absolute_url_in_the_locale(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os/team']);

        self::assertSame('https://shop.example/en_US/about-us', self::generator()->generate($page, new Channel(), 'en_US'));
        self::assertSame('https://shop.example/da_DK/om-os/team', self::generator()->generate($page, new Channel(), 'da_DK'));
    }

    /**
     * @test
     */
    public function it_puts_the_content_types_prefix_in_the_url(): void
    {
        $post = new Post();
        $post->setCurrentLocale('en_US');
        $post->setSlug('hello');

        self::assertSame('https://shop.example/en_US/posts/hello', self::generator()->generate($post, new Channel(), 'en_US'));
    }

    /**
     * @test
     */
    public function it_generates_none_in_a_locale_without_a_slug(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => null]);

        self::assertNull(self::generator()->generate($page, new Channel(), 'da_DK'));
        self::assertNull(self::generator()->generate($page, new Channel(), 'de_DE'));
    }

    /**
     * @test
     */
    public function it_generates_none_for_a_content_type_without_its_own_url(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setSlug('pancakes');

        self::assertNull(self::generator()->generate($recipe, new Channel(), 'en_US'));
    }

    /**
     * @param array<string, string|null> $slugs by locale
     */
    private static function page(array $slugs): Page
    {
        $page = new Page();
        foreach ($slugs as $locale => $slug) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle('Title');
            $translation->setSlug($slug);
            $page->addTranslation($translation);
        }

        return $page;
    }

    private static function generator(): CanonicalUrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.+(?<!/)']));

        return new CanonicalUrlGenerator(
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'app.post'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            new UrlGenerator($routes, new RequestContext(host: 'shop.example', scheme: 'https')),
        );
    }
}
