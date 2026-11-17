<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Media\Image\ImageNormalizer;
use Setono\SyliusCMSPlugin\Media\Image\SvgSanitizer;
use Setono\SyliusCMSPlugin\Media\Video\VideoDimensions;

final readonly class MediaFileChecker implements MediaFileCheckerInterface
{
    public function __construct(
        private UploadLimit $uploadLimit,
        private MediaTypes $types,
        private MimeTypeDetector $mimeTypeDetector,
        private ImageNormalizer $imageNormalizer,
        private SvgSanitizer $svgSanitizer,
    ) {
    }

    public function check(\SplFileInfo $file, string $filename): CheckedFile
    {
        $size = $file->isFile() ? $file->getSize() : false;
        if (false === $size) {
            throw RefusedFileException::notUploaded($filename);
        }

        if (!$this->uploadLimit->allows($size)) {
            throw RefusedFileException::tooBig($filename, $size, $this->uploadLimit->bytes());
        }

        $path = $file->getPathname();
        $mimeType = $this->mimeTypeDetector->detect($path);
        $extension = $this->mimeTypeDetector->extension($mimeType);
        $kind = $this->types->kindOf($mimeType) ?? throw RefusedFileException::notAllowed($filename, MediaTypes::name($mimeType, $extension), $this->allowed(), $this->uploadLimit->bytes());

        try {
            if (MediaKind::Image === $kind) {
                return $this->image($path, $mimeType, $extension);
            }

            $dimensions = MediaKind::Video === $kind ? VideoDimensions::read($path, $mimeType) : null;

            return new CheckedFile($kind, $mimeType, $extension, $path, $size, $dimensions[0] ?? null, $dimensions[1] ?? null, (string) hash_file('sha256', $path));
        } catch (UnreadableFileException $e) {
            throw RefusedFileException::unreadable($filename, MediaTypes::name($mimeType, $extension), $e);
        }
    }

    /**
     * An image is stored as sanitised or stripped, so that's what its size, dimensions and checksum are read from
     *
     * @throws UnreadableFileException
     */
    private function image(string $path, string $mimeType, string $extension): CheckedFile
    {
        $content = (string) file_get_contents($path);

        if (MimeTypeDetector::SVG === $mimeType) {
            $content = $this->svgSanitizer->sanitize($content);
            $dimensions = $this->svgSanitizer->dimensions($content);
        } else {
            $content = $this->imageNormalizer->normalize($content, $mimeType);
            $info = @getimagesizefromstring($content);
            // PHP reads an AVIF's dimensions from its image spatial extents, which an AVIF may lack, so one without stays
            // a valid image. Any other type it can't read is broken
            if (false === $info && 'image/avif' !== $mimeType) {
                throw new UnreadableFileException('The image\'s dimensions can\'t be read');
            }

            $dimensions = false === $info || 0 === $info[0] || 0 === $info[1] ? null : [$info[0], $info[1]];
        }

        $temporary = tempnam(sys_get_temp_dir(), 'setono_sylius_cms_media_');
        if (false === $temporary || false === file_put_contents($temporary, $content)) {
            throw new \RuntimeException('The checked image couldn\'t be written to a temporary file');
        }

        return new CheckedFile(MediaKind::Image, $mimeType, $extension, $temporary, strlen($content), $dimensions[0] ?? null, $dimensions[1] ?? null, hash('sha256', $content), temporary: true);
    }

    /**
     * What editors call the types the library takes, such as JPEG, in the order media.types lists them
     *
     * @return list<string>
     */
    private function allowed(): array
    {
        $names = [];
        foreach ($this->types->all() as $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                $names[] = MediaTypes::name($mimeType, $this->mimeTypeDetector->extension($mimeType));
            }
        }

        return array_values(array_unique($names));
    }
}
