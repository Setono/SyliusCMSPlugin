<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

/**
 * A revision has no page to preview in the channel and locale asked for. The message says why
 */
final class RevisionPreviewUnavailableException extends \RuntimeException
{
}
