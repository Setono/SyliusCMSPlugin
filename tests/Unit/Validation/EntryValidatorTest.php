<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\EmbedCode;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Article;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\LandingTranslation;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\BlockTreeValidator;
use Setono\SyliusCMSPlugin\Validation\EmbedCodeValidator;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidator;
use Setono\SyliusCMSPlugin\Validation\FieldValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Validation;

final class EntryValidatorTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const EMBED_CODE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    private const EMBED_CODE_OFF = 'Embed code is switched off in this shop, so a new Embed code block can\'t be published. Remove it.';

    /** @var ObjectProphecy<EntryUrlValidatorInterface> */
    private ObjectProphecy $urlValidator;

    protected function setUp(): void
    {
        $this->urlValidator = $this->prophesize(EntryUrlValidatorInterface::class);
        $this->urlValidator->validate(Argument::any())->willReturn([]);
    }

    /**
     * @test
     */
    public function it_finds_the_entrys_errors_by_entry_field_and_the_trees_in_every_locale(): void
    {
        $entry = new Landing();
        foreach (['en_US', 'da_DK'] as $locale) {
            self::translation($entry, $locale)->teaser = 'a teaser that is far too long';
        }

        $entry->setBlockTree(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1)]));

        self::assertEquals(
            [
                new ValidationError(null, 'subtitle', null, 'This field is required.'),
                new ValidationError(null, 'headline', 'da_DK', 'This field is required.'),
                new ValidationError(null, 'headline', 'en_US', 'This field is required.'),
                new ValidationError(null, 'campaign', null, 'This value should not be blank.'),
                new ValidationError(null, 'teaser', 'da_DK', 'This value is too long. It should have 10 characters or less.'),
                new ValidationError(null, 'teaser', 'da_DK', 'This value is not valid.'),
                new ValidationError(null, 'teaser', 'en_US', 'This value is too long. It should have 10 characters or less.'),
                new ValidationError(null, 'teaser', 'en_US', 'This value is not valid.'),
                new ValidationError(self::KEY, 'text', 'da_DK', 'This field is required.'),
                new ValidationError(self::KEY, 'text', 'en_US', 'This field is required.'),
            ],
            $this->validator()->validate($entry, $entry),
        );
    }

    /**
     * @test
     */
    public function it_checks_a_shared_entry_fields_constraints_once(): void
    {
        $entry = self::validEntry();
        $entry->setSubtitle('A subtitle far too long');

        self::assertEquals(
            [new ValidationError(null, 'subtitle', null, 'This value is too long. It should have 10 characters or less.')],
            $this->validator()->validate($entry, $entry),
        );
    }

    /**
     * @test
     */
    public function it_validates_a_translatable_entry_field_in_each_locale_with_the_value_that_renders_there(): void
    {
        $entry = self::validEntry();
        self::translation($entry, 'en_US')->headline = 'A headline far too long';
        // Danish falls back to the English headline, and has a tone of its own
        self::translation($entry, 'da_DK')->tone = 'shouty';
        self::translation($entry, 'da_DK')->setTitle('Vintersalg');

        self::assertEquals(
            [
                new ValidationError(null, 'headline', 'en_US', 'This value is too long. It should have 10 characters or less.'),
                new ValidationError(null, 'tone', 'da_DK', 'The value you selected is not a valid choice.'),
            ],
            $this->validator()->validate($entry, $entry),
        );
    }

    /**
     * @test
     */
    public function it_validates_an_entry_of_a_content_type_without_translatable_fields(): void
    {
        $entry = new Recipe();
        $entry->setCurrentLocale('en_US');
        $entry->setTitle('Pancakes');

        self::assertSame([], $this->validator()->validate($entry, $entry));
    }

    /**
     * @test
     */
    public function it_finds_no_errors_in_a_valid_entry(): void
    {
        $entry = self::validEntry();

        self::assertSame([], $this->validator()->validate($entry, $entry));
    }

    /**
     * @test
     */
    public function it_checks_the_entrys_urls_after_its_fields_and_before_its_tree(): void
    {
        $entry = self::validEntry();
        $entry->setSubtitle('A subtitle far too long');
        $entry->setBlockTree(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1)]));
        $slugError = new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/landing/cart is taken by the route sylius_shop_cart_summary.');
        $this->urlValidator->validate($entry)->willReturn([$slugError]);

        self::assertEquals(
            [
                new ValidationError(null, 'subtitle', null, 'This value is too long. It should have 10 characters or less.'),
                $slugError,
                new ValidationError(self::KEY, 'text', 'en_US', 'This field is required.'),
            ],
            $this->validator()->validate($entry, $entry),
        );
    }

    /**
     * A translation without a title is no translation, such as one whose adding was undone in the builder
     *
     * @test
     */
    public function it_leaves_out_a_translation_without_a_title_when_another_has_one(): void
    {
        $entry = self::validEntry();
        self::translation($entry, 'da_DK')->tone = 'shouty';
        $entry->setBlockTree(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 42]])]));

        self::assertSame([], $this->validator()->validate($entry, $entry));
    }

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_that_is_not_in_the_entrys_published_tree_while_embed_code_is_off(): void
    {
        $entry = self::article(new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']]));
        $entry->publish(new \DateTimeImmutable());
        $workingCopy = clone $entry;
        $workingCopy->setBlockTree(new StoredTree(null, [...$entry->getBlockTree()->blocks, new StoredBlock(self::EMBED_CODE, 'embed_code', 1)]));

        self::assertEquals(
            [new ValidationError(self::EMBED_CODE, null, null, self::EMBED_CODE_OFF)],
            $this->validator()->validate($entry, $workingCopy),
        );
    }

    /**
     * @test
     */
    public function it_accepts_an_embed_code_block_the_entry_has_published_while_embed_code_is_off(): void
    {
        $entry = self::article(new StoredBlock(self::EMBED_CODE, 'embed_code', 1));
        $entry->publish(new \DateTimeImmutable());

        self::assertSame([], $this->validator()->validate($entry, clone $entry));
    }

    /**
     * A never-published entry is its own working copy: its rows hold its draft, and nothing is published
     *
     * @test
     */
    public function it_refuses_an_embed_code_block_of_an_entry_never_published_while_embed_code_is_off(): void
    {
        $entry = self::article(new StoredBlock(self::EMBED_CODE, 'embed_code', 1));

        self::assertEquals(
            [new ValidationError(self::EMBED_CODE, null, null, self::EMBED_CODE_OFF)],
            $this->validator()->validate($entry, $entry),
        );
    }

    private static function article(StoredBlock ...$blocks): Article
    {
        $entry = new Article();
        $entry->setCurrentLocale('en_US');
        $entry->setTitle('Embeds');
        $entry->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $entry;
    }

    private static function validEntry(): Landing
    {
        $entry = new Landing();
        $entry->campaign = 'Winter sale';
        $entry->setSubtitle('Sale');
        $entry->setCurrentLocale('en_US');
        $entry->setTitle('Winter sale');
        self::translation($entry, 'en_US')->headline = 'Hello';
        $heading = new StoredBlock(self::KEY, 'heading', 1);
        $heading->setValue('text', 'en_US', 'Hello');
        $entry->setBlockTree(new StoredTree(null, [$heading]));

        return $entry;
    }

    private static function translation(Landing $entry, string $locale): LandingTranslation
    {
        $translation = $entry->getTranslation($locale);
        self::assertInstanceOf(LandingTranslation::class, $translation);

        return $translation;
    }

    private function validator(): EntryValidator
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK', 'en_US']);

        $translator = BlockTreeValidatorTest::translator();
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'embed_code' => EmbedCode::class], new FieldReader(), ['embed_code']);
        $contentTypes = new ContentTypeRegistry([
            'landing' => ['model' => Landing::class, 'translation' => LandingTranslation::class, 'resource' => 'app.landing'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'article' => ['model' => Article::class, 'translation' => null, 'resource' => 'app.article'],
        ]);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
        ]));
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();
        $fieldValidator = new FieldValidator($handlers, new FallbackValues($handlers, $fallbackChain->reveal()), $validator, $translator);

        return new EntryValidator(
            new BlockTreeValidator($blockTypes, new NestingRules($blockTypes, $contentTypes), $fieldValidator, $translator),
            $fieldValidator,
            new FieldReader(),
            $contentTypes,
            $validator,
            $this->urlValidator->reveal(),
            new Availability(),
            new EmbedCodeValidator(false, $translator),
        );
    }
}
