<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * Publish refused the entry, because validation found errors in it
 */
final class InvalidEntryException extends \RuntimeException
{
    /**
     * @param non-empty-list<ValidationError> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation found errors in the entry, so it can\'t be published');
    }
}
