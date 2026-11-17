<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\EntryFields;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

final class EntryFieldsTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_a_content_type_with_its_own_url_a_slug_and_the_seo_fields(): void
    {
        $page = self::registry()->get('page');

        self::assertSame(['title', 'slug', 'metaTitle', 'metaDescription'], EntryFields::translatable($page));
        self::assertSame(['noindex'], EntryFields::shared($page));
    }

    /**
     * @test
     */
    public function it_gives_a_content_type_without_its_own_url_only_a_title(): void
    {
        $recipe = self::registry()->get('recipe');

        self::assertSame(['title'], EntryFields::translatable($recipe));
        self::assertSame([], EntryFields::shared($recipe));
    }

    /**
     * @test
     */
    public function it_gives_a_snippet_its_declared_context_as_a_shared_field(): void
    {
        self::assertSame(['title'], EntryFields::translatable(self::registry()->get('snippet')));
        self::assertSame(['context'], EntryFields::shared(self::registry()->get('snippet')));
    }

    /**
     * @test
     */
    public function it_reads_and_writes_the_shared_fields_of_an_entry_with_null_for_their_default(): void
    {
        $page = new Page();
        EntryFields::writeShared($page, 'noindex', EntryFields::sharedValue('noindex', true));
        self::assertTrue($page->isNoindex());
        self::assertTrue(EntryFields::readShared($page, 'noindex'));
        EntryFields::writeShared($page, 'noindex', EntryFields::sharedValue('noindex', null));
        self::assertFalse($page->isNoindex());

        $snippet = new Snippet();
        EntryFields::writeShared($snippet, 'context', EntryFields::sharedValue('context', ['product', 'taxon']));
        self::assertSame(['product', 'taxon'], $snippet->getContext());
        self::assertSame(['product', 'taxon'], EntryFields::readShared($snippet, 'context'));
        EntryFields::writeShared($snippet, 'context', EntryFields::sharedValue('context', null));
        self::assertSame([], $snippet->getContext());
    }

    /**
     * @test
     *
     * @dataProvider provideValuesThatDoNotFit
     */
    public function it_refuses_a_shared_value_that_does_not_fit_its_field(string $field, mixed $value, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        EntryFields::sharedValue($field, $value);
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public function provideValuesThatDoNotFit(): iterable
    {
        yield 'hide from search engines that is a string' => ['noindex', 'yes', 'must be true, false or null'];
        yield 'a context that is a string' => ['context', 'product', 'must be a list of context keys or null'];
        yield 'a context that is a map' => ['context', ['page' => 'product'], 'must be a list of context keys or null'];
        yield 'a context with a key that is no code' => ['context', ['Product'], 'must be a list of context keys or null'];
        yield 'a context with a key that is a number' => ['context', [1], 'must be a list of context keys or null'];
        yield 'a field that is not a shared one' => ['title', 'About us', '"title" is not one of the shared entry fields'];
    }

    /**
     * @test
     */
    public function it_refuses_to_read_a_shared_field_the_entry_does_not_have(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"context" is not one of the shared entry fields of this entry');

        EntryFields::readShared(new Page(), 'context');
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
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
        ]);
    }
}
