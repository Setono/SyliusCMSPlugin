<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
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
     * A published page with the UUID the committed export's page has, as when environments were cloned from each other.
     * The test case uses Entries too
     */
    private static function createPageWithUuid(string $uuid, string $title, string $slug): PageInterface
    {
        $page = self::createPage($title, $slug, published: true, channels: ['WEB']);
        self::entityManager()->getConnection()->update('setono_sylius_cms__entry', ['uuid' => $uuid], ['id' => $page->getId()]);
        self::entityManager()->clear();

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);
        $page = $entries->findOneByUuid($uuid);
        self::assertInstanceOf(PageInterface::class, $page);

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

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * The command's output without any whitespace, for messages with a path SymfonyStyle may wrap anywhere
     */
    private static function squashed(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', '', $tester->getDisplay());
    }
}
