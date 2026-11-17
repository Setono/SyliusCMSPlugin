<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownLinkTargetException extends \InvalidArgumentException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('There is no link target with the code "%s"', $code));
    }
}
