<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * Data that isn't on-save settings, such as a request body. The message says what's wrong, for the editor
 */
final class InvalidEntrySettingsException extends \InvalidArgumentException
{
}
