<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What the link control's search picker finds for the media target: the documents and videos of the media library,
 * which have permanent URLs. See "JSON endpoints" in docs/spec/builder.md and "Link targets" in docs/spec/extending.md
 */
final class MediaSearchTest extends MediaApiTestCase
{
    /**
     * @test
     */
    public function it_finds_documents_and_videos_by_name_or_original_filename_and_no_images(): void
    {
        $client = self::signedIn();
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf('size guide'));
        $tour = $this->uploadMediaItem('shop-tour-size-matters.mp4', self::mp4());
        $this->uploadMediaItem('size-chart.png', self::png());
        $this->uploadMediaItem('returns.pdf', self::pdf('returns'));

        $found = (array) self::json($client, 'GET', '/admin/cms/api/search/media?q=SIZE');

        self::assertEqualsCanonicalizing([
            ['ref' => $sizeGuide->getUuid(), 'name' => 'size-guide', 'thumbnail' => null],
            ['ref' => $tour->getUuid(), 'name' => 'shop-tour-size-matters', 'thumbnail' => null],
        ], $found);
    }

    /**
     * @test
     */
    public function it_looks_documents_and_videos_up_by_uuid(): void
    {
        $client = self::signedIn();
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        $image = $this->uploadMediaItem('size-chart.png', self::png());

        self::assertSame(
            [['ref' => $sizeGuide->getUuid(), 'name' => 'size-guide', 'thumbnail' => null]],
            self::json($client, 'GET', sprintf('/admin/cms/api/search/media?refs=%s,%s,0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f,size-guide', strtoupper($sizeGuide->getUuid()), $image->getUuid())),
        );
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }
}
