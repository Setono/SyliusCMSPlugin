<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Media\FileSize;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * An export zip that unpacks to more than transfer.max_unpacked_size, by its directory before anything is extracted, or
 * by what's extracted, since a zip's headers can lie. It refuses the import, which names the total, or only the limit
 * for a zip whose directory understates what it unpacks to, as extracting stops one byte past it. Its message says the
 * same as its reason, in English, for a log. See "Media files" in docs/spec/transfer.md
 */
final class ExportTooBigException extends \RuntimeException
{
    /**
     * @param TranslatableMessage $reason why, in the messages domain
     * @param string $message the reason in English, with its parameters
     */
    private function __construct(public readonly TranslatableMessage $reason, string $message)
    {
        parent::__construct(strtr($message, $reason->getParameters()));
    }

    /**
     * A zip whose directory says it unpacks to more than the limit
     */
    public static function declared(int $size, int $limit): self
    {
        return new self(
            new TranslatableMessage('setono_sylius_cms.import.zip.too_big', ['%size%' => FileSize::format($size), '%limit%' => FileSize::format($limit)]),
            'The export zip unpacks to %size%, over the import limit of %limit%.',
        );
    }

    /**
     * A zip that turned out to unpack to more than the limit while it was extracted, more than its directory says
     */
    public static function unpacked(int $limit): self
    {
        return new self(
            new TranslatableMessage('setono_sylius_cms.import.zip.over_limit', ['%limit%' => FileSize::format($limit)]),
            'The export zip unpacks to more than the import limit of %limit%, more than its directory says.',
        );
    }
}
