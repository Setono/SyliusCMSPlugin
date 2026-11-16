<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class EntryFieldsTest extends TestCase
{
    /**
     * @test
     */
    public function a_content_type_with_its_own_url_has_a_slug_and_the_seo_fields(): void
    {
        $page = self::registry()->get('page');

        self::assertSame(['title', 'slug', 'metaTitle', 'metaDescription'], EntryFields::translatable($page));
        self::assertSame(['noindex'], EntryFields::shared($page));
    }

    /**
     * @test
     */
    public function a_content_type_without_its_own_url_has_only_a_title(): void
    {
        $recipe = self::registry()->get('recipe');

        self::assertSame(['title'], EntryFields::translatable($recipe));
        self::assertSame([], EntryFields::shared($recipe));
    }

    /**
     * @test
     */
    public function it_reads_and_writes_the_translatable_fields_of_a_translation(): void
    {
        $translation = new PageTranslation();

        foreach (['title' => 'About us', 'slug' => 'about-us', 'metaTitle' => 'About us | Shop', 'metaDescription' => 'All about us'] as $field => $value) {
            EntryFields::write($translation, $field, $value);
        }

        self::assertSame('About us', $translation->getTitle());
        self::assertSame('about-us', $translation->getSlug());
        self::assertSame('About us | Shop', $translation->getMetaTitle());
        self::assertSame('All about us', $translation->getMetaDescription());
        self::assertSame('About us', EntryFields::read($translation, 'title'));
        self::assertSame('about-us', EntryFields::read($translation, 'slug'));
        self::assertSame('About us | Shop', EntryFields::read($translation, 'metaTitle'));
        self::assertSame('All about us', EntryFields::read($translation, 'metaDescription'));
    }

    /**
     * @test
     */
    public function it_refuses_to_read_a_field_that_is_not_one_of_them(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"noindex" is not one of the translatable entry fields');

        EntryFields::read(new PageTranslation(), 'noindex');
    }

    /**
     * @test
     */
    public function it_refuses_to_write_a_field_that_is_not_one_of_them(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"subtitle" is not one of the translatable entry fields');

        EntryFields::write(new PageTranslation(), 'subtitle', 'Hello');
    }

    private static function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);
    }
}
