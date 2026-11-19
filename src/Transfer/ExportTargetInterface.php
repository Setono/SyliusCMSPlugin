<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * Where an export's files are written: a folder, or a zip holding the same folder at its root. Paths are relative to
 * the export's root, such as entries/<uuid>.json, as Format\ExportFormat names them
 */
interface ExportTargetInterface
{
    public function write(string $path, string $contents): void;

    /**
     * @param resource $stream read to its end; the caller closes it
     */
    public function writeStream(string $path, $stream): void;
}
