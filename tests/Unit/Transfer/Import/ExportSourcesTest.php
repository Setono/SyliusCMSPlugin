<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSourceInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSources;
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
        $folder = ExportSources::open(self::FIXTURE);
        self::assertInstanceOf(DirectorySource::class, $folder);
        $zip = ExportSources::open($this->zipOfFixture());
        self::assertInstanceOf(ZipSource::class, $zip);

        foreach ([$folder, $zip] as $source) {
            self::assertSame(self::ENTRIES, $source->files('entries'));
            self::assertSame(['0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a.json'], $source->files('presets'));
            self::assertSame([], $source->files('nothing'));
            self::assertSame(file_get_contents(self::FIXTURE . '/manifest.json'), $source->read('manifest.json'));
            self::assertTrue($source->has('media/folders.json'));
            self::assertFalse($source->has('media/missing.json'));

            $stream = $source->readStream('media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png');
            self::assertSame(file_get_contents(self::FIXTURE . '/media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png'), stream_get_contents($stream));
            fclose($stream);

            $this->assertUnreadable($source, 'entries/missing.json');
        }
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
                ExportSources::open($path);
                self::fail(sprintf('It opened %s', $path));
            } catch (InvalidExportException) {
                $this->addToAssertionCount(1);
            }
        }
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

    private function zipOfFixture(): string
    {
        $file = $this->temporaryDirectory() . '/export.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::FIXTURE, \FilesystemIterator::SKIP_DOTS)) as $path) {
            \assert($path instanceof \SplFileInfo);
            $zip->addFile($path->getPathname(), substr($path->getPathname(), strlen(self::FIXTURE) + 1));
        }

        $zip->close();

        return $file;
    }
}
