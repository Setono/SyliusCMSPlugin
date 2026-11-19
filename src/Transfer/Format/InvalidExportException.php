<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

/**
 * What an import reads isn't an export it can read: a folder or zip without a manifest, a format it doesn't know, or a
 * file that isn't in the export format. setono:cms:import exits with 2 (Command::INVALID). See "Commands" in
 * docs/spec/transfer.md
 */
final class InvalidExportException extends \RuntimeException
{
}
