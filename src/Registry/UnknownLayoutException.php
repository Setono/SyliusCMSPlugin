<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownLayoutException extends \InvalidArgumentException
{
    public function __construct(string $contentType, string $code)
    {
        parent::__construct(sprintf('The content type "%s" has no layout with the code "%s"', $contentType, $code));
    }
}
