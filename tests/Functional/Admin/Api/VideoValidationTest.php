<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Publish checks that a Video block holds exactly one of a video media item and a YouTube or Vimeo URL, and that the
 * URL is a YouTube or Vimeo video's. See "Video" in docs/spec/blocks.md and "Validation" in docs/spec/publishing.md
 */
final class VideoValidationTest extends MediaApiTestCase
{
    private const BOTH = '0193a5be-0000-7000-8000-0000000000f1';

    private const NEITHER = '0193a5be-0000-7000-8000-0000000000f2';

    private const OTHER_SITE = '0193a5be-0000-7000-8000-0000000000f3';

    /**
     * @test
     */
    public function it_refuses_to_publish_a_video_block_with_both_a_video_and_a_url_or_neither(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());
        $page = self::createPageWithBlocks('Tour', 'tour', [
            new StoredBlock(self::BOTH, 'video', 1, fields: ['video' => $video->getUuid(), 'embedUrl' => 'https://youtu.be/dQw4w9WgXcQ']),
            new StoredBlock(self::NEITHER, 'video', 1, fields: ['embedUrl' => '']),
        ]);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        $message = 'Choose a video, or enter a YouTube or Vimeo URL, but not both.';
        self::assertSame(['error' => '2 errors need fixing', 'errors' => [
            ['block' => self::BOTH, 'field' => null, 'locale' => null, 'message' => $message],
            ['block' => self::NEITHER, 'field' => null, 'locale' => null, 'message' => $message],
        ]], $response);
        self::assertFalse(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_url_that_is_not_a_youtube_or_vimeo_video(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Tour', 'tour', [new StoredBlock(self::OTHER_SITE, 'video', 1, fields: ['embedUrl' => 'https://example.com/our-video'])]);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'One error needs fixing', 'errors' => [
            ['block' => self::OTHER_SITE, 'field' => 'embedUrl', 'locale' => null, 'message' => 'Enter the URL of a YouTube or Vimeo video.'],
        ]], $response);
    }

    /**
     * @test
     */
    public function it_publishes_a_video_block_with_a_video_or_a_url(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());
        $page = self::createPageWithBlocks('Tour', 'tour', [
            new StoredBlock(self::BOTH, 'video', 1, fields: ['video' => $video->getUuid()]),
            new StoredBlock(self::NEITHER, 'video', 1, fields: ['embedUrl' => 'https://vimeo.com/76979871']),
        ]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertTrue(self::reloadPage($page)->isPublished());
    }
}
