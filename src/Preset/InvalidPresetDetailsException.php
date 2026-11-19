<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * A preset's name, category or description that an editor can't save, such as an empty name
 */
final class InvalidPresetDetailsException extends InvalidPresetRequestException
{
}
