<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportTooBigException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A zip that unpacks to more than transfer.max_unpacked_size refuses the import, with the reason editors see and a
 * message that says the same in English, for a log. See "Media files" in docs/spec/transfer.md
 */
final class ExportTooBigExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_names_the_total_of_a_zip_whose_directory_says_it_unpacks_to_more_than_the_limit(): void
    {
        $exception = ExportTooBigException::declared(20 * 1024, 16 * 1024);

        self::assertSame('The export zip unpacks to 20 KB, over the import limit of 16 KB.', $exception->getMessage());
        self::assertEquals(new TranslatableMessage('setono_sylius_cms.import.zip.too_big', ['%size%' => '20 KB', '%limit%' => '16 KB']), $exception->reason);
    }

    /**
     * Extracting stops one byte past the limit, so only the limit is known
     *
     * @test
     */
    public function it_names_the_limit_of_a_zip_that_unpacks_to_more_than_its_directory_says(): void
    {
        $exception = ExportTooBigException::unpacked(16 * 1024);

        self::assertSame('The export zip unpacks to more than the import limit of 16 KB, more than its directory says.', $exception->getMessage());
        self::assertEquals(new TranslatableMessage('setono_sylius_cms.import.zip.over_limit', ['%limit%' => '16 KB']), $exception->reason);
    }

    /**
     * Reading it stops one byte past the cap, so only the cap is known
     *
     * @test
     */
    public function it_names_a_json_file_over_the_cap_on_each_json_file_an_import_reads(): void
    {
        $exception = ExportTooBigException::jsonFile('entries/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e.json', 16 * 1024 * 1024);

        self::assertSame('The export\'s file entries/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e.json is over the import limit of 16 MB per JSON file.', $exception->getMessage());
        self::assertEquals(new TranslatableMessage('setono_sylius_cms.import.json_file.over_limit', ['%path%' => 'entries/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e.json', '%limit%' => '16 MB']), $exception->reason);
    }
}
