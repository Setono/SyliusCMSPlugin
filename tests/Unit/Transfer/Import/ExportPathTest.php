<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportPath;

/**
 * Every file of an export must resolve inside the export's root: an absolute path or a ".." segment refuses the
 * import. See "Media files" in docs/spec/transfer.md
 */
final class ExportPathTest extends TestCase
{
    /**
     * @test
     */
    public function it_takes_a_path_relative_to_the_exports_root(): void
    {
        foreach (['manifest.json', 'media/0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/autumn-banner.png', 'media/', './entries/a.json', 'media/a..b.png', 'media/.hidden'] as $path) {
            self::assertTrue(ExportPath::isInsideRoot($path), $path);
        }
    }

    /**
     * @test
     */
    public function it_refuses_an_absolute_path(): void
    {
        foreach (['/etc/passwd', '\\Windows\\win.ini', 'C:/evil', 'c:\\evil', '//server/share/evil', ''] as $path) {
            self::assertFalse(ExportPath::isInsideRoot($path), $path);
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_path_with_a_parent_segment_whichever_slash_it_uses(): void
    {
        foreach (['../evil', 'media/../../evil', 'media/a/..', 'media\\..\\..\\evil', '..', "media/\0/../evil"] as $path) {
            self::assertFalse(ExportPath::isInsideRoot($path), $path);
        }
    }
}
