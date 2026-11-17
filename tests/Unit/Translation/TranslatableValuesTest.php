<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Translation\Fixture\Promo;
use Setono\SyliusCMSPlugin\Translation\Completeness;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValue;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Translation\ValueState;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class TranslatableValuesTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    /**
     * @test
     */
    public function it_gives_a_value_missing_in_the_locale_while_another_has_one_as_untranslated_from_the_locale_it_falls_back_to(): void
    {
        $page = self::page([self::heading(self::A, ['en_US' => 'Hello', 'de_DE' => 'Hallo', 'fr_FR' => ''])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Untranslated, 'de_DE', 'Hallo', Fingerprint::of('Hallo'), null)],
            $this->blockValues($page, 'de_CH'),
        );
        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Untranslated, 'de_DE', 'Hallo', Fingerprint::of('Hallo'), null)],
            $this->blockValues($page, 'fr_FR'),
        );
    }

    /**
     * @test
     */
    public function it_gives_an_untranslated_value_the_locale_its_fallback_chain_reaches_before_one_first_by_code(): void
    {
        $page = self::page([self::heading(self::A, ['da_DK' => 'Hej', 'en_US' => 'Hello', 'fr_FR' => ''])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Untranslated, 'en_US', 'Hello', Fingerprint::of('Hello'), null)],
            $this->blockValues($page, 'de_CH'),
        );
    }

    /**
     * @test
     */
    public function it_gives_an_untranslated_value_the_first_locale_by_code_that_has_one_when_its_fallback_chain_reaches_none(): void
    {
        $page = self::page([self::heading(self::A, ['sv_SE' => 'Hej', 'nb_NO' => 'Hei', 'da_DK' => ''])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Untranslated, 'nb_NO', 'Hei', Fingerprint::of('Hei'), null)],
            $this->blockValues($page, 'da_DK'),
        );

        // The translations of the rows come in the order they were added, unlike the tree's values, which are sorted
        $recipe = new Recipe();
        foreach (['sv_SE' => 'Pannkakor', 'nb_NO' => 'Pannekaker'] as $localeCode => $title) {
            $translation = new EntryTranslation();
            $translation->setLocale($localeCode);
            $translation->setTitle($title);
            $recipe->addTranslation($translation);
        }

        self::assertEquals(
            [new TranslatableValue('entry.title', ValueState::Untranslated, 'nb_NO', 'Pannekaker', Fingerprint::of('Pannekaker'), null)],
            $this->values()->of($recipe, 'da_DK'),
        );
    }

    /**
     * A stored value that doesn't fit its field type counts as missing. See "Values that don't fit" in
     * docs/spec/content-model.md
     *
     * @test
     */
    public function it_counts_a_value_that_does_not_fit_its_field_type_as_missing(): void
    {
        $page = self::page([self::heading(self::A, ['da_DK' => 42, 'en_US' => 'Hello', 'sv_SE' => ['not' => 'text']])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Untranslated, 'en_US', 'Hello', Fingerprint::of('Hello'), null)],
            $this->blockValues($page, 'da_DK'),
        );
        self::assertSame([], $this->blockValues(self::page([self::heading(self::A, ['da_DK' => 42, 'en_US' => ''])]), 'en_US'));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_value_no_locale_has(): void
    {
        $page = self::page([
            self::heading(self::A, ['en_US' => '', 'da_DK' => null]),
            new StoredBlock(self::B, 'heading', 1),
            new StoredBlock(self::C, 'heading', 1, fields: ['text' => 'not a locale map', 'level' => ['en_US' => 'h2']]),
        ]);

        self::assertSame([], $this->blockValues($page, 'da_DK'));
    }

    /**
     * @test
     */
    public function it_gives_a_value_whose_source_has_changed_since_as_outdated(): void
    {
        $page = self::page([self::heading(self::A, ['en_US' => 'Hello there', 'da_DK' => 'Hej'], ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::Outdated, 'en_US', 'Hello there', Fingerprint::of('Hello there'), 'Hej')],
            $this->blockValues($page, 'da_DK'),
        );
    }

    /**
     * @test
     */
    public function it_gives_a_value_whose_source_is_unchanged_as_up_to_date(): void
    {
        $page = self::page([self::heading(self::A, ['en_US' => 'Hello', 'da_DK' => 'Hej'], ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::UpToDate, 'en_US', 'Hello', Fingerprint::of('Hello'), 'Hej')],
            $this->blockValues($page, 'da_DK'),
        );
    }

    /**
     * @test
     */
    public function it_gives_a_value_typed_without_a_source_as_up_to_date_without_one(): void
    {
        $page = self::page([self::heading(self::A, ['en_US' => 'Hello', 'da_DK' => 'Hej'], ['de_DE' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hi')]])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::UpToDate, null, null, null, 'Hej')],
            $this->blockValues($page, 'da_DK'),
        );
        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::UpToDate, null, null, null, 'Hello')],
            $this->blockValues($page, 'en_US'),
        );
    }

    /**
     * @test
     */
    public function it_gives_a_value_whose_source_has_no_value_any_more_as_up_to_date(): void
    {
        $page = self::page([self::heading(self::A, ['en_US' => '', 'da_DK' => 'Hej'], ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]])]);

        self::assertEquals(
            [new TranslatableValue('block.' . self::A . '.text', ValueState::UpToDate, 'en_US', null, null, 'Hej')],
            $this->blockValues($page, 'da_DK'),
        );
    }

    /**
     * @test
     */
    public function it_reads_translation_state_that_is_not_a_map_as_none(): void
    {
        $page = self::page([
            self::heading(self::A, ['en_US' => 'Hello', 'da_DK' => 'Hej'], 'nope'),
            self::heading(self::B, ['en_US' => 'Hello', 'da_DK' => 'Hej'], ['da_DK' => 'en_US']),
            self::heading(self::C, ['en_US' => 'Hello', 'da_DK' => 'Hej'], ['da_DK' => ['from' => 1, 'fingerprint' => Fingerprint::of('Hi')]]),
        ]);

        self::assertSame(
            [ValueState::UpToDate, ValueState::UpToDate, ValueState::UpToDate],
            array_map(static fn (TranslatableValue $value): ValueState => $value->state, $this->blockValues($page, 'da_DK')),
        );
    }

    /**
     * @test
     */
    public function it_compares_rich_text_with_its_keys_in_any_order_and_counts_an_empty_document_as_missing(): void
    {
        $english = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]]];
        $reordered = ['content' => [['content' => [['text' => 'Hello', 'type' => 'text']], 'type' => 'paragraph']], 'type' => 'doc'];
        $danish = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hej']]]]];
        $empty = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
        $page = self::page([
            new StoredBlock(self::A, 'rich_text', 1, fields: ['content' => ['en_US' => $reordered, 'da_DK' => $danish]], translated: ['content' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of($english)]]]),
            new StoredBlock(self::B, 'rich_text', 1, fields: ['content' => ['en_US' => $english, 'da_DK' => $empty]]),
        ]);

        self::assertSame(
            [ValueState::UpToDate, ValueState::Untranslated],
            array_map(static fn (TranslatableValue $value): ValueState => $value->state, $this->blockValues($page, 'da_DK')),
        );
    }

    /**
     * Nobody reads a block in a locale it's hidden in, nor what's inside it
     *
     * @test
     */
    public function it_leaves_out_the_values_of_a_block_a_locale_condition_hides_in_the_locale_and_of_the_blocks_inside_it(): void
    {
        $hidden = new StoredBlock(self::A, 'group', 1, conditions: [['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]]], children: [self::heading(self::B, ['en_US' => 'Inside'])]);
        $page = self::page([$hidden, self::heading(self::C, ['en_US' => 'Outside'])]);

        self::assertSame(['block.' . self::C . '.text'], array_map(static fn (TranslatableValue $value): string => $value->path, $this->blockValues($page, 'da_DK')));
        self::assertSame(['block.' . self::B . '.text', 'block.' . self::C . '.text'], array_map(static fn (TranslatableValue $value): string => $value->path, $this->blockValues($page, 'fr_FR')));
    }

    /**
     * @test
     */
    public function it_gives_the_values_of_the_entry_fields_the_layout_and_the_blocks_at_any_depth_in_order(): void
    {
        $page = self::page(
            [
                new StoredBlock(self::A, 'group', 1, fields: ['fullWidth' => true], children: [self::heading(self::B, ['en_US' => 'Hello'])]),
                new StoredBlock(self::C, 'removed', 1, fields: ['text' => ['en_US' => 'Hello']]),
            ],
            new StoredLayout('promo', 1, ['tagline' => ['en_US' => 'Sale', 'da_DK' => 'Udsalg'], 'dark' => true], ['tagline' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Big sale')]]]),
        );
        $page->getTranslation('en_US')->setTranslated(['title' => ['from' => 'da_DK', 'fingerprint' => Fingerprint::of('Om os')]]);
        $danish = new PageTranslation();
        $danish->setLocale('da_DK');
        $danish->setTitle('Om os');
        $danish->setSlug('');
        $page->addTranslation($danish);

        self::assertEquals([
            new TranslatableValue('entry.title', ValueState::UpToDate, null, null, null, 'Om os'),
            new TranslatableValue('entry.slug', ValueState::Untranslated, 'en_US', 'about-us', Fingerprint::of('about-us'), null),
            new TranslatableValue('layout.tagline', ValueState::Outdated, 'en_US', 'Sale', Fingerprint::of('Sale'), 'Udsalg'),
            new TranslatableValue('block.' . self::B . '.text', ValueState::Untranslated, 'en_US', 'Hello', Fingerprint::of('Hello'), null),
        ], $this->values()->of($page, 'da_DK'));
        self::assertEquals([
            new TranslatableValue('entry.title', ValueState::UpToDate, 'da_DK', 'Om os', Fingerprint::of('Om os'), 'About us'),
            new TranslatableValue('entry.slug', ValueState::UpToDate, null, null, null, 'about-us'),
            new TranslatableValue('layout.tagline', ValueState::UpToDate, null, null, null, 'Sale'),
            new TranslatableValue('block.' . self::B . '.text', ValueState::UpToDate, null, null, null, 'Hello'),
        ], $this->values()->of($page, 'en_US'));
    }

    /**
     * The meta title and meta description are translated like the title. See "SEO fields" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_gives_the_meta_title_and_the_meta_description_after_the_slug(): void
    {
        $page = self::page([]);
        $page->getTranslation('en_US')->setMetaTitle('About us | Shop');
        $page->getTranslation('en_US')->setMetaDescription('All about us');
        $danish = new PageTranslation();
        $danish->setLocale('da_DK');
        $danish->setTitle('Om os');
        $danish->setSlug('om-os');
        $danish->setMetaTitle('Om os | Butik');
        $danish->setTranslated(['metaTitle' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('About us')]]);
        $page->addTranslation($danish);

        self::assertEquals([
            new TranslatableValue('entry.title', ValueState::UpToDate, null, null, null, 'Om os'),
            new TranslatableValue('entry.slug', ValueState::UpToDate, null, null, null, 'om-os'),
            new TranslatableValue('entry.metaTitle', ValueState::Outdated, 'en_US', 'About us | Shop', Fingerprint::of('About us | Shop'), 'Om os | Butik'),
            new TranslatableValue('entry.metaDescription', ValueState::Untranslated, 'en_US', 'All about us', Fingerprint::of('All about us'), null),
        ], $this->values()->of($page, 'da_DK'));
    }

    /**
     * @test
     */
    public function it_gives_only_the_title_of_a_content_type_without_its_own_url_and_nothing_of_a_layout_it_does_not_know(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        $recipe->setSlug('pancakes');
        $recipe->getTranslation('en_US')->setMetaTitle('Pancakes | Recipes');
        $recipe->getTranslation('en_US')->setMetaDescription('Thin ones');
        $recipe->setBlockTree(new StoredTree(new StoredLayout('promo', 1, ['tagline' => ['en_US' => 'Yum']]), []));

        self::assertEquals(
            [new TranslatableValue('entry.title', ValueState::Untranslated, 'en_US', 'Pancakes', Fingerprint::of('Pancakes'), null)],
            $this->values()->of($recipe, 'da_DK'),
        );
    }

    /**
     * The entry renders in the default layout, which ignores the values of a disabled one
     *
     * @test
     */
    public function it_gives_nothing_of_a_disabled_layout(): void
    {
        $page = self::page([], new StoredLayout('promo', 1, ['tagline' => ['en_US' => 'Sale']]));

        self::assertSame(['entry.title', 'entry.slug'], array_map(static fn (TranslatableValue $value): string => $value->path, $this->values(['page.promo'])->of($page, 'da_DK')));
    }

    /**
     * @test
     */
    public function it_counts_the_untranslated_and_outdated_values_of_a_locale(): void
    {
        $page = self::page([
            self::heading(self::A, ['en_US' => 'Hello there', 'da_DK' => 'Hej'], ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]]),
            self::heading(self::B, ['en_US' => 'Welcome']),
            self::heading(self::C, ['en_US' => 'Shipping']),
        ]);

        self::assertEquals(new Completeness('da_DK', 4, 1), $this->values()->completeness($page, 'da_DK'));
        self::assertEquals(new Completeness('en_US', 0, 0), $this->values()->completeness($page, 'en_US'));
    }

    /**
     * @return list<TranslatableValue>
     */
    private function blockValues(EntryInterface $entry, string $localeCode): array
    {
        return array_values(array_filter($this->values()->of($entry, $localeCode), static fn (TranslatableValue $value): bool => str_starts_with($value->path, 'block.')));
    }

    /**
     * @param array<string, mixed> $text
     */
    private static function heading(string $key, array $text, mixed $translated = []): StoredBlock
    {
        $block = new StoredBlock($key, 'heading', 1, fields: ['text' => $text, 'level' => 'h2']);
        $block->translated = ['text' => $translated];

        return $block;
    }

    /**
     * A page with an English translation, with the blocks and layout
     *
     * @param list<StoredBlock> $blocks
     */
    private static function page(array $blocks, ?StoredLayout $layout = null): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');
        $page->setBlockTree(new StoredTree($layout, $blocks));

        return $page;
    }

    /**
     * @param list<string> $disabledLayouts
     */
    private function values(array $disabledLayouts = []): TranslatableValues
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('de_CH')->willReturn(['de_CH', 'fr_FR', 'de_DE', 'en_US']);
        $fallbackChain->localesFor('fr_FR')->willReturn(['fr_FR', 'de_DE', 'en_US']);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK', 'en_US']);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            RichTextHandler::class => static fn (): RichTextHandler => Links::richTextHandler(),
        ]));

        return new TranslatableValues(
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            new BlockTypeRegistry(['heading' => Heading::class, 'rich_text' => RichText::class, 'group' => Group::class], new FieldReader()),
            new LayoutRegistry(['page' => ['promo' => Promo::class]], new FieldReader(), disabled: $disabledLayouts),
            new FallbackValues($handlers, $fallbackChain->reveal()),
            $fallbackChain->reveal(),
        );
    }
}
