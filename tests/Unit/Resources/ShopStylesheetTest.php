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
    public function it_puts_everything_in_the_cms_cascade_layer(): void
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
            '--cms-banner-color' => ['--cms-banner-color', '#ffffff'],
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
    public function it_spaces_a_slot_s_blocks_like_a_tree_s_without_containing_them(): void
    {
        $slot = self::declarations('.cms-slot');

        self::assertSame('grid', $slot['display'] ?? null);
        self::assertSame('var(--cms-block-spacing)', $slot['row-gap'] ?? null);
        self::assertArrayNotHasKey('max-width', $slot);
        self::assertStringNotContainsString('.cms-slot >', self::css(), 'Nothing contains its blocks to --cms-content-width');
    }

    /**
     * @test
     */
    public function it_gives_a_group_a_background_from_its_styles_custom_properties(): void
    {
        self::assertSame(['background' => 'var(--cms-muted-background)'], array_intersect_key(self::declarations('.cms-group--muted'), ['background' => true, 'color' => true]));
        self::assertSame(['background' => 'var(--cms-dark-background)', 'color' => 'var(--cms-dark-color)'], array_intersect_key(self::declarations('.cms-group--dark'), ['background' => true, 'color' => true]));
    }

    /**
     * @test
     */
    public function it_gives_a_group_with_no_style_no_background(): void
    {
        foreach (['.cms-group', '.cms-group__inner'] as $selector) {
            self::assertArrayNotHasKey('background', self::declarations($selector), $selector);
            self::assertArrayNotHasKey('background-color', self::declarations($selector), $selector);
        }
    }

    /**
     * @test
     */
    public function it_sets_the_slides_per_view_on_each_carousel_from_data_cms_per_view(): void
    {
        $wide = self::withoutMedia();
        self::assertSame('1', self::declarations('.cms-carousel', $wide)['--cms-carousel-per-view'] ?? null);
        foreach (['2', '3', '4'] as $perView) {
            self::assertSame($perView, self::declarations(sprintf('.cms-carousel[data-cms-per-view="%s"]', $perView), $wide)['--cms-carousel-per-view'] ?? null);
        }
    }

    /**
     * @test
     */
    public function it_shows_at_most_2_slides_below_48rem_and_1_below_30rem(): void
    {
        $narrow = self::media('(width < 48rem)');
        self::assertSame('2', self::declarations('.cms-carousel[data-cms-per-view="3"]', $narrow)['--cms-carousel-per-view'] ?? null);
        self::assertSame('2', self::declarations('.cms-carousel[data-cms-per-view="4"]', $narrow)['--cms-carousel-per-view'] ?? null);

        self::assertSame('1', self::declarations('.cms-carousel[data-cms-per-view]', self::media('(width < 30rem)'))['--cms-carousel-per-view'] ?? null);
        self::assertGreaterThan(strpos(self::css(), '@media (width < 48rem)'), strpos(self::css(), '@media (width < 30rem)'), 'The narrowest screens come last, so they win');
    }

    /**
     * @test
     */
    public function it_scrolls_a_carousel_s_track_with_scroll_snap_and_sizes_its_slides_by_the_slides_per_view(): void
    {
        $track = self::declarations('.cms-carousel__track');
        self::assertSame('auto', $track['overflow-x'] ?? null);
        self::assertSame('x mandatory', $track['scroll-snap-type'] ?? null);
        self::assertSame('calc((100% - (var(--cms-carousel-per-view) - 1) * var(--cms-gap)) / var(--cms-carousel-per-view))', $track['grid-auto-columns'] ?? null);
        self::assertSame('start', self::declarations('.cms-carousel__slide')['scroll-snap-align'] ?? null);
    }

    /**
     * @test
     */
    public function it_makes_a_banner_at_least_its_min_height_with_its_image_covering_it_and_its_content_over_it(): void
    {
        self::assertSame('var(--cms-banner-min-height)', self::declarations('.cms-banner')['min-height'] ?? null);

        // The media and the content share the banner's one grid cell, so the content sits over the image
        self::assertSame('1 / 1', self::declarations('.cms-banner__media')['grid-area'] ?? null);
        self::assertSame('1 / 1', self::declarations('.cms-banner__content')['grid-area'] ?? null);
        self::assertSame('stretch', self::declarations('.cms-banner__media')['align-self'] ?? null, 'The media fills a banner its content or min height makes taller');
        self::assertSame(['width' => '100%', 'height' => '100%', 'object-fit' => 'cover'], array_intersect_key(self::declarations('.cms-banner__media img'), ['width' => true, 'height' => true, 'object-fit' => true]));
    }

    /**
     * @test
     */
    public function it_shades_a_banner_s_image_behind_its_content_with_the_overlay_in_the_banner_s_colour(): void
    {
        self::assertSame('var(--cms-banner-overlay)', self::declarations('.cms-banner__media ~ .cms-banner__content')['background'] ?? null);
        self::assertSame('var(--cms-banner-color)', self::declarations('.cms-banner__media ~ .cms-banner__content')['color'] ?? null);
        self::assertArrayNotHasKey('background', self::declarations('.cms-banner__content'), 'A banner without an image has no shade');
        self::assertArrayNotHasKey('color', self::declarations('.cms-banner__content'), 'A banner without an image has the shop\'s colours');
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

    /**
     * The stylesheet without its media queries
     */
    private static function withoutMedia(): string
    {
        $css = self::css();
        while (false !== $start = strpos($css, '@media')) {
            $css = substr($css, 0, $start) . substr($css, self::closingBraceOf($css, (int) strpos($css, '{', $start)) + 1);
        }

        return $css;
    }

    /**
     * The rules inside the media query, such as "(width < 48rem)"
     */
    private static function media(string $query): string
    {
        $css = self::css();
        $start = strpos($css, sprintf('@media %s {', $query));
        self::assertIsInt($start, sprintf('The stylesheet has a @media %s', $query));
        $opening = (int) strpos($css, '{', $start);

        return substr($css, $opening + 1, self::closingBraceOf($css, $opening) - $opening - 1);
    }

    /**
     * The declarations of every rule whose selector list has the selector, merged in order, in the whole stylesheet,
     * whose media queries count as if they always applied, or in the CSS given
     *
     * @return array<string, string> property => value
     */
    private static function declarations(string $selector, ?string $css = null): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css ?? self::css(), $rules, \PREG_SET_ORDER);

        $declarations = [];
        foreach ($rules as [, $selectors, $body]) {
            if (!in_array($selector, array_map(trim(...), explode(',', $selectors)), true)) {
                continue;
            }

            foreach (array_filter(array_map(trim(...), explode(';', $body)), static fn (string $declaration): bool => '' !== $declaration) as $declaration) {
                [$property, $value] = array_map(trim(...), explode(':', $declaration, 2)) + [1 => ''];
                $declarations[$property] = $value;
            }
        }

        return $declarations;
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
