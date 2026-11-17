<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Video block: a video media item from its current file with a poster, or a YouTube or Vimeo URL as the provider's
 * embed, in a 16:9 frame. See "Video" in docs/spec/blocks.md
 */
final class VideoTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BLOCK = '0193a5be-0000-7000-8000-0000000000e1';

    /**
     * @test
     */
    public function it_renders_a_youtube_url_as_its_privacy_enhanced_embed_in_a_16_9_frame(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Our story', 'our-story', [self::video(['embedUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])], published: true);

        $crawler = $client->request('GET', '/en_US/our-story');

        self::assertResponseIsSuccessful();
        $iframe = $crawler->filter('.cms-tree > div.cms-video > div.cms-video__frame > iframe');
        self::assertCount(1, $iframe);
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $iframe->attr('src'));
        self::assertSame('Video', $iframe->attr('title'));
        self::assertSame('lazy', $iframe->attr('loading'));
        self::assertNotNull($iframe->attr('allowfullscreen'));
    }

    /**
     * @test
     */
    public function it_renders_a_video_media_item_from_its_current_file_with_its_own_poster_when_the_block_has_none(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());
        $poster = $this->uploadMediaItem('tour-poster.jpg', self::jpeg(1600, 900));
        $video->setPoster($poster);
        self::flush();
        self::createPageWithBlocks('Tour', 'tour', [self::video(['video' => $video->getUuid()])], published: true);

        $crawler = $client->request('GET', '/en_US/tour');

        $element = $crawler->filter('.cms-tree > .cms-video > .cms-video__frame > video');
        self::assertCount(1, $element);
        self::assertSame('/media/cms/' . $video->getPath(), $element->attr('src'));
        self::assertStringStartsWith('/media/cache/resolve/setono_sylius_cms_webp/rc/', (string) $element->attr('poster'));
        self::assertStringContainsString('/' . $poster->getPath() . '?', (string) $element->attr('poster'));
        self::assertNotNull($element->attr('controls'));
    }

    /**
     * @test
     */
    public function it_takes_the_block_s_poster_over_the_video_s_own(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());
        $video->setPoster($this->uploadMediaItem('tour-poster.jpg', self::jpeg(1600, 900)));
        self::flush();
        $poster = $this->uploadMediaItem('summer-poster.jpg', self::jpeg(1600, 900, [200, 30, 30]));
        self::createPageWithBlocks('Tour', 'tour', [self::video(['video' => $video->getUuid(), 'poster' => $poster->getUuid()])], published: true);

        $crawler = $client->request('GET', '/en_US/tour');

        self::assertStringContainsString('/' . $poster->getPath() . '?', (string) $crawler->filter('.cms-tree .cms-video video')->attr('poster'));
    }

    /**
     * @test
     */
    public function it_renders_nothing_without_a_video_in_the_shop_and_a_placeholder_in_the_builder_s_preview(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Tour', 'tour', [self::video(['embedUrl' => 'https://example.com/our-video'])], published: true);

        $client->request('GET', '/en_US/tour');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cms-tree .cms-video');

        $crawler = $client->request('GET', '/en_US/tour?_cms_preview=' . self::previewToken($page));
        self::assertCount(1, $crawler->filter('.cms-tree > .cms-video > .cms-video__frame > .cms-image-placeholder'));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function video(array $fields): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'video', 1, fields: $fields);
    }

    private static function flush(): void
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->flush();
    }
}
