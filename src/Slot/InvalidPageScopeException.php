<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A page scope that can't be saved for its placement, with what's wrong with it
 */
final class InvalidPageScopeException extends \InvalidArgumentException
{
    /**
     * @param list<TranslatableMessage> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(sprintf('The page scope has %d errors', count($errors)));
    }
}
