<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * The kinds of record an import plans
 */
enum RecordKind: string
{
    case Entry = 'entry';
    case Preset = 'preset';
    case MediaItem = 'media_item';
    case MediaFolder = 'media_folder';
}
