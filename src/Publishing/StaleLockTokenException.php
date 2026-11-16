<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * A save carries a lock token the server can't merge onto: one it never gave, or one so old that the saves since have
 * been deleted. The builder has to load the entry again
 */
final class StaleLockTokenException extends \RuntimeException
{
}
