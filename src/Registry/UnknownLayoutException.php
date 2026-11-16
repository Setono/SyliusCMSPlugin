<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownLayoutException extends \InvalidArgumentException
{
    /**
     * @param string|null $code null when the content type has no layouts at all
     */
    public function __construct(string $contentType, ?string $code = null)
    {
        parent::__construct(null === $code
            ? sprintf('The content type "%s" has no layouts', $contentType)
            : sprintf('The content type "%s" has no layout with the code "%s"', $contentType, $code));
    }
}
