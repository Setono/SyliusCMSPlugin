<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset's name, category or description that an editor can't save, such as an empty name
 */
final class InvalidPresetDetailsException extends \InvalidArgumentException
{
    public function __construct(public readonly TranslatableMessage $translatable)
    {
        parent::__construct($translatable->getMessage());
    }
}
