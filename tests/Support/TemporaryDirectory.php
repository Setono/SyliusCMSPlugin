<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

/**
 * A directory of the test's own for the files it writes, made on first use and removed, with what's in it, after the
 * test
 */
trait TemporaryDirectory
{
    private ?string $temporaryDirectory = null;

    /**
     * @after
     */
    protected function removeTemporaryDirectory(): void
    {
        if (null === $this->temporaryDirectory || !is_dir($this->temporaryDirectory)) {
            $this->temporaryDirectory = null;

            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->temporaryDirectory);
        $this->temporaryDirectory = null;
    }

    private function temporaryDirectory(): string
    {
        if (null === $this->temporaryDirectory) {
            $this->temporaryDirectory = sys_get_temp_dir() . '/setono-cms-test-' . bin2hex(random_bytes(6));
            mkdir($this->temporaryDirectory);
        }

        return $this->temporaryDirectory;
    }

    private function temporaryFile(string $name, string $content): string
    {
        $path = $this->temporaryDirectory() . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
