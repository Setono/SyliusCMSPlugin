<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\FileSize;

final class FileSizeTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider sizes
     */
    public function it_reads_a_size_as_php_ini_writes_it(int|string $size, int $bytes): void
    {
        self::assertSame($bytes, FileSize::parse($size));
    }

    /**
     * @return iterable<string, array{int|string, int}>
     */
    public static function sizes(): iterable
    {
        yield 'bytes as an integer' => [1500, 1500];
        yield 'bytes as a string' => ['1500', 1500];
        yield 'kilobytes' => ['512K', 512 * 1024];
        yield 'megabytes, in lower case' => ['20m', 20 * 1024 * 1024];
        yield 'gigabytes' => ['2G', 2 * 1024 * 1024 * 1024];
    }

    /**
     * @test
     *
     * @dataProvider invalidSizes
     */
    public function it_refuses_what_is_not_a_size(int|string $size): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FileSize::parse($size);
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function invalidSizes(): iterable
    {
        yield 'empty' => [''];
        yield 'words' => ['twenty megabytes'];
        yield 'an unknown unit' => ['20T'];
        yield 'zero' => ['0'];
        yield 'a negative integer' => [-1];
        yield 'negative' => ['-1M'];
    }

    /**
     * @test
     *
     * @dataProvider formatted
     */
    public function it_writes_a_size_for_editors(int $bytes, string $formatted): void
    {
        self::assertSame($formatted, FileSize::format($bytes));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function formatted(): iterable
    {
        yield 'bytes' => [900, '900 B'];
        yield 'kilobytes' => [1536, '1.5 KB'];
        yield 'whole megabytes' => [20 * 1024 * 1024, '20 MB'];
        yield 'megabytes' => [(int) (2.25 * 1024 * 1024), '2.3 MB'];
        yield 'gigabytes' => [2 * 1024 * 1024 * 1024, '2 GB'];
    }
}
