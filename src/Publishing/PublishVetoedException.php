<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * A BeforeEntryPublish listener vetoed the publish. The message is the listener's, for the builder or admin to show
 */
final class PublishVetoedException extends \RuntimeException
{
}
