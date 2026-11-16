<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Image;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Image\SvgSanitizer;
use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Media items are public, so an SVG is sanitised on upload: it could otherwise run scripts on the shop's own origin.
 * See "What the library holds" in docs/spec/media.md
 */
final class SvgSanitizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_removes_scripts_event_handlers_and_javascript_links(): void
    {
        $svg = (new SvgSanitizer())->sanitize(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="50" onload="alert('onload')">
                <script>alert('script')</script>
                <rect width="100" height="50" fill="#c00" onclick="alert('onclick')"/>
                <a xlink:href="javascript:alert('link')"><text x="10" y="30">Sale</text></a>
                <foreignObject width="100" height="50"><iframe xmlns="http://www.w3.org/1999/xhtml" src="https://evil.example"/></foreignObject>
            </svg>
            SVG);

        self::assertStringNotContainsString('alert', $svg);
        self::assertStringNotContainsString('<script', $svg);
        self::assertStringNotContainsString('onload', $svg);
        self::assertStringNotContainsString('onclick', $svg);
        self::assertStringNotContainsString('javascript:', $svg);
        self::assertStringNotContainsString('iframe', $svg);
        self::assertStringNotContainsString('evil.example', $svg);

        // What it draws stays
        self::assertStringContainsString('<rect width="100" height="50" fill="#c00"', $svg);
        self::assertStringContainsString('Sale', $svg);
    }

    /**
     * @test
     */
    public function it_removes_references_to_files_elsewhere(): void
    {
        $svg = (new SvgSanitizer())->sanitize('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="https://tracker.example/pixel.png" width="1" height="1"/></svg>');

        self::assertStringNotContainsString('tracker.example', $svg);
    }

    /**
     * @test
     */
    public function it_refuses_what_is_not_xml(): void
    {
        $this->expectException(UnreadableFileException::class);

        (new SvgSanitizer())->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect></svg');
    }

    /**
     * @test
     *
     * @dataProvider dimensions
     */
    public function it_reads_the_dimensions_from_the_width_and_height_or_else_the_view_box(string $svg, ?array $dimensions): void
    {
        self::assertSame($dimensions, (new SvgSanitizer())->dimensions($svg));
    }

    /**
     * @return iterable<string, array{string, array{int, int}|null}>
     */
    public static function dimensions(): iterable
    {
        yield 'width and height' => ['<svg xmlns="http://www.w3.org/2000/svg" width="120" height="80"/>', [120, 80]];
        yield 'in pixels, with decimals' => ['<svg xmlns="http://www.w3.org/2000/svg" width="120.4px" height="79.6px"/>', [120, 80]];
        yield 'the view box' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"/>', [300, 150]];
        yield 'the view box, with relative width and height' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0,0,64,32"/>', [64, 32]];
        yield 'neither' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', null];
    }
}
