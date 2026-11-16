<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * A BeforeEntryDelete listener vetoed the delete. The message is the listener's, for the admin to show
 */
final class DeleteVetoedException extends \RuntimeException
{
}
