<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * The kinds of record an import plans, in the order its plan lists them. The plan, its stored result, setono:cms:import
 * and the admin go through the kinds, so a kind is added here, with its translations
 */
enum RecordKind: string
{
    case Entry = 'entry';
    case Preset = 'preset';
    case MediaItem = 'media_item';
    case MediaFolder = 'media_folder';

    /**
     * Its records' name in the admin's import plan: its translation keys, such as setono_sylius_cms.ui.import.media_items
     * and setono_sylius_cms.ui.import.count.media_items, and its HTML attributes, such as data-cms-import-media-items
     */
    public function plural(): string
    {
        return match ($this) {
            self::Entry => 'entries',
            self::Preset => 'presets',
            self::MediaItem => 'media_items',
            self::MediaFolder => 'media_folders',
        };
    }

    /**
     * What setono:cms:import calls one of its records and more than one, in English, as the command prints in English
     *
     * @return array{string, string}
     */
    public function nouns(): array
    {
        return match ($this) {
            self::Entry => ['entry', 'entries'],
            self::Preset => ['editor preset', 'editor presets'],
            self::MediaItem => ['media item', 'media items'],
            self::MediaFolder => ['media folder', 'media folders'],
        };
    }
}
