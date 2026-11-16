<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\UploadLimit;

/**
 * See "What the library holds" in docs/spec/media.md
 */
final class UploadLimitTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_phps_effective_limit_when_none_is_configured(): void
    {
        self::assertSame(8 * 1024 * 1024, (new UploadLimit(null, 8 * 1024 * 1024))->bytes());
    }

    /**
     * @test
     */
    public function it_is_the_configured_limit_when_that_is_below_phps(): void
    {
        self::assertSame(2 * 1024 * 1024, (new UploadLimit(2 * 1024 * 1024, 8 * 1024 * 1024))->bytes());
    }

    /**
     * PHP refuses a bigger file before the plugin sees it
     *
     * @test
     */
    public function a_configured_limit_above_phps_is_lowered_to_it(): void
    {
        self::assertSame(8 * 1024 * 1024, (new UploadLimit(64 * 1024 * 1024, 8 * 1024 * 1024))->bytes());
    }

    /**
     * @test
     */
    public function it_allows_a_file_of_exactly_the_limit_and_refuses_a_byte_more(): void
    {
        $limit = new UploadLimit(1000, 8 * 1024 * 1024);

        self::assertTrue($limit->allows(1000));
        self::assertFalse($limit->allows(1001));
    }

    /**
     * @test
     */
    public function it_reads_phps_limit_from_the_smaller_of_upload_max_filesize_and_post_max_size(): void
    {
        $php = self::phpLimit();

        self::assertSame($php, UploadLimit::fromPhp(null)->bytes());
        self::assertSame(min(1000, $php), UploadLimit::fromPhp(1000)->bytes());
    }

    private static function phpLimit(): int
    {
        $bytes = static function (string $value): int {
            $number = (int) $value;

            return match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        };

        $limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))], static fn (int $limit): bool => $limit > 0);

        return [] === $limits ? \PHP_INT_MAX : min($limits);
    }
}
