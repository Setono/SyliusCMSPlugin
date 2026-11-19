<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

/**
 * An export's manifest.json: the format's version, the plugin version that wrote it, when, and the UUIDs of the picked
 * records. Every other record in the export is a dependency. See "manifest.json" in docs/spec/transfer.md
 */
final readonly class Manifest
{
    /**
     * @param string $plugin the plugin version that wrote the export, for information
     * @param \DateTimeImmutable $exportedAt when the export was written
     * @param list<string> $entries the UUIDs of the picked entries
     * @param list<string> $presets the UUIDs of the picked editor presets
     * @param list<string> $media the UUIDs of the picked media items
     * @param int $format the export format's version
     */
    public function __construct(
        public string $plugin,
        public \DateTimeImmutable $exportedAt,
        public array $entries = [],
        public array $presets = [],
        public array $media = [],
        public int $format = ExportFormat::VERSION,
    ) {
    }
}
