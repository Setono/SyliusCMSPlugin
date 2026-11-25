<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportFileCopy;

final class ExportFileCopyTest extends TestCase
{
    /**
     * @test
     */
    public function it_copies_no_more_than_the_length_and_closes_the_files_stream(): void
    {
        $stream = self::stream('Hello world', 'r+');
        $target = self::stream('', 'r+');

        self::assertSame(5, ExportFileCopy::copy($stream, $target, 5, 'media/lookbook.mp4'));
        self::assertFalse(is_resource($stream));
        rewind($target);
        self::assertSame('Hello', stream_get_contents($target));
    }

    /**
     * @test
     */
    public function it_refuses_a_file_it_could_not_open(): void
    {
        $this->expectException(InvalidExportException::class);
        $this->expectExceptionMessage('The export has no readable file media/lookbook.mp4 in export.zip');

        ExportFileCopy::copy(false, self::stream('', 'r+'), 5, 'media/lookbook.mp4 in export.zip');
    }

    /**
     * @test
     */
    public function it_refuses_a_file_it_could_not_copy_and_still_closes_its_stream(): void
    {
        $stream = self::stream('Hello world', 'r+');

        try {
            ExportFileCopy::copy($stream, self::stream('', 'r'), 5, 'media/lookbook.mp4');
            self::fail('The copy was not refused');
        } catch (InvalidExportException $e) {
            self::assertSame('The export\'s file media/lookbook.mp4 can\'t be read', $e->getMessage());
        }

        self::assertFalse(is_resource($stream));
    }

    /**
     * @return resource
     */
    private static function stream(string $contents, string $mode)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        if ('r' === $mode) {
            fclose($stream);
            $stream = fopen('php://memory', 'r');
            self::assertIsResource($stream);
        }

        return $stream;
    }
}
