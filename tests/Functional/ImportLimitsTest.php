<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SmallImportLimitsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * An import takes media files of up to transfer.max_file_size, 1 KB here, zips unpacking to up to
 * transfer.max_unpacked_size, 16 KB here, and JSON files of up to the cap on each, 4 KB here. Going over any refuses it
 * with exit 1, naming the file or the zip's total. See "Media files" in docs/spec/transfer.md
 */
final class ImportLimitsTest extends KernelTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use TemporaryDirectory;

    /** The committed export's picked page */
    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /** Its JSON file, 2126 bytes */
    private const PAGE_FILE = 'entries/' . self::PAGE . '.json';

    protected static function getKernelClass(): string
    {
        return SmallImportLimitsKernel::class;
    }

    /**
     * @test
     */
    public function it_imports_a_zip_with_a_media_file_of_exactly_the_limit(): void
    {
        $export = $this->copyOfFixture();
        self::growVideoTo($export, 1024);

        $tester = self::import(['path' => $this->zipOf($export)]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_refuses_a_media_file_over_the_limit_naming_it(): void
    {
        $export = $this->copyOfFixture();
        self::growVideoTo($export, 1025);

        foreach ([$export, $this->zipOf($export)] as $path) {
            $tester = self::import(['path' => $path]);

            self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
            self::assertStringContainsString('The media item lookbook can\'t be imported: lookbook.mp4 is over the import limit of 1 KB per file.', self::display($tester));
            self::assertNull(self::entries()->findOneByUuid(self::PAGE), 'Nothing is imported');
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_zip_that_unpacks_to_more_than_the_limit_naming_its_total(): void
    {
        $zip = $this->zipOf(self::exportFixture(), ['padding.txt' => self::exportPadding(20 * 1024)]);

        $tester = self::import(['path' => $zip]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The import refused. Nothing changed.', self::display($tester));
        self::assertStringContainsString('The export zip unpacks to 20 KB, over the import limit of 16 KB.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE), 'Nothing is imported');
    }

    /**
     * A folder isn't unpacked, so what its files add up to isn't limited
     *
     * @test
     */
    public function it_imports_a_folder_of_more_than_a_zip_may_unpack_to(): void
    {
        $export = $this->copyOfFixture();
        file_put_contents($export . '/padding.txt', self::exportPadding(20 * 1024));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
    }

    /**
     * @test
     */
    public function it_imports_an_export_with_a_json_file_of_exactly_the_cap(): void
    {
        $export = $this->copyOfFixture();
        self::growJsonTo($export, self::PAGE_FILE, 4 * 1024);

        foreach ([$export, $this->zipOf($export)] as $path) {
            $tester = self::import(['path' => $path, '--dry-run' => true]);

            self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        }
    }

    /**
     * @test
     */
    public function it_refuses_an_export_with_a_json_file_over_the_cap_naming_it(): void
    {
        $export = $this->copyOfFixture();
        self::growJsonTo($export, self::PAGE_FILE, 4 * 1024 + 1);

        foreach ([$export, $this->zipOf($export)] as $path) {
            $tester = self::import(['path' => $path]);

            self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
            self::assertStringContainsString('The import refused. Nothing changed.', self::display($tester));
            self::assertStringContainsString('The export\'s file entries/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e.json is over the import limit of 4 KB per JSON file.', self::display($tester));
            self::assertNull(self::entries()->findOneByUuid(self::PAGE), 'Nothing is imported');
        }
    }

    private static function entries(): EntryRepositoryInterface
    {
        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        return $entries;
    }
}
