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
        }), 'autumn-banner.png')->willReturn($checked)->shouldBeCalledOnce();

        $read = (new ImportedFileChecker($checker->reveal(), new UploadLimit(null, 1000)))->check($this->source(), self::mediaItem(), true);

        self::assertSame($checked, $read->file);
        self::assertTrue($read->picked);
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
            $source->readStream(Argument::any())->shouldNotBeCalled();

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
     * @test
     */
    public function it_refuses_a_file_over_the_upload_limit_before_it_copies_more_of_it(): void
    {
        $reason = $this->refusal($this->source(), self::mediaItem(), new UploadLimit(10, 1000));

        self::assertSame('setono_sylius_cms.media.refused.over_limit', $reason->getMessage());
        self::assertSame(['%filename%' => 'autumn-banner.png', '%limit%' => '10 B'], $reason->getParameters());
    }

    /**
     * @test
     */
    public function it_refuses_what_an_upload_would_refuse_whatever_the_json_says(): void
    {
        $refused = RefusedFileException::notAllowed('autumn-banner.png', 'EXE', ['PNG'], 1000);
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::type(\SplFileInfo::class), 'autumn-banner.png')->willThrow($refused);

        try {
            (new ImportedFileChecker($checker->reveal(), new UploadLimit(null, 1000)))->check($this->source(), self::mediaItem(), false);
            self::fail('It took a file an upload refuses');
        } catch (RefusedFileException $e) {
            self::assertSame($refused, $e);
        }
    }

    private function refusal(ExportSourceInterface $source, ExportedMediaItem $mediaItem, ?UploadLimit $limit = null): TranslatableMessage
    {
        $checker = $this->prophesize(MediaFileCheckerInterface::class);
        $checker->check(Argument::cetera())->shouldNotBeCalled();

        try {
            (new ImportedFileChecker($checker->reveal(), $limit ?? new UploadLimit(null, 1000)))->check($source, $mediaItem, false);
        } catch (RefusedFileException $e) {
            return $e->reason;
        }

        self::fail('It took the file');
    }

    private function source(): ExportSourceInterface
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, self::CONTENT);
        rewind($stream);

        $source = $this->prophesize(ExportSourceInterface::class);
        $source->readStream('media/' . self::UUID . '/autumn-banner.png')->willReturn($stream);

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
