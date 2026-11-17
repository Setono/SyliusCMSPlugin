<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * A BeforeMediaItemDelete listener vetoed the delete. The message is the listener's, for the admin to show
 */
final class MediaItemDeleteVetoedException extends \RuntimeException
{
}
