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
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\MediaFileCheckerInterface;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportReader;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSourceInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportedFileChecker;
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
    use TemporaryDirectory;

    private const FIXTURE = __DIR__ . '/../../../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /** A video, whose poster is POSTER */
    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    private const POSTER = '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62';

    /** A UUID the export has no record of */
    private const OTHER = '0193a5be-7fff-7fff-8fff-000000000000';

    /**
     * @test
     */
    public function it_reads_the_entries_presets_and_media_of_the_committed_export_with_what_it_picked(): void
    {
        $export = $this->reader()->read(new DirectorySource(self::FIXTURE));

        self::assertSame([], $export->refusals);
        self::assertSame([self::PAGE, self::SNIPPET, self::INNER_SNIPPET], array_keys($export->entries));
        self::assertTrue($export->entries[self::PAGE]->isPicked());
        self::assertFalse($export->entries[self::SNIPPET]->isPicked());
        self::assertSame('Autumn sale', $export->entries[self::PAGE]->label());
        self::assertSame('page', $export->entries[self::PAGE]->contentType->code);
        self::assertInstanceOf(Page::class, $export->entries[self::PAGE]->content);
        self::assertSame(self::PAGE, $export->entries[self::PAGE]->content->getUuid());
        self::assertSame('2026-11-01 08:30:00', $export->entries[self::PAGE]->content->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame(['0193a5be-0000-7000-8000-000000000005', '0193a5be-0000-7000-8000-000000000006'], $export->entries[self::PAGE]->content->getBlockTree()->keys());
        self::assertCount(1, $export->entries[self::SNIPPET]->exported->placements);

        self::assertSame([self::PRESET], array_keys($export->presets));
        self::assertTrue($export->presets[self::PRESET]->isPicked());
        self::assertSame('Autumn hero', $export->presets[self::PRESET]->label());

        self::assertCount(2, $export->mediaFolders);
        self::assertSame([self::BANNER, self::VIDEO, self::POSTER], array_keys($export->mediaItems));
        self::assertFalse($export->mediaItems[self::BANNER]->isPicked());
        self::assertSame('Autumn banner', $export->mediaItems[self::BANNER]->label());
        // What the check read from the file, never what the JSON says
        self::assertSame('image/webp', $export->mediaItems[self::BANNER]->file->mimeType);
        self::assertSame(MediaKind::Video, $export->mediaItems[self::VIDEO]->file->kind);

        $export->cleanUp();
    }

    /**
     * @test
     */
    public function it_takes_a_media_item_the_manifest_picks_as_picked(): void
    {
        $export = $this->copyOfFixture();
        file_put_contents($export . '/manifest.json', str_replace('"media": []', sprintf('"media": ["%s"]', self::BANNER), (string) file_get_contents($export . '/manifest.json')));

        $read = $this->reader()->read(new DirectorySource($export));

        self::assertTrue($read->mediaItems[self::BANNER]->isPicked());
        self::assertFalse($read->mediaItems[self::POSTER]->isPicked());
        $read->cleanUp();
    }

    /**
     * @test
     */
    public function it_refuses_an_export_that_holds_a_path_outside_its_root(): void
    {
        $export = $this->copyOfFixture();
        symlink($this->temporaryFile('outside.png', 'Outside'), $export . '/media/evil.png');

        $read = $this->reader()->read(new DirectorySource($export));

        self::assertSame([[null, 'setono_sylius_cms.import.path_outside_root', ['%path%' => 'media/evil.png']]], self::refusals($read->refusals));
        $read->cleanUp();
    }

    /**
     * @test
     */
    public function it_refuses_a_media_item_whose_file_fails_its_checks_naming_the_media_item(): void
    {
        $refused = RefusedFileException::notAllowed('lookbook.mp4', 'MP4', ['PNG'], 1000);

        $export = $this->reader(refusedFiles: ['lookbook.mp4' => $refused])->read(new DirectorySource(self::FIXTURE));

        self::assertSame([[self::VIDEO, 'setono_sylius_cms.import.media_refused', ['%name%' => 'lookbook', '%reason%' => $refused->reason]]], self::refusals($export->refusals));
        self::assertSame([self::BANNER, self::POSTER], array_keys($export->mediaItems));
        $export->cleanUp();
    }

    /**
     * @test
     */
    public function it_cannot_read_a_media_item_in_a_folder_the_export_does_not_list(): void
    {
        $export = $this->copyOfFixture();
        file_put_contents($export . '/media/folders.json', '[]');

        $this->expectException(InvalidExportException::class);
        $this->expectExceptionMessage('The media item 0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60 is in the folder 0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c, which media/folders.json doesn\'t list');
        $this->reader()->read(new DirectorySource($export));
    }

    /**
     * @test
     */
    public function it_cannot_read_a_media_item_whose_file_the_export_lacks(): void
    {
        $export = $this->copyOfFixture();
        unlink($export . '/media/' . self::VIDEO . '/lookbook.mp4');

        $this->expectException(InvalidExportException::class);
        $this->reader()->read(new DirectorySource($export));
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
        $export->cleanUp();

        $export = $this->reader(contentTypes: ['snippet'])->read(new DirectorySource(self::FIXTURE));

        self::assertSame([[self::PAGE, 'setono_sylius_cms.import.unknown_content_type', ['%title%' => 'Autumn sale', '%content_type%' => 'page']]], self::refusals($export->refusals));
        self::assertArrayNotHasKey(self::PAGE, $export->entries);
        $export->cleanUp();
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
        $export->cleanUp();
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
     * @test
     */
    public function it_cannot_read_a_presets_file_that_holds_another_preset(): void
    {
        $export = $this->copyOfFixture();
        rename($export . '/presets/' . self::PRESET . '.json', $export . '/presets/' . self::OTHER . '.json');

        $this->expectExceptionObject(new InvalidExportException(sprintf('The file presets/%s.json holds the preset %s', self::OTHER, self::PRESET)));
        $this->reader()->read(new DirectorySource($export));
    }

    /**
     * @test
     */
    public function it_cannot_read_a_media_items_file_that_holds_another_media_item(): void
    {
        $export = $this->copyOfFixture();
        rename($export . '/media/' . self::VIDEO . '.json', $export . '/media/' . self::OTHER . '.json');

        $this->expectExceptionObject(new InvalidExportException(sprintf('The file media/%s.json holds the media item %s', self::OTHER, self::VIDEO)));
        $this->reader()->read(new DirectorySource($export));
    }

    /**
     * @test
     */
    public function it_cannot_read_an_entry_that_is_not_in_the_entry_format(): void
    {
        $export = $this->copyOfFixture();
        self::changeEntry($export, self::INNER_SNIPPET, static function (array $entry): array {
            $entry['fields'] = ['context' => 'product'];

            return $entry;
        });

        $this->expectException(InvalidExportException::class);
        $this->expectExceptionMessage(sprintf('The entry %s isn\'t in the entry format: ', self::INNER_SNIPPET));
        $this->reader()->read(new DirectorySource($export));
    }

    /**
     * Before it's read, an entry is named by its title, or by its UUID when its translations can't be read
     *
     * @test
     */
    public function it_names_an_entry_whose_translations_can_not_be_read_by_its_uuid(): void
    {
        $export = $this->copyOfFixture();
        self::changeEntry($export, self::INNER_SNIPPET, static function (array $entry): array {
            $entry['contentType'] = 'recipe';
            $entry['translations'] = 'Newsletter signup';

            return $entry;
        });

        $read = $this->reader()->read(new DirectorySource($export));

        self::assertSame([[self::INNER_SNIPPET, 'setono_sylius_cms.import.unknown_content_type', ['%title%' => self::INNER_SNIPPET, '%content_type%' => 'recipe']]], self::refusals($read->refusals));
        $read->cleanUp();
    }

    /**
     * @test
     */
    public function it_refuses_a_starting_point_of_a_content_type_or_a_layout_the_code_does_not_have(): void
    {
        $export = $this->copyOfFixture();
        $path = $export . '/presets/' . self::PRESET . '.json';
        $preset = (string) file_get_contents($path);

        file_put_contents($path, str_replace('"startingPoint": null', '"startingPoint": {"contentType": "recipe", "layout": null}', $preset));
        $read = $this->reader()->read(new DirectorySource($export));

        self::assertSame([[self::PRESET, 'setono_sylius_cms.import.unknown_content_type', ['%title%' => 'Autumn hero', '%content_type%' => 'recipe']]], self::refusals($read->refusals));
        self::assertSame([], $read->presets);
        $read->cleanUp();

        file_put_contents($path, str_replace('"startingPoint": null', '"startingPoint": {"contentType": "page", "layout": {"code": "wide", "version": 1, "fields": {}, "translated": {}}}', $preset));
        $read = $this->reader()->read(new DirectorySource($export));

        self::assertSame([[self::PRESET, 'setono_sylius_cms.import.unknown_layout', ['%title%' => 'Autumn hero', '%layout%' => 'wide']]], self::refusals($read->refusals));
        $read->cleanUp();
    }

    /**
     * @param array<string, class-string> $blockTypes
     * @param array<string, array<string, class-string>> $layouts
     * @param list<string> $contentTypes
     * @param array<string, RefusedFileException> $refusedFiles what the media library refuses, by filename
     */
    private function reader(
        array $blockTypes = ['heading' => Heading::class, 'image' => Image::class, 'video' => Video::class, 'snippet' => SnippetBlock::class],
        array $layouts = ['page' => ['standard' => Standard::class]],
        array $contentTypes = ['page', 'snippet'],
        ?NewerVersion $newer = null,
        array $refusedFiles = [],
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
        $upgrader->upgradeTree(Argument::type('array'), Argument::type('string'))->will($upgraded);

        // Reads each file as a WebP image or an MP4 video, whatever its JSON says, unless it's refused
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::type(\SplFileInfo::class), Argument::type('string'), Argument::type(UploadLimit::class))->will(static function (array $args) use ($refusedFiles): CheckedFile {
            [$file, $filename] = $args;
            \assert($file instanceof \SplFileInfo && is_string($filename));
            if (isset($refusedFiles[$filename])) {
                throw $refusedFiles[$filename];
            }

            return str_ends_with($filename, '.mp4')
                ? new CheckedFile(MediaKind::Video, 'video/mp4', 'mp4', $file->getPathname(), (int) $file->getSize(), null, null, str_repeat('b', 64))
                : new CheckedFile(MediaKind::Image, 'image/webp', 'webp', $file->getPathname(), (int) $file->getSize(), 48, 20, str_repeat('a', 64));
        });

        return new ExportReader(
            $registry,
            new LayoutRegistry($layouts, new FieldReader()),
            new BlockTypeRegistry($blockTypes, new FieldReader()),
            $upgrader->reveal(),
            new PresetUpgrader($upgrader->reveal()),
            new EntryDenormalizer($registry),
            new ImportedFileChecker($checker->reveal(), new UploadLimit(null, 1_000_000), 1_000_000),
        );
    }

    /**
     * A copy of the committed export to change, in the test's temporary directory
     */
    private function copyOfFixture(): string
    {
        $copy = $this->temporaryDirectory() . '/export';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::FIXTURE, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $path = $copy . substr($file->getPathname(), strlen(self::FIXTURE));
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            copy($file->getPathname(), $path);
        }

        return $copy;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $change what the entry's "entry" holds, changed
     */
    private static function changeEntry(string $export, string $uuid, callable $change): void
    {
        $path = sprintf('%s/entries/%s.json', $export, $uuid);
        /** @var array{entry: array<string, mixed>} $file */
        $file = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        $file['entry'] = $change($file['entry']);
        file_put_contents($path, json_encode($file, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
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
