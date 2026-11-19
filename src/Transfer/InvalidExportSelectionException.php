<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * A selection names a record or a content type there's none of. Its message says which, for the operator
 */
final class InvalidExportSelectionException extends \InvalidArgumentException
{
}
