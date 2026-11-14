<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;

/**
 * The contract of the shipped stylesheet. See "Styling" in docs/spec/blocks.md
 */
final class ShopStylesheetTest extends TestCase
{
    /**
     * @test
     */
    public function everything_in_it_sits_in_the_cms_cascade_layer(): void
    {
        $css = self::css();

        self::assertStringStartsWith('@layer cms {', $css);
        self::assertStringEndsWith('}', $css);
        self::assertSame(1, substr_count($css, '@layer'));

        $opening = strpos($css, '{');
        self::assertIsInt($opening);
        self::assertSame(strlen($css) - 1, self::closingBraceOf($css, $opening), 'The layer block closes at the end of the file');
    }

    /**
     * @test
     *
     * @dataProvider provideCustomProperties
     */
    public function it_sets_each_custom_property_to_its_default(string $property, string $default): void
    {
        self::assertMatchesRegularExpression(sprintf('/%s:\s*%s;/', preg_quote($property, '/'), preg_quote($default, '/')), self::css());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideCustomProperties(): iterable
    {
        yield from [
            '--cms-content-width' => ['--cms-content-width', '100%'],
            '--cms-block-spacing' => ['--cms-block-spacing', '2rem'],
            '--cms-gap' => ['--cms-gap', '1rem'],
            '--cms-column-min-width' => ['--cms-column-min-width', '240px'],
            '--cms-grid-item-min-width' => ['--cms-grid-item-min-width', '220px'],
            '--cms-carousel-per-view' => ['--cms-carousel-per-view', '1'],
            '--cms-spacer-small' => ['--cms-spacer-small', '1rem'],
            '--cms-spacer-medium' => ['--cms-spacer-medium', '2rem'],
            '--cms-spacer-large' => ['--cms-spacer-large', '4rem'],
            '--cms-muted-background' => ['--cms-muted-background', '#f4f4f4'],
            '--cms-dark-background' => ['--cms-dark-background', '#1b1c1d'],
            '--cms-dark-color' => ['--cms-dark-color', '#ffffff'],
            '--cms-banner-min-height' => ['--cms-banner-min-height', '360px'],
            '--cms-banner-overlay' => ['--cms-banner-overlay', 'rgba(0, 0, 0, 0.35)'],
            '--cms-button-primary-background' => ['--cms-button-primary-background', '#1b1c1d'],
            '--cms-button-primary-color' => ['--cms-button-primary-color', '#ffffff'],
            '--cms-button-secondary-background' => ['--cms-button-secondary-background', 'transparent'],
            '--cms-button-secondary-color' => ['--cms-button-secondary-color', 'inherit'],
            '--cms-radius' => ['--cms-radius', '0.25rem'],
            '--cms-placeholder-background' => ['--cms-placeholder-background', '#e0e0e0'],
        ];
    }

    /**
     * @test
     */
    public function it_lays_out_the_tree_full_width_blocks_columns_and_spacers(): void
    {
        $css = self::css();

        foreach (['.cms-tree', '.cms-tree > .cms-full-width', '.cms-columns', '.cms-column', '.cms-spacer--small', '.cms-spacer--medium', '.cms-spacer--large'] as $selector) {
            self::assertStringContainsString($selector . ' {', $css);
        }

        self::assertStringContainsString('flex: 1 1 var(--cms-column-min-width);', $css, 'Columns wrap below --cms-column-min-width');
        self::assertStringContainsString('margin-inline: calc(50% - 50vw);', $css);
    }

    /**
     * @test
     */
    public function it_never_styles_html_or_body(): void
    {
        self::assertDoesNotMatchRegularExpression('/(^|[\s,}>~+])(html|body)\b/', self::css());
    }

    /**
     * The stylesheet without its comments, trimmed
     */
    private static function css(): string
    {
        $css = file_get_contents(dirname(__DIR__, 3) . '/src/Resources/public/shop/cms.css');
        self::assertIsString($css);

        return trim((string) preg_replace('#/\*.*?\*/#s', '', $css));
    }

    private static function closingBraceOf(string $css, int $opening): int
    {
        $depth = 0;
        for ($i = $opening, $length = strlen($css); $i < $length; ++$i) {
            if ('{' === $css[$i]) {
                ++$depth;
            } elseif ('}' === $css[$i] && 0 === --$depth) {
                return $i;
            }
        }

        return -1;
    }
}
