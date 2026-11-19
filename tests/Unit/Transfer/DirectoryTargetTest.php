<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\DirectoryTarget;

/**
 * Writes an export into a folder: manifest.json at its root, and entries/, presets/ and media/ beside it, making the
 * folders its files sit in. See "Export format" in docs/spec/transfer.md
 */
final class DirectoryTargetTest extends TestCase
{
    use TemporaryDirectory;

    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function it_writes_a_file_at_its_path_in_the_folder_making_the_folders_it_sits_in(): void
    {
        $directory = $this->temporaryDirectory() . '/export';
        $target = new DirectoryTarget($directory);

        $target->write('manifest.json', '{"format": 1}');
        $target->write('media/' . self::BANNER . '.json', '{"name": "Autumn banner"}');

        self::assertSame('{"format": 1}', file_get_contents($directory . '/manifest.json'));
        self::assertSame('{"name": "Autumn banner"}', file_get_contents($directory . '/media/' . self::BANNER . '.json'));
    }

    /**
     * @test
     */
    public function it_takes_a_folder_given_with_a_trailing_slash(): void
    {
        $directory = $this->temporaryDirectory() . '/export';
        (new DirectoryTarget($directory . '/'))->write('entries/a.json', '{}');

        self::assertSame('{}', file_get_contents($directory . '/entries/a.json'));
    }

    /**
     * @test
     */
    public function it_replaces_a_file_it_writes_again(): void
    {
        $directory = $this->temporaryDirectory();
        $target = new DirectoryTarget($directory);

        $target->write('manifest.json', 'first');
        $target->write('manifest.json', 'second');

        self::assertSame('second', file_get_contents($directory . '/manifest.json'));
    }

    /**
     * @test
     */
    public function it_copies_a_stream_into_a_file_making_the_folders_it_sits_in(): void
    {
        $directory = $this->temporaryDirectory();
        $stream = self::stream('The original file');

        (new DirectoryTarget($directory))->writeStream('media/' . self::BANNER . '/autumn-banner.jpg', $stream);

        self::assertSame('The original file', file_get_contents($directory . '/media/' . self::BANNER . '/autumn-banner.jpg'));
        self::assertTrue(is_resource($stream), 'The stream is the caller\'s to close');
        fclose($stream);
    }

    /**
     * @test
     */
    public function it_throws_when_a_folder_a_file_sits_in_cannot_be_made(): void
    {
        $directory = $this->temporaryDirectory();
        // A file where the folder would go
        file_put_contents($directory . '/media', 'A file');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The directory %s/media couldn\'t be made', $directory));
        self::quietly(static fn () => (new DirectoryTarget($directory))->write('media/a.json', '{}'));
    }

    /**
     * @test
     */
    public function it_throws_when_a_file_cannot_be_written(): void
    {
        $directory = $this->temporaryDirectory();
        // A folder where the file would go
        mkdir($directory . '/manifest.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The file %s/manifest.json couldn\'t be written', $directory));
        self::quietly(static fn () => (new DirectoryTarget($directory))->write('manifest.json', '{}'));
    }

    /**
     * @test
     */
    public function it_throws_when_a_stream_cannot_be_copied_into_its_file(): void
    {
        $directory = $this->temporaryDirectory();
        mkdir($directory . '/media');
        $stream = self::stream('The original file');

        try {
            self::quietly(static fn () => (new DirectoryTarget($directory))->writeStream('media', $stream));
            self::fail('The stream is not copied over a folder');
        } catch (\RuntimeException $e) {
            self::assertSame(sprintf('The file %s/media couldn\'t be written', $directory), $e->getMessage());
        } finally {
            fclose($stream);
        }
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
