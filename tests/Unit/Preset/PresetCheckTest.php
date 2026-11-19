<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\EmbedCode;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\CodeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Layout\Page\Standard;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetIssue;
use Setono\SyliusCMSPlugin\Preset\PresetIssueKind;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredValueFit;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

/**
 * What's wrong with a preset's blocks, which setono:cms:preset:validate refuses and the Presets tab marks the preset for.
 * See "Code presets" and "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class PresetCheckTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    private const COLUMNS = '0193a5be-0000-7000-8000-000000000002';

    private const CHILD = '0193a5be-0000-7000-8000-000000000003';

    private const OTHER_CHILD = '0193a5be-0000-7000-8000-000000000004';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $this->upgrader->upgradeTree(Argument::type('array'), Argument::type('string'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
    }

    /**
     * @test
     */
    public function it_finds_nothing_wrong_with_valid_blocks_whose_texts_are_locale_maps_or_translation_keys(): void
    {
        $preset = new Preset('Hero', [
            new StoredBlock(self::KEY, 'heading', 1, fields: ['level' => 'h3', 'text' => ['$trans' => 'app.hero.heading']]),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [self::block('column', [self::block('rich_text', fields: ['content' => ['$trans' => 'app.hero.text']])]), self::block('column')]),
            self::block('heading', fields: ['text' => ['en_US' => 'Winter sale']]),
        ], code: 'hero');

        self::assertSame([], $this->check()->check($preset));
    }

    /**
     * A preset is one or more saved blocks (GLOSSARY.md)
     *
     * @test
     */
    public function it_finds_a_preset_without_blocks(): void
    {
        self::assertEquals([
            new PresetIssue(PresetIssueKind::NoBlocks, null, new TranslatableMessage('setono_sylius_cms.preset.issue.no_blocks')),
        ], $this->check()->check(new Preset('Empty', [], code: 'empty')));
        self::assertTrue(PresetIssueKind::NoBlocks->failsValidation());
        self::assertFalse(PresetIssueKind::NoBlocks->disablesStartingPoint());
    }

    /**
     * @test
     */
    public function it_finds_blocks_of_removed_and_disabled_types(): void
    {
        $preset = new Preset('Hero', [new StoredBlock(self::KEY, 'gallery', 1, children: [self::block('nope')]), new StoredBlock(self::COLUMNS, 'embed_code', 1, fields: ['code' => '<p>Hi</p>'])], code: 'hero');

        self::assertEquals([
            new PresetIssue(PresetIssueKind::RemovedBlockType, self::KEY, new TranslatableMessage('setono_sylius_cms.preset.issue.removed_block_type', ['%type%' => 'gallery'])),
            new PresetIssue(PresetIssueKind::DisabledBlockType, self::COLUMNS, new TranslatableMessage('setono_sylius_cms.preset.issue.disabled_block_type', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.embed_code')])),
        ], $this->check()->check($preset));
        self::assertTrue(PresetIssueKind::RemovedBlockType->failsValidation());
        self::assertFalse(PresetIssueKind::DisabledBlockType->failsValidation());
    }

    /**
     * @test
     */
    public function it_finds_blocks_saved_with_a_newer_version_than_the_code_s(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::BlockType, 'heading', 2, 1)]));

        self::assertEquals([
            new PresetIssue(PresetIssueKind::NewerVersion, null, new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'heading', '%version%' => 2, '%code_version%' => 1])),
        ], $this->check()->check(new Preset('Hero', [new StoredBlock(self::KEY, 'heading', 2)], code: 'hero')));
    }

    /**
     * A starting point is upgraded as a tree of its content type, its layout with its blocks, and what's newer in either
     * is all that's found, as nothing else in it can be read as the code would
     *
     * @test
     */
    public function it_finds_only_what_a_starting_point_holds_with_a_newer_version_in_its_layout_or_blocks(): void
    {
        $this->upgrader->upgradeTree(Argument::type('array'), 'page')->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::Layout, 'page:standard', 3, 1)]));
        $preset = new Preset('Landing', [new StoredBlock(self::KEY, 'gallery', 1)], startingPoint: new StartingPoint('page', new StoredLayout('standard', 3, ['showTitle' => 'yes'])), code: 'landing');

        self::assertEquals([
            new PresetIssue(PresetIssueKind::NewerVersion, null, new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'page:standard', '%version%' => 3, '%code_version%' => 1])),
        ], $this->check()->check($preset));
    }

    /**
     * @test
     */
    public function it_finds_children_their_block_does_not_allow_and_too_few_or_too_many_of_a_type(): void
    {
        $preset = new Preset('Hero', [
            new StoredBlock(self::KEY, 'columns', 1, children: [self::block('column'), new StoredBlock(self::CHILD, 'heading', 1)]),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: array_map(static fn (): StoredBlock => self::block('column'), range(1, 5))),
        ], code: 'hero');

        self::assertEquals([
            new PresetIssue(PresetIssueKind::Nesting, self::CHILD, new TranslatableMessage('setono_sylius_cms.preset.issue.not_allowed', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.heading'), '%parent%' => new TranslatableMessage('setono_sylius_cms.block_type.columns')])),
            new PresetIssue(PresetIssueKind::Nesting, self::KEY, new TranslatableMessage('setono_sylius_cms.preset.issue.too_few', ['%count%' => 2, '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column'), '%parent%' => new TranslatableMessage('setono_sylius_cms.block_type.columns')])),
            new PresetIssue(PresetIssueKind::Nesting, self::COLUMNS, new TranslatableMessage('setono_sylius_cms.preset.issue.too_many', ['%count%' => 4, '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column'), '%parent%' => new TranslatableMessage('setono_sylius_cms.block_type.columns')])),
        ], $this->check()->check($preset));
    }

    /**
     * @test
     */
    public function it_allows_any_block_at_the_top_level_of_a_block_preset_and_checks_a_starting_point_s_against_its_content_type(): void
    {
        $columns = [new StoredBlock(self::CHILD, 'column', 1), new StoredBlock(self::OTHER_CHILD, 'column', 1)];

        self::assertSame([], $this->check()->check(new Preset('Pair', $columns, code: 'pair')));
        self::assertEquals([
            new PresetIssue(PresetIssueKind::Nesting, self::CHILD, new TranslatableMessage('setono_sylius_cms.preset.issue.not_allowed', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column'), '%parent%' => new TranslatableMessage('setono_sylius_cms.content_type.page')])),
            new PresetIssue(PresetIssueKind::Nesting, self::OTHER_CHILD, new TranslatableMessage('setono_sylius_cms.preset.issue.not_allowed', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column'), '%parent%' => new TranslatableMessage('setono_sylius_cms.content_type.page')])),
        ], $this->check()->check(new Preset('Pair', $columns, startingPoint: new StartingPoint('page', new StoredLayout('standard', 1)), code: 'pair')));
    }

    /**
     * @test
     */
    public function it_finds_values_that_do_not_fit_their_field_types_including_a_translation_key_in_a_shared_field(): void
    {
        $preset = new Preset('Hero', [
            new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => 'Not by locale', 'level' => 'h9']),
            new StoredBlock(self::COLUMNS, 'embed_code', 1, fields: ['code' => ['$trans' => 'app.embed']]),
        ], code: 'hero');

        $issues = array_values(array_filter($this->check()->check($preset), static fn (PresetIssue $issue): bool => PresetIssueKind::Misfit === $issue->kind));

        self::assertEquals([
            new PresetIssue(PresetIssueKind::Misfit, self::KEY, new TranslatableMessage('setono_sylius_cms.problem.misfit_value', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.heading.text'), '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.heading')])),
            new PresetIssue(PresetIssueKind::Misfit, self::KEY, new TranslatableMessage('setono_sylius_cms.problem.misfit_value', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.heading.level'), '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.heading')])),
            new PresetIssue(PresetIssueKind::Misfit, self::COLUMNS, new TranslatableMessage('setono_sylius_cms.problem.misfit_value', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.embed_code.code'), '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.embed_code')])),
        ], $issues);
    }

    /**
     * @test
     */
    public function it_finds_a_starting_point_whose_content_type_or_layout_the_code_does_not_have_and_layout_values_that_do_not_fit(): void
    {
        $check = $this->check();

        self::assertEquals([
            new PresetIssue(PresetIssueKind::StartingPoint, null, new TranslatableMessage('setono_sylius_cms.preset.issue.unknown_content_type', ['%content_type%' => 'blog_post'])),
        ], $check->check(new Preset('Blog', [], startingPoint: new StartingPoint('blog_post', null), code: 'blog')));
        self::assertEquals([
            new PresetIssue(PresetIssueKind::StartingPoint, null, new TranslatableMessage('setono_sylius_cms.preset.issue.unknown_layout', ['%layout%' => 'wide', '%content_type%' => new TranslatableMessage('setono_sylius_cms.content_type.page')])),
        ], $check->check(new Preset('Landing', [], startingPoint: new StartingPoint('page', new StoredLayout('wide', 1)), code: 'landing')));
        self::assertEquals([
            new PresetIssue(PresetIssueKind::Misfit, null, new TranslatableMessage('setono_sylius_cms.problem.misfit_layout_value', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.standard.show_title')])),
        ], $check->check(new Preset('Landing', [], startingPoint: new StartingPoint('page', new StoredLayout('standard', 1, ['showTitle' => 'yes'])), code: 'landing')));
    }

    private function check(): PresetCheck
    {
        $blockTypes = new BlockTypeRegistry([
            'heading' => Heading::class,
            'rich_text' => RichText::class,
            'columns' => Columns::class,
            'column' => Column::class,
            'embed_code' => EmbedCode::class,
        ], new FieldReader(), ['embed_code']);
        $contentTypes = new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            CodeHandler::class => static fn (): CodeHandler => new CodeHandler(),
            RichTextHandler::class => Links::richTextHandler(...),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new Translator('en')),
        ]));

        return new PresetCheck(
            $blockTypes,
            $contentTypes,
            new LayoutRegistry(['page' => ['standard' => Standard::class]], new FieldReader()),
            new NestingRules($blockTypes, $contentTypes),
            new PresetUpgrader($this->upgrader->reveal()),
            new TranslationKeyValues(new RichTextSchema()),
            new StoredValueFit(new FallbackValues($handlers, $this->prophesize(FallbackChainInterface::class)->reveal())),
        );
    }

    /**
     * @param list<StoredBlock> $children
     * @param array<string, mixed> $fields
     */
    private static function block(string $type, array $children = [], array $fields = []): StoredBlock
    {
        return new StoredBlock(sprintf('0193a5be-0000-7000-8000-%012d', random_int(3, 999999)), $type, 1, fields: $fields, children: $children);
    }
}
