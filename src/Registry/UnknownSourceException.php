<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownSourceException extends \InvalidArgumentException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('There is no source with the code "%s"', $code));
    }
}
