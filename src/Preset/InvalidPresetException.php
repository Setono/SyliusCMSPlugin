<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * A preset that isn't in the preset file format. See "Preset file format" in docs/spec/layouts-and-presets.md
 */
final class InvalidPresetException extends \RuntimeException
{
}
