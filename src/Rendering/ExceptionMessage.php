<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Twig\Error\RuntimeError;

/**
 * The message of what went wrong while something rendered. Twig wraps an exception a template threw, so that's
 * unwrapped
 *
 * @internal
 */
final class ExceptionMessage
{
    private function __construct()
    {
    }

    public static function of(\Throwable $exception): string
    {
        while ($exception instanceof RuntimeError && null !== $exception->getPrevious()) {
            $exception = $exception->getPrevious();
        }

        return $exception->getMessage();
    }
}
