<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\ZipTarget;

/**
 * Writes an export into a zip holding the export's folder at its root, complete once it's closed. A file from a stream
 * waits in a temporary file until then, which closing removes. See "Export format" in docs/spec/transfer.md
 */
final class ZipTargetTest extends TestCase
{
    use TemporaryDirectory;

    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function it_writes_the_files_and_streams_into_a_zip_holding_the_export_at_its_root(): void
    {
        $file = $this->temporaryDirectory() . '/export.zip';
        $stream = self::stream('The original file');

        $target = new ZipTarget($file);
        $target->write('manifest.json', '{"format": 1}');
        $target->write('entries/a.json', '{"changedAt": "2026-11-12T10:15:00Z"}');
        $target->writeStream('media/' . self::BANNER . '/autumn-banner.jpg', $stream);
        fclose($stream);
        $target->close();

        self::assertSame([
            'manifest.json' => '{"format": 1}',
            'entries/a.json' => '{"changedAt": "2026-11-12T10:15:00Z"}',
            'media/' . self::BANNER . '/autumn-banner.jpg' => 'The original file',
        ], self::contents($file));
    }

    /**
     * @test
     */
    public function it_removes_the_temporary_files_of_the_streams_once_it_is_closed(): void
    {
        $before = self::temporaryFiles();
        $target = new ZipTarget($this->temporaryDirectory() . '/export.zip');
        $stream = self::stream('The original file');
        $target->writeStream('media/' . self::BANNER . '/autumn-banner.jpg', $stream);
        fclose($stream);

        $waiting = array_values(array_diff(self::temporaryFiles(), $before));
        self::assertNotSame([], $waiting, 'The streamed file waits in a temporary file until the zip is closed');

        $target->close();

        foreach ($waiting as $temporaryFile) {
            self::assertFileDoesNotExist($temporaryFile);
        }
    }

    /**
     * @test
     */
    public function it_removes_the_temporary_files_even_when_the_zip_cannot_be_written(): void
    {
        $directory = $this->temporaryDirectory() . '/gone';
        mkdir($directory);
        $before = self::temporaryFiles();
        $target = new ZipTarget($directory . '/export.zip');
        $stream = self::stream('The original file');
        $target->writeStream('media/' . self::BANNER . '/autumn-banner.jpg', $stream);
        fclose($stream);
        $waiting = array_values(array_diff(self::temporaryFiles(), $before));
        // The zip is only written on close, so its folder can still go
        rmdir($directory);

        try {
            self::quietly($target->close(...));
            self::fail('A zip whose folder is gone is not written');
        } catch (\RuntimeException $e) {
            self::assertSame(sprintf('The zip %s/export.zip couldn\'t be written', $directory), $e->getMessage());
        }

        self::assertNotSame([], $waiting);
        foreach ($waiting as $temporaryFile) {
            self::assertFileDoesNotExist($temporaryFile);
        }
    }

    /**
     * @test
     */
    public function it_cannot_make_a_zip_that_exists_already(): void
    {
        $file = $this->temporaryFile('export.zip', 'An earlier export');

        try {
            new ZipTarget($file);
            self::fail('An existing zip is not overwritten');
        } catch (\RuntimeException $e) {
            self::assertSame(sprintf('The zip %s couldn\'t be made (error %d)', $file, \ZipArchive::ER_EXISTS), $e->getMessage());
        }

        self::assertSame('An earlier export', file_get_contents($file));
    }

    /**
     * @return array<string, string> each file's contents by its path in the zip
     */
    private static function contents(string $file): array
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::RDONLY));

        $contents = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            $contents[$name] = (string) $zip->getFromIndex($i);
        }

        $zip->close();

        return $contents;
    }

    /**
     * @return list<string> the temporary files ZipTarget makes for streams, wherever they're from
     */
    private static function temporaryFiles(): array
    {
        $files = glob(sys_get_temp_dir() . '/setono_sylius_cms_export_*');

        return false === $files ? [] : $files;
    }

    /**
     * @return resource
     */
    private static function stream(string $contents)
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * Runs the call without PHP's warnings, which PHPUnit would otherwise throw in place of the target's own exception
     */
    private static function quietly(callable $call): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $call();
        } finally {
            restore_error_handler();
        }
    }
}
