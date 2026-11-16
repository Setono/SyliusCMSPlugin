<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

/**
 * How bad a problem is. See "Problems" in docs/spec/admin.md
 */
enum Severity: string
{
    /** Something broken, such as a broken reference or a failed scheduled publish */
    case Error = 'error';

    /** Something to look at, such as a block type that was removed from code */
    case Warning = 'warning';
}
