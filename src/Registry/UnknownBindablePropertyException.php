<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownBindablePropertyException extends \InvalidArgumentException
{
    public function __construct(string $binding)
    {
        parent::__construct(sprintf('There is no bindable property "%s"', $binding));
    }
}
