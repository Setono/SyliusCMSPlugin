<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * A change to a media item or a folder that can't be saved, such as an empty name, a focal point on a document or a
 * folder moved into itself. Its message says why
 */
final class InvalidMediaChangeException extends \InvalidArgumentException
{
}
