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
 * An import checks each media file's size against media.max_upload_size, 1 KB here, as it's smaller than
 * transfer.max_file_size, and not against the limits of the PHP running it. See "Media files" in docs/spec/transfer.md
 */
final class ImportUploadLimitTest extends KernelTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use TemporaryDirectory;

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
        self::growVideoTo($export, 1024);

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_refuses_a_media_file_over_the_configured_limit(): void
    {
        $export = $this->copyOfFixture();
        self::growVideoTo($export, 1025);

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The media item lookbook can\'t be imported: lookbook.mp4 is over the import limit of 1 KB per file.', self::display($tester));
    }
}
