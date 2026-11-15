<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture\LandingTranslation;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\BlockTreeValidator;
use Setono\SyliusCMSPlugin\Validation\EntryValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Validation;

final class EntryValidatorTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_finds_the_entrys_constraint_violations_by_entry_field_and_the_trees_errors_in_every_locale(): void
    {
        $entry = new Landing();
        foreach (['en_US', 'da_DK'] as $locale) {
            $translation = $entry->getTranslation($locale);
            self::assertInstanceOf(LandingTranslation::class, $translation);
            $translation->teaser = 'a teaser that is far too long';
        }

        $entry->setBlockTree(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1)]));

        self::assertEquals(
            [
                new ValidationError(null, 'campaign', null, 'This value should not be blank.'),
                new ValidationError(null, 'teaser', 'da_DK', 'This value is too long. It should have 10 characters or less.'),
                new ValidationError(null, 'teaser', 'da_DK', 'This value is not valid.'),
                new ValidationError(null, 'teaser', 'en_US', 'This value is too long. It should have 10 characters or less.'),
                new ValidationError(null, 'teaser', 'en_US', 'This value is not valid.'),
                new ValidationError(self::KEY, 'text', 'da_DK', 'This field is required.'),
                new ValidationError(self::KEY, 'text', 'en_US', 'This field is required.'),
            ],
            $this->validator()->validate($entry),
        );
    }

    /**
     * @test
     */
    public function a_valid_entry_has_no_errors(): void
    {
        $entry = new Landing();
        $entry->campaign = 'Winter sale';
        $entry->setCurrentLocale('en_US');
        $entry->setTitle('Winter sale');
        $heading = new StoredBlock(self::KEY, 'heading', 1);
        $heading->setValue('text', 'en_US', 'Hello');
        $entry->setBlockTree(new StoredTree(null, [$heading]));

        self::assertSame([], $this->validator()->validate($entry));
    }

    private function validator(): EntryValidator
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK']);

        $translator = BlockTreeValidatorTest::translator();
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class], new FieldReader());
        $contentTypes = new ContentTypeRegistry([
            'landing' => ['model' => Landing::class, 'translation' => LandingTranslation::class, 'resource' => 'app.landing'],
        ]);

        return new EntryValidator(
            new BlockTreeValidator(
                $blockTypes,
                new NestingRules($blockTypes, $contentTypes),
                $handlers = new FieldHandlerLocator(new ServiceLocator([
                    TextHandler::class => static fn (): TextHandler => new TextHandler(),
                    ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
                ])),
                new FallbackValues($handlers, $fallbackChain->reveal()),
                $validator,
                $translator,
            ),
            $contentTypes,
            $validator,
        );
    }
}
