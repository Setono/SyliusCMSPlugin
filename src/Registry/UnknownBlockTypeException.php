<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownBlockTypeException extends \InvalidArgumentException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('There is no block type with the code "%s"', $code));
    }
}
