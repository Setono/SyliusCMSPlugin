<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tests\Support\ZipHeaders;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportTooBigException;
use Setono\SyliusCMSPlugin\Transfer\Import\ZipSource;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A zip unpacks to no more than transfer.max_unpacked_size: by its directory, before anything is extracted, and by
 * what's extracted, since a zip's headers can lie. See "Media files" in docs/spec/transfer.md
 */
final class ZipSourceTest extends TestCase
{
    use TemporaryDirectory;
    use ZipHeaders;

    private const MANIFEST = '{"format": 1}';

    /**
     * @test
     */
    public function it_refuses_a_zip_whose_directory_says_it_unpacks_to_more_than_the_limit_before_extracting_anything(): void
    {
        $zip = $this->zip(['media/a/banner.png' => str_repeat('a', 2000)]);
        // What it holds and what's in it are read from its directory, which extracts nothing
        $source = new ZipSource($zip, 1024);
        self::assertTrue($source->has('manifest.json'));
        self::assertSame(['banner.png'], $source->files('media/a'));

        $reason = self::refusal(static fn () => $source->read('manifest.json'));
        self::assertSame('setono_sylius_cms.import.zip.too_big', $reason->getMessage());
        self::assertSame(['%size%' => '2 KB', '%limit%' => '1 KB'], $reason->getParameters());

        $target = self::target();
        self::refusal(static fn () => $source->extract('media/a/banner.png', $target, 10_000));
        self::assertSame(0, ftell($target), 'Nothing is extracted');
    }

    /**
     * @test
     */
    public function it_unpacks_a_zip_of_exactly_the_limit(): void
    {
        $source = new ZipSource($this->zip(['media/a/banner.png' => str_repeat('a', 2000)]), 2000 + strlen(self::MANIFEST));

        self::assertSame(self::MANIFEST, $source->read('manifest.json'));
        $target = self::target();
        self::assertSame(2000, $source->extract('media/a/banner.png', $target, 10_000));
    }

    /**
     * Its directory and the file's local header say it's 100 bytes, which a zip takes no one's word for. It holds 1 MB,
     * which is extracted no further than one byte past the limit
     *
     * @test
     */
    public function it_stops_extracting_a_file_whose_headers_understate_its_size_once_the_zip_is_over_the_limit(): void
    {
        $zip = $this->zip(['media/a/banner.png' => str_repeat("\0", 1024 * 1024)]);
        self::declareSize($zip, 'media/a/banner.png', 100);
        $source = new ZipSource($zip, 10 * 1024);

        self::assertSame(self::MANIFEST, $source->read('manifest.json'));
        $target = self::target();
        $reason = self::refusal(static fn () => $source->extract('media/a/banner.png', $target, 2 * 1024 * 1024));

        self::assertSame('setono_sylius_cms.import.zip.over_limit', $reason->getMessage());
        self::assertSame(['%limit%' => '10 KB'], $reason->getParameters());
        self::assertSame(10 * 1024 - strlen(self::MANIFEST) + 1, ftell($target));
    }

    /**
     * What it reads, such as an entry's JSON, counts as much as what it extracts, such as the part of a media item's
     * file over the import's limit per file
     *
     * @test
     */
    public function it_counts_everything_it_unpacks_toward_the_limit(): void
    {
        $zip = $this->zip(['media/a/banner.png' => str_repeat("\0", 6000), 'entries/a.json' => str_repeat('a', 5000)]);
        self::declareSize($zip, 'media/a/banner.png', 100);

        $source = new ZipSource($zip, 7000);
        self::assertSame(3000, $source->extract('media/a/banner.png', self::target(), 3000));
        self::assertSame('setono_sylius_cms.import.zip.over_limit', self::refusal(static fn () => $source->read('entries/a.json'))->getMessage());

        $source = new ZipSource($zip, 7000);
        self::assertSame(5000, strlen($source->read('entries/a.json')));
        $target = self::target();
        self::assertSame('setono_sylius_cms.import.zip.over_limit', self::refusal(static fn () => $source->extract('media/a/banner.png', $target, 10_000))->getMessage());
        self::assertSame(7000 - 5000 + 1, ftell($target));
    }

    /**
     * @test
     */
    public function it_refuses_to_extract_a_file_that_does_not_match_its_checksum_as_one_it_cannot_read(): void
    {
        $zip = $this->zip(['media/a/banner.png' => str_repeat('abcdefgh', 1024)], \ZipArchive::CM_STORE);
        $data = (string) file_get_contents($zip);
        $offset = (int) strpos($data, str_repeat('abcdefgh', 1024));
        $data[$offset + 10] = 'X';
        file_put_contents($zip, $data);

        $this->expectException(InvalidExportException::class);

        (new ZipSource($zip))->extract('media/a/banner.png', self::target(), 10_000);
    }

    /**
     * The export's manifest, with these files
     *
     * @param array<string, string> $files
     * @param int $method how they're compressed
     */
    private function zip(array $files, int $method = \ZipArchive::CM_DEFLATE): string
    {
        $file = $this->temporaryDirectory() . '/export-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE));
        $zip->addFromString('manifest.json', self::MANIFEST);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
            $zip->setCompressionName($name, $method);
        }

        $zip->close();

        return $file;
    }

    /**
     * @return resource
     */
    private static function target()
    {
        $target = fopen('php://memory', 'w+b');
        self::assertIsResource($target);

        return $target;
    }

    /**
     * @param callable(): mixed $unpack
     */
    private static function refusal(callable $unpack): TranslatableMessage
    {
        try {
            $unpack();
        } catch (ExportTooBigException $e) {
            return $e->reason;
        }

        self::fail('It unpacked a zip over the limit');
    }
}
