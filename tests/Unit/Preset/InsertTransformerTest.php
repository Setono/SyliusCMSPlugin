<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Button;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\EmbedCode;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Image;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Preset\InsertTransformer;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyResolverInterface;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The blocks a preset inserts: upgraded, each with a new key, without the values of locales the entry doesn't have,
 * with translation keys resolved for the entry's locales, and without the blocks of removed or disabled types. See
 * "What inserting copies" and "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class InsertTransformerTest extends TestCase
{
    use ProphecyTrait;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const COLUMNS = '0193a5be-0000-7000-8000-000000000002';

    private const COLUMN = '0193a5be-0000-7000-8000-000000000003';

    private const IMAGE = '0193a5be-0000-7000-8000-000000000004';

    private const MEDIA = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    /** @var ObjectProphecy<TranslationKeyResolverInterface> */
    private ObjectProphecy $translationKeys;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));
        $this->translationKeys = $this->prophesize(TranslationKeyResolverInterface::class);
        $this->translationKeys->resolve(Argument::cetera())->willReturn(null);
    }

    /**
     * @test
     */
    public function it_copies_every_block_with_a_new_key_keeping_everything_else(): void
    {
        $heading = new StoredBlock(self::HEADING, 'heading', 1, null, ['level' => 'h3', 'text' => ['en_US' => 'Winter sale']], conditions: [['kind' => 'customer_group', 'negate' => true, 'params' => ['groups' => ['wholesale']]]]);
        $image = new StoredBlock(self::IMAGE, 'image', 1, fields: ['image' => self::MEDIA]);
        $columns = new StoredBlock(self::COLUMNS, 'columns', 1, children: [new StoredBlock(self::COLUMN, 'column', 1, children: [$heading, $image])]);

        $inserted = $this->transformer()->transform(new Preset('Hero', [$columns], code: 'hero'), ['en_US']);

        self::assertFalse($inserted->refused);
        self::assertSame([], $inserted->notices);
        self::assertCount(1, $inserted->blocks);
        $keys = $inserted->blocks[0]->keys();
        self::assertCount(4, array_unique($keys));
        foreach ($keys as $key) {
            self::assertMatchesRegularExpression(self::UUID_PATTERN, $key);
            self::assertNotContains($key, [self::HEADING, self::COLUMNS, self::COLUMN, self::IMAGE]);
        }

        $copiedHeading = $inserted->blocks[0]->children[0]->children[0];
        self::assertSame(['heading', 1, null], [$copiedHeading->type, $copiedHeading->version, $copiedHeading->style]);
        self::assertSame(['level' => 'h3', 'text' => ['en_US' => 'Winter sale']], $copiedHeading->fields);
        self::assertSame($heading->conditions, $copiedHeading->conditions);
        // A media reference is kept
        self::assertSame(['image' => self::MEDIA], $inserted->blocks[0]->children[0]->children[1]->fields);
        // The preset itself is left as it is
        self::assertSame(self::HEADING, $heading->key);
    }

    /**
     * @test
     */
    public function it_gives_the_blocks_new_keys_each_time(): void
    {
        $preset = new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 1)], code: 'hero');

        self::assertNotSame($this->transformer()->transform($preset, ['en_US'])->blocks[0]->key, $this->transformer()->transform($preset, ['en_US'])->blocks[0]->key);
    }

    /**
     * @test
     */
    public function it_drops_the_values_and_translation_state_of_locales_the_entry_does_not_have(): void
    {
        $state = ['from' => 'en_US', 'fingerprint' => 'abc'];
        $heading = new StoredBlock(self::HEADING, 'heading', 1, null, ['text' => ['da_DK' => 'Vinterudsalg', 'de_DE' => 'Winterschlussverkauf', 'en_US' => 'Winter sale']], ['text' => ['da_DK' => $state, 'de_DE' => $state]]);
        $onlyGerman = new StoredBlock(self::IMAGE, 'image', 1, null, ['caption' => ['de_DE' => 'Bild'], 'image' => self::MEDIA, 'link' => ['target' => 'url', 'ref' => ['de_DE' => '/de', 'en_US' => '/en'], 'anchor' => null, 'label' => ['de_DE' => 'Mehr', 'en_US' => 'More']]]);

        $blocks = $this->transformer()->transform(new Preset('Hero', [$heading, $onlyGerman], code: 'hero'), ['en_US', 'da_DK'])->blocks;

        self::assertSame(['text' => ['da_DK' => 'Vinterudsalg', 'en_US' => 'Winter sale']], $blocks[0]->fields);
        self::assertSame(['text' => ['da_DK' => $state]], $blocks[0]->translated);
        self::assertEquals(['image' => self::MEDIA, 'link' => ['target' => 'url', 'ref' => ['en_US' => '/en'], 'anchor' => null, 'label' => ['en_US' => 'More']]], $blocks[1]->fields);
    }

    /**
     * @test
     */
    public function it_resolves_translation_keys_in_each_locale_of_the_entry_that_has_a_translation_leaving_the_others_empty(): void
    {
        $this->translationKeys->resolve(new TranslationKey('app.hero.heading'), 'en_US')->willReturn('Winter sale');
        $this->translationKeys->resolve(new TranslationKey('app.hero.heading'), 'da_DK')->willReturn('Vinterudsalg');
        $this->translationKeys->resolve(new TranslationKey('app.button', 'app'), 'en_US')->willReturn('Shop now');
        $heading = new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['$trans' => 'app.hero.heading']]);
        $button = new StoredBlock(self::COLUMN, 'button', 1, fields: ['link' => ['target' => 'url', 'ref' => '/sale', 'anchor' => null, 'label' => ['$trans' => 'app.button', '$domain' => 'app']]]);
        $untranslated = new StoredBlock(self::IMAGE, 'heading', 1, fields: ['text' => ['$trans' => 'app.missing']]);

        $blocks = $this->transformer()->transform(new Preset('Hero', [$heading, $button, $untranslated], code: 'hero'), ['en_US', 'da_DK', 'de_DE'])->blocks;

        self::assertSame(['text' => ['en_US' => 'Winter sale', 'da_DK' => 'Vinterudsalg']], $blocks[0]->fields);
        self::assertEquals(['link' => ['target' => 'url', 'ref' => '/sale', 'anchor' => null, 'label' => ['en_US' => 'Shop now']]], $blocks[1]->fields);
        // A key no locale has a translation of leaves the field empty
        self::assertSame([], $blocks[2]->fields);
    }

    /**
     * @test
     */
    public function it_reads_the_translation_of_a_translation_key_in_a_rich_text_field_as_html(): void
    {
        $this->translationKeys->resolve(new TranslationKey('app.hero.text'), 'en_US')->willReturn('<p>Up to <strong>50%</strong> off.</p>');
        $richText = new StoredBlock(self::HEADING, 'rich_text', 1, fields: ['content' => ['$trans' => 'app.hero.text']]);

        $blocks = $this->transformer()->transform(new Preset('Hero', [$richText], code: 'hero'), ['en_US'])->blocks;

        self::assertSame(['content' => ['en_US' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Up to '],
            ['type' => 'text', 'text' => '50%', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' off.'],
        ]]]]]], $blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_runs_the_upgraders_on_the_blocks_before_copying_them(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static function (array $arguments): Upgraded {
            /** @var list<array<string, mixed>> $blocks */
            $blocks = $arguments[0];
            $blocks[0]['version'] = 2;
            $blocks[0]['fields'] = ['text' => ['en_US' => 'Upgraded']];

            return new Upgraded($blocks);
        });

        $blocks = $this->transformer()->transform(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 1, fields: ['title' => ['en_US' => 'Old']])], code: 'hero'), ['en_US'])->blocks;

        self::assertSame(2, $blocks[0]->version);
        self::assertSame(['text' => ['en_US' => 'Upgraded']], $blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_refuses_a_preset_with_a_block_saved_with_a_newer_version_than_the_code_s(): void
    {
        $this->upgrader->upgradeBlocks(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0], [new NewerVersion(Kind::BlockType, 'heading', 3, 1)]));

        $inserted = $this->transformer()->transform(new Preset('Hero', [new StoredBlock(self::HEADING, 'heading', 3)], code: 'hero'), ['en_US']);

        self::assertTrue($inserted->refused);
        self::assertSame([], $inserted->blocks);
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.newer_version', ['%type%' => 'heading', '%version%' => 3, '%code_version%' => 1])], $inserted->notices);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_blocks_of_removed_and_disabled_types_with_what_is_inside_them_and_a_notice_for_each_type(): void
    {
        $preset = new Preset('Hero', [
            new StoredBlock(self::HEADING, 'gallery', 1, children: [new StoredBlock(self::COLUMN, 'heading', 1)]),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [new StoredBlock(self::IMAGE, 'embed_code', 1, fields: ['code' => '<script></script>'])]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'gallery', 1),
        ], code: 'hero');

        $inserted = $this->transformer()->transform($preset, ['en_US']);

        self::assertFalse($inserted->refused);
        self::assertSame(['columns'], array_map(static fn (StoredBlock $block): string => $block->type, $inserted->blocks));
        self::assertSame([], $inserted->blocks[0]->children);
        self::assertEquals([
            new TranslatableMessage('setono_sylius_cms.preset.removed_block_type', ['%type%' => 'gallery']),
            new TranslatableMessage('setono_sylius_cms.preset.disabled_block_type', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.embed_code')]),
        ], $inserted->notices);
    }

    /**
     * @test
     */
    public function it_writes_the_copies_in_the_stored_block_format(): void
    {
        $heading = new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['$trans' => 'app.missing']]);

        $copy = $this->transformer()->transform(new Preset('Hero', [$heading], code: 'hero'), ['en_US'])->blocks[0];

        self::assertSame('{"key":"' . $copy->key . '","type":"heading","version":1,"style":null,"fields":{},"translated":{},"conditions":[],"children":[]}', CanonicalJson::encode((new StoredTreeSerializer())->normalizeBlock($copy)));
    }

    private function transformer(): InsertTransformer
    {
        return new InsertTransformer(
            $this->upgrader->reveal(),
            new BlockTypeRegistry([
                'heading' => Heading::class,
                'rich_text' => RichText::class,
                'button' => Button::class,
                'image' => Image::class,
                'columns' => Columns::class,
                'column' => Column::class,
                'embed_code' => EmbedCode::class,
            ], new FieldReader(), ['embed_code']),
            $this->translationKeys->reveal(),
            new TranslationKeyValues(new RichTextSchema()),
        );
    }
}
