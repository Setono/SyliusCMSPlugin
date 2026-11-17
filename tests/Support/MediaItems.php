<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Media\MediaFolderModifierInterface;
use Setono\SyliusCMSPlugin\Media\MediaUploaderInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Helpers for functional and UI tests that need media items, folders or files to upload. Media items are created through
 * the media library's own uploader, so they're checked and stored like any upload, in the test application's
 * public/media/cms. Each test removes the files it stored there, since the database is rolled back but the files aren't
 */
trait MediaItems
{
    /** @var list<string>|null what the storage held before the test */
    private ?array $storedBefore = null;

    /** @var list<string> */
    private array $temporaryUploads = [];

    /** @var list<string> the directories of the files fileToUpload() wrote */
    private array $uploadDirectories = [];

    abstract protected static function getContainer(): ContainerInterface;

    /**
     * @before
     */
    protected function rememberStoredMedia(): void
    {
        $this->storedBefore = self::storedMedia();
    }

    /**
     * @after
     */
    protected function removeStoredMedia(): void
    {
        foreach (array_diff(self::storedMedia(), $this->storedBefore ?? []) as $directory) {
            self::removeDirectory(self::mediaDirectory() . '/' . $directory);
        }

        foreach ($this->temporaryUploads as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryUploads = [];

        foreach ($this->uploadDirectories as $directory) {
            self::removeDirectory($directory);
        }

        $this->uploadDirectories = [];
    }

    /**
     * A file with this name on disk, as an editor picks it in the browser's file dialog in a UI test
     */
    protected function fileToUpload(string $filename, string $content): string
    {
        $directory = sys_get_temp_dir() . '/setono_sylius_cms_ui_' . bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory . '/' . $filename, $content);
        $this->uploadDirectories[] = $directory;

        return $directory . '/' . $filename;
    }

    /**
     * A PNG of this size in one colour
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
     */
    protected static function png(int $width = 40, int $height = 20, array $rgb = [200, 30, 30]): string
    {
        return Images::png($width, $height, $rgb);
    }

    /**
     * A JPEG of this size in one colour
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
     */
    protected static function jpeg(int $width = 40, int $height = 20, array $rgb = [30, 30, 200]): string
    {
        return Images::jpeg($width, $height, $rgb);
    }

    /**
     * The smallest PDF a MIME type check takes for one, a document
     */
    protected static function pdf(string $comment = 'price list'): string
    {
        return sprintf("%%PDF-1.4\n%% %s\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%%%EOF\n", $comment);
    }

    /**
     * An MP4 holding only its file type box, which a MIME type check takes for a video without dimensions
     */
    protected static function mp4(): string
    {
        $type = 'isom' . pack('N', 512) . 'isomiso2mp41';

        return pack('N', 8 + strlen($type)) . 'ftyp' . $type;
    }

    /**
     * A file as a browser uploads it, with this name
     */
    protected function uploadedFile(string $filename, string $content, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'setono_sylius_cms_test_');
        file_put_contents($path, $content);
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, $filename, null, $error, true);
    }

    protected function uploadMediaItem(string $filename, string $content, ?MediaFolderInterface $folder = null): MediaItemInterface
    {
        /** @var MediaUploaderInterface $uploader */
        $uploader = self::getContainer()->get(MediaUploaderInterface::class);

        return $uploader->upload($this->uploadedFile($filename, $content), $filename, $folder, true)->mediaItem;
    }

    protected static function createMediaFolder(string $name, ?MediaFolderInterface $parent = null): MediaFolderInterface
    {
        /** @var MediaFolderModifierInterface $modifier */
        $modifier = self::getContainer()->get(MediaFolderModifierInterface::class);

        return $modifier->create($name, $parent);
    }

    /**
     * The test application's default media storage
     */
    protected static function mediaDirectory(): string
    {
        return dirname(__DIR__) . '/Application/public/media/cms';
    }

    /**
     * @return list<string>
     */
    private static function storedMedia(): array
    {
        $entries = is_dir(self::mediaDirectory()) ? scandir(self::mediaDirectory()) : false;

        return false === $entries ? [] : array_values(array_diff($entries, ['.', '..']));
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
