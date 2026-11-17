<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\LandingTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Testimonial;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\BlockTreeValidator;
use Setono\SyliusCMSPlugin\Validation\FieldValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;

final class BlockTreeValidatorTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const E = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e';

    /**
     * @test
     */
    public function it_finds_a_missing_required_field_at_its_block_key_field_and_locale(): void
    {
        $filled = self::heading(self::A, 'Hello');
        $empty = new StoredBlock(self::B, 'heading', 1);

        self::assertEquals(
            [new ValidationError(self::B, 'text', 'en_US', 'This field is required.')],
            $this->validator()->validate(new StoredTree(null, [$filled, $empty]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_a_required_translatable_field_missing_only_in_locales_whose_fallback_chain_finds_no_value(): void
    {
        $danishOnly = new StoredBlock(self::A, 'heading', 1);
        $danishOnly->setValue('text', 'da_DK', 'Hej');
        $englishOnly = self::heading(self::B, 'Hello');

        self::assertEquals(
            [new ValidationError(self::A, 'text', 'en_US', 'This field is required.')],
            $this->validator()->validate(new StoredTree(null, [$danishOnly, $englishOnly]), 'page', ['en_US', 'da_DK']),
        );
    }

    /**
     * @test
     */
    public function it_counts_an_empty_rich_text_document_and_a_value_that_is_not_a_locale_map_as_missing(): void
    {
        $emptyDocument = self::testimonial(self::A);
        $emptyDocument->setValue('bio', 'en_US', ['type' => 'doc', 'content' => [['type' => 'paragraph']]]);
        $notALocaleMap = self::testimonial(self::B);
        $notALocaleMap->fields['bio'] = 'Born in 1970';

        self::assertEquals(
            [
                new ValidationError(self::A, 'bio', 'en_US', 'This field is required.'),
                new ValidationError(self::B, 'bio', 'en_US', 'This field is required.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$emptyDocument, $notALocaleMap]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_constraint_violations_once_in_the_locale_of_the_value_that_breaks_them(): void
    {
        $block = self::testimonial(self::A);
        $block->setValue('quote', 'en_US', 'far too long for this quote');

        self::assertEquals(
            [
                new ValidationError(self::A, 'quote', 'en_US', 'This value is too long. It should have 20 characters or less.'),
                new ValidationError(self::A, 'quote', 'en_US', 'This value is not valid.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$block]), 'page', ['en_US', 'da_DK']),
        );
    }

    /**
     * @test
     */
    public function it_checks_constraints_on_a_missing_value_too_and_gives_a_shared_field_no_locale(): void
    {
        $block = self::testimonial(self::A);
        unset($block->fields['author']);

        self::assertEquals(
            [
                new ValidationError(self::A, 'author', null, 'This value should not be blank.'),
                new ValidationError(self::A, 'author', null, 'This value should not be null.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$block]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_runs_the_field_type_handlers_validation_before_the_constraints(): void
    {
        $block = self::heading(self::A, 'Hello');
        $block->setValue('level', null, 'h6');
        $notText = self::testimonial(self::B);
        $notText->setValue('quote', 'en_US', ['far too long for this quote, and not text']);

        self::assertEquals(
            [
                new ValidationError(self::A, 'level', null, 'The value you selected is not a valid choice.'),
                new ValidationError(self::B, 'quote', 'en_US', 'This value should be text.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$block, $notText]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_columns_with_too_few_or_too_many_columns_at_the_columns_block(): void
    {
        $one = new StoredBlock(self::A, 'columns', 1, children: [new StoredBlock(self::C, 'column', 1)]);
        $five = new StoredBlock(self::B, 'columns', 1, children: array_map(
            static fn (int $i): StoredBlock => new StoredBlock(sprintf('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e5%d', $i), 'column', 1),
            range(1, 5),
        ));

        self::assertEquals(
            [
                new ValidationError(self::A, null, null, 'Needs at least 2 Column blocks inside.'),
                new ValidationError(self::B, null, null, 'Can hold at most 4 Column blocks.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$one, $five]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_a_block_where_its_type_is_not_allowed_at_the_block(): void
    {
        $columnAtTheTop = new StoredBlock(self::A, 'column', 1);
        $headingInColumns = new StoredBlock(self::B, 'columns', 1, children: [
            new StoredBlock(self::C, 'column', 1),
            new StoredBlock(self::D, 'column', 1),
            self::heading(self::E, 'Hello'),
        ]);

        self::assertEquals(
            [
                new ValidationError(self::A, null, null, 'Column can\'t go here.'),
                new ValidationError(self::E, null, null, 'Heading can\'t go here.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$columnAtTheTop, $headingInColumns]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_validates_blocks_at_any_depth(): void
    {
        $columns = new StoredBlock(self::A, 'columns', 1, children: [
            new StoredBlock(self::B, 'column', 1, children: [new StoredBlock(self::C, 'heading', 1)]),
            new StoredBlock(self::D, 'column', 1),
        ]);

        self::assertEquals(
            [new ValidationError(self::C, 'text', 'en_US', 'This field is required.')],
            $this->validator()->validate(new StoredTree(null, [$columns]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_a_block_of_a_type_the_code_no_longer_has_leaves_what_is_inside_it_alone_and_goes_on(): void
    {
        $unknown = new StoredBlock(self::A, 'banner', 1, children: [new StoredBlock(self::B, 'heading', 1)]);

        self::assertEquals(
            [
                new ValidationError(self::A, null, null, 'Its block type "banner" no longer exists, so it isn\'t shown.'),
                new ValidationError(self::C, 'text', 'en_US', 'This field is required.'),
            ],
            $this->validator()->validate(new StoredTree(null, [$unknown, new StoredBlock(self::C, 'heading', 1)]), 'page', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_validates_the_top_level_against_the_content_type_and_reports_its_min_and_max_on_the_entry(): void
    {
        $validator = $this->validator();

        self::assertEquals(
            [new ValidationError(null, null, null, 'Needs at least one Heading block inside.')],
            $validator->validate(new StoredTree(null, []), 'landing', ['en_US']),
        );
        self::assertEquals(
            [new ValidationError(null, null, null, 'Can hold at most one Heading block.')],
            $validator->validate(new StoredTree(null, [self::heading(self::A, 'Hello'), self::heading(self::B, 'Hi')]), 'landing', ['en_US']),
        );
        self::assertSame([], $validator->validate(new StoredTree(null, [self::heading(self::A, 'Hello')]), 'landing', ['en_US']));
        // Only blocks of the type count towards its max
        self::assertEquals(
            [new ValidationError(self::B, null, null, 'Column can\'t go here.')],
            $validator->validate(new StoredTree(null, [self::heading(self::A, 'Hello'), new StoredBlock(self::B, 'column', 1)]), 'landing', ['en_US']),
        );
        self::assertEquals(
            [new ValidationError(self::A, null, null, 'Heading can\'t go here.')],
            $validator->validate(new StoredTree(null, [self::heading(self::A, 'Hello')]), 'recipe', ['en_US']),
        );
    }

    /**
     * @test
     */
    public function it_finds_no_errors_in_a_valid_tree(): void
    {
        $columns = new StoredBlock(self::A, 'columns', 1, children: [
            new StoredBlock(self::B, 'column', 1, children: [self::heading(self::C, 'Hello'), self::testimonial(self::E)]),
            new StoredBlock(self::D, 'column', 1),
        ]);

        self::assertSame([], $this->validator()->validate(new StoredTree(null, [$columns]), 'page', ['en_US', 'da_DK']));
    }

    /**
     * A testimonial with every field filled in en_US
     */
    private static function testimonial(string $key): StoredBlock
    {
        $block = new StoredBlock($key, 'testimonial', 1);
        $block->setValue('quote', 'en_US', 'Great shoes');
        $block->setValue('author', null, 'Jane');
        $block->setValue('bio', 'en_US', ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Runner']]]]]);

        return $block;
    }

    private static function heading(string $key, string $text): StoredBlock
    {
        $block = new StoredBlock($key, 'heading', 1);
        $block->setValue('text', 'en_US', $text);

        return $block;
    }

    /**
     * The plugin's own English messages, which the validator and the block type labels are translated with
     */
    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/messages.en.yaml', 'en');
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/validators.en.yaml', 'en', 'validators');

        return $translator;
    }

    private function validator(): BlockTreeValidator
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK', 'en_US']);

        $translator = self::translator();
        $blockTypes = new BlockTypeRegistry([
            'heading' => Heading::class,
            'columns' => Columns::class,
            'column' => Column::class,
            'testimonial' => Testimonial::class,
        ], new FieldReader());
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            RichTextHandler::class => static fn (): RichTextHandler => Links::richTextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
        ]));

        return new BlockTreeValidator(
            $blockTypes,
            new NestingRules($blockTypes, new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'landing' => ['model' => Landing::class, 'translation' => LandingTranslation::class, 'resource' => 'app.landing'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ])),
            new FieldValidator(
                $handlers,
                new FallbackValues($handlers, $fallbackChain->reveal()),
                Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator(),
                $translator,
            ),
            $translator,
        );
    }
}
