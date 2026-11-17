<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Banner block: an image with an optional mobile image for narrow screens, in a <picture>, and a heading, text and
 * Button children over it. Full width, it breaks out of the shop's container. See "Banner" and "Class names" in
 * docs/spec/blocks.md
 */
final class BannerTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BANNER = '0193a5be-0000-7000-8000-0000000000d1';

    private const BUTTON = '0193a5be-0000-7000-8000-0000000000d2';

    /** The media query the mobile image shows for: below the shop's mobile breakpoint, 48rem */
    private const NARROW = '(max-width: 47.99rem)';

    /**
     * @test
     */
    public function it_renders_a_banner_with_its_image_in_a_picture_with_the_mobile_image_as_a_narrow_source_and_its_heading_text_and_buttons_over_it(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        $mobileImage = $this->uploadMediaItem('summer-sale-mobile.jpg', self::jpeg(600, 800));
        self::createPageWithBlocks('Summer', 'summer', [self::banner([
            'image' => $image->getUuid(),
            'mobileImage' => $mobileImage->getUuid(),
            'alt' => ['en_US' => 'A beach at dawn'],
            'heading' => ['en_US' => 'Summer <sale>'],
            'text' => ['en_US' => self::document('Up to ', '50%', ' off.')],
        ], [self::button('Shop now', '/en_US/sale')])], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        self::assertResponseIsSuccessful();
        $banner = $crawler->filter('.cms-tree > .cms-banner');
        self::assertSame('cms-banner', $banner->attr('class'));
        self::assertSame(['picture', 'div'], $banner->children()->each(static fn (Crawler $part): string => $part->nodeName()));

        $picture = $banner->filter('picture.cms-banner__media');
        // Each narrow source, AVIF where the server writes it and then WebP, offers the mobile image at its own size
        $narrow = $picture->filter(sprintf('source[media="%s"]', self::NARROW));
        self::assertGreaterThanOrEqual(1, $narrow->count());
        $narrow->each(static function (Crawler $source) use ($mobileImage): void {
            self::assertStringContainsString('/' . $mobileImage->getPath() . '?', (string) $source->attr('srcset'));
            self::assertSame(['100vw', '600', '800'], [$source->attr('sizes'), $source->attr('width'), $source->attr('height')]);
        });
        self::assertCount(0, $picture->filter('source:not([media])')->reduce(static fn (Crawler $source): bool => str_contains((string) $source->attr('srcset'), $mobileImage->getPath())));

        $img = $picture->filter('img');
        self::assertSame('A beach at dawn', $img->attr('alt'));
        self::assertSame('100vw', $img->attr('sizes'));
        self::assertSame(['1600', '800'], [$img->attr('width'), $img->attr('height')]);
        self::assertStringContainsString('/' . $image->getPath() . '?', (string) $img->attr('srcset'));

        self::assertSame(
            '<div class="cms-banner__content">'
            . '<h2 class="cms-banner__heading">Summer &lt;sale&gt;</h2>'
            . '<div class="cms-banner__text"><p>Up to <strong>50%</strong> off.</p></div>'
            . '<div class="cms-banner__buttons"><a class="cms-button cms-button--primary" href="/en_US/sale">Shop now</a></div>'
            . '</div>',
            Html::withoutWhitespaceBetweenTags($banner->filter('.cms-banner__content')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_renders_the_picture_without_a_narrow_source_when_the_banner_has_no_mobile_image(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        self::createPageWithBlocks('Summer', 'summer', [self::banner(['image' => $image->getUuid(), 'heading' => ['en_US' => 'Summer sale']])], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        $picture = $crawler->filter('.cms-tree > .cms-banner > picture.cms-banner__media');
        self::assertCount(1, $picture);
        self::assertCount(0, $picture->filter('source[media]'));
        self::assertStringContainsString('/' . $image->getPath() . '?', (string) $picture->filter('img')->attr('srcset'));
        self::assertSame('', $picture->filter('img')->attr('alt'), 'The media item has no alt text');
    }

    /**
     * @test
     */
    public function it_marks_a_full_width_banner_full_width_directly_inside_the_tree(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        self::createPageWithBlocks('Summer', 'summer', [self::banner(['image' => $image->getUuid(), 'fullWidth' => true], [self::button('Shop now', '/en_US/sale')])], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        self::assertSame('cms-banner cms-full-width', $crawler->filter('.cms-tree > .cms-banner')->attr('class'));
        self::assertSelectorExists('.cms-tree > .cms-banner.cms-full-width > picture.cms-banner__media');
    }

    /**
     * A banner's image loads lazily, unless the banner is set to load first, as a hero banner at the top of a page is
     *
     * @test
     */
    public function it_loads_a_banner_s_image_first_only_when_it_is_set_to_load_first(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        self::createPageWithBlocks('Summer', 'summer', [
            self::banner(['image' => $image->getUuid(), 'priority' => true]),
            self::banner(['image' => $image->getUuid()], uuid: '0193a5be-0000-7000-8000-0000000000d3'),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        $images = $crawler->filter('.cms-tree > .cms-banner > picture.cms-banner__media > img');
        self::assertCount(2, $images);
        self::assertSame(['high', null, null], [$images->eq(0)->attr('fetchpriority'), $images->eq(0)->attr('loading'), $images->eq(0)->attr('decoding')]);
        self::assertSame([null, 'lazy', 'async'], [$images->eq(1)->attr('fetchpriority'), $images->eq(1)->attr('loading'), $images->eq(1)->attr('decoding')]);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_parts_a_banner_has_nothing_for(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        self::createPageWithBlocks('Summer', 'summer', [
            self::banner(['heading' => ['en_US' => 'Summer sale']]),
            new StoredBlock('0193a5be-0000-7000-8000-0000000000d3', 'banner', 1, fields: ['image' => $image->getUuid()]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        $banners = $crawler->filter('.cms-tree > .cms-banner');
        self::assertSame(
            '<div class="cms-banner"><div class="cms-banner__content"><h2 class="cms-banner__heading">Summer sale</h2></div></div>',
            Html::withoutWhitespaceBetweenTags($banners->eq(0)->outerHtml()),
            'Without an image, the shop gets no media, and without text or buttons, no text or buttons',
        );
        self::assertSame(['picture'], $banners->eq(1)->children()->each(static fn (Crawler $part): string => $part->nodeName()), 'Without a heading, text or buttons, there is no content over the image');
    }

    /**
     * @test
     */
    public function it_renders_a_placeholder_for_a_banner_s_empty_image_in_the_builder_s_preview(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Summer', 'summer', [self::banner(['heading' => ['en_US' => 'Summer sale']])], published: true);

        $crawler = $client->request('GET', '/en_US/summer?_cms_preview=' . self::previewToken($page));

        $placeholder = $crawler->filter('.cms-tree > .cms-banner > span.cms-image-placeholder.cms-banner__media');
        self::assertCount(1, $placeholder);
        self::assertSame('aspect-ratio: 1.7778', $placeholder->attr('style'));
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<StoredBlock> $buttons
     */
    private static function banner(array $fields, array $buttons = [], string $uuid = self::BANNER): StoredBlock
    {
        return new StoredBlock($uuid, 'banner', 1, fields: $fields, children: $buttons);
    }

    private static function button(string $label, string $url): StoredBlock
    {
        return new StoredBlock(self::BUTTON, 'button', 1, style: 'primary', fields: [
            'link' => ['target' => 'url', 'ref' => ['en_US' => $url], 'anchor' => null, 'label' => ['en_US' => $label]],
        ]);
    }

    /**
     * A rich text document with one paragraph, whose middle part is bold
     *
     * @return array<string, mixed>
     */
    private static function document(string $before, string $bold, string $after): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => $before],
            ['type' => 'text', 'text' => $bold, 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => $after],
        ]]]];
    }
}
