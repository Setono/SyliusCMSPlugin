<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Opens an export folder or zip, as setono:cms:import takes either
 */
final class ExportSources
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidExportException when the path is neither a folder nor a zip that can be read, or holds no manifest
     */
    public static function open(string $path): ExportSourceInterface
    {
        if (is_dir($path)) {
            $source = new DirectorySource($path);
        } elseif (is_file($path) && is_readable($path)) {
            $source = new ZipSource($path);
        } else {
            throw new InvalidExportException(sprintf('%s is neither an export folder nor a zip', $path));
        }

        if (!$source->has(ExportFormat::MANIFEST)) {
            throw new InvalidExportException(sprintf('%s has no %s, so it isn\'t an export', $path, ExportFormat::MANIFEST));
        }

        return $source;
    }
}
