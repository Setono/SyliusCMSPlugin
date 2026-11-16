<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\MimeTypeDetector;
use Symfony\Component\Mime\MimeTypes;

/**
 * Nothing is trusted from a file's name. See "Uploading" in docs/spec/media.md
 */
final class MimeTypeDetectorTest extends TestCase
{
    use TemporaryFiles;

    /**
     * @test
     */
    public function it_reads_the_mime_type_from_the_content_not_the_name(): void
    {
        $detector = new MimeTypeDetector(new MimeTypes());

        self::assertSame('image/png', $detector->detect($this->temporaryFile('photo.pdf', self::png())));
        self::assertSame('application/pdf', $detector->detect($this->temporaryFile('logo.png', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n")));
        self::assertSame('text/plain', $detector->detect($this->temporaryFile('holiday.jpg', "Just some words, not a picture at all.\n")));
    }

    /**
     * @test
     *
     * @dataProvider svgs
     */
    public function it_recognises_an_svg_by_its_root_element(string $svg): void
    {
        self::assertSame('image/svg+xml', (new MimeTypeDetector(new MimeTypes()))->detect($this->temporaryFile('logo.txt', $svg)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function svgs(): iterable
    {
        yield 'with an XML declaration' => ['<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>'];
        yield 'without one' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'];
        yield 'after a comment and a doctype' => ["<!-- Made by hand -->\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n<svg xmlns=\"http://www.w3.org/2000/svg\"><rect width=\"10\" height=\"10\"/></svg>"];
    }

    /**
     * @test
     */
    public function an_html_page_named_svg_is_not_an_svg(): void
    {
        $detector = new MimeTypeDetector(new MimeTypes());

        self::assertNotSame('image/svg+xml', $detector->detect($this->temporaryFile('logo.svg', '<!DOCTYPE html><html><body><svg xmlns="http://www.w3.org/2000/svg"></svg></body></html>')));
    }

    /**
     * @test
     */
    public function it_names_the_extension_of_a_mime_type(): void
    {
        $detector = new MimeTypeDetector(new MimeTypes());

        self::assertSame('jpg', $detector->extension('image/jpeg'));
        self::assertSame('svg', $detector->extension('image/svg+xml'));
        self::assertSame('pdf', $detector->extension('application/pdf'));
        self::assertSame('mp4', $detector->extension('video/mp4'));
        self::assertSame('bin', $detector->extension('application/x-made-up'));
    }

    private static function png(): string
    {
        $image = imagecreatetruecolor(4, 3);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
