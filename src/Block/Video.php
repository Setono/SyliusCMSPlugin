<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Validation\Constraint\ExactlyOneOf;
use Setono\SyliusCMSPlugin\Validation\Constraint\VideoEmbedUrl;

/**
 * A video in a 16:9 frame: a video media item, or a YouTube or Vimeo video by its URL, since those aren't media items.
 * Publish checks that exactly one of them is set. The poster shows before a video media item plays, and falls back to
 * the media item's own. See "Video" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'video',
    label: 'setono_sylius_cms.block_type.video',
    template: '@SetonoSyliusCMSPlugin/block/video.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/video.svg',
)]
#[ExactlyOneOf(fields: ['video', 'embedUrl'], message: 'setono_sylius_cms.block.video_or_embed_url')]
final class Video
{
    #[Media(label: 'setono_sylius_cms.field.video.video', kinds: ['video'])]
    public ?MediaItemInterface $video = null;

    #[Text(label: 'setono_sylius_cms.field.video.embed_url', help: 'setono_sylius_cms.field.video.embed_url_help', translatable: false)]
    #[VideoEmbedUrl]
    public ?string $embedUrl = null;

    /** Shown before a video media item plays. Empty, it falls back to the media item's own poster */
    #[Media(label: 'setono_sylius_cms.field.video.poster', help: 'setono_sylius_cms.field.video.poster_help')]
    public ?MediaItemInterface $poster = null;
}
