<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Transfer\Import\UploadedExports;

final class UploadedExportsTest extends TestCase
{
    use ProphecyTrait;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @test
     */
    public function it_keeps_an_uploaded_zip_in_the_storage_privately_under_a_path_no_one_guesses(): void
    {
        $storage = $this->prophesize(FilesystemOperator::class);
        $written = [];
        $storage->writeStream(Argument::type('string'), Argument::any(), ['visibility' => 'private'])->will(static function (array $arguments) use (&$written): void {
            \assert(is_string($arguments[0]) && is_resource($arguments[1]));
            $written[$arguments[0]] = stream_get_contents($arguments[1]);
        });
        $exports = new UploadedExports($storage->reveal(), new NullLogger());

        $first = $exports->keep(new \SplFileInfo($this->file('PK zip')));
        $second = $exports->keep(new \SplFileInfo($this->file('PK zip')));

        self::assertMatchesRegularExpression('#^imports/[0-9a-f]{32}\.zip$#', $first);
        self::assertNotSame($first, $second);
        self::assertSame([$first => 'PK zip', $second => 'PK zip'], $written);
    }

    /**
     * @test
     */
    public function it_reads_a_kept_zip_from_a_local_copy_it_removes_afterwards(): void
    {
        $storage = $this->prophesize(FilesystemOperator::class);
        $storage->readStream('imports/abc.zip')->willReturn(fopen($this->file('PK zip'), 'rb'));
        $exports = new UploadedExports($storage->reveal(), new NullLogger());

        $copy = $exports->read('imports/abc.zip', static function (string $file): string {
            self::assertSame('PK zip', file_get_contents($file));

            return $file;
        });

        self::assertFileDoesNotExist($copy);
    }

    /**
     * @test
     */
    public function it_deletes_a_kept_zip_and_logs_one_it_cannot_delete_rather_than_failing(): void
    {
        $storage = $this->prophesize(FilesystemOperator::class);
        $storage->delete('imports/abc.zip')->shouldBeCalled();
        $storage->delete('imports/def.zip')->willThrow(UnableToDeleteFile::atLocation('imports/def.zip', 'the disk is gone'));
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->warning(Argument::containingString('imports/def.zip'), Argument::type('array'))->shouldBeCalledOnce();
        $exports = new UploadedExports($storage->reveal(), $logger->reveal());

        $exports->delete('imports/abc.zip');
        $exports->delete('imports/def.zip');
    }

    private function file(string $content): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'setono_sylius_cms_test_');
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
