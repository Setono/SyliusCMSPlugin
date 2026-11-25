<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSourceInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSources;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportTooBigException;
use Setono\SyliusCMSPlugin\Transfer\Import\ZipSource;

/**
 * An import reads an export folder, or a zip holding the same folder at its root. See "Export format" in
 * docs/spec/transfer.md
 */
final class ExportSourcesTest extends TestCase
{
    use TemporaryDirectory;

    private const FIXTURE = __DIR__ . '/../../../Fixture/export';

    private const ENTRIES = [
        '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e.json',
        '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b.json',
        '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c.json',
    ];

    /**
     * @test
     */
    public function it_reads_an_export_folder_and_the_same_folder_in_a_zip_alike(): void
    {
        $folder = self::sources()->open(self::FIXTURE);
        self::assertInstanceOf(DirectorySource::class, $folder);
        $zip = self::sources()->open($this->zipOfFixture());
        self::assertInstanceOf(ZipSource::class, $zip);

        foreach ([$folder, $zip] as $source) {
            self::assertSame(self::ENTRIES, $source->files('entries'));
            self::assertSame(['0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a.json'], $source->files('presets'));
            self::assertSame([], $source->files('nothing'));
            self::assertSame(file_get_contents(self::FIXTURE . '/manifest.json'), $source->read('manifest.json'));
            self::assertTrue($source->has('media/folders.json'));
            self::assertFalse($source->has('media/missing.json'));

            $banner = (string) file_get_contents(self::FIXTURE . '/media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png');
            self::assertSame([130, $banner], self::extract($source, 'media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png', 1000));

            $this->assertUnreadable($source, 'entries/missing.json');
            $this->assertNotExtracted($source, 'media/missing.png');
        }
    }

    /**
     * So a file bigger than an import takes can't fill the disk
     *
     * @test
     */
    public function it_extracts_no_more_of_a_file_than_it_is_asked_to(): void
    {
        $banner = (string) file_get_contents(self::FIXTURE . '/media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png');

        foreach ([self::sources()->open(self::FIXTURE), self::sources()->open($this->zipOfFixture())] as $source) {
            self::assertSame([11, substr($banner, 0, 11)], self::extract($source, 'media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png', 11));
        }
    }

    /**
     * A folder isn't unpacked, so what its files add up to isn't limited. See "Media files" in docs/spec/transfer.md
     *
     * @test
     */
    public function it_opens_a_zip_that_unpacks_to_no_more_than_transfer_max_unpacked_size(): void
    {
        $sources = new ExportSources(100);

        self::assertSame(file_get_contents(self::FIXTURE . '/manifest.json'), $sources->open(self::FIXTURE)->read('manifest.json'));

        $zip = $this->zipOfFixture();
        foreach ([$sources->open($zip), $sources->zip($zip)] as $source) {
            try {
                $source->read('manifest.json');
                self::fail('It unpacked a zip over the limit');
            } catch (ExportTooBigException $e) {
                self::assertSame('setono_sylius_cms.import.zip.too_big', $e->reason->getMessage());
            }
        }
    }

    /**
     * The committed export's manifest is 294 bytes
     *
     * @test
     */
    public function it_opens_a_folder_and_a_zip_that_read_no_json_file_over_the_cap(): void
    {
        $sources = new ExportSources(\PHP_INT_MAX, 293);

        $zip = $this->zipOfFixture();
        foreach ([$sources->open(self::FIXTURE), $sources->open($zip), $sources->zip($zip)] as $source) {
            try {
                $source->read('manifest.json');
                self::fail('It read a JSON file over the cap');
            } catch (ExportTooBigException $e) {
                self::assertSame('setono_sylius_cms.import.json_file.over_limit', $e->reason->getMessage());
            }
        }

        self::assertSame(file_get_contents(self::FIXTURE . '/manifest.json'), (new ExportSources(\PHP_INT_MAX, 294))->open($zip)->read('manifest.json'));
    }

    /**
     * @test
     */
    public function it_never_reads_a_file_outside_an_export_folder(): void
    {
        $source = new DirectorySource(self::FIXTURE . '/entries');

        self::assertFalse($source->has('../manifest.json'));
        self::assertSame([], $source->files('../media'));
        $this->assertUnreadable($source, '../manifest.json');
    }

    /**
     * @test
     */
    public function it_opens_only_a_folder_or_a_zip_with_a_manifest(): void
    {
        foreach ([$this->temporaryDirectory() . '/missing', $this->temporaryDirectory(), $this->temporaryFile('export.zip', 'Not a zip')] as $path) {
            try {
                self::sources()->open($path);
                self::fail(sprintf('It opened %s', $path));
            } catch (InvalidExportException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @test
     */
    public function it_finds_the_entries_of_a_zip_that_do_not_resolve_inside_the_exports_root(): void
    {
        self::assertSame([], (new ZipSource($this->zipOfFixture()))->pathsOutsideRoot());

        $zip = new ZipSource($this->zipOfFixture(
            ['../evil' => 'Escaped', '/etc/evil' => 'Absolute', 'media\\..\\..\\evil' => 'Escaped on Windows', 'media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/fine.png' => 'Inside'],
            ['media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/link.png' => '/etc/passwd'],
        ));

        self::assertSame(['../evil', '/etc/evil', 'media\\..\\..\\evil', 'media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/link.png'], $zip->pathsOutsideRoot());
    }

    /**
     * @test
     */
    public function it_finds_the_links_in_an_export_folder(): void
    {
        self::assertSame([], (new DirectorySource(self::FIXTURE))->pathsOutsideRoot());

        $folder = $this->temporaryDirectory() . '/export';
        mkdir($folder . '/media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', 0777, true);
        file_put_contents($folder . '/manifest.json', '{}');
        symlink($this->temporaryFile('outside.png', 'Outside'), $folder . '/media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png');

        self::assertSame(['media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png'], (new DirectorySource($folder))->pathsOutsideRoot());
    }

    private static function sources(): ExportSources
    {
        return new ExportSources(\PHP_INT_MAX);
    }

    private function assertUnreadable(ExportSourceInterface $source, string $path): void
    {
        try {
            $source->read($path);
            self::fail(sprintf('It read %s', $path));
        } catch (InvalidExportException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertNotExtracted(ExportSourceInterface $source, string $path): void
    {
        try {
            self::extract($source, $path, 1000);
            self::fail(sprintf('It extracted %s', $path));
        } catch (InvalidExportException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return array{int, string} how many bytes it extracted, and what
     */
    private static function extract(ExportSourceInterface $source, string $path, int $length): array
    {
        $target = fopen('php://memory', 'w+b');
        self::assertIsResource($target);
        $extracted = $source->extract($path, $target, $length);
        rewind($target);

        return [$extracted, (string) stream_get_contents($target)];
    }

    /**
     * The committed export in a zip, with more files crafted in it
     *
     * @param array<string, string> $files more files by their names in the zip, which it keeps as they're given
     * @param array<string, string> $links links by their names in the zip, to their targets
     */
    private function zipOfFixture(array $files = [], array $links = []): string
    {
        $file = $this->temporaryDirectory() . '/export-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::FIXTURE, \FilesystemIterator::SKIP_DOTS)) as $path) {
            \assert($path instanceof \SplFileInfo);
            $zip->addFile($path->getPathname(), substr($path->getPathname(), strlen(self::FIXTURE) + 1));
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        foreach ($links as $name => $target) {
            $zip->addFromString($name, $target);
            // A Unix symbolic link, as zip -y stores one
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, (0120000 | 0777) << 16);
        }

        $zip->close();

        return $file;
    }
}
