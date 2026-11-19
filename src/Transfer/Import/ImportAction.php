<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What an import does with a record of the export. See "What an import does" in docs/spec/transfer.md
 */
enum ImportAction: string
{
    /** The target lacks it, so it's created, whether it's picked or came along */
    case Create = 'create';

    /** A picked record the target has differently: its content and on-save settings are written */
    case Update = 'update';

    /** The target has it identical, so it's skipped: no draft and no revision */
    case Unchanged = 'unchanged';

    /** A record that only came along, which the target has: it's left as the target has it */
    case LeaveAlone = 'left_alone';
}
