<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Exception;

use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;
use Twig\Error\LoaderError;

/**
 * This exception is thrown when an element is not found in the database
 *
 * @internal
 */
final class NonExistingElementException extends LoaderError
{
    public static function fromLogicalTemplateName(LogicalTemplateName $logicalTemplateName): self
    {
        return new self(
            sprintf(
                'Element with type "%s" and code "%s" does not exist',
                $logicalTemplateName->type,
                $logicalTemplateName->code,
            ),
        );
    }
}
