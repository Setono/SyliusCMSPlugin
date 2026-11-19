<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Image;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Block\Video;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Layout\Page\Standard;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportReader;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSourceInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;

/**
 * Step 1 of an import: it reads the export's files, runs the upgraders on its entries and editor presets, and finds
 * what the code here doesn't know, which refuses the import. See "What an import does" and "Code the target must have"
 * in docs/spec/transfer.md
 */
final class ExportReaderTest extends TestCase
{
    use ProphecyTrait;

    private const FIXTURE = __DIR__ . '/../../../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    /**
     * @test
     */
    public function it_reads_the_entries_presets_and_media_of_the_committed_export_with_what_it_picked(): void
    {
        $export = $this->reader()->read(new DirectorySource(self::FIXTURE));

        self::assertSame([], $export->refusals);
        self::assertSame([self::PAGE, self::SNIPPET, self::INNER_SNIPPET], array_keys($export->entries));
        self::assertTrue($export->entries[self::PAGE]->picked);
        self::assertFalse($export->entries[self::SNIPPET]->picked);
        self::assertSame('Autumn sale', $export->entries[self::PAGE]->label());
        self::assertSame('page', $export->entries[self::PAGE]->contentType->code);
        self::assertInstanceOf(Page::class, $export->entries[self::PAGE]->content);
        self::assertSame(self::PAGE, $export->entries[self::PAGE]->content->getUuid());
        self::assertSame('2026-11-01 08:30:00', $export->entries[self::PAGE]->content->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame(['0193a5be-0000-7000-8000-000000000005', '0193a5be-0000-7000-8000-000000000006'], $export->entries[self::PAGE]->content->getBlockTree()->keys());
        self::assertCount(1, $export->entries[self::SNIPPET]->exported->placements);

        self::assertSame([self::PRESET], array_keys($export->presets));
        self::assertTrue($export->presets[self::PRESET]->picked);
        self::assertSame('Autumn hero', $export->presets[self::PRESET]->label());

        self::assertCount(2, $export->mediaFolders);
        self::assertCount(3, $export->mediaItems);
    }

    /**
     * @test
     */
    public function it_refuses_a_content_type_a_layout_and_block_types_the_code_does_not_have(): void
    {
        $export = $this->reader(blockTypes: ['heading' => Heading::class], layouts: [])->read(new DirectorySource(self::FIXTURE));

        self::assertSame([
            [self::PAGE, 'setono_sylius_cms.import.unknown_layout', ['%title%' => 'Autumn sale', '%layout%' => 'standard']],
            [self::PAGE, 'setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn sale', '%type%' => 'snippet']],
            [self::SNIPPET, 'setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn banner', '%type%' => 'image']],
            [self::SNIPPET, 'setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn banner', '%type%' => 'video']],
            [self::SNIPPET, 'setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn banner', '%type%' => 'snippet']],
            [self::PRESET, 'setono_sylius_cms.import.unknown_block_type', ['%title%' => 'Autumn hero', '%type%' => 'image']],
        ], self::refusals($export->refusals));

        $export = $this->reader(contentTypes: ['snippet'])->read(new DirectorySource(self::FIXTURE));

        self::assertSame([[self::PAGE, 'setono_sylius_cms.import.unknown_content_type', ['%title%' => 'Autumn sale', '%content_type%' => 'page']]], self::refusals($export->refusals));
        self::assertArrayNotHasKey(self::PAGE, $export->entries);
    }

    /**
     * @test
     */
    public function it_refuses_what_was_saved_with_a_newer_version_than_the_codes(): void
    {
        $newer = new NewerVersion(Kind::BlockType, 'heading', 3, 1);

        $export = $this->reader(newer: $newer)->read(new DirectorySource(self::FIXTURE));

        self::assertSame([
            [self::PAGE, 'setono_sylius_cms.import.newer_version', ['%title%' => 'Autumn sale', '%reason%' => (string) $newer]],
            [self::SNIPPET, 'setono_sylius_cms.import.newer_version', ['%title%' => 'Autumn banner', '%reason%' => (string) $newer]],
            [self::INNER_SNIPPET, 'setono_sylius_cms.import.newer_version', ['%title%' => 'Newsletter signup', '%reason%' => (string) $newer]],
            [self::PRESET, 'setono_sylius_cms.import.newer_version', ['%title%' => 'Autumn hero', '%reason%' => (string) $newer]],
        ], self::refusals($export->refusals));
        self::assertSame([], $export->entries);
    }

    /**
     * @test
     */
    public function it_cannot_read_an_entrys_file_that_holds_another_entry(): void
    {
        $source = $this->prophesize(ExportSourceInterface::class);
        $source->read('manifest.json')->willReturn((string) file_get_contents(self::FIXTURE . '/manifest.json'));
        $source->files('entries')->willReturn([self::SNIPPET . '.json']);
        $source->read('entries/' . self::SNIPPET . '.json')->willReturn((string) file_get_contents(self::FIXTURE . '/entries/' . self::PAGE . '.json'));

        $this->expectException(InvalidExportException::class);
        $this->reader()->read($source->reveal());
    }

    /**
     * @param array<string, class-string> $blockTypes
     * @param array<string, array<string, class-string>> $layouts
     * @param list<string> $contentTypes
     */
    private function reader(
        array $blockTypes = ['heading' => Heading::class, 'image' => Image::class, 'video' => Video::class, 'snippet' => SnippetBlock::class],
        array $layouts = ['page' => ['standard' => Standard::class]],
        array $contentTypes = ['page', 'snippet'],
        ?NewerVersion $newer = null,
    ): ExportReader {
        $resources = [
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        ];
        $registry = new ContentTypeRegistry(array_intersect_key($resources, array_flip($contentTypes)));

        // The export is the code's versions already, unless the code is older than it
        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgraded = static fn (array $args): Upgraded => new Upgraded((array) $args[0], null === $newer ? [] : [$newer]);
        $upgrader->upgradeEntry(Argument::type('array'))->will($upgraded);
        $upgrader->upgradeBlocks(Argument::type('array'))->will($upgraded);

        return new ExportReader(
            $registry,
            new LayoutRegistry($layouts, new FieldReader()),
            new BlockTypeRegistry($blockTypes, new FieldReader()),
            $upgrader->reveal(),
            new EntryDenormalizer($registry),
        );
    }

    /**
     * @param list<ImportRefusal> $refusals
     *
     * @return list<array{string|null, string, array<array-key, mixed>}>
     */
    private static function refusals(array $refusals): array
    {
        return array_map(static function (ImportRefusal $refusal): array {
            self::assertInstanceOf(\Symfony\Component\Translation\TranslatableMessage::class, $refusal->reason);

            return [$refusal->uuid, $refusal->reason->getMessage(), $refusal->reason->getParameters()];
        }, $refusals);
    }
}
