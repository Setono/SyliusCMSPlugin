<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\MediaItemDeleterInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Image block and the images cms_image() renders: responsive variants that LiipImagine makes on their first request
 * with the plugin's loader and resolver, cropped around the focal point, SVGs as uploaded, and a placeholder for an
 * empty image in the builder's preview only. See "Image" and "Empty images" in docs/spec/blocks.md and "Image
 * processing" in docs/spec/media.md
 */
final class ImageTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BLOCK = '0193a5be-0000-7000-8000-0000000000c1';

    /**
     * @test
     */
    public function it_renders_an_image_block_as_a_figure_with_a_responsive_lazy_image_its_link_and_its_caption(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1200, 800));
        $image->setAltText('en_US', 'A beach at dawn');
        self::flush();
        self::createPageWithBlocks('Summer', 'summer', [self::imageBlock([
            'image' => $image->getUuid(),
            'caption' => ['en_US' => 'Our <summer> sale'],
            'link' => ['target' => 'url', 'ref' => ['en_US' => '/en_US/sale'], 'anchor' => null, 'label' => []],
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        self::assertResponseIsSuccessful();
        $figure = $crawler->filter('.cms-tree > figure.cms-image');
        self::assertCount(1, $figure);
        self::assertSame('/en_US/sale', $figure->filter('a')->attr('href'));
        self::assertNull($figure->filter('a')->attr('aria-label'), 'The image\'s alt text names the link');

        $img = $figure->filter('a img');
        self::assertSame('A beach at dawn', $img->attr('alt'));
        self::assertSame('1200', $img->attr('width'));
        self::assertSame('800', $img->attr('height'));
        self::assertSame('lazy', $img->attr('loading'));
        self::assertSame('async', $img->attr('decoding'));
        self::assertSame('(min-width: 1200px) 1200px, 100vw', $img->attr('sizes'));
        self::assertSame([320, 640, 960, 1200], self::srcsetWidths((string) $img->attr('srcset')));
        self::assertStringStartsWith('/media/cache/resolve/setono_sylius_cms_webp/rc/', (string) $img->attr('src'));
        self::assertStringContainsString('/' . $image->getPath() . '?', (string) $img->attr('src'));

        self::assertSame('Our <summer> sale', $figure->filter('figcaption.cms-image__caption')->text());
    }

    /**
     * @test
     */
    public function it_takes_the_block_s_alt_text_over_the_media_item_s_and_names_the_link_by_its_label(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1200, 800));
        $image->setAltText('en_US', 'A beach at dawn');
        self::flush();
        self::createPageWithBlocks('Summer', 'summer', [self::imageBlock([
            'image' => $image->getUuid(),
            'alt' => ['en_US' => 'Our summer sale'],
            'link' => ['target' => 'url', 'ref' => ['en_US' => '/en_US/sale'], 'anchor' => null, 'label' => ['en_US' => 'Shop the sale']],
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/summer');

        $figure = $crawler->filter('.cms-tree > figure.cms-image');
        self::assertSame('Our summer sale', $figure->filter('img')->attr('alt'));
        self::assertSame('Shop the sale', $figure->filter('a')->attr('aria-label'));
        self::assertCount(0, $figure->filter('figcaption'), 'An empty caption isn\'t rendered');
    }

    /**
     * @test
     */
    public function it_serves_an_svg_as_uploaded_without_a_srcset(): void
    {
        $client = self::createClient();
        $logo = $this->uploadMediaItem('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40"><rect width="120" height="40" fill="#1b1c1d"/></svg>');
        self::createPageWithBlocks('About us', 'about-us', [self::imageBlock(['image' => $logo->getUuid()])], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        $img = $crawler->filter('.cms-tree > figure.cms-image img');
        self::assertSame('/media/cms/' . $logo->getPath(), $img->attr('src'));
        self::assertNull($img->attr('srcset'));
        self::assertCount(0, $crawler->filter('.cms-tree picture'));
    }

    /**
     * @test
     */
    public function it_renders_nothing_for_an_image_block_without_an_image_in_the_shop_and_a_placeholder_in_the_builder_s_preview(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Summer', 'summer', [self::imageBlock(['caption' => ['en_US' => 'Our summer sale']])], published: true);

        $client->request('GET', '/en_US/summer');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cms-tree .cms-image');
        self::assertSelectorNotExists('.cms-image-placeholder');

        $crawler = $client->request('GET', '/en_US/summer?_cms_preview=' . self::previewToken($page));
        $placeholder = $crawler->filter('.cms-tree > figure.cms-image > .cms-image-placeholder');
        self::assertCount(1, $placeholder);
        self::assertSame('aspect-ratio: 1.7778', $placeholder->attr('style'));
        self::assertSame('Our summer sale', $crawler->filter('.cms-tree .cms-image__caption')->text());
    }

    /**
     * @test
     */
    public function it_makes_a_webp_variant_at_the_width_and_crop_of_its_url_on_its_first_request_around_the_focal_point(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.png', self::redLeftBlueRight());
        $image->setFocalPoint(new FocalPoint(0.9, 0.5));
        self::flush();

        $url = self::variantUrl($image, new ImageTransform(100, 1.0, ImageFormat::Webp, $image->getFocalPoint()));
        $client->request('GET', $url);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('~^/media/cms/cache/setono_sylius_cms_webp/' . preg_quote($image->getPath(), '~') . '/[^/]+/summer-sale\.webp$~', $location);

        $variant = self::mediaDirectory() . substr($location, strlen('/media/cms'));
        self::assertFileExists($variant);
        $info = getimagesize($variant);
        self::assertIsArray($info);
        self::assertSame('image/webp', $info['mime']);
        self::assertSame([100, 100], [$info[0], $info[1]]);
        $pixels = imagecreatefromwebp($variant);
        self::assertNotFalse($pixels);
        $colour = imagecolorsforindex($pixels, (int) imagecolorat($pixels, 50, 50));
        self::assertGreaterThan(200, $colour['blue'], 'The square is cropped from the right, around the focal point');
        self::assertLessThan(50, $colour['red']);

        // The next request finds the variant stored
        $client->request('GET', $url);
        self::assertResponseRedirects($location);
    }

    /**
     * @test
     */
    public function it_deletes_a_media_item_s_variants_with_its_file(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.png', self::redLeftBlueRight());
        $client->request('GET', self::variantUrl($image, new ImageTransform(100, null, ImageFormat::Webp, $image->getFocalPoint())));
        $variant = self::mediaDirectory() . substr((string) $client->getResponse()->headers->get('Location'), strlen('/media/cms'));
        self::assertFileExists($variant);

        /** @var MediaItemDeleterInterface $deleter */
        $deleter = self::getContainer()->get(MediaItemDeleterInterface::class);
        $deleter->delete($image);

        self::assertFileDoesNotExist($variant);
        self::assertDirectoryDoesNotExist(self::mediaDirectory() . '/cache/setono_sylius_cms_webp/' . $image->getPath());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function imageBlock(array $fields): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'image', 1, fields: $fields);
    }

    private static function variantUrl(MediaItemInterface $image, ImageTransform $transform): string
    {
        /** @var ImageProviderInterface $provider */
        $provider = self::getContainer()->get(ImageProviderInterface::class);

        return $provider->url($image, $transform);
    }

    /**
     * The widths of a srcset's candidates
     *
     * @return list<int>
     */
    private static function srcsetWidths(string $srcset): array
    {
        preg_match_all('~ (\d+)w(?:,|$)~', $srcset, $matches);

        return array_map(intval(...), $matches[1]);
    }

    /**
     * A PNG of 400 by 200 pixels, red on the left half and blue on the right
     */
    private static function redLeftBlueRight(): string
    {
        $image = imagecreatetruecolor(400, 200);
        imagefilledrectangle($image, 0, 0, 199, 199, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 200, 0, 399, 199, (int) imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private static function flush(): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->flush();
    }
}
