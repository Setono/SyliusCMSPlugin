<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Media\MediaFileReplacerInterface;
use Setono\SyliusCMSPlugin\Media\PermanentUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Documents and videos have a permanent URL, /media/{uuid}/{filename}, which redirects to the media item's current
 * file, so links outside the shop survive a replace. Only the UUID is looked up. See "Permanent URLs" in
 * docs/spec/media.md
 */
final class PermanentUrlTest extends WebTestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_gives_a_document_a_permanent_url_outside_the_locale_prefix_with_a_filename_from_its_original_filename(): void
    {
        self::createClient();
        $document = $this->uploadMediaItem('Size Guide (EU).PDF', self::pdf());

        self::assertSame(
            sprintf('http://localhost/media/%s/size-guide-eu.pdf', $document->getUuid()),
            self::permanentUrls()->generate($document),
        );
    }

    /**
     * @test
     */
    public function it_redirects_a_document_s_permanent_url_temporarily_to_its_current_file(): void
    {
        $client = self::createClient();
        $document = $this->uploadMediaItem('size-guide.pdf', self::pdf());

        $client->request('GET', (string) self::permanentUrls()->generate($document));

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/media/cms/' . $document->getPath());
        self::assertFileExists(self::mediaDirectory() . '/' . $document->getPath());
    }

    /**
     * @test
     */
    public function it_redirects_a_video_s_permanent_url_to_its_current_file(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());

        $client->request('GET', sprintf('/media/%s/shop-tour.mp4', $video->getUuid()));

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/media/cms/' . $video->getPath());
    }

    /**
     * @test
     */
    public function it_looks_up_only_the_uuid_so_any_filename_answers(): void
    {
        $client = self::createClient();
        $document = $this->uploadMediaItem('size-guide.pdf', self::pdf());

        $client->request('GET', sprintf('/media/%s/an-old-name.pdf', $document->getUuid()));

        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/media/cms/' . $document->getPath());
    }

    /**
     * @test
     */
    public function it_redirects_to_the_new_file_after_a_replace_and_names_it_after_the_new_original_filename(): void
    {
        $client = self::createClient();
        $document = $this->uploadMediaItem('size-guide-2026.pdf', self::pdf('2026'));
        $oldUrl = (string) self::permanentUrls()->generate($document);
        $oldPath = $document->getPath();

        /** @var MediaFileReplacerInterface $replacer */
        $replacer = self::getContainer()->get(MediaFileReplacerInterface::class);
        $replacer->replace($document, new \SplFileInfo($this->uploadedFile('size-guide-2027.pdf', self::pdf('2027'))->getPathname()), 'size-guide-2027.pdf');

        $replaced = self::reloaded($document);
        self::assertNotSame($oldPath, $replaced->getPath());

        $client->request('GET', $oldUrl);
        self::assertResponseStatusCodeSame(302);
        self::assertResponseRedirects('/media/cms/' . $replaced->getPath());
        self::assertSame(sprintf('http://localhost/media/%s/size-guide-2027.pdf', $document->getUuid()), self::permanentUrls()->generate($replaced));
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_unknown_uuid(): void
    {
        $client = self::createClient();

        $client->request('GET', '/media/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f/size-guide.pdf');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_gives_an_image_no_permanent_url(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.png', self::png());

        self::assertNull(self::permanentUrls()->generate($image));

        $client->request('GET', sprintf('/media/%s/summer-sale.png', $image->getUuid()));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_never_catches_sylius_s_images_or_liip_s_cache_under_media(): void
    {
        self::createClient();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame('setono_sylius_cms_media', $router->match('/media/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f/size-guide.pdf')['_route']);
        self::assertSame('liip_imagine_filter', $router->match('/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/summer.jpg')['_route']);

        foreach (['/media/image/ab/cd/summer.jpg', '/media/image/summer.jpg', '/media/cms/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', '/media/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f/ab/summer.jpg'] as $path) {
            try {
                $route = $router->match($path)['_route'] ?? null;
                self::fail(sprintf('%s should be left to the web server, but the route %s takes it', $path, is_string($route) ? $route : '?'));
            } catch (ResourceNotFoundException) {
                // The web server serves it as a file
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @test
     */
    public function it_leaves_sylius_s_product_images_to_liip(): void
    {
        $client = self::createClient();
        $directory = dirname(self::mediaDirectory()) . '/image/cms-test';
        $cached = dirname(self::mediaDirectory()) . '/cache/sylius_shop_product_thumbnail/cms-test';
        @mkdir($directory, 0777, true);
        file_put_contents($directory . '/shirt.jpg', self::jpeg(400, 400));

        try {
            $client->request('GET', '/media/cache/resolve/sylius_shop_product_thumbnail/cms-test/shirt.jpg');

            self::assertResponseRedirects('http://localhost/media/cache/sylius_shop_product_thumbnail/cms-test/shirt.jpg');
            self::assertFileExists($cached . '/shirt.jpg');
        } finally {
            @unlink($directory . '/shirt.jpg');
            @rmdir($directory);
            @unlink($cached . '/shirt.jpg');
            @rmdir($cached);
        }
    }

    private static function permanentUrls(): PermanentUrlGeneratorInterface
    {
        /** @var PermanentUrlGeneratorInterface $permanentUrls */
        $permanentUrls = self::getContainer()->get(PermanentUrlGeneratorInterface::class);

        return $permanentUrls;
    }

    private static function reloaded(MediaItemInterface $mediaItem): MediaItemInterface
    {
        /** @var MediaItemRepositoryInterface $repository */
        $repository = self::getContainer()->get(MediaItemRepositoryInterface::class);
        $reloaded = $repository->findOneByUuid($mediaItem->getUuid());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
