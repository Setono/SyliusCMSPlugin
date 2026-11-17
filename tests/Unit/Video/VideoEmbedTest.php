<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Video;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Video\VideoEmbed;

final class VideoEmbedTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider youTubeUrls
     */
    public function it_reads_a_youtube_url_as_the_privacy_enhanced_embed_of_its_video(string $url, string $src): void
    {
        $embed = VideoEmbed::fromUrl($url);

        self::assertNotNull($embed);
        self::assertSame(VideoEmbed::YOUTUBE, $embed->provider);
        self::assertSame('dQw4w9WgXcQ', $embed->id);
        self::assertSame($src, $embed->src());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function youTubeUrls(): iterable
    {
        $embed = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

        yield 'a watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'a watch page in a playlist' => ['https://www.youtube.com/watch?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG&v=dQw4w9WgXcQ&index=2', $embed];
        yield 'a watch page without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'a mobile watch page' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'a share link' => ['https://youtu.be/dQw4w9WgXcQ?si=FbXmLx9vB2cQ3zKe', $embed];
        yield 'an embed URL' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', $embed];
        yield 'a privacy-enhanced embed URL' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $embed];
        yield 'a short' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', $embed];
        yield 'a live stream' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=share', $embed];
        yield 'plain http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'no scheme' => ['www.youtube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'spaces around it' => ['  https://youtu.be/dQw4w9WgXcQ ', $embed];
        yield 'a host in capitals' => ['https://WWW.YouTube.com/watch?v=dQw4w9WgXcQ', $embed];
        yield 'a start in seconds' => ['https://youtu.be/dQw4w9WgXcQ?t=90', $embed . '?start=90'];
        yield 'a start in seconds with an s' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s', $embed . '?start=90'];
        yield 'a start in minutes and seconds' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s', $embed . '?start=90'];
        yield 'a start in hours' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1h', $embed . '?start=3600'];
        yield 'an embed URL\'s start' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=42', $embed . '?start=42'];
        yield 'a start at the beginning' => ['https://youtu.be/dQw4w9WgXcQ?t=0', $embed];
        yield 'a start it can\'t read' => ['https://youtu.be/dQw4w9WgXcQ?t=soon', $embed];
    }

    /**
     * @test
     *
     * @dataProvider vimeoUrls
     */
    public function it_reads_a_vimeo_url_as_the_embed_of_its_video_without_tracking(string $url, string $src): void
    {
        $embed = VideoEmbed::fromUrl($url);

        self::assertNotNull($embed);
        self::assertSame(VideoEmbed::VIMEO, $embed->provider);
        self::assertSame('76979871', $embed->id);
        self::assertSame($src, $embed->src());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function vimeoUrls(): iterable
    {
        $embed = 'https://player.vimeo.com/video/76979871';

        yield 'a video page' => ['https://vimeo.com/76979871', $embed . '?dnt=1'];
        yield 'a video page with www' => ['https://www.vimeo.com/76979871/', $embed . '?dnt=1'];
        yield 'an unlisted video\'s page' => ['https://vimeo.com/76979871/8272103f6e', $embed . '?h=8272103f6e&dnt=1'];
        yield 'a channel\'s video' => ['https://vimeo.com/channels/staffpicks/76979871', $embed . '?dnt=1'];
        yield 'a group\'s video' => ['https://vimeo.com/groups/shortfilms/videos/76979871', $embed . '?dnt=1'];
        yield 'a showcase\'s video' => ['https://vimeo.com/showcase/9876543/video/76979871', $embed . '?dnt=1'];
        yield 'a player URL' => ['https://player.vimeo.com/video/76979871', $embed . '?dnt=1'];
        yield 'an unlisted video\'s player URL' => ['https://player.vimeo.com/video/76979871?h=8272103f6e&badge=0', $embed . '?h=8272103f6e&dnt=1'];
        yield 'no scheme' => ['vimeo.com/76979871', $embed . '?dnt=1'];
        yield 'a start' => ['https://vimeo.com/76979871#t=1m5s', $embed . '?dnt=1#t=65s'];
        yield 'a start in seconds' => ['https://vimeo.com/76979871#t=65', $embed . '?dnt=1#t=65s'];
    }

    /**
     * @test
     *
     * @dataProvider otherUrls
     */
    public function it_reads_nothing_from_a_url_that_is_not_a_youtube_or_vimeo_video(string $url): void
    {
        self::assertNull(VideoEmbed::fromUrl($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherUrls(): iterable
    {
        yield 'nothing' => [''];
        yield 'not a URL' => ['a video of our summer sale'];
        yield 'another site' => ['https://example.com/watch?v=dQw4w9WgXcQ'];
        yield 'a site that looks like YouTube' => ['https://youtube.com.example.com/watch?v=dQw4w9WgXcQ'];
        yield 'a site that ends like YouTube' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'];
        yield 'another scheme' => ['javascript://www.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'a YouTube channel' => ['https://www.youtube.com/@setono'];
        yield 'a YouTube watch page without a video' => ['https://www.youtube.com/watch?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG'];
        yield 'a YouTube ID that is too short' => ['https://youtu.be/dQw4w9WgXc'];
        yield 'a YouTube ID with other characters' => ['https://www.youtube.com/watch?v=dQw4w9WgX%22Q'];
        yield 'a Vimeo channel' => ['https://vimeo.com/channels/staffpicks'];
        yield 'a Vimeo user' => ['https://vimeo.com/user12345'];
        yield 'a Vimeo player URL without a video' => ['https://player.vimeo.com/video/'];
    }
}
