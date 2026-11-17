<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\EntryFormat;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class EntryNormalizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_writes_an_entry_in_the_entry_format(): void
    {
        $page = new Page();
        foreach (['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']] as $locale => [$title, $slug]) {
            $page->setCurrentLocale($locale);
            $page->setTitle($title);
            $page->setSlug($slug);
        }

        $json = CanonicalJson::encode($this->normalizer()->normalize($page));

        self::assertSame(
            sprintf(
                '{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{"noindex":false},"translations":{"da_DK":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":"om-os","title":"Om os"},"translated":{}},"en_US":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[]}}',
                $page->getUuid(),
                $page->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            ),
            $json,
        );
    }

    /**
     * @test
     */
    public function it_writes_the_seo_fields_of_a_content_type_with_its_own_url(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->getTranslation('en_US')->setMetaTitle('About us | Shop');
        $page->getTranslation('en_US')->setMetaDescription('All about us');
        $page->setNoindex(true);

        $json = CanonicalJson::encode($this->normalizer()->normalize($page));

        self::assertStringContainsString('"fields":{"noindex":true}', $json);
        self::assertStringContainsString('"fields":{"metaDescription":"All about us","metaTitle":"About us | Shop","slug":null,"title":"About us"}', $json);
    }

    /**
     * Entries without their own URL have no slug and no SEO fields
     *
     * @test
     */
    public function it_writes_only_the_title_of_a_content_type_without_its_own_url(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');

        $json = CanonicalJson::encode($this->normalizer()->normalize($recipe));

        self::assertStringContainsString('"fields":{},"translations":{"en_US":{"shown":true,"fields":{"title":"Pancakes"},"translated":{}}}', $json);
    }

    /**
     * @test
     */
    public function it_writes_a_held_back_translation_as_not_shown(): void
    {
        $page = new Page();
        $page->setCurrentLocale('da_DK');
        $page->setTitle('Om os');
        $page->getTranslation('da_DK')->setShown(false);

        self::assertStringContainsString('"translations":{"da_DK":{"shown":false,', CanonicalJson::encode($this->normalizer()->normalize($page)));
    }

    /**
     * @test
     */
    public function it_writes_the_translation_state_of_a_translation_s_fields_in_the_listed_key_order(): void
    {
        $fingerprint = hash('sha256', '"About us"');
        $page = new Page();
        $page->setCurrentLocale('da_DK');
        $page->setTitle('Om os');
        $page->getTranslation('da_DK')->setTranslated(['title' => ['fingerprint' => $fingerprint, 'from' => 'en_US']]);

        self::assertStringContainsString(
            sprintf('"translations":{"da_DK":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":null,"title":"Om os"},"translated":{"title":{"from":"en_US","fingerprint":"%s"}}}}', $fingerprint),
            CanonicalJson::encode($this->normalizer()->normalize($page)),
        );
    }

    /**
     * @test
     */
    public function it_writes_created_at_in_utc(): void
    {
        $page = new Page();
        $createdAt = new \ReflectionProperty($page, 'createdAt');
        $createdAt->setValue($page, new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        $json = CanonicalJson::encode($this->normalizer()->normalize($page));

        self::assertStringContainsString('"createdAt":"2026-11-14T11:30:00Z"', $json);
    }

    /**
     * @test
     */
    public function it_writes_the_context_a_snippet_declares(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);

        self::assertStringContainsString('"fields":{"context":["product"]}', CanonicalJson::encode($this->normalizer()->normalize($snippet)));
        self::assertStringContainsString('"fields":{"context":[]}', CanonicalJson::encode($this->normalizer()->normalize(new Snippet())));
    }

    /**
     * @test
     */
    public function it_writes_an_entry_without_translations_with_an_empty_map(): void
    {
        self::assertStringContainsString('"translations":{}', CanonicalJson::encode($this->normalizer()->normalize(new Page())));
    }

    private function normalizer(): EntryNormalizer
    {
        return new EntryNormalizer(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
        ]));
    }
}
