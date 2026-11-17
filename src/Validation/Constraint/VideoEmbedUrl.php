<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * The URL of a YouTube or Vimeo video, which the Video block embeds: a watch page, a share link, an embed URL and the
 * like (Video\VideoEmbed). An empty value passes, so it goes with required when one is needed. See "Video" in
 * docs/spec/blocks.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class VideoEmbedUrl extends Constraint
{
    public string $message = 'setono_sylius_cms.field.not_video_embed_url';

    /**
     * @param list<string>|null $groups
     */
    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
