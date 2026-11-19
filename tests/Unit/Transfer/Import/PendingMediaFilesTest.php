<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Tests\Support\StorageDirectory;
use Setono\SyliusCMSPlugin\Transfer\Import\PendingMediaFiles;

/**
 * An import writes its media files to storage only once its plan is complete, and leaves none behind when it can't
 * finish. See "What an import does" in docs/spec/transfer.md
 */
final class PendingMediaFilesTest extends TestCase
{
    use ProphecyTrait;
    use StorageDirectory;

    /**
     * @test
     */
    public function it_writes_nothing_until_it_stores_the_files_at_their_paths(): void
    {
        $storage = $this->storage();
        $files = new PendingMediaFiles();
        $files->add($this->checkedFile('banner'), 'a/1/banner.png');
        $files->add($this->checkedFile('poster'), 'b/2/poster.png');

        self::assertFalse($storage->fileExists('a/1/banner.png'));
        self::assertSame(['a/1/banner.png', 'b/2/poster.png'], $files->paths());

        $files->store($storage);

        self::assertSame('banner', $storage->read('a/1/banner.png'));
        self::assertSame('poster', $storage->read('b/2/poster.png'));
    }

    /**
     * @test
     */
    public function it_removes_the_files_it_stored_when_the_import_cannot_finish(): void
    {
        $storage = $this->storage();
        $files = new PendingMediaFiles();
        $files->add($this->checkedFile('banner'), 'a/1/banner.png');
        $files->store($storage);

        $files->remove($storage);

        self::assertFalse($storage->fileExists('a/1/banner.png'));
    }

    /**
     * @test
     */
    public function it_removes_what_it_stored_when_storing_one_fails(): void
    {
        $storage = $this->prophesize(FilesystemOperator::class);
        $storage->writeStream('a/1/banner.png', Argument::any())->shouldBeCalled();
        $storage->writeStream('b/2/poster.png', Argument::any())->willThrow(UnableToWriteFile::atLocation('b/2/poster.png'));
        $storage->delete('a/1/banner.png')->shouldBeCalled();
        $storage->delete('b/2/poster.png')->shouldNotBeCalled();

        $files = new PendingMediaFiles();
        $files->add($this->checkedFile('banner'), 'a/1/banner.png');
        $files->add($this->checkedFile('poster'), 'b/2/poster.png');

        $this->expectException(UnableToWriteFile::class);
        $files->store($storage->reveal());
    }

    private function checkedFile(string $content): CheckedFile
    {
        $path = $this->temporaryFile($content . '.tmp', $content);

        return new CheckedFile(MediaKind::Image, 'image/png', 'png', $path, strlen($content), 1, 1, hash('sha256', $content));
    }
}
