<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * The kinds of record an import plans. Media items (#280) join them
 */
enum RecordKind: string
{
    case Entry = 'entry';

    case Preset = 'preset';
}
