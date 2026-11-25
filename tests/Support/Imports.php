<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Helpers for kernel tests that run setono:cms:import on the committed export (tests/Fixture/export), as it is, changed
 * in a copy, or zipped with crafted entries. The test case uses TemporaryDirectory too
 *
 * @mixin KernelTestCase
 */
trait Imports
{
    use CommandOutput;

    /** The committed export's video, lookbook.mp4 in Unsorted */
    private const EXPORTED_VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    abstract private function temporaryDirectory(): string;

    /**
     * The committed export: a picked page with a snippet in it, which holds another, an editor preset, and the media
     * items they use with their folders
     */
    private static function exportFixture(): string
    {
        return dirname(__DIR__) . '/Fixture/export';
    }

    /**
     * A copy of the committed export to change, in the test's temporary directory
     */
    private function copyOfFixture(): string
    {
        $copy = $this->temporaryDirectory() . '/export-' . bin2hex(random_bytes(4));
        foreach (self::filesIn(self::exportFixture()) as $path) {
            $directory = dirname($copy . '/' . $path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            copy(self::exportFixture() . '/' . $path, $copy . '/' . $path);
        }

        return $copy;
    }

    /**
     * An export folder in a zip, with more entries crafted in it, as an attacker could
     *
     * @param array<string, string> $files more files by their names in the zip, which it keeps as they're given
     * @param array<string, string> $links symbolic links by their names in the zip, to their targets
     */
    private function zipOf(string $folder, array $files = [], array $links = []): string
    {
        $file = $this->temporaryDirectory() . '/export-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE));
        foreach (self::filesIn($folder) as $path) {
            $zip->addFile($folder . '/' . $path, $path);
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        foreach ($links as $name => $target) {
            $zip->addFromString($name, $target);
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, (0120000 | 0777) << 16);
        }

        $zip->close();

        return $file;
    }

    /**
     * @return list<string> the paths of the files in the directory, relative to it, sorted
     */
    private static function filesIn(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            \assert($file instanceof \SplFileInfo);
            $files[] = substr($file->getPathname(), strlen($directory) + 1);
        }

        sort($files, \SORT_STRING);

        return $files;
    }

    /**
     * Grows the video of a copy of the committed export to the size, in bytes, by a media data box, and writes its new
     * checksum in its JSON
     */
    private static function growVideoTo(string $export, int $size): void
    {
        $json = sprintf('%s/media/%s.json', $export, self::EXPORTED_VIDEO);
        /** @var array{filename: string, checksum: string} $mediaItem */
        $mediaItem = json_decode((string) file_get_contents($json), true, flags: \JSON_THROW_ON_ERROR);
        $file = sprintf('%s/media/%s/%s', $export, self::EXPORTED_VIDEO, $mediaItem['filename']);
        $box = $size - (int) filesize($file);
        self::assertGreaterThanOrEqual(8, $box, 'A media data box needs 8 bytes of its own');
        file_put_contents($file, pack('N', $box) . 'mdat' . str_repeat("\0", $box - 8), \FILE_APPEND);
        clearstatcache(true, $file);
        file_put_contents($json, str_replace($mediaItem['checksum'], 'sha256:' . hash_file('sha256', $file), (string) file_get_contents($json)));
    }

    /**
     * Grows a JSON file of a copy of the committed export to the size, in bytes, with whitespace after its JSON, which
     * keeps it valid
     *
     * @param string $path the file's in the export, such as entries/<uuid>.json
     */
    private static function growJsonTo(string $export, string $path, int $size): void
    {
        $file = $export . '/' . $path;
        $padding = $size - (int) filesize($file);
        self::assertGreaterThanOrEqual(0, $padding, sprintf('%s is already bigger', $path));
        file_put_contents($file, str_repeat(' ', $padding), \FILE_APPEND);
        clearstatcache(true, $file);
    }

    /**
     * A file that brings what the committed export's files add up to to the size, in bytes
     */
    private static function exportPadding(int $size): string
    {
        $exportSize = array_sum(array_map(static fn (string $path): int => (int) filesize(self::exportFixture() . '/' . $path), self::filesIn(self::exportFixture())));

        return str_repeat('a', $size - $exportSize);
    }

    /**
     * A published page with the UUID the committed export's page has, as when environments were cloned from each other.
     * The test case uses Entries too
     */
    private static function createPageWithUuid(string $uuid, string $title, string $slug): PageInterface
    {
        $page = self::createPage($title, $slug, published: true, channels: ['WEB']);
        $page->setUuid($uuid);
        self::entityManager()->flush();

        return $page;
    }

    /**
     * @param array<string, mixed> $input
     * @param bool $boot false to run it in the kernel the test booted, with its listeners
     */
    private static function import(array $input, bool $boot = true): CommandTester
    {
        if ($boot || null === self::$kernel) {
            self::bootKernel();
        }

        \assert(null !== self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:import'));
        $tester->execute($input, ['decorated' => false]);

        return $tester;
    }
}
