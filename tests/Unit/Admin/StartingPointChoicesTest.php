<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\StartingPointChoice;
use Setono\SyliusCMSPlugin\Admin\StartingPointChoices;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Layout\Page\Standard;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredValueFit;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * The starting points the create dialog offers for a content type: from code and from editors, with their texts in
 * the admin user's locale and their thumbnails, the default code preset marked, and those with blocks of removed types
 * disabled, saying why. See "Starting points" in docs/spec/layouts-and-presets.md and "Create dialog" in
 * docs/spec/admin.md
 */
final class StartingPointChoicesTest extends TestCase
{
    use ProphecyTrait;

    private const EDITOR_PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    /**
     * @test
     */
    public function it_offers_the_content_types_starting_points_from_code_and_from_editors_in_the_order_they_are_provided(): void
    {
        $landing = new Preset(new TranslationKey('app.preset.landing.name'), [self::heading()], 'Pages', new TranslationKey('app.preset.landing.description'), new StartingPoint('page', new StoredLayout('standard', 1)), 'landing', '/build/landing.svg');
        $saved = new Preset('Our campaign page', [self::heading()], startingPoint: new StartingPoint('page', null), uuid: self::EDITOR_PRESET);

        $choices = $this->choices([
            new Preset('Hero', [self::heading()], code: 'hero'),
            $landing,
            new Preset('Footer', [self::heading()], startingPoint: new StartingPoint(SnippetInterface::CONTENT_TYPE, null), code: 'footer'),
            $saved,
        ])->of('page');

        self::assertEquals([
            new StartingPointChoice($landing, 'Landing page', 'A banner and some text', '/build/landing.svg', false, []),
            new StartingPointChoice($saved, 'Our campaign page', null, null, false, []),
        ], $choices);
        self::assertSame(['landing', self::EDITOR_PRESET], array_map(static fn (StartingPointChoice $choice): string => $choice->id(), $choices));
        self::assertFalse($choices[0]->disabled);
    }

    /**
     * @test
     */
    public function it_marks_the_code_preset_that_is_the_default_but_never_an_editor_preset(): void
    {
        $choices = $this->choices([
            new Preset('Landing page', [], startingPoint: new StartingPoint('page', null, true), code: 'landing'),
            new Preset('Saved', [], startingPoint: new StartingPoint('page', null, true), uuid: self::EDITOR_PRESET),
        ])->of('page');

        self::assertSame([true, false], array_map(static fn (StartingPointChoice $choice): bool => $choice->default, $choices));
    }

    /**
     * @test
     */
    public function it_disables_a_starting_point_with_blocks_of_types_the_code_no_longer_has_and_says_why(): void
    {
        $choices = $this->choices([
            new Preset('Countdown page', [self::heading(), new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'countdown', 1)], startingPoint: new StartingPoint('page', null), code: 'countdown_page'),
            new Preset('Retired layout', [self::heading()], startingPoint: new StartingPoint('page', new StoredLayout('retired', 1)), code: 'retired_layout'),
        ])->of('page');

        self::assertTrue($choices[0]->disabled);
        self::assertSame(['It has blocks of the type "countdown", which the code no longer has.'], $choices[0]->issues);
        // A layout the code no longer has is replaced by the default one, so the starting point is offered, saying so
        self::assertFalse($choices[1]->disabled);
        self::assertSame(['Its layout "retired" isn\'t one of Page\'s.'], $choices[1]->issues);
    }

    /**
     * @param list<Preset> $presets
     */
    private function choices(array $presets): StartingPointChoices
    {
        $provider = $this->prophesize(PresetProviderInterface::class);
        $provider->all()->willReturn($presets);

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'app.preset.landing.name' => 'Landing page',
            'app.preset.landing.description' => 'A banner and some text',
            'setono_sylius_cms.content_type.page' => 'Page',
            'setono_sylius_cms.preset.issue.removed_block_type' => 'It has blocks of the type "%type%", which the code no longer has.',
            'setono_sylius_cms.preset.issue.unknown_layout' => 'Its layout "%layout%" isn\'t one of %content_type%\'s.',
        ], 'en');

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $upgrader->upgradeTree(Argument::type('array'), Argument::type('string'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class], new FieldReader());
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
        ]);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
        ]));
        $check = new PresetCheck(
            $blockTypes,
            $contentTypes,
            new LayoutRegistry(['page' => ['standard' => Standard::class]], new FieldReader()),
            new NestingRules($blockTypes, $contentTypes),
            $upgrader->reveal(),
            new TranslationKeyValues(new RichTextSchema()),
            new StoredValueFit(new FallbackValues($handlers, $this->prophesize(FallbackChainInterface::class)->reveal())),
        );

        return new StartingPointChoices($provider->reveal(), $check, $translator);
    }

    private static function heading(): StoredBlock
    {
        return new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1, fields: ['level' => 'h2', 'text' => ['en_US' => 'Winter sale']]);
    }
}
