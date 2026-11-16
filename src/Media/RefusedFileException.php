<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A file the media library won't take, with the message editors see: it names the upload limit, and for a type it
 * doesn't take, the types it does. See "Uploading" in docs/spec/media.md
 */
final class RefusedFileException extends \RuntimeException
{
    public function __construct(public readonly TranslatableMessage $reason, ?\Throwable $previous = null)
    {
        parent::__construct(strtr($reason->getMessage(), $reason->getParameters()), previous: $previous);
    }

    public static function tooBig(string $filename, ?int $size, int $limit): self
    {
        return null === $size
            ? new self(new TranslatableMessage('setono_sylius_cms.media.refused.over_limit', ['%filename%' => $filename, '%limit%' => FileSize::format($limit)]))
            : new self(new TranslatableMessage('setono_sylius_cms.media.refused.too_big', ['%filename%' => $filename, '%size%' => FileSize::format($size), '%limit%' => FileSize::format($limit)]));
    }

    /**
     * @param list<string> $allowed what editors call the types it takes
     */
    public static function notAllowed(string $filename, string $type, array $allowed, int $limit): self
    {
        return new self(new TranslatableMessage('setono_sylius_cms.media.refused.not_allowed', [
            '%filename%' => $filename,
            '%type%' => $type,
            '%types%' => implode(', ', $allowed),
            '%limit%' => FileSize::format($limit),
        ]));
    }

    public static function unreadable(string $filename, string $type, \Throwable $previous): self
    {
        return new self(new TranslatableMessage('setono_sylius_cms.media.refused.unreadable', ['%filename%' => $filename, '%type%' => $type]), $previous);
    }

    public static function notUploaded(string $filename): self
    {
        return new self(new TranslatableMessage('setono_sylius_cms.media.refused.not_uploaded', ['%filename%' => $filename]));
    }
}
