<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * A request to save or replace an editor preset that can't be answered, with the reason the builder shows the editor,
 * translated in the admin user's language (ADR 0008)
 */
class InvalidPresetRequestException extends \InvalidArgumentException
{
    public function __construct(public readonly TranslatableMessage $translatable)
    {
        parent::__construct($translatable->getMessage());
    }
}
