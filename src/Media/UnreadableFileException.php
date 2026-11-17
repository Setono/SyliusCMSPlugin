<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * A file's content isn't what its MIME type says, or is cut short, so it can't be checked, processed or stored
 */
final class UnreadableFileException extends \RuntimeException
{
}
