<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SmallUploadLimitKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * An import checks each media file's size against media.max_upload_size, 1 KB here, and nothing else. See "Media
 * files" in docs/spec/transfer.md
 */
final class ImportUploadLimitTest extends KernelTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use TemporaryDirectory;

    /** A video in Unsorted, of 28 bytes */
    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    protected static function getKernelClass(): string
    {
        return SmallUploadLimitKernel::class;
    }

    /**
     * @test
     */
    public function it_imports_a_media_file_of_exactly_the_configured_limit(): void
    {
        $export = $this->copyOfFixture();
        self::growVideo($export, self::VIDEO, 1024 - 28 - 8);

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_refuses_a_media_file_over_the_configured_limit(): void
    {
        $export = $this->copyOfFixture();
        self::growVideo($export, self::VIDEO, 1024 - 28 - 8 + 1);

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The media item lookbook can\'t be imported: lookbook.mp4 is over the upload limit of 1 KB.', self::display($tester));
    }
}
