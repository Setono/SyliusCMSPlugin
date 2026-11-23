<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaFileCheckerInterface;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSourceInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportTooBigException;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportedFileChecker;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Every file in an export goes through the checks an upload does, after its filename and its checksum: an export is
 * untrusted input. See "Media files" in docs/spec/transfer.md
 */
final class ImportedFileCheckerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const CONTENT = 'The banner as the source stored it';

    /**
     * @test
     */
    public function it_checks_a_copy_of_the_file_as_an_upload_is_checked_and_removes_the_copy_once_done(): void
    {
        $checked = new CheckedFile(MediaKind::Image, 'image/png', 'png', '/tmp/checked.png', 34, 48, 20, str_repeat('a', 64));
        $copy = null;
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::that(static function (\SplFileInfo $file) use (&$copy): bool {
            $copy = $file->getPathname();

            return self::CONTENT === file_get_contents($copy);
        }), 'autumn-banner.png', Argument::type(UploadLimit::class))->willReturn($checked)->shouldBeCalledOnce();

        $read = (new ImportedFileChecker($checker->reveal(), new UploadLimit(null, 1000)))->check($this->source(), self::mediaItem(), true);

        self::assertSame($checked, $read->file);
        self::assertTrue($read->isPicked());
        self::assertSame(self::UUID, $read->uuid());
        self::assertSame('Autumn banner', $read->label());
        self::assertIsString($copy);
        self::assertFileExists($copy);

        $read->cleanUp();
        self::assertFileDoesNotExist($copy);
    }

    /**
     * @test
     */
    public function it_refuses_a_filename_that_is_not_a_plain_basename_without_reading_the_file(): void
    {
        foreach (['../evil.png', 'media/evil.png', 'evil\\..\\..png', "evil\n.png", '..'] as $filename) {
            $source = $this->prophesize(ExportSourceInterface::class);
            $source->extract(Argument::cetera())->shouldNotBeCalled();

            $reason = $this->refusal($source->reveal(), self::mediaItem(filename: $filename));

            self::assertSame('setono_sylius_cms.import.media_file.filename', $reason->getMessage());
            self::assertSame(['%filename%' => $filename], $reason->getParameters());
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_file_whose_sha256_does_not_match_its_checksum(): void
    {
        $reason = $this->refusal($this->source(), self::mediaItem(checksum: hash('sha256', 'Another file')));

        self::assertSame('setono_sylius_cms.import.media_file.checksum', $reason->getMessage());
        self::assertSame(['%filename%' => 'autumn-banner.png'], $reason->getParameters());
    }

    /**
     * The file is extracted no further than one byte past the limit, which tells a file over it, so an export can't
     * fill the disk with it
     *
     * @test
     */
    public function it_refuses_a_file_over_the_imports_limit_before_it_extracts_more_of_it(): void
    {
        $lengths = [];
        $reason = $this->refusal($this->source($lengths), self::mediaItem(), new UploadLimit(null, 1000), 10);

        self::assertSame('setono_sylius_cms.import.media_file.over_limit', $reason->getMessage());
        self::assertSame(['%filename%' => 'autumn-banner.png', '%limit%' => '10 B'], $reason->getParameters());
        self::assertSame([11], $lengths);
    }

    /**
     * @test
     */
    public function it_refuses_a_file_over_the_configured_upload_limit_when_that_is_the_smaller(): void
    {
        $reason = $this->refusal($this->source(), self::mediaItem(), new UploadLimit(10, 1000), 1000);

        self::assertSame('setono_sylius_cms.import.media_file.over_limit', $reason->getMessage());
        self::assertSame(['%filename%' => 'autumn-banner.png', '%limit%' => '10 B'], $reason->getParameters());
    }

    /**
     * The PHP that runs an import, such as the CLI's, didn't upload the file, so its own limit, 10 bytes here, doesn't
     * count. The file is checked against the smaller of the configured one and transfer.max_file_size
     *
     * @test
     */
    public function it_checks_the_file_against_the_smaller_of_the_configured_limit_and_the_imports_not_that_of_the_php_running_it(): void
    {
        foreach ([[null, 50, 50], [100, 1000, 100], [1000, 100, 100]] as [$configured, $maxFileSize, $expected]) {
            $checked = new CheckedFile(MediaKind::Image, 'image/png', 'png', '/tmp/checked.png', 34, 48, 20, str_repeat('a', 64));
            $checker = $this->prophesize(MediaFileCheckerInterface::class);
            $checker->check(
                Argument::type(\SplFileInfo::class),
                'autumn-banner.png',
                Argument::that(static fn (UploadLimit $limit): bool => $expected === $limit->bytes()),
            )->willReturn($checked)->shouldBeCalledOnce();

            $read = (new ImportedFileChecker($checker->reveal(), (new UploadLimit($configured, 10))->forImports($maxFileSize)))->check($this->source(), self::mediaItem(), true);

            self::assertSame($checked, $read->file);
            $read->cleanUp();
        }
    }

    /**
     * Such as when a zip turns out to unpack to more than it may, or can't be read
     *
     * @test
     */
    public function it_removes_its_copy_of_a_file_whose_extraction_fails(): void
    {
        $copy = null;
        $source = $this->prophesize(ExportSourceInterface::class);
        $source->extract(Argument::type('string'), Argument::any(), Argument::type('int'))->will(static function (array $arguments) use (&$copy): int {
            /** @var resource $target */
            $target = $arguments[1];
            $copy = stream_get_meta_data($target)['uri'] ?? null;
            fwrite($target, 'Half the file');

            throw ExportTooBigException::unpacked(10);
        });

        try {
            (new ImportedFileChecker($this->prophesize(MediaFileCheckerInterface::class)->reveal(), new UploadLimit(null, 1000)))->check($source->reveal(), self::mediaItem(), true);
            self::fail('It took a file it couldn\'t extract');
        } catch (ExportTooBigException) {
        }

        self::assertIsString($copy);
        self::assertFileDoesNotExist($copy);
    }

    /**
     * @test
     */
    public function it_refuses_what_an_upload_would_refuse_whatever_the_json_says(): void
    {
        $refused = RefusedFileException::notAllowed('autumn-banner.png', 'EXE', ['PNG'], 1000);
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::type(\SplFileInfo::class), 'autumn-banner.png', Argument::type(UploadLimit::class))->willThrow($refused);

        try {
            (new ImportedFileChecker($checker->reveal(), new UploadLimit(null, 1000)))->check($this->source(), self::mediaItem(), false);
            self::fail('It took a file an upload refuses');
        } catch (RefusedFileException $e) {
            self::assertSame($refused, $e);
        }
    }

    private function refusal(ExportSourceInterface $source, ExportedMediaItem $mediaItem, ?UploadLimit $limit = null, int $maxFileSize = 1000): TranslatableMessage
    {
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::cetera())->shouldNotBeCalled();

        try {
            (new ImportedFileChecker($checker->reveal(), ($limit ?? new UploadLimit(null, 1000))->forImports($maxFileSize)))->check($source, $mediaItem, false);
        } catch (RefusedFileException $e) {
            return $e->reason;
        }

        self::fail('It took the file');
    }

    /**
     * An export holding the banner, which extracts no more of it than it's asked to
     *
     * @param list<int> $lengths the lengths it's asked to extract, which it adds to
     */
    private function source(array &$lengths = []): ExportSourceInterface
    {
        $source = $this->prophesize(ExportSourceInterface::class);
        $source->extract('media/' . self::UUID . '/autumn-banner.png', Argument::any(), Argument::type('int'))->will(static function (array $arguments) use (&$lengths): int {
            /** @var array{string, resource, int} $arguments */
            [, $target, $length] = $arguments;
            $lengths[] = $length;

            return (int) fwrite($target, substr(self::CONTENT, 0, $length));
        });

        return $source->reveal();
    }

    private static function mediaItem(string $filename = 'autumn-banner.png', ?string $checksum = null): ExportedMediaItem
    {
        return new ExportedMediaItem(
            self::UUID,
            null,
            MediaKind::Image,
            'Autumn banner',
            $filename,
            // What the JSON says of the file is never read
            'image/jpeg',
            482113,
            2400,
            1000,
            null,
            [],
            new FocalPoint(0.5, 0.5),
            null,
            $checksum ?? hash('sha256', self::CONTENT),
            new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC')),
        );
    }
}
