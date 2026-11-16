<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownConditionKindException extends \InvalidArgumentException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('There is no condition kind with the code "%s"', $code));
    }
}
