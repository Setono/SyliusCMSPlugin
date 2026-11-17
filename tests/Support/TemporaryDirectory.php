<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

/**
 * Files a test writes, in a directory of its own that's removed after the test
 */
trait TemporaryFiles
{
    private ?string $temporaryDirectory = null;

    /**
     * @after
     */
    protected function removeTemporaryFiles(): void
    {
        if (null === $this->temporaryDirectory) {
            return;
        }

        $files = glob($this->temporaryDirectory . '/*');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->temporaryDirectory);
        $this->temporaryDirectory = null;
    }

    private function temporaryFile(string $name, string $content): string
    {
        if (null === $this->temporaryDirectory) {
            $this->temporaryDirectory = sys_get_temp_dir() . '/setono-cms-test-' . bin2hex(random_bytes(6));
            mkdir($this->temporaryDirectory);
        }

        $path = $this->temporaryDirectory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
