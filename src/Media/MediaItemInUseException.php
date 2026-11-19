<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;

/**
 * A media item can't be deleted while the usage index lists a use
 */
final class MediaItemInUseException extends \RuntimeException
{
    /**
     * @param non-empty-list<MediaUsageInterface> $uses where it's used
     */
    public function __construct(public readonly string $uuid, public readonly array $uses)
    {
        parent::__construct(sprintf('The media item %s is used in %d entries and editor presets, so it can\'t be deleted', $uuid, count($uses)));
    }
}
