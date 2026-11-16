<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A problem's message, for tests that count or order problems and never show what they say
 */
final class AnyMessage implements TranslatableInterface
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return 'Something is wrong';
    }
}
