<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\EntryFormat;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

final class EntryDenormalizerTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_round_trips_the_entry_format_with_its_translations_and_tree_in_the_listed_key_order(): void
    {
        $rows = self::page(['en_US' => ['About us', 'about-us']]);

        $draft = clone $rows;
        $draft->getTranslation('en_US')->setTitle('About');
        $draft->setCurrentLocale('da_DK');
        $draft->setTitle('Om os');
        $draft->setSlug('om-os');
        $heading = new StoredBlock(self::KEY, 'heading', 1);
        $heading->setValue('text', 'en_US', 'Hello');
        $heading->setValue('text', 'da_DK', 'Hej');
        $heading->setValue('level', null, 'h3');
        $draft->setBlockTree(new StoredTree(new StoredLayout('standard', 1), [$heading]));

        $json = CanonicalJson::encode($this->normalizer()->normalize($draft));
        self::assertSame(
            sprintf(
                '{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{},"translations":{"da_DK":{"shown":true,"fields":{"slug":"om-os","title":"Om os"},"translated":{}},"en_US":{"shown":true,"fields":{"slug":"about-us","title":"About"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"level":"h3","text":{"da_DK":"Hej","en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}}',
                $rows->getUuid(),
                $rows->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
                self::KEY,
            ),
            $json,
        );

        $this->denormalizer()->denormalize(CanonicalJson::decode($json), $rows);

        self::assertSame($json, CanonicalJson::encode($this->normalizer()->normalize($rows)));
        self::assertInstanceOf(PageTranslation::class, $rows->getTranslations()->get('da_DK'));
        self::assertSame($rows, $rows->getTranslation('da_DK')->getTranslatable());
    }

    /**
     * The shown switch is drafted, so a translation goes live together with its finished text
     *
     * @test
     */
    public function it_copies_whether_each_translation_is_shown(): void
    {
        $rows = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']]);
        $rows->getTranslation('da_DK')->setShown(false);
        $draft = clone $rows;
        $draft->getTranslation('da_DK')->setShown(true);
        $draft->getTranslation('en_US')->setShown(false);
        $draft->setCurrentLocale('de_DE');
        $draft->setTitle('Über uns');
        $draft->getTranslation('de_DE')->setShown(false);

        $this->denormalizer()->denormalize(CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($draft))), $rows);

        self::assertTrue($rows->getTranslation('da_DK')->isShown());
        self::assertFalse($rows->getTranslation('en_US')->isShown());
        self::assertFalse($rows->getTranslation('de_DE')->isShown());
    }

    /**
     * @test
     */
    public function it_copies_the_translation_state_of_each_translation_s_fields(): void
    {
        $fingerprint = hash('sha256', '"About us"');
        $rows = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']]);
        $rows->getTranslation('en_US')->setTranslated(['title' => ['from' => 'da_DK', 'fingerprint' => $fingerprint]]);
        $draft = clone $rows;
        $draft->getTranslation('en_US')->setTranslated([]);
        $draft->getTranslation('da_DK')->setTranslated(['title' => ['from' => 'en_US', 'fingerprint' => $fingerprint], 'slug' => ['from' => 'en_US', 'fingerprint' => $fingerprint]]);

        $this->denormalizer()->denormalize(CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($draft))), $rows);

        self::assertSame([], $rows->getTranslation('en_US')->getTranslated());
        self::assertSame(
            ['slug' => ['from' => 'en_US', 'fingerprint' => $fingerprint], 'title' => ['from' => 'en_US', 'fingerprint' => $fingerprint]],
            $rows->getTranslation('da_DK')->getTranslated(),
        );
    }

    /**
     * Drafts saved before translation state was kept have none
     *
     * @test
     */
    public function it_reads_a_translation_without_translation_state_as_having_none(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->getTranslation('en_US')->setTranslated(['title' => ['from' => 'da_DK', 'fingerprint' => hash('sha256', '"Om os"')]]);
        $data = CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($page)));
        self::assertIsArray($data);
        $data['translations'] = ['en_US' => ['shown' => true, 'fields' => ['title' => 'About us']]];

        $this->denormalizer()->denormalize($data, $page);

        self::assertSame([], $page->getTranslation('en_US')->getTranslated());
    }

    /**
     * @test
     */
    public function it_removes_the_translations_the_entry_format_does_not_have(): void
    {
        $rows = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']]);
        $draft = clone $rows;
        $draft->removeTranslation($draft->getTranslation('da_DK'));

        $this->denormalizer()->denormalize(CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($draft))), $rows);

        self::assertSame(['en_US'], $rows->getTranslations()->getKeys());
    }

    /**
     * @test
     */
    public function it_leaves_the_slug_alone_for_a_content_type_without_its_own_url(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        $recipe->setSlug('pancakes');
        $draft = clone $recipe;
        $draft->setTitle('Crêpes');

        $this->denormalizer()->denormalize(CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($draft))), $recipe);

        self::assertSame('Crêpes', $recipe->getTitle());
        self::assertSame('pancakes', $recipe->getSlug());
    }

    /**
     * @test
     */
    public function it_leaves_a_translation_without_a_title_or_a_slug_without_them(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $data = CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($page)));
        self::assertIsArray($data);
        $data['translations'] = ['en_US' => ['shown' => true, 'fields' => new \stdClass(), 'translated' => new \stdClass()]];

        $this->denormalizer()->denormalize($data, $page);

        self::assertNull($page->getTranslation('en_US')->getTitle());
        self::assertNull($page->getTranslation('en_US')->getSlug());
    }

    /**
     * @test
     */
    public function it_needs_a_translation_model_to_add_a_locale(): void
    {
        $rows = self::page(['en_US' => ['About us', 'about-us']]);
        $draft = clone $rows;
        $draft->setCurrentLocale('da_DK');
        $draft->setTitle('Om os');
        $data = CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($draft)));

        $denormalizer = new EntryDenormalizer(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => null, 'resource' => 'setono_sylius_cms.page'],
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('da_DK');

        $denormalizer->denormalize($data, $rows);
    }

    /**
     * @test
     *
     * @dataProvider invalidEntries
     */
    public function it_refuses_data_that_is_not_this_entry_in_the_entry_format(callable $change, string $message): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $data = CanonicalJson::decode(CanonicalJson::encode($this->normalizer()->normalize($page)));
        self::assertIsArray($data);

        $this->expectException(InvalidEntryFormatException::class);
        $this->expectExceptionMessage($message);

        $this->denormalizer()->denormalize($change($data), $page);
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): mixed, string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'a list' => [static fn (array $data): array => [$data], 'An entry must be a JSON object'];
        yield 'no UUID' => [static fn (array $data): array => array_diff_key($data, ['uuid' => true]), 'is not the entry'];
        yield 'no content type' => [static fn (array $data): array => array_diff_key($data, ['contentType' => true]), 'is not the entry'];
        yield 'no translations' => [static fn (array $data): array => array_diff_key($data, ['translations' => true]), 'The translations must be a JSON object'];
        yield 'no tree' => [static fn (array $data): array => array_diff_key($data, ['tree' => true]), 'The tree is invalid: The block tree must be a JSON object'];
        yield 'another entry' => [static fn (array $data): array => ['uuid' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e'] + $data, 'is not the entry'];
        yield 'another content type' => [static fn (array $data): array => ['contentType' => 'blog_post'] + $data, 'is not the entry'];
        yield 'translations that are a list' => [static fn (array $data): array => ['translations' => ['en_US']] + $data, 'The translations must be a JSON object'];
        yield 'a translation that is a string' => [static fn (array $data): array => ['translations' => ['en_US' => 'About us']] + $data, 'The translation en_US must be a JSON object'];
        yield 'a translation without fields' => [static fn (array $data): array => ['translations' => ['en_US' => ['shown' => true]]] + $data, 'The fields of the translation en_US must be a JSON object'];
        yield 'a title that is a number' => [static fn (array $data): array => ['translations' => ['en_US' => ['fields' => ['title' => 1]]]] + $data, 'The title of the translation en_US must be a string or null'];
        yield 'a slug that is a list' => [static fn (array $data): array => ['translations' => ['en_US' => ['fields' => ['slug' => ['about-us']]]]] + $data, 'The slug of the translation en_US must be a string or null'];
        yield 'a translation without a shown switch' => [static fn (array $data): array => ['translations' => ['en_US' => ['fields' => ['title' => 'About us']]]] + $data, 'The shown switch of the translation en_US must be true or false'];
        yield 'a shown switch that is a string' => [static fn (array $data): array => ['translations' => ['en_US' => ['shown' => 'yes', 'fields' => ['title' => 'About us']]]] + $data, 'The shown switch of the translation en_US must be true or false'];
        $translation = static fn (mixed $translated): array => ['translations' => ['en_US' => ['shown' => true, 'fields' => ['title' => 'About us'], 'translated' => $translated]]];
        $fingerprint = hash('sha256', '"Om os"');
        yield 'translation state that is a list' => [static fn (array $data): array => $translation(['title']) + $data, 'The translation state of the translation en_US must be a JSON object'];
        yield 'translation state of a field that is a string' => [static fn (array $data): array => $translation(['title' => 'da_DK']) + $data, 'The translation state of the title of the translation en_US must be a JSON object'];
        yield 'translation state without a locale' => [static fn (array $data): array => $translation(['title' => ['fingerprint' => $fingerprint]]) + $data, 'The translation state of the title of the translation en_US must have the locale it was translated from and a fingerprint'];
        yield 'translation state with an empty locale' => [static fn (array $data): array => $translation(['title' => ['from' => '', 'fingerprint' => $fingerprint]]) + $data, 'must have the locale it was translated from and a fingerprint'];
        yield 'translation state with a locale that is a number' => [static fn (array $data): array => $translation(['title' => ['from' => 1, 'fingerprint' => $fingerprint]]) + $data, 'must have the locale it was translated from and a fingerprint'];
        yield 'translation state without a fingerprint' => [static fn (array $data): array => $translation(['title' => ['from' => 'da_DK']]) + $data, 'must have the locale it was translated from and a fingerprint'];
        yield 'translation state with a fingerprint that is a number' => [static fn (array $data): array => $translation(['title' => ['from' => 'da_DK', 'fingerprint' => 1]]) + $data, 'must have the locale it was translated from and a fingerprint'];
        yield 'translation state with a fingerprint that is not a SHA-256' => [static fn (array $data): array => $translation(['title' => ['from' => 'da_DK', 'fingerprint' => 'abc']]) + $data, 'must have the locale it was translated from and a fingerprint'];
        yield 'a tree without blocks' => [static fn (array $data): array => ['tree' => ['layout' => null]] + $data, 'The tree is invalid: The block tree must have a list of blocks'];
    }

    /**
     * @param array<string, array{string, string}> $translations locale => [title, slug]
     */
    private static function page(array $translations): Page
    {
        $page = new Page();
        foreach ($translations as $locale => [$title, $slug]) {
            $page->setCurrentLocale($locale);
            $page->setTitle($title);
            $page->setSlug($slug);
        }

        return $page;
    }

    private function normalizer(): EntryNormalizer
    {
        return new EntryNormalizer($this->registry());
    }

    private function denormalizer(): EntryDenormalizer
    {
        return new EntryDenormalizer($this->registry());
    }

    private function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);
    }
}
