<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\PresetRow;
use Setono\SyliusCMSPlugin\Admin\PresetRows;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Layout\Page\Standard;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
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
use Setono\SyliusCMSPlugin\Tree\StoredValueFit;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * What the Presets screen lists: the starting points or the block presets, in the order they're provided, their texts
 * in the admin user's locale, flagged with what's wrong with their blocks, and the categories in use. See "Presets" in
 * docs/spec/admin.md
 */
final class PresetRowsTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lists_the_block_presets_or_the_starting_points_in_the_order_they_are_provided(): void
    {
        $rows = $this->rows([
            $hero = new Preset(new TranslationKey('app.preset.hero.name'), [self::heading()], new TranslationKey('app.preset_category.banners'), 'A heading', code: 'hero'),
            $landing = new Preset('Landing page', [], 'Pages', startingPoint: new StartingPoint('page', null), uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a'),
            $promo = new Preset('Promo', [new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'countdown', 1)], uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b'),
        ]);

        self::assertEquals([
            new PresetRow($hero, 'Hero', 'Banners', 'A heading', null, []),
            new PresetRow($promo, 'Promo', null, null, null, ['It has blocks of the type "countdown", which the code no longer has, so inserting it leaves them out.']),
        ], $rows->rows(false));
        self::assertEquals([new PresetRow($landing, 'Landing page', 'Pages', null, 'Page', [])], $rows->rows(true));
        self::assertSame(['hero', 'hero', false], [$rows->rows(false)[0]->id(), $hero->id(), $rows->rows(false)[1]->fromCode()]);
    }

    /**
     * @test
     */
    public function it_gives_the_categories_in_use_each_once_in_order(): void
    {
        $rows = $this->rows([
            new Preset('Hero', [], new TranslationKey('app.preset_category.banners'), code: 'hero'),
            new Preset('Promo', [], 'campaigns', uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a'),
            new Preset('Sale', [], 'Banners', uuid: '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b'),
            new Preset('Landing page', [], 'Pages', startingPoint: new StartingPoint('page', null), code: 'landing_page'),
            new Preset('Plain', [], code: 'plain'),
        ]);

        self::assertSame(['Banners', 'campaigns', 'Pages'], $rows->categories());
    }

    /**
     * @param list<Preset> $presets
     */
    private function rows(array $presets): PresetRows
    {
        $provider = $this->prophesize(PresetProviderInterface::class);
        $provider->all()->willReturn($presets);

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'app.preset.hero.name' => 'Hero',
            'app.preset_category.banners' => 'Banners',
            'setono_sylius_cms.content_type.page' => 'Page',
            'setono_sylius_cms.preset.issue.removed_block_type' => 'It has blocks of the type "%type%", which the code no longer has, so inserting it leaves them out.',
        ], 'en');

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class], new FieldReader());
        $contentTypes = new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
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

        return new PresetRows($provider->reveal(), $check, $contentTypes, $translator);
    }

    private static function heading(): StoredBlock
    {
        return new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'heading', 1, fields: ['level' => 'h2', 'text' => ['en_US' => 'Winter sale']]);
    }
}
